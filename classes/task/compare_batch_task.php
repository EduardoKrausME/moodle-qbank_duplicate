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
use core_question\local\bank\question_edit_contexts;
use qbank_duplicate\ai\comparator;
use qbank_duplicate\scan_service;
use Throwable;

/**
 * Adhoc task that semantically compares a bounded batch of candidate pairs.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class compare_batch_task extends adhoc_task {
    /**
     * Method get_name.
     *
     * @return string Return value.
     */
    public function get_name(): string {
        return get_string('task:compare', 'qbank_duplicate');
    }

    /**
     * Method execute.
     *
     * @return void Return value.
     */
    public function execute(): void {
        global $DB;
        $data = $this->get_custom_data();
        $scanid = (int)$data->scanid;
        $scan = $DB->get_record('qbank_duplicate_scan', ['id' => $scanid]);
        if (!$scan || !in_array($scan->status, ['comparing', 'running'], true)) {
            return;
        }

        try {
            $context = context::instance_by_id((int)$scan->contextid, MUST_EXIST);
            $questioncontexts = new question_edit_contexts($context);
            $questioncontexts->require_one_edit_tab_cap('questions');
            require_capability('qbank/duplicate:scan', $context);
            $comparator = new comparator();

            foreach ((array)$data->pairids as $pairid) {
                $pair = $DB->get_record('qbank_duplicate_pair', ['id' => (int)$pairid, 'lastscanid' => $scanid]);
                if (!$pair || $pair->state !== 'candidate') {
                    continue;
                }
                $a = $DB->get_record('qbank_duplicate_snapshot', ['questionbankentryid' => (int)$pair->entrya]);
                $b = $DB->get_record('qbank_duplicate_snapshot', ['questionbankentryid' => (int)$pair->entryb]);
                if (!$a || !$b || (int)$a->categoryid !== (int)$scan->categoryid ||
                    (int)$b->categoryid !== (int)$scan->categoryid) {
                    $pair->state = 'aierror';
                    $pair->aierror = get_string('error:stalepair', 'qbank_duplicate');
                    $pair->timemodified = time();
                    $DB->update_record('qbank_duplicate_pair', $pair);
                    $DB->execute('UPDATE {qbank_duplicate_scan}
                                     SET aierrors = aierrors + 1, timemodified = :now
                                   WHERE id = :id', ['now' => time(), 'id' => $scanid]);
                    continue;
                }
                try {
                    $result = $comparator->compare($pair, $a, $b);
                    $pair->classification = $result['classification'];
                    $pair->confidence = $result['confidence'];
                    $pair->reason = $result['reason'];
                    $pair->evidence = json_encode($result['evidence'],
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                    $pair->state = 'analysed';
                    $pair->aierror = null;
                    $pair->timemodified = time();
                    $DB->update_record('qbank_duplicate_pair', $pair);
                    $DB->execute('UPDATE {qbank_duplicate_scan}
                                     SET aicalls = aicalls + 1, timemodified = :now
                                   WHERE id = :id', ['now' => time(), 'id' => $scanid]);
                } catch (Throwable $e) {
                    $pair->state = 'aierror';
                    $pair->aierror = clean_param($e->getMessage(), PARAM_TEXT);
                    $pair->timemodified = time();
                    $DB->update_record('qbank_duplicate_pair', $pair);
                    $DB->execute('UPDATE {qbank_duplicate_scan}
                                     SET aicalls = aicalls + 1, aierrors = aierrors + 1, timemodified = :now
                                   WHERE id = :id', ['now' => time(), 'id' => $scanid]);
                }
            }
            scan_service::refresh_progress($scanid);
        } catch (Throwable $e) {
            $scan = $DB->get_record('qbank_duplicate_scan', ['id' => $scanid]);
            if ($scan && in_array($scan->status, ['queued', 'running', 'comparing'], true)) {
                $scan->status = 'failed';
                $scan->phase = 'failed';
                $scan->message = clean_param($e->getMessage(), PARAM_TEXT);
                $scan->timefinished = time();
                $scan->timemodified = time();
                $DB->update_record('qbank_duplicate_scan', $scan);
            }
            throw $e;
        }
    }
}
