<?php

class Request
{
    public object $get;
    public object $post;
    public object $server;

    public function __construct()
    {
        $this->get = (object) $_GET;
        $this->post = (object) $_POST;
        $this->server = (object) $_SERVER;
    }

}

?>