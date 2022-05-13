<?php 

/** Calculates and stores trade data. */
class Trade {

    public string $symbol;
    public string $assetType;
    public string $strategy;
    public string $openTimestamp;
    public string $closeTimestamp;
    public int $lengthSeconds;
    public float $return;
    public int $quantity;
    public array $orderIds;
    public array $transactionIds;
    public object $buy;
    public object $sell;
    public string $status;

    private string $longOrShort;
    private int $outstanding;
    private array $buyTransactions;
    private array $sellTransactions;

    public function __construct()
    {
        $this->status = 'incomplete';
        $this->orderIds = array();
        $this->transactionIds = array();
        $this->outstanding = 0;
    }

    /** Add a new transaction to the trade. Once transactions have been added and the outstanding assets reaches zero, the trade will be proccessed. */
    public function addTransaction(Transaction $transaction): bool
    {
        $validation = $this->validate($transaction);
        if ($validation === FALSE) {
            return FALSE;
        }
        $this->processTransaction($transaction);
        if ($this->outstanding === 0) {
            $this->createSummary();
            $this->status = 'complete';
        }
            return TRUE;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    /** Determine if the transaction can be added to the trade without issues. */
    private function validate($transaction): bool
    {
        $this->symbol = $this->symbol ?? $transaction->symbol;
        if ($transaction->symbol !== $this->symbol) {
            $errorText = 'Transaction symbol doesn\'t match trade symbol.';
        }
        $this->assetType = $this->assetType ?? $transaction->assetType;
        if ($transaction->assetType !== $this->assetType) {
            $errorText = 'Transaction asset type doesn\'t match trade asset type.';
        }
        $transactionAlreadyAdded = in_array($transaction->transactionId, $this->transactionIds);
        if ($transactionAlreadyAdded) {
            $errorText = 'Transaction has already been added to the trade.';
        }
        if (!isset($this->longOrShort)) {
            if ($transaction->transactionSubType === 'BY') {
                $this->longOrShort = 'Long';
            } elseif ($transaction->transactionSubType === 'SS') {
                $this->longOrShort = 'Short';
            } elseif ($transaction->transactionSubType === 'SL') {
                $errorText = 'Attempting to close long on a trade with no open position.';
            } elseif ($transaction->transactionSubType === 'CS') {
                $errorText = 'Attempting to close short on a trade with no open position.';
            }
        } else {
            if ($this->longOrShort === 'Long' && $transaction->transactionSubType === 'SS') {
                $errorText = 'Attempting to open short a long position.';
            } elseif ($this->longOrShort === 'Long' && $transaction->transactionSubType === 'CS') {
                $errorText = 'Attempting to close short on a long positon.';
            } elseif ($this->longOrShort === 'Short' && $transaction->transactionSubType === 'BY') {
                $errorText = 'Attempting to open long on a short position.';
            } elseif ($this->longOrShort === 'Short' && $transaction->transactionSubType === 'SL') {
                $errorText = 'Attempting to close long on a short position.';
            }
        }
        if (isset($errorText)) {
            $errorText .= ' Transaction ID: ' . $transaction->transactionId;
            trigger_error($errorText, E_USER_WARNING);
            return FALSE;
        }
        return TRUE;
    }

    /** Add a new transaction and recalculate the trade. */
    private function processTransaction($transaction): void
    {
        $this->transactionIds[] = $transaction->transactionId;
        if (!in_array($transaction->orderId, $this->orderIds)) {
            $this->orderIds[] = $transaction->orderId;
        }

        $timestamp = strtotime($transaction->transactionDate);
        $this->openTimestamp = (isset($this->openTimestamp)) ? min($timestamp, $this->openTimestamp) : $timestamp;
        $this->closeTimestamp = (isset($this->closeTimestamp)) ? max($timestamp, $this->closeTimestamp) : $timestamp;

        if ($transaction->transactionSubType === 'BY' || $transaction->transactionSubType === 'CS') {
            $this->outstanding = bcadd("$this->outstanding", "$transaction->amount", 0);
            $this->buyTransactions[] = $transaction;
        } elseif ($transaction->transactionSubType === 'SS' || $transaction->transactionSubType === 'SL') {
            $this->outstanding = bcsub("$this->outstanding", "$transaction->amount", 0);
            $this->sellTransactions[] = $transaction;
        }
    }

    /** Create a trade summary that includes return, trade length, and strategy. */
    private function createSummary(): void
    {
        $this->buy = $this->calculateLeg($this->buyTransactions);
        $this->sell = $this->calculateLeg($this->sellTransactions);
        $this->quantity = $this->buy->amount;
        $this->return = bcadd($this->sell->netCost, $this->buy->netCost, 2);
        $this->lengthSeconds = bcsub("$this->closeTimestamp", "$this->openTimestamp", 0);
        $this->strategy = $this->longOrShort;
        $this->strategy .= (count($this->orderIds) > 2) ? 'Scaled ' : '';
        $this->strategy .= ($this->lengthSeconds <= 3600) ? 'Scalp' : 'Swing';
    }

    /** Calculate the total cost, including fees, for the buy or sell leg of a trade. */
    private function calculateLeg($transactions): object
    {
        $leg = new stdClass;
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

?>