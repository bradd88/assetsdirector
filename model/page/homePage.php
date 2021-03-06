<?php

class HomePage extends AbstractPage
{
    protected View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    public function exec(): string
    {
        $content = $this->view->get('page/home.phtml', []);
        return $this->generatePage($content, TRUE);
    }
}

?>