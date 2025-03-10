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
 * Authorization Code login flow.
 *
 * @package auth_iomadoidc
 * @author James McQuillan <james.mcquillan@remote-learner.net>
 * @author Lai Wei <lai.wei@enovation.ie>
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @copyright (C) 2014 onwards Microsoft, Inc. (http://microsoft.com/)
 */

namespace auth_iomadoidc\loginflow;

use auth_iomadoidc\event\user_authed;
use auth_iomadoidc\event\user_rename_attempt;
use auth_iomadoidc\jwt;
use auth_iomadoidc\utils;
use core\output\notification;
use core_text;
use core_user;
use moodle_exception;
use moodle_url;
use pix_icon;
use iomad;
use context_system;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/auth/iomadoidc/lib.php');
require_once($CFG->dirroot . '/user/lib.php');

/**
 * Login flow for the oauth2 authorization code grant.
 */
class authcode extends base {
    /**
     * Returns a list of potential IdPs that this authentication plugin supports. Used to provide links on the login page.
     *
     * @param string $wantsurl The relative url fragment the user wants to get to.
     * @return array Array of IdPs.
     */
    public function loginpage_idp_list($wantsurl) {
        global $CFG;

        require_once($CFG->dirroot . '/local/iomad/lib/company.php');
        $companyid = iomad::get_my_companyid(context_system::instance(), false);
        if (!empty($companyid)) {
            $postfix = "_$companyid";
        } else {
            $postfix = "";
        }

        if (!auth_iomadoidc_is_setup_complete()) {
            return [];
        }

        $configname = "customicon" . $postfix;
        if (!empty($this->config->$configname)) {
            $icon = new pix_icon('0/'.$configname, get_string('pluginname', 'auth_iomadoidc'), 'auth_iomadoidc');
        } else {
            $configname = "icon" . $postfix;
            $icon = (!empty($this->config->$configname)) ? $this->config->$configname : 'auth_iomadoidc:o365';
            $icon = explode(':', $icon);
            if (isset($icon[1])) {
                [$iconcomponent, $iconkey] = $icon;
            } else {
                $iconcomponent = 'auth_iomadoidc';
                $iconkey = 'o365';
            }
            $icon = new pix_icon($iconkey, get_string('pluginname', 'auth_iomadoidc'), $iconcomponent);
        }
        $opname = "opname" . $postfix;

        return [
            [
                'url' => new moodle_url('/auth/iomadoidc/'),
                'icon' => $icon,
                'name' => strip_tags(format_text($this->config->$opname)),
            ]
        ];
    }

    /**
     * Get an IOMADOIDC parameter.
     *
     * This is a modification to PARAM_ALPHANUMEXT to add a few additional characters from Base64-variants.
     *
     * @param string $name The name of the parameter.
     * @param string $fallback The fallback value.
     * @return string The parameter value, or fallback.
     */
    protected function getiomadoidcparam($name, $fallback = '') {
        $val = optional_param($name, $fallback, PARAM_RAW);
        $val = trim($val);
        $valclean = preg_replace('/[^A-Za-z0-9\_\-\.\+\/\=]/i', '', $val);
        if ($valclean !== $val) {
            utils::debug('Authorization error.', 'authcode::cleaniomadoidcparam', $name);
            throw new moodle_exception('errorauthgeneral', 'auth_iomadoidc');
        }
        return $valclean;
    }

    /**
     * Handle requests to the redirect URL.
     *
     * @return mixed Determined by loginflow.
     */
    public function handleredirect() {
        global $CFG, $SESSION;

        // IOMAD
        require_once($CFG->dirroot . '/local/iomad/lib/company.php');
        $companyid = iomad::get_my_companyid(context_system::instance(), false);
        if (!empty($companyid)) {
            $postfix = "_$companyid";
        } else {
            $postfix = "";
        }

        if (get_config('auth_iomadoidc', 'idptype' . $postfix) == AUTH_IOMADOIDC_IDP_TYPE_MICROSOFT_IDENTITY_PLATFORM) {
            $adminconsent = optional_param('admin_consent', '', PARAM_TEXT);
            if ($adminconsent) {
                $state = $this->getiomadoidcparam('state');
                if (!empty($state)) {
                    $requestparams = [
                        'state' => $state,
                        'error_description' => optional_param('error_description', '', PARAM_TEXT),
                    ];
                    $this->handlecertadminconsentresponse($requestparams);
                }
            }
        }

        $state = $this->getiomadoidcparam('state');
        $code = $this->getiomadoidcparam('code');
        $promptlogin = (bool)optional_param('promptlogin', 0, PARAM_BOOL);
        $promptaconsent = (bool)optional_param('promptaconsent', 0, PARAM_BOOL);
        $justauth = (bool)optional_param('justauth', 0, PARAM_BOOL);
        if (!empty($state)) {
            $requestparams = [
                'state' => $state,
                'code' => $code,
                'error_description' => optional_param('error_description', '', PARAM_TEXT),
            ];
            // Response from OP.
            $this->handleauthresponse($requestparams);
        } else {
            if (isloggedin() && !isguestuser() && empty($justauth) && empty($promptaconsent)) {
                if (isset($SESSION->wantsurl) and (strpos($SESSION->wantsurl, $CFG->wwwroot) === 0)) {
                    $urltogo = $SESSION->wantsurl;
                    unset($SESSION->wantsurl);
                } else {
                    $urltogo = new moodle_url('/');
                }
                redirect($urltogo);
                die();
            }
            // Initial login request.
            $stateparams = ['forceflow' => 'authcode'];
            $extraparams = [];
            if ($promptaconsent === true) {
                $extraparams = ['prompt' => 'admin_consent'];
            }
            if ($justauth === true) {
                $stateparams['justauth'] = true;
            }
            $this->initiateauthrequest($promptlogin, $stateparams, $extraparams);
        }
    }

    /**
     * This is the primary method that is used by the authenticate_user_login() function in moodlelib.php.
     *
     * @param string $username The username (with system magic quotes)
     * @param string $password The password (with system magic quotes)
     * @return bool Authentication success or failure.
     */
    public function user_login($username, $password = null) {
        global $CFG, $DB;

        // Check user exists.
        $userfilters = ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id, 'auth' => 'iomadoidc'];
        $userexists = $DB->record_exists('user', $userfilters);

        // Check token exists.
        $tokenrec = $DB->get_record('auth_iomadoidc_token', ['username' => $username]);
        $code = optional_param('code', null, PARAM_RAW);
        $tokenvalid = (!empty($tokenrec) && !empty($code) && $tokenrec->authcode === $code) ? true : false;
        return ($userexists === true && $tokenvalid === true) ? true : false;
    }

    /**
     * Initiate an authorization request to the configured OP.
     *
     * @param bool $promptlogin Whether to prompt for login or use existing session.
     * @param array $stateparams Parameters to store as state.
     * @param array $extraparams Additional parameters to send with the IOMADOIDC request.
     */
    public function initiateauthrequest($promptlogin = false, array $stateparams = array(), array $extraparams = array()) {
        $client = $this->get_iomadoidcclient();
        $client->authrequest($promptlogin, $stateparams, $extraparams);
    }

    /**
     * Initiaite an admin consent request when using Microsoft Identity Platform.
     *
     * @param array $stateparams
     * @param array $extraparams
     * @return void
     */
    public function initiateadminconsentrequest(array $stateparams = [], array $extraparams = []) {
        $client = $this->get_iomadoidcclient();
        $client->adminconsentrequest($stateparams, $extraparams);
    }

    /**
     * @param array $authparams
     * @return void
     * @throws moodle_exception
     */
    protected function handlecertadminconsentresponse(array $authparams) {
        global $CFG, $DB, $SESSION;

        if (!empty($authparams['error_description'])) {
            utils::debug('Authorization error.', 'authcode::handleauthresponse', $authparams);
            redirect($CFG->wwwroot, get_string('errorauthgeneral', 'auth_iomadoidc'), null, notification::NOTIFY_ERROR);
        }

        if (!isset($authparams['state'])) {
            utils::debug('No state received.', 'authcode::handleauthresponse', $authparams);
            throw new moodle_exception('errorauthunknownstate', 'auth_iomadoidc');
        }

        // Validate and expire state.
        $staterec = $DB->get_record('auth_iomadoidc_state', ['state' => $authparams['state']]);
        if (empty($staterec)) {
            throw new moodle_exception('errorauthunknownstate', 'auth_iomadoidc');
        }

        $orignonce = $staterec->nonce;
        $additionaldata = [];
        if (!empty($staterec->additionaldata)) {
            $additionaldata = @unserialize($staterec->additionaldata);
            if (!is_array($additionaldata)) {
                $additionaldata = [];
            }
        }
        $SESSION->stateadditionaldata = $additionaldata;
        $DB->delete_records('auth_iomadoidc_state', ['id' => $staterec->id]);

        // Get token.
        $client = $this->get_iomadoidcclient();
        $tokenparams = $client->app_access_token_request();
        if (!isset($tokenparams['access_token'])) {
            throw new moodle_exception('errorauthnoaccesstoken', 'auth_iomadoidc');
        }

        $eventdata = [
            'other' => [
                'authparams' => $authparams,
                'tokenparams' => $tokenparams,
                'statedata' => $additionaldata,
            ]
        ];
        $event = user_authed::create($eventdata);
        $event->trigger();

        $redirect = (!empty($additionaldata['redirect'])) ? $additionaldata['redirect'] : '/auth/iomadoidc/ucp.php';
        redirect(new moodle_url($redirect));
    }

    /**
     * Handle an authorization request response received from the configured OP.
     *
     * @param array $authparams Received parameters.
     */
    protected function handleauthresponse(array $authparams) {
        global $DB, $SESSION, $USER, $CFG;

        // IOMAD
        require_once($CFG->dirroot . '/local/iomad/lib/company.php');
        $companyid = iomad::get_my_companyid(context_system::instance(), false);
        if (!empty($companyid)) {
            $postfix = "_$companyid";
        } else {
            $postfix = "";
        }

        $sid = optional_param('session_state', '', PARAM_TEXT);

        if (!empty($authparams['error_description'])) {
            utils::debug('Authorization error.', 'authcode::handleauthresponse', $authparams);
            redirect($CFG->wwwroot, get_string('errorauthgeneral', 'auth_iomadoidc'), null, notification::NOTIFY_ERROR);
        }

        if (!isset($authparams['code'])) {
            utils::debug('No auth code received.', 'authcode::handleauthresponse', $authparams);
            throw new moodle_exception('errorauthnoauthcode', 'auth_iomadoidc');
        }

        if (!isset($authparams['state'])) {
            utils::debug('No state received.', 'authcode::handleauthresponse', $authparams);
            throw new moodle_exception('errorauthunknownstate', 'auth_iomadoidc');
        }

        // Validate and expire state.
        $staterec = $DB->get_record('auth_iomadoidc_state', ['state' => $authparams['state']]);
        if (empty($staterec)) {
            throw new moodle_exception('errorauthunknownstate', 'auth_iomadoidc');
        }

        $orignonce = $staterec->nonce;
        $additionaldata = [];
        if (!empty($staterec->additionaldata)) {
            $additionaldata = @unserialize($staterec->additionaldata);
            if (!is_array($additionaldata)) {
                $additionaldata = [];
            }
        }
        $SESSION->stateadditionaldata = $additionaldata;
        $DB->delete_records('auth_iomadoidc_state', ['id' => $staterec->id]);

        // Get token from auth code.
        $client = $this->get_iomadoidcclient();
        $tokenparams = $client->tokenrequest($authparams['code']);
        if (!isset($tokenparams['id_token'])) {
            throw new moodle_exception('errorauthnoidtoken', 'auth_iomadoidc');
        }

        // Decode and verify ID token.
        [$iomadoidcuniqid, $idtoken] = $this->process_idtoken($tokenparams['id_token'], $orignonce);

        // Check restrictions.
        $passed = $this->checkrestrictions($idtoken);
        if ($passed !== true && empty($additionaldata['ignorerestrictions'])) {
            $errstr = 'User prevented from logging in due to restrictions.';
            utils::debug($errstr, 'handleauthresponse', $idtoken);
            throw new moodle_exception('errorrestricted', 'auth_iomadoidc');
        }

        // This is for setting the system API user.
        if (isset($additionaldata['justauth']) && $additionaldata['justauth'] === true) {
            $eventdata = [
                'other' => [
                    'authparams' => $authparams,
                    'tokenparams' => $tokenparams,
                    'statedata' => $additionaldata,
                ]
            ];
            $event = user_authed::create($eventdata);
            $event->trigger();
            return true;
        }

        // Check if IOMADOIDC user is already migrated.
        $tokenrec = $DB->get_record('auth_iomadoidc_token', ['iomadoidcuniqid' => $iomadoidcuniqid]);
        if (isloggedin() && !isguestuser() && (empty($tokenrec) || (isset($USER->auth) && $USER->auth !== 'iomadoidc'))) {
            // If user is already logged in and trying to link Microsoft 365 account or use it for IOMADOIDC.
            // Check if that Microsoft 365 account already exists in moodle.
            if (get_config('auth_iomadoidc', 'idptype' . $postfix) == AUTH_IOMADOIDC_IDP_TYPE_MICROSOFT_IDENTITY_PLATFORM) {
                $upn = $idtoken->claim('preferred_username');
                if (empty($upn)) {
                    $upn = $idtoken->claim('email');
                }
            } else {
                $upn = $idtoken->claim('upn');
                if (empty($upn)) {
                    $upn = $idtoken->claim('unique_name');
                }
            }
            $userrec = $DB->count_records_sql('SELECT COUNT(*)
                                                 FROM {user}
                                                WHERE username = ?
                                                      AND id != ?',
                    [$upn, $USER->id]);

            if (!empty($userrec)) {
                if (empty($additionaldata['redirect'])) {
                    $redirect = '/auth/iomadoidc/ucp.php?o365accountconnected=true';
                } else if ($additionaldata['redirect'] == '/local/o365/ucp.php') {
                    $redirect = $additionaldata['redirect'].'?action=connection&o365accountconnected=true';
                } else {
                    throw new moodle_exception('errorinvalidredirect_message', 'auth_iomadoidc');
                }
                redirect(new moodle_url($redirect));
            }

            // If the user is already logged in we can treat this as a "migration" - a user switching to IOMADOIDC.
            $connectiononly = false;
            if (isset($additionaldata['connectiononly']) && $additionaldata['connectiononly'] === true) {
                $connectiononly = true;
            }
            $this->handlemigration($iomadoidcuniqid, $authparams, $tokenparams, $idtoken, $connectiononly);
            $redirect = (!empty($additionaldata['redirect'])) ? $additionaldata['redirect'] : '/auth/iomadoidc/ucp.php';
            redirect(new moodle_url($redirect));
        } else {
            // Otherwise it's a user logging in normally with IOMADOIDC.
            $this->handlelogin($iomadoidcuniqid, $authparams, $tokenparams, $idtoken);
            if ($USER->id && $DB->record_exists('auth_iomadoidc_token', ['userid' => $USER->id])) {
                $DB->set_field('auth_iomadoidc_token', 'sid', $sid, ['userid' => $USER->id]);
            }
            redirect(core_login_get_return_url());
        }
    }

    /**
     * Handle a user migration event.
     *
     * @param string $iomadoidcuniqid A unique identifier for the user.
     * @param array $authparams Parameters received from the auth request.
     * @param array $tokenparams Parameters received from the token request.
     * @param jwt $idtoken A JWT object representing the received id_token.
     * @param bool $connectiononly Whether to just connect the user (true), or to connect and change login method (false).
     */
    protected function handlemigration($iomadoidcuniqid, $authparams, $tokenparams, $idtoken, $connectiononly = false) {
        global $USER, $DB, $CFG;

        // Check if IOMADOIDC user is already connected to a Moodle user.
        $tokenrec = $DB->get_record('auth_iomadoidc_token', ['iomadoidcuniqid' => $iomadoidcuniqid]);
        if (!empty($tokenrec)) {
            $existinguserparams = ['username' => $tokenrec->username, 'mnethostid' => $CFG->mnet_localhost_id];
            $existinguser = $DB->get_record('user', $existinguserparams);
            if (empty($existinguser)) {
                $DB->delete_records('auth_iomadoidc_token', ['id' => $tokenrec->id]);
            } else {
                if ($USER->username === $tokenrec->username) {
                    // Already connected to current user.
                    if ($connectiononly !== true && $USER->auth !== 'iomadoidc') {
                        // Update auth plugin.
                        $DB->update_record('user', (object)['id' => $USER->id, 'auth' => 'iomadoidc']);
                        $USER = $DB->get_record('user', ['id' => $USER->id]);
                        $USER->auth = 'iomadoidc';
                    }
                    $this->updatetoken($tokenrec->id, $authparams, $tokenparams);
                    return true;
                } else {
                    // IOMADOIDC user connected to user that is not us. Can't continue.
                    throw new moodle_exception('errorauthuserconnectedtodifferent', 'auth_iomadoidc');
                }
            }
        }

        // Check if Moodle user is already connected to an IOMADOIDC user.
        $tokenrec = $DB->get_record('auth_iomadoidc_token', ['userid' => $USER->id]);
        if (!empty($tokenrec)) {
            if ($tokenrec->iomadoidcuniqid === $iomadoidcuniqid) {
                // Already connected to current user.
                if ($connectiononly !== true && $USER->auth !== 'iomadoidc') {
                    // Update auth plugin.
                    $DB->update_record('user', (object)['id' => $USER->id, 'auth' => 'iomadoidc']);
                    $USER = $DB->get_record('user', ['id' => $USER->id]);
                    $USER->auth = 'iomadoidc';
                }
                $this->updatetoken($tokenrec->id, $authparams, $tokenparams);
                return true;
            } else {
                throw new moodle_exception('errorauthuseralreadyconnected', 'auth_iomadoidc');
            }
        }

        // Create token data.
        $tokenrec = $this->createtoken($iomadoidcuniqid, $USER->username, $authparams, $tokenparams, $idtoken, $USER->id);

        $eventdata = [
            'objectid' => $USER->id,
            'userid' => $USER->id,
            'other' => [
                'username' => $USER->username,
                'userid' => $USER->id,
                'iomadoidcuniqid' => $iomadoidcuniqid,
            ],
        ];
        $event = \auth_iomadoidc\event\user_connected::create($eventdata);
        $event->trigger();

        // Switch auth method, if requested.
        if ($connectiononly !== true) {
            if ($USER->auth !== 'iomadoidc') {
                $DB->delete_records('auth_iomadoidc_prevlogin', ['userid' => $USER->id]);
                $userrec = $DB->get_record('user', ['id' => $USER->id]);
                if (!empty($userrec)) {
                    $prevloginrec = [
                        'userid' => $userrec->id,
                        'method' => $userrec->auth,
                        'password' => $userrec->password,
                    ];
                    $DB->insert_record('auth_iomadoidc_prevlogin', $prevloginrec);
                }
            }
            $DB->update_record('user', (object)['id' => $USER->id, 'auth' => 'iomadoidc']);
            $USER = $DB->get_record('user', ['id' => $USER->id]);
            $USER->auth = 'iomadoidc';
        }

        return true;
    }

    /**
     * Determines whether the given Azure AD UPN is already matched to a Moodle user (and has not been completed).
     *
     * @param $aadupn
     * @return false|stdClass Either the matched Moodle user record, or false if not matched.
     */
    protected function check_for_matched($aadupn) {
        global $DB;

        if (auth_iomadoidc_is_local_365_installed()) {
            $match = $DB->get_record('local_o365_connections', ['aadupn' => $aadupn]);
            if (!empty($match) && \local_o365\utils::is_o365_connected($match->muserid) !== true) {
                return $DB->get_record('user', ['id' => $match->muserid]);
            }
        }

        return false;
    }

    /**
     * Check for an existing user object.
     * @param string $iomadoidcuniqid The user object ID to look up.
     * @param string $username The original username.
     * @return string If there is an existing user object, return the username associated with it.
     *                If there is no existing user object, return the original username.
     */
    protected function check_objects($iomadoidcuniqid, $username) {
        global $DB;

        $user = null;
        if (auth_iomadoidc_is_local_365_installed()) {
            $sql = 'SELECT u.username
                      FROM {local_o365_objects} obj
                      JOIN {user} u ON u.id = obj.moodleid
                     WHERE obj.objectid = ? and obj.type = ?';
            $params = [$iomadoidcuniqid, 'user'];
            $user = $DB->get_record_sql($sql, $params);
        }

        return (!empty($user)) ? $user->username : $username;
    }

    /**
     * Handle a login event.
     *
     * @param string $iomadoidcuniqid A unique identifier for the user.
     * @param array $authparams Parameters received from the auth request.
     * @param array $tokenparams Parameters received from the token request.
     * @param jwt $idtoken A JWT object representing the received id_token.
     */
    protected function handlelogin(string $iomadoidcuniqid, array $authparams, array $tokenparams, jwt $idtoken) {
        global $DB, $CFG;

        // IOMAD
        require_once($CFG->dirroot . '/local/iomad/lib/company.php');
        $companyid = iomad::get_my_companyid(context_system::instance(), false);
        if (!empty($companyid)) {
            $postfix = "_$companyid";
        } else {
            $postfix = "";
        }

        $tokenrec = $DB->get_record('auth_iomadoidc_token', ['iomadoidcuniqid' => $iomadoidcuniqid]);

        // Do not continue if auth plugin is not enabled.
        if (!is_enabled_auth('iomadoidc')) {
            throw new moodle_exception('erroriomadoidcnotenabled', 'auth_iomadoidc', null, null, '1');
        }

        // Find the latest real Microsoft username.
        // Determine remote username depending on IdP type, or fall back to standard 'sub'.
        if (get_config('auth_iomadoidc', 'idptype' . $postfix) == AUTH_IOMADOIDC_IDP_TYPE_MICROSOFT_IDENTITY_PLATFORM) {
            $iomadoidcusername = $idtoken->claim('preferred_username');
            if (empty($iomadoidcusername)) {
                $iomadoidcusername = $idtoken->claim('email');
            }
        } else {
            $iomadoidcusername = $idtoken->claim('upn');
            if (empty($iomadoidcusername)) {
                $iomadoidcusername = $idtoken->claim('unique_name');
            }
        }
        if (empty($iomadoidcusername)) {
            $iomadoidcusername = $idtoken->claim('sub');
        }

        $usernamechanged = false;
        if ($iomadoidcusername && $tokenrec && strtolower($iomadoidcusername) !== strtolower($tokenrec->iomadoidcusername)) {
            $usernamechanged = true;
        }

        $existingmatching = null;
        if (auth_iomadoidc_is_local_365_installed()) {
            if ($existingmatching = $DB->get_record('local_o365_objects', ['type' => 'user', 'objectid' => $iomadoidcuniqid])) {
                $existinguser = core_user::get_user($existingmatching->moodleid);
                if ($existinguser && strtolower($existingmatching->o365name) != strtolower($iomadoidcusername)) {
                    $usernamechanged = true;
                }
            }
        }

        $supportupnchangeconfig = get_config('local_o365', 'support_upn_change' . $postfix);

        if (!empty($tokenrec)) {
            // Already connected user.
            if (empty($tokenrec->userid)) {
                // Existing token record, but missing the user ID.
                $user = null;
                if ($usernamechanged) {
                    $user = $DB->get_record('user', ['username' => $iomadoidcusername]);
                }
                if (empty($user)) {
                    $user = $DB->get_record('user', ['username' => $tokenrec->username]);
                }

                if (empty($user)) {
                    // Token exists, but it doesn't have a valid username.
                    // In this case, delete the token, and try to process login again.
                    $DB->delete_records('auth_iomadoidc_token', ['id' => $tokenrec->id]);
                    return $this->handlelogin($iomadoidcuniqid, $authparams, $tokenparams, $idtoken);
                }
                $tokenrec->userid = $user->id;
                if ($usernamechanged) {
                    $tokenrec->iomadoidcusername = $iomadoidcusername;
                }
                $DB->update_record('auth_iomadoidc_token', $tokenrec);
            } else {
                // Existing token with a user ID.
                $user = $DB->get_record('user', ['id' => $tokenrec->userid]);
                if (empty($user)) {
                    $failurereason = AUTH_LOGIN_NOUSER;
                    $eventdata = ['other' => ['username' => $tokenrec->username, 'reason' => $failurereason]];
                    $event = \core\event\user_login_failed::create($eventdata);
                    $event->trigger();
                    // Token is invalid, delete it.
                    $DB->delete_records('auth_iomadoidc_token', ['id' => $tokenrec->id]);
                    return $this->handlelogin($iomadoidcuniqid, $authparams, $tokenparams, $idtoken);
                }

                // Handle username change - update token, update connection.
                if ($usernamechanged) {
                    if ($supportupnchangeconfig != 1) {
                        // Username change is not supported, throw exception.
                        throw new moodle_exception('errorupnchangeisnotsupported', 'local_o365', null, null, '2');
                    }
                    $potentialduplicateuser = core_user::get_user_by_username($iomadoidcusername);
                    if ($potentialduplicateuser) {
                        // Username already exists, cannot change Moodle account username, throw exception.
                        throw new moodle_exception('erroruserwithusernamealreadyexists', 'auth_iomadoidc', null, null, '2');
                    } else {
                        // Username does not exist:
                        //  1. can change Moodle account username (if the user uses auth_iomadoidc),
                        //  2. can change token record.
                        if ($user->auth == 'iomadoidc') {
                            $user->username = $iomadoidcusername;
                            user_update_user($user, false);

                            $fullmessage = 'Attempt to change username of user ' . $user->id . ' from ' .
                                $tokenrec->iomadoidcusername . ' to ' . $iomadoidcusername;
                            $event = user_rename_attempt::create(['objectid' => $user->id, 'other' => $fullmessage,
                                'userid' => $user->id]);
                            $event->trigger();

                            $tokenrec->username = $iomadoidcusername;
                        }

                        $tokenrec->iomadoidcusername = $iomadoidcusername;
                        $DB->update_record('auth_iomadoidc_token', $tokenrec);
                    }

                    // Update local_o365_objects table.
                    if (auth_iomadoidc_is_local_365_installed()) {
                        if ($o365objectrecord = $DB->get_record('local_o365_objects',
                            ['moodleid' => $user->id, 'type' => 'user'])) {
                            $o365objectrecord->o365name = $iomadoidcusername;
                            $DB->update_record('local_o365_objects', $o365objectrecord);
                        }
                    }
                }
            }
            $username = $user->username;
            $this->updatetoken($tokenrec->id, $authparams, $tokenparams);
            $user = authenticate_user_login($username, '', true);

            if (!empty($user)) {
                complete_user_login($user);
            } else {
                // There was a problem in authenticate_user_login.
                throw new moodle_exception('errorauthgeneral', 'auth_iomadoidc', null, null, '2');
            }
        } else if ($usernamechanged) {
            // User has connection record, but no token; and the user has been renamed in Microsoft.
            // In this case, we need to:
            //  1. attempt to update Moodle username,
            //  2. create token record,
            //  3. update connection record in local_o365_objects table.

            if ($supportupnchangeconfig != 1) {
                throw new moodle_exception('errorupnchangeisnotsupported', 'local_o365', null, null, '2');
            }

            $existinguser = core_user::get_user($existingmatching->moodleid);

            if (get_config('auth_iomadoidc', 'idptype' . $postfix) == AUTH_IOMADOIDC_IDP_TYPE_MICROSOFT_IDENTITY_PLATFORM) {
                $username = $idtoken->claim('preferred_username');
                if (empty($username)) {
                    $username = $idtoken->claim('email');
                }
            } else {
                $username = $idtoken->claim('upn');
                if (empty($username)) {
                    $username = $idtoken->claim('unique_name');
                }
            }
            $originalupn = null;

            if (empty($username)) {
                $username = $iomadoidcuniqid;

                // If upn claim is missing, it can mean either the IdP is not Azure AD, or it's a guest user.
                if (auth_iomadoidc_is_local_365_installed()) {
                    $apiclient = \local_o365\utils::get_api();
                    $userdetails = $apiclient->get_user($iomadoidcuniqid, true);
                    if (!is_null($userdetails) && isset($userdetails['userPrincipalName']) &&
                        stripos($userdetails['userPrincipalName'], '#EXT#') !== false && $idtoken->claim('unique_name')) {
                        $originalupn = $userdetails['userPrincipalName'];
                        $username = $idtoken->claim('unique_name');
                    }
                }
            }
            $username = trim(core_text::strtolower($username));

            // Update username.
            $userwithduplicateusername = core_user::get_user_by_username($username);
            if ($userwithduplicateusername) {
                // Cannot rename user, username already exists.
                throw new moodle_exception('erroruserwithusernamealreadyexists', 'auth_iomadoidc', null, null, '2');
            } else {
                $originalusername = $existinguser->username;
                $existinguser->username = $username;
                user_update_user($existinguser, false);

                $fullmessage =
                    'Attempt to change username of user ' . $existinguser->id . ' from ' . $originalusername . ' to ' .
                    $username;
                $event = user_rename_attempt::create(['objectid' => $existinguser->id, 'other' => $fullmessage,
                    'userid' => $existinguser->id]);
                $event->trigger();
            }

            // Create token.
            $this->createtoken($iomadoidcuniqid, $username, $authparams, $tokenparams, $idtoken, 0, $originalupn);

            // Update connection record in local_o365_objects table.
            $existingmatching->o365name = $iomadoidcusername;
            $DB->update_record('local_o365_objects', $existingmatching);

            $user = authenticate_user_login($username, '', true);

            if (!empty($user)) {
                complete_user_login($user);
            } else {
                // There was a problem in authenticate_user_login.
                throw new moodle_exception('errorauthgeneral', 'auth_iomadoidc', null, null, '2');
            }
        } else {
            /* No existing token, user not connected. Possibilities:
                - Matched user.
                - New user (maybe create).
            */

            // Generate a Moodle username.
            // Use 'upn' if available for username (Azure-specific), or fall back to lower-case iomadoidcuniqid.
            if (get_config('auth_iomadoidc', 'idptype' . $postfix) == AUTH_IOMADOIDC_IDP_TYPE_MICROSOFT_IDENTITY_PLATFORM) {
                $username = $idtoken->claim('preferred_username');
                if (empty($username)) {
                    $username = $idtoken->claim('email');
                }
            } else {
                $username = $idtoken->claim('upn');
                if (empty($username)) {
                    $username = $idtoken->claim('unique_name');
                }
            }
            $originalupn = null;

            if (empty($username)) {
                $username = $iomadoidcuniqid;

                // If upn claim is missing, it can mean either the IdP is not Azure AD, or it's a guest user.
                if (auth_iomadoidc_is_local_365_installed()) {
                    $apiclient = \local_o365\utils::get_api();
                    $userdetails = $apiclient->get_user($iomadoidcuniqid, true);
                    if (!is_null($userdetails) && isset($userdetails['userPrincipalName']) &&
                        stripos($userdetails['userPrincipalName'], '#EXT#') !== false && $idtoken->claim('unique_name')) {
                        $originalupn = $userdetails['userPrincipalName'];
                        $username = $idtoken->claim('unique_name');
                    }
                }
            }

            // See if we have an object listing.
            $username = $this->check_objects($iomadoidcuniqid, $username);
            $matchedwith = $this->check_for_matched($username);
            if (!empty($matchedwith)) {
                if ($matchedwith->auth != 'iomadoidc') {
                    $matchedwith->aadupn = $username;
                    throw new moodle_exception('errorusermatched', 'auth_iomadoidc', null, $matchedwith);
                }
            }
            $username = trim(core_text::strtolower($username));
            $tokenrec = $this->createtoken($iomadoidcuniqid, $username, $authparams, $tokenparams, $idtoken, 0, $originalupn);

            $existinguserparams = ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id];
            if ($DB->record_exists('user', $existinguserparams) !== true) {
                // User does not exist. Create user if site allows, otherwise fail.
                if (empty($CFG->authpreventaccountcreation)) {
                    if (!$CFG->allowaccountssameemail) {
                        $userinfo = $this->get_userinfo($username);
                        if ($DB->count_records('user', array('email' => $userinfo['email'], 'deleted' => 0)) > 0) {
                            throw new moodle_exception('errorauthloginfaileddupemail', 'auth_iomadoidc', null, null, '1');
                        }
                    }
                    $user = create_user_record($username, '', 'iomadoidc');
                } else {
                    // Trigger login failed event.
                    $failurereason = AUTH_LOGIN_NOUSER;
                    $eventdata = ['other' => ['username' => $username, 'reason' => $failurereason]];
                    $event = \core\event\user_login_failed::create($eventdata);
                    $event->trigger();
                    throw new moodle_exception('errorauthloginfailednouser', 'auth_iomadoidc', null, null, '1');
                }
            }

            $user = authenticate_user_login($username, '', true);

            if (!empty($user)) {
                $tokenrec = $DB->get_record('auth_iomadoidc_token', ['id' => $tokenrec->id]);
                // This should be already done in auth_plugin_iomadoidc::user_authenticated_hook, but just in case...
                if (!empty($tokenrec) && empty($tokenrec->userid)) {
                    $updatedtokenrec = new \stdClass;
                    $updatedtokenrec->id = $tokenrec->id;
                    $updatedtokenrec->userid = $user->id;
                    $DB->update_record('auth_iomadoidc_token', $updatedtokenrec);
                }
                complete_user_login($user);
            } else {
                // There was a problem in authenticate_user_login. Clean up incomplete token record.
                if (!empty($tokenrec)) {
                    $DB->delete_records('auth_iomadoidc_token', ['id' => $tokenrec->id]);
                }

                redirect($CFG->wwwroot, get_string('errorauthgeneral', 'auth_iomadoidc'), null, notification::NOTIFY_ERROR);
            }

        }
        return true;
    }
}
