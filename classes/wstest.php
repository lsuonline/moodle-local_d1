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
 * @package    enrol_d1
 * @copyright  2022 onwards LSUOnline & Continuing Education
 * @copyright  2022 onwards Robert Russo
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/*
    **********************************************************
    * This is only a test file and will not be used anywhere *
    **********************************************************
*/

// Make sure this can only run via CLI.
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');

global $CFG;

require_once("$CFG->libdir/clilib.php");

// Require the magicness.
require_once('d1.php');

// Get the token.
$token = lsud1::get_token();
var_dump($token);

$pdcourses = lsud1::get_pd_dgps(true);

foreach ($pdcourses as $pdcourse) {
    // Get the Course Section ObjectId for the section for posting grades.
    $csobjectid = lsud1::get_cs_objectid($pdcourse->coursenumber, $pdcourse->sectionnumber);
    $courseidnumber = $pdcourse->coursenumber . '__' . $pdcourse->sectionnumber;
    mtrace("\nPosting grades for " . $courseidnumber . " - Objectid: " . $csobjectid);
    $pdgrades = lsud1::get_pd_dgps(false, $courseidnumber);

    $count = 0;
    foreach ($pdgrades as $pdgrade) {
        $count++;
        $pdgrade->csobjectid = $csobjectid;
        mtrace("  $count: Posting \"$pdgrade->finallettergrade\" from ($pdgrade->finaldate) in $courseidnumber with ObjectId $pdgrade->csobjectid for student $pdgrade->x_number.");
        $post = lsud1::post_update_grade($token, $pdgrade->x_number, $pdgrade->csobjectid, $pdgrade->finallettergrade, $pdgrade->finaldate);
        if (isset($post->createOrUpdateStudentFinalGradeResult)) {
            mtrace("    Posted \"$pdgrade->finallettergrade\" for $pdgrade->x_number with status of \"" . $post->createOrUpdateStudentFinalGradeResult->status . "\".");
        } else {
            mtrace("    Unable to post \"$pdgrade->finallettergrade\" for $pdgrade->x_number in course $courseidnumber with status of " . $post->SRSException->message . ".");
        }
        mtrace("  $count: Completed Posting for student $pdgrade->x_number in course $courseidnumber.");
    }
    mtrace("Posted grades for " . $courseidnumber . " - Objectid: " . $csobjectid);
die();
}


/*
$gka = array();
$gk = new stdClass();

$gk->course = 'ACCT 2001';
$gk->section = '003';
$gk->grade = 'A-';

$gka[] = $gk;
$gk = new stdClass();

$gk->course = 'ACCT 2101';
$gk->section = '003';
$gk->grade = 'B';

$gka[] = $gk;
$gk = new stdClass();

$gk->course = 'ACCT 3001';
$gk->section = '006';
$gk->grade = 'D';

$gka[] = $gk;

foreach ($gka as $g) {
$idnumber = $g->course . '__' . $g->section;
$objectid = d1pghelpers::get_cs_objectid($idnumber);
echo("$idnumber = $objectid\n");
}
*/

/*

$gka = array();
$gk = new stdClass();

$gk->course = 'ACCT 2001';
$gk->section = '003';
$gk->grade = 'A-';

$gka[] = $gk; 
$gk = new stdClass();

$gk->course = 'ACCT 2101';
$gk->section = '003';
$gk->grade = 'B';

$gka[] = $gk;
$gk = new stdClass();

$gk->course = 'ACCT 3001';
$gk->section = '006';
$gk->grade = 'D';

$gka[] = $gk;

foreach ($gka as $g) {
$optionalparms = ', "advancedCriteria": {"sectionCode": "' . $g->section . '"}';
$course = wshelpers::get_course_by('250','courseCode',$g->course, $optionalparms, 'Short');
$csobjectid = $course->courseSectionProfiles->courseSectionProfile->objectId;
var_dump($csobjectid);
var_dump($course->courseSectionProfiles->courseSectionProfile);
die();

$gradepost = wshelpers::post_update_grade($token, 'X000015', $csobjectid, $g->grade);

  echo("$g->course" . "__" . "$g->section - $csobjectid: $g->grade\n");

}
die();

$optionalparms = ', "advancedCriteria": {"sectionCode": "' . $g->section . '"}';
$course = wshelpers::get_course_by('250','courseCode',$g->course, $optionalparms, 'Short');
$csobjectid = $course->courseSectionProfiles->courseSectionProfile->objectId;

var_dump($csobjectid);

$gradepost = wshelpers::post_update_grade($token, 'X000015', $csobjectid, $g->grade);
die();

//Get the approved category.
$categories = get_config('enrol_d1', 'categories');

// Get courses in the approved category.
$courses = course_helper::get_courses($categories);

foreach ($courses as $course) {
    echo"\nCourse: ";
    print_r($course->idnumber);

           $prestage = enrol_d1::prestage_drops($course->idnumber, 'unenroll');
            mtrace("  Prestaged drops for $course->idnumber.");

    $enrollments = wshelpers::set_student_enrollments($token, $course);
            mtrace("\nFound " . count($enrollments) . " enrollments in course: " . $course->idnumber . ".\n");


  ["section"]=>
  string(14) "ACCT 2001__003"
  ["firstname"]=>
  string(16) "Unit Student One"
  ["lastname"]=>
  string(16) "Unit Testing One"
  ["d1id"]=>
  int(1357165)
  ["xnumber"]=>
  string(7) "X001688"
  ["lsuid"]=>
  string(0) ""
  ["username"]=>
  string(12) "unitstudent1"
  ["email"]=>
  string(13) "calba@lsu.edu"
  ["enrollstart"]=>
  string(23) "15 Oct 2022 12:25:08 PM"
  ["startstamp"]=>
  int(1665854708)
  ["enrollend"]=>
  string(23) "13 Apr 2023 12:25:08 PM"
  ["eestamp"]=>
  int(1681406708)
  ["duedate"]=>
  string(23) "13 Jun 2023 12:00:00 AM"
  ["endstamp"]=>
  int(1686632400)
  ["enrollstatus"]=>
  string(9) "Completed"


if (empty($enrollments[0])) {
echo"\nNO Enrollments in $course->idnumber\n";
continue;
} 

    foreach ($enrollments as $enrollment) {

echo("$enrollment->section, $enrollment->xnumber, $enrollment->email, start: $enrollment->enrollstart, end: $enrollment->enrollend, due: $enrollment->duedate\n");

                try {
                    $user = wshelpers::populate_stu_db($enrollment);
                } catch (exception $en) {
                    echo"\nBegin Error\n";
                    var_dump($en);
                    echo"\nEnd Error\n";
                }
            $test = enrol_d1::insert_or_update_enrolldb($enrollment);
            mtrace("Touched row $test.");
     }

}

$d1students = enrol_d1::get_d1students($all=true);
foreach ($d1students as $d1student) {
    try {
        $user = enrol_d1::create_update_user($d1student);
    } catch (exception $cu) {
        echo"\nBegin Creation Error\n";
        var_dump($cu->getMessage());
        echo"\nEnd Creation Error\n";
    }
}

*/
?>
