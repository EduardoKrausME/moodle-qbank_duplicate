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

/**
 * Human decisions for candidate pairs.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');

use qbank_duplicate\pair_repository;
use qbank_duplicate\scan_service;

require_login();
core_question\local\bank\helper::require_plugin_enabled('qbank_duplicate');
require_sesskey();

$pairid = required_param('pairid', PARAM_INT);
$decision = required_param('decision', PARAM_ALPHA);
$returnurl = required_param('returnurl', PARAM_LOCALURL);

$pair = $DB->get_record('qbank_duplicate_pair', ['id' => $pairid], '*', MUST_EXIST);
$category = $DB->get_record('question_categories', ['id' => (int)$pair->categoryid], '*', MUST_EXIST);
$context = context::instance_by_id((int)$category->contextid, MUST_EXIST);
$questioncontexts = new core_question\local\bank\question_edit_contexts($context);
$questioncontexts->require_one_edit_tab_cap('questions');
require_capability('qbank/duplicate:manage', $context);

pair_repository::decide($pairid, $decision);
scan_service::refresh_progress((int)$pair->lastscanid);
redirect(new moodle_url($returnurl), get_string('decisionsaved', 'qbank_duplicate'));
