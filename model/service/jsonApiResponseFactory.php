<?php

class JsonApiResponseFactory
{
    public function __construct()
    {
    }

    public function create(int $httpCode, string $response): JsonApiResponse
    {
        return new JsonApiResponse($httpCode, $response);
    }
}

?>