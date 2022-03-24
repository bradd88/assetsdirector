<?php

/** Use curl to GET or POST to an API that returns JSON encoded data. Data is decoded into array/objects */
class CurlJsonApi
{
    /**
     * Use curl to query a URL using POST.
     *
     * @return array|object Response is JSON decoded into an array and/or objects.
     */
    public function post($header, $url, $vars)
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
     * Use curl to query a URL using GET.
     *
     * @return array|object Response is JSON decoded into an array and/or objects.
     */
    public function get($header, $url)
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