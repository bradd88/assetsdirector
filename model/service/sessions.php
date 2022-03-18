<?php 

class Session
{
    
    private MySql $mySql;
    private object $appSettings;
    
    public $accountId;

    public function __construct(MySql $mySql, Config $config)
    {
        $this->mySql = $mySql;
        $this->appSettings = $config->getSettings('application');
        $this->start();
    }

    private function start()
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

    public function login($id)
    {
        $this->accountId = $id;
        $_SESSION['accountId'] = $this->accountId;
        $_SESSION['expire'] = time() + $this->appSettings->login_timeout;
        $this->mySql->update('accounts', ['account_id' => $this->accountId,], ['lastSeen' => time()]);
        session_regenerate_id();
    }

    public function check()
    {
        if (isset($_SESSION['accountId'])) {
            if (time() < $_SESSION['expire']) {
                // Login the user if their session isn't expired.
                $this->login($_SESSION['accountId']);
                return TRUE;
            } else {
                // Cleanup session if it's expired.
                $this->stop();
                return FALSE;
            }
        } else {
            // No account logged in.
            return FALSE;
        }
    }

    public function stop()
    {
        session_unset();
        session_destroy();
    }

}

?>