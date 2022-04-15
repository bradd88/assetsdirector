<?php 


/** Stores and proccesses a list of transactions from an array. */
class TransactionList
{
    public array $transactions;
    public int $outstandingAssets;
    private TransactionFactory $transactionFactory;

    public function __construct(TransactionFactory $transactionFactory)
    {
        $this->transactionFactory = $transactionFactory;
        $this->outstandingAssets = 0;
    }

    /**
     * Create and return the list of transaction objects.
     * @return Transaction[]
     */
    public function create(array $array): iterable
    {
        foreach ($array as $item) {
            $transaction = $this->transactionFactory->cast($item);
            $this->transactions[] = $transaction;
        }
        if (count($this->transactions) > 0) {
            usort($this->transactions, [$this, "sort"]);
        }
        return $this->transactions;
    }

    /** Comparison function for quicksort. Sorts descending by order ID, then by transaction ID. */
    private function sort(Transaction $a, Transaction $b): int
    {
        $output = $a->orderId <=> $b->orderId;
        if ($output == 0) {
            $output = $a->transactionId <=> $b->transactionId;
        }
        return $output;
    }

}

?>