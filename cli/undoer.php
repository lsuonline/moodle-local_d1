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
 * @package    local_d1
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

// Require the magicness.
require(__DIR__ . '/../../../config.php');
require_once("$CFG->libdir/clilib.php");
require_once('../classes/d1.php');

mtrace("Getting csobjectids from D1.");

// We need this.
global $CFG;

// Grab the token.
$token = lsupgd1::get_token();
mtrace("Token: $token");

// Grab the courses from the DB.
$courses = gradeposter::get_courses();

$count = count($courses);

mtrace("$count courses to be procesed.");

// Set the counter.
$counter = 0;

// Loop through the courses.
foreach ($courses as $course) {
    mtrace("  Let's set $course->coursenumber to $course->course_status and $course->course_applicability.");
    $updated = gradeposter::update_remote_course_sa($course->coursenumber, $course->course_status, $course->course_applicability);
    if ($updated->updateCourseResult->responseCode == "Success") {
        mtrace("    We set D1 course: $course->coursenumber to $course->course_status and $course->course_applicability.");
        $dbup = gradeposter::update_local_undo_status($course->coursenumber);
        if ($dbup) {
            mtrace("    We updated the local database to reflect this.");
        }
    }
}

class gradeposter {

    public static function update_local_undo_status($coursenumber) {
        global $DB;

        $sql = 'UPDATE mdl_scotty_grades2 g
                SET g.undostatus = 1
                WHERE g.coursenumber = "' . $coursenumber . '"';

        $status = $DB->execute($sql);

        return $status;
    }

    public static function update_remote_course_sa($coursenumber, $status, $applicability) {
        // Get the data needed.
        $s = lsupgd1::get_d1_settings();

        $token = lsupgd1::get_token();

        // Set the URL for the post command to get a list of the courses matching the parms.
        $url = $s->wsurl . '/webservice/InternalViewREST/updateCourse?_type=json';

        // Set the POST body.
        $body = '{"updateCourseRequestDetail":{"course":{"associationMode":"update","courseNumber":"'.$coursenumber.'","objectStatusCode":"'.$status.'","applicability":"'.$applicability.'"}}}';

        // Set the POST header.
        $header = array('Content-Type: application/json',
                'sessionId:' . $token);

        // Set up the CURL handler.
        $curl = curl_init($url);

        // Se the CURL options.
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        // Grab the response.
        $json_response = curl_exec($curl);

        // Set the HTTP code for debugging.
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close the CURL handler.
        curl_close($curl);

        // Decode the response.
        $response = json_decode($json_response);

        // Return the response.
        return $response;
    }

  public static function get_courses() {
    global $DB;

    // Build the SQL.
    $sql = 'SELECT s.id AS sid,
              coursenumber,
              s.course_status,
              s.course_applicability
            FROM mdl_scotty_grades2 s
            WHERE s.csobjectid IS NOT NULL
                AND s.undostatus = 0
                AND s.course_status IS NOT NULL
                AND s.course_applicability IS NOT NULL
            GROUP BY s.coursenumber
            ORDER BY s.coursenumber ASC';

    // Get the courses.
    $courses = $DB->get_records_sql($sql);

    // Return the courses.
    return $courses;
  }
}

?>
