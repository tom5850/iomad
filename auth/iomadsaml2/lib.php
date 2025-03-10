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
 * Main file
 *
 * @package auth_iomadsaml2
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright Catalyst IT
 */

/**
 * Check if we have the saml=on param set. If so, disable guest access and force the user to log in with saml.
 *
 * This is an implementation of a legacy callback that will only be called in older Moodle versions.
 * It will not be called in Moodle 4.5+ that contain the hook core\hook\after_config,
 * instead, the callback auth_iomadsaml2\local\hooks\after_config::callback will be executed.
 *
 * @since  Moodle 3.8
 * @return void
 */
function auth_iomadsaml2_after_config() {
    global $CFG;
    try {
        $iomadsaml = optional_param('iomadsaml', null, PARAM_BOOL);
        if ($iomadsaml == 1) {
            if (isguestuser()) {
                // We want to force users to log in with a real account, so log guest users out.
                require_logout();
            }
            // We have the saml=on param set. Disable guest access (in memory -
            // not saved in database) to force the login with saml for this request.
            unset($CFG->autologinguests);
        }
    } catch (\Exception $exception) {
        debugging('auth_iomadsaml2_after_config error', DEBUG_DEVELOPER, $exception->getTrace());
    }
}

/**
 * Callback immediately after require_login succeeds.
 *
 * This is an implementation of a legacy callback that will only be called in older Moodle versions.
 * It will not be called in Moodle 4.4+ that contain the hook core\hook\output\before_http_headers,
 * instead, the callback auth_iomadsaml2\local\hooks\output\before_http_headers::callback will be executed.
 */
function auth_iomadsaml2_after_require_login() {
    \auth_iomadsaml2\auto_login::process();
}

/**
 * Callback before HTTP headers are sent.
 *
 * This is called on every page.
 */
function auth_iomadsaml2_before_http_headers() {
    \auth_iomadsaml2\auto_login::process();
}

/**
 * Add service status checks
 *
 * @return array of check objects
 */
function auth_iomadsaml2_status_checks() : array {
    global $iomadsam2auth;
    require_once(__DIR__ . '/setup.php');

    // Only if saml is configured then check certificate expiry.
    if ($iomadsam2auth->is_configured()) {
        return [
            new \auth_iomadsaml2\check\certificateexpiry(),
        ];
    }
    return [];
}
