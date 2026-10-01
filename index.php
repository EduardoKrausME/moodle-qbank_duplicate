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
 * Duplicate question report.
 *
 * @package   qbank_duplicate
 * @copyright 2026 Eduardo Kraus
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/question/editlib.php');

use core_question\output\qbank_action_menu;
use qbank_duplicate\scan_service;

require_login();
core_question\local\bank\helper::require_plugin_enabled('qbank_duplicate');

[$thispageurl, $contexts, $cmid, $cm, $module, $pagevars] =
    question_edit_setup('questions', '/question/bank/duplicate/index.php');

[$categoryid, $categorycontextid] = array_map('intval', explode(',', $pagevars['cat']));
$category = $DB->get_record('question_categories', [
    'id' => $categoryid,
    'contextid' => $categorycontextid,
], '*', MUST_EXIST);
$context = context::instance_by_id($categorycontextid, MUST_EXIST);
require_capability('qbank/duplicate:view', $context);

if (optional_param('startscan', 0, PARAM_BOOL)) {
    require_sesskey();
    require_capability('qbank/duplicate:scan', $context);
    scan_service::queue($categoryid, $categorycontextid, (int)$USER->id);
    redirect($thispageurl, get_string('scanqueued', 'qbank_duplicate'));
}

$showall = optional_param('showall', 0, PARAM_BOOL);
$page = max(0, optional_param('page', 0, PARAM_INT));
$perpage = 50;

$PAGE->set_url($thispageurl);
$PAGE->set_title(get_string('pluginname', 'qbank_duplicate'));
$PAGE->set_heading($COURSE->fullname);
$PAGE->activityheader->disable();

$renderer = $PAGE->get_renderer('core_question', 'bank');
echo $OUTPUT->header();
echo $renderer->render(new qbank_action_menu($thispageurl));

echo $OUTPUT->heading(get_string('pluginname', 'qbank_duplicate'));
echo html_writer::tag('p', get_string('reportintro', 'qbank_duplicate'));
echo html_writer::tag('p', get_string('currentcategory', 'qbank_duplicate', format_string($category->name)), [
    'class' => 'text-muted',
]);

if (has_capability('qbank/duplicate:scan', $context)) {
    $scanurl = new moodle_url($thispageurl, ['startscan' => 1, 'sesskey' => sesskey()]);
    echo $OUTPUT->single_button($scanurl, get_string('startscan', 'qbank_duplicate'), 'post');
}

