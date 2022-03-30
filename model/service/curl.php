<?php

/** This class uses curl to GET or POST to an API that returns JSON encoded data. Data is decoded into array of objects */
class CurlJsonApi
{

    private Log $log;

    public function __construct(Log $log)
    {
        $this->log = $log;
    }

    /**
     * Query a url using http GET or POST. Data is returned as an object or array of objects.
     * @return array|object
     */
    public function exec(string $method, array $header, string $url, ?string $vars = NULL): mixed
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
        $response = json_decode(curl_exec($request));
        curl_close($request);
        $this->log->save('curl', $logMessage);
        return $response;
    }
    
}

?>