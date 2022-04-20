<?php

/** CURL an API using GET or POST, and return the JSON response as a CurlResponse object. */
class CurlJsonApi
{

    private Log $log;
    private JsonApiResponseFactory $jsonApiResponseFactory;

    public function __construct(Log $log, JsonApiResponseFactory $jsonApiResponseFactory)
    {
        $this->log = $log;
        $this->jsonApiResponseFactory = $jsonApiResponseFactory;
    }

    /** Query a url using http GET or POST and return a JsonApiResponse object containing the http code and decoded json response. */
    public function exec(string $method, array $header, string $url, ?string $vars = NULL): JsonApiResponse
    {
        $logMessage = $method . ' "' . $url . '"';
        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, $url);
        curl_setopt($request, CURLOPT_HTTPHEADER, $header);
        if ($method === 'post') {
            curl_setopt($request, CURLOPT_POST, 1);
            curl_setopt($request, CURLOPT_POSTFIELDS, $vars);
            $logMessage .= ' "' . $vars . '"';
        }
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($request);
        $responseCode = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
        curl_close($request);
        $this->log->save('curl', $logMessage);
        $curlResponse = $this->jsonApiResponseFactory->create($responseCode, $response);
        return $curlResponse;
    }
    
}

?>