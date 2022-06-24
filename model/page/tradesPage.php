<?php

class TradesPage extends AbstractPage
{
    protected View $view;
    private MySql $mySql;
    private Login $login;
    private TradeListFactory $tradeListFactory;
    private TradeFactory $tradeFactory;
    private GraphFactory $graphFactory;

    public function __construct(
        View $view,
        MySql $mySql,
        Login $login,
        TradeListFactory $tradeListFactory,
        TradeFactory $tradeFactory,
        GraphFactory $graphFactory
        )
    {
        $this->view = $view;
        $this->mySql = $mySql;
        $this->login = $login;
        $this->tradeListFactory = $tradeListFactory;
        $this->tradeFactory = $tradeFactory;
        $this->graphFactory = $graphFactory;
    }

    public function exec(): string
    {
        // Get the trades and add running statistics.
        $trades = $this->mySql->read(
            'trades',
            NULL,
            [
                ['account_id', 'isEqual', $this->login->getAccountId()],
                ['assetType', 'isEqual', 'EQUITY'],
                ['stop', 'isBetween', ['2021-01-01T00:00:00+0000', '2021-02-30T00:00:00+0000']]
            ],
            [
                ['start', 'ASC']
            ]
        );

        // Create the Trades list and generate statistics.
        $tradeList = $this->tradeListFactory->create();
        foreach ($trades as $row) {
            $trade = $this->tradeFactory->cast($row);
            $tradeList->addTrade($trade);
        }
        $tradeList->addStatistics();

        // Generate the graph of trade data.
        $graph = $this->graphFactory->create();
        $graph->addLine('Returns', 'black', $tradeList->generateGraphData());
        $graph->generate(25);

        // Generate html.
        $tradeTable = $this->view->get('page/trades.phtml', ['calendar' => $this->calendar, 'trades' => $tradeList->getTrades()]);
        $graphCanvas = $this->view->get('presentation/graph.phtml', ['graph' => $graph]);
        $content = $graphCanvas . $tradeTable;
        return $this->generatePage($content, TRUE);
    }
}

?>