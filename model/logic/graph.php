<?php 

class Graph
{
    private array $unplottedLines;
    private array $labels;

    public int $width;
    public int $height;
    public bool $invertedYAxis;
    public object $xAxis;
    public object $yAxis;
    public object $origin;
    public array $lines;

    public function __construct()
    {
        
    }

    /** Add a line to the graph. */
    public function addLine(string $label, string $color, array $coordinates): void
    {
        $this->unplottedLines[] = (object) array('label' => $label, 'color' => $color, 'coordinates' => $coordinates);
    }

    public function addLabel(string $axisName, string $affixType, string $affixString): void
    {
        $this->labels[] = (object) ['axis' => $axisName, 'type' => $affixType, 'string' => $affixString];
    }

    /** Generate the graph with the specified settings. */
    public function generate(int $canvasWidth, int $canvasHeight, int $maxGridCount, array $margins, bool $invertYAxis): void
    {
        $this->width = $canvasWidth;
        $this->height = $canvasHeight;
        $this->invertedYAxis = $invertYAxis;
        $values = $this->findMinMaxValues($this->unplottedLines);
        $this->xAxis = $this->createAxis($values['xAxisMin'], $values['xAxisMax'], $canvasWidth, $maxGridCount, [$margins[3], $margins[1]], FALSE);
        $this->yAxis = $this->createAxis($values['yAxisMin'], $values['yAxisMax'], $canvasHeight, $maxGridCount, [$margins[2], $margins[0]], $this->invertedYAxis);
        $this->origin = $this->findOriginPosition();
        $this->applyLabelAffixes();
        $this->plotLines();
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

    /** Calculate pixel positions and values for an axis using canvas/margin pixel size: start, stop, and grid increments. */
    private function createAxis(float $minimumValue, float $maximumValue, int $pixelSize, int $maxGridCount, array $margins, bool $inverted): object
    {
        $marginTotal = bcadd($margins[0], $margins[1], 0);
        $startValue = $this->closestReadableNumber('lte', $minimumValue);
        $valueRangeMinimum = bcsub("$maximumValue", "$startValue", 10);
        $gridValueIncrementMinimum = bcdiv("$valueRangeMinimum", "$maxGridCount", 10);
        $gridValueIncrement = $this->closestReadableNumber('gte', $gridValueIncrementMinimum);
        $gridCount = ceil(bcdiv("$valueRangeMinimum", "$gridValueIncrement", 10));
        $valueRange = bcmul("$gridCount", "$gridValueIncrement", 0);
        $stopValue = bcadd("$startValue", "$valueRange", 0);
        $pixelSizeWithMargin = bcsub("$pixelSize", "$marginTotal", 0);
        $pixelToValueRatio = bcdiv("$pixelSizeWithMargin", $valueRange, 10);
        $gridPixelIncrement = bcmul("$pixelToValueRatio", "$gridValueIncrement", 10);
        $startPixel = $margins[0];
        $stopPixel = bcsub("$pixelSize", $margins[1], 0);
        if ($inverted === TRUE) {
            $startPixel = bcsub($pixelSize, $startPixel, 0);
            $stopPixel = bcsub($pixelSize, $stopPixel, 0);
        }
        return (object) array(
            'gridValueIncrement' => (int) $gridValueIncrement,
            'gridPixelIncrement' => (int) $gridPixelIncrement,
            'pixelToValueRatio' => (float) $pixelToValueRatio,
            'gridCount' => (int) $gridCount,
            'startValue' => (int) $startValue,
            'startPixel' => (int) $startPixel,
            'stopValue' => (int) $stopValue,
            'stopPixel' => (int) $stopPixel,
            'pixelSize' => (int) $pixelSize,
            'labelPrefix' => '',
            'labelSuffix' => ''
        );
    }

    private function findOriginPosition(): object
    {
        $x = $this->calculateValuePosition($this->xAxis, 0, FALSE);
        $y = $this->calculateValuePosition($this->yAxis, 0, $this->invertedYAxis);
        return (object) array('xPixel' => $x, 'yPixel' => $y);
    }


    /** Calculate pixel positions for point coordinates in all lines, and save the plotted lines. */
    private function plotLines(): void
    {
        foreach ($this->unplottedLines as $unplottedLine) {
            $points = array();
            foreach ($unplottedLine->coordinates as $coordinate) {
                $points[] = (object) array(
                    'xPos' => $this->calculateValuePosition($this->xAxis, $coordinate[0], FALSE),
                    'xValue' => $coordinate[0],
                    'yPos' => $this->calculateValuePosition($this->yAxis, $coordinate[1], $this->invertedYAxis),
                    'yValue' => $coordinate[1]
                );
            }
            $this->lines[] = (object) array('label' => $unplottedLine->label, 'color' => $unplottedLine->color, 'points' => $points);
        }
    }

    /** Calculate the pixel position of a coordinate on an axis. */
    private function calculateValuePosition(object $axis, float $value, bool $inverted): int
    {
        $valueDistanceFromStart = bcsub($value, $axis->startValue, 0);
        $pixelDistanceFromStart = bcmul($valueDistanceFromStart, $axis->pixelToValueRatio, 0);
        if ($inverted === FALSE) {
            $output = bcadd($axis->startPixel, $pixelDistanceFromStart, 0);
        } else {
            $output = bcsub($axis->startPixel, $pixelDistanceFromStart, 0);
        }
        return (int) $output;
    }

    private function applyLabelAffixes(): void
    {
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