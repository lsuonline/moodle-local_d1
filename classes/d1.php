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
 *
 * @copyright 2022 onwards LSUOnline & Continuing Education
 * @copyright 2022 onwards Robert Russo
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * @package    enrol_d1
 * @copyright  2022 onwards LSUOnline & Continuing Education
 * @copyright  2022 onwards Robert Russo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class lsud1 {

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

        // Conditionally return the response.
        if ($status == 200) {
            return $response;
        } else {
           return false;
        }
    }

    /**
     * Gets a list of courses that fall within the approved categories.
     *
     * @return @arrayy of @objects $courses
     */
    public static function get_courses($categories, $courseid=null) {
        global $CFG, $DB;

        // Categories are stored as CSV, so create an array of them.
        $cats = explode(',', $categories);

        // Build the empty courses array.
        $courses = array();

        // Loop through each category and grab courses that match.
        foreach ($cats as $cat) {
            // Set the select requirement.
            if (isset($courseid)) {
            $select = 'id = ' . $courseid . ' AND category = ' . $cat . ' AND '
                      . $DB->sql_like('idnumber', ':pattern', false);
            } else {
            $select = 'category = ' . $cat . ' AND '
                      . $DB->sql_like('idnumber', ':pattern', false);
            }

            // Set the params.
            $params = ['pattern' => '%\_\_%'];

            // Get the course list.
            $ccourses = $DB->get_records_select('course', $select, $params);

            // If we return some data, aggregate it into a new array.
            if (!empty($ccourses && count($cats) > 1)) {
                // Merge the contents of the ccourses array into the courses one.
                $courses = array_merge($courses, $ccourses);
            } else if (!empty($ccourses)) {
                // We only have one course category, set it appropriately.
                $courses = $ccourses;
            }
        }

        // Return the courses array.
        return $courses;
    }

    /**
     * Grabs the D1 ObjectID for the courseSection from the studentListItem.
     *
     * @param  @array of @objects $enrollments
     * @return @string $sectionid
     */
    public static function get_section_objectid($enrollments) {
        $sectionid = $enrollments->getClassListResult->courseSectionProfile->objectId;
        return $sectionid;
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

    public static function get_cs_objectid($coursenumber, $sectionnumber) {
        // Set the optional parameters to search for the section.
        $optionalparms = ', "advancedCriteria": {"sectionCode": "' . $sectionnumber . '"}';

        // Return the list of courses that match the course number and section number.
        $courses = self::get_course_by('250','courseCode', $coursenumber, $optionalparms, 'Short');

        // Loop through the courses and find the one that matches EXACTLY.
        foreach ($courses->courseSectionProfiles->courseSectionProfile as $course) {

            // Find the exact course number match.
            if ($course->associatedCourse->courseNumber == $coursenumber) {

                // Set the course section object id.
                $csobjectid = $course->objectId;
            }
        }

        // Return the course section object id.
        return $csobjectid;
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
    public static function get_mcoursesection($courseidnumber) {
        $coursesection = substr($courseidnumber, -3, 3);
        return $coursesection;
    }

    /**
     * Grabs the studentListItems.
     *
     * @param  @array of @objects $enrollments
     * @return @array of @objects $students
     */
    public static function get_studentenrollments($enrollments) {
        $students = $enrollments->getClassListResult->studentListItems;
        return $students;

    }

    /**
     * Grabs an array of courseids to be processed.
     *
     * @return @array of @objects $courseids
     */
    public static function get_courseids_arr() {
        global $CFG, $DB;

        // Build the table name.
        $etable = $CFG->prefix . 'enrol_d1_enrolls';
        $ctable = $CFG->prefix . 'course';

        // Get the course categories.
        $cats = get_config('enrol_d1', 'categories');

        // Build the SQL.
        $sql = 'SELECT d1e.courseidnumber AS courseidnumber, d1e.courseid AS courseid'
               . ' FROM ' . $etable . ' d1e'
               . ' INNER JOIN ' . $ctable . ' c'
               . ' ON c.id = d1e.courseid'
               . ' WHERE c.category IN(' . $cats . ')'
               . ' GROUP BY d1e.courseidnumber, d1e.courseid';

        // Get the list of qualifying course ids.
        $courseids = $DB->get_records_sql($sql);

        // Return the array.
        return $courseids;
    }

    /**
     * Master function to grab the D1 Student Enrollments for a given course.
     *
     * @param  @string $token
     * @param  @object $course
     * @return @array of @objects $enrolledstus
     */
    public static function set_student_enrollments($token, $course) {
        // Short circuit things if we don't have a token.
        if ($token == false) {
            return;
        }

        // Get the course name from the idnumber.
        $cn = self::get_coursename($course->idnumber);

        // Get the course section number from the idnumber.
        $cs = self::get_mcoursesection($course->idnumber);

        // Grab the enrollments based on the given info.
        $enrollments = self::get_section_enrollment($token, $cn, $cs);

        // If we have enrollments, start building the array of enrollment objects.
        if (!empty($enrollments->getClassListResult->studentListItems)) {
            // Set the sectionid.
            $sectionid = self::get_section_objectid($enrollments);

            // Get the section data based on the sectionid above.
            $section   = self::get_coursesection($token, $sectionid);

            // Grab the student enrollments subobjects.
            $students  = self::get_studentenrollments($enrollments);

            // Loop through the students and grab the studentlistitems.
            $enrolledstus = array();
            foreach ($students as $slis) {
                // If there is more than one, D1 returns an array, if 1, returns a subobject.
                $sli = is_array($slis) ? $slis : array($slis);

                // Loop through the studentlistitems.
                foreach ($sli as $studentlistitem) {

                    // Get the username for this student.
                    $username = self::get_username($token, $studentlistitem->studentId);

                    // Convert the start date to unix timestamp.
                    $sd = DateTime::createFromFormat('d M Y h:i:s A', $studentlistitem->enrollmentDate, new DateTimeZone('America/Chicago'));
                    $startdate = date_timestamp_get($sd);

                    // Conditionally get the end date if it's set.
                    if (isset($section->getCourseSectionResult->courseSection->sectionDueDateRule->daysAfterEnroll)) {
                        // Grab the number of days the section enrollment is valid.
                        $daysafter = $section->getCourseSectionResult->courseSection->sectionDueDateRule->daysAfterEnroll;

                        // Grab the student enrollment start date.
                        $sdate = $studentlistitem->enrollmentDate;

                        // Create the date based on the start date.
                        $date = date_create("$sdate");

                        // Add the $daysafter to the start date.
                        date_add($date,date_interval_create_from_date_string($daysafter . " days"));

                        // Format the date as requested.
                        $edate = date_format($date,"d M Y h:i:s A");

                        // Set up the date to convert it to unix timestamp.
                        $ed = DateTime::createFromFormat('d M Y h:i:s A', $edate, new DateTimeZone('America/Chicago'));

                        // Convert the date to unix timestamp.
                        $enddate = date_timestamp_get($ed);
                    } else {
                        $edate = "";
                        $enddate = 0;
                    }
                    $lsuid = isset($studentlistitem->studentScoolPersonnelNumber) ? $studentlistitem->studentScoolPersonnelNumber : null;

                    // Build the enrolled object for this student.
                    $enrolled = new stdClass();
                    $enrolled->section      = $course->idnumber;
                    $enrolled->firstname    = $studentlistitem->studentFirstName;
                    $enrolled->lastname     = $studentlistitem->studentLastName;
                    $enrolled->d1id         = $studentlistitem->studentId;
                    $enrolled->xnumber      = $studentlistitem->studentNumber;
                    $enrolled->lsuid        = $lsuid;
                    $enrolled->username     = $username->getStudentResult->student->loginId;
                    $enrolled->email        = $studentlistitem->studentPreferredEmail;
                    $enrolled->enrollstart  = $studentlistitem->enrollmentDate;
                    $enrolled->startstamp   = $startdate;
                    $enrolled->enrollend    = isset($edate) ? $edate : null;
                    $enrolled->endstamp     = $enddate;
                    $enrolled->enrollstatus = $studentlistitem->enrollmentStatus;

                    // Populate the array.
                    $enrolledstus[]         = $enrolled;
                }
            }
        } else {
            $enrolledstus[] = null;
        }

        return $enrolledstus;
    }

    /**
     * Grabs the section enrollment from D1 web services.
     *
     * @param  @string $token
     * @param  @string $course
     * @param  @string $section
     * @return @array of @objects $response
     */
    public static function get_section_enrollment($token, $course, $section) {
        // Short circuit things if we don't have a token.
        if ($token == false) {
            return;
        }

        // Get the data needed.
        $s = self::get_d1_settings();

        // Set the URL.
        $url = $s->wsurl . '/webservice/InternalViewRESTV2/getClassList?_type=json&overrideMaxResults=Y&sessionId=' . $token;

        // Set the POST body.
        $body = '{"getClassListRequestDetail": {"courseNumber": "' . $course . '","sectionNumber": "' . $section . '"}}';

        // Set the POST header.
        $header = array('Content-Type: application/json',
                'sessionId: ' . $token);

        $curl = curl_init($url);

        // Set the CURL options.
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        // Gett the JSON response.
        $json_response = curl_exec($curl);

        // Set the HTTP code for debugging.
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close the CURL handler.
        curl_close($curl);

        // Decode the response.
        $response = json_decode($json_response);

        if ($s->debugging == 1) {
            // Build the name of the course JSON.
            $str1 = $course;
            $str2 = $section;
            $pattern = '/ /i';
            $stype = preg_replace($pattern, '_', $str1);
            $sparm = preg_replace($pattern, '_', $str2);

            // Write this out to a file for debugging.
            $fp = fopen($stype . '-' . $sparm . '.json', 'w');
            fwrite($fp, $json_response);
            fclose($fp);
        }

        // Return the response.
        return($response);
    }

    /**
     * Grabs the course section for a given sectionid from the D1 web services.
     *
     * @param  @string $token
     * @param  @string $sectionid
     * @return @object $response
     */
    public static function get_coursesection($token, $sectionid) {
        // Short circuit things if we don't have a token.
        if ($token == false) {
            return;
        }

        // Get the data needed.
        $s = self::get_d1_settings();

        // Set the url. 
        $url = $s->wsurl . '/webservice/InternalViewRESTV2/courseSection/objectId/' . $sectionid . '?_type=json&informationLevel=Full&locale=en_US';

        // Set the get header.
        $header = array('Content-Type: application/json', 'sessionId: ' . $token);

        // Initiate the curl handler.
        $curl = curl_init($url);

        // Se the CURL options.
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        // Get the response.
        $json_response = curl_exec($curl);

        // Set the HTTP code for debugging.
        $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        // Close the curl handler.
        curl_close($curl);

        // Decode the response.
        $response = json_decode($json_response);

        if ($s->debugging == 1) {
            // Write this out to a file for debugging.
            $fp = fopen('D1_Section-' . $sectionid . '.json', 'w');
            fwrite($fp, $json_response);
            fclose($fp);
        }

        // Return the response.
        return $response;
    }

    /**
     * Grabs the username for a given user from the D1 web services.
     *
     * @param  @string $token
     * @param  @string $d1id
     * @return @object $response
     */
    public static function get_username($token, $d1id) {
        // Short circuit things if we don't have a token.
        if ($token == false) {
            return;
        }

        // Get the data needed.
        $s = self::get_d1_settings();

        // Set the URL.
        $url = $s->wsurl . '/webservice/InternalViewRESTV2/student/objectId/' . $d1id . '?_type=json&informationLevel=full';

        // Set the header.
        $header = array('Content-Type: application/json', 'sessionId: ' . $token);

        // Initiate the curl handler.
        $curl = curl_init($url);

        // Se the CURL options.
        curl_setopt($curl, CURLOPT_HEADER, false);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_POST, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);

        // Get the response.
        $json_response = curl_exec($curl);

        // close the curl handler.
        curl_close($curl);

        // Decode the response.
        $response = json_decode($json_response);

        if ($s->debugging == 1) {
            // Write this out to a file for debugging.
            $fp = fopen('D1_User-' . $d1id . '.json', 'w');
            fwrite($fp, $json_response);
            fclose($fp);
        }

        // Return the response.
        return $response;
    }

    /**
     * Populates the D1 student table.
     *
     * @param  @object $enrollment
     * @return @object $response
     */
    public static function populate_stu_db($enrollment) {
        global $DB;

        // Set up the needed vars.
        $table  = 'enrol_d1_students';
        $sort   = '';
        $fields = '*';
        $wheres = "userid IS NULL";

        // Build the student object.
        $student = new stdClass();
        $student->username     = $enrollment->username;
        $student->idnumber     = $enrollment->xnumber;
        $student->d1id         = $enrollment->d1id;
        $student->lsuid        = $enrollment->lsuid;
        $student->email        = $enrollment->email;
        $student->firstname    = $enrollment->firstname;
        $student->lastname     = $enrollment->lastname;
        $student->timemodified = time();

        // Build the conditions to get some users.
        $uconditions = array("username" => $student->username);
        $iconditions = array("idnumber" => $student->idnumber);
        $dconditions = array("d1id"     => $student->d1id);
        $econditions = array("email"    => $student->email);
        $lconditions = array("lsuid"    => $student->lsuid);

        // Set up the users array and grab users if they exist.
        $existing = array();
        $existing['username'] = $DB->get_record($table, $uconditions, $fields='*', $strictness=IGNORE_MISSING);
        $existing['idnumber'] = $DB->get_record($table, $iconditions, $fields='*', $strictness=IGNORE_MISSING);
        $existing['d1id']     = $DB->get_record($table, $dconditions, $fields='*', $strictness=IGNORE_MISSING);
        $existing['email']    = $DB->get_record($table, $econditions, $fields='*', $strictness=IGNORE_MISSING);

        // Loop through this funky stuff and update user info as needed.
        foreach ($existing as $key => $u0) {
            // If we have a matching user, upddate them or grab the user object.
            if (isset($u0->id)) {
                // Set this so we can update the user entry if needed.
                $student->id = $u0->id;
                // Now we can compare username, idnumber, email, first and last names.
                mtrace("    User: $student->username with matching $key exsits from search.");
                if (($student->username <> $u0->username
                  || $student->idnumber <> $u0->idnumber
                  || $student->email <> $u0->email
                  || $student->d1id <> $u0->d1id
                  || $student->firstname <> $u0->firstname
                  || $student->lastname <> $u0->lastname)
                  && $DB->update_record($table, $student, $bulk=false)) {
                    $uo = $DB->get_record($table, array("id"=>$student->id), $fields='*', $strictness=IGNORE_MISSING);
                    mtrace("      Updated user with username: $uo->username, idnumber: $uo->idnumber, email: $uo->email, and name: $uo->firstname $uo->lastname.");
                // User object matches stored data, grab the object.
                } else {
                    $uo = $DB->get_record($table, array("id"=>$student->id), $fields='*', $strictness=IGNORE_MISSING);
                    mtrace("      User: $student->username matched all parts of the existing user object.");
                }
                break 1;
            }
        }
        // If we have a user object, return it.
        if (isset($uo->id)) {
            // Return the user object.
            return $uo;
        } else {
            // We do not have a matching or existing user, please create one.
            mtrace("      New user will be created with username: $student->username, idnumber: $student->idnumber, fn: $student->firstname, ln: $student->lastname.");
            $id = $DB->insert_record($table, $student, $returnid=true, $bulk=false);
            mtrace("        Created new user with username: $student->username, idnumber: $student->idnumber, fn: $student->firstname, ln: $student->lastname.");

            // Grab the newly created user object and return it.
            $uo = $DB->get_record($table, array("id"=>$id), $fields='*', $strictness=IGNORE_MISSING);

            // Return the user object.
            return $uo;
        }
    }

    /**
     * Sets enroll statuses prior to running enrollment for the same course/section.
     *
     * @param  @object $enrollment
     * @return @bool
     */
    public static function insert_or_update_enrolldb($enrollment) {
    global $CFG, $DB;

    // Build the table names.
    $etable = 'enrol_d1_enrolls';
    $stable = 'enrol_d1_students';

    // SQL for checking to see if we have a full enrollment record.
    $sql = 'SELECT e.id, e.studentsid FROM '
           . $CFG->prefix . $etable
           . ' e INNER JOIN '
           . $CFG->prefix . $stable
           . ' s ON e.studentsid = s.id'
           . ' WHERE e.courseidnumber = "'
           . $enrollment->section
           . '" AND s.d1id = "'
           . $enrollment->d1id
           . '" AND s.idnumber = "'
           . $enrollment->xnumber
           . '" AND s.username = "'
           . $enrollment->username
           . '" AND s.email = "'
           . $enrollment->email . '"';

    // Get both the enrollment and student record id.
    $eid = $DB->get_record_sql($sql);

    // If we don't have the student id, get it.
    if (!isset($eid->studentsid)) {
        // Set the requirements for grabbing the studentsid.
        $reqs = array(
                    'd1id' => $enrollment->d1id,
                    'idnumber' => $enrollment->xnumber,
                    'username' => $enrollment->username,
                    'email' => $enrollment->email
                );

        // Grab the student id.
        $sid       = $DB->get_record($stable, $reqs, $fields = 'id');

        // Set it so we're not dealing with an object.
        $studentid = $sid->id;
    } else {
        // Set it so we're not dealing with an object.
        $studentid = $eid->studentsid;
    }

    // Build the data array.
    $data = array();
    $data['studentsid']     = $studentid;
    $data['courseidnumber'] = $enrollment->section;
    $data['status']         = 'enroll';
    $data['enrollstart']    = $enrollment->startstamp;
    $data['enrollend']      = $enrollment->endstamp;
    $data['timemodified']   = time();

    // Set the search requirements.
    $ereqs = array('studentsid' => $studentid, 'courseidnumber' => $enrollment->section);

    // If we already have an enrollment id OR are able to set one.
    if (isset($eid->id) || $eid = $DB->get_record($etable, $ereqs, $fields = 'id')) {
        $data['id'] = $eid->id;
        mtrace("      We found an existing enrollment, update it.");

        // Update the enrollment record.
        $updated = $DB->update_record($etable, $data);
    } else {
       mtrace("      We found a new enrollemt, add it to the table.");

       // Insert the enrollment record.
       $inserted = $DB->insert_record($etable, $data, $returnid=true);
    }

    // Set the return result based on what we have.
    $result = isset($updated) ? $eid->id : $inserted;

    // Return the id of the inserted or updated row.
    return $result;
    }

    /**
     * Sets enroll statuses prior to running enrollment for the same course/section.
     *
     * @param  @int    $courseid
     * @param  @int    $userid
     * @param  @string $enrollstatus
     * @return @bool
     */
    public static function updated1db($courseid, $userid, $enrollstatus) {
    global $CFG, $DB;

    // Build the table name.
    $etable = $CFG->prefix . 'enrol_d1_enrolls';
    $stable = $CFG->prefix . 'enrol_d1_students';

    // Generate the SQL.
    $sql = 'UPDATE '
           . $etable
           . ' d1e INNER JOIN '
           . $stable
           . ' d1s ON d1s.id = d1e.studentsid'
           . ' SET d1e.status = "'
           . $enrollstatus . 'ed'
           . '", d1e.timemodified = UNIX_TIMESTAMP()'
           . ' WHERE d1e.courseid = '
           . $courseid
           . ' AND d1s.userid = '
           . $userid;

    // Do the nasty.
    $result = $DB->execute($sql);

    // Return success or failure.
    return $result;
    }

    /**
     * Gets courseidnumbers without course ids from the d1 enroll table.
     *
     * @return @int $courseidnumbers
     */
    public static function get_courseidnumbers_noid() {
        global $DB;

        // Build the table name.
        $table  = 'enrol_d1_enrolls';

        // Set the fields.
        $fields = 'courseidnumber';

        // Set the wehres.
        $wheres = "courseid IS NULL GROUP BY $fields";

        // Get the course object.
        $cidns = $DB->get_records_select($table, $wheres, null, $sort='', $fields);

        // Set this array up.
        $courseidnumbers = array();

        // Build a boring array.
        foreach ($cidns as $cidn) {
            $courseidnumbers[] = $cidn->courseidnumber;
        }

        // Return the courseidnumbers.
        return $courseidnumbers;
    }

    /**
     * Sets matching courseid where we have a corresponding course idnumber.
     *
     * @param  @string $courseidnumber
     * @return @bool
     */
    public static function set_courseids($courseidnumber) {
        global $CFG, $DB;

        // Get the courseid.
        $courseid = self::get_courseid($courseidnumber);

        // Build the table name.
        $d1table = $CFG->prefix . 'enrol_d1_enrolls';

        // Generate the SQL.
        $sql = 'UPDATE '
           . $d1table
           . ' SET courseid = "'
           . $courseid
           . '" WHERE courseidnumber = "'
           . $courseidnumber . '"';

        // Do the nasty.
        if ($courseid > 0) {
            $result = $DB->execute($sql);
        } else {
            $result = false;
        }

        // Return success or failure.
        return $result;
    }

    /**
     * Gets matching courseid where we have a corresponding course idnumber.
     *
     * @param  @int $courseidnumber
     * @return @int $courseid
     */
    public static function get_courseid($courseidnumber) {
        global $DB;

        // Build the table name.
        $table = 'course';

        // Set the conditions.
        $conditions = 'idnumber = "' . $courseidnumber . '"';

        // Get the course object.
        $course = $DB->get_record_select($table, $conditions, null, $fields='*', $strictness=IGNORE_MISSING);

        // Set the id.
        $courseid = isset($course->id) ? $course->id : 0;

        // Return the courseid.
        return $courseid;
    }

    /**
     * Gets the list of students in the d1 students table.
     *
     * @return @array of @objects
     */
    public static function get_d1students($all) {
        global $CFG, $DB;

        // Set up the needed vars.
        $table  = 'enrol_d1_students';
        $sort   = '';
        $fields = '*';
        $wheres = $all == false ? "userid IS NULL" : "";

        // Get the course object.
        $d1students = $DB->get_records_select($table, $wheres, null, $sort, $fields);

        // Return the data
        return $d1students;
    }

    /**
     * Creates or updates users as needed.
     *
     * @param  @object $d1student
     * @return @object $uo
     */
    public static function create_update_user($d1student, $courseid=null) {
        global $CFG, $DB;

        // Grab the table for future use.
        $table = 'user';

        // Build the auth methods and choose the default one.
        $auth = explode(',', $CFG->auth);
        $auth = reset($auth);

        // Set up the user object.
        $user               = new stdClass();
        $user->username     = $d1student->username;
        $user->idnumber     = $d1student->idnumber;
        $user->email        = $d1student->email;
        $user->firstname    = $d1student->firstname;
        $user->lastname     = $d1student->lastname;
        $user->lang         = $CFG->lang;
        $user->auth         = $auth;
        $user->confirmed    = 1;
        $user->timemodified = time();
        $user->mnethostid   = $CFG->mnet_localhost_id;

        // Build the conditions to get some users.
        $uconditions = array("username"=>$d1student->username);
        $iconditions = array("idnumber"=>$d1student->idnumber);
        $econditions = array("email"=>$d1student->email);

        // Set up the users array and grab users if they exist.
        $users = array();

        $users['username'] = $DB->get_record($table, $uconditions, $fields='*', $strictness=IGNORE_MISSING);
        $users['idnumber'] = $DB->get_records($table, $iconditions, 'id', $fields='*');
        if (is_array($users['idnumber']) && count($users['idnumber']) > 1) {
            if (!isset($courseid)) {
                mtrace('Found more than one user for idnumber: ' . $d1student->idnumber);
            }
            foreach($users['idnumber'] as $idn) {
                if (!isset($courseid)) {
                    mtrace('  IDNumber: ' . $d1student->idnumber . ' = Username: ' . $idn->username . ' = ID: ' . $idn->id);
                }
            }
            unset($users['idnumber']);
        } else {
            $users['idnumber'] = reset($users['idnumber']);
        }
        $users['email']    = $DB->get_records($table, $econditions, 'id', $fields='*');
        if (is_array($users['email']) && count($users['email']) > 1) {
            if (!isset($courseid)) {
                mtrace('Found more than one user for email: ' . $d1student->email);
            }
            foreach($users['email'] as $idn) {
                if (!isset($courseid)) {
                    mtrace('  Email: ' . $d1student->email . ' = Username: ' . $idn->username . ' = ID: ' . $idn->id);
                }
            }
            unset($users['email']);
        } else {
            $users['email'] = reset($users['email']);
        }

        // Loop through this funky stuff and update user info as needed.
        foreach ($users as $key => $u0) {
            // If we have a matching user, upddate them or grab the user object.
            if (isset($u0->id)) {
                // Set this so we can update the user entry if needed.
                $user->id = $u0->id;
                // Now we can compare username, idnumber, email, first and last names.
                if (!isset($courseid)) {
                    mtrace("    User: $user->username with matching $key exsits from search.");
                }
                if (($user->username <> $u0->username
                  || $user->idnumber <> $u0->idnumber
                  || $user->email <> $u0->email
                  || $user->firstname <> $u0->firstname
                  || $user->lastname <> $u0->lastname)
                  && $DB->update_record($table, $user, $bulk=false)) {
                    $uo = $DB->get_record($table, array("id"=>$user->id), $fields='*', $strictness=IGNORE_MISSING);
                    if (!isset($courseid)) {
                        mtrace("    Updated user with username: $uo->username, idnumber: $uo->idnumber, email: $uo->email, and name: $uo->firstname $uo->lastname.");
                    }
                // User object matches stored data, grab the object.
                } else {
                    $uo = $DB->get_record($table, array("id"=>$user->id), $fields='*', $strictness=IGNORE_MISSING);
                    if (!isset($courseid)) {
                        mtrace("    User ID: $user->id matched all parts of the user object.");
                    }
                }
                // Update the userid of the d1student table JIC.
                $d1student->userid = $uo->id;
                $DB->update_record('enrol_d1_students', $d1student);
                // If we found a user and did all the above, exit the loop.
                break;
            }
        }

        // If we have a user object, return it.
        if (isset($uo->id)) {
            return $uo;
        } else {
            // We do not have a matching or existing user, please create one.
            $id = $DB->insert_record($table, $user, $returnid=true, $bulk=false);
            if (!isset($courseid)) {
                mtrace("Created userid: $id,  username: $user->username, idnumber: $user->idnumber, fn: $user->firstname, ln: $user->lastname.");
            }

            // Grab the newly created user object and return it.
            $uo = $DB->get_record($table, array("id"=>$id), $fields='*', $strictness=IGNORE_MISSING);

            // Update the userid of the d1student table JIC.
            $d1student->userid = $uo->id;
            $DB->update_record('enrol_d1_students', $d1student);
            return $uo;
        }
    }

    /**
     * Get the actionable d1 enrollment data.
     *
     * @param  @string $courseid
     * @return @array of @objects $enrolldata
     */
    public static function get_d1enrolls($courseid) {
    global $CFG, $DB;

    // Build the table name.
    $etable = $CFG->prefix . 'enrol_d1_enrolls';
    $stable = $CFG->prefix . 'enrol_d1_students';

    // Generate the SQL.
    $sql = 'SELECT d1e.id, d1s.userid, d1e.courseid, '
           . ' d1e.courseidnumber, d1e.status, '
           . ' d1e.enrollstart, d1e.enrollend '
           . ' FROM ' . $etable
           . ' d1e INNER JOIN ' . $stable
           . ' d1s ON d1s.id = d1e.studentsid '
           . ' WHERE d1e.courseid = "'
           . $courseid . '"'
           . ' AND d1e.status IN ("enroll", "unenroll")';

    // Do the nasty.
    $enrolldata = $DB->get_records_sql($sql);

    // Return success or failure.
    return $enrolldata;
    }

    /**
     * Sets enroll statuses prior to running enrollment for the same course/section.
     *
     * @param  @string $courseidnumber
     * @param  @string $enrollstatus
     * @return @bool
     */
    public static function prestage_drops($courseidnumber, $enrollstatus) {
    global $CFG, $DB;

    // Build the table name.
    $table = $CFG->prefix . 'enrol_d1_enrolls';

    // Generate the SQL.
    $sql = 'UPDATE '
           . $table
           . ' SET status = "'
           . $enrollstatus
           . '" WHERE courseidnumber = "'
           . $courseidnumber . '"';

    // Do the nasty.
    $result = $DB->execute($sql);

    // Return success or failure.
    return $result;
    }

    /**
     * Enrolls a student and sets the appropriate status and time.
     *
     * @param  @int    $courseid
     * @param  @int    $userid
     * @param  @string $enrollstatus
     * @param  @int    $time
     * @return @object $status
     */
    public static function d1_enrollment($courseid, $userid, $enrollstatus, $enrollstart, $enrollend) {
        global $CFG, $DB;

        // Instantiate the enroller.
        $enroller = new enrol_d1;

        // Grab the role id if one is present, otherwise use the Moodle default.
        $roleid = isset($CFG->enrol_d1_studentrole) ? $CFG->enrol_d1_studentrole : 5;

        // Set the time in seconds from epoch.
        $time = time();

        // Add or remove this student or teacher to the course...
        $stu = new stdClass();
        $stu->userid = $userid;
        $stu->enrol = 'd1';
        $stu->course = $courseid;
        $stu->time = $time;
        $stu->timemodified = $time;

        // Set this up for getting the enroll instance.
        $etable      = 'enrol';
        $econditions = array('courseid' => $courseid, 'enrol' => $stu->enrol);

        // Get the enroll instance.
        $einstance   = $DB->get_record($etable, $econditions);

        // Set this up for getting the course.
        $ctable      = 'course';
        $cconditions = array('id' => $courseid);

        // Get the course object.
        $course      = $DB->get_record($ctable, $cconditions);

        // If we do not have an existing enrollment instance, add it.
        if (empty($einstance)) {
            $enrollid = $enroller->add_instance($course);
            $einstance = $DB->get_record('enrol', array('id' => $enrollid));
            mtrace("    Enroll instance for $einstance->enrol with ID: $einstance->id created.");
        } else {
            mtrace("    Existing enrollment instance for $einstance->enrol with ID: $einstance->id already here.");
        }

        // Determine if we're removing or suspending oa user on unenroll.
        $unenroll = get_config('enrol_d1', 'unenroll');

        // If we're unenrolling a student.
        if ($enrollstatus == "unenroll") {
            // If we're removing them from the course.
            if ($unenroll == 1) {
                // Do the nasty.
                $enrolluser   = $enroller->unenrol_user(
                                    $einstance,
                                    $stu->userid);
                mtrace("User $stu->userid unenrolled from course: $stu->course.");
            // Or we're suspending them.
            } else {
                // Do the nasty.
                $enrolluser   = $enroller->update_user_enrol(
                                    $einstance,
                                    $stu->userid, ENROL_USER_SUSPENDED);
                mtrace("    User ID: $stu->userid suspended from course: $stu->course.");
            }
        // If we're enrolling a student in the course.
        } else if ($enrollstatus == "enroll") {
            // Set the start date if it's there.
            $enrollstart = isset($enrollstart) ? $enrollstart : 0;
            // Set their end date if it's there.
            $enrollend = isset($enrollend) ? $enrollend : 0;
            // Do the nasty.
            $enrolluser = $enroller->enrol_user(
                              $einstance,
                              $stu->userid,
                              $roleid,
                              $enrollstart,
                              $enrollend,
                              $status = ENROL_USER_ACTIVE);
            mtrace("    User ID: $stu->userid enrolled into course: $stu->course.");
        }

        // Update the D1 enrollment table with the appropriate action status.
        $updated1db = self::updated1db($courseid, $userid, $enrollstatus);

        // TODO: Log some errors and return them.
        // Return some stuff.
        return true;
    }

    /**
     * Gets the daily grade postings array.
     *
     * @return @array of @objects $odl_dgps
     */
    public static function get_odl_dgps($unique, $courseidnumber=null) {
        global $DB;

        // For getting courses, we only return distinct course records, otherwise we get all grade records.
        if ($unique) {
            $grouper = " GROUP BY coursenumber, sectionnumber";
        } else {
            $grouper = " GROUP BY coursenumber, sectionnumber, x_number, FinalLetterGrade";
        }

        // If a courseidnumber is present, only fetch enrollments for that course.
        if (isset($courseidnumber)) {
            $wheres = ' AND c.idnumber = "' . $courseidnumber . '"';
        } else {
            $wheres = '';
        }

        // Get the D1 settings data needed.
        $s = self::get_d1_settings();

        // Build the PD sql for fetching the requested data.
        $sql = 'SELECT CONCAT(c.idnumber, " ", d1s.idnumber, " ", gg.finalgrade) AS uniqer,
                  ccx.name,
                  SUBSTRING_INDEX(c.idnumber, "__", 1) AS coursenumber,
                  SUBSTRING_INDEX(c.idnumber, "__", -1) AS sectionnumber,
                  d1s.idnumber AS x_number,
                  IF(ls.id IS NULL,
                    (SELECT DISTINCT(letter) FROM mdl_grade_letters WHERE contextid = 1 AND lowerboundary = (SELECT(MAX(gl1.lowerboundary)) FROM mdl_grade_letters gl1 WHERE 1 = gl1.contextid AND gg.finalgrade / gg.rawgrademax * 100 >= gl1.lowerboundary)),
                    (SELECT DISTINCT(letter) FROM mdl_grade_letters WHERE contextid = ctx.id AND lowerboundary = (SELECT(MAX(gl1.lowerboundary)) FROM mdl_grade_letters gl1 WHERE ctx.id = gl1.contextid AND gg.finalgrade / gg.rawgrademax * 100 >= gl1.lowerboundary))
                  ) AS FinalLetterGrade,
                  DATE_FORMAT(CONVERT_TZ(FROM_UNIXTIME(sub.timemodified), @@GLOBAL.time_zone, "America/Chicago"), "%d %b %Y") AS FinalDate
                FROM mdl_course c
                  INNER JOIN mdl_course_categories ccx ON ccx.id = c.category
                  INNER JOIN mdl_enrol e ON e.courseid = c.id AND e.enrol = "d1"
                  INNER JOIN mdl_user_enrolments ue ON ue.enrolid = e.id
                  INNER JOIN mdl_user u ON u.id = ue.userid
                  INNER JOIN mdl_enrol_d1_students d1s ON d1s.username = u.username AND d1s.email = u.email
                  INNER JOIN mdl_enrol_d1_enrolls d1e ON d1s.id = d1e.studentsid AND d1e.courseid = c.id
                  INNER JOIN mdl_assign a ON a.course = c.id
                  INNER JOIN mdl_assign_submission sub ON sub.assignment = a.id AND sub.userid = u.id AND sub.status = "submitted" AND sub.latest = 1
                  INNER JOIN mdl_context ctx ON ctx.instanceid = c.id AND ctx.contextlevel = "50"
                  INNER JOIN mdl_grade_letters gl2 ON gl2.contextid = 1
                  INNER JOIN mdl_grade_items gi ON gi.courseid = c.id AND gi.itemtype = "course"
                  INNER JOIN mdl_grade_grades gg ON gg.itemid = gi.id AND u.id = gg.userid
                  INNER JOIN mdl_course_completions cc ON cc.course = c.id AND cc.userid = u.id
                  LEFT JOIN mdl_grade_letters ls ON ls.contextid = ctx.id
                  LEFT JOIN mdl_user_info_data ud ON ud.userid = u.id AND ud.fieldid = 3 
                WHERE gg.finalgrade IS NOT NULL
                  AND gg.finalgrade >= 0
                  AND cc.timecompleted IS NOT NULL
                  AND cc.reaggregate = 0
                  AND (a.name = "Final Examination" OR a.name = "Final Examination Verification" OR a.name = "Final Exam" OR a.name = "Final Quiz" OR a.name = "Final Capstone" OR a.name = "Capstone" OR a.name = "Final Project" OR a.name LIKE "Final Exam Part %" OR a.name LIKE "Final Exam V%")
                  AND u.deleted = 0
                  AND c.category NOT IN (
                    SELECT DISTINCT(cc.id) AS "CC_ID"
                    FROM mdl_course_categories cc
                      LEFT JOIN mdl_course_categories cc2 ON cc2.id = cc.parent
                      LEFT JOIN mdl_course_categories cc3 ON cc3.id = cc2.parent
                    WHERE IF(cc3.id IS NULL, IF(cc2.id IS NULL, cc.name, cc2.name), cc3.name) IN ("Professional Development")
                  )
                  AND c.category NOT IN (
                    SELECT DISTINCT(cc.id) AS "CC_ID"
                    FROM mdl_course_categories cc
                  WHERE cc.name IN ("Archived")
                  )' . $wheres . $grouper . '

                UNION

                SELECT CONCAT(c.idnumber, " ", d1s.idnumber, " ", gg.finalgrade) AS uniqer,
                  ccx.name,
                  SUBSTRING_INDEX(c.idnumber, "__", 1) AS coursenumber,
                  SUBSTRING_INDEX(c.idnumber, "__", -1) AS sectionnumber,
                  d1s.idnumber AS x_number,
                  IF(ls.id IS NULL,
                    (SELECT DISTINCT(letter) FROM mdl_grade_letters WHERE contextid = 1 AND lowerboundary = (SELECT(MAX(gl1.lowerboundary)) FROM mdl_grade_letters gl1 WHERE 1 = gl1.contextid AND gg2.finalgrade / gg2.rawgrademax * 100 >= gl1.lowerboundary)),
                    (SELECT DISTINCT(letter) FROM mdl_grade_letters WHERE contextid = ctx.id AND lowerboundary = (SELECT(MAX(gl1.lowerboundary)) FROM mdl_grade_letters gl1 WHERE ctx.id = gl1.contextid AND gg2.finalgrade / gg2.rawgrademax * 100 >= gl1.lowerboundary))
                  ) AS FinalLetterGrade,
                  DATE_FORMAT(CONVERT_TZ(FROM_UNIXTIME(IF(qa.timefinish IS NULL, gg.timemodified, qa.timefinish)), @@GLOBAL.time_zone, "America/Chicago"), "%d %b %Y") AS FinalDate
                FROM mdl_course c
                  INNER JOIN mdl_course_categories ccx ON ccx.id = c.category
                  INNER JOIN mdl_course_categories cat ON cat.id = c.category
                  INNER JOIN mdl_course_completions cc ON cc.course = c.id
                  INNER JOIN mdl_user u ON cc.userid = u.id
                  INNER JOIN mdl_enrol_d1_students d1s ON d1s.username = u.username AND d1s.email = u.email
                  INNER JOIN mdl_enrol_d1_enrolls d1e ON d1s.id = d1e.studentsid AND d1e.courseid = c.id
                  INNER JOIN mdl_quiz q ON q.course = c.id
                  INNER JOIN mdl_grade_items gi ON gi.courseid = c.id AND gi.itemtype = "mod" AND gi.itemmodule = "quiz" AND gi.iteminstance = q.id
                  INNER JOIN mdl_grade_grades gg ON gg.itemid = gi.id AND gg.userid = u.id
                  INNER JOIN mdl_grade_items gi2 ON gi2.courseid = c.id AND gi2.itemtype = "course"
                  INNER JOIN mdl_grade_grades gg2 ON gg2.itemid = gi2.id AND gg2.userid = u.id
                  INNER JOIN mdl_context ctx ON ctx.instanceid = c.id AND ctx.contextlevel = "50"
                  LEFT JOIN mdl_grade_letters ls ON ls.contextid = ctx.id
                  LEFT JOIN mdl_quiz_attempts qa ON qa.quiz = q.id AND qa.userid = u.id AND qa.state = "finished"
                WHERE gi2.itemtype = "course"
                  AND gg2.finalgrade IS NOT NULL
                  AND gg.finalgrade >= 0
                  AND gg.finalgrade IS NOT NULL
                  AND gg2.finalgrade >= 0
                  AND ctx.contextlevel = "50"
                  AND cc.timecompleted IS NOT NULL
                  AND (q.name = "Final Examination" OR q.name = "Final Exam" OR q.name = "Final Examination Verification" OR q.name = "Final Quiz" OR q.name = "Final Capstone" OR q.name = "Capstone" OR q.name = "Final Project" OR q.name LIKE "Final Exam Part %" OR q.name LIKE "Final Exam V%")
                  AND u.deleted = 0
                  AND u.idnumber IS NOT NULL
                  AND u.idnumber <> ""
                  AND c.category NOT IN (
                    SELECT DISTINCT(cc.id) AS "CC_ID"
                    FROM mdl_course_categories cc
                      LEFT JOIN mdl_course_categories cc2 ON cc2.id = cc.parent
                      LEFT JOIN mdl_course_categories cc3 ON cc3.id = cc2.parent
                    WHERE IF(cc3.id IS NULL, IF(cc2.id IS NULL, cc.name, cc2.name), cc3.name) IN ("Professional Development")
                  )
                  AND c.category NOT IN (
                    SELECT DISTINCT(cc.id) AS "CC_ID"
                    FROM mdl_course_categories cc
                    WHERE cc.name IN ("Archived")
                  )' . $wheres . $grouper;

        // Actually fetch the data.
        $odl_dgps = $DB->get_records_sql($sql);

        // Return the data.
        return $odl_dgps;
    }

    /**
     * Gets the daily grade postings array.
     *
     * @return @array of @objects $pd_dgps
     */
    public static function get_pd_dgps($unique, $courseidnumber=null) {
        global $DB;

        // For getting courses, we only return distinct course records, otherwise we get all grade records.
        if ($unique) {
            $grouper = " GROUP BY coursenumber, sectionnumber";
        } else {
            $grouper = " GROUP BY coursenumber, sectionnumber, x_number, FinalLetterGrade";
        }

        // If a courseidnumber is present, only fetch enrollments for that course.
        if (isset($courseidnumber)) {
            $wheres = ' AND c.idnumber = "' . $courseidnumber . '"';
        } else {
            $wheres = '';
        } 

        // Get the D1 settings data needed.
        $s = self::get_d1_settings();

        // Build the PD sql for fetching the requested data.
        $sql = 'SELECT CONCAT(c.idnumber, " ", d1s.idnumber, " ", gg2.finalgrade) AS uniqer,
                  SUBSTRING_INDEX(c.idnumber, "__", 1) AS coursenumber,
                  SUBSTRING_INDEX(c.idnumber, "__", -1) AS sectionnumber,
                  d1s.idnumber AS x_number,
                  IF (((((gg1.finalgrade/gg1.rawgrademax) >= .70) AND (gg2.finalgrade/gg2.rawgrademax) >= .70)), "Pass", "Fail") AS FinalLetterGrade,
                  CONCAT(DATE_FORMAT(CONVERT_TZ(FROM_UNIXTIME(IF(cmc.timemodified > 0, cmc.timemodified, gg1.timemodified)), @@GLOBAL.time_zone, "America/Chicago"), "%d %b %Y")) AS FinalDate
                FROM mdl_course c
                 INNER JOIN mdl_context ctx ON ctx.instanceid = c.id AND ctx.contextlevel = "50"
                 INNER JOIN mdl_grade_items gi1 ON gi1.courseid = c.id
                 INNER JOIN mdl_grade_items gi2 ON gi2.courseid = c.id AND gi2.itemtype = "course"
                 INNER JOIN mdl_enrol e ON e.courseid = c.id AND e.enrol = "d1"
                 INNER JOIN mdl_user_enrolments ue ON ue.enrolid = e.id
                 INNER JOIN mdl_grade_grades gg1 ON gg1.itemid = gi1.id
                 INNER JOIN mdl_grade_grades gg2 ON gg2.itemid = gi2.id
                 INNER JOIN mdl_user u ON ue.userid = u.id
                 INNER JOIN mdl_enrol_d1_students d1s ON d1s.username = u.username AND d1s.email = u.email
                 INNER JOIN mdl_enrol_d1_enrolls d1e ON d1s.id = d1e.studentsid AND d1e.courseid = c.id
                 INNER JOIN mdl_course_modules cm ON cm.course = c.id AND gi1.iteminstance = cm.instance
                 INNER JOIN mdl_modules m ON m.id = cm.module AND m.name = gi1.itemmodule
                 LEFT JOIN mdl_course_modules_completion cmc ON cmc.coursemoduleid = cm.id AND u.id = cmc.userid AND cmc.completionstate = 1
                 LEFT JOIN mdl_grade_letters ls ON ls.contextid = ctx.id
                 LEFT JOIN mdl_user_info_data uid ON uid.userid = u.id AND uid.fieldid = 3
               WHERE u.id = gg1.userid
                 AND u.id = gg2.userid
                 AND c.fullname NOT LIKE "Master %"
                 AND gg1.finalgrade IS NOT NULL
                 AND (gi1.itemname = "Final Examination" OR gi1.itemname = "Final Exam" OR gi1.itemname = "Final Quiz" OR gi1.itemname = "Final Capstone" OR gi1.itemname = "Capstone" OR gi1.itemname = "Final Project" OR gi1.itemname LIKE "Final Exam Part %")
                 AND c.shortname NOT LIKE "LCCON.Online%"
                 AND c.shortname NOT LIKE "LCRES.Online%"
                 AND ((gg1.finalgrade/gg1.rawgrademax) >= .70)
                 AND ((gg2.finalgrade/gg2.rawgrademax) >= .70)
                 AND ((cmc.timemodified > 1640973599)
                   OR (gg1.timemodified > 1640973599)
                   OR (gg2.timemodified > 1640973599)
                 )
                 AND c.category IN (
                   SELECT DISTINCT(cc.id) AS "CC_ID"
                   FROM mdl_course_categories cc
                     LEFT JOIN mdl_course_categories cc2 ON cc2.id = cc.parent
                     LEFT JOIN mdl_course_categories cc3 ON cc3.id = cc2.parent
                   WHERE IF(cc3.id IS NULL, IF(cc2.id IS NULL, cc.name, cc2.name), cc3.name) IN ("Professional Development")
                 )
                 AND c.category NOT IN (
                   SELECT DISTINCT(cc.id) AS "CC_ID"
                   FROM mdl_course_categories cc
                   WHERE cc.name IN ("Archived")
                 )' . $wheres . $grouper;

        // Actually fetch the data.
        $pd_dgps = $DB->get_records_sql($sql);

        // Return the data.
        return $pd_dgps;
    }

    /**
     * Posts grades for course completions.
     *
     * @param  @array of @objects $dgps
     * @return @bool  $response
     */
    public static function daily_grade_postings($dgps) {
        // Get the token for later.
        $token = self::get_token();

        // Loop through the daily grade postings array.
        foreach ($dgps as $dgp) {

            // Post the grade.
            $pg = self::post_update_grade($token, $dgp->xnumber, $dgp->csobjectid, $dgp->grade, $dgp->date);
        }
    }

    /**
     * Post / updates a grade for a user in a course.
     *
     * @param  @string $token
     * @param  @string $stunumber // X Number.
     * @param  @int    $csobjectid // Course objectId.
     * @param  @string $grade
     * @param  @string $date
     *
     * @return @bool   $response
     */
    public static function post_update_grade($token, $stunumber, $csobjectid, $grade, $date) {
        // Get the data needed.
        $s = self::get_d1_settings();

        // Set the URL for the post command to post a grade for a student
        $url = $s->wsurl . '/webservice/InternalViewRESTV2/createOrUpdateStudentFinalGrade?_type=json';

        // Set the POST body.
        $body = '{"createOrUpdateStudentFinalGradeRequestDetail":
                 {"studentGrade": {
                  "completionDate": "' . $date . '",
                  "isInstructorApproved": "Yes",
                  "isProgramApproved": "Yes",
                  "isRegistrarApproved": "Yes",
                  "gradingSheet": 
                 {"courseSectionProfile":
                 {"objectId": "' . $csobjectid . '"}},"student":
                 {"personNumber": "' . $stunumber . '"},"studentGradeItems":
                 {"studentGradeItem": {"grade": "' . $grade . '"}}}}}';

        // Set the POST header.
        $header = array('Content-Type: application/json',
                'sessionId:' . $token);

        // Set up the CURL handler.
        $curl = curl_init($url);

        // Set the CURL options.
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
}

/**
 * LSU DestinyOne webservice enrollment plugin.
 *
 */
class enrol_d1 extends enrol_plugin {

    /**
     * Add new instance of enrol plugin.
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public static function add_enroll_instance($course) {
        return $instance;
    }
}
