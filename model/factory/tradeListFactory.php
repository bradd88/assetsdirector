<?php

/** Creates trade lists */
class TradeListFactory
{
    private TradeFactory $tradeFactory;

    public function __construct(TradeFactory $tradeFactory)
    {
        $this->tradeFactory = $tradeFactory;
    }

    public function create(): TradeList
    {
        return new TradeList($this->tradeFactory);
    }
}

?>