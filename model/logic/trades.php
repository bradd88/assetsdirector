<?php 

/**
 * Create a list of trades with P/L from a list of transactions.
 *
 * @param array $transactions Array of transaction objects.
 * @return Array Array of trade objects.
 */
function createTrades($transactions) {
    $longBuyList = [];
    $longSellList = [];
    $shortSellList = [];
    $shortBuyList = [];

    // Sort transactions into arrays based on transaction sub type.
    foreach ($transactions as $transaction) {
        switch ($transaction->transactionSubType) {
            case 'BY':
                $transaction->typeDescription = 'Long';
                $longBuyList[] = $transaction;
                break;
            case 'SL':
                $transaction->typeDescription = 'Long';
                $longSellList[] = $transaction;
                break;
            case 'SS':
                $transaction->typeDescription = 'Short';
                $shortSellList[] = $transaction;
                break;
            case 'CS':
                $transaction->typeDescription = 'Short';
                $shortBuyList[] = $transaction;
                break;
        }
    }

    // Calculate trades based on transactions being long or short.
    $longTrades = calculateTrades($longBuyList, $longSellList);
    $shortTrades = calculateTrades($shortBuyList, $shortSellList);
    $trades = array_merge($longTrades, $shortTrades);
    usort($trades, "sortTrades");

    return $trades;
}

/**
 * Calculate trades from buying and closing transactions.
 *
 * @param array $buyTransactions Array of buying transaction objects.
 * @param array $sellTransactions Array of closing transaction objects.
 * @return array Array of trade objects.
 */
function calculateTrades($buyTransactions, $sellTransactions) {
    $trades = [];

    // Loop through transactions until there aren't enough left to create a trade.
    while (sizeof($buyTransactions) > 0 && sizeof($sellTransactions) > 0) {
        $buy = $buyTransactions[0];
        $sell = $sellTransactions[0];
        $trade = new stdClass();

        // Calculate actual buy price per share. Only do this once, to avoid recursive calculations.
        if (!isset($buy->pricePer)) {
            $buyCost = abs($buy->cost);
            $buyNet = bcsub("$buyCost", "$buy->regFee", 10);
            $buy->pricePer = bcdiv("$buyNet", "$buy->amount", 10);
        }

        // Calculate actual sell price per share. Only do this once, to avoid recursive calculations.
        if (!isset($sell->pricePer)) {
            $sellCost = abs($sell->cost);
            $sellNet = bcsub("$sellCost", "$sell->regFee", 10);
            $sell->pricePer = bcdiv("$sellNet", "$sell->amount", 10);
        }

        // Calculate trade return and store trade information.
        $trade->symbol = $buy->symbol;
        $trade->type = $buy->typeDescription;
        $trade->quantity = min($buy->amount, $sell->amount);
        $trade->buyId = $buy->transactionId;
        $trade->sellId = $sell->transactionId;
        $trade->openTimestamp = min(strtotime($buy->transactionDate), strtotime($sell->transactionDate));
        $trade->closeTimestamp = max(strtotime($buy->transactionDate), strtotime($sell->transactionDate));
        $trade->length = calculateTimespan($trade->openTimestamp, $trade->closeTimestamp, 2);
        $trade->buyPricePer = $buy->pricePer;
        $trade->sellPricePer = $sell->pricePer;
        $trade->priceDiff = bcsub("$trade->sellPricePer", "$trade->buyPricePer", 10);
        $trade->return = bcmul("$trade->priceDiff", "$trade->quantity", 10);
        $trade->win = ($trade->return >= 0) ? 'true' : 'false';
        $trades[] = $trade;

        // Update buy transaction quantities.
        if ($buy->amount == $trade->quantity) {
            array_shift($buyTransactions);
        } else {
            $buy->amount -= $trade->quantity;
        }

        // Update sell transaction quantities.
        if ($sell->amount == $trade->quantity) {
            array_shift($sellTransactions);
        } else {
            $sell->amount -= $trade->quantity;
        }

    }

    return $trades;
}

// Comparison function for quicksort. Sorts descending by trade opening time, then by trade closing time.
function sortTrades($a, $b) {
    $output = $a->openTimestamp <=> $b->openTimestamp;
    if ($output == 0) {
        $output = $a->closeTimestamp <=> $b->closeTimestamp;
    }
    return $output;
}

function calculatePL($trades) {
    $totalTrades = 0;
    $totalReturn = 0;

    $winTrades = 0;
    $lossTrades = 0;

    foreach ($trades as $trade) {
        // Track returns and trades.
        if ($trade->return >= 0) {
            $winTrades++;
        } else {
            $lossTrades++;
        }
        $totalTrades++;

        // Keep a running total for trades.
        $totalReturn = bcadd("$trade->return", "$totalReturn", 10);
        $trade->runningPl = $totalReturn;
        $winPercentage = bcdiv("$winTrades", "$totalTrades", 10);
        $trade->runningWinRate = bcmul("$winPercentage", "100", 10);

    }

    return $trades;
}

// Create a list of graph coordinates for trade Profit and loss.
function calculatePLCoordinates($trades) {
    $totalReturn = 0;
    $coordinates = [];
    $y = 1;
    foreach ($trades as $trade) {
        $totalReturn = bcadd("$trade->return", "$totalReturn", 10);
        $trade->runningPl = $totalReturn;
        $coordinates[] = [$y, $totalReturn, $trade->return];
        $y++;
    }
    return $coordinates;
}

?>