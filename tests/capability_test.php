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

use advanced_testcase;
use context_course;

/**
 * Tests access defaults for the report.
 *
 * @coversNothing
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class capability_test extends advanced_testcase {
    /**
     * Method test_editing_teacher_can_scan_but_student_cannot.
     *
     * @return void Return value.
     */
    public function test_editing_teacher_can_scan_but_student_cannot(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $context = context_course::instance($course->id);

        $this->assertTrue(has_capability('qbank/duplicate:view', $context, $teacher));
        $this->assertTrue(has_capability('qbank/duplicate:scan', $context, $teacher));
        $this->assertTrue(has_capability('qbank/duplicate:manage', $context, $teacher));
        $this->assertFalse(has_capability('qbank/duplicate:view', $context, $student));
        $this->assertFalse(has_capability('qbank/duplicate:scan', $context, $student));
    }
}
