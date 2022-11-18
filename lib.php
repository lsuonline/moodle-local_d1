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
    }


    /**
     * Master function for PD grade posting.
     *
     * @return boolean
     */
    public function run_post_pd() {
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
