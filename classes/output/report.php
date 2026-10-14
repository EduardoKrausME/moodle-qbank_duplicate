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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace qbank_duplicate\output;

use renderable;
use renderer_base;
use stdClass;
use templatable;

/**
 * Separates the duplicate report's presentation context from its controller.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class report implements renderable, templatable {
    /**
     * Data prepared for the report template.
     *
     * @var stdClass
     */
    private readonly stdClass $data;

    /**
     * Initialise the duplicate report.
     *
     * @param stdClass $data Already validated and prepared report values.
     */
    public function __construct(stdClass $data) {
        $this->data = $data;
    }

    /**
     * Export simple values consumed by templates/report.mustache.
     *
     * @param renderer_base $output Moodle renderer.
     * @return stdClass Template context.
     */
    public function export_for_template(renderer_base $output): stdClass {
        return $this->data;
    }
}
