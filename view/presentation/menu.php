<?php 

function presentationMenu() {
    $output = '
        <div id="navwrapper">
        <div id="nav" class="content">
        <div class="table">
        <a class="tr link" href="./home">Home</a>
        <a class="tr link" href="./transactions">Transactions</a>
        <a class="tr link" href="./trades">Trades</a>
        <a class="tr link" href="./summary">Summary</a>
        <a class="tr link" href="./logout">Logout</a>
        </div>
        </div>
        </div>
    ';
    return $output;
}

?>