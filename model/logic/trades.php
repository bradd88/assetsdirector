<?php 

class Trade {
    private $buyTransactions;
    private $sellTransactions;

    public $symbol;
    public $transactionCount;
    public $type;
    public $openTimestamp;
    public $closeTimestamp;
    public $length;
    public $buy;
    public $sell;
    public $return;
    public $win;
    public $orderIds;

    // Calculate buy, sell, and return data for the trade.
    public function __construct($transactions)
    {
        $this->transactionCount = 0;
        $this->orderIds = [];
        foreach ($transactions as $transaction) {
            // Keep record of the timestamps for the first transaction and the last transaction.
            $timestamp = strtotime($transaction->transactionDate);
            $this->openTimestamp = (isset($this->openTimestamp)) ? min($timestamp, $this->openTimestamp) : $timestamp;
            $this->closeTimestamp = (isset($this->closeTimestamp)) ? max($timestamp, $this->closeTimestamp) : $timestamp;

            // Keep track of the order Ids and number of transactions
            if (!in_array($transaction->orderId, $this->orderIds)) {
                $this->orderIds[] = $transaction->orderId;
            }
            $this->transactionCount += 1;

            // Sort transactions into lists by buy/sell
            if ($transaction->transactionSubType === 'BY' || $transaction->transactionSubType === 'CS') {
                $this->buyTransactions[] = $transaction;
            } elseif ($transaction->transactionSubType === 'SS' || $transaction->transactionSubType === 'SL') {
                $this->sellTransactions[] = $transaction;
            }
        }

        // Calculate p/l and basic trade info
        $this->buy = $this->calculateLeg($this->buyTransactions);
        $this->sell = $this->calculateLeg($this->sellTransactions);
        $this->return = bcadd($this->sell->netCost, $this->buy->netCost, 2);
        $this->win = ($this->return >= 0) ? 'true' : 'false';
        $this->symbol = $transactions[0]->symbol;
        $this->length = calculateTimespan($this->openTimestamp, $this->closeTimestamp, 2);

        // Determine the type and strategy for the trade
        $this->type = ($transactions[0]->transactionSubType === 'BY') ? 'Long ' : 'Short ';
        $lengthInSeconds = bcsub("$this->closeTimestamp", "$this->openTimestamp", 0);
        $this->type .= (count($this->orderIds) > 2) ? 'Scaled ' : '';
        $this->type .= ($lengthInSeconds <= 3600) ? 'Scalp' : 'Swing';
    }

    // Calculate data for the buy or sell side of the trade.
    private function calculateLeg($transactions)
    {
        $leg = new stdClass;
        $leg->firstDt = NULL;
        $leg->lastDt = NULL;
        $leg->fee = 0;
        $leg->cost = 0;
        $leg->amount = 0;
        foreach ($transactions as $transaction) {
            $leg->fee = bcadd("$leg->fee", "$transaction->regFee", 2);
            $leg->cost = bcadd("$leg->cost", "$transaction->cost", 2);
            $leg->amount = bcadd("$leg->amount", "$transaction->amount", 2);
        }
        $leg->netCost = bcsub("$leg->cost", "$leg->fee", 2);
        $leg->avgPrice = bcdiv("$leg->netCost", "$leg->amount", 10);
        return $leg;
    }

}

class TradeList {
    private $transactions;

    public $trades;
    public $graphCoordinates;

    public function __construct($transactions)
    {
        $this->transactions = $transactions;
        $this->findTrades();
        $this->calculatePl();
    }

    private function findTrades()
    {
        $tradeTransactions = [];
        $outstanding = 0;
        foreach ($this->transactions as $transaction) {
            // Store transactions in a list that will be used to create a trade.
            $tradeTransactions[] = $transaction;
            // Keep a running total of outstanding shares.
            if ($transaction->transactionSubType === 'BY' || $transaction->transactionSubType === 'CS') {
                $outstanding = bcadd("$outstanding", "$transaction->amount", 0);
            } elseif ($transaction->transactionSubType === 'SS' || $transaction->transactionSubType === 'SL') {
                $outstanding = bcsub("$outstanding", "$transaction->amount", 0);
            }

            // When outstanding shares equal out, use the stored transactions to create a trade, and clear the stored transactions.
            if ($outstanding == 0) {
                $this->trades[] = new Trade($tradeTransactions);
                $tradeTransactions = [];
            }
        }
    }

    private function calculatePl()
    {
        $totalTrades = 0;
        $totalReturn = 0;
        $winTrades = 0;
        $coordinates = [];
        foreach ($this->trades as &$trade) {
            // Track returns and trades.
            ($trade->return > 0) ? $winTrades++ : '';
            $totalTrades++;
    
            // Keep a running total for trades.
            $totalReturn = bcadd("$trade->return", "$totalReturn", 10);
            $trade->runningPl = $totalReturn;
            $winPercentage = bcdiv("$winTrades", "$totalTrades", 10);
            $trade->runningWinRate = bcmul("$winPercentage", "100", 10);

            // Save coordinates for creating p/l graphs. [X Coordinate, Y Coordinate, Label]
            $coordinates[] = [$totalTrades, $totalReturn, $trade->return];
        }
        $this->graphCoordinates = $coordinates;
    }

}

?>