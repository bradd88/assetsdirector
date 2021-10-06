<?php

/**
 * Generate page containing a table of transactions.
 *
 * @param array $transactionList Array of transaction objects.
 * @return string Page HTML.
 */
function pageTransactions($transactionList) {
    // Create table and header
    $output = '
    <div class="table">
    <div class="tr">
    <div class="td">Date</div>
    <div class="td">ID</div>
    <div class="td">Description</div>
    <div class="td">Type</div>
    <div class="td">Symbol</div>
    <div class="td">Instruction</div>
    <div class="td">Amount</div>
    <div class="td">Price</div>
    <div class="td">Fees</div>
    <div class="td">Cost</div>
    <div class="td">Outstanding</div>
    </div>
    ';

    // Create a table row for each transaction.
    foreach ($transactionList as $transaction) {
        if ($transaction->error == 'true') {
            $output .= '<div class="tr error">';
        } else {
            $output .= '<div class="tr">';
        }
        $output .= '
        <div class="td">' . $transaction->transactionDate . '</div>
        <div class="td">' . $transaction->transactionId . '</div>
        <div class="td">' . $transaction->description . '</div>
        <div class="td">' . $transaction->transactionSubType . '</div>
        <div class="td">' . $transaction->symbol . '</div>
        <div class="td">' . $transaction->instruction . '</div>
        <div class="td">' . number_format($transaction->amount, 0, '.', ',') . '</div>
        <div class="td">' . number_format($transaction->price, 2, '.', ',') . '</div>
        <div class="td">' . number_format($transaction->secFee, 2, '.', ',') . '</div>
        <div class="td">' . number_format($transaction->netAmount, 2, '.', ',') . '</div>
        <div class="td">' . number_format($transaction->outstanding, 0, '.', ',') . '</div>
        </div>
        ';
    }

    // Create footer and table end.
    $output .= '
    <div class="tr">
    <div class="td">-</div>
    <div class="td">-</div>
    <div class="td">-</div>
    <div class="td">-</div>
    <div class="td">-</div>
    <div class="td">-</div>
    <div class="td">-</div>
    <div class="td">-</div>
    <div class="td">-</div>
    <div class="td">-</div>
    <div class="td">' . $transaction->outstanding . '</div>
    </div>
    </div>
    ';

    return $output;
}

?>