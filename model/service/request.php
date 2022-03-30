<?php

/** This class retrieves and stores globaly set variables/arrays such as $_GET, $_POST, and $_SERVER */
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