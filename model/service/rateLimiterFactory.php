<?php

/** Instantiates RateLimiter objects. */
class RateLimiterFactory
{
    public function __construct()
    {
        
    }

    /** Create a new RateLimiter object. */
    public function create(float $executionsPerMinute): RateLimiter
    {
        return new RateLimiter($executionsPerMinute);
    }
}

?>