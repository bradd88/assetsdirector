<?php

/** Creates a list of trades from supplied trades and transactions. */
class TradeList
{
    public float $totalReturn;
    public int $tradeCount;
    public float $winRate;
    private array $trades;
    private Trade $openTrade;
    private TradeFactory $tradeFactory;

    public function __construct(TradeFactory $tradeFactory)
    {
        $this->tradeFactory = $tradeFactory;
        $this->trades = array();
        $this->openTrade = $this->tradeFactory->create();
    }

    /** Add an already completed trade to the list. */
    public function addTrade(Trade $trade): void
    {
        $this->trades[] = $trade;
    }

    /**
     * Return the array of trades.
     * @return Trade[]
     */
    public function getTrades(): iterable
    {
        return $this->trades;
    }

    /** Sort trades. */
    public function sortTrades(): void
    {
        usort($this->trades, [$this, "sortComparison"]);
    }

    /** Add running statistics to each trade, and summary properties to the list object. */
    public function addStatistics(): void
    {
        $runningTotalCount = 0;
        $runningWinCount = 0;
        $runningReturn = 0;
        foreach ($this->trades as $trade) {
            $runningTotalCount++;
            $runningWinCount = ($trade->return > 0) ? bcadd($runningWinCount, 1, 0) : $runningWinCount;
            $runningWinRate = bcdiv($runningWinCount, $runningTotalCount, 4);
            $trade->runningWinRate = bcmul($runningWinRate, 100, 2);
            $runningReturn = bcadd($runningReturn, $trade->return, 2);
            $trade->runningReturn = $runningReturn;
        }
        $this->totalReturn = $this->trades[array_key_last($this->trades)]->runningReturn;
        $this->winRate = $this->trades[array_key_last($this->trades)]->runningWinRate;
        $this->tradeCount = count($this->trades);
    }

    /** Create graph coordinates using the trade close time and return as the x and y coordinates. */
    public function generateGraphData(): array
    {
        $coordArray = array();
        foreach ($this->getTrades() as $trade) {
            $dayNumberOfYear = new DateTime('@' . $trade->closeTimestamp);
            $dayOfYear = $dayNumberOfYear->format('z') + 1;
            $coordArray[] = array($dayOfYear, $trade->runningReturn);
        }
        return $coordArray;
    }

    /** Comparison function for quicksort. Sorts descending by closeing timestamp */
    private function sortComparison(Trade $a, Trade $b): int
    {
        $output = $a->closeTimestamp <=> $b->closeTimestamp;
        if ($output == 0) {
            $output = $a->openTimestamp <=> $b->openTimestamp;
        }
        return $output;
    }

}

?>