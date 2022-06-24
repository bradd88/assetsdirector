<?php

abstract class AbstractPage
{
    protected View $view;

    public function __construct(View $view)
    {
        $this->view = $view;
    }

    /** Execute page code and return the page HTML as a string. */
    abstract public function exec(): string;

    /** Use the View class to insert page content and generate the HTML string for the page. */
    protected function generatePage(string $content, bool $menuEnabled): string
    {
        $css = $this->view->get('presentation/style.css');
        $menu = ($menuEnabled === TRUE) ? $this->view->get('presentation/menu.phtml') : '';
        $html = $this->view->get('presentation/layout.phtml', ['css' => $css, 'menu' => $menu, 'content' => $content]);
        $html = preg_replace('( {4})', '', $html);
        return $html;
    }
}

?>