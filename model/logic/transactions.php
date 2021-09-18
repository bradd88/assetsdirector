<?php 

/**
 * Filter transactions.
 *
 * @param array $transactions Array of transaction objects.
 * @param string $transactionType 'ALL' or specific type of transaction to keep.
 * @param string $assetType 'ALL' or specific type of asset to keep.
 * @param string $symbol 'ALL' or specific symbol of underlying asset to keep.
 * @return array Filtered array of transaction objects.
 */
function filterTransactions($transactions, $transactionType, $assetType, $symbol) {
    foreach ($transactions as $key => $transaction) {
        if (($transactionType != $transaction->type && $transactionType != 'ALL') || ($assetType != $transaction->assetType && $assetType != 'ALL') || ($symbol != $transaction->symbol && $symbol != 'ALL')) {
            unset($transactions[$key]);
        }
    }
    return $transactions;
}

?>