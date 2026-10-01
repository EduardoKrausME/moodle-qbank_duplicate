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

use core\task\manager;
use qbank_duplicate\task\scan_category_task;
use stdClass;

/**
 * Queue and progress helpers for category scans.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scan_service {
    /**
     * Queue a scan, reusing an already active scan for the same category.
     */
    public static function queue(int $categoryid, int $contextid, int $userid): int {
        global $DB;
        $active = $DB->get_record_select('qbank_duplicate_scan',
            'categoryid = :categoryid AND status IN (:queued, :running, :comparing)',
            ['categoryid' => $categoryid, 'queued' => 'queued', 'running' => 'running', 'comparing' => 'comparing'],
            '*', IGNORE_MULTIPLE);
        if ($active) {
            return (int)$active->id;
        }
        $now = time();
        $scanid = $DB->insert_record('qbank_duplicate_scan', (object)[
            'categoryid' => $categoryid,
            'contextid' => $contextid,
            'status' => 'queued',
            'phase' => 'queued',
            'totalquestions' => 0,
            'processedquestions' => 0,
            'totalcandidates' => 0,
            'processedcandidates' => 0,
            'aicalls' => 0,
            'aierrors' => 0,
            'message' => null,
            'timecreated' => $now,
            'timestarted' => 0,
            'timefinished' => 0,
            'timemodified' => $now,
        ]);
        $task = new scan_category_task();
        $task->set_custom_data(['scanid' => $scanid]);
        $task->set_userid($userid);
        $task->set_attempts_available(2);
        manager::queue_adhoc_task($task, true);
        return $scanid;
    }

    /**
     * Return latest scan for category.
     */
    public static function latest(int $categoryid): ?stdClass {
        global $DB;
        $records = $DB->get_records('qbank_duplicate_scan', ['categoryid' => $categoryid], 'id DESC', '*', 0, 1);
        return $records ? reset($records) : null;
    }

    /**
     * Recalculate pair progress and finalize a scan if no candidates remain.
     */
    public static function refresh_progress(int $scanid): void {
        global $DB;
        $scan = $DB->get_record('qbank_duplicate_scan', ['id' => $scanid]);
        if (!$scan) {
            return;
        }
        $total = (int)$DB->count_records_select('qbank_duplicate_pair',
            'lastscanid = :scanid AND state NOT IN (:ignored, :notduplicate)',
            ['scanid' => $scanid, 'ignored' => 'ignored', 'notduplicate' => 'notduplicate']);
        $pending = (int)$DB->count_records('qbank_duplicate_pair', ['lastscanid' => $scanid, 'state' => 'candidate']);
        $scan->totalcandidates = $total;
        $scan->processedcandidates = max(0, $total - $pending);
        if ($scan->status === 'comparing' && $pending === 0) {
            $scan->status = ((int)$scan->aierrors > 0) ? 'completedwitherrors' : 'completed';
            $scan->phase = 'done';
            $scan->timefinished = time();
        }
        $scan->timemodified = time();
        $DB->update_record('qbank_duplicate_scan', $scan);
    }
}
