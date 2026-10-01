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

namespace qbank_duplicate\ai;

use moodle_exception;

/**
 * Strict parser for qbankduplicate-compare output.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class response_parser {
    /** @var string[] */
    public const CLASSIFICATIONS = [
        'same_question',
        'strongly_overlapping',
        'related_but_distinct',
        'unrelated',
    ];

    /** @var string[] Exact keys accepted in the AI response object. */
    private const KEYS = ['classification', 'confidence', 'reason', 'evidence'];

    /**
     * Parse and validate strict JSON.
     *
     * @return array{classification:string,confidence:float,reason:string,evidence:array}
     */
    public static function parse(string $text): array {
        $data = json_decode(trim($text), true);
        if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE || array_is_list($data)) {
            throw new moodle_exception('error:invalidairesponse', 'qbank_duplicate');
        }
        $keys = array_keys($data);
        sort($keys, SORT_STRING);
        $expected = self::KEYS;
        sort($expected, SORT_STRING);
        if ($keys !== $expected) {
            throw new moodle_exception('error:invalidairesponse', 'qbank_duplicate');
        }
        if (!is_string($data['classification']) ||
            !in_array($data['classification'], self::CLASSIFICATIONS, true)) {
            throw new moodle_exception('error:invalidairesponse', 'qbank_duplicate');
        }
        if (!is_int($data['confidence']) && !is_float($data['confidence'])) {
            throw new moodle_exception('error:invalidairesponse', 'qbank_duplicate');
        }
        $confidence = (float)$data['confidence'];
        if ($confidence < 0.0 || $confidence > 1.0) {
            throw new moodle_exception('error:invalidairesponse', 'qbank_duplicate');
        }
        if (!is_string($data['reason']) || trim($data['reason']) === '') {
            throw new moodle_exception('error:invalidairesponse', 'qbank_duplicate');
        }
        $evidence = $data['evidence'];
        if (!is_array($evidence) || !array_is_list($evidence) || count($evidence) > 8) {
            throw new moodle_exception('error:invalidairesponse', 'qbank_duplicate');
        }
        $clean = [];
        foreach ($evidence as $item) {
            if (!is_string($item)) {
                throw new moodle_exception('error:invalidairesponse', 'qbank_duplicate');
            }
            $item = trim($item);
            if ($item !== '') {
                $clean[] = $item;
            }
        }
        return [
            'classification' => $data['classification'],
            'confidence' => $confidence,
            'reason' => trim($data['reason']),
            'evidence' => $clean,
        ];
    }
}
