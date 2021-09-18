<?php 

/*
 * This is the TD Ameritrade API model, which handles queries to the various TDA APIs.
 */

// Use curl to query a URL using POST.
function curlPost($header, $url, $vars) {
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
function curlGet($header, $url) {
    $request = curl_init();
    curl_setopt($request, CURLOPT_URL, $url);
    curl_setopt($request, CURLOPT_HTTPHEADER, $header);
    curl_setopt($request, CURLOPT_RETURNTRANSFER, true);
    $response = json_decode(curl_exec($request));
    curl_close($request);
    return $response;
}

// Setup the initial authorization and Refresh Token using the TDA API. This only needs to be done once at setup.
function tdaCreateRefreshToken($permissionCode, $consumerKey, $redirectUri) {
    $url = "https://api.tdameritrade.com/v1/oauth2/token";
    $vars = 'grant_type=authorization_code&refresh_token=&access_type=offline&code=' . urlencode($permissionCode) . '&client_id=' . urlencode($consumerKey) . '&redirect_uri=' . urlEncode($redirectUri);
    $header = array('Content-Type: application/x-www-form-urlencoded');
    $apiResponse = curlPost($header, $url, $vars);
    return $apiResponse;
}

// Request a new Refresh Token from the TDA API (which allows requesting of Access Tokens) and return it as a string.
// Refresh Tokens expire after 90 days.
function tdaRetrieveRefreshToken($refreshToken, $consumerKey) {
    $url = "https://api.tdameritrade.com/v1/oauth2/token";
    $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=offline&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
    $header = array('Content-Type: application/x-www-form-urlencoded');
    $apiResponse = curlPost($header, $url, $vars);
    return $apiResponse;
}

// Request a new Access Token from the TDA API, and return it as a string.
// TDA Access Tokens expire after 30 minutes.
function tdaRetrieveAccessToken($refreshToken, $consumerKey) {
    $url = "https://api.tdameritrade.com/v1/oauth2/token";
    $vars = 'grant_type=refresh_token&refresh_token=' . urlencode($refreshToken) . '&access_type=&code=&client_id=' . urlencode($consumerKey) . '&redirect_uri=';
    $header = array('Content-Type: application/x-www-form-urlencoded');
    $apiResponse = curlPost($header, $url, $vars);
    return $apiResponse;
}

/**
 * Download Transaction History from the TDA API.
 * Transactions are updated by TDA once per day.
 *
 * @param string $accessToken TDA API access token.
 * @param int $accountNumber TD Ameritrade account number.
 * @param string $startDate Download transactions on or after this date.
 * @param string $endDate Download transactions up to this date.
 * @return array Array of objects containing transactions.
 */
function tdaRetrieveTransactionHistory($accessToken, $accountNumber, $startDate, $endDate) {
    //$url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?type=' . $type . '&startDate=' . $startDate . '&endDate=' . $endDate;
    $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber . '/transactions?startDate=' . $startDate . '&endDate=' . $endDate;
    $header = array('Authorization: Bearer ' . $accessToken);
    $apiResponse = curlGet($header, $url);
    return $apiResponse;
}

// Download Orders from the TDA API, and return the data as an array of objects.
// TDA Orders are realtime (updated as new orders are placed).
// Warning: This API is unreliable and frequently missing orders that are placed too rapidly. Need a way to validate data in realtime.
function tdaRetrieveOrders($status, $startDate, $endDate, $accessToken) {
    $url = 'https://api.tdameritrade.com/v1/orders?fromEnteredTime=' . $startDate . '&toEnteredTime=' . $endDate . '&status=' . $status;
    $header = array('Authorization: Bearer ' . $accessToken);
    $apiResponse = curlGet($header, $url);
    return $apiResponse;
}

// Get Account balances, positions, and orders from the TDA API, and return the data as an array of objects.
function tdaRetrieveAccount($accessToken, $accountNumber) {
    $url = 'https://api.tdameritrade.com/v1/accounts/' . $accountNumber;
    $header = array('Authorization: Bearer ' . $accessToken);
    $apiResponse = curlGet($header, $url);
    return $apiResponse;
}
?>