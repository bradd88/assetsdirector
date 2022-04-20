<?php

class JsonApiResponse
{
    public int $httpdCode;
    public array|object $response;

    public function __construct(int $httpdCode, string $response)
    {
        $this->httpdCode = $httpdCode;
        $this->response = json_decode($response);
    }
}

?>