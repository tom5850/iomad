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
 * @package   local_iomad
 * @copyright 2021 Derick Turner
 * @author    Derick Turner
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/../../../config.php');
require_once(dirname(__FILE__) . '/iomad.php');
require_once(dirname(__FILE__) . '/user.php');

class company {

    public $id = 0;

    protected $companyrecord = null;

    // These are the fields that will be retrieved by.
    public $cssfields = array('bgcolor_header', 'bgcolor_content');

    public function __construct($companyid) {
        global $DB, $SESSION;

        $this->id = $companyid;
        if (!$this->companyrecord = $DB->get_record('company', ['id' => $this->id], '*')) {
            unset($SESSION->currenteditingcompany);
            unset($SESSION->company);
            unset($this->id);
            return;
        }
    }

    /**
     * Get selected fields
     * @param mixed fields string or array
     * @return mixed string or object (if array)
     */
    public function get($fields) {
        if (is_string($fields)) {
            if (isset($this->companyrecord->$fields)) {
                return $this->companyrecord->$fields;
            } else {
                throw new \Exception("Field not found in company record - " . $fields);
            }
        } else {
            $result = new \stdClass;
            foreach ($fields as $field) {
                if (property_exists($this->companyrecord, $field)) {
                    $result->$field = $this->companyrecord->$field;
                } else {
                    throw new \Exception("Field not found in company record - " . $field);
                }
            }
            return $result;
        }
    }

    /**
     * Return an instance of the class using the company shortname
     *
     * Paramters -
     *             $userid = int;
     *
     * Returns class object.
     *
     **/
    public static function by_userid($userid, $login = false) {
        global $DB, $SESSION;

        if (!$login && !empty($SESSION->currenteditingcompany)) {
            return new company($SESSION->currenteditingcompany);
        } else {
            if ($companies = $DB->get_records_sql("SELECT DISTINCT companyid,lastused
                                                   FROM {company_users}
                                                   WHERE userid = :userid
                                                   ORDER BY lastused, companyid DESC",
                                                  ['userid' => $userid])) {
                $company = array_shift($companies);
                return new company($company->companyid);
            } else {
                return false;
            }
        }
    }

    /**
     * Gets the company name for the current instance
     *
     * Returns text;
     *
     **/
    public function get_name() {
        return $this->companyrecord->name;
    }

    /**
     * Gets the company name for the current instance
     *
     * Returns text;
     *
     **/
    public function get_payment_account() {
        global $CFG;

        if (!empty($this->companyrecord->paymentaccount)) {
            return $this->companyrecord->paymentaccount;
        } else {
            return $CFG->commerce_admin_paymentaccount;
        }
    }

    /**
     * Gets the types of managers available to the class
     *
     * Returns array();
     *
     **/
    public function get_managertypes($full = false) {
        global $CFG;

        $returnarray = array('0' => get_string('user', 'block_iomad_company_admin'));
        $companycontext = \core\context\company::instance($this->id);
        if ($full || iomad::has_capability('block/iomad_company_admin:assign_company_manager', $companycontext)) {
            $returnarray['1'] = get_string('companymanager', 'block_iomad_company_admin');
        }
        if ($full || iomad::has_capability('block/iomad_company_admin:assign_department_manager', $companycontext)) {
            $returnarray['2'] = get_string('departmentmanager', 'block_iomad_company_admin');
        }
        if ($full || (!$CFG->iomad_autoenrol_managers && iomad::has_capability('block/iomad_company_admin:assign_educator', $companycontext))) {
            $returnarray['3'] = get_string('educator', 'block_iomad_company_admin');
        }
        if ($full || iomad::has_capability('block/iomad_company_admin:assign_company_reporter', $companycontext)) {
            $returnarray['4'] = get_string('companyreporter', 'block_iomad_company_admin');
        }
        return $returnarray;
    }

    /**
     * Gets the company short name for the current instance
     *
     * @return string;
     *
     **/
    public function get_shortname() {
        return $this->companyrecord->shortname;
    }

    /**
     * Gets the company theme name for the current instance
     *
     * @return string
     *
     **/
    public function get_theme() {
        return $this->companyrecord->theme;
    }

    /**
     * Gets the company parentid name for the current instance
     *
     * @return mixed
     *
     **/
    public function get_parentid() {
        if (!empty($this->companyrecord->parentid)) {
            return $this->companyrecord->parentid;
        } else {
            return false;
        }
    }

    /**
     * Gets the company wwwroot for the current instance
     *
     * @return URL
     *
     **/
    public function get_wwwroot() {
        global $CFG;

        // Do we have a hostname for this company?
        if (!empty($this->companyrecord->hostname)) {
            // Parse the current wwwroot.
            $u = parse_url($CFG->wwwroot);
            if (empty($u["path"])) {
                 $u["path"] = "";
            }
            $url = "$u[scheme]://".$this->companyrecord->hostname."$u[path]" . (isset($u["query"]) ? "?$u[query]" : "");

            // Return the parse URL.
            return $url;
        } else {
            // Return the default wwwroot.
            return $CFG->wwwroot;
        }
    }

    /**
     * Gets the relative URL given wwwroot for the current instance
     *
     * @return URL
     *
     **/
    public static function get_relativeurl($url) {
        $u = parse_url($url);
        if (empty($u["path"])) {
             $u["path"] = "";
        }
        // Return the relative URL.
        return $u["path"] . (isset($u["query"]) ? "?$u[query]" : "");
    }

    /**
     * Recurses up the company tree to get the parent company.
     *
     * @return int
     *
     **/
    public function get_topcompanyid() {

        // Set the return id to by myself initially.
        $returnid = $this->id;

        // Check if I have a parent id.
        if ($parentid = $this->get_parentid()) {

            // Check it if has a parent id.
            $parentcompany = new company($parentid);
            $returnid = $parentcompany->get_topcompanyid();
        }

        return $returnid;
    }

    /**
     * Gets the file path for the company logo for the current instance
     *
     * @return string
     *
     */
    public static function get_logo_url($companyid, $maxwidth = null, $maxheight = 200) {
        
        // Get the company logo config settings.
        $logo = get_config('core_admin', 'logo'.$companyid);
        if (!empty($logo)) {
            // Return the company logo URL.
            // 200px high is the default image size which should be displayed at 100px in the page to account for retina displays.
            // It's not worth the overhead of detecting and serving 2 different images based on the device.

            // Hide the requested size in the file path.
            $filepath = ((int) $maxwidth . 'x' . (int) $maxheight) . '/';

            // Use $CFG->themerev to prevent browser caching when the file changes.
            return moodle_url::make_pluginfile_url(context_system::instance()->id, 'core_admin', 'logo'.$companyid, $filepath,
                theme_get_revision(), $logo);
        } else {
            // Return the default site logo URL if there is one.
            $logo = get_config('core_admin', 'logo');
            if (empty($logo)) {
                return false;
            }

            // 200px high is the default image size which should be displayed at 100px in the page to account for retina displays.
            // It's not worth the overhead of detecting and serving 2 different images based on the device.

            // Hide the requested size in the file path.
            $filepath = ((int) $maxwidth . 'x' . (int) $maxheight) . '/';

            // Use $CFG->themerev to prevent browser caching when the file changes.
            return moodle_url::make_pluginfile_url(context_system::instance()->id, 'core_admin', 'logo', $filepath,
                theme_get_revision(), $logo);
        }
    }

    /**
     * Gets the record set of all companies
     *
     * @param int $page
     * @param int $perpage
     *
     * @return array
     *
     */
    public static function get_companies_rs($page=0, $perpage=0) {
        global $DB;

        return $DB->get_recordset('company', null, 'name', '*', $page, $perpage);
    }

    /**
     * Creates an array of companies to be used in a Select menu
     *
     * @return array
     *
     */
    public static function get_companies_select($showsuspended=false, $useprepend = true, $showchildren = true, $sort = 'name', $search = '') {
        global $CFG, $DB, $USER;

        // Is this an admin, or a normal user?
        if (iomad::has_capability('block/iomad_company_admin:company_view_all', context_system::instance())) {
            $sqlparams = [];
            $sqlwhere = "";
            if (!empty($CFG->iomad_show_company_structure)) {
                $sqlparams['parentid'] = 0;
                $sqlwhere .= " AND parentid = :parentid ";
            }
            if (!$showsuspended) {
                $sqlparams['suspended'] = 0;
                $sqlwhere .= " AND suspended = :suspended ";
            }
            if (!empty($search)) {
                $sqlwhere .= " AND " . $DB->sql_like('name', ':search', false);
                $sqlparams['search'] = '%' . $DB->sql_like_escape($search) . '%';
            }
            $companies = $DB->get_records_sql_menu("SELECT id, CASE WHEN suspended=0 THEN name ELSE concat(name, ' (S)') END AS name FROM {company}
                                                    WHERE 1 = 1
                                                    $sqlwhere
                                                    ORDER BY name",
                                                    $sqlparams);
        } else {
            if ($showsuspended) {
                $suspendedsql = '';
            } else {
                $suspendedsql = "AND c.suspended = 0";
            }
            $searchsql = "";
            $companiesparams = ['userid' => $USER->id];
            if (!empty($search)) {
                $searchsql = "AND " . $DB->sql_like('c.name', ':search', false);
                $companiesparams['search'] = '%' . $DB->sql_like_escape($search) . '%';
            }
            // Show the hierarchy if required.
            if (!empty($CFG->iomad_show_company_structure)) {
                $companies = $DB->get_records_sql_menu("SELECT DISTINCT c.id, CASE WHEN c.suspended=0 THEN c.name ELSE concat(c.name, ' (S)') END AS name, cu.lastused
                                                        FROM {company} c
                                                        JOIN {company_users} cu ON (c.id = cu.companyid)
                                                        WHERE cu.userid = :userid
                                                        AND cu.suspended = 0
                                                        $searchsql
                                                        $suspendedsql
                                                        ORDER BY $sort",
                                                        $companiesparams);
            } else {
                $companies = $DB->get_records_sql_menu("SELECT DISTINCT c.id, CASE WHEN c.suspended=0 THEN c.name ELSE concat(c.name, ' (S)') END AS name, cu.lastused
                                                        FROM {company} c
                                                        JOIN {company_users} cu ON (c.id = cu.companyid)
                                                        WHERE cu.userid = :userid
                                                        AND cu.suspended = 0
                                                        $searchsql
                                                        $suspendedsql
                                                        ORDER BY $sort",
                                                        $companiesparams);
            }
        }

        // Show the hierarchy if required.
        if (!empty($CFG->iomad_show_company_structure)) {
            $companyselect = array();
            foreach ($companies as $id => $companyname) {
                $companyselect[$id] = $companyname;
                $allchildren = self::get_formatted_child_companies_select($id);
                $companyselect = $companyselect + $allchildren;
            }
            return $companyselect;
        } else {
            return $companies;
        }
    }

    private static function get_formatted_child_companies_select($companyid, $useprepend = true, &$companyarray = [], $prepend = "") {
        global $DB;

       if ($children = $DB->get_records('company', ['parentid' => $companyid ], 'name', 'id,name,parentid')) {
           if ($useprepend) {
               $prepend = "--" . $prepend;
           } else {
               $prepend = "";
           }
           foreach ($children as $child) {
               $companyarray[$child->id] = $prepend . format_string($child->name);
               self::get_formatted_child_companies_select($child->id, $useprepend = true, $companyarray, $prepend);
           }
        }
        return $companyarray;
    }

    /**
     * Creates an array of child companies to be used in a Select menu
     *
     * @return array
     *
     */
    public function get_child_companies() {
        global $DB;

        $childcompanies = $DB->get_records('company', array('parentid' => $this->id), 'name');

        return $childcompanies;
    }

    /**
     * Creates a recursive array of child companies.
     *
     * Returns array;
     *
     **/
    public function get_child_companies_recursive() {
        global $DB;

        $returnarray = array();

        $childcompanies = $this->get_child_companies();
        foreach ($childcompanies as $child) {
            $returnarray[$child->id] = $child;
            $childcompany = new company($child->id);
            $returnarray = $returnarray + $childcompany->get_child_companies_recursive();
        }
        return $returnarray;
    }

    /**
     * Creates a recursive array of parent companies .
     *
     * Returns array;
     *
     **/
    public function get_parent_companies_recursive() {
        global $DB;

        $returnarray = array();

        // Check if I have a parent id.
        if ($parentid = $this->get_parentid()) {
            $returnarray[$parentid] = $parentid;

            // Check it if has a parent id.
            $parentcompany = new company($parentid);
            $returnarray = $returnarray + $parentcompany->get_parent_companies_recursive();
        }

        return $returnarray;
    }

    /**
     * Creates an array of child companies to be used in a Select menu
     *
     * Returns array;
     *
     **/
    public function get_child_companies_select() {
        global $DB, $USER;

        $companyselect = array();

        // Get all of the child companies.
        $companies = $this->get_child_companies_recursive();

        foreach ($companies as $company) {
            if (empty($company->suspended)) {
                $companyselect[$company->id] = $company->name;
            }
        }

        return $companyselect;
    }

    /**
     * Gets the name of a company given its ID
     *
     * Parameters -
     *              $companyid = int;
     *
     * Returns text;
     *
     **/
    public static function get_companyname_byid($companyid) {
        global $DB;
        $company = $DB->get_record('company', array('id' => $companyid));
        return $company->name;
    }

    /**
     * Gets the company record given a member
     *
     * Parameters -
     *              $userid = int;
     *
     * Returns stdclass();
     *
     **/
    public static function get_company_byuserid($userid) {
        global $DB;
        $companies = (array) $DB->get_records_sql("SELECT c.* FROM {company_users} cu
                                                   INNER JOIN {company} c ON cu.companyid = c.id
                                                   WHERE cu.userid = :userid
                                                   ORDER BY cu.id",
                                                   array('userid' => $userid), 0, 1);
        return array_shift($companies);
    }

    /**
     * Gets the user info category record associated to a company
     *
     * Parameters -
     *              $companyid = int;
     *
     * Returns stdclass() or false;
     *
     **/
    public static function get_category($companyid) {
        global $DB;
        if ($category = $DB->get_record_sql("SELECT uic.id, uic.name FROM
                                             {user_info_category} uic, {company} c
                                             WHERE c.id = ".$companyid."
                                             AND ".$DB->sql_compare_text('c.shortname'). "=".
                                             "'".$DB->sql_compare_text('uic.name')."'")) {
            return $category;
        } else {
            return false;
        }
    }

    /**
     * Get company role templates
     *
     **/
    public static function get_role_templates($companyid = 0) {
        global $DB;

        if (empty($companyid)) {
            $companycontext = context_system::instance();
        } else {
            $companycontext = \core\context\company::instance($companyid);
        }

        if (iomad::has_capability('block/iomad_company_admin:company_add', $companycontext)) {
            $templates = $DB->get_records_menu('company_role_templates', array(), 'name', 'id,name');
        } else {
            $templates = $DB->get_records_sql_menu("SELECT crt.id,crt.name FROM {company_role_templates} crt
                                                    JOIN {company_role_templates_ass} crta
                                                    ON (crt.id = crta.templateid)
                                                    WHERE crta.companyid = :companyid
                                                    ORDEr BY crt.name",
                                                    array('companyid' => $companyid));
        }
        $templates = array('i' => get_string('inherit', 'block_iomad_company_admin')) + $templates;

        // Add the default.
        $templates = array(0 => get_string('none')) + $templates;

        return $templates;
    }

    /**
     * Apply company role templates
     *
     **/
    public function apply_role_templates($templateid = 0) {
        global $DB;

        if (!empty($templateid)) {
            $restrictions = $DB->get_records('company_role_templates_caps', array('templateid' => $templateid));
        } else {
            // Get the same role entries as for the parent company id.
            $restrictions = $DB->get_records('company_role_restriction', array('companyid' => $this->get_parentid()));
        }

        // Insert the restrictions.
        // Remove them first.
        $DB->delete_records('company_role_restriction', array('companyid' => $this->id));

        // Add the template.
        foreach ($restrictions as $restriction) {
            $DB->insert_record('company_role_restriction', array('companyid' => $this->id, 'roleid' => $restriction->roleid, 'capability' => $restriction->capability));
        }
    }

    /**
     * Assign company role templates
     *
     **/
    public function assign_role_templates($templates = array(), $clear = false) {
        global $DB;

        // Deal with any children.
        $children = $this->get_child_companies_recursive();
        foreach ($children as $child) {
            $childcompany = new company($child->id);
            $childcompany->assign_role_templates($templates, $clear);
        }

        // Final Deal with our own.
        if ($clear) {
            $DB->delete_records('company_role_templates_ass', array('companyid' => $this->id));
        }
        foreach ($templates as $templateid) {
            $DB->insert_record('company_role_templates_ass', array('companyid' => $this->id, 'templateid' => $templateid));
        }
    }

    /**
     * Get company email templates
     *
     **/
    public static function get_email_templates($companyid = 0) {
        global $DB;

        $templates = $DB->get_records_menu('email_templateset', array(), 'templatesetname', 'id,templatesetname');

        // Add the default.
        $templates = array(0 => get_string('none')) + $templates;

        return $templates;
    }

    /**
     * Apply company email templates
     *
     **/
    public function apply_email_templates($templatesetid = 0) {
        global $DB;

        if (!empty($templatesetid)) {
            $templates = $DB->get_records('email_templateset_templates', array('templateset' => $templatesetid));
        } else {
            return false;
        }

        // Insert the restrictions.
        // Remove them first.
        $DB->delete_records('email_template', array('companyid' => $this->id));

        // Add the template.
        foreach ($templates as $template) {
            unset($template->templateset);
            $template->companyid = $this->id;
            $DB->insert_record('email_template', $template);
        }

        return true;
    }

