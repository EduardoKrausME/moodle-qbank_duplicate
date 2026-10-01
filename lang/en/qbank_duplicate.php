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
 * qbank_duplicate.php
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus {@link https://eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;


$string['classification:related_but_distinct'] = 'Related but distinct';
$string['classification:same_question'] = 'Same question';
$string['classification:strongly_overlapping'] = 'Strongly overlapping';
$string['classification:unrelated'] = 'Unrelated';
$string['confidencevalue'] = 'AI confidence: {$a}%';
$string['currentcategory'] = 'Current question category: {$a}';
$string['decisionsaved'] = 'The pair decision was saved.';
$string['duplicate:manage'] = 'Manage duplicate question decisions';
$string['duplicate:scan'] = 'Scan question categories for duplicates';
$string['duplicate:view'] = 'View duplicate question reports';
$string['error:invalidairesponse'] = 'The AI bridge returned an invalid response for duplicate comparison.';
$string['error:stalepair'] = 'The candidate pair changed or is no longer available in this category.';
$string['heuristicvalue'] = 'Local heuristic: {$a}%';
$string['hideunrelated'] = 'Hide unrelated and AI errors';
$string['ignorepair'] = 'Ignore pair';
$string['marknotduplicate'] = 'Mark as not duplicate';
$string['nopairs'] = 'No reportable duplicate pairs were found in the latest scan.';
$string['noscans'] = 'No duplicate scan has been run for this category yet.';
$string['phase:candidates'] = 'Generating candidates';
$string['phase:done'] = 'Done';
$string['phase:failed'] = 'Failed';
$string['phase:queued'] = 'Queued';
$string['phase:semantic'] = 'Semantic comparison';
$string['phase:snapshot'] = 'Normalizing question versions';
$string['pluginname'] = 'Duplicate questions';
$string['privacy:metadata'] = 'The duplicate question bank plugin does not store personal user data. It stores question-bank analysis metadata only.';
$string['questiona'] = 'Question A';
$string['questionb'] = 'Question B';
$string['reason'] = 'Reason';
$string['reportintro'] = 'Find exact and semantic duplicates without comparing every possible pair. Candidate generation runs locally first; AI is used only for bounded candidate pairs. Nothing is deleted automatically.';
$string['scanqueued'] = 'The duplicate scan was queued.';
$string['scanstatus'] = 'Status: {$a->status}. Phase: {$a->phase}. Questions: {$a->questions}. Candidate pairs: {$a->candidates}. AI calls: {$a->aicalls}. AI errors: {$a->aierrors}.';
$string['setting:comparebatchsize'] = 'Pairs per adhoc task';
$string['setting:comparebatchsize_desc'] = 'Number of candidate pairs processed by each semantic comparison adhoc task.';
$string['setting:maxaipairs'] = 'Maximum new AI pairs per scan';
$string['setting:maxaipairs_desc'] = 'Hard safety limit for new candidate pairs that may require AI in one category scan. Previously analysed unchanged pairs are reused without consuming this budget.';
$string['setting:maxbucket'] = 'Maximum non-exact bucket size';
$string['setting:maxbucket_desc'] = 'LSH/keyword buckets larger than this are treated as too common and skipped. Exact normalized-text buckets are represented as a connected N−1 chain instead of N² pairs.';
$string['setting:maxcandidatesperquestion'] = 'Maximum candidates per question';
$string['setting:maxcandidatesperquestion_desc'] = 'Caps how many active semantic candidates a single question can produce in one scan.';
$string['showunrelated'] = 'Show unrelated and AI errors';
$string['similarity'] = 'Similarity';
$string['startscan'] = 'Scan this category';
$string['status:aierror'] = 'AI comparison error';
$string['status:comparing'] = 'Comparing candidates';
$string['status:completed'] = 'Completed';
$string['status:completedwitherrors'] = 'Completed with AI errors';
$string['status:failed'] = 'Failed';
$string['status:queued'] = 'Queued';
$string['status:running'] = 'Running';
$string['task:compare'] = 'Compare duplicate question candidates with AI';
$string['task:scan'] = 'Scan question category for duplicate candidates';
