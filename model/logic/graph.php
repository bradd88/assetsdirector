<?php 

/**
 * Find the best common interval for a given value.
 * For positive values: The best common interval is defined as the smallest integer (equal to 1\*10^n or 2\*10^n or 5\*10^n, where n > 0) that is larger than the given value.
 * For negative values: The best common interval is defined as the largest integer (equal to -1\*10^n or -2\*10^n or or -5\*10^n, where n > 0.) that is smaller than the given value.
 *
 * @param int $value
 * @return int
 */
function findCommonInterval($value) {
    $multipliers = [1, 2, 5];
    $n = 1;
    while (TRUE) {
        if ($value == 0) {
            return 0;
        }
        foreach ($multipliers as $multiplier) {
            $power = bcpow("10", "$n", 10);
            $interval = bcmul("$multiplier", "$power", 10);
            if ($value < 0) {
                $interval = bcmul("$interval", "-1", 10);
                if ($value > $interval) {
                    return $interval;
                }
            } elseif ($value < $interval) {
                return $interval;
            }
        }
        $n++;
    }
}

function configureGraph($coordinates, $canvasWidth, $canvasHeight, $xOffset = NULL, $yOffset = NULL) {

    // Create the graph settings object and set the dimensions.
    $graph = new stdClass;
    $graph->width = $canvasWidth;
    $graph->height = $canvasHeight;
    $graph->xOffset = $xOffset ?? 50;
    $graph->yOffset = $yOffset ?? 50;
    $graph->xOrigin = $graph->xOffset;
    $graph->yOrigin = $graph->height - $graph->yOffset;

    // Find the min and max values for x/y coordinates.
    $maxXValue = 0;
    $maxYValue = 0;
    $minYValue = 0;
    foreach ($coordinates as $coordinate) {
        $maxXValue = ($coordinate[0] > $maxXValue) ? $coordinate[0] : $maxXValue;
        $maxYValue = ($coordinate[1] > $maxYValue) ? $coordinate[1] : $maxYValue;
        $minYValue = ($coordinate[1] < $minYValue) ? $coordinate[1] : $minYValue;
    }

    // Find the best value to start the x and y axis labels.
    $graph->yStart = findCommonInterval($minYValue);
    $graph->xStart = 0;

    // Find the pixel increment for gridlines, rounded up to the nearest common interval, that results in the number of grid lines being as close as possible to max.
    $maxGridLines = 20;
    $heightRange = bcsub("$maxYValue", "$graph->yStart", 10);
    $widthRange = bcsub("$maxXValue", "$graph->xStart", 10);
    $yGridExactIncrement = bcdiv("$heightRange", "$maxGridLines", 10);
    $xGridExactIncrement = bcdiv("$widthRange", "$maxGridLines", 10);
    $yGridInterval = findCommonInterval($yGridExactIncrement);
    $xGridInterval = findCommonInterval($xGridExactIncrement);

    // Calculate the graph height and width based on the number of grids needed to cover the viewable area.
    $viewableHeight = bcmul("$heightRange", "1.1", 10);
    $yGridsRequired = ceil(bcdiv("$viewableHeight", "$yGridInterval", 10));
    $graphHeight = bcmul("$yGridsRequired", "$yGridInterval", 10);
    $viewableWidth = bcmul("$widthRange", "1.1", 10);
    $xGridsRequired = ceil(bcdiv("$viewableWidth", "$xGridInterval", 10));
    $graphWidth = bcmul("$xGridsRequired", "$xGridInterval", 10);

    // Calculate the pixel increment of gridlines when the graph is scaled up to the canvas size, and save the value each grid represents.
    $yGridPercentIncrement = bcdiv("$yGridInterval", "$graphHeight", 10);
    $graph->yGridPixelIncrement = bcmul("$yGridPercentIncrement", "$canvasHeight", 10);
    $graph->yGridValueIncrement = $yGridInterval;
    $xGridPercentIncrement = bcdiv("$xGridInterval", "$graphWidth", 10);
    $graph->xGridPixelIncrement = bcmul("$xGridPercentIncrement", "$canvasWidth", 10);
    $graph->xGridValueIncrement = $xGridInterval;

    // Calculate point coordinates when the graph is scaled to the size of the canvas.
    foreach ($coordinates as $coordinate) {
        // Create a new point object, and save information.
        $point = new stdClass;
        $point->xValue = $coordinate[0];
        $point->yValue = $coordinate[1];
        $point->text = $coordinate[2];

        // Adjust the coordinate y values for a non zero start.
        $point->yValue = bcsub("$point->yValue", "$graph->yStart", 10);

        // Calculate the point coordinates when the graph is scaled to the canvas size.
        $xPercent = bcdiv("$point->xValue", "$graphWidth", 10);
        $point->xPosition = bcmul("$xPercent", "$canvasWidth", 10);
        $yPercent = bcdiv("$point->yValue", "$graphHeight", 10);
        $point->yPosition = bcmul("$yPercent", "$canvasHeight", 10);

        // Apply offsets, then invert Y so the origin is the bottom left.
        $point->xPosition = bcadd("$point->xPosition", "$graph->xOffset", 10);
        $point->yPosition = bcadd("$point->yPosition", "$graph->yOffset", 10);
        $point->yPosition = bcsub("$graph->height", "$point->yPosition", 10);

        // Add the new point to the graph coordinates array.
        $graph->coordinates[] = $point;
    }

    return $graph;
}

?>