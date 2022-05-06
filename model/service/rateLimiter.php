<?php

/** Delays the execution of a function/method based on the rate limit. */
class RateLimiter
{
    private float $executionsPerMinute;
    private float $lastExecutionTime;

    public function __construct(float $executionsPerMinute)
    {
        $this->executionsPerMinute = $executionsPerMinute;
        $this->lastExecutionTime = 0;
    }

    /** Delay the execution of a method until the rate limit has been reached. Return the return value of the supplied method. */
    public function exec(callable $method, array $parameters): mixed
    {
        $rateLimitDelay = bcdiv(60, $this->executionsPerMinute, 6);
        $targetExecutionTime = (float) bcadd($this->lastExecutionTime, $rateLimitDelay, 6);
        while (TRUE) {
            if (microtime(TRUE) >= $targetExecutionTime) {
                $methodReturn = $method(...$parameters);
                $this->lastExecutionTime = microtime(TRUE);
                return $methodReturn;
            } else {
                usleep(10000);
            }
        }
    }
}

?>