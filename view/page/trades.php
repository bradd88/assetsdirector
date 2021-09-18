<?php

// Generate page containing a table with trade data, and return it as an array.
function pageTrades($trades) {
    $output = '';
    
    // Collumn titles.
    $output .= '
        <div class="table">
        <div class="tr">
        <div class="td">Start</div>
        <div class="td">Stop</div>
        <div class="td">Symbol</div>
        <div class="td">Type</div>
        <div class="td">Buy</div>
        <div class="td">Sell</div>
        <div class="td">Quantity</div>
        <div class="td">Length</div>
        <div class="td">Return</div>
        <div class="td">Total P/L</div>
        </div>
    ';
    
    // Trade rows. Keep a running total of Return
    foreach ($trades as $trade) {
        $output .= '
            <div class="tr">
            <div class="td">' . date('Y/m/d H:i:s', $trade->openTimestamp) . '</div>
            <div class="td">' . date('Y/m/d H:i:s', $trade->closeTimestamp) . '</div>
            <div class="td">' . $trade->symbol . '</div>
            <div class="td">' . $trade->type . '</div>
            <div class="td">' . number_format(abs($trade->buyPricePer), 2, '.', ',') . '</div>
            <div class="td">' . number_format(abs($trade->sellPricePer), 2, '.', ',') . '</div>
            <div class="td">' . number_format($trade->quantity, 0, '.', ',') . '</div>
            <div class="td">' . $trade->length . '</div>
            <div class="td">' . number_format($trade->return, 2, '.', ',') . '</div>
            <div class="td">' . number_format($trade->runningPl, 2, '.', ',') . '</div>
            </div>
        ';
    }
    
    // Bottom line totals.
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
        </div>
        </div>
    ';
    
    return $output;
}

?>