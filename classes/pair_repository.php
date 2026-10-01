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

use coding_exception;
use stdClass;

/**
 * Persistence helpers for duplicate candidate pairs.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class pair_repository {
    /**
     * Invalidate semantic decisions when one question changes. Explicit ignores remain ignored.
     */
    public static function invalidate_for_entry(int $entryid): void {
        global $DB;
        $select = 'state <> :ignored AND (entrya = :entrya OR entryb = :entryb)';
        $DB->delete_records_select('qbank_duplicate_pair', $select, [
            'ignored' => 'ignored',
            'entrya' => $entryid,
            'entryb' => $entryid,
        ]);
    }

    /**
     * Persist or refresh a candidate pair.
     *
     * @return string Result state: active, ignored, notduplicate, reused, or budget.
     */
    public static function upsert_candidate(
        int       $categoryid,
        stdClass $a,
        stdClass $b,
        float     $score,
        array     $heuristics,
        int       $scanid,
        bool      $allownew = true
    ): string {
        global $DB;

        if ((int)$a->questionbankentryid > (int)$b->questionbankentryid) {
            [$a, $b] = [$b, $a];
        }
        $params = [
            'categoryid' => $categoryid,
            'entrya' => (int)$a->questionbankentryid,
            'entryb' => (int)$b->questionbankentryid,
        ];
        $existing = $DB->get_record('qbank_duplicate_pair', $params);
        if ($existing) {
            if ($existing->state === 'ignored') {
                return 'ignored';
            }
            $samehashes = hash_equals((string)$existing->hasha, (string)$a->sourcehash)
                && hash_equals((string)$existing->hashb, (string)$b->sourcehash);
            if ($existing->state === 'notduplicate' && $samehashes) {
                $existing->lastscanid = $scanid;
                $existing->heuristicscore = $score;
                $existing->heuristics = json_encode($heuristics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $existing->timemodified = time();
                $DB->update_record('qbank_duplicate_pair', $existing);
                return 'notduplicate';
            }
            if ($samehashes && in_array($existing->state, ['analysed'], true)) {
                $existing->lastscanid = $scanid;
                $existing->heuristicscore = $score;
                $existing->heuristics = json_encode($heuristics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                $existing->timemodified = time();
                $DB->update_record('qbank_duplicate_pair', $existing);
                return 'reused';
            }
            if (!$allownew) {
                return 'budget';
            }
            $existing->hasha = $a->sourcehash;
            $existing->hashb = $b->sourcehash;
            $existing->heuristicscore = $score;
            $existing->heuristics = json_encode($heuristics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $existing->classification = null;
            $existing->confidence = 0;
            $existing->reason = null;
            $existing->evidence = null;
            $existing->state = 'candidate';
            $existing->aierror = null;
            $existing->lastscanid = $scanid;
            $existing->timemodified = time();
            $DB->update_record('qbank_duplicate_pair', $existing);
            return 'active';
        }

        if (!$allownew) {
            return 'budget';
        }
        $now = time();
        $record = (object)[
            'categoryid' => $categoryid,
            'entrya' => (int)$a->questionbankentryid,
            'entryb' => (int)$b->questionbankentryid,
            'hasha' => $a->sourcehash,
            'hashb' => $b->sourcehash,
            'heuristicscore' => $score,
            'heuristics' => json_encode($heuristics, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'classification' => null,
            'confidence' => 0,
            'reason' => null,
            'evidence' => null,
            'state' => 'candidate',
            'aierror' => null,
            'lastscanid' => $scanid,
            'timecreated' => $now,
            'timemodified' => $now,
        ];
        $DB->insert_record('qbank_duplicate_pair', $record);
        return 'active';
    }

    /**
     * Apply a human decision.
     */
    public static function decide(int $pairid, string $decision): void {
        global $DB;
        if (!in_array($decision, ['notduplicate', 'ignored'], true)) {
            throw new coding_exception('Invalid duplicate pair decision.');
        }
        $pair = $DB->get_record('qbank_duplicate_pair', ['id' => $pairid], '*', MUST_EXIST);
        $pair->state = $decision;
        $pair->timemodified = time();
        $DB->update_record('qbank_duplicate_pair', $pair);
    }
}
