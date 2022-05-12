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
        return $this->generate('page/home.phtml', [], TRUE);
    }
}

?>