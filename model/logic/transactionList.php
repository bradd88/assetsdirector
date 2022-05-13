<?php 


/** Stores and proccesses a list of transactions from an array. */
class TransactionList
{
    public array $transactions;

    public function __construct()
    {
    }

    public function addTransaction(Transaction $transaction): void
    {
        $this->transactions[] = $transaction;
    }

    /**
     * Return the list of transaction objects.
     * @return Transaction[]
     */
    public function getTransactions(): iterable
    {
        return $this->transactions;
    }

    public function sortTransactions(): void
    {
        if (count($this->transactions) > 0) {
            usort($this->transactions, [$this, "sortComparison"]);
        }
    }

    /** Comparison function for quicksort. Sorts descending by order ID, then by transaction ID. */
    private function sortComparison(Transaction $a, Transaction $b): int
    {
        $output = $a->orderId <=> $b->orderId;
        if ($output == 0) {
            $output = $a->transactionId <=> $b->transactionId;
        }
        return $output;
    }

}

?>