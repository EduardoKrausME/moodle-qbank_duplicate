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
 * Settings for qbank_duplicate.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'qbank_duplicate/maxbucket',
        get_string('setting:maxbucket', 'qbank_duplicate'),
        get_string('setting:maxbucket_desc', 'qbank_duplicate'),
        120,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'qbank_duplicate/maxcandidatesperquestion',
        get_string('setting:maxcandidatesperquestion', 'qbank_duplicate'),
        get_string('setting:maxcandidatesperquestion_desc', 'qbank_duplicate'),
        30,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'qbank_duplicate/maxaipairs',
        get_string('setting:maxaipairs', 'qbank_duplicate'),
        get_string('setting:maxaipairs_desc', 'qbank_duplicate'),
        5000,
        PARAM_INT
    ));
    $settings->add(new admin_setting_configtext(
        'qbank_duplicate/comparebatchsize',
        get_string('setting:comparebatchsize', 'qbank_duplicate'),
        get_string('setting:comparebatchsize_desc', 'qbank_duplicate'),
        20,
        PARAM_INT
    ));
}
