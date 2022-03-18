<?php

class Curl
{
    // Use curl to query a URL using POST.
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

    // Use curl to query a URL using GET.
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