<?php
///////////////////////////////////////////////////////////////////////////
//                                                                       //
// NOTICE OF COPYRIGHT                                                   //
//                                                                       //
//                      Online Judge for Moodle                          //
//        https://github.com/hit-moodle/moodle-local_onlinejudge         //
//                                                                       //
// Copyright (C) 2009 onwards  Sun Zhigang  http://sunner.cn             //
//                                                                       //
// This program is free software; you can redistribute it and/or modify  //
// it under the terms of the GNU General Public License as published by  //
// the Free Software Foundation; either version 3 of the License, or     //
// (at your option) any later version.                                   //
//                                                                       //
// This program is distributed in the hope that it will be useful,       //
// but WITHOUT ANY WARRANTY; without even the implied warranty of        //
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the         //
// GNU General Public License for more details:                          //
//                                                                       //
//          http://www.gnu.org/copyleft/gpl.html                         //
//                                                                       //
///////////////////////////////////////////////////////////////////////////

/**
 * Testcases management
 *
 * @package   local_onlinejudge
 * @copyright 2011 Sun Zhigang (http://sunner.cn)
 * @author    Sun Zhigang
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../../../config.php');
require_once("$CFG->dirroot/mod/assignment/lib.php");
require_once('testcase_form.php');

$id = optional_param('id', 0, PARAM_INT);  // Course Module ID
$a  = optional_param('a', 0, PARAM_INT);   // Assignment ID

$url = new moodle_url('/mod/assignment/type/onlinejudge/testcase.php');
if ($id) {
    if (! $cm = get_coursemodule_from_id('assignment', $id)) {
        print_error('invalidcoursemodule');
    }

    if (! $assignment = $DB->get_record("assignment", array("id"=>$cm->instance))) {
        print_error('invalidid', 'assignment');
    }

    if (! $course = $DB->get_record("course", array("id"=>$assignment->course))) {
        print_error('coursemisconf', 'assignment');
    }
    $url->param('id', $id);
} else {
    if (!$assignment = $DB->get_record("assignment", array("id"=>$a))) {
        print_error('invalidid', 'assignment');
    }
    if (! $course = $DB->get_record("course", array("id"=>$assignment->course))) {
        print_error('coursemisconf', 'assignment');
    }
    if (! $cm = get_coursemodule_from_instance("assignment", $assignment->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

$PAGE->set_url($url);
require_login($course, true, $cm);

global $context;
$context = get_context_instance(CONTEXT_MODULE, $cm->id);
require_capability('mod/assignment:grade', $context);

$testform = new testcase_form($DB->count_records('assignment_oj_testcases', array('assignment' => $assignment->id)));

if ($testform->is_cancelled()){

	redirect($CFG->wwwroot.'/mod/assignment/view.php?id='.$id);

} else if ($fromform = $testform->get_data()){

	for ($i = 0; $i < $fromform->boundary_repeats; $i++) {
        if (emptycase($fromform, $i)) {
            if ($fromform->caseid[$i] != -1) {
                $DB->delete_records('assignment_oj_testcases', array('id' => $fromform->caseid[$i]));
                $fs = get_file_storage();
                $fs->delete_area_files($context->id, 'mod_assignment', 'onlinejudge_input', $fromform->caseid[$i]);
                $fs->delete_area_files($context->id, 'mod_assignment', 'onlinejudge_output', $fromform->caseid[$i]);
            }
            continue;
        }

        if (isset($fromform->usefile[$i])) {
            $testcase->usefile = true;
			$testcase->inputfile = $fromform->inputfile[$i];
			$testcase->outputfile = $fromform->outputfile[$i];
        } else {
            $testcase->usefile = false;
			$testcase->input = $fromform->input[$i];
			$testcase->output = $fromform->output[$i];
        }

        $testcase->feedback = $fromform->feedback[$i];
        $testcase->subgrade = $fromform->subgrade[$i];
        $testcase->assignment = $assignment->id;
        $testcase->id = $fromform->caseid[$i];
        $testcase->sortorder = $i;

        if ($testcase->id != -1) {
            $DB->update_record('assignment_oj_testcases', $testcase);
        } else {
            $testcase->id = $DB->insert_record('assignment_oj_testcases', $testcase);
        }

        if ($testcase->usefile) {
            file_save_draft_area_files($testcase->inputfile, $context->id, 'mod_assignment', 'onlinejudge_input', $testcase->id);
            file_save_draft_area_files($testcase->outputfile, $context->id, 'mod_assignment', 'onlinejudge_output', $testcase->id);
        }

        unset($testcase);
	}

	redirect($CFG->wwwroot.'/mod/assignment/view.php?id='.$id);    

} else {

    $assignmentinstance = new assignment_onlinejudge($cm->id, $assignment, $cm, $course);
    $assignmentinstance->view_header();

    $testcases = $DB->get_records('assignment_oj_testcases', array('assignment' => $assignment->id), 'sortorder ASC');

    $toform = array();
    if ($testcases) {
        $i = 0;
        foreach ($testcases as $tstObj => $tstValue) {
            $toform["input[$i]"] = $tstValue->input;
            $toform["output[$i]"] = $tstValue->output;
            $toform["feedback[$i]"] = $tstValue->feedback;
            $toform["subgrade[$i]"] = $tstValue->subgrade;
            $toform["usefile[$i]"] = $tstValue->usefile;
            $toform["caseid[$i]"] = $tstValue->id;

            file_prepare_draft_area($toform["inputfile[$i]"], $context->id, 'mod_assignment', 'onlinejudge_input', $tstValue->id, array('subdirs' => 0, 'maxfiles' => 1));
            file_prepare_draft_area($toform["outputfile[$i]"], $context->id, 'mod_assignment', 'onlinejudge_output', $tstValue->id, array('subdirs' => 0, 'maxfiles' => 1));

            $i++;
        }
    }

	$testform->set_data($toform);
	$testform->display();

	$assignmentinstance->view_footer();
}

function emptycase(&$form, $i) {
    if ($form->subgrade[$i] != 0.0)
        return false;

    if (isset($form->usefile[$i]))
        return empty($form->inputfile[$i]) && empty($form->outputfile[$i]);
    else
        return empty($form->input[$i]) && empty($form->output[$i]);
}

/**
 * Compare two testcases
 *
 * @return true if identified
 */
function casecmp($oldcase, $newcase) {
    if ($oldcase->usefile != $newcase->usefile) {
        return false;
    } else if (!$oldcase->usefile) {
        return ($oldcase->input == $newcase->input and $oldcase->output == $newcase->output);
    }

    $oldinput = null;
    $oldoutput = null;
    $newinput = null;
    $newoutput = null;

    $files = file_get_drafarea_files($newcase->inputfile);
    foreach ($files as $file) {
        if ($file->get_filename() != '.') {
            $newinput = $file->get_contenthash();
        }
    }
    $files = file_get_drafarea_files($newcase->outputfile);
    foreach ($files as $file) {
        if ($file->get_filename() != '.') {
            $newoutput = $file->get_contenthash();
        }
    }

    $fs = get_file_storage();

    $files = $fs->get_area_files($context->id, 'mod_assignment', 'onlinejudge_input', $oldcase->id);
    foreach ($files as $file) {
        if ($file->get_filename() != '.') {
            $oldinput = $file->get_contenthash();
        }
    }
    $files = $fs->get_area_files($context->id, 'mod_assignment', 'onlinejudge_output', $oldcase->id);
    foreach ($files as $file) {
        if ($file->get_filename() != '.') {
            $oldoutput = $file->get_contenthash();
        }
    }

    return ($oldinput == $newinput && $oldoutput == $newoutput);
}

