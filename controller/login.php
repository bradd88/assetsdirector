<?php

/** Handles user logins */
class Login
{
    private MySql $mySql;
    private Log $log;
    private Config $config;
    private int $accountId;

    public function __construct(MySql $mySql, Log $log, Config $config)
    {
        $this->mySql = $mySql;
        $this->log = $log;
        $this->config = $config;
        $this->startSession();
    }

    /** Attempt to login the user. */
    public function attemptLogin(string $username, string $password, string $ip): bool
    {  
        $userId = $this->mySql->verifyLogin($username, $password);
        if ($userId === FALSE) {
            $logMessage = 'Failed Login - IP: ' . $ip . ' User: ' . $username . ' Pass: ' . $password;
            $this->log->save('login_failure', $logMessage);
            return FALSE;
        } else {
            $this->login($userId);
            $logMessage = 'Successful Login - IP: ' . $ip . ' User: ' . $username;
            $this->log->save('login_success', $logMessage);
            return TRUE;
        }
    }

    /** Logout the current user. */
    public function logout(): void
    {
        unset($this->accountId);
        session_unset();
        session_destroy();
    }

    /** Check the current session, and update it based on session expiration. */
    public function status(): bool
    {
        if (isset($_SESSION['accountId'])) {
            if (time() < $_SESSION['expire']) {
                $this->login($_SESSION['accountId']);
                return TRUE;
            } else {
                $this->logout();
                return FALSE;
            }
        } else {
            return FALSE;
        }
    }

    /** Return the account ID of the currently logged in user. */
    public function getAccountId(): int
    {
        return $this->accountId;
    }

    /** Create session and store session data in a cookie. */
    private function startSession(): void
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

    /** Save login data to the session. */
    private function login(int $id): void
    {
        $this->accountId = $id;
        $_SESSION['accountId'] = $this->accountId;
        $_SESSION['expire'] = time() + $this->config->getSettings('application')->login_timeout;
        $this->mySql->update('accounts', ['account_id' => $this->accountId,], ['lastSeen' => time()]);
        session_regenerate_id();
    }
}

?>