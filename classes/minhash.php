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

/**
 * Small deterministic MinHash implementation for LSH buckets.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class minhash {
    /** @var int */
    private const SIGNATURES = 12;

    /** @var int */
    private const BANDSIZE = 3;

    /**
     * Calculate MinHash signatures from unique tokens.
     *
     * @return int[]
     */
    public static function signatures(array $tokens): array {
        $tokens = array_values(array_unique($tokens));
        if (!$tokens) {
            return [];
        }
        $result = [];
        for ($seed = 0; $seed < self::SIGNATURES; $seed++) {
            $min = PHP_INT_MAX;
            foreach ($tokens as $token) {
                $hex = substr(hash('sha256', $seed . ':' . $token), 0, 8);
                $value = (int)hexdec($hex);
                if ($value < $min) {
                    $min = $value;
                }
            }
            $result[] = $min;
        }
        return $result;
    }

    /**
     * Convert signatures to band bucket keys.
     *
     * @return string[]
     */
    public static function buckets(array $signatures): array {
        $buckets = [];
        if (count($signatures) !== self::SIGNATURES) {
            return $buckets;
        }
        for ($offset = 0; $offset < self::SIGNATURES; $offset += self::BANDSIZE) {
            $band = array_slice($signatures, $offset, self::BANDSIZE);
            $buckets[] = 'mh:' . ($offset / self::BANDSIZE) . ':' . substr(hash('sha256', implode(':', $band)), 0, 32);
        }
        return $buckets;
    }
}
