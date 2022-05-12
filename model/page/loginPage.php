<?php

class LoginPage extends AbstractPage
{
    protected View $view;
    private Request $request;
    private Login $login;

    public function __construct(View $view, Request $request, Login $login)
    {
        $this->view = $view;
        $this->request = $request;
        $this->login = $login;
    }

    public function exec(): string
    {
        $this->request->get->page = $this->request->get->page ?? 'home';
        $this->request->get->page = ($this->request->get->page === 'login') ? 'home' : $this->request->get->page;
        $message = 'Please login.';
        if (isset($this->request->post->username) && isset($this->request->post->password)) {
            $loginAttempt = $this->login->attemptLogin($this->request->post->username, $this->request->post->password, $this->request->server->REMOTE_ADDR);
            if ($loginAttempt === TRUE) {
                header('Location: ' . $this->request->get->page);
            } else {
                $message = 'Incorrect username/password.';
            }
        }
        return $this->generate('page/login.phtml', ['message' => $message, 'requested' => $this->request->get->page], FALSE);
    }
}

?>