<?php

class ViewFactory
{
    private Config $config;

    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    public function create(): View
    {
        return new View($this->config);
    }
}

?>