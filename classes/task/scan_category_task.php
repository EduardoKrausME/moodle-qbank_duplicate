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

namespace qbank_duplicate\task;

use context;
use core\task\adhoc_task;
use core\task\manager;
use core_question\local\bank\question_edit_contexts;
use qbank_duplicate\candidate_generator;
use qbank_duplicate\scan_service;
use qbank_duplicate\snapshot_service;
use Throwable;

/**
 * Adhoc task that snapshots a category, generates candidates, and queues semantic comparison batches.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scan_category_task extends adhoc_task {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return get_string('task:scan', 'qbank_duplicate');
    }

    /**
     * Method execute.
     *
     * @return void Return value.
     */
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $scan = $DB->get_record('qbank_duplicate_scan', ['id' => (int)$data->scanid], '*', MUST_EXIST);

        try {
            $context = context::instance_by_id((int)$scan->contextid, MUST_EXIST);
            $questioncontexts = new question_edit_contexts($context);
            $questioncontexts->require_one_edit_tab_cap('questions');
            require_capability('qbank/duplicate:scan', $context);
            $category = $DB->get_record('question_categories',
                ['id' => (int)$scan->categoryid, 'contextid' => (int)$scan->contextid], '*', MUST_EXIST);

            $scan->status = 'running';
            $scan->phase = 'snapshot';
            $scan->timestarted = $scan->timestarted ?: time();
            $scan->timemodified = time();
            $DB->update_record('qbank_duplicate_scan', $scan);

            $snapshots = new snapshot_service();
            $total = $snapshots->sync_category($category, (int)$scan->id,
                static function (int $processed, int $total) use ($DB, $scan): void {
                    $DB->set_field('qbank_duplicate_scan', 'totalquestions', $total, ['id' => (int)$scan->id]);
                    $DB->set_field('qbank_duplicate_scan', 'processedquestions', $processed, ['id' => (int)$scan->id]);
                    $DB->set_field('qbank_duplicate_scan', 'timemodified', time(), ['id' => (int)$scan->id]);
                });
            $DB->set_field('qbank_duplicate_scan', 'totalquestions', $total, ['id' => (int)$scan->id]);
            $DB->set_field('qbank_duplicate_scan', 'processedquestions', $total, ['id' => (int)$scan->id]);
            $DB->set_field('qbank_duplicate_scan', 'phase', 'candidates', ['id' => (int)$scan->id]);

            $generator = new candidate_generator();
            $generator->generate((int)$category->id, (int)$scan->id);

            $DB->set_field('qbank_duplicate_scan', 'status', 'comparing', ['id' => (int)$scan->id]);
            $DB->set_field('qbank_duplicate_scan', 'phase', 'semantic', ['id' => (int)$scan->id]);
            scan_service::refresh_progress((int)$scan->id);

            $pending = $DB->get_records('qbank_duplicate_pair',
                ['lastscanid' => (int)$scan->id, 'state' => 'candidate'], 'id ASC', 'id');
            if (!$pending) {
                scan_service::refresh_progress((int)$scan->id);
                return;
            }
            $batchsize = max(1, min(100, (int)(get_config('qbank_duplicate', 'comparebatchsize') ?: 20)));
            $pairids = array_map('intval', array_keys($pending));
            foreach (array_chunk($pairids, $batchsize) as $chunk) {
                $task = new compare_batch_task();
                $task->set_custom_data(['scanid' => (int)$scan->id, 'pairids' => $chunk]);
                if ($this->get_userid()) {
                    $task->set_userid((int)$this->get_userid());
                }
                $task->set_attempts_available(2);
                manager::queue_adhoc_task($task);
            }
        } catch (Throwable $e) {
            $scan = $DB->get_record('qbank_duplicate_scan', ['id' => (int)$scan->id], '*', MUST_EXIST);
            $scan->status = 'failed';
            $scan->phase = 'failed';
            $scan->message = clean_param($e->getMessage(), PARAM_TEXT);
            $scan->timefinished = time();
            $scan->timemodified = time();
            $DB->update_record('qbank_duplicate_scan', $scan);
            throw $e;
        }
    }
}
