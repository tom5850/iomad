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
 * My Moodle -- a user's personal dashboard
 *
 * - each user can currently have their own page (cloned from system and then customised)
 * - only the user can see their own dashboard
 * - users can add any blocks they want
 * - the administrators can define a default site dashboard for users who have
 *   not created their own dashboard
 *
 * This script implements the user's view of the dashboard, and allows editing
 * of the dashboard.
 *
 * @package    moodlecore
 * @subpackage my
 * @copyright  2010 Remote-Learner.net
 * @author     Hubert Chathi <hubert@remote-learner.net>
 * @author     Olav Jordan <olav.jordan@remote-learner.net>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

global $USER, $DB, $PAGE, $CFG, $SESSION;
require_once(__DIR__ . '/../config.php');
require_once($CFG->dirroot . '/my/lib.php');
require_once($CFG->dirroot . '/local/iomad/lib/iomad.php');
require_once($CFG->libdir . '/accesslib.php'); // Access library for capabilities functions

redirect_if_major_upgrade_required();

// TODO Add sesskey check to edit
$edit   = optional_param('edit', null, PARAM_BOOL);    // Turn editing on and off
$reset  = optional_param('reset', null, PARAM_BOOL);

require_login();

$hassiteconfig = has_capability('moodle/site:config', context_system::instance());
if ($hassiteconfig && moodle_needs_upgrading()) {
    redirect(new moodle_url('/admin/index.php'));
}

$strmymoodle = get_string('myhome');

if (empty($CFG->enabledashboard)) {
    // Dashboard is disabled, so the /my page shouldn't be displayed.
    $defaultpage = get_default_home_page();
    if ($defaultpage == HOMEPAGE_MYCOURSES) {
        // If default page is set to "My courses", redirect to it.
        redirect(new moodle_url('/my/courses.php'));
    } else {
        // Otherwise, raise an exception to inform the dashboard is disabled.
        throw new moodle_exception('error:dashboardisdisabled', 'my');
    }
}

if (isguestuser()) {  // Force them to see system default, no editing allowed
    // If guests are not allowed my moodle, send them to front page.
    if (empty($CFG->allowguestmymoodle)) {
        redirect(new moodle_url('/login/index.php')); //return to login
    }

    $userid = null;
    $USER->editing = $edit = 0;  // Just in case
    $context = context_system::instance();
    $PAGE->set_blocks_editing_capability('moodle/my:configsyspages');  // unlikely :)
    $strguest = get_string('guest');
    $pagetitle = "$strmymoodle ($strguest)";

}

// Get the user's company ID (tenant ID)
$company = iomad::get_my_companyid(context_system::instance(), false);

$systemcontext = context_system::instance();
$companycontext = $systemcontext;

if (!empty($company)) {
    $companycontext =  \core\context\company::instance($company);
	$companycode = $DB->get_field('company', 'code', ['id' => $company]); //get company code, needed to decide which enduser dashboard should be shown
}

// CROSS-TENANT LOGIN DETECTION
// When a user logs in via a subdomain (e.g. CompanyB.domain.com), get_my_companyid() automatically
// sets $SESSION->currenteditingcompany to that subdomain's company. However, if the user doesn't
// actually belong to that company (no company_users record), we need to find their real primary
// company and update the session so subsequent requests route them to the correct dashboard.
if (!empty($company)) {
    if (!$DB->record_exists('company_users', ['userid' => $USER->id, 'companyid' => $company])) {
        // User has no company_users record for the detected company.
        // Look up their actual primary company (most recently used).
        $usercompanies = $DB->get_records_sql(
            "SELECT DISTINCT companyid, lastused FROM {company_users}
              WHERE userid = :userid
              ORDER BY lastused DESC, companyid DESC",
            ['userid' => $USER->id]
        );
        if (!empty($usercompanies)) {
            // Switch the session to the user's real company and reload.
            $actualcompany = reset($usercompanies);
            if ($actualcompany->companyid != $company) {
                $SESSION->currenteditingcompany = $actualcompany->companyid;
                redirect(new moodle_url('/my/'));
            }
        }
        // If the user has no company_users record at all, fall through to default routing.
    }
}

