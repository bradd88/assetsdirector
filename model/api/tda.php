<?php 

/*
 * This is the TD Ameritrade API model, which handles queries to the various TDA APIs.
 */

class Curl {

    // Use curl to query a URL using POST.
    public static function post($header, $url, $vars)
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
    public static function get($header, $url)
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

class TDAToken {

    // Create a brand new refresh token using a permission code. This only needs to be run on first setup or when the current refresh token expired.
    public static function create($permissionCode, $consumerKey, $redirectUri)
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=authorization_code&refresh_token=&access_type=offline&code=' . urlencode($permissionCode) . '&client_id=' . urlencode($consumerKey) . '&redirect_uri=' . urlEncode($redirectUri);
        $header = array('Content-Type: application/x-www-form-urlencoded');
        $apiResponse = Curl::post($header, $url, $vars);
        return $apiResponse;
    }
    
    // Request a new Refresh Token from the TDA API (which allows requesting of Access Tokens) and return it as a string. This should be run before the current refresh token expires, to avoid the manual step of creating a permission code for a grant.
    public static function refresh($refreshToken, $consumerKey)
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=offline&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        $apiResponse = Curl::post($header, $url, $vars);
        return $apiResponse;
    }
    
    // Request a new Access Token from the TDA API, and return it as a string.
    // TDA Access Tokens expire after 30 minutes.
    public static function access($refreshToken, $consumerKey)
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        $apiResponse = Curl::post($header, $url, $vars);
        return $apiResponse;
    }

}

class TDAAccount {

    // Download Transaction History from the TDA API.
    public static function getTransactions($accessToken, $accountNumber, $startDate, $endDate)
    {
        //$url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?type=' . $type . '&startDate=' . $startDate . '&endDate=' . $endDate;
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?startDate=' . $startDate . '&endDate=' . $endDate;
        $header = array('Authorization: Bearer ' . $accessToken);
        $apiResponse = Curl::get($header, $url);
        return $apiResponse;
    }

    // Download Orders from the TDA API, and return the data as an array of objects.
    // TDA Orders are realtime (updated as new orders are placed).
    // Warning: This API is unreliable and frequently missing orders that are placed too rapidly. Need a way to validate data in realtime.
    public static function getOrders($status, $startDate, $endDate, $accessToken)
    {
        $url = 'https://api.tdameritrade.com/v1/orders?fromEnteredTime=' . $startDate . '&toEnteredTime=' . $endDate . '&status=' . $status;
        $header = array('Authorization: Bearer ' . $accessToken);
        $apiResponse = Curl::get($header, $url);
        return $apiResponse;
    }

    // Get Account balances, positions, and orders from the TDA API, and return the data as an array of objects.
    public static function getAccountInfo($accessToken, $accountNumber)
    {
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber;
        $header = array('Authorization: Bearer ' . $accessToken);
        $apiResponse = Curl::get($header, $url);
        return $apiResponse;
    }

}

?>