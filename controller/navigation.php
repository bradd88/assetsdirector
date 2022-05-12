<?php 


class Navigation
{
    private Login $login;
    private Request $request;

    public function __construct(Login $login, Request $request)
    {
        $this->login = $login;
        $this->request = $request;
    }

    /** Examine the page request and return the class name for the appropriate page. If the user is not logged in the login page class name is returned. */
    public function exec(): string
    {
        if ($this->login->status() === TRUE) {
            $this->request->get->page = strtolower(htmlentities($this->request->get->page, ENT_QUOTES)) ?? 'home';
            $this->request->get->page = ($this->request->get->page === 'login') ? 'home' : $this->request->get->page;
            return $this->pageClassName($this->request->get->page);
        } else {
            return $this->pageClassName('login');
        }
    }

    /** Determine the class to load for the requested page. */
    public function pageClassName(string $page): string
    {
        $page = strtolower(htmlentities($page, ENT_QUOTES));
        $pages = array('login', 'logout', 'home', 'transactions', 'trades', 'summary', 'account');
        if (in_array($page, $pages)) {
            $pageClassName = ucfirst($page) . 'Page';
        } else {
            $pageClassName = 'NotFound' . 'Page';
        }
        return $pageClassName;
    }

}

?>