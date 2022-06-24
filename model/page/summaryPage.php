<?php

class SummaryPage extends AbstractPage
{
    protected View $view;
    private MySql $mySql;

    public function __construct(View $view, MySql $mySql)
    {
        $this->view = $view;
        $this->mySql = $mySql;
    }

    public function exec(): string
    {
        $totalFunding = $this->mySql->read(
            'transactions',
            [
                'function' => 'sum',
                'column' => 'netAmount',
                'as' => 'totalFunding'
            ],
            [
                ['type', 'isEqual', ['ELECTRONIC_FUND', 'JOURNAL']],
                ['description', 'notEqual', 'MARK TO THE MARKET']
            ]
        )[0]->totalFunding;

        $dividendAndInterest = $this->mySql->read(
            'transactions',
            [
                'function' => 'sum',
                'column' => 'netAmount',
                'as' => 'dividendAndInterest'
            ],
            [
                ['type', 'isEqual', 'DIVIDEND_OR_INTEREST']
            ]
        )[0]->dividendAndInterest;

        $fees = $this->mySql->read(
            'transactions',
            [
                'function' => 'sum',
                'column' => 'regFee',
                'as' => 'fees'
            ]
        )[0]->fees;

        $transactionCount = $this->mySql->read(
            'transactions',
            [
                'function' => 'count',
                'column' => 'type',
                'as' => 'transactionCount'
            ],
            [
                ['type', 'isEqual', 'TRADE']
            ]
        )[0]->transactionCount;

        $equityReturns = $this->mySql->read(
            'transactions',
            [
                'function' => 'sum',
                'column' => 'netAmount',
                'as' => 'equityReturns'
            ],
            [
                ['assetType', 'isEqual', 'EQUITY']
            ]
        )[0]->equityReturns;

        $optionReturns = $this->mySql->read(
            'transactions',
            [
                'function' => 'sum',
                'column' => 'netAmount',
                'as' => 'optionReturns'
            ],
            [
                ['assetType', 'isEqual', 'OPTION']
            ]
        )[0]->optionReturns;

        $equitiesPurchased = $this->mySql->read(
            'transactions',
            [
                'function' => 'sum',
                'column' => 'amount',
                'as' => 'equitiesPurchased'
            ],
            [
                ['description', 'isEqual', 'BUY TRADE']
            ]
        )[0]->equitiesPurchased;

        $equitiesSold = $this->mySql->read(
            'transactions',
            [
                'function' => 'sum',
                'column' => 'amount',
                'as' => 'equitiesSold'
            ],
            [
                ['description', 'isEqual', 'SELL TRADE']
            ]
        )[0]->equitiesSold;

        $equitiesOutstanding = bcsub($equitiesPurchased, $equitiesSold, 0);
        $tradeReturns = bcadd($equityReturns, $optionReturns, 2);
        $totalReturns = bcadd($tradeReturns, $dividendAndInterest, 2);
        $percentReturn = bcdiv($totalReturns, $totalFunding, 4);
        $percentReturnReadable = bcmul($percentReturn, 100, 2);
        $returnPerTransaction = bcdiv($totalReturns, $transactionCount, 2);

        $pageParameters = array(
            'totalFunding' => $totalFunding,
            'dividendAndInterest' => $dividendAndInterest,
            'fees' => $fees,
            'transactionCount' => $transactionCount,
            'equityReturns' => $equityReturns,
            'optionReturns' => $optionReturns,
            'equitiesOutstanding' => $equitiesOutstanding,
            'totalFunding' => $totalFunding,
            'totalReturns' => $totalReturns,
            'percentReturnReadable' => $percentReturnReadable,
            'returnPerTransaction' => $returnPerTransaction
        );
        $content = $this->view->get('page/summary.phtml', $pageParameters);
        return $this->generatePage($content, TRUE);
    }
}

?>