    /**
     * Associates a course to a company
     *
     * Parameters -
     *              $course = stdclass();
     *              $departmentid = int;
     *              $own = boolean;
     *
     **/
    public function add_course($course, $departmentid=0, $own=false, $licensed=false) {
        global $DB, $CFG;

        $coursecontext = context_course::instance($course->id);

        if ($departmentid != 0 ) {
            // Adding to a specified department.
            $companydepartment = $departmentid;
        } else {
            // Put course in default company department.
            $companydepartmentnode = self::get_company_parentnode($this->id);
            $companydepartment = $companydepartmentnode->id;
        }
        if (!$DB->record_exists('company_course', array('companyid' => $this->id,
                                                       'courseid' => $course->id))) {
            $DB->insert_record('company_course', array('companyid' => $this->id,
                                                      'courseid' => $course->id,
                                                      'departmentid' => $companydepartment));
        }

        // Set up defaults for course management.
        if (!$DB->get_record('iomad_courses', array('courseid' => $course->id))) {
            $DB->insert_record('iomad_courses', array('courseid' => $course->id,
                                                         'licensed' => $licensed,
                                                         'shared' => 0));
        }
        // Set up manager roles.
        if (!$licensed) {
            $companycoursenoneditorrole = $DB->get_record('role', ['shortname' => 'companycoursenoneditor']);
            $companycourseeditorrole = $DB->get_record('role', ['shortname' => 'companycourseeditor']);
            if ($CFG->iomad_autoenrol_managers) {
                // Enrol the managers as teacher types.
                if ($companymanagers = $DB->get_records_sql("SELECT * FROM {company_users}
                                                             WHERE companyid = :companyid
                                                             AND managertype != 0", array('companyid' => $this->id))) {
                    foreach ($companymanagers as $companymanager) {
                        if ($user = $DB->get_record('user', array('id' => $companymanager->userid,
                                                                  'deleted' => 0)) ) {
                            if ($DB->record_exists('course', array('id' => $course->id))) {
                                if (!$own) {
                                    // Not created by a company manager.
                                    company_user::enrol($user, array($course->id), $this->id,
                                                        $companycoursenoneditorrole->id);
                                } else {
                                    if ($companymanager->managertype == 2) {
                                        // Assign the department manager course access role.
                                        company_user::enrol($user, array($course->id), $this->id,
                                                            $companycoursenoneditorrole->id);
                                    } else {
                                        // Assign the company manager course access role.
                                        company_user::enrol($user, array($course->id), $this->id,
                                                            $companycourseeditorrole->id);

                                        // Check if this is a newly delegated course?
                                        if (user_has_role_assignment($user->id, $companycoursenoneditorrole->id, $coursecontext->id)) {
                                            role_unassign($companycoursenoneditorrole->id, $user->id, $coursecontext->id);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            } else {
                // Enrol the educators as teacher types.
                if ($educators = $DB->get_records_sql("SELECT * FROM {company_users}
                                                             WHERE companyid = :companyid
                                                             AND educator != 0", array('companyid' => $this->id))) {
                    foreach ($educators as $educator) {
                        if ($user = $DB->get_record('user', array('id' => $educator->userid,
                                                                  'deleted' => 0)) ) {
                            if ($DB->record_exists('course', array('id' => $course->id))) {
                                if ($DB->record_exists('iomad_courses', array('courseid' => $course->id, 'shared' => 1))) {
                                    // Not created by a company manager.
                                    company_user::enrol($user, array($course->id), $this->id,
                                                        $companycoursenoneditorrole->id);
                                } else {
                                    // Assign the company manager course access role.
                                    company_user::enrol($user, array($course->id), $this->id,
                                                        $companycourseeditorrole->id);

                                    // Check if this is a newly delegated course?
                                    if (user_has_role_assignment($user->id, $companycoursenoneditorrole->id, $coursecontext->id)) {
                                        role_unassign($companycoursenoneditorrole->id, $user->id, $coursecontext->id);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($own && $departmentid == 0) {
            // Add it to the list of company created courses.
            if (!$DB->record_exists('company_created_courses', array('companyid' => $this->id,
                                                                     'courseid' => $course->id))) {
                $DB->insert_record('company_created_courses', array('companyid' => $this->id,
                                                                    'courseid' => $course->id));
            }
        }

        cache_helper::purge_by_event('changesincompanycourses');
        return true;
    }

    /**
     * removes control of a course froma company
     *
     * Parameters -
     *              $courseid = int;
     *
     **/
    public function remove_control_of_course($courseid) {
        global $DB, $CFG;

        $coursecontext = context_course::instance($courseid);

        // Set up manager roles.
        $companycoursenoneditorrole = $DB->get_record('role', ['shortname' => 'companycoursenoneditor']);
        $companycourseeditorrole = $DB->get_record('role', ['shortname' => 'companycourseeditor']);

        if ($CFG->iomad_autoenrol_managers) {
            // Enrol the managers as teacher types.
            if ($companymanagers = $DB->get_records_sql("SELECT * FROM {company_users}
                                                         WHERE companyid = :companyid
                                                         AND managertype != 0", array('companyid' => $this->id))) {
                foreach ($companymanagers as $companymanager) {
                    if ($user = $DB->get_record('user', array('id' => $companymanager->userid,
                                                              'deleted' => 0)) ) {
                        if ($DB->record_exists('course', array('id' => $courseid))) {
                            // Not created by a company manager.
                            company_user::enrol($user, [$courseid], $this->id,
                                                $companycoursenoneditorrole->id);

                            // Clean up old roles.
                            if (user_has_role_assignment($user->id, $companycourseeditorrole->id, $coursecontext->id)) {
                                role_unassign($companycourseeditorrole->id, $user->id, $coursecontext->id);
                            }
                        }
                    }
                }
            }
        } else {
            // Enrol the educators as teacher types.
            if ($educators = $DB->get_records_sql("SELECT * FROM {company_users}
                                                         WHERE companyid = :companyid
                                                         AND educator != 0", array('companyid' => $this->id))) {
                foreach ($educators as $educator) {
                    if ($user = $DB->get_record('user', array('id' => $educator->userid,
                                                              'deleted' => 0)) ) {
                        if ($DB->record_exists('course', array('id' => $courseid))) {
                            company_user::enrol($user, [$courseid], $this->id,
                                                $companycoursenoneditorrole->id);

                            // Clean up old roles.
                            if (user_has_role_assignment($user->id, $companycourseeditorrole->id, $coursecontext->id)) {
                                role_unassign($companycourseeditorrole->id, $user->id, $coursecontext->id);
                            }
                        }
                    }
                }
            }
        }

        // remove it from the list of company created courses.
        $DB->delete_records('company_created_courses', ['companyid' => $this->id,
                                                        'courseid' => $courseid]);

        cache_helper::purge_by_event('changesincompanycourses');
        return true;
    }

    /**
     * Removes a course from a company
     *
     * Parameters -
     *              $course = stdclass();
     *              $companyid = int;
     *              $departmentid = int;
     *
     **/
    public static function remove_course($course, $companyid, $departmentid=0) {
        global $DB, $PAGE;

        $errors = false;
        $transaction = $DB->start_delegated_transaction();

        if (!$course = $DB->get_record('course', array('id' => $course->id))) {
            try {
                throw new Exception(get_string('couldnotdeletecourse', 'block_iomad_Company_admin'));
            } catch (\Exception $e) {
                $transaction->rollback($e);
            }
            return false;
        }

        if (!$iomadcourse = $DB->get_record('iomad_courses', array('courseid' => $course->id))) {
            try {
                throw new Exception(get_string('couldnotdeletecourse', 'block_iomad_Company_admin'));
            } catch (\Exception $e) {
                $transaction->rollback($e);
            }
            return false;
        }

        if ($departmentid == 0) {
            // Deal with the company departments.
            $companydepartments = $DB->get_records('department', array ('company' => $companyid));
            // Check if it was a company created course and remove if it was.
            if ($companycourse = $DB->get_record('company_created_courses',
                                                 array('companyid' => $companyid,
                                                       'courseid' => $course->id))) {
                if (!$DB->delete_records('company_created_courses', array('id' => $companycourse->id))) {
                    $errors=true;
                }
            }
            // Check if its an unshared course in iomad.
            if ($iomadcourse->shared == 0) {
                if (!$DB->delete_records('iomad_courses', array('courseid' => $course->id, 'shared' => 0))) {
                    $errors = true;
                }
            }
            if (!$DB->delete_records('company_course', array('companyid' => $companyid,
                                                       'courseid' => $course->id))) {
                $errors = true;
            }

            if (!$DB->delete_records('company_shared_courses', array('companyid' => $companyid,
                                                                     'courseid' => $course->id))) {
                $errors = true;
            }

        } else {
            // Put course in default company department.
            $companydepartment = self::get_company_parentnode($companyid);
            if (!self::assign_course_to_department($companydepartment->id, $course->id, $companyid)) {
                $errors = true;
            }
        }

        // Remove the course from any licenses
        if ($licenses = $DB->get_records_sql("SELECT cl.* FROM {companylicense} cl
                                              JOIN {companylicense_courses} clc ON (cl.id = clc.licenseid)
                                              WHERE clc.courseid = :courseid
                                              AND cl.companyid = :companyid",
                                              array('courseid' => $course->id,
                                                    'companyid' => $companyid))) {

            foreach ($licenses as $license) {
                // Delete anyone using the license for that course.
                if (!$DB->delete_records('companylicense_users', array('licenseid' => $license->id, 'licensecourseid' => $course->id))) {
                    $errors = true;
                }
                // Delete the course from the license.
                if (!$DB->delete_records('companylicense_courses', array('licenseid' => $license->id, 'courseid' => $course->id))) {
                    $errors = true;
                }

                // Fire an event for this.
                $eventother = array('licenseid' => $license->id,
                                    'parentid' => $license->parentid);

                $event = \block_iomad_company_admin\event\company_license_updated::create(array('context' => \core\context\company::instance($companyid),
                                                                                                'userid' => $USER->id,
                                                                                                'objectid' => $license->id,
                                                                                                'other' => $eventother));
                $event->trigger();
            }
        }

        // Un-enrol anyone from the course which hasn't already been cleared.
        $courseenrolment = new course_enrolment_manager($PAGE, $course);
        $userlist = $courseenrolment->get_users('u.id', 'ASC', 0, 0);

        // We only want _our_ company users if it's shared.
        if ($iomadcourse->shared != 0) {
            $allcompanyusers = $DB->get_records_sql("SELECT DISTINCT lit.userid
                                                     FROM {local_iomad_track} lit
                                                     WHERE lit.coursecleared = 0
                                                     AND lit.courseid = :courseid
                                                     AND lit.companyid = :companyid
                                                     AND lit.userid NOT IN (
                                                         SELECT lit2.userid FROM {local_iomad_track} lit2
                                                         WHERE lit.userid = lit2.userid
                                                         AND lit.courseid = lit2.courseid
                                                         AND lit.coursecleared = lit2.coursecleared
                                                         AND lit.companyid != lit2.companyid
                                                     )",
                                                     ['courseid' => $course->id,
                                                      'companyid' => $companyid]);

            $userlist = array_intersect_key($userlist, $allcompanyusers);

            // Remove the company groups.
            self::delete_company_course_group($companyid, $course, false);
        }

        // Remove their enrolments.
        foreach ($userlist as $user) {
            $ues = $courseenrolment->get_user_enrolments($user->id);
            foreach ($ues as $ue) {
                list ($instance, $plugin) = $courseenrolment->get_user_enrolment_components($ue);
                if ($instance && $plugin && $plugin->allow_unenrol_user($instance, $ue)) {
                   $plugin->unenrol_user($instance, $ue->userid);
                }
            }
        }

        if ($errors) {
            try {
                throw new Exception('Could not delete course');
            } catch (\Exception $e) {
                $transaction->rollback(get_string('couldnotremovecoursefromcompany', 'block_iomad_company_admin'));
            }
            return false;
        } else {
            $transaction->allow_commit();
            cache_helper::purge_by_event('changesincompanycourses');
            return true;
        }
    }

    /**
     * Deletes a course from a company
     *
     * Parameters -
     *              $companyid = stdclass();
     *              $courseid = int;
     *              $destroy = boolean; True removes all entries from the {local_iomad_track} table
     *
     **/
    public static function delete_course($companyid, $courseid, $destroy = false) {
        global $DB, $USER, $CFG;

        $errors = false;
        $gone = false;
        require_once(__DIR__ . '/../../../course/format/lib.php');

        $transaction = $DB->start_delegated_transaction();

        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            try {
                throw new Exception(get_string('couldnotdeletecourse', 'block_iomad_Company_admin'));
            } catch (\Exception $e) {
                $transaction->rollback($e);
            }
            return false;
        }

        if (!$iomadcourse = $DB->get_record('iomad_courses', array('courseid' => $courseid))) {
            try {
                throw new Exception(get_string('couldnotdeletecourse', 'block_iomad_Company_admin'));
            } catch (\Exception $e) {
                $transaction->rollback($e);
            }
            return false;
        }

        // Remove the course from the company.
        if ($iomadcourse->shared != 1 && !self::remove_course($course, $companyid)) {
            $errors = true;
        }

        // Is the course a shared course?
        if ($iomadcourse->shared == 0) {
            // Call the moodle course delete function.
            if (!delete_course($courseid)) {
                $errors = true;
            }
            if (!$DB->delete_records('iomad_courses', array('id' => $iomadcourse->id))) {
                $errors = true;
            }
            $gone=true;
        } else {
            // Check if it belongs to a company now?
            if (!$DB->get_records_sql("SELECT id FROM {company_course}
                                       WHERE courseid = :courseid
                                       AND companyid != :companyid",
                                       array('courseid' => $courseid,
                                             'companyid' => $companyid))) {
                // Call the moodle course delete function.
                if (!delete_course($courseid)) {
                    $errors = true;
                }
                if (!$DB->delete_records('iomad_courses', array('id' => $iomadcourse->id))) {
                    $errors = true;
                }
                $gone = true;
            }
        }

        // remove all entries from the {local_iomad_track_table} if destroy is true.
        if ($destroy) {
            if (!$gone) {
                if (!$DB->delete_records('local_iomad_track', array('companyid' => $companyid, 'courseid' => $courseid))) {
                    $errors = true;
                }
            } else {
                if (!$DB->delete_records('local_iomad_track', array('courseid' => $courseid))) {
                    $errors = true;
                }
            }
        }

        if ($errors) {
            try {
                throw new Exception('Could not delete course');
            } catch (\Exception $e) {
                $transaction->rollback($e);
            }
            return false;
        } else {
            $transaction->allow_commit();
            cache_helper::purge_by_event('changesincompanycourses');
            return true;
        }
    }

    /**
     * Gets the copmpany defined user account default variables
     *
     * Returns stdclass();
     *
     **/
    public function get_user_defaults() {
        global $DB;

        $companyrecord = $DB->get_record('company', array('id' => $this->id),
                       'city, country, maildisplay, mailformat, maildigest, autosubscribe,
                        trackforums, htmleditor, screenreader, timezone, lang',
                        MUST_EXIST);

        return $companyrecord;
    }

    /**
     * Get the user ids associated to a company
     * does not pass back any managers
     *
     * returns stdclass();
     *
     **/
    public function get_user_ids() {
        global $DB;

        // By default wherecondition retrieves all users except the
        // deleted, not confirmed and guest.
        $params['companyid'] = $this->id;
        $params['companyidforjoin'] = $this->id;

        $sql = " SELECT u.id, u.id AS mid, u.lastname, u.firstname
                FROM
                    {company_users} cu
                    INNER JOIN {user} u ON (cu.userid = u.id)
                WHERE u.deleted = 0
                      AND cu.managertype = 0";

        $order = ' ORDER BY u.lastname ASC, u.firstname ASC';

        return $DB->get_records_sql_menu($sql . $order, $params);
    }

    /**
     * Get all the user ids associated to a company
     *
     * returns stdclass();
     *
     **/
    public function get_all_user_ids() {
        global $DB;

        // By default wherecondition retrieves all users except the
        // deleted, not confirmed and guest.
        $params['companyid'] = $this->id;
        $params['companyidforjoin'] = $this->id;

        $sql = " SELECT DISTINCT u.id, u.id AS mid, u.lastname, u.firstname
                FROM
                    {company_users} cu
                    INNER JOIN {user} u ON (cu.userid = u.id)
                WHERE u.deleted = 0
                AND cu.companyid = :companyid";

        $order = ' ORDER BY u.lastname ASC, u.firstname ASC';

        return $DB->get_records_sql_menu($sql . $order, $params);
    }

    /**
     * Associates a user to a company
     *
     * Parameters -
     *              $userid = int;
     *              $departmentid = int;
     *              $managertype = int;
     *
     **/
    public function assign_user_to_company($userid, $departmentid = 0, $managertype = 0, $ws = false, $import = false) {
        global $CFG, $DB;

        // is the user valid?
        if (!$user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0, 'suspended' => 0))) {
            return false;
        }

        // Were we passed a departmentid?
        if (!empty($departmentid)) {
            // Check its a department in this company.
            if (!$DB->get_record('department', array('id' => $departmentid, 'company' => $this->id))) {
                $defaultdepartment = self::get_company_parentnode($this->id);
                $departmentid = $defaultdepartment->id;
            }
        } else {
            // Make it the default department id.
            $defaultdepartment = self::get_company_parentnode($this->id);
            $departmentid = $defaultdepartment->id;
        }

        // Were we passed a manager type?  Check it.
        if ($managertype > 2) {
            // Default is standard user.
            $managertype = 0;
        }

        // if this is the only company, set the theme and any company profile info.
        if (!$DB->get_records('company_users', array('userid' => $userid))) {
            $DB->set_field('user', 'theme', $this->get_theme(), array('id' => $userid));
            if (!empty($CFG->iomad_sync_institution)) {
                $institution = $this->get('shortname');
                $DB->set_field('user', 'institution', $institution, array('id' => $userid));
            }
            if (!empty($CFG->iomad_sync_department)) {
                $deptrec = $DB->get_record('department', array('id' => $departmentid));
                $DB->set_field('user', 'department', $deptrec->name, array('id' => $userid));
            }
        }

        // Create the record.
        $userrecord = array();
        $userrecord['departmentid'] = $departmentid;
        $userrecord['userid'] = $userid;
        $userrecord['managertype'] = $managertype;
        $userrecord['companyid'] = $this->id;

        if ($DB->get_record('company_users', array('companyid' => $this->id, 'userid' => $userid, 'departmentid' => $departmentid))) {
            // Already in this company.  Nothing left to do.
            return true;
        }

        // Moving a user.
        if ($CFG->iomad_autoenrol_managers && $managertype > 0 ) {
            $educator = true;
        } else {
            $educator = false;
        }
        if (!self::upsert_company_user($userid, $this->id, $departmentid, $managertype, $educator, $ws)) {
            if ($ws) {
                return false;
            } else {
                throw new moodle_exception(get_string('cantassignusersdb', 'block_iomad_company_admin'));
            }
        }

        // Are we importing their completion data too?
        if ($import) {
            // Create an adhoctask to set up these roles once cron runs again.
            $importtask = new \local_iomad_track\task\importusertask();
            $importtask->set_custom_data(['companyid' => $this->id, 'userid' => $userid]);

            // Queue the task.
            \core\task\manager::queue_adhoc_task($importtask);
        }

        // Deal with auto enrolments.
        if ($CFG->local_iomad_signup_autoenrol) {
            $user->companyid = $this->id;
            $this->autoenrol($user);
        }

        return true;
    }


    public static function upsert_company_user($userid, $companyid, $departmentid, $managertype, $educator=false, $ws=false, $move=false) {
        global $DB, $CFG;

        $assign = [
            'companyid'=>$companyid,
            'userid'=>$userid,
            'departmentid' => $departmentid];

        $success = true;
        $company = new company($companyid);
        $managertypes = $company->get_managertypes(true);

        // Is this a real user?
        if (!$userrec = $DB->get_record('user', array('id' => $userid))) {
            return false;
        }

        // Get the company context.
        $companycontext = \core\context\company::instance($companyid);

        // Get the manager roles.
        $companymanagerrole = $DB->get_record('role', array('shortname' => 'companymanager'));
        $departmentmanagerrole = $DB->get_record('role', array('shortname' => 'companydepartmentmanager'));
        $companycoursenoneditorrole = $DB->get_record('role', array('shortname' => 'companycoursenoneditor'));
        $companycourseeditorrole = $DB->get_record('role', array('shortname' => 'companycourseeditor'));
        $companyreporterrole = $DB->get_record('role', array('shortname' => 'companyreporter'));

        // Get the full company tree as we may need it.
        $topcompanyid = $company->get_topcompanyid();
        $topcompany = new company($topcompanyid);
        $companytree = $topcompany->get_child_companies_recursive();
        $parentcompanies = $company->get_parent_companies_recursive();
        $companytree[$topcompanyid] = $topcompanyid;
        if (!empty($companytree)) {
            $parentcompanysql = " AND companyid NOT IN (" . implode(',', array_keys($companytree)) . ")";
        } else {
            $parentcompanysql = " AND companyid != :companyid";
        }


        // Get the list of company courses.
        $companyassignedcourses = $DB->get_records('company_course', ['companyid' => $companyid]);
        $sharedcourses = $DB->get_records('iomad_courses', ['shared' => 1]);
        $companycourses = [];
        foreach ($companyassignedcourses as $companyassignedcourse) {
            $companycourses[$companyassignedcourse->courseid] = $companyassignedcourse;
        }
        foreach ($sharedcourses as $sharedcourse) {
            $sharedcourse->companyid = $companyid;
            $companycourses[$sharedcourse->courseid] = $sharedcourse;
        }

        // Does the user exist in the department?
        if (!$user=$DB->get_record('company_users', $assign)) {
            if (($managertype == 1 || $managertype == 2) && $CFG->iomad_autoenrol_managers) {
                $assign['educator'] = 1;
            } else {
                $assign['educator'] = $educator;
            }   

            // Add the user to the new department.
            $success = $DB->insert_record('company_users',
                array_merge($assign,['managertype'=>$managertype,'departmentid'=>$departmentid]));

            // Are we moving the user?
            if ($move) {
                $DB->delete_records_select('company_users',
                                           'companyid = :companyid AND userid = :userid AND departmentid != :departmentid',
                                           ['companyid' => $companyid,
                                            'userid' => $userid,
                                            'departmentid' => $departmentid]);
            }
            if ($managertype == 0 &&
                $DB->get_records_sql('SELECT id FROM {company_users}
                                      WHERE
                                      userid = :userid
                                      AND managertype != 0
                                      AND companyid = :companyid',
                                      ['userid' => $userid,
                                      'companyid' => $companyid])) {
                // We are demoting a manager type.
                role_unassign($departmentmanagerrole->id, $userid, $companycontext->id);
                role_unassign($companyreporterrole->id, $userid, $companycontext->id);
                role_unassign($companymanagerrole->id, $userid, $companycontext->id);

                // Deal with course permissions.
                if ($CFG->iomad_autoenrol_managers && !empty($companycourses)) {
                    foreach ($companycourses as $companycourse) {
                        if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                            company_user::unenrol($userid,
                                                  [$companycourse->courseid],
                                                  $companycourse->companyid);

                        }
                    }
                }

                // Make sure all department records in the company match this.
                $DB->set_field('company_users', 'managertype', 0, ['companyid' => $companyid, 'userid' => $userid]);

            } else if ($managertype == 1 &&
                       $DB->get_records_sql("SELECT id FROM {company_users}
                                            WHERE
                                            userid = :userid
                                            AND managertype = :roletype
                                            $parentcompanysql",
                                            array('userid' => $userid,
                                                  'roletype' => 1,
                                                  'companyid' => $companyid))) {
                // We have a company manager from another company.
                // Deal with company courses.
                if ($CFG->iomad_autoenrol_managers && !empty($companycourses)) {
                    foreach ($companycourses as $companycourse) {
                        if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                            if ($DB->record_exists('company_created_courses',
                                                    array('companyid' => $companycourse->companyid,
                                                          'courseid' => $companycourse->courseid))) {
                                company_user::enrol($userid,
                                                    array($companycourse->courseid),
                                                    $companycourse->companyid,
                                                    $companycourseeditorrole->id);
                            } else {
                                company_user::enrol($userid,
                                                    array($companycourse->courseid),
                                                    $companycourse->companyid,
                                                    $companycoursenoneditorrole->id);
                            }
                        }
                    }
                    role_assign($companymanagerrole->id, $userid, $companycontext->id);
                    // External company managers don't go down the child company tree.
                }
            } else if ($managertype == 1) {
                // Give them the company manager role.
                role_unassign($departmentmanagerrole->id, $userid, $companycontext->id);
                role_unassign($companyreporterrole->id, $userid, $companycontext->id);
                role_assign($companymanagerrole->id, $userid, $companycontext->id);

                // Deal with course permissions.
                if ($CFG->iomad_autoenrol_managers && !empty($companycourses)) {
                    foreach ($companycourses as $companycourse) {
                        if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                            // If its a company created course then assign the editor role to the user.
                            if ($DB->record_exists('company_created_courses',
                                                    array ('companyid' => $companyid,
                                                           'courseid' => $companycourse->courseid))) {
                                company_user::unenrol($userid,
                                                      array($companycourse->courseid),
                                                            $companycourse->companyid);
                                company_user::enrol($userid, array($companycourse->courseid),
                                                    $companycourse->companyid,
                                                    $companycourseeditorrole->id);

                            } else {
                                 company_user::enrol($userid, array($companycourse->courseid),
                                                     $companycourse->companyid,
                                                     $companycoursenoneditorrole->id);
                            }
                        }
                    }
                }   

                $companycount = $DB->count_records_select('company_users', "userid = :userid AND (managertype = 1 OR managertype = 2)",
                                                        array('userid' => $userid));
                if ($companycount == 0) {
                    // Fire an email for this.
                    EmailTemplate::send('user_promoted',
                                   array('company' => $company->companyrecord,
                                         'user' => $userrec));
                }
            } else if ($managertype == 2) {
                // Give them the department manager role.
                role_unassign($companymanagerrole->id, $userid, $companycontext->id);
                role_unassign($companyreporterrole->id, $userid, $companycontext->id);
                role_assign($departmentmanagerrole->id, $userid, $companycontext->id);

                // Deal with company course roles.
                if ($CFG->iomad_autoenrol_managers && !empty($companycourses)) {
                    foreach ($companycourses as $companycourse) {
                        if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                            company_user::unenrol($userid, array($companycourse->courseid),
                                                  $companycourse->companyid);
                            company_user::enrol($userid, array($companycourse->courseid),
                                                $companycourse->companyid,
                                                $companycoursenoneditorrole->id);
                        }
                    }
                }   

                // Make sure all department records in the company match this.
                $DB->set_field('company_users', 'managertype', 2, ['companyid' => $companyid, 'userid' => $userid]);

                $companycount = $DB->count_records_select('company_users', "userid = :userid AND (managertype = 1 OR managertype = 2)",
                                                        array('userid' => $userid));
                if ($companycount == 0) {
                    // Fire an email for this.
                    EmailTemplate::send('user_promoted',
                                   array('company' => $company->companyrecord,
                                         'user' => $userrec));
                }
            } else if ($managertype == 4 ) {
                // Give them the company reporter role.
                role_unassign($companymanagerrole->id, $userid, $companycontext->id);
                role_unassign($departmentmanagerrole->id, $userid, $companycontext->id);
                role_assign($companyreporterrole->id, $userid, $companycontext->id);

                // Make sure all department records in the company match this.
                $DB->set_field('company_users', 'managertype', 4, ['companyid' => $companyid, 'userid' => $userid]);
            }
        } else {
            // Changing a user that is currently in the department.
            $s = [];
            if($user->departmentid != $departmentid) {
                $s['departmentid'] = $departmentid;
            }   
            if($user->managertype != $managertype && $managertype != 3) {
                $s['managertype'] = $managertype;
            }   
            if (($managertype == 1 || $managertype == 2) && $CFG->iomad_autoenrol_managers) {
                $s['educator'] = 1;
            } else if ($CFG->iomad_autoenrol_managers) {
                $s['educator'] = 0;
            } else if ($managertype == 3) {
                $s['educator'] = $educator;
            } else {
                $s['educator'] = $educator;
            }   

            // Deal with any management role changes.
            if ($managertype != 0) {
                if ($managertype == 1) {
                    // Give them the company manager role.
                    role_unassign($departmentmanagerrole->id, $userid, $companycontext->id);
                    role_unassign($companyreporterrole->id, $userid, $companycontext->id);
                    role_assign($companymanagerrole->id, $userid, $companycontext->id);

                    // Deal with course permissions.
                    if ($CFG->iomad_autoenrol_managers && !empty($companycourses)) {
                        foreach ($companycourses as $companycourse) {
                            if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                                // If its a company created course then assign the editor role to the user.
                                if ($DB->record_exists('company_created_courses',
                                                        array ('companyid' => $companyid,
                                                               'courseid' => $companycourse->courseid))) {
                                    company_user::unenrol($userid,
                                                          array($companycourse->courseid),
                                                                $companycourse->companyid);
                                    company_user::enrol($userid, array($companycourse->courseid),
                                                        $companycourse->companyid,
                                                        $companycourseeditorrole->id);

                                } else {
                                     company_user::enrol($userid, array($companycourse->courseid),
                                                         $companycourse->companyid,
                                                         $companycoursenoneditorrole->id);
                                }
                            }
                        }
                    }   

                    if ($user->managertype == 0) {
                        $companycount = $DB->count_records_select('company_users', "userid = :userid AND (managertype = 1 OR managertype = 2)",
                                                                array('userid' => $userid));
                        if ($companycount == 0) {
                            // Fire an email for this.
                            EmailTemplate::send('user_promoted',
                                           array('company' => $company->companyrecord,
                                                 'user' => $userrec));
                        }
                    }
                } else if ($managertype == 2) {
                    // Give them the department manager role.
                    role_unassign($companymanagerrole->id, $userid, $companycontext->id);
                    role_unassign($companyreporterrole->id, $userid, $companycontext->id);
                    role_assign($departmentmanagerrole->id, $userid, $companycontext->id);

                    // Deal with company course roles.
                    if ($CFG->iomad_autoenrol_managers && !empty($companycourses)) {
                        foreach ($companycourses as $companycourse) {
                            if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                                company_user::unenrol($userid, array($companycourse->courseid),
                                                      $companycourse->companyid);
                                company_user::enrol($userid, array($companycourse->courseid),
                                                    $companycourse->companyid,
                                                    $companycoursenoneditorrole->id);
                            }
                        }
                    }
                    if ($user->managertype == 0) {
                        // Fire an email for this.
                        EmailTemplate::send('user_promoted',
                                       array('company' => $company->companyrecord,
                                             'user' => $userrec));
                    }   
                } else if ($managertype == 3 && !$CFG->iomad_autoenrol_managers) {
                    // Deal with company course roles.
                    if ($CFG->iomad_autoenrol_managers && !empty($companycourses)) {
                        foreach ($companycourses as $companycourse) {
                            if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                                if ($educator) {
                                    // If its a company created course then assign the editor role to the user.
                                    if ($DB->record_exists('company_created_courses',
                                                            array ('companyid' => $companyid,
                                                                   'courseid' => $companycourse->courseid))) {
                                        company_user::unenrol($userid,
                                                              array($companycourse->courseid),
                                                                    $companycourse->companyid);
                                        company_user::enrol($userid, array($companycourse->courseid),
                                                            $companycourse->companyid,
                                                            $companycourseeditorrole->id);

                                    } else {
                                         company_user::enrol($userid, array($companycourse->courseid),
                                                             $companycourse->companyid,
                                                             $companycoursenoneditorrole->id);
                                    }
                                } else {
                                    if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                                        company_user::unenrol($userid,
                                                              array($companycourse->courseid),
                                                                    $companycourse->companyid);
                                    }
                                }
                            }
                        }
                    }
                } else if ($managertype == 4 ) {
                    // Give them the company reporter role.
                    role_unassign($companymanagerrole->id, $userid, $companycontext->id);
                    role_unassign($departmentmanagerrole->id, $userid, $companycontext->id);
                    role_assign($companyreporterrole->id, $userid, $companycontext->id);

                }

                if ($managertype == 1 || $user->managertype == 1) {
                    // Deal with child companies.
                    foreach ($company->get_child_companies_recursive() as $childcompany) {
                        // get the top level department of the child company.
                        $childdepartment = self::get_company_parentnode($childcompany->id);
                        self::upsert_company_user($userid,$childcompany->id,$childdepartment->id,$managertype, $educator);
                    }
                }   
            }
            if (($user->managertype == 1 ||
                 $user->managertype == 2 ||
                 $user->managertype == 4)
                 && $managertype == 0) {
                // Demoting a manager to a user.
                // Deal with company course roles.
                if ($CFG->iomad_autoenrol_managers &&
                    !empty($companycourses)) {
                    foreach ($companycourses as $companycourse) {
                        if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                            company_user::unenrol($userid, array($companycourse->courseid),
                                                  $companycourse->companyid);
                        }
                    }
                }
                if ($DB->get_records_sql("SELECT id FROM {company_users}
                                          WHERE userid = :userid
                                          AND companyid NOT IN
                                          (" . join(',', array_keys($companytree)) .")
                                          AND managertype = 1",
                                          array('userid' => $userid,
                                                'companyid' => $companyid))) {
                    // Remove the user from this company.
                    $DB->delete_records('company_users', (array) $user);

                    // Create an event for this.
                    $eventother = array('companyname' => $company->get_name(),
                                        'companyid' => $company->id,
                                        'usertype' => $managertype,
                                        'usertypename' => $managertypes[$managertype]);
                    $event = \block_iomad_company_admin\event\company_user_unassigned::create(array('context' => $companycontext,
                                                                                                    'objectid' => $company->id,
                                                                                                    'userid' => $userid,
                                                                                                    'other' => $eventother));

                    $event->trigger();
                    return true;
                } else {
                    role_unassign($companymanagerrole->id, $userid, $companycontext->id);
                    role_unassign($departmentmanagerrole->id, $userid, $companycontext->id);
                    role_unassign($companyreporterrole->id, $userid, $companycontext->id);
                }   
                if ($user->managertype == 1) {
                    // Deal with child companies.
                    $childcompanies = $company->get_child_companies_recursive();
                    foreach ($childcompanies as $childcompany) {
                        // get the top level department of the child company.
                        $childdepartment = self::get_company_parentnode($childcompany->id);
                        self::upsert_company_user($userid,$childcompany->id, $childdepartment->id, $managertype, $educator);
                        $DB->delete_records('company_users', array('companyid' => $childcompany->id, 'userid' => $userid));
                    }
                }   

                if ($user->managertype == 1 || $user->managertype == 2) {
                    $companycount = $DB->count_records_select('company_users', "userid = :userid AND (managertype = 1 OR managertype = 2)",
                                                  array('userid' => $userid));
                    if ($companycount == 1) {
                        // Fire an email for this.
                        EmailTemplate::send('admin_deleted',
                                       array('company' => $company->companyrecord,
                                             'user' => $userrec));
                    }
                }
                // Make sure all department records in the company match this.
                $DB->set_field('company_users', 'managertype', 0, ['companyid' => $companyid, 'userid' => $userid]);
            }
            if ($educator && $user->educator != 1 &&
                 !$CFG->iomad_autoenrol_managers &&
                 !empty($companycourses)) {
                foreach ($companycourses as $companycourse) {
                    if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                        // If its a company created course then assign the editor role to the user.
                        if ($DB->record_exists('company_created_courses',
                                                array ('companyid' => $companyid,
                                                       'courseid' => $companycourse->courseid))) {
                            company_user::unenrol($userid,
                                                  array($companycourse->courseid),
                                                        $companycourse->companyid);
                            company_user::enrol($userid, array($companycourse->courseid),
                                                $companycourse->companyid,
                                                $companycourseeditorrole->id);

                        } else {
                             company_user::enrol($userid, array($companycourse->courseid),
                                                 $companycourse->companyid,
                                                 $companycoursenoneditorrole->id);
                        }
                    }
                }
            }   

            if (!$educator && $user->educator == 1 &&
                 !$CFG->iomad_autoenrol_managers &&
                 !empty($companycourses)) {
                foreach ($companycourses as $companycourse) {
                    if ($DB->record_exists('course', array('id' => $companycourse->courseid))) {
                        company_user::unenrol($userid,
                                              array($companycourse->courseid),
                                                    $companycourse->companyid);
                    }
                }
            }   

            // Are we updating the user record?
            if(count($s)) {
                $s['id'] = $user->id;
                $success = $DB->update_record('company_users', array_merge($assign,$s));
            }
        }
        if(!$success) {
            throw new moodle_exception(get_string('cantassignusersdb', 'block_iomad_company_admin'));
        }

        // Create an event for this.
        $eventother = array('companyname' => $company->get_name(),
                            'companyid' => $company->id,
                            'departmentid' => $departmentid,
                            'usertype' => $managertype,
                            'usertypename' => $managertypes[$managertype],
                            'moved' => $move);
        $event = \block_iomad_company_admin\event\company_user_assigned::create(array('context' => $companycontext,
                                                                                      'objectid' => $company->id,
                                                                                      'userid' => $userid,
                                                                                      'other' => $eventother));
        // Fire the event.
        $event->trigger();

        if($ws) return $success;

        return true;
    }

    /**
     * Removes a user from a company
     *
     * Parameters -
     *              $userid = int;
     *
     **/
    public function unassign_user_from_company($userid, $ws = false) {
        global $CFG, $DB;

        $timestamp = time();

        // Moving a user.
        if (!$userrecords = $DB->get_records('company_users', array('companyid' => $this->id,
                                                                    'userid' => $userid))) {
            if ($ws) {
                return false;
            } else {
                throw new moodle_exception(get_string('cantassignusersdb', 'block_iomad_company_admin'));
            }
        }

        // Deal with company courses
        if ($companycourses = $this->get_menu_courses(true, false, false, false)) {
            foreach ($companycourses as $courseid => $name) {
                $coursecontext = \context_course::instance($courseid);
                if ($licrecs = $DB->get_records_select('local_iomad_track',
                                                       'userid = :userid
                                                        AND courseid = :courseid
                                                        AND companyid = :companyid
                                                        AND coursecleared = 0
                                                        AND timecompleted > 0',
                                                       ['userid' => $userid,
                                                        'companyid' => $this->id,
                                                        'courseid' => $courseid],
                                                        'id')) {
                    // Clear down the user from the courses.
                    foreach ($licrecs as $licrec) {
                        // Remove this specific record.
                        company_user::delete_user_course($userid, $courseid, 'autodelete', $licrec->id);
                    }
                }
            }
        }

        // Get licenses which are reusable and can be removed.
        if ($reusablelicenses = $DB->get_records_sql("SELECT clu.*
                                                      FROM {companylicense_users} clu
                                                      JOIN {companylicense} cl ON (clu.licenseid = cl.id)
                                                      WHERE cl.companyid = :companyid 
                                                      AND (cl.type = 1 OR cl.type = 3)
                                                      AND cl.expirydate > :timestamp
                                                      AND clu.userid = :userid",
                                                      array('timestamp' => $timestamp,
                                                            'userid' => $userid,
                                                            'companyid' => $this->id))) {
            foreach ($reusablelicenses as $reusablelicense) {
                $DB->delete_records('companylicense_users', array('id' => $reusablelicense->id));

                // Fire the license deleted event.
                $eventother = array('licenseid' => $reusablelicense->licenseid,
                                    'duedate' => 0);
                $event = \block_iomad_company_admin\event\user_license_unassigned::create(array('context' => context_course::instance($reusablelicense->licensecourseid),
                                                                                                'objectid' => $reusablelicense->licenseid,
                                                                                                'courseid' => $reusablelicense->licensecourseid,
                                                                                                'userid' => $reusablelicense->userid,
                                                                                                'other' => $eventother));
                $event->trigger();

                // Update the license usage.
                self::update_license_usage($reusablelicense->licenseid);
            }
        }

        // Get licenses which are unused, non-program and can be removed.
        if ($nonprogramlicenses = $DB->get_records_sql("SELECT clu.*
                                                        FROM {companylicense_users} clu
                                                        JOIN {companylicense} cl ON (clu.licenseid = cl.id)
                                                        WHERE cl.companyid = :companyid
                                                        AND (cl.type = 0 OR cl.type = 2)
                                                        AND cl.program = 0
                                                        AND clu.isusing = 0
                                                        AND cl.expirydate > :timestamp
                                                        AND clu.userid = :userid",
                                                        array('timestamp' => $timestamp,
                                                              'userid' => $userid,
                                                              'companyid' => $this->id))) {
            foreach ($nonprogramlicenses as $nonprogramlicense) {
                $DB->delete_records('companylicense_users', array('id' => $nonprogramlicense->id));

                // Fire the license deleted event.
                $eventother = array('licenseid' => $nonprogramlicense->licenseid,
                                    'duedate' => 0);
                $event = \block_iomad_company_admin\event\user_license_unassigned::create(array('context' => context_course::instance($nonprogramlicense->licensecourseid),
                                                                                                'objectid' => $nonprogramlicense->licenseid,
                                                                                                'courseid' => $nonprogramlicense->licensecourseid,
                                                                                                'userid' => $nonprogramlicense->userid,
                                                                                                'other' => $eventother));
                $event->trigger();

                // Update the license usage.
                self::update_license_usage($nonprogramlicense->licenseid);
            }
        }

        // Deal with program licenses.
        if ($programlicenses = $DB->get_records_sql("SELECT DISTINCT cl.id
                                                     FROM {companylicense} cl
                                                     JOIN {companylicense_users} clu ON (cl.id = clu.licenseid)
                                                     WHERE cl.companyid = :companyid
                                                     AND cl.program = 1
                                                     AND clu.userid = :userid
                                                     AND cl.expirydate > :timestamp",
                                                     array('timestamp' => $timestamp,
                                                           'userid' => $userid,
                                                           'companyid' => $this->id))) {

            foreach ($programlicenses as $programlicense) {
                // Check if there is a used course here
                if ($DB->get_records('companylicense_users', array('userid' => $userid, 'licenseid' => $programlicense->id, 'isusing' => 1))) {
                    continue;
                } else {
                    $licenserecords = $DB->get_records('companylicense_users', array('userid' => $userid, 'licenseid' => $programlicense->id));

                    foreach ($licenserecords as $licenserecord) {
                        // Fire the license deleted event.
                        $eventother = array('licenseid' => $licenserecord->licenseid,
                                            'duedate' => 0);
                        $event = \block_iomad_company_admin\event\user_license_unassigned::create(array('context' => context_course::instance($licenserecord->licensecourseid),
                                                                                                        'objectid' => $licenserecord->licenseid,
                                                                                                        'courseid' => $licenserecord->licensecourseid,
                                                                                                        'userid' => $licenserecord->userid,
                                                                                                        'other' => $eventother));
                        $event->trigger();
                    }

                    // Update the license usage.
                    self::update_license_usage($programlicense->id);
                }
            }
        }

        // Deal with any course reminders.
        $DB->set_field('local_iomad_track', 'notstartedstop', true, array('userid' => $userid, 'companyid' => $this->id));
        $DB->set_field('local_iomad_track', 'completedstop', true, array('userid' => $userid, 'companyid' => $this->id));
        $DB->set_field('local_iomad_track', 'expiredstop', true, array('userid' => $userid, 'companyid' => $this->id));
        $DB->set_field('local_iomad_track', 'modifiedtime', time(), array('userid' => $userid, 'companyid' => $this->id));

        // Delete the records.
        foreach ($userrecords as $userrecord) {
            // Are they something other than an ordinary user?
            if ($userrecord->managertype > 0) {
                // Deal with that.
                self::upsert_company_user($userid, $this->id, $userrecord->departmentid, 0, 0, $ws);
            }

            $DB->delete_records('company_users', array('id' => $userrecord->id));
        }

        if ($CFG->commerce_enable_external && !empty($CFG->commerce_externalshop_url)) {
            // Fire off the payload to the external site.
            require_once($CFG->dirroot . '/blocks/iomad_commerce/locallib.php');
            $user = $DB->get_record('user', array('id' => $userid));
            iomad_commerce::delete_user($user->username, $this->id);
        }

        // Deal with the company theme.
        $DB->set_field('user', 'theme', '', array('id' => $userid));

        return true;
    }

    public function assign_parent_managers($parentid, $finalcompanyid = 0) {
        global $DB;

        if (empty($finalcompanyid)) {
            $finalcompanyid = $this->id;
        }
        $parentcompany = new company($parentid);
        $parentmanagers = $parentcompany->get_company_managers();
        $finalcompany = new company($finalcompanyid);
        foreach ($parentmanagers as $managerid) {
            $finalcompany->assign_user_to_company($managerid->userid, 0, 1, true);
        }
        // Is there any more?
        $grandparentid = $parentcompany->get_parentid();
        if (!empty($grandparentid)) {
            $parentcompany->assign_parent_managers($grandparentid, $finalcompanyid);
        }
    }

    public function unassign_parent_managers($parentid, $finalcompanyid = 0) {
        global $DB;

        if (empty($finalcompanyid)) {
            $finalcompanyid = $this->id;
        }
        $parentcompany = new company($parentid);
        $parentmanagers = $parentcompany->get_company_managers();

        $finalcompany = new company($finalcompanyid);
        foreach ($parentmanagers as $managerid) {
            $finalcompany->unassign_user_from_company($managerid->userid, true);
        }
        // Is there any more?
        $grandparentid = $parentcompany->get_parentid();
        if (!empty($grandparentid)) {
            $parentcompany->unassign_parent_managers($grandparentid, $finalcompanyid);
        }
    }

    public function get_company_managers($managertype=1) {
        global $DB;

        return $DB->get_records('company_users', array('companyid' => $this->id, 'managertype' => $managertype), null, 'userid');
    }

    // Department functions.

    /**
     * Set up default company department.
     *
     * Parameters -
     *              $companyid = int;
     *
     **/
    public static function initialise_departments($companyid) {
        global $DB;
        $company = $DB->get_record('company', array('id' => $companyid));
        $parentnode = array();
        $parentnode['shortname'] = $company->shortname;
        $parentnode['name'] = $company->name;
        $parentnode['company'] = $company->id;
        $parentnode['parent'] = 0;
        $parentnodeid = $DB->insert_record('department', $parentnode);
        // Get the company user's ids.
        if ($userids = $DB->get_records('company_users', array('companyid' => $companyid))) {
            foreach ($userids as $userid) {
                $userid->departmentid = $parentnodeid;
                $DB->update_record('company_users', $userid);
            }
        }
        // Get the company courses.
        if ($companycourses = $DB->get_records('company_course', array('companyid' => $company->id))) {
            foreach ($companycourses as $companycourse) {
                $companycourse->departmentid = $parentnodeid;
                $DB->update_record('company_course', $companycourse);
            }
        }
    }

    /**
     * Set up default company department.
     *
     * Parameters -
     *              $companyid = INT;
     *              $currentdepartment = department obtject;
     *              $importtree = json decoded department tree;
     *              $toplevel = boolean - true if this is the first time the tree is being accessed so initial value will be the same as the parent department.
     *
     **/
    public static function import_departments($companyid, $currentdepartment, $importtree, $toplevel = false) {
        global $DB;

        if (!$toplevel) {
            // Creating a new department.
            $newdepartment = new stdclass();
            $newdepartment->name = $importtree->name;
            $newdepartment->shortname = $importtree->shortname;
            $newdepartment->company = $companyid;
            $newdepartment->parent = $currentdepartment->id;
            $newdepartment->id = $DB->insert_record('department', $newdepartment);
        } else {
            // Already created so pass it.
            $newdepartment = $currentdepartment;
        }
        // Are there any children?
        if (empty($importtree->children)) {
            return;
        } else {
            // Create them.
            foreach ($importtree->children as $child) {
                self::import_departments($companyid, $newdepartment, $child, false);
            }
        }
    }

    /**
     * Get the department a user is associated to.
     *
     * Parameters -
     *              $user = stdclass();
     *
     * Returns stdclass();
     *
     **/
    public function get_userlevel($user) {

        global $DB;

        // Get the company context.
        $companycontext = \core\context\company::instance($this->id);

        // Can the user see the whole department tree?
        if (is_siteadmin() ||
            iomad::has_capability('block/iomad_company_admin:edit_all_departments', $companycontext) ||
            iomad::has_capability('block/iomad_company_admin:company_add', $companycontext) ||
            iomad::has_capability('block/iomad_company_admin:company_add_child', $companycontext)) {

            $topdepartment = self::get_company_parentnode($this->id);
            return array($topdepartment->id => $topdepartment);
        }

        // If not, get the department the user is assigned to in this company.
        if ($userdepartments = $DB->get_records_sql("SELECT d.* FROM {department} d
                                                     JOIN {company_users} cu ON (d.company = cu.companyid AND d.id = cu.departmentid)
                                                     WHERE cu.userid = :userid
                                                     AND cu.companyid = :companyid
                                                     ORDER BY  d.name",
                                                     array('userid' => $user->id, 'companyid' => $this->id))) {
            return $userdepartments;
        } else {
            // User doesn't exist in this company.
            return array();
        }
    }

    /**
     * Get the department a user is associated to.
     *
     * Parameters -
     *              $user = stdclass();
     *
     * Returns stdclass();
     *
     **/
    public static function get_usersupervisor($userid) {
        global $DB, $CFG;

        // get the company info.
        $companyinfo = self::get_company_byuserid($userid);
        if (!empty($companyinfo->emailprofileid)) {
            // Does the user have one defined by the company field?
            if (!$supervisor = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $companyinfo->emailprofileid))) {
                return false;
            }
        } else if (!empty($CFG->companyemailprofileid)) {
            // Does the user have one defined by the default field?
            if (!$supervisor = $DB->get_record('user_info_data', array('userid' => $userid, 'fieldid' => $CFG->companyemailprofileid))) {
                return false;
            }
        }
        if (empty($supervisor)) {
            return false;
        }

        $emaillist = array();
        foreach(explode(',', $supervisor->data) as $testemail) {
            // Is it a valid email address?
            if (validate_email($testemail)) {
                $emaillist[$testemail] = $testemail;
            }
        }

        return $emaillist;
    }

    /**
     * Get the department details given an id.
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Returns stdclass();
     *
     **/
    public static function get_departmentbyid($departmentid) {
        global $DB;
        return $DB->get_record('department', array('id' => $departmentid));
    }

    public static function get_parentdepartments($department) {
        global $DB;

        $returnarray = $department;
        // Check to see if its the top node.
        if (isset($department->id)) {
            if ($department->parent != 0) {
                $parent = self::get_department_parentnode($department->id);
                if ($parent->parent != 0 ) {

                    $returnarray->parents[] = self::get_parentdepartments($parent);
                } else {
                    $returnarray->parents[] = $parent;
                }
            }
        }

        return $returnarray;
    }

    /**
     * Get list of departments which are below this on on the tree.
     *
     * Parameters -
     *              $parent = stdclass();
     *
     * Returns array();
     *
     **/
    public static function get_subdepartments($parent, $ignorecurrentbranch = false) {
        global $DB;

        // Are we trimming a current branch?
        if (isset($parent->id) && $parent->id == $ignorecurrentbranch) {
            return $parent;
        }

        $returnarray = $parent;
        // Check to see if its the top node.
        if (isset($parent->id)) {
            if ($children = $DB->get_records('department', array('parent' => $parent->id), 'name', '*')) {
                foreach ($children as $child) {
                    $returnarray->children[] = self::get_subdepartments($child, $ignorecurrentbranch);
                }
            }
        }

        return $returnarray;
    }

    /**
     * Get an array of all subdepartments to be used in a select.
     *
     * Parameters -
     *              $parent = stdclass();
     *
     * Returns array();
     *
     **/
    public static function get_subdepartments_list($parent) {
        $subdepartmentstree = self::get_subdepartments($parent);
        $subdepartmentslist = self::get_department_list($subdepartmentstree);
        $returnlist = self::array_flatten($subdepartmentslist);
        unset($returnlist[$parent->id]);
        return $returnlist;
    }

    /**
     * Get a list of all departments
     *
     * Parameters -
     *              $tree = stdclass();
     *              $path = text;
     *
     * Returns array();
     *
     **/
    public static function get_department_list( $tree, $path='' ) {

        $flatlist = array();
        if (isset($tree->id)) {
            if (!empty($path)) {
                $flatlist[$tree->id] = $path . ' / ' . $tree->name;
            } else {
                $flatlist[$tree->id] = $tree->name;
            }
        }

        if (!empty($tree->children)) {
            foreach ($tree->children as $child) {
                if (!empty($path)) {
                    $flatlist[$child->id] = self::get_department_list($child, $path.' / '.$tree->name);
                } else {
                    $flatlist[$child->id] = self::get_department_list($child, $tree->name);
                }
            }
        }

        return $flatlist;
    }

    /**
     * Get a list of all departments
     *
     * Parameters -
     *              $tree = stdclass();
     *              $path = text;
     *
     * Returns array();
     *
     **/
    public static function get_parents_list($tree, &$return = array()) {

        if (isset($tree->id)) {
            $return[$tree->id] = $tree->id;
        }

        if (!empty($tree->parents)) {
            foreach ($tree->parents as $parent) {
                self::get_parents_list($parent, $return);
            }
        }
    }

    /**
     * The top level department given a companyid
     *
     * Parameters -
     *              $companyid = int;
     *
     * Returns stdclass() || false;
     *
     **/
    public static function get_company_parentnode($companyid) {
        global $DB;
        if (!$parentnode = $DB->get_record('department', array('company' => $companyid,
                                                               'parent' => '0'))) {
            self::initialise_departments($companyid);
            $parentnode = $DB->get_record('department', array('company' => $companyid,
                                                               'parent' => '0'));
        }
        return $parentnode;
    }

    /**
     * The parent department given a departmentid
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Returns stdclass() || false;
     *
     **/
    public static function get_department_parentnode($departmentid) {
        global $DB;
        if ($department = $DB->get_record('department', array('id' => $departmentid))) {
            $parent = $DB->get_record('department', array('id' => $department->parent));
            return $parent;
        } else {
            return false;
        }
    }

    /**
     * All parent departments given a departmentid
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Returns stdclass() || false;
     *
     **/
    public static function get_department_parentnodes($departmentid) {
        global $DB;

        $parents = array();
        while ($myparent = self::get_department_parentnode($departmentid)) {
            $parents[$myparent->id] = $myparent;
            $departmentid = $myparent->id;
        }
        return $parents;
    }

    /**
     * The top level department given a departmentid
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Returns int;
     *
     **/
    public static function get_top_department($departmentid) {
        global $DB;
        $department = $DB->get_record('department', array('id' => $departmentid));
        $parentnode = self::get_company_parentnode($department->company);
        return $parentnode->id;
    }

    /**
     * Gets a department tree list given a company id.
     *
     * Parameters -
     *              $companyid = int;
     *
     * Returns array()
     *
     **/
    public static function get_all_departments($company) {

        $parentlist = array();
        $parentnode = self::get_company_parentnode($company);
        $parentlist[$parentnode->id] = array($parentnode->id => $parentnode->name);
        $departmenttree = self::get_subdepartments($parentnode);
        $departmentlist = self::array_flatten($parentlist +
                                              self::get_department_list($departmenttree));
        return $departmentlist;
    }

    /**
     * Get array of all departments given companyid
     * Used to display select tree
     * @param int companyid
     * @return array
     */
    public static function get_all_departments_raw($companyid) {
        $parentlist = array();
        $parentnode = self::get_company_parentnode($companyid);
        $departmenttree = self::get_subdepartments($parentnode);

        return $departmenttree;
    }

    /**
     * Get array of all departments given companyid
     * Used to display select tree
     * @param int companyid
     * @return array
     */
    public static function get_all_subdepartments_raw($departmentid, $ignorecurrentbranch = false, $addchildcompanies = false) {

        // Are we trimming a current branch?
        if ($departmentid == $ignorecurrentbranch) {
            return;
        }

        $departmentnode = self::get_departmentbyid($departmentid);
        $departmenttree = self::get_subdepartments($departmentnode, $ignorecurrentbranch);

        if ($addchildcompanies) {
            $currentcompany = new company($departmentnode->company);
            if ($childcompanies = $currentcompany->get_child_companies_recursive()) {
                foreach ($childcompanies as $childcompany) {
                    $childnode = self::get_company_parentnode($childcompany->id);
                    $departmenttree->children[] = self::get_subdepartments($childnode, $ignorecurrentbranch);
                    
                }
            }
        }

        return $departmenttree;
    }

    /**
     * function to flatten a multi-dimension array to a single dimension array.
     *
     * Parameters -
     *              $array = array();
     *              &$result = array();
     *
     * Returns array();
     *
     **/
    public static function array_flatten($array, &$result=null) {

        $r = null === $result;
        $i = 0;
        foreach ($array as $key => $value) {
            $i++;
            if (is_array($value)) {
                self::array_flatten($value, $result);
            } else {
                $result[$key] = $value;
            }
        }
        if ($r) {
            return $result;
        }
    }

    /**
     * Gets a list of the sub department tree list given a department id
     * including the passed department.
     *
     * Parameters -
     *              $parentnodeid = int;
     *
     * Returns array()
     *
     **/
    public static function get_all_subdepartments($parentnodeid, $addchildcompanies = false) {
        global $PAGE;

        // format_string() references $PAGE context so nee to set that if it's not already set.
        $options = [];
        if (empty($PAGE->context)) {
            // format string needs the context.
            $options['context'] = context_system::instance();
        }

        $parentnode = self::get_departmentbyid($parentnodeid);
        $parentlist = array();
        $parentlist[$parentnodeid] = format_string($parentnode->name, true, $options);
        $departmenttree = self::get_subdepartments($parentnode);
        if ($addchildcompanies) {
            $currentcompany = new company($parentnode->company);
            if ($childcompanies = $currentcompany->get_child_companies_recursive()) {
                foreach ($childcompanies as $childcompany) {
                    $childnode = self::get_company_parentnode($childcompany->id);
                    $childtree = self::get_subdepartments($childnode);
                    $childlist[$childnode->id] = format_string($childnode->name, true, $options);
                    $departmenttree->children[] = $childtree;
                    
                }
            }
        }

        $departmentlist = self::array_flatten($parentlist +
                          self::get_department_list($departmenttree));

        return $departmentlist;
    }

    /**
     * Gets a list of all users from this department down
     * including the passed department.
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Returns array()
     *
     **/
    public static function get_recursive_department_users($departmentid, $addchildcompanies = false) {
        global $DB;

        $departmentlist = self::get_all_subdepartments($departmentid, $addchildcompanies);
        $userlist = array();
        foreach ($departmentlist as $id => $value) {
            $departmentusers = self::get_department_users($id);
            $userlist = $userlist + $departmentusers;
        }
        return $userlist;
    }

    /**
     * Gets all of the users that manager is responsible for
     *
     * Parameters -
     *             $companyid = int;
     *             $departmentid = int;
     *
     * Returns array()
     *
     **/
    public static function get_my_users($companyid=0, $departmentid=0) {
        global $USER;

        if (empty($companyid)) {
            return array();
        }
        $company = new company($companyid);
        if (empty($departmentid)) {
            if (is_siteadmin($USER->id)) {
                $department = self::get_company_parentnode($companyid);
                $departmentids = array($department->id);
            } else {
                $departments = $company->get_userlevel($USER);
                $departmentids = array_keys($departments);
            }
        }
        $users = array();
        foreach ($departmentids as $departmentid) { 
            $users = $users + self::get_recursive_department_users($departmentid);
        }
        return $users;
    }

    /**
     * Gets a list of the company managers for the company
     *
     * Returns array of objects
     *
     **/
    public function get_managers() {
        global $DB;

        $parentsql = "";
        if ($parentslist = $this->get_parent_companies_recursive()) {
            $parentsql = "AND u.id NOT IN (
                          SELECT userid FROM {company_users}
                          WHERE companyid IN (" . implode(',', array_keys($parentslist)) ."))";
        }

        // Get the managers in that list of departments.
        $managers = $DB->get_records_sql("SELECT u.* FROM {user} u
                                          JOIN {company_users} cu ON (u.id = cu.userid)
                                          WHERE cu.managertype = 1
                                          AND cu.companyid = :companyid
                                          $parentsql",
                                          array('companyid' => $this->id));
        //  return them.
        return $managers;
    }

    /**
     * Gets a list of the company managers for the company
     *
     * Returns an array to be used by a form select.
     *
     **/
    public function get_managers_select() {

        // Set up the initial array.
        $managerlist = array('0' => get_string('none'));

        // Get any company managers.
        if ($managers = $this->get_managers()) {
            foreach ($managers as $manager) {
                $managerlist[$manager->id] = fullname($manager);
            }
        }

        return $managerlist;
    }

    /**
     * Gets a list of the managers for that user
     *
     * Parameters -
     *             $userid = int;
     *             $managertype = int;
     *
     * Returns string
     *
     **/
    public function get_my_managers($userid, $managertype) {
        global $DB, $USER;

        // Get the users department.
        $userdepartments = $DB->get_records('company_users', array('userid' => $userid, 'companyid' => $this->id));

        // Set the initial return array.
        $managers = array();
        $departments = array();
        // Get the list of parent departments.
        foreach ($userdepartments as $companyuserrec) {
            if ($userdepartment = $this->get_departmentbyid($companyuserrec->departmentid)) {
                $departmentlist = $this->get_parentdepartments($userdepartment);
                self::get_parents_list($departmentlist, $departments);
            }
        }
        if (!empty($departments)) {
            // Get the managers in that list of departments.
            $managers = $DB->get_records_sql("SELECT userid FROM {company_users}
                                              WHERE managertype = :managertype
                                              AND userid != :userid
                                              AND departmentid IN (".implode(',', array_keys($departments)).")",
                                              array('managertype' => $managertype, 'userid' => $USER->id));
        }

        //  return them.
        return $managers;
    }

    /**
     * Gets a list of the users that manager is responsible for
     *
     * Parameters -
     *             $companyid = int;
     *             $departmentid = int;
     *
     * Returns string
     *
     **/
    public static function get_my_users_list($companyid=0, $departmentid=0) {
        global $USER;

        if (empty($companyid)) {
            return array();
        }
        $userlist = self::get_my_users($companyid, $departmentid);
        $users = array();
        foreach ($userlist as $user) {
            $users[] = $user->userid;
        }
        return implode(',', $users);
    }

    /**
     * Gets a list of the users at this department id
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Returns array()
     *
     **/
    public static function get_department_users($departmentid) {
        global $DB;
        if ($departmentusers = $DB->get_records('company_users',
                                                 array('departmentid' => $departmentid),
                                                 null,
                                                 'userid,id,companyid,managertype,departmentid,suspended')) {
            return $departmentusers;
        } else {
            return array();
        }
    }

    /**
     * Assign a user to a department.
     *
     * Parameters -
     *              $departmentid = int;
     *              $userid = int;
     *
     **/
    public static function assign_user_to_department($departmentid, $userid, $managertype = 0, $ws = false) {
        global $DB;

        $userrecord = array();
        $userrecord['departmentid'] = $departmentid;
        $userrecord['userid'] = $userid;

        // We need the company.
        $departmentrec = $DB->get_record('department', array('id' => $departmentid));

        // Moving a user.
        if ($currentuser = $DB->get_record('company_users', array('userid' => $userid, 'companyid' => $departmentrec->company))) {
            $currentuser->departmentid = $departmentid;
            if ($ws && !empty($managertype)) {
                $currentuser->managertype = $managertype;
            }
            if (!$DB->update_record('company_users', $currentuser)) {
                if ($ws) {
                    return false;
                } else {
                    throw new moodle_exception(get_string('cantupdatedepartmentusersdb', 'block_iomad_company_admin'));
                }
            }
        }
        return true;
    }

    /**
     * Creates a new department
     *
     * Parameters -
     *              $departmentid = int;
     *              $companyid = int;
     *              $fullname = string;
     *              $shortname = string;
     *              $parentid = int;
     *
     **/
    public static function create_department($departmentid, $companyid, $fullname,
                                      $shortname, $parentid=0) {
        global $DB;
        $newdepartment = array();
        if (!empty($departmentid)) {
            if ($departmentid == $parentid) {
                return;
            }
            $newdepartment['id'] = $departmentid;
        }
        if ($parentid) {
            $newdepartment['parent'] = $parentid;
        }
        $newdepartment['company'] = $companyid;
        $newdepartment['name'] = $fullname;
        $newdepartment['shortname'] = $shortname;
        if (isset($newdepartment['id'])) {
            // We are editing a current department.
            if (!$DB->update_record('department', $newdepartment)) {
                throw new moodle_exception(get_string('cantupdatedepartmentdb', 'block_iomad_company_admin'));
            }
        } else {
            // Adding a new department.
            if (!$DB->insert_record('department', $newdepartment)) {
                throw new moodle_exception(get_string('cantinsertdepartmentdb', 'block_iomad_company_admin'));
            }
        }

        return true;
    }

    /**
     * Delete a department.
     *
     * Parameters -
     *              $departmentid = int;
     *
     **/
    public static function delete_department($departmentid) {
        global $DB;
        if (!$DB->delete_records('department', array('id' => $departmentid))) {
            throw new moodle_exception(get_string('cantdeletedepartmentdb', 'blocks_iomad_company_admin'));
        }
        return true;
    }

    /**
     * Delete all departments from this point down moving all the associated things to targetid
     *
     * Parameters -
     *              $departmentid = int;
     *              $targetid = int;
     *
     **/
    public static function delete_department_recursive($departmentid, $targetdepartment=0) {
        // Get all the users from here and below.
        $userlist = self::get_recursive_department_users($departmentid);
        $departmentlist = self::get_all_subdepartments($departmentid);
        if ($targetdepartment == 0) {
            // Moving users to the parent node of the current department.
            $parentnode = self::get_department_parentnode($departmentid);
            $targetdepartment = $parentnode->id;
        }
        foreach ($userlist as $user) {
            //  Move the users.
            self::assign_user_to_department($targetdepartment, $user->id);
        }
        foreach ($departmentlist as $id => $value) {
            self::delete_department($id);
        }
    }

    /**
     * Check if a user is a manger of this department.
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Return boolean;
     **/
    public static function can_manage_department($departmentid) {
        global $DB, $USER;

        // Get the department record.
        $departmentrec = $DB->get_record('department', array('id' => $departmentid), '*', MUST_EXIST);

        // And the context.
        $companycontext = \core\context\company::instance($departmentrec->company);

        // Can we manage it?
        if (iomad::has_capability('block/iomad_company_admin:edit_all_departments', $companycontext)) {
            return true;
        } else if (!iomad::has_capability('block/iomad_company_admin:edit_departments', $companycontext)) {
            return false;
        } else {
            $company = new company($departmentrec->company);
            // Get the list of departments at and below the user assignment.
            $userhierarchylevels = $company->get_userlevel($USER);
            $subhierarchytree = array();
            foreach ($userhierarchylevels as $userhierarchylevel) {
                $subhierarchytree = $subhierarchytree + self::get_all_subdepartments($userhierarchylevel->id);
            }
            if (isset($subhierarchytree[$departmentid])) {
                // Current department is a child of the users assignment.
                return true;
            } else {
                return false;
            }
        }
        // We shouldn't get this far, return a default no.
        return false;
    }

    /**
     * Gets a list of all courses from this department down
     * including the passed department.
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Returns array()
     *
     **/
    public static function get_recursive_department_courses($departmentid) {
        global $DB;

        $departmentlist = self::get_all_subdepartments($departmentid);
        $courselist = array();
        foreach ($departmentlist as $id => $value) {
            $departmentcourses = self::get_department_courses($id);
            $courselist = $courselist + $departmentcourses;
        }
        // Get the top level courses.
        $companydepartment = self::get_top_department($departmentid);
        if ($companydepartment != $departmentid ) {
            $topdepartmentcourses = self::get_department_courses($companydepartment);
            $courselist = $courselist + $topdepartmentcourses;
        }
        //  Get the shared courses.
        $sharedcourses = $DB->get_records('iomad_courses', array('shared' => 1));
        return $courselist + $sharedcourses;
    }

    /**
     * Gets a list of all courses in this department
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Returns array()
     *
     **/
    public static function get_department_courses($departmentid) {
        global $DB;
        if ($departmentcourses = $DB->get_records('company_course',
                                                   array('departmentid' => $departmentid))) {
            return $departmentcourses;
        } else {
            return array();
        }
    }

    /**
     * Assign a course to this department
     *
     * Parameters -
     *              $departmentid = int;
     *              $courseid = int;
     *              $companyid = int;
     *
     **/
    public static function assign_course_to_department($departmentid, $courseid, $companyid) {
        global $DB;

        // Moving a course.
        // Get all the department assignments which may exist taking
        // shared courses into consideration.
        if ($currentcourses = $DB->get_records('company_course',
                                                array('courseid' => $courseid))) {
            $foundcourse = false;
            foreach ($currentcourses as $currentcourse) {
                // Check if the found record belongs to the current company.
                if ($DB->get_record('department', array('company' => $companyid,
                                                        'id' => $departmentid))) {
                    $foundcourse = true;
                    //  Update it.
                    $currentcourse->departmentid = $departmentid;
                    if (!$DB->update_record('company_course', $currentcourse)) {
                        throw new moodle_exception(get_string('cantupdatedepartmentcoursesdb',
                                               'block_iomad_company_admin'));
                    }
                    break;
                }
            }
            if (!$foundcourse) {
                // Assigning a shared course to a new company.
                $courserecord = array();
                $courserecord['departmentid'] = $departmentid;
                $courserecord['courseid'] = $courseid;
                $courserecord['companyid'] = $companyid;
                if (!$DB->insert_record('company_course', $courserecord)) {
                    throw new moodle_exception(get_string('cantinsertdepartmentcoursesdb',
                                           'block_iomad_company_admin'));
                }
            }
        } else {
            // Assigning a new course to a company.
            $courserecord = array();
            $courserecord['departmentid'] = $departmentid;
            $courserecord['courseid'] = $courseid;
            $courserecord['companyid'] = $companyid;
            if (!$DB->insert_record('company_course', $courserecord)) {
                throw new moodle_exception(get_string('cantinsertdepartmentcoursesdb',
                                       'block_iomad_company_admin'));
            }
        }
        return true;
    }

    /**
     * Get a list of departments a course is associated to
     *
     * Parameters -
     *              $courseid = int;
     *
     *  Return array();
     **/
    public static function get_departments_by_course($courseid) {
        global $DB;
        if ($depts = $DB->get_records('company_course', array('courseid' => $courseid),
                                                                   null, 'departmentid')) {
            return array_keys($depts);
        } else {
            return array();
        }
    }

    // Licenses stuff.

    /**
     * Gets a list of all licenses from this department down
     * including the passed department.
     *
     * Parameters -
     *              $departmentid = int;
     *
     * Returns array()
     *
     **/
    public static function get_recursive_departments_licenses($departmentid) {

        // Get all the courses for this department down.
        $courses = self::get_recursive_department_courses($departmentid);
        $licenselist = array();
        foreach ($courses as $course) {
            $courselicenses = self::get_course_licenses($course->courseid);
            $licenselist = $licenselist + $courselicenses;
        }
        return $licenselist;
    }

    /**
     * Gets a list of all licenses for this course
     *
     * Parameters -
     *              $courseid = int;
     *
     * Returns array()
     *
     **/
    public static function get_course_licenses($courseid) {
        global $DB;
        if ($licenses = $DB->get_records('companylicense_courses', array('courseid' => $courseid),
                                                                          null, 'licenseid')) {
            return $licenses;
        } else {
            return array();
        }
    }

    /**
     * Gets a list of all courses for this license
     *
     * Parameters -
     *              $licenseid = int;
     *
     * Returns array()
     *
     **/
    public static function get_courses_by_license($licenseid, $visible = true) {
        global $DB;

        if ($visible) {
            $visiblesql = " AND c.visible = 1 ";
        } else {
            $visiblesql = "";
        }
        if ($courseids = $DB->get_records_sql("SELECT c.id
                                              FROM {course} c
                                              JOIN {companylicense_courses} clc ON (c.id = clc.courseid)
                                              WHERE clc.licenseid = :licenseid
                                              $visiblesql
                                              ORDER BY c.fullname",
                                              array('licenseid' => $licenseid))) {
            $sql = "SELECT id, fullname FROM {course} WHERE id IN (".
                      implode(',', array_keys($courseids)).
                   ") ";
            if ($courses = $DB->get_records_sql($sql)) {

                // Format multi-language course full name
                foreach ($courses as $key => $course) {
                    $courses[$key]->fullname = format_string($course->fullname, true, 1);
                }

                return $courses;
            } else {
                return array();
            }
        } else {
            return array();
        }
    }

    /** Update license usage.
     *
     * Parameters -
     *              $licenseid = int;
     *
     **/
    public static function update_license_usage($licenseid) {
        global $DB;

        // Get the allocation from any child licenses.
        if ($childusage = $DB->get_records_sql("SELECT sum(allocation) AS total
                                                FROM {companylicense}
                                                WHERE parentid = :parentid",
                                                array('parentid' => $licenseid))) {
            $child = array_pop($childusage);
            $childtotal = $child->total;
        } else {
            $childtotal = 0;
        }

        // Get the number of user assigned licenses for this license.
        if ($userusage = $DB->get_records_sql("SELECT count(id) AS total
                                               FROM {companylicense_users}
                                               WHERE licenseid = :licenseid",
                                               array('licenseid' => $licenseid))) {

            $user = array_pop($userusage);
            $usertotal = $user->total;
        } else {
            $usertotal = 0;
        }

        // If we have a license, update it.
        if ($license = $DB->get_record('companylicense', array('id' => $licenseid))) {
            $license->used = $childtotal + $usertotal;
            $DB->update_record('companylicense', $license);
        }
    }

    /** Check if a license is in a child company.
     *
     * Parameters -
     *              $licenseid = int;
     *
     * Returns Boolean
     *
     **/
    public function is_child_license($licenseid) {
        global $DB;

        if (!$licenseinfo = $DB->get_record('companylicense', array('id' => $licenseid))) {
            return false;
        }
        // Get the child companies.
        $childcompanies = $this->get_child_companies_recursive();

        // Check if they match the license company?
        foreach ($childcompanies as $childcompany) {
            if ($licenseinfo->companyid == $childcompany->id) {
                // If so then it is.
                return true;
            }
        }

        // Default return false.
        return false;
    }

    public function get_menu_courses($shared = false, $licensed = false, $groups = false, $default = true, $onlylicensed = false, $noncompany = false) {
        global $DB;

        // Deal with license option.
        if ($licensed) {
            $licensesql = "c.id NOT IN (
                             SELECT courseid FROM {iomad_courses}
                             WHERE licensed = 1
                           )
                           AND";
            $sharedlicsql = " AND licensed != 1 ";
        } else {
            $licensesql = "";
            $sharedlicsql = "";
        }

        if ($onlylicensed) {
            $onlylicensedsql = "c.id IN (
                             SELECT courseid FROM {iomad_courses}
                             WHERE licensed = 1
                           )
                           AND";
        } else {
            $onlylicensedsql = "";
        }

        // Deal with shared option.
        if ($shared) {
            $sharedsql = " OR
                           c.id IN (
                              SELECT courseid FROM {iomad_courses}
                              WHERE shared = 1
                              $sharedlicsql
                              AND courseid NOT IN (
                                  SELECT courseid FROM {company_course}
                                  WHERE companyid = :companyid2
                              )
                          )";
        } else {
            $sharedsql = "";
        }

        // Deal with groups option.
        if ($groups) {
            $groupsql = "c.groupmode != 0 AND";
        } else {
            $groupsql = "";
        }

        // Deal with any courses which don't belong to any company.
        $noncompanysql = "";
        if ($noncompany) {
            $noncompanysql = " OR
                               c.id IN (
                                  SELECT id FROM {course}
                                  WHERE id NOT IN (
                                      SELECT courseid FROM {company_course}
                                  )
                              )";
        }
        // Get the courses.
        $retcourses = $DB->get_records_sql_menu("SELECT c.id, c.fullname
                                                 FROM {course} c
                                                 WHERE
                                                 $groupsql
                                                 $licensesql
                                                 $onlylicensedsql
                                                 c.id IN (
                                                     SELECT courseid FROM {company_course}
                                                     WHERE companyid = :companyid
                                                 )
                                                 $sharedsql
                                                 $noncompanysql
                                                 ORDER BY c.fullname",
                                                 array('companyid' => $this->id,
                                                       'companyid2' => $this->id));

        // Take care of multilanguage
        foreach ($retcourses as $courseid => $course) {
            $retcourses[$courseid] = format_string($course, true, 1);
        }

        // Add a default entry and return the courses.
        if ($default) {
            return array('0' => get_string('noselection', 'form')) + $retcourses;
        } else {
            return $retcourses;
        }
    }

    public function get_course_groups_menu($courseid) {
        global $DB;

        $retgroups =  $DB->get_records_sql_menu("SELECT g.id, g.description
                                                 FROM {groups} g
                                                 JOIN {company_course_groups} ccg
                                                 ON (g.id = ccg.groupid)
                                                 WHERE ccg.companyid = :companyid
                                                 AND ccg.courseid = :courseid",
                                                 array('companyid' => $this->id,
                                                       'courseid' => $courseid));

        return array('0' => get_string('noselection', 'form')) + $retgroups;
    }

    /**
     * Check if a user can use a license to access a course..
     *
     * Parameters -
     *              $licenseid = int;
     *              $courseid = int;
     *              $userid = int;
     *
     **/
    public static function license_ok_to_use($licenseid, $courseid, $userid) {
        global $DB, $CFG;

        // Check if the course is associated to any learning path.
        if (!$DB->get_records('iomad_learningpathcourse', array('course' => $courseid))) {
            return true;
        }

        // Check if the license is associated to a learning path.
        if (!$learningpath = $DB->get_record_sql("SELECT lp.* FROM {iomad_learningpath} lp
                                                  JOIN {iomad_learningpathuser} lpu ON (lp.id = lpu.pathid)
                                                  WHERE lp.licenseid = :licenseid
                                                  AND lpu.userid = :userid",
                                                  array('licenseid' => $licenseid, 'userid' => $userid))) {
            return true;
        }

        // Check if the group is sequenced.
        if (!$groupinfo = $DB->get_record_sql("SELECT lpc.* FROM {iomad_learninpathcourse}
                                 JOIN {iomad_learningpathgroup} lpg ON (lpc.groupid = lpg.id)
                                 WHERE lpc.courseid = :courseid
                                 AND lpc.path = :path
                                 AND lpg.sequence = 1",
                                 array('courseid' => $courseid, 'path' => $learningpath->id))) {
            return true;
        }

        // Check if the user has met all the conditions.
        $groupcourses = $DB->get_records('iomad_learningpathcourse', array('groupid' => $groupinfo->groupid), 'sequence ASC');
        foreach ($groupcourses as $groupcourse) {
            // Is this the next course?
            if ($groupcourse->courseid == $courseid) {
                return true;
            }
            // If not, is it completed?
            if ($DB->get_record('local_iomad_track', array('userid' => $userid, 'courseid' => $courseid, 'licenseid' => $licenseid, 'timecompleted' => null))) {
                return false;
            }
        }
        // Default return true.
        return true;
    }

    // Shared course stuff.

    /**
     * Create a company group for the passed course
     *
     * Parameters -
     *              $companyid = int;
     *              $courseid = int;
     *
     * Returns int;
     *
     **/
    public static function create_company_course_group($companyid, $courseid, $groupdata = null) {
        global $CFG, $DB;
        require_once($CFG->dirroot.'/group/lib.php');

        // Creates a company group within a shared course.
        $company = $DB->get_record('company', array('id' => $companyid));
        if (empty($groupdata)) {
            $data = new stdclass();
            $data->timecreated  = time();
            $data->timemodified = $data->timecreated;
            $data->name = $company->shortname;
            $data->description = get_string('coursegroup', 'block_iomad_company_admin') . $company->name;
            $data->courseid = $courseid;
        } else if (!empty($groupdata->groupid)) {
            // Already exists so we are updating it.
            $grouprecord = $DB->get_record('groups', array('id' => $groupdata->groupid), '*', MUST_EXIST);
            $DB->set_field('groups', 'description', $groupdata->description, array('id' => $grouprecord->id));
            return $grouprecord->id;
        } else {
            $data = new stdclass();
            $data->timecreated  = time();
            $data->timemodified = $data->timecreated;
            $data->name = $company->shortname . ' - ' . $groupdata->description;
            $data->description = $groupdata->description;
            $data->courseid = $courseid;
        }

        // Create the group record.
        $groupid = groups_create_group($data);

        // Create the pivot table entry.
        $grouppivot = array();
        $grouppivot['companyid'] = $companyid;
        $grouppivot['courseid'] = $courseid;
        $grouppivot['groupid'] = $groupid;

        // Write the data to the DB.
        if (!$DB->insert_record('company_course_groups', $grouppivot)) {
            throw new moodle_exception(get_string('cantcreatecompanycoursegroup', 'block_iomad_company_admin'));
        }
        return $groupid;
    }

    /**
     * Get the course group name for the company for the passed course
     *
     * Parameters -
     *              $companyid = int;
     *              $courseid = int;
     *
     * Returns string;
     *
     **/
    public static function get_company_groupname($companyid, $courseid) {
        global $DB;
        // Gets the company course groupname.
        $company = $DB->get_record('company', array('id' => $companyid));
        if (!$companygroup = $DB->get_record('company_course_groups', array('companyid' => $companyid,
                                                                          'courseid' => $courseid,
                                                                          'name' => $company->shortname))) {
            // Not got one, create a default.
            $companygroup->groupid = self::create_company_course_group($companyid, $courseid);
        }
        // Get the group information.
        $groupinfo = $DB->get_record('groups', array('id' => $companygroup->groupid));
        return $groupinfo->name;
    }

    /**
     * Get the course group for the company for the passed course
     *
     * Parameters -
     *              $companyid = int;
     *              $courseid = int;
     *
     * Returns stdclass();
     *
     **/
    public static function get_company_group($companyid, $courseid) {
        global $DB;

        $company = $DB->get_record('company', array('id' => $companyid));

        // Gets the company course groupname.
        if (!$companygroup = $DB->get_record_sql("SELECT ccg.*
                                                  FROM {company_course_groups} ccg
                                                  JOIN {groups} g
                                                  ON (ccg.groupid = g.id)
                                                  WHERE ccg.companyid = :companyid
                                                  AND ccg.courseid = :courseid
                                                  AND g.name = :name",
                                                  array('companyid' => $companyid,
                                                        'courseid' => $courseid,
                                                        'name' => $company->shortname))) {
            // Not got one, create a default.
            $companygroup = new stdclass();
            $companygroup->groupid = self::create_company_course_group($companyid, $courseid);
        }
        // Get the group information.
        $groupinfo = $DB->get_record('groups', array('id' => $companygroup->groupid));
        return $groupinfo;
    }

    /**
     * Add a company user to a shared course company group.
     *
     * Parameters -
     *              $courseid = int;
     *              $userid = int;
     *              $companyid = int;
     *
     **/
    public static function add_user_to_shared_course($courseid, $userid, $companyid, $groupid = 0, $clear = false) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/group/lib.php');

        if (!empty($clear)) {
            // Clear the user from all groups.
            self::remove_user_from_shared_course($courseid, $userid, $companyid);
        }

        // Adds a user to a shared course.
        if (empty($groupid)) {
            $company = $DB->get_record('company', array('id' => $companyid));
            // Get the group id.
            if (!$groupinfo = $DB->get_record_sql("SELECT ccg.*
                                                  FROM {company_course_groups} ccg
                                                  JOIN {groups} g
                                                  ON (ccg.groupid = g.id)
                                                  WHERE ccg.companyid = :companyid
                                                  AND ccg.courseid = :courseid
                                                  AND g.name = :name",
                                                  array('companyid' => $companyid,
                                                        'courseid' => $courseid,
                                                        'name' => $company->shortname))) {
                $groupid = self::create_company_course_group($companyid, $courseid);
            } else {
                $groupid = $groupinfo->groupid;
            }
        }

        // Add the user to the group.
        groups_add_member($groupid, $userid);
    }

