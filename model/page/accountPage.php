<?php

class AccountPage extends AbstractPage
{
    protected View $view;
    private MySql $mySql;
    private TdaApi $tdaApi;
    private Login $login;

    public function __construct(View $view, MySql $mySql, TdaApi $tdaApi, Login $login)
    {
        $this->view = $view;
        $this->mySql = $mySql;
        $this->tdaApi = $tdaApi;
        $this->login = $login;
    }

    public function exec(): string
    {
        // If a permission grant code has been submitted then generate new TDA tokens for the account.
        if (isset($this->request->get->code)) {
            $this->tdaApi->createTokens($this->login->getAccountId(), htmlspecialchars($this->request->get->code, ENT_QUOTES));
        }

        // Display Token expiration status.
        $account = $this->mySql->read(
            'tda_api',
            NULL,
            [
                ['account_id', 'isEqual', $this->login->getAccountId()]
            ]
            )[0];
        $refreshTokenStatus = ($account->refreshTokenExpiration > time()) ? 'Current' : 'Expired';
        $accessTokenStatus = ($account->accessTokenExpiration > time()) ? 'Current' : 'Expired';

        $pageParameters = array(
            'consumerKey' => $account->consumerKey,
            'redirectUri' => $account->redirectUri,
            'refreshTokenStatus' => $refreshTokenStatus,
            'accessTokenStatus' => $accessTokenStatus
        );
        $content = $this->view->get('page/account.phtml', $pageParameters);
        return $this->generatePage($content, TRUE);
    }
}

?>