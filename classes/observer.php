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

use core\event\base;

/**
 * Invalidates cached duplicate decisions when question content or placement changes.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class observer {
    /**
     * Invalidate any cached snapshot and non-ignored comparisons for the affected bank entry.
     *
     * @param base $event Question lifecycle event.
     */
    public static function question_changed(base $event): void {
        global $DB;

        $questionid = (int)$event->objectid;
        if (!$questionid) {
            return;
        }

        $entryid = $DB->get_field('question_versions', 'questionbankentryid', ['questionid' => $questionid]);
        if (!$entryid) {
            // A delete event can be dispatched after the core version record is gone. The plugin
            // snapshot still gives us the bank entry for the version that had been analysed.
            $entryid = $DB->get_field('qbank_duplicate_snapshot', 'questionbankentryid',
                ['questionid' => $questionid]);
        }
        if (!$entryid) {
            return;
        }

        pair_repository::invalidate_for_entry((int)$entryid);
        $snapshot = $DB->get_record('qbank_duplicate_snapshot', ['questionbankentryid' => (int)$entryid], 'id');
        if ($snapshot) {
            $DB->delete_records('qbank_duplicate_bucket', ['snapshotid' => (int)$snapshot->id]);
            $DB->delete_records('qbank_duplicate_snapshot', ['id' => (int)$snapshot->id]);
        }
    }
}
