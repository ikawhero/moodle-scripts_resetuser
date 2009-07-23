<?php

/**
 * This script is designed to do any of the following functions:
 * 1: Create a new account for the given username if it doesn't already exist
 * 2: Make an account an admin
 * 3: Make account from step 2 the primary admin
 * 4: Reset the password of an account
 */

/// Do not edit the following 2 lines
unset($NEWUSER);
unset($INFO);

/// Step 1 Information.
/// Edit as necessary. Leave the username as xxxx to skip this step
$NEWUSER->username = 'xxxx';
$NEWUSER->password = 'xxxx';
$NEWUSER->firstname = 'xxxx';
$NEWUSER->lastname = 'xxxx';
$NEWUSER->email = 'xxxx';
$NEWUSER->city = 'xxxx';
$NEWUSER->country = 'AU';
$NEWUSER->auth = 'manual';

/// Step 2 Information. Leave the username as xxxx to skip this step
$INFO->username = 'xxxx';

/// Step 3 Information. Leave as false to skip this step
$INFO->makeprimaryadmin = false;

/// Step 4 Informaiton. Leave the username as xxxx to skip this step
$INFO->resetusername = 'xxxx';
$INFO->resetpassword = 'xxxx';



/**************************************************/
/****** DO NOT EDIT ANYTHING BELOW THIS LINE ******/

require_once('./config.php');

echo 'Starting script<br />';

/// Step 1
echo 'Step 1:<br />';

if ($NEWUSER->username !== 'xxxx') {

    /// Set some other basic information to create an account
    $NEWUSER->confirmed = 1;
    $NEWUSER->lang = (function_exists('current_language')) ? current_language() : 'en';
    $NEWUSER->firstaccess = time();
    $NEWUSER->mnethostid = (empty($CFG->mnet_localhost_id)) ? 0 : $CFG->mnet_localhost_id;
    if (function_exists('random_string')) {
        $NEWUSER->secret = random_string(15);
    }

    /// Hash the password
    $NEWUSER->password = (function_exists('hash_internal_user_password')) ? hash_internal_user_password($NEWUSER->password) : md5($NEWUSER->password);

    /// Insert new user record
    if (! record_exists('user', 'username', $NEWUSER->username)) {
        if (insert_record('user', $NEWUSER)) {
            echo 'New user \''.$NEWUSER->username.'\' successfully added<br />';
        } else {
            error('Failed to add new user \''.$NEWUSER->username);
            exit;
        }
    }

}


/// Step 2
echo 'Step 2:<br />';

if ($INFO->username !== 'xxxx') {

    if ($INFO->userid = get_field('user', 'id', 'username', $INFO->username)) {

        if ($CFG->version > 2006101000) { /// We are dealing with roles!
        
            $systemcontext = get_context_instance(CONTEXT_SYSTEM, SITEID);
            $role = get_record('role','shortname','admin');
            role_assign($role->id, $INFO->userid, 0, $systemcontext->id);

        } else {

            add_admin($INFO->userid);

        }

         echo 'Added \''.$INFO->username.'\' as an admin<br />';

    } else {
    
        error('Could not make \''.$INFO->username.'\' an admin. User does not exist');
        exit;

    }

}


/// Step 3
echo 'Step 3:<br />';

if (($INFO->username !== 'xxxx') and $INFO->makeprimaryadmin) {
    
    /// Check they are already the primary admin
    $primaryadmin = get_admin();
    if ($primaryadmin->username == $INFO->username) {
        
        error('\''.$INFO->username.'\' is already the primary admin');
        exit;

    }

    if ($CFG->version > 2006101000) { /// We are dealing with roles!

        /// Let's get the full records from the role_assignments table for our user
        /// and the existing primary admin
        if (!($primaryrecord = get_record('role_assignments', 'roleid', $role->id, 'contextid', $systemcontext->id, 'userid', $primaryadmin->id))) {
            error('Something went wrong trying to get the primary admin');
            exit;
        }
        if (!($adminrecord = get_record('role_assignments', 'roleid', $role->id, 'contextid', $systemcontext->id, 'userid', $INFO->userid))) {
            error('Something went wrong getting our admin record');
            exit;
        }

        /// We need to swap the records. This requires some trickery to avoid the
        /// database uniqueness checks (roleid, contextid, userid)

        /// Let's delete our admin record
        if (!(delete_records('role_assignments', 'id', $adminrecord->id))) {
            error('There was an error deleting our old admin record');
            exit;
        }

        /// Now let's use our admin record but with the primary admin id to update
        $adminrecord->id = $primaryrecord->id;
        if (!(update_record('role_assignments', $adminrecord))) {
            error('There was an error changing the primary admin details');
            exit;
        }

        /// Now we need to add the old primary admin back in as a normal admin
        unset($primaryrecord->id);
        if (!(insert_record('role_assignments', $primaryrecord))) {
            error('There was an error adding the old primary admin record');
            exit;
        }

    } else { /// We are dealing with old system

        /// Let's get the full records from user_admins table for our user
        /// and the existing primary admin
        if (!($primaryrecord = get_record('user_admins', 'userid', $primaryadmin->id))) {
            error('Something went wrong trying to get the primary admin');
            exit;
        }
        if (!($adminrecord = get_record('user_admins', 'userid', $INFO->userid))) {
            error('Something went wrong getting our admin record');
            exit;
        }

        /// We need to swap the records.
        
        /// Let's delete our admin record
        if (!(delete_records('user_admins', 'id', $adminrecord->id))) {
            error('There was an error deleting our old admin record');
            exit;
        }

        /// Now let's use our admin record but with the primary admin id to update
        $adminrecord->id = $primaryrecord->id;
        if (!(update_record('user_admins', $adminrecord))) {
            error('There was an error changing the primary admin details');
            exit;
        }

        /// Now we need to add the old primary admin back in as a normal admin
        unset($primaryrecord->id);
        if (!(insert_record('user_admins', $primaryrecord))) {
            error('There was an error adding the old primary admin record');
            exit;
        }

    }

    echo '\''.$INFO->username.'\' is now the primary admin';

}



/// Step 4
echo 'Step 4:<br />';

if ($INFO->resetusername !== 'xxxx') {

    if ($user = get_record('user', 'username', $INFO->resetusername, '', '', '', '', 'id, password')) {
        $user->password = (function_exists('hash_internal_user_password')) ? hash_internal_user_password($INFO->resetpassword) : md5($INFO->resetpassword);

        if (!update_record('user', $user)) {
            error('Could not reset password for: '.$INFO->resetusername);
        }
    }
}



print_continue($CFG->wwwroot);

?>
