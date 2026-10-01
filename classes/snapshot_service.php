<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace qbank_duplicate;

use core_question\local\bank\question_version_status;
use stdClass;

/**
 * Builds and caches normalized snapshots of the latest visible question versions.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot_service {
    private const BATCH_SIZE = 250;

    /**
     * Synchronize one question category with cached snapshots.
     *
     * @param stdClass $category Question category record.
     * @param int $scanid Scan id.
     * @param callable|null $progress Called with number processed and total.
     * @return int Number of visible latest questions.
     */
    public function sync_category(stdClass $category, int $scanid, ?callable $progress = null): int {
        global $DB;

        $hidden = question_version_status::QUESTION_STATUS_HIDDEN;
        $countsql = "SELECT COUNT(1)
                       FROM {question_bank_entries} qbe
                       JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                  LEFT JOIN {question_versions} newer
                         ON newer.questionbankentryid = qv.questionbankentryid
                        AND newer.version > qv.version
                        AND newer.status <> :hiddennewer
                      WHERE qbe.questioncategoryid = :categoryid
                        AND qv.status <> :hiddencurrent
                        AND newer.id IS NULL";
        $params = [
            'hiddennewer' => $hidden,
            'hiddencurrent' => $hidden,
            'categoryid' => (int)$category->id,
        ];
        $total = (int)$DB->count_records_sql($countsql, $params);
        $processed = 0;
        $offset = 0;

        while ($offset < $total) {
            $sql = "SELECT qbe.id AS questionbankentryid,
                           q.id AS questionid,
                           qv.id AS versionid,
                           qv.version AS versionno,
                           q.qtype,
                           q.name,
                           q.questiontext,
                           q.generalfeedback,
                           q.timemodified
                      FROM {question_bank_entries} qbe
                      JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
                      JOIN {question} q ON q.id = qv.questionid
                 LEFT JOIN {question_versions} newer
                        ON newer.questionbankentryid = qv.questionbankentryid
                       AND newer.version > qv.version
                       AND newer.status <> :hiddennewer
                     WHERE qbe.questioncategoryid = :categoryid
                       AND qv.status <> :hiddencurrent
                       AND newer.id IS NULL
                  ORDER BY qbe.id";
            $questions = $DB->get_records_sql($sql, $params, $offset, self::BATCH_SIZE);
            if (!$questions) {
                break;
            }
            $questionids = array_map(static fn(stdClass $q): int => (int)$q->questionid, $questions);
            $answersbyquestion = $this->load_answers($questionids);
            foreach ($questions as $question) {
                $answers = $answersbyquestion[(int)$question->questionid] ?? [];
                $this->sync_one($category, $question, $answers, $scanid);
                $processed++;
                if ($progress && ($processed % 25 === 0 || $processed === $total)) {
                    $progress($processed, $total);
                }
            }
            $offset += count($questions);
        }

        $stale = $DB->get_records_select('qbank_duplicate_snapshot',
            'categoryid = :categoryid AND lastscanid <> :scanid',
            ['categoryid' => (int)$category->id, 'scanid' => $scanid], '', 'id,questionbankentryid');
        foreach ($stale as $record) {
            pair_repository::invalidate_for_entry((int)$record->questionbankentryid);
            $DB->delete_records('qbank_duplicate_bucket', ['snapshotid' => (int)$record->id]);
            $DB->delete_records('qbank_duplicate_snapshot', ['id' => (int)$record->id]);
        }

        return $total;
    }

    /**
     * Fetch generic question_answers for a set of question ids.
     *
     * @return array<int,array<int,array{answer:string,fraction:float}>>
     */
    private function load_answers(array $questionids): array {
        global $DB;
        if (!$questionids) {
            return [];
        }
        [$insql, $inparams] = $DB->get_in_or_equal($questionids, SQL_PARAMS_NAMED, 'qid');
        $records = $DB->get_records_select('question_answers', "question $insql", $inparams, 'question,id',
            'id,question,answer,fraction');
        $result = [];
        foreach ($records as $answer) {
            $result[(int)$answer->question][] = [
                'answer' => (string)$answer->answer,
                'fraction' => (float)$answer->fraction,
            ];
        }
        return $result;
    }

    /**
     * Synchronize one snapshot and its buckets.
     */
    private function sync_one(stdClass $category, stdClass $question, array $answers, int $scanid): void {
        global $DB;

        $semantictext = normalizer::plain((string)$question->questiontext);
        $normalized = normalizer::text((string)$question->questiontext);
        $answerparts = [];
        $correctanswers = [];
        $answerpayload = [];
        foreach ($answers as $answer) {
            $normalizedanswer = normalizer::answer($answer['answer']);
            $answerparts[] = sprintf('%.7F:%s', $answer['fraction'], $normalizedanswer);
            if ((float)$answer['fraction'] > 0.0 && $normalizedanswer !== '') {
                $correctanswers[] = $normalizedanswer;
            }
            $answerpayload[] = [
                'fraction' => (float)$answer['fraction'],
                'text' => normalizer::plain($answer['answer']),
            ];
        }
        sort($answerparts, SORT_STRING);
        $correctanswers = array_values(array_unique($correctanswers));
        sort($correctanswers, SORT_STRING);
        usort($answerpayload, static function (array $a, array $b): int {
            return $b['fraction'] <=> $a['fraction'] ?: strcmp($a['text'], $b['text']);
        });
        $answerhash = $correctanswers ? hash('sha256', implode('|', $correctanswers)) : null;
        $answersummary = json_encode($answerpayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $sourcehash = hash('sha256', implode("\n", [
            (string)$question->qtype,
            (string)$question->name,
            (string)$question->questiontext,
            (string)$question->generalfeedback,
            implode('|', $answerparts),
        ]));
        $texthash = hash('sha256', $normalized);
        $keywords = normalizer::keywords($normalized);
        $signatures = minhash::signatures(normalizer::tokens($normalized));

        $snapshot = $DB->get_record('qbank_duplicate_snapshot',
            ['questionbankentryid' => (int)$question->questionbankentryid]);
        $changed = !$snapshot || !hash_equals((string)$snapshot->sourcehash, $sourcehash);
        $now = time();
        if (!$snapshot) {
            $snapshot = (object)[
                'questionbankentryid' => (int)$question->questionbankentryid,
                'timecreated' => $now,
            ];
        }
        $snapshot->questionid = (int)$question->questionid;
        $snapshot->versionid = (int)$question->versionid;
        $snapshot->versionno = (int)$question->versionno;
        $snapshot->categoryid = (int)$category->id;
        $snapshot->contextid = (int)$category->contextid;
        $snapshot->qtype = (string)$question->qtype;
        $snapshot->name = (string)$question->name;
        $snapshot->sourcehash = $sourcehash;
        $snapshot->texthash = $texthash;
        $snapshot->answerhash = $answerhash;
        $snapshot->answersummary = $answersummary;
        $snapshot->semantictext = $semantictext;
        $snapshot->normalizedtext = $normalized;
        $snapshot->keywords = json_encode($keywords, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $snapshot->minhash = json_encode($signatures);
        $snapshot->lastscanid = $scanid;
        $snapshot->timemodified = $now;

        if (empty($snapshot->id)) {
            $snapshot->id = $DB->insert_record('qbank_duplicate_snapshot', $snapshot);
            $changed = true;
        } else {
            $DB->update_record('qbank_duplicate_snapshot', $snapshot);
        }

        if ($changed) {
            pair_repository::invalidate_for_entry((int)$question->questionbankentryid);
            $this->rebuild_buckets($snapshot, $keywords, $signatures);
        } else if (!$DB->record_exists('qbank_duplicate_bucket', ['snapshotid' => (int)$snapshot->id])) {
            $this->rebuild_buckets($snapshot, $keywords, $signatures);
        }
    }

    /**
     * Rebuild cheap candidate-generation buckets.
     */
    private function rebuild_buckets(stdClass $snapshot, array $keywords, array $signatures): void {
        global $DB;
        $DB->delete_records('qbank_duplicate_bucket', ['snapshotid' => (int)$snapshot->id]);

        $keys = [];
        if ((string)$snapshot->normalizedtext !== '') {
            $keys[] = 'tx:' . substr((string)$snapshot->texthash, 0, 40);
        }
        if (!empty($snapshot->answerhash)) {
            $keys[] = 'an:' . substr(hash('sha256', $snapshot->qtype . ':' . $snapshot->answerhash), 0, 40);
        }
        foreach (array_slice($keywords, 0, 6) as $keyword) {
            $keys[] = 'kw:' . substr(hash('sha256', $keyword), 0, 40);
        }
        $keys = array_merge($keys, minhash::buckets($signatures));
        $keys = array_values(array_unique($keys));
        foreach ($keys as $key) {
            $DB->insert_record('qbank_duplicate_bucket', (object)[
                'snapshotid' => (int)$snapshot->id,
                'categoryid' => (int)$snapshot->categoryid,
                'bucketkey' => $key,
            ], false, true);
        }
    }
}
