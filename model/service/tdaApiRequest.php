<?php

/** Request information from the TD Ameritrade API. Automatically Rate Limits requests to 100 per minute. */
class TdaApiRequest
{
    public CurlJsonApi $curl;
    private RateLimiter $rateLimiter;

    public function __construct(CurlJsonApi $curl, RateLimiterFactory $rateLimiterFactory)
    {
        $this->curl = $curl;
        $this->rateLimiter = $rateLimiterFactory->create(100);
    }

    /** Request brand new refresh and access tokens from the TDA API using a permission grant code. */
    public function newTokens(string $permissionCode, string $consumerKey, string $redirectUri): JsonApiResponse
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=authorization_code&refresh_token=&access_type=offline&code=' . urlencode($permissionCode) . '&client_id=' . urlencode($consumerKey) . '&redirect_uri=' . urlEncode($redirectUri);
        $header = array('Content-Type: application/x-www-form-urlencoded');
        return $this->rateLimiter->exec([$this->curl, 'exec'], ['post', $header, $url, $vars]);
    }
    
    /** Request new refresh and access tokens from the TDA API using an unexpired refresh token. */
    public function refreshToken(string $refreshToken, string $consumerKey): JsonApiResponse
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=offline&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        return $this->rateLimiter->exec([$this->curl, 'exec'], ['post', $header, $url, $vars]);
    }
    
    /** Request a new access token from the TDA API using an unexpired refresh token. */
    public function accessToken(string $refreshToken, string $consumerKey): JsonApiResponse
    {
        $url = "https://api.tdameritrade.com/v1/oauth2/token";
        $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
        $header = array('Content-Type: application/x-www-form-urlencoded');
        return $this->rateLimiter->exec([$this->curl, 'exec'], ['post', $header, $url, $vars]);
    }

    /** Request transaction history data from the TDA API. */
    public function transactions(string $accessToken, string $accountNumber, string $startDate, string $endDate): JsonApiResponse
    {
        //$url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?type=' . $type . '&startDate=' . $startDate . '&endDate=' . $endDate;
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?startDate=' . $startDate . '&endDate=' . $endDate;
        $header = array('Authorization: Bearer ' . $accessToken);
        return $this->rateLimiter->exec([$this->curl, 'exec'], ['get', $header, $url]);
    }

    /** Request orders data from the TDA API. */
    public function orders(string $startDate, string $endDate, string $accessToken, ?string $status = NULL): JsonApiResponse
    {
        $url = 'https://api.tdameritrade.com/v1/orders?fromEnteredTime=' . $startDate . '&toEnteredTime=' . $endDate . '&status=' . $status;
        $header = array('Authorization: Bearer ' . $accessToken);
        return $this->rateLimiter->exec([$this->curl, 'exec'], ['get', $header, $url]);
    }

    /** Request account information from the TDA API. */
    public function accountInfo(string $accessToken, string $accountNumber): JsonApiResponse
    {
        $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber;
        $header = array('Authorization: Bearer ' . $accessToken);
        return $this->rateLimiter->exec([$this->curl, 'exec'], ['get', $header, $url]);
    }

}