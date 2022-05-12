<?php

class TradesPage extends AbstractPage
{
    protected View $view;
    private MySql $mySql;
    private Login $login;

    public function __construct(View $view, MySql $mySql, Login $login)
    {
        $this->view = $view;
        $this->mySql = $mySql;
        $this->login = $login;
    }

    public function exec(): string
    {
        $transactions = $this->mySql->read(
            'transactions',
            NULL,
            [
                ['account_id', 'isEqual', $this->login->getAccountId()],
                ['type', 'isEqual', 'TRADE'],
                ['assetType', 'isEqual', 'EQUITY'],
                ['transactionDate', 'isBetween', ['2021-01-01T00:00:00+0000', '2021-10-30T00:00:00+0000']]
            ]
        );
        return $this->generate('page/transactions.phtml', ['transactions' => $transactions], TRUE);
    }
}

?>