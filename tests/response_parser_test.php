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
use moodle_exception;
use qbank_duplicate\ai\response_parser;

/**
 * Tests for strict AI JSON parsing.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class response_parser_test extends advanced_testcase {
    /**
     * Method test_valid_response.
     *
     * @return void Return value.
     */
    public function test_valid_response(): void {
        $result = response_parser::parse(json_encode([
            'classification' => 'same_question',
            'confidence' => 0.97,
            'reason' => 'Both ask for the same fact.',
            'evidence' => ['Same concept', 'Equivalent expected answer'],
        ]));

        $this->assertSame('same_question', $result['classification']);
        $this->assertSame(0.97, $result['confidence']);
        $this->assertCount(2, $result['evidence']);
    }

    /**
     * Method test_markdown_fence_is_rejected.
     *
     * @return void Return value.
     */
    public function test_markdown_fence_is_rejected(): void {
        $this->expectException(moodle_exception::class);
        $json = '{"classification":"unrelated","confidence":0.4,' .
            '"reason":"Different task.","evidence":[]}';
        response_parser::parse("```json\n{$json}\n```");
    }

    /**
     * Method test_unexpected_key_is_rejected.
     *
     * @return void Return value.
     */
    public function test_unexpected_key_is_rejected(): void {
        $this->expectException(moodle_exception::class);
        response_parser::parse('{"classification":"unrelated","confidence":0.4,' .
            '"reason":"Different.","evidence":[],"extra":true}');
    }

    /**
     * Method test_invalid_classification_is_rejected.
     *
     * @return void Return value.
     */
    public function test_invalid_classification_is_rejected(): void {
        $this->expectException(moodle_exception::class);
        response_parser::parse('{"classification":"duplicate-ish","confidence":1,"reason":"x","evidence":[]}');
    }
}
