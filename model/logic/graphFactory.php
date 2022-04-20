<?php

/** Handles creation of new graphs. */
class GraphFactory
{
    public function __construct()
    {
        
    }
    
    /** Create and return a new graph. */
    public function create(): Graph
    {
        return new Graph();
    }
}

?>