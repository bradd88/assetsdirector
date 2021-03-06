<?php

class TransactionsPage extends AbstractPage
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
                ['symbol', 'isEqual', 'AAPL'],
                ['transactionDate', 'isBetween', ['2022-02-17T00:00:00+0000', '2022-02-18T00:00:00+0000']]
            ],
            [
                ['orderDate', 'ASC']
            ]
            );
        $content = $this->view->get('page/transactions.phtml', ['transactions' => $transactions]);
        return $this->generatePage($content, TRUE);
    }
}

?>