/*
//If TreeSolution Company then ...
if ($company == 3) {
// Define user ID (e.g. currently logged in user)
$userid = $USER->id; // Oder eine spezifische ID: $userid = 123;


// Load all capabilities
$capabilities = $DB->get_records('capabilities');

// Initialise log file
error_log(print_r("Capability Check for User ID: $userid\n", true));

foreach ($capabilities as $capability) {
    // Check whether the user has the capability
    $hascapability = has_capability($capability->name, $companycontext, $userid);

    // Format result
    $status = $hascapability ? 'YES' : 'NO';
    $logentry = "{$capability->name}: {$status}";

    // Write to the log file
    error_log(print_r($logentry, true));
}
}
*/  
//Default dashboards
$dashboard_EndUser = '/local/dash/addon/dashboard/dashboard.php?id=2'; //Dash Dashboard
$dashboard_CompanyManager = '/local/dash/addon/dashboard/dashboard.php?id=3'; //Dash Dashboard
$dashboard_Journey = '/local/dash/addon/dashboard/dashboard.php?id=7'; //Dash Dashboard
$dashboard_Default = '/my/default.php'; //Default Moodle dashboard (renamed original index.php)

//Tenant specific dashboards
//Check if JOURNEY or normal Academy:
$parts = explode(':', $companycode);
if (isset($parts[1]) && stripos($parts[1], 'JOURNEY') === 0) {
    // It says "JOURNEY" after the first colon.
	$dashboard_EndUser=$dashboard_Journey; //set Journey dashboard as enduser dashboard
}
//TreeSolution Demo tenant:
if ($company == 19) {
$dashboard_CompanyManager = '/local/dash/addon/dashboard/dashboard.php?id=4'; } //Dashboard with enhanced tag filter for sales
//Gemeindeverband ICT (GICT):
if ($company == 44) {
$dashboard_EndUser = '/local/dash/addon/dashboard/dashboard.php?id=5'; } //Dashboard with library of past e-learnings							 

//error_log("Forwarding to dashboard ...");
// Ensure we have a valid company.
if ($company) {
	// CLIENT ADMINISTRATOR (usually = SysAdmin)
    // Check if the user has the right to add companies.
    if (has_capability('block/iomad_company_admin:company_add', $companycontext, $USER->id)) {
        //error_log("The user is a Client Administrator in the company context.");		
		redirect(new moodle_url($dashboard_CompanyManager));
    }
	// PARTNER MANAGER
    // Check if the user has right to add child companies
    else if (has_capability('block/iomad_company_admin:company_add_child', $companycontext, $USER->id)) {
        //error_log("The user is a Partner Manager in the company context.");		
		redirect(new moodle_url($dashboard_CompanyManager));
    }
	// ALL OTHER ROLES: load from company_users table
	else {
		$companyuser = $DB->get_record('company_users', ['userid' => $USER->id, 'companyid' => $company]);

		// COMPANY MANAGER
		if (!empty($companyuser) && $companyuser->managertype == 1) {
			//error_log("The user is a Company Manager in the company context.");
			redirect(new moodle_url($dashboard_CompanyManager));
		}
		// COMPANY REPORTER ONLY
		else if (!empty($companyuser) && $companyuser->managertype == 4) {
			//error_log("The user is a Company Reporter Only in the company context.");
			redirect(new moodle_url($dashboard_EndUser));
		}
		// DEPARTMENT MANAGER + EDUCATOR
		else if (!empty($companyuser) && $companyuser->managertype == 2 && $companyuser->educator == 1) {
			//error_log("The user is a Department Manager and Educator in the company context.");
			redirect(new moodle_url($dashboard_CompanyManager));
		}
		// DEPARTMENT MANAGER only
		else if (!empty($companyuser) && $companyuser->managertype == 2) {
			//error_log("The user is a Department Manager (no educator) in the company context.");
			redirect(new moodle_url($dashboard_EndUser));
		}
		// STUDENT
		else if (!empty($companyuser) && $companyuser->managertype == 0) {
			//error_log("The user is a Student in the company context.");
			redirect(new moodle_url($dashboard_EndUser));
		}
		// FALLBACK
		else {
			//error_log("The user role could not be determined in the company context.");
			redirect(new moodle_url($dashboard_Default));
		}
	}
} else {
    //error_log("User does not belong to any company (tenant).");
		redirect(new moodle_url($dashboard_Default));
}
