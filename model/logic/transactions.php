<?php 

/**
 * Filter and sort transactions.
 *
 * @param array $transactions Array of transaction objects.
 * @param string $transactionType 'ALL' or specific type of transaction to keep.
 * @param string $assetType 'ALL' or specific type of asset to keep.
 * @param string $symbol 'ALL' or specific symbol of underlying asset to keep.
 * @return array Filtered and sorted array of transaction objects.
 */
function filterTransactions($transactions, $transactionType, $assetType, $symbol) {
    foreach ($transactions as $key => $transaction) {
        if (($transactionType != $transaction->type && $transactionType != 'ALL') || ($assetType != $transaction->assetType && $assetType != 'ALL') || ($symbol != $transaction->symbol && $symbol != 'ALL')) {
            unset($transactions[$key]);
        }
    }
    usort($transactions, "sortTransactions");
    return $transactions;
}

// Comparison function for quicksort. Sorts descending by transaction time, then by transaction id.
function sortTransactions($a, $b) {
    $output = strtotime($a->transactionDate) <=> strtotime($b->transactionDate);
    if ($output == 0) {
        $output = $b->transactionId <=> $a->transactionId;
    }
    return $output;
}

function calculateOutstanding($transactions) {

    $totalOutstanding = 0;
    foreach ($transactions as $transaction) {
        // Check outstanding shares to see if the transaction type is possible, and Flag problem transactions.
        $transaction->error = 'false';
        if (
            ($transaction->transactionSubType == 'BY' && $totalOutstanding < 0) ||
            ($transaction->transactionSubType == 'SL' && $totalOutstanding <= 0) ||
            ($transaction->transactionSubType == 'SS' && $totalOutstanding > 0) ||
            ($transaction->transactionSubType == 'CS' && $totalOutstanding >= 0)
        ) {
            $transaction->error = 'true';
        }

        // Calculate running Outstanding shares.
        if ($transaction->instruction == 'BUY') {
            $totalOutstanding = bcadd("$totalOutstanding", "$transaction->amount", 0);
        } elseif ($transaction->instruction == 'SELL') {
            $totalOutstanding = bcsub("$totalOutstanding", "$transaction->amount", 0);
        }
        $transaction->outstanding = $totalOutstanding;
    }
    
    return $transactions;
}

?>