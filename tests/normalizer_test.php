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

/**
 * Tests for deterministic text preprocessing.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class normalizer_test extends advanced_testcase {
    /**
     * Method test_normalization_and_hash_are_stable.
     *
     * @return void Return value.
     */
    public function test_normalization_and_hash_are_stable(): void {
        $a = normalizer::text('<p>Hello&nbsp; WORLD!</p>');
        $b = normalizer::text(" hello   world ");

        $this->assertSame('hello world', $a);
        $this->assertSame($a, $b);
        $this->assertSame(hash('sha256', $a), hash('sha256', $b));
    }

    /**
     * Method test_keywords_remove_common_words.
     *
     * @return void Return value.
     */
    public function test_keywords_remove_common_words(): void {
        $keywords = normalizer::keywords(normalizer::text('Qual é o planeta vermelho e qual planeta aparece no céu?'));

        $this->assertContains('planeta', $keywords);
        $this->assertNotContains('qual', $keywords);
    }

    /**
     * Method test_minhash_is_deterministic.
     *
     * @return void Return value.
     */
    public function test_minhash_is_deterministic(): void {
        $tokens = normalizer::tokens('planeta vermelho marte sistema solar');
        $first = minhash::signatures($tokens);
        $second = minhash::signatures(array_reverse($tokens));

        $this->assertSame($first, $second);
        $this->assertCount(4, minhash::buckets($first));
    }
}
