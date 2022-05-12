<?php

class SummaryPage extends AbstractPage
{
    protected View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    public function exec(): string
    {
        return $this->generate('page/summary.phtml', [], TRUE);
    }
}

?>