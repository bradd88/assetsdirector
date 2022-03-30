<?php

/** This class uses curl to GET or POST to an API that returns JSON encoded data. Data is decoded into array of objects */
class CurlJsonApi
{

    /**
     * Query a url using http post. Data is returned as an object or array of objects.
     * @return array|object
     */
    public function post($header, $url, $vars): mixed
    {
        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, $url);
        curl_setopt($request, CURLOPT_HTTPHEADER, $header);
        curl_setopt($request, CURLOPT_POST, 1);
        curl_setopt($request, CURLOPT_POSTFIELDS, $vars);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($request));
        curl_close($request);
        return $response;
    }

    /**
     * Query a url using http post. Data is returned as an object or array of objects.
     * @return array|object
     */
    public function get($header, $url): string
    {
        $request = curl_init();
        curl_setopt($request, CURLOPT_URL, $url);
        curl_setopt($request, CURLOPT_HTTPHEADER, $header);
        curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
        $response = json_decode(curl_exec($request));
        curl_close($request);
        return $response;
    }
    
}

?>