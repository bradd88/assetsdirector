<?php

/** Provides information and methods relating to the trade calendar. */
class Calendar
{

    /** Find the date of the first trading day of the calendar year (the first Monday in January). */
    public function firstTradeDay(string $year): string
    {
        $date = new DateTime($year . '-01-01');
        $date->modify('first monday of this month');
        return $date->format('Y-m-d');
    }

    /** Find the trade year (not calendar year) that a date is in. */
    public function tradeYear(string $date): int
    {
        $date = new DateTime($date);
        $year = $date->format('Y');
        $firstTradeDayOfCalendarYear = $this->firstTradeDay($year);
        $tradeYear = ($date < $firstTradeDayOfCalendarYear) ? bcsub($year, 1, 0) : $year;
        return $tradeYear;
    }

    /** Find the week number of the trade year that a given date falls in. */
    public function tradeWeekNumber(string $date): int
    {
        $date = new DateTime($date);
        $tradeYear = $this->tradeYear($date->format('Y-m-d'));
        $firstTradeDay = new DateTime($this->firstTradeDay($tradeYear));
        $daysDiff = $firstTradeDay->diff($date)->format('%a');
        $dayNumber = bcadd($daysDiff, 1, 0);
        $weekNumber = ceil(bcdiv($dayNumber, 7, 1));
        return $weekNumber;
    }

    public function tradeWeekStart(string $weekNumber, string $year): string
    {
        $firstTradeDayOfYear = new DateTime($this->firstTradeDay($year));
        $weeksFromStart = bcsub($weekNumber, 1, 0);
        $daysToWeek = new DateInterval('P' . bcmul($weeksFromStart, 7, 0) . 'D');
        $weekStartDate = $firstTradeDayOfYear->add($daysToWeek);
        return $weekStartDate->format('Y-m-d');
    }

    public function tradeWeekStop(string $startDate): string
    {
        $startDate = new DateTime($startDate);
        $sixDays = new DateInterval('P6D');
        $weekStopDate = $startDate->add($sixDays);
        return $weekStopDate->format('Y-m-d');
    }

    /** Convert seconds into a human readable format. */
    public static function calculateTimespan(int $timespan, int $intervalCount): string
    {
        $intervalLength = array(
            "year" => 31557600,
            "month" => 2629800,
            "day" => 86400,
            "hour" => 3600,
            "minute" => 60
        );
        $intervalQuantity = array(
            "year" => floor($timespan/$intervalLength['year']),
            "month" => floor(($timespan%$intervalLength['year'])/$intervalLength['month']),
            "day" => floor(($timespan%$intervalLength['month'])/$intervalLength['day']),
            "hour" => floor(($timespan%$intervalLength['day'])/$intervalLength['hour']),
            "minute" => floor(($timespan%$intervalLength['hour'])/$intervalLength['minute']),
            "second" => floor($timespan%$intervalLength['minute']),
        );

        $output = '';
        $outputCounter = 0;
        foreach ($intervalQuantity as $intervalName => $intervalValue) {
            if ($outputCounter < $intervalCount && $intervalValue > 0) {
                $output .= $intervalValue . ' ' . $intervalName . (($intervalValue > 1) ? 's' : '');
                $outputCounter++;
                if ($outputCounter < $intervalCount && $intervalName != array_key_last($intervalQuantity)) {
                    $output .= ', ';
                }
            }
        }
        return $output;
    }
}

?>
