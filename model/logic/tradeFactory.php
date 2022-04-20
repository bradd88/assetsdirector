<?php

class TradeFactory
{
    public function __construct()
    {
    }
    public function create(): Trade
    {
        return new Trade();
    }
}

?>