    /**
     * Remove a company user to a shared course company group.
     *
     * Parameters -
     *              $courseid = int;
     *              $userid = int;
     *              $companyid = int;
     *
     **/
    public static function remove_user_from_shared_course($courseid, $userid, $companyid) {
        global $DB, $CFG;
        require_once($CFG->dirroot.'/group/lib.php');

        // Removes a user from a shared course.
        // Get the group id.
        if (!$groups = $DB->get_records_sql("SELECT gm.groupid
                                                FROM {groups_members} gm
                                                JOIN {groups} g
                                                ON (gm.groupid = g.id)
                                                WHERE g.courseid = :courseid
                                                AND gm.userid = :userid",
                                                array('userid' => $userid,
                                                      'courseid' => $courseid))) {
            return;  // Dont need to remove them.
        } else {
            foreach ($groups as $group)
            // Remove the user from the group.
            groups_remove_member($group->groupid, $userid);
        }

    }

    /**
     * Delete a shared course company group.
     *
     * Parameters -
     *              $companyid = int;
     *              $course = stdclass();
     *              $oktounenroll = boolean;
     *
     **/
    public static function delete_company_course_group($companyid, $course, $oktounenroll=false, $groupid = 0) {
        global $DB;
        // Removes a company group within a shared course.
        // Get the group.
        if ($group = self::get_company_group($companyid, $course->id)) {
            if (empty($groupid) || $groupid == $group->id) {
                // Check there are no members of the group unless oktounenroll.
                if (!$DB->get_records('company_course_groups', array('groupid' => $group->id)) ||
                    $oktounenroll) {
                    // Delete the group.
                    $DB->delete_records('groups', array('id' => $group->id));
                    $DB->delete_records('company_course_groups', array('companyid' => $companyid,
                                                                       'groupid' => $group->id,
                                                                       'courseid' => $course->id));
                    self::remove_course($course, $companyid);
                    return true;
                } else {
                    return "usersingroup";
                }
            } else {
                // Move everyone to the default company group.
                if ($groupusers = $DB->get_records('groups_members', array('groupid' => $groupid))) {
                    foreach($groupusers as $user) {
                        groups_add_member($group->id, $user->userid);
                        groups_remove_member($groupid, $user->userid);
                    }
                }
                $DB->delete_records('groups', array('id' => $groupid));
                $DB->delete_records('company_course_groups', array('groupid' => $groupid));
            }
        }
    }

