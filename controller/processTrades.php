<?php

/** Create and save trades using transactions from the database. */
class ProcessTrades
{
    private MySql $mySql;
    private TransactionListFactory $transactionListFactory;
    private TransactionFactory $transactionFactory;
    private TradeListFactory $tradeListFactory;
    private TradeFactory $tradeFactory;
    private TradeList $openTradeList;
    private Trade $openTrade;
    private array $heldTransactions;

    public function __construct(
        MySql $mySql,
        TransactionListFactory $transactionListFactory,
        TransactionFactory $transactionFactory,
        TradeListFactory $tradeListFactory,
        TradeFactory $tradeFactory
        )
    {
        $this->mySql = $mySql;
        $this->transactionListFactory = $transactionListFactory;
        $this->transactionFactory = $transactionFactory;
        $this->tradeListFactory = $tradeListFactory;
        $this->tradeFactory = $tradeFactory;
    }

    public function exec(int $accountId, string $startDate, string $stopDate): void
    {
        $transactionLists = $this->createTransactionLists($accountId, $startDate, $stopDate);
        $tradeLists = $this->createTradeLists($transactionLists);
        $this->saveTrades($tradeLists, $accountId);
    }

    /** Get transactions from the database, and sort them into transaction lists based on asset type and symbol. */
    private function createTransactionLists(int $accountId, string $startDate, string $stopDate): array
    {
        $databaseResults = $this->mySql->read(
            'transactions',
            NULL,
            [
                ['account_id', 'isEqual', $accountId],
                ['type', 'isEqual', 'TRADE'],
                ['assetType', 'isEqual', 'EQUITY'],
                ['transactionDate', 'isBetween', ['2021-01-01T00:00:00+0000', '2023-01-01T00:00:00+0000']]
            ],
            [
                ['orderId', 'ASC']
            ]
        );

        $transactionLists = array();
        foreach ($databaseResults as $row) {
            $transaction = $this->transactionFactory->cast($row);
            if (!isset($transactionLists[$transaction->symbol])) {
                $transactionLists[$transaction->symbol] = array();
            }
            if (!isset($transactionLists[$transaction->symbol][$transaction->assetType])) {
                $transactionLists[$transaction->symbol][$transaction->assetType] = $this->transactionListFactory->create();
            }
            /** @var TransactionList $transactionList */
            $transactionList = $transactionLists[$transaction->symbol][$transaction->assetType];
            $transactionList->addTransaction($transaction);
        }

        return $transactionLists;
    }

    /** Take transactions from the transaction lists and create trade lists.
     * Transactions are added to open trades one at a time, until the trade is complete and a new trade is opened.
     * 
     * Problem: Transactions are not in actual chronological order, no matter how they are sorted, because the date/time stamps from the TDA API are inaccurate or wrong.
     * Workaround: Transactions that fail validation when being added to a trade will be held to the side, and will be added to a future trade.
     * This workaround is most effective when transactions are sorted ascending by Order ID, as the positions are usually only one off from actual chronological order.
     * Sorting by Transaction Date or Order Date can result in significant issues, as transaction timestamps may be as much as several days off from reality.
     */
    private function createTradeLists(array $transactionLists): array
    {
        $tradeLists = array();
        foreach ($transactionLists as $symbol) {
            foreach ($symbol as $assetType) {
                $this->openTradeList = $this->tradeListFactory->create();
                $this->openTrade = $this->tradeFactory->create();
                $this->heldTransactions = array();
                /** @var TransactionList $transactionList */
                $transactionList = $assetType;
                foreach ($transactionList->getTransactions() as $transaction) {
                    $addTransaction = $this->addTransactionToTrade($transaction);
                    if (count($this->heldTransactions) > 0) {
                        foreach ($this->heldTransactions as $key => $heldTransaction) {
                            $this->heldTransactions[$key]['holdTime']++;
                            if ($this->addTransactionToTrade($heldTransaction['transaction']) === TRUE) {
                                $id = $this->heldTransactions[$key]['transaction']->transactionId;
                                $holdTime = $this->heldTransactions[$key]['holdTime'];
                                trigger_error('Transaction ' . $id . ' was out of place by ' . $holdTime . ' positions.', E_USER_NOTICE);
                                unset($this->heldTransactions[$key]);
                            }
                        }
                    }
                    if ($addTransaction === FALSE) {
                        $this->heldTransactions[] = array('transaction' => $transaction, 'holdTime' => 0);
                    }
                }
                $tradeLists[] = $this->openTradeList;
            }
        }
        return $tradeLists;
    }

    private function addTransactionToTrade(Transaction $transaction): bool
    {
        $addStatus = $this->openTrade->addTransaction($transaction);
        if ($this->openTrade->status === 'complete') {
            $this->openTradeList->addTrade($this->openTrade);
            $this->openTrade = $this->tradeFactory->create();
        }
        return $addStatus;
    }

    /** Save trades from the trade lists to the database, and update transactions in the database to reference the new trades. Return the number of trades entered into the database. */
    private function saveTrades(array $tradeLists, int $accountId): int
    {
        $tradeCount = 0;
        foreach ($tradeLists as $tradeList) {
            /** @var TradeList $tradeList */
            foreach ($tradeList->getTrades() as $trade) {
                $start = new DateTime('@' . $trade->openTimestamp);
                $stop = new DateTime('@' . $trade->closeTimestamp);
                $tradeId = $this->mySql->create('trades', [
                    'account_id' => $accountId,
                    'symbol' => $trade->symbol,
                    'assetType' => $trade->assetType,
                    'quantity' => $trade->quantity,
                    'return' => $trade->return,
                    'start' => $start->format('c'),
                    'stop' => $stop->format('c'),
                    'length' => $trade->lengthSeconds,
                    'tradeDescription' => $trade->strategy
                ]);
                $tradeCount++;
                foreach ($trade->transactionIds as $transactionId) {
                    $affectedRows = $this->mySql->update('transactions', [
                        ['transactionId', 'isEqual', $transactionId]
                    ], ['trade_id' => $tradeId]);
                    if ($affectedRows !== 1) {
                        throw new Exception('Unexpected error updating transaction id: ' . $transactionId . '. Affected Rows: . ' . $affectedRows);
                    }
                }
            }
        }
        return $tradeCount;
    }

}

?>