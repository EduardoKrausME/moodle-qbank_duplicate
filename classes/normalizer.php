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

use core_text;

/**
 * Text normalization helpers used before candidate generation.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class normalizer {
    /** @var array Common words that add little candidate-selection value. */
    private const STOPWORDS = [
        'a', 'o', 'as', 'os', 'de', 'da', 'do', 'das', 'dos', 'e', 'em', 'um', 'uma', 'para', 'por', 'com', 'que',
        'qual', 'quais', 'como', 'na', 'no', 'nas', 'nos', 'ao', 'aos', 'se', 'ser', 'the', 'a', 'an', 'of', 'and',
        'or', 'to', 'in', 'on', 'for', 'with', 'which', 'what', 'how', 'is', 'are', 'be', 'this', 'that', 'el', 'la',
        'los', 'las', 'de', 'y', 'o', 'en', 'un', 'una', 'para', 'por', 'con', 'que', 'cual', 'como', 'es', 'son',
    ];

    /**
     * Convert editor HTML to compact plain text while preserving semantic case and punctuation.
     */
    public static function plain(string $html): string {
        $text = preg_replace('/<[^>]*>/u', ' ', $html) ?? $html;
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace(["\u{00A0}", "\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"], ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * Normalize a question text for hashes and lexical comparisons.
     */
    public static function text(string $html): string {
        $text = core_text::strtolower(self::plain($html));
        $text = preg_replace('/[^\p{L}\p{N}\p{M}\+\-\/=<>%\.]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        return trim($text);
    }

    /**
     * Normalize answer text while keeping fractions separate in the answer signature.
     */
    public static function answer(string $html): string {
        return self::text($html);
    }

    /**
     * Return meaningful lexical tokens.
     *
     * @return string[]
     */
    public static function tokens(string $normalized): array {
        if ($normalized === '') {
            return [];
        }
        $parts = preg_split('/\s+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $stop = array_flip(self::STOPWORDS);
        $tokens = [];
        foreach ($parts as $token) {
            if (isset($stop[$token])) {
                continue;
            }
            if (core_text::strlen($token) < 2 && !ctype_digit($token)) {
                continue;
            }
            $tokens[] = $token;
        }
        return $tokens;
    }

    /**
     * Pick stable keywords by frequency, then length and lexical order.
     *
     * @return string[]
     */
    public static function keywords(string $normalized, int $limit = 8): array {
        $tokens = self::tokens($normalized);
        $counts = array_count_values($tokens);
        $items = [];
        foreach ($counts as $token => $count) {
            $items[] = ['token' => $token, 'count' => $count, 'length' => core_text::strlen($token)];
        }
        usort($items, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'] ?: $b['length'] <=> $a['length'] ?: strcmp($a['token'], $b['token']);
        });
        return array_column(array_slice($items, 0, $limit), 'token');
    }

    /**
     * Jaccard similarity over unique significant tokens.
     */
    public static function jaccard(string $a, string $b): float {
        $ta = array_values(array_unique(self::tokens($a)));
        $tb = array_values(array_unique(self::tokens($b)));
        if (!$ta || !$tb) {
            return 0.0;
        }
        $sa = array_flip($ta);
        $sb = array_flip($tb);
        $intersection = count(array_intersect_key($sa, $sb));
        $union = count($sa + $sb);
        return $union ? $intersection / $union : 0.0;
    }
}
