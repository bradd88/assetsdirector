<?php

/** Request information from the TD Ameritrade API */
class TdaApiRequest
{
    public CurlJsonApi $curl;

    public function __construct(CurlJsonApi $curl)
    {
        $this->curl = $curl;
    }

    /**
     * Request new refresh and access tokens from the TDA API using a permission grant code.
     * Should only be used when the current refresh token was not renewed in time and is now expired, as generating a permission code requires manual verification by the user.
     *
     * @return object Object containing token data.
     */
    public function newTokens(string $permissionCode, string $consumerKey, string $redirectUri)
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=authorization_code&refresh_token=&access_type=offline&code=' . urlencode($permissionCode) . '&client_id=' . urlencode($consumerKey) . '&redirect_uri=' . urlEncode($redirectUri);
        $header = array('Content-Type: application/x-www-form-urlencoded');
        $apiResponse = $this->curl->post($header, $url, $vars);
        return $apiResponse;
    }
    
    /**
     * Request new refresh and access tokens from the TDA API using a valid refresh token.
     *
     * @return object Object containing token data.
     */
    public function refreshToken(string $refreshToken, string $consumerKey)
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=offline&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        $apiResponse = $this->curl->post($header, $url, $vars);
        return $apiResponse;
    }
    
    /**
     * Request a new access token from the TDA API using a valid refresh token.
     *
     * @return object Object containing token data.
     */
    public function accessToken(string $refreshToken, string $consumerKey)
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        $apiResponse = $this->curl->post($header, $url, $vars);
        return $apiResponse;
    }

    /**
     * Request transaction history from the TDA API.
     *
     * @return array Array of objects containing transaction data.
     */
    public function transactions(string $accessToken, string $accountNumber, string $startDate, string $endDate)
    {
        //$url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?type=' . $type . '&startDate=' . $startDate . '&endDate=' . $endDate;
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?startDate=' . $startDate . '&endDate=' . $endDate;
        $header = array('Authorization: Bearer ' . $accessToken);
        $apiResponse = $this->curl->get($header, $url);
        return $apiResponse;
    }

    /**
     * Request orders data from the TDA API.
     * Note: Data seems to be unreliable, and sometimes missings orders. Transaction API is more reliable.
     * 
     * @return array Array of objects containg order data.
     */
    public function orders(string $status, string $startDate, string $endDate, string $accessToken)
    {
        $url = 'https://api.tdameritrade.com/v1/orders?fromEnteredTime=' . $startDate . '&toEnteredTime=' . $endDate . '&status=' . $status;
        $header = array('Authorization: Bearer ' . $accessToken);
        $apiResponse = $this->curl->get($header, $url);
        return $apiResponse;
    }

    /**
     * Request account information from the TDA API.
     *
     * @param string $accessToken
     * @param string $accountNumber
     * @return array Array of objects containing balance, position, and order data.
     */
    public function accountInfo(string $accessToken, string $accountNumber)
    {
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber;
        $header = array('Authorization: Bearer ' . $accessToken);
        $apiResponse = $this->curl->get($header, $url);
        return $apiResponse;
    }
}