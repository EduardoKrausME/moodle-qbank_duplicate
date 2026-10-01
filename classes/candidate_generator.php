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

use stdClass;

/**
 * Generates a bounded candidate set without constructing all N² pairs.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class candidate_generator {
    /**
     * Generate candidate pairs for one category.
     *
     * @return array{active:int,reused:int,suppressed:int,newpending:int}
     */
    public function generate(int $categoryid, int $scanid): array {
        global $DB;

        $maxbucket = max(10, (int)(get_config('qbank_duplicate', 'maxbucket') ?: 120));
        $maxperquestion = max(5, (int)(get_config('qbank_duplicate', 'maxcandidatesperquestion') ?: 30));
        $maxnew = max(1, (int)(get_config('qbank_duplicate', 'maxaipairs') ?: 5000));
        $counts = [];
        $seen = [];
        $newpending = 0;
        $stats = ['active' => 0, 'reused' => 0, 'suppressed' => 0, 'newpending' => 0];

        // Exact normalized-text duplicates must not depend on LSH/keyword bucket limits.
        $exactsql = "SELECT texthash, COUNT(1) AS snapshotcount
                       FROM {qbank_duplicate_snapshot}
                      WHERE categoryid = :categoryid
                        AND texthash <> :emptyhash
                   GROUP BY texthash
                     HAVING COUNT(1) > 1
                   ORDER BY texthash";
        $exactclusters = $DB->get_recordset_sql($exactsql, [
            'categoryid' => $categoryid,
            'emptyhash' => hash('sha256', ''),
        ]);
        foreach ($exactclusters as $cluster) {
            $snapshots = array_values($DB->get_records(
                'qbank_duplicate_snapshot',
                ['categoryid' => $categoryid, 'texthash' => (string)$cluster->texthash],
                'answerhash ASC, questionbankentryid ASC'
            ));
            for ($i = 1; $i < count($snapshots); $i++) {
                $this->consider(
                    $snapshots[$i - 1],
                    $snapshots[$i],
                    $categoryid,
                    $scanid,
                    $counts,
                    $seen,
                    $maxperquestion,
                    $newpending,
                    $maxnew,
                    $stats,
                    true
                );
            }
        }
        $exactclusters->close();

        $sql = "SELECT bucketkey, COUNT(1) AS bucketcount
                  FROM {qbank_duplicate_bucket}
                 WHERE categoryid = :categoryid
              GROUP BY bucketkey
                HAVING COUNT(1) > 1
              ORDER BY CASE
                           WHEN bucketkey LIKE 'tx:%' THEN 0
                           WHEN bucketkey LIKE 'an:%' THEN 1
                           WHEN bucketkey LIKE 'mh:%' THEN 2
                           ELSE 3
                       END, bucketkey";
        $recordset = $DB->get_recordset_sql($sql, ['categoryid' => $categoryid]);
        foreach ($recordset as $bucket) {
            $bucketkey = (string)$bucket->bucketkey;
            $bucketcount = (int)$bucket->bucketcount;
            if ($bucketcount > $maxbucket && !str_starts_with($bucketkey, 'tx:')) {
                continue;
            }
            $snapshots = $DB->get_records_sql(
                "SELECT s.*
                   FROM {qbank_duplicate_bucket} b
                   JOIN {qbank_duplicate_snapshot} s ON s.id = b.snapshotid
                  WHERE b.categoryid = :categoryid AND b.bucketkey = :bucketkey
               ORDER BY s.questionbankentryid",
                ['categoryid' => $categoryid, 'bucketkey' => $bucketkey]
            );
            $snapshots = array_values($snapshots);
            if (count($snapshots) < 2) {
                continue;
            }

            if (str_starts_with($bucketkey, 'tx:')) {
                // Exact-text clusters can be arbitrarily large. A connected chain represents
                // every member with N-1 pairs instead of N², and sorting by answer signature
                // keeps the strongest exact duplicates adjacent where possible.
                usort($snapshots, static function (stdClass $a, stdClass $b): int {
                    $answercompare = strcmp((string)$a->answerhash, (string)$b->answerhash);
                    return $answercompare ?: ((int)$a->questionbankentryid <=> (int)$b->questionbankentryid);
                });
                for ($i = 1; $i < count($snapshots); $i++) {
                    $this->consider($snapshots[$i - 1], $snapshots[$i], $categoryid, $scanid, $counts, $seen,
                        $maxperquestion, $newpending, $maxnew, $stats, true);
                }
                continue;
            }

            $count = count($snapshots);
            for ($i = 0; $i < $count - 1; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $this->consider($snapshots[$i], $snapshots[$j], $categoryid, $scanid, $counts, $seen,
                        $maxperquestion, $newpending, $maxnew, $stats, false);
                }
            }
        }
        $recordset->close();

        $stats['newpending'] = $newpending;
        return $stats;
    }

    /**
     * Score and persist one pair if it remains useful after cheap checks.
     */
    private function consider(
        stdClass $a,
        stdClass $b,
        int $categoryid,
        int $scanid,
        array &$counts,
        array &$seen,
        int $maxperquestion,
        int &$newpending,
        int $maxnew,
        array &$stats,
        bool $forceexact
    ): void {
        $ea = (int)$a->questionbankentryid;
        $eb = (int)$b->questionbankentryid;
        if ($ea === $eb) {
            return;
        }
        $low = min($ea, $eb);
        $high = max($ea, $eb);
        $key = $low . ':' . $high;
        if (isset($seen[$key])) {
            return;
        }
        $seen[$key] = true;

        if (($counts[$ea] ?? 0) >= $maxperquestion || ($counts[$eb] ?? 0) >= $maxperquestion) {
            return;
        }

        $exacttext = hash_equals((string)$a->texthash, (string)$b->texthash) && $a->normalizedtext !== '';
        $sameanswer = !empty($a->answerhash) && !empty($b->answerhash)
            && hash_equals((string)$a->answerhash, (string)$b->answerhash);
        $sameqtype = (string)$a->qtype === (string)$b->qtype;
        $jaccard = normalizer::jaccard((string)$a->normalizedtext, (string)$b->normalizedtext);
        $score = $exacttext ? 1.0 : min(1.0, ($jaccard * 0.80) + ($sameanswer ? 0.15 : 0.0) + ($sameqtype ? 0.05 : 0.0));
        // Matching answer signatures are a strong candidate-generation signal even when the
        // question was completely reworded. Oversized answer buckets are already capped, so
        // allowing this lower threshold does not turn the scan into an N² comparison.
        $threshold = ($sameqtype && $sameanswer) ? 0.20 : ($sameqtype ? 0.28 : 0.38);
        if (!$forceexact && !$exacttext && $score < $threshold) {
            return;
        }

        $heuristics = [
            'exact_normalized_text' => $exacttext,
            'same_answer_signature' => $sameanswer,
            'same_question_type' => $sameqtype,
            'token_jaccard' => round($jaccard, 5),
        ];
        $allownew = $newpending < $maxnew;
        $result = pair_repository::upsert_candidate($categoryid, $a, $b, $score, $heuristics, $scanid, $allownew);
        if ($result === 'active') {
            $counts[$ea] = ($counts[$ea] ?? 0) + 1;
            $counts[$eb] = ($counts[$eb] ?? 0) + 1;
            $newpending++;
            $stats['active']++;
        } else if ($result === 'reused') {
            $counts[$ea] = ($counts[$ea] ?? 0) + 1;
            $counts[$eb] = ($counts[$eb] ?? 0) + 1;
            $stats['reused']++;
        } else if (in_array($result, ['ignored', 'notduplicate'], true)) {
            $stats['suppressed']++;
        }
    }
}
