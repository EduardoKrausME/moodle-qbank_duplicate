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
use qbank_duplicate\output\report;
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

// Only provide data here. The report renderable and Mustache template own presentation.
$templatedata = [
    'title' => get_string('pluginname', 'qbank_duplicate'),
    'intro' => get_string('reportintro', 'qbank_duplicate'),
    'categoryname' => get_string('currentcategory', 'qbank_duplicate', format_string($category->name)),
    'canscan' => has_capability('qbank/duplicate:scan', $context),
    'scanurl' => $thispageurl->out(false),
    'sesskey' => sesskey(),
    'scanlabel' => get_string('startscan', 'qbank_duplicate'),
    'hasscan' => false,
    'noscans' => get_string('noscans', 'qbank_duplicate'),
    'haspairs' => false,
    'nopairs' => get_string('nopairs', 'qbank_duplicate'),
    'headers' => [
        'questiona' => get_string('questiona', 'qbank_duplicate'),
        'questionb' => get_string('questionb', 'qbank_duplicate'),
        'similarity' => get_string('similarity', 'qbank_duplicate'),
        'reason' => get_string('reason', 'qbank_duplicate'),
        'actions' => get_string('actions'),
    ],
    'pairs' => [],
];

$scan = scan_service::latest($categoryid);
if ($scan) {
    $templatedata['hasscan'] = true;
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
    $templatedata['percent'] = $percent;
    $templatedata['summary'] = get_string('scanstatus', 'qbank_duplicate', (object)[
        'status' => $statuslabel,
        'phase' => $phaselabel,
        'questions' => (int)$scan->totalquestions,
        'candidates' => (int)$scan->totalcandidates,
        'aicalls' => (int)$scan->aicalls,
        'aierrors' => (int)$scan->aierrors,
    ]);
    $templatedata['hasmessage'] = !empty($scan->message);
    $templatedata['message'] = (string)$scan->message;

    // The AMD module is compiled into amd/build for production Moodle sites.
    if (in_array($scan->status, ['queued', 'running', 'comparing'], true)) {
        $PAGE->requires->js_call_amd('qbank_duplicate/scan_refresh', 'init', [5000]);
    }

    $toggleurl = new moodle_url($thispageurl, ['showall' => $showall ? 0 : 1]);
    $templatedata['toggleurl'] = $toggleurl->out(false);
    $templatedata['togglelabel'] = get_string(
        $showall ? 'hideunrelated' : 'showunrelated',
        'qbank_duplicate'
    );

    $params = ['categoryid' => $categoryid, 'scanid' => (int)$scan->id];
    $where = 'p.categoryid = :categoryid AND p.lastscanid = :scanid';
    if ($showall) {
        $where .= " AND p.state IN ('analysed', 'aierror')";
    } else {
        $where .= " AND p.state = 'analysed' AND p.classification <> 'unrelated'";
    }

    $totalpairs = (int)$DB->count_records_sql(
        "SELECT COUNT(1) FROM {qbank_duplicate_pair} p WHERE $where",
        $params
    );
    $sql = "SELECT p.*,
                   a.questionid AS questionida, a.name AS namea, a.qtype AS qtypea,
                   b.questionid AS questionidb, b.name AS nameb, b.qtype AS qtypeb
              FROM {qbank_duplicate_pair} p
              JOIN {qbank_duplicate_snapshot} a ON a.questionbankentryid = p.entrya
              JOIN {qbank_duplicate_snapshot} b ON b.questionbankentryid = p.entryb
             WHERE $where
          ORDER BY p.confidence DESC, p.heuristicscore DESC, p.id ASC";
    $pairs = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);

    $templatedata['haspairs'] = !empty($pairs);
    $returnurl = $thispageurl->out(false);
    $actionurl = new moodle_url('/question/bank/duplicate/action.php');
    $canmanage = has_capability('qbank/duplicate:manage', $context);
    $notduplicatelabel = get_string('marknotduplicate', 'qbank_duplicate');
    $ignorelabel = get_string('ignorepair', 'qbank_duplicate');

    foreach ($pairs as $pair) {
        $editparams = ['returnurl' => $returnurl];
        if ($cmid) {
            $editparams['cmid'] = $cmid;
        } else {
            $editparams['courseid'] = $COURSE->id;
        }
        $urla = new moodle_url('/question/bank/editquestion/question.php',
            $editparams + ['id' => (int)$pair->questionida]);
        $urlb = new moodle_url('/question/bank/editquestion/question.php',
            $editparams + ['id' => (int)$pair->questionidb]);

        $isaierror = $pair->state === 'aierror';
        $evidence = [];
        if (!$isaierror) {
            $evidencedata = json_decode((string)$pair->evidence, true);
            if (is_array($evidencedata)) {
                foreach ($evidencedata as $item) {
                    if (is_string($item)) {
                        $evidence[] = ['text' => $item];
                    }
                }
            }
        }
        $templatedata['pairs'][] = [
            'urla' => $urla->out(false),
            'urlb' => $urlb->out(false),
            // format_string returns Moodle-sanitized markup; the template handles its rendering.
            'namea' => format_string($pair->namea),
            'nameb' => format_string($pair->nameb),
            'typea' => (string)$pair->qtypea,
            'typeb' => (string)$pair->qtypeb,
            'isaierror' => $isaierror,
            'aierrorlabel' => get_string('status:aierror', 'qbank_duplicate'),
            'classification' => $isaierror ? '' :
                get_string('classification:' . $pair->classification, 'qbank_duplicate'),
            'confidence' => $isaierror ? '' :
                get_string('confidencevalue', 'qbank_duplicate', round(((float)$pair->confidence) * 100)),
            'heuristic' => $isaierror ? '' :
                get_string('heuristicvalue', 'qbank_duplicate', round(((float)$pair->heuristicscore) * 100)),
            'reason' => $isaierror ? (string)$pair->aierror : (string)$pair->reason,
            'hasevidence' => !empty($evidence),
            'evidence' => $evidence,
            'canmanage' => $canmanage,
            'actionurl' => $actionurl->out(false),
            'pairid' => (int)$pair->id,
            'returnurl' => $returnurl,
            'notduplicatelabel' => $notduplicatelabel,
            'ignorelabel' => $ignorelabel,
        ];
    }
    if ($templatedata['haspairs']) {
        $pagingurl = new moodle_url($thispageurl, ['showall' => $showall ? 1 : 0]);
        $templatedata['pagingbar'] = $OUTPUT->paging_bar($totalpairs, $page, $perpage, $pagingurl);
    }
}

$renderer = $PAGE->get_renderer('core_question', 'bank');
echo $OUTPUT->header();
echo $renderer->render(new qbank_action_menu($thispageurl));
echo $OUTPUT->render(new report((object)$templatedata));
echo $OUTPUT->footer();
