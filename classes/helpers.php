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

// Do not run directly.
defined('MOODLE_INTERNAL') || die();

class d1pghelpers {

    /**
     * Grabs D1 Webseervice settings.
     *
     * @return @object $s
     */
    public static function get_d1_settings() {
        global $CFG;

        // Build the object.
        $s = new stdClass();
        // Get the debug file storage location.
        $s->debugfiles = get_config('enrol_d1', 'debugfiles');
        // Get the Moodle data root.
        $s->dataroot   = $CFG->dataroot;
        // Get the DestinyOne webservice url prefix.
        $s->wsurl      = $CFG->d1_wsurl;
        // Determine if we should do our extra debugging.
        $s->debugging  = get_config('enrol_d1', 'debuextra') == 1
                         && $CFG->debugdisplay == 1 ? 1 : 0;

        return $s;
    }

    /**
     * Grabs D1 Webseervice credentials.
     *
     * @return @object $c
     */
    public static function get_d1_creds() {
        // Build the object.
        $c = new stdClass();
        // Get the username from the config settings.
        $c->username   = get_config('enrol_d1', 'username');
        // Get the webservice password.
        $c->password   = get_config('enrol_d1', 'password');
        // Get the debug file storage location.

        return $c;
    }

    /**
     * Grabs the token from the D1 web services.
     *
     * @return @string $token
     */
    public static function get_token() {
        // Get the data needed.
        $s = self::get_d1_settings();
        $c = self::get_d1_creds();

        // Set the URL for the REST command to get our token.
        $url = "$s->wsurl/webservice/InternalViewREST/login?_type=json&username=$c->username&password=$c->password";

        // Set up the CURL handler.
        $curl = curl_init($url);

        // Set the CURL options.
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, false);

        // Grab the response.
        $response = curl_exec($curl);

        // Set the HTTP code for debugging.
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close the curl handler.
        curl_close($curl);

        if ($s->debugging == 1) {
            // If there is a location set, use it.
            $loc = isset($s->debugfiles) ? $s->debugfiles : $s->dataroot;

            // Write this out to a file for debugging.
            $fp = fopen($loc . '/token.txt', 'w');
            fwrite($fp, $response);
            fclose($fp);
        }

        // Return the response.
        return($response);
    }

    /**
     * Grabs the D1 Course Name from the courseidnumber.
     *
     * @param  @string $courseidnumber
     * @return @string $coursename
     */
    public static function get_coursename($courseidnumber) {
        $pos = strpos($courseidnumber, '__');
        $coursename = substr($courseidnumber, 0, $pos);
        return $coursename;
    }

    /**
     * Grabs the D1 Course Section Number from the courseidnumber.
     *
     * @param  @string $courseidnumber
     * @return @string $coursesection
     */
    public static function get_coursesection($courseidnumber) {
        $coursesection = substr($courseidnumber, -3, 3);
        return $coursesection;
    }

    /**
     * Searches for the course section from the D1 web services.
     *
     * @return @string $token
     */
    public static function get_course_by($size, $type, $parm, $optionalparms = null, $level) {
        // Get the data needed.
        $s = self::get_d1_settings();

        // Set the URL for the post command to get a list of the courses matching the parms.
        $url = $s->wsurl . '/webservice/PublicViewREST/searchCourseSection?informationLevel=' . $level . '&locale=en_US&_type=json';

        // Set the POST body.
        $body = '{"searchCourseSectionProfileRequestDetail": {"paginationConstruct": {"pageNumber": "1","pageSize": "'. $size . '"},"courseSectionSearchCriteria": {"' . $type . '": "' . $parm . '"' . $optionalparms . '}}}';

        // Set the POST header.
        $header = array('Content-Type: application/json');

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
        $response = isset($response->SearchCourseSectionProfileResult) ? $response->SearchCourseSectionProfileResult : $response;

        if ($s->debugging == 1) {
            // Build the name of the course JSON.
            $str1 = $type;
            $str2 = $parm;
            $pattern = '/ /i';
            $stype = preg_replace($pattern, '_', $str1);
            $sparm = preg_replace($pattern, '_', $str2);

            // Write this out to a file for debugging.
            $fp = fopen($stype . '-' . $sparm . '.json', 'w');
            fwrite($fp, $json_response);
            fclose($fp);
        }

        return $response;
    }

    public static function get_cs_objectid($idnumber) {
        $coursename    = self::get_coursename($idnumber);

        $coursesection = self::get_coursesection($idnumber);

        $optionalparms = ', "advancedCriteria": {"sectionCode": "' . $coursesection . '"}';

        $courses = self::get_course_by('250','courseCode',$coursename, $optionalparms, 'Short');

        foreach ($courses->courseSectionProfiles->courseSectionProfile as $course) {

            if ($course->associatedCourse->courseNumber <> 'EXT ' . $coursename) {

                $csobjectid = $course->objectId;
            }
        }

        return $csobjectid;
    }

    /**
     * Posts the final grade.
     *
     * @param  @string $token
     * @param  @string $sid
     * @param  @int    $csobjectid
     * @param  @string $grade
     * @return @object $response
     */
    public static function post_update_grade($token, $sid, $csobjectid, $grade) {
        // Get the data needed.
        $s = self::get_d1_settings();

        // Set the URL for the post command to post a grade for a student
        $url = $s->wsurl . '/webservice/InternalViewRESTV2/createOrUpdateStudentFinalGrade?_type=json';

        // Set the POST body.
        $body = '{"createOrUpdateStudentFinalGradeRequestDetail":
                 {"studentGrade": {"gradingSheet": {"courseSectionProfile":
                 {"objectId": "' . $csobjectid . '"}},"student":
                 {"personNumber": "' . $stunumber . '"},"studentGradeItems":
                 {"studentGradeItem": {"grade": "' . $grade . '"}}}}}';

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

        if ($s->debugging == 1) {
            // Write this out to a file for debugging.
            $fp = fopen($sid . '-' . $csobjectid . '.json', 'w');
            fwrite($fp, $json_response);
            fclose($fp);
        }

        // Return the response.
        return $response;
    }
}
?>
