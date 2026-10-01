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

use core_question\local\bank\navigation_node_base;
use moodle_url;

/**
 * Question bank navigation node.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class navigation extends navigation_node_base {
    /**
     * Method get_navigation_title.
     *
     * @return string Return value.
     */
    public function get_navigation_title(): string {
        return get_string('pluginname', 'qbank_duplicate');
    }

    /**
     * Method get_navigation_key.
     *
     * @return string Return value.
     */
    public function get_navigation_key(): string {
        return 'duplicate';
    }

    /**
     * Method get_navigation_url.
     *
     * @return moodle_url Return value.
     */
    public function get_navigation_url(): moodle_url {
        return new moodle_url('/question/bank/duplicate/index.php');
    }

    /**
     * Method get_navigation_capabilities.
     *
     * @return ?array Return value.
     */
    public function get_navigation_capabilities(): ?array {
        return ['qbank/duplicate:view'];
    }
}
