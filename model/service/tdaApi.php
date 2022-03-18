<?php 

/*
 * This class handles interactions with the TDA API.
 */
class TdaApi
{

    private Curl $curl;

    public function __construct(Curl $curl)
    {
        $this->curl = $curl;
    }

    // Create a brand new refresh token using a permission code. This only needs to be run on first setup or when the current refresh token expired.
    public function newTokens(string $permissionCode, string $consumerKey, string $redirectUri)
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=authorization_code&refresh_token=&access_type=offline&code=' . urlencode($permissionCode) . '&client_id=' . urlencode($consumerKey) . '&redirect_uri=' . urlEncode($redirectUri);
        $header = array('Content-Type: application/x-www-form-urlencoded');
        $apiResponse = $this->curl->post($header, $url, $vars);
        return $apiResponse;
    }
    
    // Request a new Refresh Token from the TDA API (which allows requesting of Access Tokens) and return it as a string. This should be run before the current refresh token expires, to avoid the manual step of creating a permission code for a grant.
    public function refreshToken(string $refreshToken, string $consumerKey)
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=offline&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        $apiResponse = $this->curl->post($header, $url, $vars);
        return $apiResponse;
    }
    
    // Request a new Access Token from the TDA API, and return it as a string.
    // TDA Access Tokens expire after 30 minutes.
    public function accessToken(string $refreshToken, string $consumerKey)
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        $apiResponse = $this->curl->post($header, $url, $vars);
        return $apiResponse;
    }

    // Download Transaction History from the TDA API.
    public function getTransactions(string $accessToken, string $accountNumber, string $startDate, string $endDate)
    {
        //$url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?type=' . $type . '&startDate=' . $startDate . '&endDate=' . $endDate;
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?startDate=' . $startDate . '&endDate=' . $endDate;
        $header = array('Authorization: Bearer ' . $accessToken);
        $apiResponse = $this->curl->get($header, $url);
        return $apiResponse;
    }

    // Download Orders from the TDA API, and return the data as an array of objects.
    // TDA Orders are realtime (updated as new orders are placed).
    // Warning: This API is unreliable and frequently missing orders that are placed too rapidly. Need a way to validate data in realtime.
    public function getOrders(string $status, string $startDate, string $endDate, string $accessToken)
    {
        $url = 'https://api.tdameritrade.com/v1/orders?fromEnteredTime=' . $startDate . '&toEnteredTime=' . $endDate . '&status=' . $status;
        $header = array('Authorization: Bearer ' . $accessToken);
        $apiResponse = $this->curl->get($header, $url);
        return $apiResponse;
    }

    // Get Account balances, positions, and orders from the TDA API, and return the data as an array of objects.
    public function getAccountInfo(string $accessToken, string $accountNumber)
    {
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber;
        $header = array('Authorization: Bearer ' . $accessToken);
        $apiResponse = $this->curl->get($header, $url);
        return $apiResponse;
    }

}

?>