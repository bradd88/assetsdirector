<?php

/** Request information from the TD Ameritrade API */
class TdaApiRequest
{
    public CurlJsonApi $curl;

    public function __construct(CurlJsonApi $curl)
    {
        $this->curl = $curl;
    }

    /** Request brand new refresh and access tokens from the TDA API using a permission grant code. */
    public function newTokens(string $permissionCode, string $consumerKey, string $redirectUri): object
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=authorization_code&refresh_token=&access_type=offline&code=' . urlencode($permissionCode) . '&client_id=' . urlencode($consumerKey) . '&redirect_uri=' . urlEncode($redirectUri);
        $header = array('Content-Type: application/x-www-form-urlencoded');
        return $this->curl->exec('post', $header, $url, $vars);
    }
    
    /** Request new refresh and access tokens from the TDA API using an unexpired refresh token. */
    public function refreshToken(string $refreshToken, string $consumerKey): object
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=offline&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        return $this->curl->exec('post', $header, $url, $vars);
    }
    
    /** Request a new access token from the TDA API using an unexpired refresh token. */
    public function accessToken(string $refreshToken, string $consumerKey): object
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        return $this->curl->exec('post', $header, $url, $vars);
    }

    /** Request transaction history from the TDA API. Returns an array of objects. */
    public function transactions(string $accessToken, string $accountNumber, string $startDate, string $endDate): array
    {
        //$url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?type=' . $type . '&startDate=' . $startDate . '&endDate=' . $endDate;
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?startDate=' . $startDate . '&endDate=' . $endDate;
        $header = array('Authorization: Bearer ' . $accessToken);
        return $this->curl->exec('get', $header, $url);
    }

    /** Request orders data from the TDA API. Data is unreliable from this API, so it's prefferable to use the transactions API. Returns an array of objects. */
    public function orders(string $status, string $startDate, string $endDate, string $accessToken): array
    {
        $url = 'https://api.tdameritrade.com/v1/orders?fromEnteredTime=' . $startDate . '&toEnteredTime=' . $endDate . '&status=' . $status;
        $header = array('Authorization: Bearer ' . $accessToken);
        return $this->curl->exec('get', $header, $url);
    }

    /** Request account information from the TDA API. */
    public function accountInfo(string $accessToken, string $accountNumber): mixed
    {
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber;
        $header = array('Authorization: Bearer ' . $accessToken);
        // FIX: verify the TDA API always returns the same data type: Array of objects or just a single object.
        return $this->curl->exec('get', $header, $url);
    }
}