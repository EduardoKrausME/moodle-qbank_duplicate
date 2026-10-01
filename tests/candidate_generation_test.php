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
use stdClass;

/**
 * Tests local candidate generation and snapshot invalidation.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
final class candidate_generation_test extends advanced_testcase {
    /**
     * Method test_candidate_generation_reduces_to_plausible_pair.
     *
     * @return void Return value.
     */
    public function test_candidate_generation_reduces_to_plausible_pair(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('maxbucket', 120, 'qbank_duplicate');
        set_config('maxcandidatesperquestion', 30, 'qbank_duplicate');
        set_config('maxaipairs', 100, 'qbank_duplicate');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $generator->create_question('shortanswer', null, [
            'category' => $category->id,
            'questiontext' => 'Which planet is known as the red planet?',
        ]);
        $generator->create_question('shortanswer', null, [
            'category' => $category->id,
            'questiontext' => 'What planet is commonly called the red planet?',
        ]);
        $generator->create_question('shortanswer', null, [
            'category' => $category->id,
            'questiontext' => 'Explain dependency injection in PHP applications.',
        ]);

        $service = new snapshot_service();
        $service->sync_category($category, 10);
        $stats = (new candidate_generator())->generate((int)$category->id, 10);

        $this->assertGreaterThanOrEqual(1, $stats['newpending']);
        $pairs = $DB->get_records('qbank_duplicate_pair', ['categoryid' => $category->id]);
        $this->assertNotEmpty($pairs);
        foreach ($pairs as $pair) {
            $this->assertLessThanOrEqual(1.0, (float)$pair->heuristicscore);
            $this->assertSame('candidate', $pair->state);
        }
    }


    /**
     * Method test_same_answer_and_type_can_candidate_completely_reworded_questions.
     *
     * @return void Return value.
     */
    public function test_same_answer_and_type_can_candidate_completely_reworded_questions(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('maxbucket', 120, 'qbank_duplicate');
        set_config('maxcandidatesperquestion', 30, 'qbank_duplicate');
        set_config('maxaipairs', 100, 'qbank_duplicate');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $generator->create_question('shortanswer', null, [
            'category' => $category->id,
            'questiontext' => 'Identify the fourth planet from the Sun.',
        ]);
        $generator->create_question('shortanswer', null, [
            'category' => $category->id,
            'questiontext' => 'Name the world whose surface hosts Olympus Mons.',
        ]);

        (new snapshot_service())->sync_category($category, 16);
        (new candidate_generator())->generate((int)$category->id, 16);

        $pair = $DB->get_record('qbank_duplicate_pair', ['categoryid' => $category->id], '*', MUST_EXIST);
        $heuristics = json_decode($pair->heuristics, true);
        $this->assertTrue($heuristics['same_answer_signature']);
        $this->assertTrue($heuristics['same_question_type']);
    }

    /**
     * Method test_large_exact_cluster_uses_linear_chain_not_all_combinations.
     *
     * @return void Return value.
     */
    public function test_large_exact_cluster_uses_linear_chain_not_all_combinations(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('maxbucket', 10, 'qbank_duplicate');
        set_config('maxcandidatesperquestion', 30, 'qbank_duplicate');
        set_config('maxaipairs', 100, 'qbank_duplicate');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        for ($i = 0; $i < 12; $i++) {
            $generator->create_question('shortanswer', null, [
                'category' => $category->id,
                'questiontext' => 'What is the capital of France?',
            ]);
        }

        (new snapshot_service())->sync_category($category, 15);
        (new candidate_generator())->generate((int)$category->id, 15);

        // A complete graph would contain 66 pairs. The exact cluster needs only N - 1 edges.
        $this->assertSame(11, $DB->count_records('qbank_duplicate_pair', ['categoryid' => $category->id]));
    }


    /**
     * Method test_notduplicate_is_reused_until_a_question_changes.
     *
     * @return void Return value.
     */
    public function test_notduplicate_is_reused_until_a_question_changes(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$category, $q1] = $this->make_pair(25);
        $pair = $DB->get_record('qbank_duplicate_pair', ['categoryid' => $category->id], '*', MUST_EXIST);
        pair_repository::decide((int)$pair->id, 'notduplicate');

        (new snapshot_service())->sync_category($category, 26);
        (new candidate_generator())->generate((int)$category->id, 26);
        $pair = $DB->get_record('qbank_duplicate_pair', ['id' => $pair->id], '*', MUST_EXIST);
        $this->assertSame('notduplicate', $pair->state);
        $this->assertSame(26, (int)$pair->lastscanid);

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $generator->update_question($q1, null, ['questiontext' => 'Changed after the human decision.']);
        $this->assertFalse($DB->record_exists('qbank_duplicate_pair', ['id' => $pair->id]));
        (new snapshot_service())->sync_category($category, 27);
    }

    /**
     * Method test_changed_question_invalidates_nonignored_pair.
     *
     * @return void Return value.
     */
    public function test_changed_question_invalidates_nonignored_pair(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$category, $q1] = $this->make_pair(20);
        $this->assertEquals(1, $DB->count_records('qbank_duplicate_pair', ['categoryid' => $category->id]));

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $generator->update_question($q1, null, ['questiontext' => 'A completely changed question about databases.']);
        $this->assertEquals(0, $DB->count_records('qbank_duplicate_pair', ['categoryid' => $category->id]));
        (new snapshot_service())->sync_category($category, 21);
    }

    /**
     * Method test_ignored_pair_survives_question_change.
     *
     * @return void Return value.
     */
    public function test_ignored_pair_survives_question_change(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        [$category, $q1] = $this->make_pair(30);
        $pair = $DB->get_record('qbank_duplicate_pair', ['categoryid' => $category->id], '*', MUST_EXIST);
        pair_repository::decide((int)$pair->id, 'ignored');

        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $generator->update_question($q1, null, ['questiontext' => 'Changed wording after a deliberate ignore.']);
        (new snapshot_service())->sync_category($category, 31);

        $pair = $DB->get_record('qbank_duplicate_pair', ['id' => $pair->id], '*', MUST_EXIST);
        $this->assertSame('ignored', $pair->state);
    }

    /**
     * Build two close questions and run local candidate generation.
     *
     * @return array{0:stdClass,1:stdClass}
     */
    private function make_pair(int $scanid): array {
        $generator = $this->getDataGenerator()->get_plugin_generator('core_question');
        $category = $generator->create_question_category();
        $q1 = $generator->create_question('shortanswer', null, [
            'category' => $category->id,
            'questiontext' => 'Which planet is known as the red planet?',
        ]);
        $generator->create_question('shortanswer', null, [
            'category' => $category->id,
            'questiontext' => 'What planet is commonly called the red planet?',
        ]);
        (new snapshot_service())->sync_category($category, $scanid);
        (new candidate_generator())->generate((int)$category->id, $scanid);
        return [$category, $q1];
    }
}
