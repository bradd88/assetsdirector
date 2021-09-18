<?php

/*
 * This is the session controller, which handles cookies and sessions for account log ins.
 */

// Start a session.
function sessionBegin() {
    if (session_status() === PHP_SESSION_ACTIVE) {
        sessionEnd();
    }
    $cookieParams = session_get_cookie_params();
    $cookieParams['secure'] = 'true';
    $cookieParams['SameSite'] = 'Strict';
    session_set_cookie_params($cookieParams);
    session_start();
}

// Stop a session.
function sessionEnd() {
    session_unset();
    session_destroy();
}

// Configure session for a logged in user.
function sessionLogin($id) {
    sessionBegin();
    $_SESSION['userId'] = $id;
    sessionRenew($id);
}

// Update session id, expiration time, and last seen time.
function sessionRenew($id) {
    session_regenerate_id();
    $_SESSION['expire'] = time() + $GLOBALS['config']['application']['loginTimeout'];
    mySqlUpdate('accounts', ['lastSeen' => time()], 'id', $id);
}

// Check the status of a session. Returns TRUE for valid session, or FALSE for an invalid session.
function sessionCheck() {
    // Make sure the session is active.
    if (session_status() === PHP_SESSION_NONE) {
        sessionBegin();
    }
    if (isset($_SESSION['userId'])) {
        if (time() > $_SESSION['expire']) {
            // End expired sessions.
            sessionEnd();
            return FALSE;
        } else {
            // Renew current sessions.
            sessionRenew($_SESSION['userId']);
            return TRUE;
        }
    } else {
        // No account logged in.
        return FALSE;
    }
}

?>