    /**
     * Adds all company users to a shared course company group.
     *
     * Parameters -
     *              $companyid = int;
     *              $courseid = int;
     *
     **/
    public static function company_users_to_company_course_group($companyid, $courseid) {
        global $DB, $CFG;
        // Adds all the users to a company group within a shared course.

        require_once($CFG->dirroot.'/group/lib.php');

        // Get the group.
        if (!$groupid = self::get_company_group($companyid, $courseid)) {
            $groupid = self::create_company_course_group($companyid, $courseid);
        }
        // This is used for a course which is becoming shared.
        //  All current course enrolled users to this company group.
        if ($users = $DB->get_records_sql("SELECT userid FROM {user_enrolments}
                                           WHERE enrolid IN (
                                           SELECT id FROM {enrol} WHERE courseid = $courseid)")) {
            foreach ($users as $user) {
                if ($DB->get_record('user', array('id' => $user->userid))) {
                    groups_add_member($groupid, $user->userid);
                }
            }
        }
    }

    /**
     * Removes all company users and group from a course.
     *
     * Parameters -
     *              $companyid = int;
     *              $courseid = int;
     *
     **/
    public static function unenrol_company_from_course($companyid, $courseid) {
        global $DB;

        $timenow = time();
        // Get the company users.
        $companydepartment = self::get_company_parentnode($companyid);
        $companyusers = self::get_recursive_department_users($companydepartment->id);
        if ($group = self::get_company_group($companyid, $courseid)) {
            // End all enrolments now..
            if ($users = $DB->get_records_sql("SELECT * FROM {user_enrolments}
                                               WHERE enrolid IN (
                                                SELECT id FROM {enrol}
                                                WHERE courseid = $courseid)
                                               AND userid IN (".
                                                implode(',', array_keys($companyusers)).
                                               ")")) {
                foreach ($users as $user) {
                    $user->timeend = $timenow;
                    $DB->update_record('user_enrolments', $user);
                }
            }
            $DB->delete_records('company_course_groups', array('groupid', $group));
        }
        $DB->delete_records('company_shared_courses', array('courseid' => $courseid,
                                                            'companyid' => $companyid));
    }

    /**
     * Updates the theme reference for all the users in the company
     *
     * Parameters -
     *              $theme = string;
     *
     **/
    public function update_theme($theme) {
        global $DB;

        // Get the company users.
        $users = $this->get_all_user_ids();

        // Update their theme.
        foreach ($users as $userid) {
            if ($user = $DB->get_record('user', array('id' => $userid))) {
                $user->theme = $theme;
                $DB->update_record('user', $user);
            }
        }
    }

    /**
     * Suspends or Unsuspends a company and all of their users.
     *
     * Parameters -
     *              $theme = string;
     *
     **/
    public function suspend($suspend = true) {
        global $DB;

        // Get the company users.
        $users = $this->get_all_user_ids();

        // Update the users.
        foreach ($users as $userid) {
            if ($user = $DB->get_record('user', array('id' => $userid))) {
                // Does the user belong to another company?
                if ($DB->count_records('company_users', array('userid' => $userid)) > 1 ) {
                    // Belongs to more than one company.  Skip.
                    continue;
                }
                if (! $DB->get_record('company_users', array('userid' => $user->id, 'companyid' => $this->id, 'suspended' => 1))) {
                    $user->suspended  = $suspend;
                    $DB->update_record('user', $user);
                }
                if (!empty($suspend)) {
                    \core\session\manager::kill_user_sessions($user->id);
                }
            }
        }

        // Set the suspend field for the company.
        $DB->set_field('company', 'suspended', $suspend, array('id' => $this->id));

        // Deal with child companies.
        $childcompanies = $this->get_child_companies_recursive();
        if (!empty($childcompanies)) {
            foreach ($childcompanies as $childcomprec) {

                $childcompany = new company($childcomprec->id);
                $childcompany->suspend($suspend);
            }
        }
    }

    /**
     * Terminates a company's contract,
     * removing all course access and licenses for
     * all of their users.
     *
     **/
    public function terminate() {
        global $DB;

        $runtime = time();

        try {
            $transaction = $DB->start_delegated_transaction();

            // Update all of the company licenes to have an end-date of now.
            $DB->set_field('companylicense', 'expirydate', time(), array('companyid' => $this->id));

            // Get the company users.
            $users = $this->get_all_user_ids();

            // Update the users.
            foreach ($users as $userid) {
                if ($user = $DB->get_record('user', array('id' => $userid))) {
                    // Does the user belong to another company?
                    if ($DB->count_records('company_users', array('userid' => $userid)) > 1 ) {
                        // Belongs to more than one company.  Skip.
                        continue;
                    }
                    // Terminate all of their enrolments.
                    $usercourses = $DB->get_records_select('local_iomad_track',
                                                           'id',
                                                           'userid = :userid
                                                            AND courseid = :courseid
                                                            AND companyid = :companyid
                                                            AND coursecleared = 0
                                                            AND timecompleted > 0',
                                                           ['userid' => $userid,
                                                            'companyid' => $this->id,
                                                            'courseid' => $courseid]);
                    foreach ($usercourses as $licrec) {
                        // Remove this specific record.
                        company_user::delete_user_course($userid, $courseid, 'autodelete', $licrec->id);
                    }
                }
            }

            // Set the companyterminated field for the company.
            $DB->set_field('company', 'companyterminated', true, array('id' => $this->id));

            // Deal with local_iomad_track lines too.
            $DB->set_field('local_iomad_track', 'timeenrolled', $runtime, array('companyid' => $this->id, 'timeenrolled' => null));
            $DB->set_field('local_iomad_track', 'timestarted', $runtime, array('companyid' => $this->id, 'timestarted' => null));
            $DB->set_field('local_iomad_track', 'timecompleted', $runtime, array('companyid' => $this->id, 'timecompleted' => null));

            // Deal with child companies.
            $childcompanies = $this->get_child_companies_recursive();
            if (!empty($childcompanies)) {
                foreach ($childcompanies as $childcomprec) {

                    $childcompany = new company($childcomprec->id);
                    $childcompany->terminate();
                }
            }

            // All OK commit the transaction.
            $transaction->allow_commit();
            return true;

            // Create an event for this.  This handles the actual lifting.
            $eventother = array('companyid' => $company->id);
            $event = \block_iomad_company_admin\event\company_terminated::create(array('context' => \core\context\company::instance($company->id),
                                                                                       'objectid' => $company->id,
                                                                                       'userid' => $USER->id,
                                                                                       'other' => $eventother));
            $event->trigger();

        } catch(Exception $e) {
            $transaction->rollback($e);
            return false;
        }
    }

    /**
     * Enables or disables ecommerce for a company.
     *
     * Parameters -
     *              $ecommerce = booloean;
     *
     **/
    public function ecommerce($ecommerce) {
        global $CFG, $DB;

        // Set the ecommerce field for the company.
        $DB->set_field('company', 'ecommerce', $ecommerce, array('id' => $this->id));

        // Do we have to update it on the external site?
        if (!empty($ecommerce) && $CFG->commerce_enable_external && !empty($CFG->commerce_externalshop_url)) {
            // Let's set up the adhoc task.
            $task = new \block_iomad_company_admin\task\companyenableshop();
            $task->queue_task($this->id);
        }
    }

    /**
     * Checks that a passed department id is valid for the companyid.
     *
     * Parameters -
     *              $companyid = int;
     *              $departmentid = int;
     *
     * Returns boolean.
     *
     **/
    public static function check_valid_department($companyid, $departmentid) {
        global $DB;

        if ($DB->get_record('department', array('id' => $departmentid,
                                                'company' => $companyid))) {
            return true;
        } else {
            // is the department within a child company of the currently selected company?
            $thiscompany = new company($companyid);
            if ($childcompanies = $thiscompany->get_child_companies_recursive()) {
                foreach ($childcompanies as $childid => $ignore) {
                    if ($DB->get_record('department', array('id' => $departmentid,
                                                            'company' => $childid))) {
                        return true;
                    }
                }
            } 
            return false;
        }
        // Shouldn't get here.  Return a false in case.
        return false;
    }

    /**
     * Checks that a userid and department id is valid for the companyid.
     *
     * Parameters -
     *              $companyid = int;
     *              $departmentid = int;
     *              $userid = int;
     *
     * Returns boolean.
     *
     **/
    public static function check_valid_user($companyid, $userid, $deparmentid=0) {
        global $DB, $USER;

        // If current user is a site admin or they have appropriate capabilities then they can.
        if (is_siteadmin($userid) ||
            iomad::has_capability('block/iomad_company_admin:company_add', \core\context\company::instance($companyid))) {
            return true;
        }

        if (!empty($departmentid) && $DB->get_record('company_users', array('departmentid' => $departmentid,
                                                                            'companyid' => $companyid,
                                                                            'userid' => $userid))) {
            return true;
        } else if ($DB->get_records('company_users', array('companyid' => $companyid,
                                                           'userid' => $userid))) {
            return true;
        } else {
            // is the user in a child company?
            $company = new company($companyid);
            $children = $company->get_child_companies_recursive();
            if (!empty($children) &&
                $DB->get_records_sql("SELECT id FROM {company_users}
                                      WHERE userid = :userid
                                      and companyid IN (" . join(',', array_keys($children)) . ")",
                                      ['userid' => $userid])) {
                return true;
            } else {
                return false;
            }
        }
        // Shouldn't get here.  Return a false in case.
        return false;
    }

    /**
     * Checks that a userid is suspended the companyid.
     *
     * Parameters -
     *              $companyid = int;
     *              $userid = int;
     *
     * Returns boolean.
     *
     **/
    public static function check_user_suspended($companyid, $userid) {
        global $DB;

        if ($DB->get_records('company_users', ['companyid' => $companyid,
                                               'userid' => $userid,
                                               'suspended' => 1])) {
            return true;
        }

        return false;
    }

    /**
     * Checks number of new users to be added to the company won't bring it about the maximum.
     *
     * Parameters -
     *              $new = int;
     *
     * Returns boolean.
     *
     **/
    public function check_usercount($new = 0) {
        global $DB, $USER;

        // Get the company maximum.
        if (empty($this->companyrecord->maxusers)) {
            return true;
        } else {
            // Get the current number of users.
            // Deal with any parent companies.
            // all companies?
            if ($parentslist = $this->get_parent_companies_recursive()) {
                $companysql = " AND u.id NOT IN (
                                SELECT userid FROM {company_users}
                                WHERE companyid IN (" . implode(',', array_keys($parentslist)) ."))";
            } else {
                $companysql = "";
            }

            $usercount = $DB->count_records_sql("SELECT COUNT(DISTINCT u.id) FROM
                                                 {company_users} cu
                                                 JOIN {user} u ON (cu.userid = u.id)
                                                 WHERE cu.companyid = :companyid
                                                 AND u.deleted = 0
                                                 AND u.suspended = 0
                                                 $companysql",
                                                 array('companyid' => $this->id));
            if ($usercount + $new > $this->companyrecord->maxusers) {
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * Checks that the USER can edit a userid in a companyid.
     *
     * Parameters -
     *              $companyid = int;
     *              $userid = int;
     *
     * Returns boolean.
     *
     **/
    public static function check_canedit_user($companyid, $userid) {
        global $DB, $USER;

        // Can't edit an admin user here.
        if (is_siteadmin($userid)) {
            return false;
        }

        // If current user is a site admin or they have appropriate capabilities then they can.
        if (is_siteadmin($USER->id) ||
            iomad::has_capability('block/iomad_company_admin:company_add', \core\context\company::instance($companyid))) {
            return true;
        }

        // Get my companyid.
        $mycompanyid = iomad::get_my_companyid(context_system::instance());

        // If it doesn't match then return false.
        if ($mycompanyid != $companyid) {
            return false;
        }

        // Check if the user is in the company.
        if ($userrec = $DB->get_record('company_users', array('companyid' => $companyid,
                                                              'userid' => $userid))) {

            // Check the current user is a manager or not and what levels they can edit.
            if ($manrec = $DB->get_record('company_users', array('companyid' => $companyid,
                                                                 'userid' => $USER->id))) {
                if (empty($manrec->managertype)) {
                    return false;
                } else if ($manrec->managertype == 2 && $userrec->managertype == 1) {
                    return false;
                } else {
                    return true;
                }
            }
        }

        // Return a false by default.
        return false;
    }

    /**
     * Checks that a licenseid is valid for the companyid.
     *
     * Parameters -
     *              $companyid = int;
     *              $licenseid = int;
     *
     * Returns boolean.
     *
     **/
    public static function check_valid_company_license($companyid, $licenseid) {
        global $DB;

        if ($DB->get_record('companylicense', array('companyid' => $companyid,
                                                    'id' => $licenseid))) {
            return true;
        }

        // Is it a child license?
        $company = new company($companyid);

        // Return a false by default.
        return $company->is_child_license($licenseid);
    }

    /**
     * Checks that a two user id's are in the same company.
     *
     * Parameters -
     *              $userid = int;
     *
     * Returns boolean.
     *
     **/
    public static function check_can_manage($userid) {
        global $DB, $USER;

        // Set the companyid
        $companyid = iomad::get_my_companyid(context_system::instance(), false);

        if ($companyid > 0) {
            // Get the company context.
            $companycontext = \core\context\company::instance($companyid);
        } else {
            $companycontext = context_system::instance();
        }

        // If this is ourselves or we can see all users then we can see this one.
        if ($USER->id == $userid ||
            iomad::has_capability('block/iomad_company_admin:editallusers', $companycontext)) {
            return true;
        }

        // Get the list of users.
        $myusers = self::get_my_users($companyid);

        // If the user is in the list, return true.
        if (!empty($myusers[$userid])) {
            return true;
        }

        // Return a false by default.
        return false;
    }

    /**
     * Gets the department ID on user creation.
     *
     * Parameters -
     *              $user = object;
     *
     * Returns int.
     *
     **/
    public function get_auto_department($user) {
        global $DB;

        $topdepartment = self::get_company_parentnode($this->id);
        $departmentid = $topdepartment->id;

        // check if there is a different match.
        if (!empty($this->companyrecord->departmentprofileid)) {
            // get the profile field;
            if ($field = $DB->get_record('user_info_field', ['id' => $this->companyrecord->departmentprofileid])) {
                $fieldname = 'profile_field_' . $field->shortname;
                profile_load_data($user);
                if (!empty($user->$fieldname)) {
                    if ($department = $DB->get_record('department', ['name' => $user->$fieldname, 'company' => $this->id])) {
                        $departmentid = $department->id;
                    }
                }
            }
        }

        // Return departmentid.
        return $departmentid;
    }

    /**
     * Automatically enrols a users on un-licensed courses if its set in the config.
     *
     * Parameters -
     *              $user = stdclass();
     *
     **/
    public function autoenrol($user, $due = 0) {
        global $DB, $CFG, $SESSION, $SITE, $OUTPUT;

        // Did we get passed a user id?
        if (!is_object($user)) {
            $userrec = $DB->get_record('user', array('id' => $user));
            $user = $userrec;
        }

        // Get all of the courses the company can see.
        $companycoursesql = "";
        if ($companycourses = $this->get_menu_courses(true, false, false, false, false)) {
            $companycoursesql = " AND courseid IN (" . join(',', array_keys($companycourses)) . ")";
        }

        // Get the courses which are assigned to the company which are not licensed.
        $courses = $DB->get_records_sql("SELECT DISTINCT courseid
                                         FROM {company_course_autoenrol}
                                         WHERE companyid = :companyid
                                         AND autoenrol = 1
                                         $companycoursesql",
                                         array('companyid' => $this->id));

        // Get all of the licensed courses.
        $licensecourses = $DB->get_records_sql("SELECT courseid FROM {iomad_courses} WHERE licensed = 1");

        // Are we also enrolling to unattached courses?
        if (!empty($CFG->local_iomad_signup_autoenrol_unassigned)) {
            $unassignedcourses = $DB->get_records_sql("SELECT id AS courseid FROM {course}
                                                       WHERE id NOT IN (
                                                        SELECT courseid FROM {company_course}
                                                       )
                                                       AND id != :siteid",
                                                       array('siteid' => $SITE->id));
            $courses = $courses + $unassignedcourses;
        }

        // Enrol the user onto them.
        $errors = '';
        foreach ($courses as $addcourse) {
            if ($course = $DB->get_record_sql("SELECT id,fullname FROM {course} WHERE id = :courseid AND visible = 1",
                                               array('courseid' => $addcourse->courseid))) {

                // Check if this is a licensed course.
                if (!empty($licensecourses[$course->id])) {
                    if ($newlicense = company_user::auto_allocate_license($user->id, $this->id, $course->id)) {

                        // Create an event.
                        $eventother = array('licenseid' => $newlicense->licenseid,
                                            'issuedate' => time(),
                                            'duedate' => $due);
                        $event = \block_iomad_company_admin\event\user_license_assigned::create(array('context' => context_course::instance($course->id),
                                                                                                      'objectid' => $newlicense->id,
                                                                                                      'courseid' => $course->id,
                                                                                                      'userid' => $user->id,
                                                                                                      'other' => $eventother));
                        $event->trigger();
                    } else {
                        $errors .= format_string($course->fullname) . " ";
                    }
                } else {
                    company_user::enrol($user, array($course->id), $this->id, false, false, $due);
                }
            }
        }
        if (!empty($errors)) {
            //We only want to be notified of this once as sometimes this gets run multiple times.
            if (empty($SESSION->autoenrolonuser) || $SESSION->autoenrolonuser != $user->id) {
                $notify = new \core\output\notification(get_string('autoenrolmentfailed', 'block_iomad_company_admin', $errors),
                                                        \core\output\notification::NOTIFY_WARNING);
                echo $OUTPUT->render($notify);
                $SESSION->autoenrolonuser = $user->id;
            }
        }
    }

    // Competencies stuff.

    /**
     * Associates a ccompetency framework to a company
     *
     * Parameters -
     *              $framework = stdclass();
     *
     **/
    public static function add_competency_framework($companyid, $frameworkid) {
        global $DB;

        if (!$DB->record_exists('company_comp_frameworks', array('companyid' => $companyid,
                                                                 'frameworkid' => $frameworkid))) {
            $DB->insert_record('company_comp_frameworks', array('companyid' => $companyid,
                                                                'frameworkid' => $frameworkid));
        }
    }

    /**
     * Removes a course from a company
     *
     * Parameters -
     *              $course = stdclass();
     *              $companyid = int;
     *              $departmentid = int;
     *
     **/
    public static function remove_competency_framework($companyid, $frameworkid) {
        global $DB;

        $DB->delete_records('company_comp_frameworks', array('companyid' => $companyid,
                                                             'frameworkid' => $frameworkid));
    }

    /**
     * Associates a ccompetency framework to a company
     *
     * Parameters -
     *              $template = stdclass();
     *
     **/
    public static function add_competency_template($companyid, $templateid) {
        global $DB;

        if (!$DB->record_exists('company_comp_templates', array('companyid' => $companyid,
                                                                'templateid' => $templateid))) {
            $DB->insert_record('company_comp_templates', array('companyid' => $companyid,
                                                               'templateid' => $templateid));
        }
    }

    /**
     * Removes a course from a company
     *
     * Parameters -
     *              $template = stdclass();
     *
     **/
    public static function remove_competency_template($companyid, $templateid) {
        global $DB;

        $DB->delete_records('company_comp_templates', array('companyid' => $companyid,
                                                            'templateid' => $templateid));
    }

    /**
     * checks if it is OK use an email template.
     *
     **/
    public function email_template_is_enabled($templatename, $managertype = 0) {
        global $DB;

        if ($DB->get_records('email_template', array('companyid' => $this->id, 'name' => $templatename, 'disabled' => 0, 'disabledmanager' => 0, 'disabledsupervisor' => 0))) {
            // Fully enabled for the company.
            return true;
        }

        if ($managertype == 0) {
            if ($DB->get_records('email_template', array('companyid' => $this->id, 'name' => $templatename, 'disabled' => 1))) {
                // Disabled for the company.
                return false;
            }
        }

        if ($managertype == 1) {
            if ($DB->get_records('email_template', array('companyid' => $this->id, 'name' => $templatename, 'disabledmanager' => 1))) {
                // Disabled for the company.
                return false;
            }
        }

        if ($managertype == 2) {
            if ($DB->get_records('email_template', array('companyid' => $this->id, 'name' => $templatename, 'disabledsupervisor' => 1))) {
                // Disabled for the company.
                return false;
            }
        }

        // default is true as the template may not have been defined outside of defaults.
        return true;
    }

    /**
     * Set the company SMTP settings.
     * @param mailer moodle_php_mailer object
     *
     * Returns the same.
     */
    public static function set_company_mailer($mailer, $companyid = 0) {
        global $CFG;

        if (empty($companyid)) {
            $companyid = iomad::get_my_companyid(context_system::instance(), false);
        }

        // Did we get anything?
        if (empty($companyid)) {
            return $mailer;
        }

        // Deal withe the potential settings that could be changed.
        $possiblesettings = ['type' => 'smtphosts',
                             'SMTPDebug' => 'debugsmtp',
                             'SMTPSecure' => 'smtpsecure',
                             'AuthType' => 'smtpauthtype',
                             'smtpoauthservice' => 'smtpoauthservice',
                             'Username' => 'smtpuser',
                             'Password' => 'smtppass',
                             'smtpmaxbulk' => 'smtpmaxbulk',
                             'noreplyaddress' => 'noreplyaddress',
                             'DKIM_selector' => 'emaildkimselector'];

        // Make the changes.
        foreach ($possiblesettings as $name => $possiblesetting) {
            $settingfield = $possiblesetting . $companyid;
            if (!empty($CFG->$settingfield)) {
                if ($name != 'type' ) {
                    if ($name == 'Username' && !(empty($possiblesetting) || empty($CFG->smtpuser))) {
                        $mailer->SMTPAuth = true;
                    }
                    $mailer->$name = $CFG->$settingfield;
                } else {
                    if ($possiblesetting == 'qmail') {
                        // Use Qmail system.
                        $mailer->isQmail();

                    } else if (empty($possiblesetting) && empty($CFG->smpthosts)) {
                        // Use PHP mail() = sendmail.
                        $mailer->isMail();

                    } else {
                        // Use SMTP directly.
                        $mailer->isSMTP();
                        if (!empty($CFG->debugsmtp) && (!empty($CFG->debugdeveloper))) {
                            $mailer->SMTPDebug = 3;
                        }

                        // Specify mail server.
                        $mailer->Host = $CFG->$settingfield;
                    }
                }
            }
        }

        if (empty($mailer->noreplyaddress)) {
            $noreplyaddressdefault = 'noreply@' . get_host_from_url($CFG->wwwroot);
            $mailer->noreplyaddress = empty($CFG->noreplyaddress) ? $noreplyaddressdefault : $CFG->noreplyaddress;
        }

        return $mailer;
    }

    /**
     * Update plugin settings for given plugin and postfix.
     *
     * @param pluginname
     * @param postfic
     */
    public static function update_plugin($pluginname, $postfix) {
        if (empty($pluginname) || empty ($postfix)) {
            return;
        }
        $settings = [];
        $currentsettings = [];
        $pluginsettings = get_config($pluginname);
        foreach ($pluginsettings as $setting => $value) {
            if (preg_match('/_'.$postfix.'$/', $setting)) {
                $currentsettings[$setting] = $value;
            } else if ($setting == 'version' || preg_match('/_\d+$/', $setting)) {
                continue;
            } else {
                $settings[$setting] = $value;
            }
        }
        // should have all the defaults - strip any we have config for.
        foreach ($currentsettings as $current => $dump) {
            unset($settings[$current]);
        }
        // Set any missing.
        foreach ($settings as $setting => $value) {
            set_config($setting . $postfix, $value, $pluginname);
        }
    }

    /***  Event Handlers  ***/

    /**
     * Triggered via company_created event.
     *
     * @param \core\event\company_created $event
     * @return bool true on success.
     */
    public static function company_created(\block_iomad_company_admin\event\company_created $event) {
        global $CFG, $DB, $USER;

        $companyid = $event->other['companyid'];
        if (!$company = $DB->get_record('company', array('id' => $companyid))) {
            return;
        }

        if ($CFG->commerce_enable_external && !empty($CFG->commerce_externalshop_url)) {
            // Fire off the payload to the external site.
            if (empty($CFG->commerce_admin_enableall) && empty($company->ecommerce)) {
                return true;
            }

            require_once($CFG->dirroot . '/blocks/iomad_commerce/locallib.php');
            iomad_commerce::update_company($company, $company);
        }

        return true;
    }

    /**
     * Triggered via company_suspended event.
     *
     * @param \block_iomad_company_user\event\company_suspended $event
     * @return bool true on success.
     */
    public static function company_suspended(\block_iomad_company_admin\event\company_suspended $event) {
        global $DB, $CFG;

        $companyid = $event->other['companyid'];

        if (empty($companyid) || !$companyrecord = $DB->get_record('company', array('id' => $companyid))) {
            return;
        }

        $suspendcompany = new company($companyid);
        $suspendcompany->suspend(true);

        // Get the company managers.
        $managers = $DB->get_records('company_users', array('companyid' => $companyid, 'managertype' => 1));
        foreach ($managers as $manager) {
            $user = $DB->get_record('user', array('id' => $manager->userid));
            EmailTemplate::send('company_suspended',
                                 array('company' => $suspendcompany,
                                       'user' => $user));
        }

        return true;
    }

    /**
     * Triggered via company_unsuspended event.
     *
     * @param \block_iomad_company_user\event\company_unsuspended $event
     * @return bool true on success.
     */
    public static function company_unsuspended(\block_iomad_company_admin\event\company_unsuspended $event) {
        global $DB, $CFG;

        $companyid = $event->other['companyid'];

        if (empty($companyid) || !$companyrecord = $DB->get_record('company', array('id' => $companyid))) {
            return;
        }

        $suspendcompany = new company($companyid);
        $suspendcompany->suspend(false);

        // Get the company managers.
        $managers = $DB->get_records('company_users', array('companyid' => $companyid, 'managertype' => 1));
        foreach ($managers as $manager) {
            $user = $DB->get_record('user', array('id' => $manager->userid));
            EmailTemplate::send('company_unsuspended',
                                 array('company' => $suspendcompany,
                                       'user' => $user));
        }

        return true;
    }

    /**
     * Triggered via company_updated event.
     *
     * @param \core\event\company_updated $event
     * @return bool true on success.
     */
    public static function company_updated(\block_iomad_company_admin\event\company_updated $event) {
        global $CFG, $DB;

        $companyid = $event->other['companyid'];
        if (!$company = $DB->get_record('company', array('id' => $companyid))) {
            return;
        }

        $oldcompany = json_decode( $event->other['oldcompany']);

        if ($CFG->commerce_enable_external && !empty($CFG->commerce_externalshop_url)) {
            // Fire off the payload to the external site.
            require_once($CFG->dirroot . '/blocks/iomad_commerce/locallib.php');
            iomad_commerce::update_company($company, $oldcompany);
        }

        // Check if the company name has changed.
        if ($company->name != $oldcompany->name) {
            $coursecat = $DB->get_record('course_categories', array('id' => $company->category),'*',MUST_EXIST);
            $coursecat->name = $company->name;
            $DB->update_record('course_categories', $coursecat);
            fix_course_sortorder();
        }

        return true;
    }

    /**
     * Triggered via company_updated event.
     *
     * @param \core\event\company_deleted $event
     * @return bool true on success.
     */
    public static function company_deleted(\block_iomad_company_admin\event\company_deleted $event) {
        global $CFG, $DB;

        $companyid = $event->other['companyid'];
        if (!$company = $DB->get_record('company', array('id' => $companyid))) {
            return;
        }

        // Update the company name to mark it that its being deleted.
        $company->name = get_string('deletingcompany', 'block_iomad_company_admin', $company->name);
        $DB->update_record('company', $company);

        // Set up the adhoc task to do this.
        // Fire off the adhoc task to populate this new field correctly.
        $task = new local_iomad\task\deletecompanytask();
        $task->set_custom_data(['companyid' => $companyid]);
        \core\task\manager::queue_adhoc_task($task, true);

        return true;
    }

    /**
     * Triggered via competency_framework_created event.
     *
     * @param \core\event\competency_framework_created $event
     * @return bool true on success.
     */
    public static function competency_framework_created(\core\event\competency_framework_created $event) {
        $data = $event->get_data();
        if (!empty($data['companyid'])) {
            self::add_competency_framework($data['companyid'], $event->objectid);
        }
        return true;
    }

    /**
     * Triggered via competency_framework_deleted event.
     *
     * @param \core\event\competency_framework_deleted $event
     * @return bool true on success.
     */
    public static function competency_framework_deleted(\core\event\competency_framework_deleted $event) {
        global $DB;
        $DB->delete_records('company_comp_frameworks', array('frameworkid' => $event->objectid));
        return true;
    }

    /**
     * Triggered via competency_template_created event.
     *
     * @param \core\event\competency_template_created $event
     * @return bool true on success.
     */
    public static function competency_template_created(\core\event\competency_template_created $event) {

        $data = $event->get_data();
        if (!empty($data['companyid'])) {
            self::add_competency_template($data['companyid'], $event->objectid);
        }
        return true;
    }

    /**
     * Triggered via competency_template_deleted event.
     *
     * @param \core\event\competency_template_deleted $event
     * @return bool true on success.
     */
    public static function competency_template_deleted(\core\event\competency_template_deleted $event) {
        global $DB;
        $DB->delete_records('company_comp_templates', array('templateid' => $event->objectid));
        return true;
    }

    /**
     * Triggered via course_completed event.
     *
     * @param \core\event\course_completed $event
     * @return bool true on success.
     */
    public static function course_completed(\core\event\course_completed $event) {
        global $DB, $CFG;

        $data = $event->get_data();
        $userid = $data['relateduserid'];
        $courseid = $data['courseid'];

        // Get the enrolment record as the completion record isn't fully formed at this point.
        if (!$enrolrec = $DB->get_record_sql("SELECT ue.* FROM {user_enrolments} ue
                                         JOIN {enrol} e ON (ue.enrolid = e.id)
                                         WHERE ue.userid = :userid
                                         AND e.courseid = :courseid
                                         AND e.status = 0",
                                         array('userid' => $userid,
                                               'courseid' => $courseid))) {
            // User isn't enrolled. Not sure why we got this.
            return true;
        }

        // Do not send if this is already recorded.
        if (!empty($enrolrec->timestart)) {
            if ($trackrecs = $DB->get_records_sql("SELECT * FROM {local_iomad_track}
                                                   WHERE userid=:userid
                                                   AND courseid = :courseid
                                                   AND timeenrolled > :timelow
                                                   AND timeenrolled < :timehigh",
                                                  ['userid' => $userid,
                                                   'courseid' => $courseid,
                                                   'timelow' => $enrolrec->timestart - 10,
                                                   'timehigh' => $enrolrec->timestart + 10])) {
                foreach ($trackrecs as $trackrec) {
                    // Check if this enrolment time has already been processed.
                    if ($trackrec->timecompleted !=null && (round($trackrec->timecompleted  / 10 ) * 10) != (round($data['timecreated'] /10) *10)) {
                        // It has - ignore it.
                        continue;
                    }

                    // Build the emails.
                    $course = $DB->get_record('course', array('id' => $courseid));
                    $user = $DB->get_record('user', array('id' => $userid));
                    $company = new company($trackrec->companyid);
                    $attachment = (object) [];
                    if ($trackfileinfo = $DB->get_record('local_iomad_track_certs', array('trackid' => $trackrec->id))) {
                        $fileinfo = $DB->get_record('files', array('itemid' => $trackrec->id, 'component' => 'local_iomad_track', 'filename' => $trackfileinfo->filename));
                        $filedir1 = substr($fileinfo->contenthash,0,2);
                        $filedir2 = substr($fileinfo->contenthash,2,2);
                        $attachment->filepath = $CFG->dataroot . '/filedir/' . $filedir1 . '/' . $filedir2 . '/' . $fileinfo->contenthash;
                        $attachment->filename = $trackfileinfo->filename;
                    }

                    // Initial set up for handling programs.
                    $complete = false;
                    if(!empty($trackrec->licenseid) && $DB->get_record('companylicense', array('id' => $trackrec->licenseid, 'program' => 1))) {
                        $licenses = $DB->get_records('companylicense_users', array('licenseid' => $trackrec->licenseid));
                        foreach ($licenses as $license) {
                            if ($license->isusing && $DB->get_record_sql("SELECT id FROM {course_completions}
                                                                          WHERE userid = :userid
                                                                          AND course = :courseid
                                                                          AND timecompleted IS NOT NULL",
                                                                          array('courseid' => $license->licensecourseid,
                                                                                'userid' => $user->id))) {
                                $complete = true;
                            }
                        }
                    }
                    if (!$complete) {
                        EmailTemplate::send('completion_course_user', array('course' => $course, 'user' => $user, 'company' => $company, 'attachment' => $attachment));
                        $supervisortemplate = new EmailTemplate('completion_course_supervisor', array('course' => $course, 'user' => $user, 'company' => $company, 'attachment' => $attachment));
                        $supervisortemplate->email_supervisor();
                    } else {
                        EmailTemplate::send('user_programcompleted', array('course' => $course, 'user' => $user, 'company' => $company, 'attachment' => $attachment));
                    }
                }
            }
        }

        return true;
    }

    /**
     * Triggered via user_created event.
     *
     * @param \core\event\user_created $event
     * @return bool true on success.
     */
    public static function user_created(\core\event\user_created $event) {
        global $DB, $CFG;

        $userid = $event->objectid;
        $companyid = $event->companyid;
        $user = $DB->get_record('user', array('id' => $userid));
        $user->manager = 'no';

        if ($CFG->commerce_enable_external && !empty($CFG->commerce_externalshop_url)) {
            if (!empty($companyid)) {
                $company = new company($companyid);
                if (empty($CFG->commerce_admin_enableall) && empty($company->companyrecord->ecommerce)) {
                    return true;
                }
                if (empty($user->company)) {
                    $user->company = $company->get_name();
                }
            }

            // Fire off the payload to the external site.
            require_once($CFG->dirroot . '/blocks/iomad_commerce/locallib.php');
            iomad_commerce::update_user($user, $company->id);
        }

        return true;
    }

    /**
     * Triggered via user_updated event.
     *
     * @param \core\event\user_updated $event
     * @return bool true on success.
     */
    public static function user_updated(\core\event\user_updated $event) {
        global $DB, $CFG;

        $userid = $event->relateduserid;
        $user = $DB->get_record('user', array('id' => $userid));

        // Get all of the companies the user is tied to
        $usercompanies = $DB->get_records_sql("SELECT DISTINCT c.*
                                               FROM {company} c 
                                               JOIN {company_users} cu ON (c.id = cu.companyid)
                                               WHERE cu.userid = :userid",
                                               array('userid' => $userid));

        foreach ($usercompanies as $usercompany) {
            $company = new company($usercompany->id);


            if ($DB->get_record('company_users', array('userid'=> $user->id, 'companyid' => $usercompany->id, 'managertype' => 1))) {
                $user->manager = 'yes';
                $user->country = $usercompany->country;
                $user->city = $usercompany->city;
                $user->adress = "";
            } else {
                $user->manager = 'no';
            }
            $user->company = $company->get_name();

            if ($CFG->commerce_enable_external && !empty($CFG->commerce_externalshop_url)) {
                if (empty($CFG->commerce_admin_enableall) && empty($usercompany->ecommerce)) {
                    continue;
                }

                // Fire off the payload to the external site.
                require_once($CFG->dirroot . '/blocks/iomad_commerce/locallib.php');
                iomad_commerce::update_user($user, $company->id);
            }
        }

        // Check if we are assigning department by profile field.
        if (!empty($CFG->iomad_sync_department) &&
            $CFG->iomad_sync_department == 2) {
            // Check if there is a department with the name given.
            $current = $DB->count_records('department', ['company' => $company->id, 'name' => $user->department]);
            if ($current == 1) {
                // Assign them to the department.
                $department = $DB->get_record('department', ['company' => $company->id, 'name' => $user->department]);
                if ($currentdepartments = $DB->get_records('company_users', ['companyid' => $company->id, 'userid' => $user->id])) {
                    // We only do anything if they are in one department.
                    if (count($currentdepartments) == 1) {
                        foreach ($currentdepartments as $currentdepartment) {
                            // Only move them if they are not a company manager.
                            if ($currentdepartment->managertype != 1) {
                                $DB->set_field('company_users', 'departmentid', $department->id, ['id' => $currentdepartment->id]);
                            }
                        }
                    }
                } else {
                    // Assign them to this department as they aren't in any yet.
                    self::assign_user_to_department($department->id, $user->id);
                }
            } else if ($current == 0) {
                // Department doesn't exist yet. Create it!
                $shortname = str_replace(' ', '-', $user->department);
                $shortname = preg_replace('/[^A-Za-z0-9\-]/', '', $shortname);
                $topdepartment = self::get_company_parentnode($company->id);
                self::create_department(0, $company->id, $user->department, $shortname, $topdepartment->id);
                // Get the new department.
                $department = $DB->get_record('department', ['company' => $company->id, 'shortname' => $shortname]);
                if ($currentdepartments = $DB->get_records('company_users', ['companyid' => $company->id, 'userid' => $user->id])) {
                    // We only do anything if they are in one department.
                    if (count($currentdepartments) == 1) {
                        foreach ($currentdepartments as $currentdepartment) {
                            // Only move them if they are not a company manager.
                            if ($currentdepartment->managertype != 1) {
                                $DB->set_field('company_users', 'departmentid', $department->id, ['id' => $currentdepartment->id]);
                            }
                        }
                    }
                } else {
                    // Assign them to this department as they aren't in any yet.
                    self::assign_user_to_department($department->id, $user->id);
                }
            }
        }

        return true;
    }

    /**
     * Triggered via user_suspended event.
     *
     * @param \core\event\user_suspended $event
     * @return bool true on success.
     */
    public static function user_suspended(\core\event\user_suspended $event) {
        global $DB;

        $userid = $event->objectid;
        $timestamp = time();

        $user = $DB->get_record('user', array('id' => $userid));

        // Get all of the companies the user is tied to
        $usercompanies = $DB->get_records_sql("SELECT DISTINCT companyid
                                               FROM {company_users}
                                               WHERE userid = :userid",
                                               array('userid' => $userid));

        foreach ($usercompanies as $usercompany) {
            $company = new company($usercompany->companyid);
            company_user::suspend($userid, $usercompany->companyid);
            EmailTemplate::send('user_suspended',
                             array('company' => $company,
                                   'user' => $user));
        }

        return true;
    }

    /**
     * Triggered via user_suspended event.
     *
     * @param \core\event\user_suspended $event
     * @return bool true on success.
     */
    public static function user_unsuspended(\core\event\user_unsuspended $event) {
        global $DB;

        $userid = $event->objectid;
        $timestamp = time();

        $user = $DB->get_record('user', array('id' => $userid));

        // Get all of the companies the user is tied to
        $usercompanies = $DB->get_records_sql("SELECT DISTINCT companyid
                                               FROM {company_users}
                                               WHERE userid = :userid",
                                               array('userid' => $userid));

        foreach ($usercompanies as $usercompany) {
            $company = new company($usercompany->companyid);
            company_user::suspend($userid, $usercompany->companyid);
            EmailTemplate::send('user_unsuspended',
                             array('company' => $company,
                                   'user' => $user));
        }

        return true;
    }

    /**
     * Triggered via user_enrolment_created event.
     *
     * @param \core\event\user_enrolment_created $event
     * @return bool true on success.
     */
    public static function user_enrolment_created(\core\event\user_enrolment_created $event) {
        global $DB, $CFG;

        $userid = $event->relateduserid;
        $timestamp = $event->timecreated;
        $courseid = $event->courseid;
        $companyid = $event->companyid;

        // Were we passed a companyid?
        if (empty($companyid)) {
            return true;
        }

        // Is this a shared course?
        if ($DB->get_record('iomad_courses', array('courseid' => $courseid, 'shared' => 0))) {
            // It's not - return.
            return true;
        }

        // Does this course have groups?
        if (!$DB->get_record('course', array('id' => $courseid, 'groupmode' => 1))) {
            // It doesn't - return.
            return true;
        }

        // Add the user to the appropriate course group.
        self::add_user_to_shared_course($courseid, $userid, $companyid);

        return true;
    }

    /**
     * Triggered via user_licensed_used event.
     *
     * @param \block_iomad_company_admin\event\user_license_used $event
     * @return bool true on success.
     */
    public static function user_license_used(\block_iomad_company_admin\event\user_license_used $event) {
        global $DB, $CFG;

        $userid = $event->userid;
        $timestamp = $event->timecreated;
        $courseid = $event->courseid;
        $licenserecordid = $event->objectid;
        $licenseid = $event->other['licenseid'];

        // Does this record exist?
        if (!$userlicenserecord = $DB->get_record('companylicense_users', array('id' => $licenserecordid))) {
            // It's not - return.
            return true;
        }

        // Does this record exist?
        if (!$licenserecord = $DB->get_record('companylicense', array('id' => $licenseid))) {
            // It's not - return.
            return true;
        }

        // Does this license allocation have a specified group?
        if (empty($userlicenserecord->groupid)) {
            // It doesn't - return.
            return true;
        }

        // Add the user to the specific groupid.
        self::add_user_to_shared_course($courseid, $userid, $licenserecord->companyid, $userlicenserecord->groupid, true);

        return true;
    }

    /**
     * Triggered via user_deleted event.
     *
     * @param \core\event\user_deleted $event
     * @return bool true on success.
     */
    public static function user_deleted(\core\event\user_deleted $event) {
        global $DB, $CFG;

        $userid = $event->objectid;
        $timestamp = time();

        // Get all of the companies the user is tied to
        $usercompanies = $DB->get_records_sql("SELECT DISTINCT companyid
                                               FROM {company_users}
                                               WHERE userid = :userid",
                                               array('userid' => $userid));

        foreach ($usercompanies as $usercompany) {
            $company = new company($usercompany->companyid);
            $company->unassign_user_from_company($userid);

            $user = $DB->get_record('user', array('id' => $userid));
            EmailTemplate::send('user_deleted',
                                 array('company' => $usercompany,
                                       'user' => $user));
        }

        return true;
    }

    /**
     * Triggered via company_user_assigned event.
     *
     * @param \block_iomad_company_user\event\company_user_assigned $event
     * @return bool true on success.
     */
    public static function company_user_assigned(\block_iomad_company_admin\event\company_user_assigned $event) {
        global $DB, $CFG;

        $companyid = $event->objectid;
        $userid = $event->userid;
        $company = new company($companyid);
        $companyrec = $DB->get_record('company', array('id' => $companyid));
        $user = $DB->get_record('user', array('id' => $userid));

        // We only care if its a company manager.
        if ($event->other['usertype'] == 1) {
            $childcompanies = $company->get_child_companies_recursive();

            foreach ($childcompanies as $child) {
                $childcompany = new company($child->id);
                $childcompany->assign_user_to_company($userid, 0, $event->other['usertype'], true);
            }
        }

        if ($CFG->commerce_enable_external && !empty($CFG->commerce_externalshop_url)) {
            if (empty($CFG->commerce_admin_enableall) && empty($company->companyrecord->ecommerce)) {
                return true;
            }
            // Fire off the payload to the external site.
            require_once($CFG->dirroot . '/blocks/iomad_commerce/locallib.php');
            iomad_commerce::assign_user($user, $companyrec->name, $companyrec->id);
        }

        return true;
    }

    /**
     * Triggered via company_user_unassigned event.
     *
     * @param \block_iomad_company_user\event\company_user_unassigned $event
     * @return bool true on success.
     */
    public static function company_user_unassigned(\block_iomad_company_admin\event\company_user_unassigned $event) {
        global $DB;

        // We only care if its a company manager.
        if ($event->other['usertype'] != 1) {
            return true;
        }
        $companyid = $event->objectid;
        $userid = $event->userid;

        $company = new company($companyid);
        $childcompanies = $company->get_child_companies_recursive();

        foreach ($childcompanies as $child) {
            $childcompany = new company($child->id);
            $childcompany->unassign_user_from_company($userid, true);
        }

        return true;
    }

    /**
     * Triggered via user_license_assigned event.
     *
     * @param \block_iomad_company_user\event\user_license_assigned $event
     * @return bool true on success.
     */
    public static function user_license_assigned(\block_iomad_company_admin\event\user_license_assigned $event) {
        global $DB, $CFG;

        $userid = $event->userid;
        $userlicid = $event->objectid;
        $licenseid = $event->other['licenseid'];
        $courseid = $event->courseid;
        $duedate = $event->other['duedate'];
        if (!empty($event->other['noemail'])) {
            $noemail = true;
        } else {
            $noemail = false;
        }

        if (!$licenserecord = $DB->get_record('companylicense', array('id'=>$licenseid))) {
            return;
        }

        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            return;
        }

        if (!$user = $DB->get_record('user', array('id' => $userid))) {
            return;
        }

        $license = new stdclass();
        $license->length = $licenserecord->validlength;
        $license->valid = date($CFG->iomad_date_format, $licenserecord->expirydate);
        $license->startdate = date($CFG->iomad_date_format, $licenserecord->startdate);

        if (!$noemail) {
        // Send out the email.
            $company = new company($licenserecord->companyid);
            EmailTemplate::send('license_allocated', array('course' => $course,
                                                           'company' => $company,
                                                           'user' => $user,
                                                           'due' => $duedate,
                                                           'license' => $license));
        }

        // Update the license usage.
        self::update_license_usage($licenseid);

        // Check if we need to warn about usage.
        $licenserec = $DB->get_record('companylicense', ['id' => $licenseid]);
        if ($licenserec->used/$licenserec->allocation * 100 > 90) {
            // Get the company managers.
            if ($companymanagers = $DB->get_records_sql("SELECT u.*
                                                         FROM {user} u
                                                         JOIN {company_users} cu ON (u.id = cu.userid)
                                                         WHERE u.deleted = 0
                                                         AND u.suspended = 0
                                                         AND cu.companyid = :companyid
                                                         AND cu.managertype =1",
                                                        ['companyid' => $company->id])) {
                foreach ($companymanagers as $companymanager) {
                    EmailTemplate::send('licensepoolwarning', array('course' => $course,
                                                                    'company' => $company,
                                                                    'user' => $companymanager,
                                                                    'license' => $license));
                }
            }
        }

        // Is this an immediate license?
        if (!empty($licenserecord->instant)) {
            if (self::license_ok_to_use($licenseid, $courseid, $userid)) {
                if ($instance = $DB->get_record('enrol', array('courseid' => $course->id, 'enrol' => 'license'))) {
                    // Enrol the user on the course.
                    $enrol = enrol_get_plugin('license');

                    // Enrol the user in the course.
                    // Is the license available yet and specifed time is before this?
                    if ((!empty($licenserecord->startdate) && $licenserecord->startdate > time()) &&
                        (!empty($duedate) && $licenserecord->startdate > $duedate)) {
                        // If not set up the enrolment from when it is.
                        $timestart = $licenserecord->startdate;
                    } else if (!empty($duedate)) {
                        // Start it when the emails are due to go out.
                        $timestart = $duedate;
                    } else {
                        // Otherwise start it now.
                        $timestart = time();
                    }

                    if ($licenserecord->type == 0 || $licenserecord->type == 2) {
                        // Set the timeend to be time start + the valid length for the license in days.
                        $timeend = $timestart + ($licenserecord->validlength * 24 * 60 * 60 );
                    } else {
                        // Set the timeend to be when the license runs out.
                        $timeend = $licenserecord->expirydate;
                    }

                    if ($licenserecord->type < 2) {
                        if (!is_enrolled(context_course::instance($instance->courseid), $user->id)) {
                            $enrol->enrol_user($instance, $user->id, $instance->roleid, $timestart, $timeend);
                        } else if ($completedrecords = $DB->get_records_select('local_iomad_track',
                                                                                "userid = :userid
                                                                                 AND courseid = :courseid
                                                                                 AND timecompleted IS NOT NULL
                                                                                 AND coursecleared = 0
                                                                                 AND licenseallocated != :timeallocated",
                                                                                 ['userid' => $userid,
                                                                                 'courseid' => $course->id,
                                                                                 'timeallocated' => $event->timecreated])) {
                            // All previous attempts have been completed so enrol again.
                            foreach ($completedrecords as $completedrecord) {
                                // Complete any license allocations.
                                if ($licenserecord = $DB->get_record('companylicense_users', ['userid' => $completedrecord->userid,
                                                                                              'licensecourseid' => $completedrecord->courseid,
                                                                                              'licenseid' => $completedrecord->licenseid,
                                                                                              'issuedate' => $completedrecord->licenseallocated])) {
                                    if (empty($licenserecord->timecompleted)) {
                                        $DB->set_field('companylicense_users', 'timecompleted', $timestart, ['id' => $licenserecord->id]);
                                    }
                                }
                                $DB->set_field('local_iomad_track', 'completedstop', 1, ['id' => $completedrecord->id]);
                            }
                            // Clear them from the course.
                            company_user::delete_user_course($user->id, $course->id, 'autodelete');

                            // Then re-enrol them.
                            $enrol->enrol_user($instance, $user->id, $instance->roleid, $timestart, $timeend);
                        } 
                    } else {
                        // Educator role.
                        if ($DB->get_record('iomad_courses', array('courseid' => $course->id, 'shared' => 0))) {
                            // Not shared.
                            $role = $DB->get_record('role', array('shortname' => 'companycourseeditor'));
                        } else {
                            // Shared.
                            $role = $DB->get_record('role', array('shortname' => 'companycoursenoneditor'));
                        }
                        $enrol->enrol_user($instance, $user->id, $role->id, $timestart, $timeend);
                    }

                    // Get the userlicense record.
                    $userlicense = $DB->get_record('companylicense_users', array('id' => $userlicid));

                    // Update the userlicense record to mark it as in use.
                    $DB->set_field('companylicense_users', 'isusing', 1, array('id' => $userlicense->id));

                    // Fire an event to record this
                    $eventother = array('licenseid' => $licenseid);
                    $event = \block_iomad_company_admin\event\user_license_used::create(array('context' => \context_course::instance($courseid),
                                                                                              'objectid' => $userlicense->id,
                                                                                              'courseid' => $instance->courseid,
                                                                                              'userid' => $user->id,
                                                                                              'other' => $eventother));
                    $event->trigger();
                }
            }
        }

        return true;
    }

    /**
     * Triggered via user_license_unassigned event.
     *
     * @param \block_iomad_company_user\event\user_license_unassigned $event
     * @return bool true on success.
     */
    public static function user_license_unassigned(\block_iomad_company_admin\event\user_license_unassigned $event) {
        global $DB, $CFG, $PAGE;

        require_once($CFG->dirroot . '/enrol/locallib.php');

        $userid = $event->userid;
        $licenseid = $event->other['licenseid'];
        $courseid = $event->courseid;

        if (!$licenserecord = $DB->get_record('companylicense', array('id' => $licenseid))) {
            return;
        }

        if (!$course = $DB->get_record('course', array('id' => $courseid))) {
            return;
        }

        if (!$user = $DB->get_record('user', array('id' => $userid, 'deleted' => 0, 'suspended' => 0))) {
            self::update_license_usage($licenseid);
            return;
        }

        // Check if there is an enrolment in the course for this user/license.
        $manager = new course_enrolment_manager($PAGE, $course);
        if ($enrolments = $manager->get_user_enrolments($userid)) {
            foreach ($enrolments as $ue) {
                $manager->unenrol_user($ue);
            }
        }

        $license = new stdclass();
        $license->length = $licenserecord->validlength;
        $license->valid = date($CFG->iomad_date_format, $licenserecord->expirydate);

        if ($emailrecs = $DB->get_records('email', array('userid' => $user->id,
                                                         'courseid' => $course->id,
                                                         'templatename' => 'license_allocated',
                                                         'sent' => null))) {
            // Delete the email as it hasn't been sent.
            foreach ($emailrecs as $emailrec) {
                $DB->delete_records('email', array('id' => $emailrec->id));
            }
        } else {
            // Send out the email.
            EmailTemplate::send('license_removed', array('course' => $course,
                                                         'user' => $user,
                                                         'license' => $license));

        }
        // Update the license usage.
        self::update_license_usage($licenseid);

        return true;
    }

    /**
     * Triggered via company_license_created event.
     *
     * @param \block_iomad_company_user\event\company_license_created $event
     * @return bool true on success.
     */
    public static function company_license_created(\block_iomad_company_admin\event\company_license_created $event) {
        global $DB, $CFG;

        $licenseid = $event->other['licenseid'];
        $parentid = $event->other['parentid'];

        if (!$licenserecord = $DB->get_record('companylicense', array('id' => $licenseid))) {
            return;
        }

        // Deal with the human allocation.
        if (empty($licenserecord->program)) {
            $DB->set_field('companylicense', 'humanallocation', $licenserecord->allocation, array('id' => $licenseid));
        } else {
            $coursecount = $DB->count_records('companylicense_courses', array('licenseid' => $licenseid));
            $DB->set_field('companylicense', 'humanallocation', $licenserecord->allocation / $coursecount, array('id' => $licenserecord->id));
        }

        // Update the license usage.
        if (!empty($parentid)) {
            self::update_license_usage($parentid);
        }

        // Get the company managers.
        $company = new company($licenserecord->companyid);
        $managers = $company->get_managers();
        foreach ($managers as $manager) {
            // Fire the email.
            EmailTemplate::send('company_licenseassigned', array('user' => $manager, 'company' => $company));

        }

        return true;
    }

    /**
     * Triggered via company_license_updated event.
     *
     * @param \block_iomad_company_user\event\company_license_updated $event
     * @return bool true on success.
     */
    public static function company_license_updated(\block_iomad_company_admin\event\company_license_updated $event) {
        global $DB, $CFG;

        $licenseid = $event->other['licenseid'];
        $parentid = $event->other['parentid'];

        if (!$licenserecord = $DB->get_record('companylicense', array('id' => $licenseid))) {
            return;
        }

        if (!empty($licenserecord->program)) {
            // This is a program of courses.
            // If it's been updated we need to deal with any course changes.
            $currentcourses = $DB->get_records('companylicense_courses', array('licenseid' => $licenseid), null, 'courseid');
            $oldcourses = (array) json_decode($event->other['oldcourses'], true);

            // check for courses being removed.
            foreach ($oldcourses as $oldcourse) {
                $oldcourseid = $oldcourse['courseid'];

                if (empty($currentcourses[$oldcourseid])) {
                    // Deal with enrolments.
                    if ($enrolments = $DB->get_records_sql("SELECT e.id FROM {enrol} e
                                                            JOIN {companylicense_courses} clc ON (e.courseid = clc.courseid AND e.status = 0)
                                                            WHERE clc.licenseid = :licenseid
                                                            AND e.courseid = :courseid",
                                                            array('licenseid' => $licenseid, 'courseid' => $oldcourseid))) {
                        foreach ($enrolments as $enrolid) {
                            $DB->delete_records('user_enrolments', array('enrolid' => $enrolid->id));
                        }
                    }
                    $DB->delete_records('companylicense_users', array('licensecourseid' => $oldcourseid, 'licenseid' => $licenseid));
                }
            }

            foreach ($currentcourses as $currentcourse) {
                $currcourseid = $currentcourse->courseid;
                if (empty($oldcourses[$currcourseid])) {
                    // We have a new course.  Add everyone.
                    if ($licusers = $DB->get_records_sql("SELECT DISTINCT userid
                                                      FROM {companylicense_users}
                                                      WHERE licenseid = :licenseid",
                                                      array('licenseid' => $licenseid))) {

                        foreach ($licusers as $licuser) {
                            $userlic = array('licenseid' => $licenseid,
                                             'userid' => $licuser->userid,
                                             'isusing' => 0,
                                             'licensecourseid' => $currentcourse->courseid,
                                             'issuedate' => time());
                            $userlicid = $DB->insert_record('companylicense_users', $userlic);

                            // Is this an immediate license?
                            if (!empty($licenserecord->instant)) {
                                if (self::license_ok_to_use($licenseid, $currcourseid, $licuser->userid)) {
                                    if ($instance = $DB->get_record('enrol', array('courseid' => $currentcourse->courseid, 'enrol' => 'license'))) {
                                        // Enrol the user on the course.
                                        $enrol = enrol_get_plugin('license');

                                        // Enrol the user in the course.
                                        // Is the license available yet?
                                        if (!empty($licenserecord->startdate) && $licenserecord->startdate > time()) {
                                            // If not set up the enrolment from when it is.
                                            $timestart = $licenserecord->startdate;
                                        } else {
                                            // Otherwise start it now.
                                            $timestart = time();
                                        }

                                        if ($licenserecord->type == 0 || $licenserecord->type == 2) {
                                            // Set the timeend to be time start + the valid length for the license in days.
                                            $timeend = $timestart + ($licenserecord->validlength * 24 * 60 * 60 );
                                        } else {
                                            // Set the timeend to be when the license runs out.
                                            $timeend = $licenserecord->expirydate;
                                        }

                                        if ($licenserecord->type < 2) {
                                            $enrol->enrol_user($instance, $licuser->userid, $instance->roleid, $timestart, $timeend);
                                        } else {
                                            // Educator role.
                                            if ($DB->get_record('iomad_courses', array('courseid' => $currentcourse->courseid, 'shared' => 0))) {
                                                // Not shared.
                                                $role = $DB->get_record('role', array('shortname' => 'companycourseeditor'));
                                            } else {
                                                // Shared.
                                                $role = $DB->get_record('role', array('shortname' => 'companycoursenoneditor'));
                                            }
                                            $enrol->enrol_user($instance, $licuser->userid, $role->id, $timestart, $timeend);
                                        }

                                        // Get the userlicense record.
                                        $userlicense = $DB->get_record('companylicense_users', array('id' => $userlicid));

                                        // Update the userlicense record to mark it as in use.
                                        $DB->set_field('companylicense_users', 'isusing', 1, array('id' => $userlicense->id));

                                        // Fire an event to record this
                                        $eventother = array('licenseid' => $licenseid);
                                        $event = \block_iomad_company_admin\event\user_license_used::create(array('context' => \context_course::instance($currcourseid),
                                                                                                                  'objectid' => $userlicense->id,
                                                                                                                  'courseid' => $instance->courseid,
                                                                                                                  'userid' => $licuser->userid,
                                                                                                                  'other' => $eventother));
                                        $event->trigger();
                                    }
                                }
                            }
                        }
                    }
                }
            }

            if (isset($event->other['programchange']) && $licenserecord->program == 1) {
                // We have switched from an ordinary license to a program license.
                // Get the users who have courses in this licenses.
                if ($licusers = $DB->get_records_sql("SELECT DISTINCT userid
                                                      FROM {companylicense_users}
                                                      WHERE licenseid = :licenseid",
                                                      array('licenseid' => $licenseid))) {

                        foreach ($licusers as $licuser) {
                            foreach ($currentcourses as $currentcourse) {
                            // Check if they have a license allocated.
                            if (!$DB->get_record('companylicense_users', array('userid' => $licuser->userid,
                                                                               'licensecourseid' => $currentcourse->courseid,
                                                                               'licenseid' => $licenseid))) {
                                // If not, allocate it to them.
                                $userlic = array('licenseid' => $licenseid,
                                                 'userid' => $licuser->userid,
                                                 'isusing' => 0,
                                                 'licensecourseid' => $currentcourse->courseid,
                                                 'issuedate' => time());
                                $DB->insert_record('companylicense_users', $userlic);
                            }
                        }
                    }
                }
            }
        }

        // Update the license usage.
        self::update_license_usage($licenseid);

        // Deal with the parent.
        if (!empty($parentid)) {
            self::update_license_usage($parentid);
        }

        // Update the timeend for any users using this license.
        if (!empty($licenserecord->type)) {
            // This is a subscription license.
            // Update the enrolment end
            if ($enrolments = $DB->get_records_sql("SELECT ue.id FROM {enrol} e
                                                    JOIN {user_enrolments} ue ON (e.id = ue.enrolid)
                                                    JOIN {companylicense_courses} clc ON (e.courseid = clc.courseid AND e.status = 0)
                                                    JOIN {companylicense_users} clu ON (ue.userid = clu.userid AND clu.licenseid = clc.licenseid)
                                                    WHERE clc.licenseid = :licenseid",
                                                    array('licenseid' => $licenseid))) {
                foreach ($enrolments as $enrolid) {
                    $DB->set_field('user_enrolments', 'timeend', $licenserecord->expirydate,
                                  array('id' => $enrolid->id));
                }
            }
        }

        // Deal with any children.
        if ($children = $DB->get_records('companylicense', array('parentid' => $licenseid))) {
            foreach ($children as $child) {
                // If not a program of courses, check if child courses are all still present in parent courses
                if (!empty($currentcourses) && empty($licenserecord->program)) {
                    $childcourses = $DB->get_records('companylicense_courses', array('licenseid' => $child->id), '', 'courseid');
                    $childparentcourses = array_intersect_key($childcourses, $currentcourses);
                    // Clear down all of them initially.
                    $DB->delete_records('companylicense_courses', array('licenseid' => $child->id));
                    foreach ($childparentcourses as $selectedcourse) {
                        $DB->insert_record('companylicense_courses', array('licenseid' => $child->id, 'courseid' => $selectedcourse->courseid));
                    }
                }
                // If parent license is for a program of courses, overwrite child license with parent course license allocations.
                if (!empty($currentcourses) && !empty($licenserecord->program)) {
                    // Clear down all of them initially.
                    $DB->delete_records('companylicense_courses', array('licenseid' => $child->id));
                    foreach ($currentcourses as $selectedcourse) {
                        $DB->insert_record('companylicense_courses', array('licenseid' => $child->id, 'courseid' => $selectedcourse->courseid));
                    }
                }

                // Deal with the allocation amount if courses changed.
                if (!empty($child->program)) {
                    $old = count($oldcourses);
                    $new = count($currentcourses);
                    if ($old != $new) {
                        $allocation = $child->allocation / $old * $new;
                        $child->allocation = $allocation;
                    }
                }

                // Deal with the human allocation.
                if (empty($child->program)) {
                    $child->humanallocation  = $child->allocation;
                } else {
                    $child->humanallocation  = $child->allocation / $new;
                }

                // Did we change anything else about the license?
                $child->validlength = $licenserecord->validlength;
                $child->expirydate = $licenserecord->expirydate;
                $child->type = $licenserecord->type;
                $child->startdate = $licenserecord->startdate;
                $child->instant = $licenserecord->instant;
                $DB->update_record('companylicense', $child);

                // Create an event to deal with any child license allocations.
                $eventother = $event->other;
                $eventother['licenseid'] = $child->id;
                $eventother['parentid'] = $licenseid;
                $eventother['oldcourses'] = json_encode($oldcourses);

                $event = \block_iomad_company_admin\event\company_license_updated::create(array('context' => \core\context\company::instance($licenserecord->companyid),
                                                                                                'userid' => $event->userid,
                                                                                                'objectid' => $child->id,
                                                                                                'other' => $eventother));
                $event->trigger();
            }
        }

        return true;
    }

    /**
     * Triggered via company_license_deleted event.
     *
     * @param \block_iomad_company_user\event\company_license_deleted $event
     * @return bool true on success.
     */
    public static function company_license_deleted(\block_iomad_company_admin\event\company_license_deleted $event) {
        global $DB, $CFG;

        $parentid = $event->other['parentid'];

        if (empty($parentid) || !$licenserecord = $DB->get_record('companylicense', array('id' => $parentid))) {
            return;
        }
        $DB->delete_records('companylicense_courses', array('licenseid' => $event->other['licenseid']));

        // Update the license usage.
        self::update_license_usage($parentid);

        return true;
    }

    /**
     * Triggered via user_course_expired event.
     *
     * @param \block_iomad_company_user\event\company_license_created $event
     * @return bool true on success.
     */
    public static function user_course_expired(\block_iomad_company_admin\event\user_course_expired $event) {
        global $DB, $CFG;

        $userid = $event->userid;
        $courseid = $event->courseid;
        $action = 'autodelete';

        $companyid = $event->companyid;
        if ($DB->count_records_select('local_iomad_track',
                                      'userid = :userid
                                       AND courseid = :courseid
                                       AND coursecleared = 0
                                       AND timecompleted IS NULL',
                                      ['userid' => $userid,
                                       'courseid' => $courseid]) > 0) {


            // Get the specific record for this company.
            $licrecs = $DB->get_records_select('local_iomad_track',
                                               'userid = :userid
                                                AND courseid = :courseid
                                                AND companyid = :companyid
                                                AND coursecleared = 0
                                                AND timecompleted > 0',
                                               ['userid' => $userid,
                                                'companyid' => $companyid,
                                                'courseid' => $courseid]);
            foreach ($licrecs as $licrec) {

                // Remove this specific record.
                company_user::delete_user_course($userid, $courseid, $action, $licrec->id);
            }
        } else {
            // Delete them.
            company_user::delete_user_course($userid, $courseid, $action);
        }

        return true;
    }

    /**
     * Send a user's supervisor a warning email that a user hasn't completed a course.
     *
     * @param user object
     * @param course object
     * @return bool true on success.
     */
    public static function send_supervisor_warning_email($user, $course) {
        global $DB, $CFG;

        $companyinfo = self::get_company_byuserid($user->id);
        $company = new company($companyinfo->id);
        $template = new EmailTemplate('completion_warn_supervisor', array('course' => $course, 'user' => $user, 'company' => $company));

        // Is this enabled for this company?
        if (!$company->email_template_is_enabled('completion_warn_supervisor', 2)) {
            return true;
        }

        // Do we have a supervisor?
        if ($supervisoremails = self::get_usersupervisor($user->id)) {
            foreach ($supervisoremails as $supervisoremail) {
                $params = new stdclass();
                $params->fullname = $course->fullname;
                $params->firstname = $user->firstname;
                $params->lastname = $user->lastname;
                $mail = get_mailer();

                $supportuser = core_user::get_support_user();
                if (!empty($CFG->supportemail)) {
                    $supportuser->email = $CFG->supportemail;
                }
                if ($CFG->supportname) {
                    $supportuser->firstname = $CFG->supportname;
                }

                $subject = $user->email . ": " . $template->subject();
                $messagetext = $template->body();

                $mail->Sender = $CFG->noreplyaddress;
                $mail->FromName = $supportuser->firstname;
                $mail->From     = $CFG->noreplyaddress;
                if (empty($CFG->divertallemailsto)) {
                    $mail->Subject = substr($subject, 0, 900);
                } else {
                    $mail->Subject = substr('[DIVERTED ' . $supervisoremail . '] ' . $subject, 0, 900);
                    $supervisoremail = $CFG->divertallemailsto;
                }

                $mail->addAddress($supervisoremail, '');

                // Set word wrap.
                $mail->WordWrap = 79;

                $mail->Body =  "\n$messagetext\n";
                $mail->IsHTML();

                if (empty($CFG->noemailever)) {
                    $mail->send();
                }
            }
        }
        return true;
    }

    /**
     * Send a user's supervisor a warning email that a user hasn't started a course.
     *
     * @param user object
     * @param course object
     * @return bool true on success.
     */
    public static function send_supervisor_not_started_warning_email($user, $course) {
        global $DB, $CFG;

        $companyinfo = self::get_company_byuserid($user->id);
        $company = new company($companyinfo->id);
        $template = new EmailTemplate('course_not_started_warning', array('course' => $course, 'user' => $user, 'company' => $company));

        // Is this enabled for this company?
        if (!$company->email_template_is_enabled('course_not_started_warning', 2)) {
            return true;
        }

        // Do we have a supervisor?
        if ($supervisoremails = self::get_usersupervisor($user->id)) {
            foreach ($supervisoremails as $supervisoremail) {
                $params = new stdclass();
                $params->fullname = $course->fullname;
                $params->firstname = $user->firstname;
                $params->lastname = $user->lastname;
                $mail = get_mailer();

                $supportuser = core_user::get_support_user();
                if (!empty($CFG->supportemail)) {
                    $supportuser->email = $CFG->supportemail;
                }
                if ($CFG->supportname) {
                    $supportuser->firstname = $CFG->supportname;
                }

                $subject = $user->email . ": " . $template->subject();
                $messagetext = $template->body();

                $mail->Sender = $CFG->noreplyaddress;
                $mail->FromName = $supportuser->firstname;
                $mail->From     = $CFG->noreplyaddress;
                if (empty($CFG->divertallemailsto)) {
                    $mail->Subject = substr($subject, 0, 900);
                } else {
                    $mail->Subject = substr('[DIVERTED ' . $supervisoremail . '] ' . $subject, 0, 900);
                    $supervisoremail = $CFG->divertallemailsto;
                }

                $mail->addAddress($supervisoremail, '');

                // Set word wrap.
                $mail->WordWrap = 79;

                $mail->Body =  "\n$messagetext\n";
                $mail->IsHTML();
                if (empty($CFG->noemailever)) {
                    $mail->send();
                }
            }
        }
        return true;
    }

    /**
     * Send a user's supervisor a warning email that a user's training is expiring.
     *
     * @param user object
     * @param course object
     * @return bool true on success.
     */
    public static function send_supervisor_expiry_warning_email($user, $course) {
        global $DB, $CFG;

        $companyinfo = self::get_company_byuserid($user->id);
        $company = new company($companyinfo->id);
        $supervisortemplate = new EmailTemplate('completion_expiry_warn_supervisor', array('course' => $course, 'user' => $user, 'company' => $company));

        // Is this enabled for this company?
        if (!$company->email_template_is_enabled('completion_expiry_warn_supervisor', 2)) {
            return true;
        }

        // Do we have a supervisor?
        if ($supervisoremails = self::get_usersupervisor($user->id)) {
            foreach ($supervisoremails as $supervisoremail) {
                $params = new stdclass();
                $params->fullname = $course->fullname;
                $params->firstname = $user->firstname;
                $params->lastname = $user->lastname;
                $mail = get_mailer();

                $supportuser = core_user::get_support_user();
                if (!empty($CFG->supportemail)) {
                    $supportuser->email = $CFG->supportemail;
                }
                if ($CFG->supportname) {
                    $supportuser->firstname = $CFG->supportname;
                }

                $subject = $user->email . ": " . $template->subject();
                $messagetext = $template->body();

                $mail->Sender = $CFG->noreplyaddress;
                $mail->FromName = $supportuser->firstname;
                $mail->From     = $CFG->noreplyaddress;
                if (empty($CFG->divertallemailsto)) {
                    $mail->Subject = substr($subject, 0, 900);
                } else {
                    $mail->Subject = substr('[DIVERTED ' . $supervisoremail . '] ' . $subject, 0, 900);
                    $supervisoremail = $CFG->divertallemailsto;
                }

                $mail->addAddress($supervisoremail, '');

                // Set word wrap.
                $mail->WordWrap = 79;

                $mail->Body =  "\n$messagetext\n";
                $mail->IsHTML();

                if (empty($CFG->noemailever)) {
                    $mail->send();
                }

            }
        }
        return true;
    }
}