$scan = scan_service::latest($categoryid);
if (!$scan) {
    echo $OUTPUT->notification(get_string('noscans', 'qbank_duplicate'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$statuslabel = get_string('status:' . $scan->status, 'qbank_duplicate');
$phaselabel = get_string('phase:' . $scan->phase, 'qbank_duplicate');
if (in_array($scan->phase, ['snapshot', 'candidates'], true)) {
    $total = max(1, (int)$scan->totalquestions);
    $done = min($total, (int)$scan->processedquestions);
} else {
    $total = max(1, (int)$scan->totalcandidates);
    $done = min($total, (int)$scan->processedcandidates);
}
$percent = (int)floor(($done / $total) * 100);
if ($scan->phase === 'done') {
    $percent = 100;
}

$summary = get_string('scanstatus', 'qbank_duplicate', (object)[
    'status' => $statuslabel,
    'phase' => $phaselabel,
    'questions' => (int)$scan->totalquestions,
    'candidates' => (int)$scan->totalcandidates,
    'aicalls' => (int)$scan->aicalls,
    'aierrors' => (int)$scan->aierrors,
]);
echo html_writer::div($summary, 'mb-2');
echo html_writer::start_div('progress mb-3', ['style' => 'height: 1.25rem;']);
echo html_writer::div($percent . '%', 'progress-bar', [
    'role' => 'progressbar',
    'style' => 'width: ' . $percent . '%;',
    'aria-valuenow' => $percent,
    'aria-valuemin' => 0,
    'aria-valuemax' => 100,
]);
echo html_writer::end_div();

if (!empty($scan->message)) {
    echo $OUTPUT->notification(s($scan->message), 'warning');
}

if (in_array($scan->status, ['queued', 'running', 'comparing'], true)) {
    $PAGE->requires->js_init_code('setTimeout(function() { window.location.reload(); }, 5000);');
}

$toggleurl = new moodle_url($thispageurl, ['showall' => $showall ? 0 : 1]);
echo html_writer::link($toggleurl,
    get_string($showall ? 'hideunrelated' : 'showunrelated', 'qbank_duplicate'),
    ['class' => 'btn btn-secondary btn-sm mb-3']);

$params = ['categoryid' => $categoryid, 'scanid' => (int)$scan->id];
$where = 'p.categoryid = :categoryid AND p.lastscanid = :scanid';
if ($showall) {
    $where .= " AND p.state IN ('analysed', 'aierror')";
} else {
    $where .= " AND p.state = 'analysed' AND p.classification <> 'unrelated'";
}

$totalpairs = (int)$DB->count_records_sql("SELECT COUNT(1) FROM {qbank_duplicate_pair} p WHERE $where", $params);
$sql = "SELECT p.*,
               a.questionid AS questionida, a.name AS namea, a.qtype AS qtypea,
               b.questionid AS questionidb, b.name AS nameb, b.qtype AS qtypeb
          FROM {qbank_duplicate_pair} p
          JOIN {qbank_duplicate_snapshot} a ON a.questionbankentryid = p.entrya
          JOIN {qbank_duplicate_snapshot} b ON b.questionbankentryid = p.entryb
         WHERE $where
      ORDER BY p.confidence DESC, p.heuristicscore DESC, p.id ASC";
$pairs = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

if (!$pairs) {
    echo $OUTPUT->notification(get_string('nopairs', 'qbank_duplicate'), 'info');
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('questiona', 'qbank_duplicate'),
    get_string('questionb', 'qbank_duplicate'),
    get_string('similarity', 'qbank_duplicate'),
    get_string('reason', 'qbank_duplicate'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable';

foreach ($pairs as $pair) {
    $returnurl = $thispageurl->out(false);
    $editparams = ['returnurl' => $returnurl];
    if ($cmid) {
        $editparams['cmid'] = $cmid;
    } else {
        $editparams['courseid'] = $COURSE->id;
    }

    $urla = new moodle_url('/question/bank/editquestion/question.php', $editparams + ['id' => (int)$pair->questionida]);
    $urlb = new moodle_url('/question/bank/editquestion/question.php', $editparams + ['id' => (int)$pair->questionidb]);
    $labela = format_string($pair->namea) . ' [' . s($pair->qtypea) . ']';
    $labelb = format_string($pair->nameb) . ' [' . s($pair->qtypeb) . ']';

    if ($pair->state === 'aierror') {
        $similarity = get_string('status:aierror', 'qbank_duplicate');
        $reason = s((string)$pair->aierror);
    } else {
        $classification = get_string('classification:' . $pair->classification, 'qbank_duplicate');
        $similarity = $classification . html_writer::empty_tag('br')
            . get_string('confidencevalue', 'qbank_duplicate', round(((float)$pair->confidence) * 100))
            . html_writer::empty_tag('br')
            . html_writer::tag('small', get_string('heuristicvalue', 'qbank_duplicate',
                round(((float)$pair->heuristicscore) * 100)), ['class' => 'text-muted']);
        $reason = s((string)$pair->reason);
        $evidence = json_decode((string)$pair->evidence, true);
        if (is_array($evidence) && $evidence) {
            $items = '';
            foreach ($evidence as $item) {
                $items .= html_writer::tag('li', s($item));
            }
            $reason .= html_writer::tag('ul', $items, ['class' => 'mt-2 mb-0']);
        }
    }

    $actions = '';
    if (has_capability('qbank/duplicate:manage', $context)) {
        $notdupurl = new moodle_url('/question/bank/duplicate/action.php', [
            'pairid' => (int)$pair->id,
            'decision' => 'notduplicate',
            'returnurl' => $returnurl,
        ]);
        $ignoreurl = new moodle_url('/question/bank/duplicate/action.php', [
            'pairid' => (int)$pair->id,
            'decision' => 'ignored',
            'returnurl' => $returnurl,
        ]);
        $actions .= $OUTPUT->single_button($notdupurl, get_string('marknotduplicate', 'qbank_duplicate'), 'post');
        $actions .= $OUTPUT->single_button($ignoreurl, get_string('ignorepair', 'qbank_duplicate'), 'post');
    }

    $table->data[] = [
        html_writer::link($urla, $labela),
        html_writer::link($urlb, $labelb),
        $similarity,
        $reason,
        $actions,
    ];
}

echo html_writer::table($table);
$pagingurl = new moodle_url($thispageurl, ['showall' => $showall ? 1 : 0]);
echo $OUTPUT->paging_bar($totalpairs, $page, $perpage, $pagingurl);
echo $OUTPUT->footer();
