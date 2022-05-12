<?php

class LogoutPage extends AbstractPage
{
    private Login $login;

    public function __construct(Login $login)
    {
        $this->login = $login;
    }

    public function exec(): string
    {
        $this->login->logout();
        header("Location: ./");
        return '';
    }
}

?>