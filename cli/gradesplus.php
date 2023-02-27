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

/*
    *************************************** Axioms ****************************************
    * If a course returns data, it must be public.                                        *
    * If a course does not return data, regardless of Active status, it must be Internal. *
    ***************************************************************************************
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
    // Check the active/inactive flag. 
    mtrace("Checking course status for $course->coursenumber.");
    $aavailability = gradeposter::get_course_availability($course->coursenumber, "Active");
    $iavailability = gradeposter::get_course_availability($course->coursenumber, "Inactive");

    // Set active based on above.
    $active = isset($aavailability->searchCourseResult->courseProfiles->courseProfile->applicability) ? "Active" : "Inactive";

    // If neither return data, that means the course is set to Internal.
    $availability = isset($aavailability->searchCourseResult->courseProfiles->courseProfile->applicability) ? $aavailability
                    : isset($iavailability->searchCourseResult->courseProfiles->courseProfile->applicability) ? $iavailability : null;

    if (!is_null($availability) && $availability->searchCourseResult->courseProfiles->courseProfile->applicability == "Public") {
        if (is_null($course->course_applicability)) {
            mtrace("  Updating course applicability to Public for $course->coursenumber.");
            $updated = gradeposter::update_local_course_applicability($course->coursenumber, "Public");
            if ($updated) {
                mtrace("    We logged $course->coursenumber as Public.");
            }
        }
        if ($active == "Active") {
            if (is_null($course->course_status)) {
                $updated = gradeposter::update_local_course_status($course->coursenumber, "Active");
                if ($updated) {
                    mtrace("    We logged $course->coursenumber as Active.");
                }
            }
        } else {
            if (is_null($course->course_status)) {
                $updated = gradeposter::update_local_course_status($course->coursenumber, "Inactive");
                if ($updated) {
                    mtrace("    We logged $course->coursenumber as Inactive.");
                }
            }
            mtrace("    Let's set $course->coursenumber as Active.");
            $updated = gradeposter::update_remote_course_sa($course->coursenumber, 'Active', null);
            if ($updated->updateCourseResult->responseCode == "Success") {
                mtrace("  We set D1 course: $course->coursenumber to Active.");
            }
        }

    } else {
        // The course applicablity is set to Internal, let's log it.
        if (is_null($course->course_applicability)) {
            $updated = gradeposter::update_local_course_applicability($course->coursenumber, "Internal");
            if ($updated) {
                mtrace("    We logged $course->coursenumber as Internal.");
            }
        }

        mtrace("    Let's set $course->coursenumber as Public.");
        $updated = gradeposter::update_remote_course_sa($course->coursenumber, null, "Public");
        if ($updated->updateCourseResult->responseCode == "Success") {
            mtrace("  We set D1 course: $course->coursenumber to Public.");
        }

        $aavailability = gradeposter::get_course_availability($course->coursenumber, "Active");

        // Set active based on above.
        $active = isset($aavailability->searchCourseResult->courseProfiles->courseProfile->applicability) ? "Active" : "Inactive";

        if ($active == "Active") {
            if (is_null($course->course_status)) {
                $updated = gradeposter::update_local_course_status($course->coursenumber, "Active");
                if ($updated) {
                    mtrace("    We logged $course->coursenumber as Active.");
                }
            }
        } else {
            if (is_null($course->course_status)) {
                $updated = gradeposter::update_local_course_status($course->coursenumber, "Inactive");
                if ($updated) {
                    mtrace("    We logged $course->coursenumber as Inactive.");
                }
            }
            $updated = gradeposter::update_remote_course_sa($course->coursenumber, "Active", null);
            if ($updated->updateCourseResult->responseCode == "Success") {
                mtrace("  We set D1 course: $course->coursenumber to Active.");
            }
        }

        // Clearly this was an internal course, log it as such.
        if (is_null($course->course_applicability)) {
            $updated = gradeposter::update_local_course_applicability($course->coursenumber, "Internal");
            if ($updated) {
                mtrace("    We logged $course->coursenumber as Internal.");
            }
        }
    }
}

class gradeposter {

    public static function get_course_availability($coursenumber, $status) {
        // Get the data needed.
        $s = lsupgd1::get_d1_settings();

        $token = lsupgd1::get_token();

        // Set the URL for the post command to get a list of the courses matching the parms.
        $url = $s->wsurl . '/webservice/PublicViewREST/searchCourse?informationLevel=Long&locale=en_US&_type=json';

        // Set the POST body.
        if ($status == "Active") {
            $body = '{"searchCourseProfileRequestDetail": {"courseSectionSearchCriteria": {"courseCode": "' . $coursenumber . '","courseStatus": "Active"}}}';
        } else {
            $body = '{"searchCourseProfileRequestDetail": {"courseSectionSearchCriteria": {"courseCode": "' . $coursenumber . '"}}}';
        }

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

    public static function update_local_course_status($coursenumber, $status) {
        global $DB;
        if ($status == "Inactive") {
            $s = ' SET g.course_status = "Inactive" ';
        } else if ($status == "Active") {
            $s = ' SET g.course_status = "Active" ';
        }

        $sql = 'UPDATE mdl_scotty_grades2 g
                  ' . $s . '
                WHERE g.coursenumber = "' . $coursenumber . '"';

        $status = $DB->execute($sql);

        return $status;
    }

    public static function update_local_course_applicability($coursenumber, $applicability) {
        global $DB;
        if ($applicability == "Internal") {
            $s = ' SET g.course_applicability = "Internal" ';
        } else if ($applicability == "Public") {
            $s = ' SET g.course_applicability = "Public" ';
        }

        $sql = 'UPDATE mdl_scotty_grades2 g
                  ' . $s . '
                WHERE g.coursenumber = "' . $coursenumber . '"';

        $status = $DB->execute($sql);

        return $status;
    }

    public static function update_remote_course_sa($coursenumber, $status=null, $applicability=null) {
        // Get the data needed.
        $s = lsupgd1::get_d1_settings();

        $token = lsupgd1::get_token();

        // Set the URL for the post command to get a list of the courses matching the parms.
        $url = $s->wsurl . '/webservice/InternalViewREST/updateCourse?_type=json';

        // Set the POST body.
        if (is_null($applicability)) {
            $body = '{"updateCourseRequestDetail":{"course":{"associationMode":"update","courseNumber":"'.$coursenumber.'","objectStatusCode":"'.$status.'"}}}';
        } else if (is_null($status)){
            $body = '{"updateCourseRequestDetail":{"course":{"associationMode":"update","courseNumber":"'.$coursenumber.'","applicability":"'.$applicability.'"}}}';
        } else {
            $body = '{"updateCourseRequestDetail":{"course":{"associationMode":"update","courseNumber":"'.$coursenumber.'","objectStatusCode":"'.$status.'","applicability":"'.$applicability.'"}}}';
        }


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

  public static function update_status($sid, $poststatus, $reasonmodified) {
    // Grab the global DB functionality from core moodle.
    global $DB;

    // Set the table to use.
    $table = 'scotty_grades2';

    // Get the time.
    $t = time();

    // Time posted is based on if we actually posted or not.
    $timeposted   = $poststatus ? $t : null;

    // Timemodified is a formatted time string.
    $date = date("Y-m-d h:i:s", $t);

    // Build the data object.
    $dataobject = array('id'             => $sid,
                        'poststatus'     => $poststatus,
                        'timeposted'     => $timeposted,
                        'reasonmodified' => $reasonmodified,
                        'timemodified'   => $date
    );

    // Update the table accordinly.
    $update = $DB->update_record($table, $dataobject);

    return $update;

  }

  public static function write_cs_objectid($course, $csobjectid) {
    // Grab the global DB functionality from core moodle.
    global $DB;

    // Define the update SQL to write the csobjectid for all matching course sections.
    $sql = 'UPDATE mdl_scotty_grades2 msg
              SET msg.csobjectid = ' . $csobjectid . ',
              msg.reasonmodified = "csobjectid fetched",
              msg.timemodified = CONVERT_TZ(NOW(),"SYSTEM","America/Chicago")
              WHERE msg.coursenumber = "' . $course->coursenumber . '"
                AND msg.sectionnumber = "' . $course->sectionnumber . '"';

    // Run the SQL.
    $update = $DB->execute($sql);

    // If this was successful, let us know.
    if ($update) {
        mtrace("Updated scotty_grades2 csobjectid to $csobjectid for all course sections matching $course->coursenumber - $course->sectionnumber.");
    } else {
        mtrace("* Failed to update scotty_grades2 csobjectid: $csobjectid for all course sections matching $course->coursenumber - $course->sectionnumber.");
    }
    return $update;

  }

  public static function get_courses() {
    global $DB;

    // Build the SQL.
    $sql = 'SELECT s.id AS sid,
              x_number,
              coursenumber,
              sectionnumber,
              s.course_status,
              s.course_applicability
            FROM mdl_scotty_grades2 s
            WHERE s.poststatus = 0
                AND s.csobjectid IS NULL
                AND (s.course_status IS NULL OR s.course_applicability IS NULL)
            GROUP BY s.coursenumber
            ORDER BY s.coursenumber, s.customsectionnumber ASC';

    // Get the courses.
    $courses = $DB->get_records_sql($sql);

    // Return the courses.
    return $courses;
  }
}

?>
