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

defined('MOODLE_INTERNAL') or die();

/**
 * This function extends the course navigation with the bulkenrol item
 *
 * @param  @object $navigation
 * @param  @object $course
 * @param  @object $context
 * @return @object $navigation node
 */
function local_d1_extend_navigation_course($navigation, $course, $context) {
    // Make sure we can reprocess enrollments.
    if (has_capability('local/d1:postgrades', $context)) {

        // Set the url for the reprocesser.
        $url = new moodle_url('/local/d1/postgrades.php', array('courseid' => $course->id));

        // Build the navigation node.
        $d1pgnode = navigation_node::create(get_string('pluginname', 'local_d1'), $url,
                navigation_node::TYPE_SETTING, null, 'local_d1', new pix_icon('i/upload', ''));

        // Set the users' navigation node.
        $usersnode = $navigation->get('users');

        // If we have an reprocess node, add it to the users' node.
        if (isset($d1pgnode) && !empty($usersnode)) {

            // Actually add the node.
            $usersnode->add_node($d1pgnode);
        }
    }
}

/**
 * d1 lib.
 *
 * Class for building scheduled task functions
 * for fixing core and third party issues
 *
 * @package    local_d1
 * @copyright  2022 Robert Russo, Louisiana State University
 */
class d1 {

    public $emaillog;

    /**
     * Master function for ODL grade posting
     *
     * @return boolean
     */
    public function run_post_odl() {
        require_once('classes/d1.php');

        // Get a token.
        $token = lsud1::get_token();

        // Get the list of courses to be posted.
        $odlcourses = lsud1::get_odl_dgps(true);

        // Loop through those courses.
        foreach ($odlcourses as $odlcourse) {

            // Get the Course Section ObjectId for the section for posting grades.
            $csobjectid = lsud1::get_cs_objectid($odlcourse->coursenumber, $odlcourse->sectionnumber);

            // Contruct the courseidnumber for the course.
            $courseidnumber = $odlcourse->coursenumber . '__' . $odlcourse->sectionnumber;

            // Log the posting for this course.
            mtrace("\nPosting grades for " . $courseidnumber . " - Objectid: " . $csobjectid);

            // Get the PD grades for the course in question.
            $odlgrades = lsud1::get_odl_dgps(false, $courseidnumber);

            // Start the counter.
            $count = 0;

            // Loop through the grades to post.
            foreach ($odlgrades as $odlgrade) {

                // Increment the counter.
                $count++;

                // Set the cs objectId to the PD Grade object.
                $odlgrade->csobjectid = $csobjectid;

                // Log the actual posting.
                mtrace("  $count: Posting \"$odlgrade->finallettergrade\" from ($odlgrade->finaldate) in $courseidnumber with ObjectId $odlgrade->csobjectid for student $odlgrade->x_number.");

                // Post the grade.
                $post = lsud1::post_update_grade($token, $odlgrade->x_number, $odlgrade->csobjectid, $odlgrade->finallettergrade, $odlgrade->finaldate);

                // If we were successful or not, log it.
                if (isset($post->createOrUpdateStudentFinalGradeResult)) {
                    mtrace("    Posted \"$odlgrade->finallettergrade\" for $odlgrade->x_number with status of \"" . $post->createOrUpdateStudentFinalGradeResult->status . "\".");
                } else {
                    mtrace("    Unable to post \"$odlgrade->finallettergrade\" for $odlgrade->x_number in course $courseidnumber with status of " . $post->SRSException->message);
                }

                // Log that we're done with this student's posting.
                mtrace("  $count: Completed Posting for student $odlgrade->x_number in course $courseidnumber.");
            }

            // Log that we're done posting this course grades.
            mtrace("Posted grades for " . $courseidnumber . " - Objectid: " . $csobjectid);
        }
        return true;
    }


    /**
     * Master function for PD grade posting.
     *
     * @return boolean
     */
    public function run_post_pd() {
        require_once('classes/d1.php');

        // Get a token.
        $token = lsud1::get_token();

        // Get the list of courses to be posted.
        $pdcourses = lsud1::get_pd_dgps(true);

        // Loop through those courses.
        foreach ($pdcourses as $pdcourse) {

            // Get the Course Section ObjectId for the section for posting grades.
            $csobjectid = lsud1::get_cs_objectid($pdcourse->coursenumber, $pdcourse->sectionnumber);

            // Contruct the courseidnumber for the course.
            $courseidnumber = $pdcourse->coursenumber . '__' . $pdcourse->sectionnumber;

            // Log the posting for this course.
            mtrace("\nPosting grades for " . $courseidnumber . " - Objectid: " . $csobjectid);

            // Get the PD grades for the course in question.
            $pdgrades = lsud1::get_pd_dgps(false, $courseidnumber);

            // Start the counter.
            $count = 0;

            // Loop through the grades to post.
            foreach ($pdgrades as $pdgrade) {

                // Increment the counter.
                $count++;

                // Set the cs objectId to the PD Grade object.
                $pdgrade->csobjectid = $csobjectid;

                // Log the actual posting.
                mtrace("  $count: Posting \"$pdgrade->finallettergrade\" from ($pdgrade->finaldate) in $courseidnumber with ObjectId $pdgrade->csobjectid for student $pdgrade->x_number.");

                // Post the grade.
                $post = lsud1::post_update_grade($token, $pdgrade->x_number, $pdgrade->csobjectid, $pdgrade->finallettergrade, $pdgrade->finaldate);

                // If we were successful or not, log it.
                if (isset($post->createOrUpdateStudentFinalGradeResult)) {
                    mtrace("    Posted \"$pdgrade->finallettergrade\" for $pdgrade->x_number with status of \"" . $post->createOrUpdateStudentFinalGradeResult->status . "\".");
                } else {
                    mtrace("    Unable to post \"$pdgrade->finallettergrade\" for $pdgrade->x_number in course $courseidnumber with status of " . $post->SRSException->message);
                }

                // Log that we're done with this student's posting.
                mtrace("  $count: Completed Posting for student $pdgrade->x_number in course $courseidnumber.");
            }

            // Log that we're done posting this course grades.
            mtrace("Posted grades for " . $courseidnumber . " - Objectid: " . $csobjectid);
        }
        return true;
    }


    /**
     * Master function for posting from a course.
     *
     * @return boolean
     */
    public function post_course_grades() {
    }

    /**
     * Emails a Post Grades report to admin users.
     *
     * @return void
     */
    private function email_olog_report_to_admins() {
        global $CFG;

        // Get email content from email log.
        $emailcontent = implode("\n", $this->emaillog);

        // Send to each admin.
        $users = get_admins();
        foreach ($users as $user) {
            $replyto = '';
            email_to_user($user, "ODL Post Grades", sprintf('Post ODL Grades to D1 for [%s]', $CFG->wwwroot), $emailcontent);
        }
    }

    /**
     * Emails a Post Grades report to admin users.
     *
     * @return void
     */
    private function email_plog_report_to_admins() {
        global $CFG;

        // Get email content from email log.
        $emailcontent = implode("\n", $this->emaillog);

        // Send to each admin.
        $users = get_admins();
        foreach ($users as $user) {
            $replyto = '';
            email_to_user($user, "PD Post Grades", sprintf('Post PD Grades to D1 for [%s]', $CFG->wwwroot), $emailcontent);
        }
    }

    /**
     * print during cron run and prep log data for emailling.
     *
     * @param $what
     */
    private function log($what) {
        mtrace($what);

        $this->emaillog[] = $what;
    }
}
