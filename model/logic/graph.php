<?php 

/** Creates line graph data that can be used to dynamically draw a graph. */
class Graph
{
    public array $unplottedLines;
    public array $labels;
    public object $xAxis;
    public object $yAxis;

    public function __construct()
    {
    }

    /** Add a line to the graph. */
    public function addLine(string $label, string $color, array $coordinates): void
    {
        $this->unplottedLines[] = (object) array('label' => $label, 'color' => $color, 'coordinates' => $coordinates);
    }

    /** Add label affixes for the x or y axis */
    public function addLabel(string $axisName, string $affixType, string $affixString): void
    {
        $this->labels[] = (object) ['axis' => $axisName, 'type' => $affixType, 'string' => $affixString];
    }

    /** Generate the graph with the specified settings. */
    public function generate(int $maxGridCount): void
    {
        $values = $this->findMinMaxValues($this->unplottedLines);
        $this->xAxis = $this->createAxis($values['xAxisMin'], $values['xAxisMax'], $maxGridCount);
        $this->yAxis = $this->createAxis($values['yAxisMin'], $values['yAxisMax'], $maxGridCount);
        $this->applyLabelAffixes();
    }

    /** Find the minimum and maximum x and y values for an array of coordinates. */
    private function findMinMaxValues(array $lines): array
    {
        $minValueX = $lines[0]->coordinates[0][0];
        $maxValueX = $lines[0]->coordinates[0][0];
        $minValueY = $lines[0]->coordinates[0][1];
        $maxValueY = $lines[0]->coordinates[0][1];
        foreach ($lines as $line) {
            foreach ($line->coordinates as $coordinate) {
                if ($coordinate[0] > $maxValueX) {
                    $maxValueX = $coordinate[0];
                } elseif ($coordinate[0] < $minValueX) {
                    $minValueX = $coordinate[0];
                }
                if ($coordinate[1] > $maxValueY) {
                    $maxValueY = $coordinate[1];
                } elseif ($coordinate[1] < $minValueY) {
                    $minValueY = $coordinate[1];
                }
            }
        }
        return array('xAxisMin' => $minValueX, 'xAxisMax' => $maxValueX, 'yAxisMin' => $minValueY, 'yAxisMax' => $maxValueY);
    }

    /** Calculate values for an axis: start, stop, and grid increments. */
    private function createAxis(float $minimumValue, float $maximumValue, int $maxGridCount): object
    {
        $startValue = $this->closestReadableNumber('lte', $minimumValue);
        $valueRangeMinimum = bcsub("$maximumValue", "$startValue", 10);
        $gridValueIncrementMinimum = bcdiv("$valueRangeMinimum", "$maxGridCount", 10);
        $gridValueIncrement = $this->closestReadableNumber('gte', $gridValueIncrementMinimum);
        $gridCount = ceil(bcdiv("$valueRangeMinimum", "$gridValueIncrement", 10));
        $valueRange = bcmul("$gridCount", "$gridValueIncrement", 0);
        $stopValue = bcadd("$startValue", "$valueRange", 0);
        return (object) array(
            'gridValueIncrement' => (int) $gridValueIncrement,
            'gridCount' => (int) $gridCount,
            'startValue' => (int) $startValue,
            'stopValue' => (int) $stopValue,
            'valueRange' => (int) $valueRange,
            'labelPrefix' => '',
            'labelSuffix' => ''
        );
    }

    /** Add the supplied affix data to the object properties. */
    private function applyLabelAffixes(): void
    {
        if (isset($this->labels)) {
            foreach ($this->labels as $label) {
                if ($label->axis === 'x') {
                    if ($label->type === 'prefix') {
                        $this->xAxis->labelPrefix = $label->string;
                    }
                    if ($label->type === 'suffix') {
                        $this->xAxis->labelSuffix = $label->string;
                    }
                }
            }
            if ($label->axis === 'y') {
                if ($label->type === 'prefix') {
                    $this->yAxis->labelPrefix = $label->string;
                }
                if ($label->type === 'suffix') {
                    $this->yAxis->labelSuffix = $label->string;
                }
            }
        }
    }

    /**
     * Find the closest 'human readable number' that is (depending on the mode) greater than, less than, or equal to the specified value.
     * 'Human readable numbers' are defined by the equation: x = m*10^n, where n>=0 and m=[1,2,2.5,5].
     *
     * @param string $mode Valid modes are: gte, gt, lte, and lt.
     */
    function closestReadableNumber(string $mode, float $value): int
    {
        if ($value === 0.0) {
            return 0;
        }
        if ($value < 0) {
            $value = abs($value);
            $negative = TRUE;
        }
        $m = [1, 2, 2.5, 5];
        $n = 1;
        $lastX = 0;
        while(TRUE) {
            $exponent = (float) bcpow("10", "$n", 0);
            foreach ($m as $multiplier) {
                $x = (float) bcmul("$multiplier", "$exponent", 0);
                    if ($x === $value) {
                        if ($mode === 'gte' || $mode === 'lte') {
                            $output = (isset($negative)) ? bcmul(-1, $x, 0) : $x;
                        }
                        if (($mode === 'lt' && !isset($negative)) || ($mode === 'gt' && isset($negative))) {
                            $output = (isset($negative)) ? bcmul(-1, $lastX, 0) : $lastX;
                        }
                    }
                    if ($x > $value) {
                        $x = (isset($negative)) ? bcmul(-1, $x, 0) : $x;
                        $lastX = (isset($negative)) ? bcmul(-1, $lastX, 0) : $lastX;
                        $output = ($mode === 'gte' || $mode === 'gt') ? max([$x, $lastX]) : min([$x, $lastX]);
                    }
                    if (isset($output)) {
                        return (int) $output;
                    }
                $lastX = $x;
            }
            $n++;
        }
    }

}

?>