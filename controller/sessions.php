<?php 

class Session {
    
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $cookieParams = session_get_cookie_params();
            $cookieParams['secure'] = 'true';
            $cookieParams['HttpOnly'] = 'true';
            $cookieParams['SameSite'] = 'Strict';
            session_set_cookie_params($cookieParams);
            session_start();
        }
    }

    public static function stop()
    {
        session_unset();
        session_destroy();
    }

    public static function login($id)
    {
        $_SESSION['userId'] = $id;
        Self::renew($id);
    }

    public static function renew($id)
    {
        session_regenerate_id();
        $_SESSION['expire'] = time() + $GLOBALS['config']['application']['login_timeout'];
        MySql::update('accounts', ['lastSeen' => time()], 'id', $id);
    }

    public static function loggedIn()
    {
        if (isset($_SESSION['userId'])) {
            if (time() > $_SESSION['expire']) {
                // Cleanup session if it's expired.
                Self::stop();
                return FALSE;
            } else {
                // Renew session if it's not expired.
                Self::renew($_SESSION['userId']);
                return TRUE;
            }
        } else {
            // No account logged in.
            return FALSE;
        }
    }

}

?>