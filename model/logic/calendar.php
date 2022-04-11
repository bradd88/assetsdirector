<?php

/** Provides information and methods relating to the trade calendar. */
class Calendar
{

    /** Find the date of the first trading day of the calendar year (the first Monday in January). */
    public function firstTradeDay(string $year): DateTime
    {
        $date = new DateTime($year . '-01-01');
        $date->modify('first monday of this month');
        return $date;
    }

    /** Find the trade year (not calendar year) that a date is in. */
    public function tradeYear(DateTime $date = NULL): string
    {
        $date = $date ?? new DateTime('today');
        $year = $date->format('Y');
        $firstTradeDayOfCalendarYear = $this->firstTradeDay($year);
        $tradeYear = ($date < $firstTradeDayOfCalendarYear) ? bcsub($year, 1, 0) : $year;
        return $tradeYear;
    }

    /** Find the week number of the trade year that a given date falls in. */
    public function tradeWeekNumber(DateTime $date = NULL): string
    {
        $date = $date ?? new DateTime('today');
        $tradeYear = $this->tradeYear($date);
        $firstTradeDay = $this->firstTradeDay($tradeYear);
        $days = $firstTradeDay->diff($date)->format('%R%a');
        $weekNumber = ceil(bcdiv($days, 7, 1));
        return $weekNumber;
    }

    /** Find the start and end dates for a given trade week. */
    public function tradeWeekStartStop(string $weekNumber = NULL, string $year = NULL): array
    {
        $weekNumber = $weekNumber ?? $this->tradeWeekNumber();
        $year = $year ?? $this->tradeYear();
        $weeksFromStart = bcsub($weekNumber, 1, 0);
        $daysToWeek = bcmul($weeksFromStart, 7, 0);
        $daysToWeekStartInterval = new DateInterval('P' . $daysToWeek . 'D');
        $weekStartDate = $this->firstTradeDay($year)->add($daysToWeekStartInterval);
        $daysToWeekEndInterval = new DateInterval('P6D');
        $weekEndDate = $this->firstTradeDay($year)->add($daysToWeekStartInterval)->add($daysToWeekEndInterval);
        return array('start' => $weekStartDate->format('Y-m-d'), 'stop' => $weekEndDate->format('Y-m-d'));
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