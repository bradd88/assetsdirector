<?php 

function presentationGraph($graph) {

    // Create the canvas
    $output='
        <canvas id="graph" width="' . $graph->width . '" height="' . $graph->height . '" style="border:1px solid #d3d3d3;"> Unsupported browser.</canvas>
        <script>
        var canvas=document.getElementById("graph");
        var lineChart=canvas.getContext("2d");
    ';

    // Draw X axis gridlines and labels
    $output .= '
        lineChart.moveTo(' . $graph->xOrigin . ',' . $graph->yOrigin . ');
        lineChart.lineWidth=1;
        lineChart.strokeStyle="lightgrey";
        lineChart.setLineDash([5, 5]);
        lineChart.font="12px Arial";
        lineChart.textAlign="left";
        lineChart.fillStyle="grey";
        lineHeight=15;
    ';
    $currentPosition = $graph->xOrigin;
    $currentLabel = 0;
    while ($currentPosition < $graph->width) {
        $currentPosition += $graph->xGridPixelIncrement;
        $currentLabel += $graph->xGridValueIncrement;
        $output .= '
            lineChart.beginPath();
            lineChart.moveTo(' . $currentPosition . ',' . $graph->yOrigin . ');
            lineChart.lineTo(' . $currentPosition . ',0);
            lineChart.stroke();

            lineChart.save();
            lineChart.translate(' . $currentPosition . ',' . $graph->yOrigin . ');
            lineChart.rotate(Math.PI/3);
            lineChart.fillText("' . $currentLabel . '",5,lineHeight/2);
            lineChart.restore();
        ';
    }

    // Draw Y axis gridlines and labels
    $output .= '
        lineChart.moveTo(' . $graph->xOrigin . ',' . $graph->yOrigin . ');
        lineChart.lineWidth=1;
        lineChart.strokeStyle="lightgrey";
        lineChart.setLineDash([5, 5]);
        lineChart.font="12px Arial";
        lineChart.textAlign="right";
        lineChart.fillStyle="grey";
    ';
    $currentPosition = $graph->yOrigin;
    $currentLabel = $graph->yStart;
    while ($currentPosition > 0) {
        $currentPosition -= $graph->yGridPixelIncrement;
        $currentLabel += $graph->yGridValueIncrement;
        if ($currentLabel == 0) {
            $output .= '
            lineChart.strokeStyle="grey";
            lineChart.setLineDash([10, 10]);
            lineChart.fillStyle="black";
            ';
        }
        $output .= '
            lineChart.beginPath();
            lineChart.moveTo(' . $graph->xOrigin . ',' . $currentPosition . ');
            lineChart.lineTo(' . $graph->width . ',' . $currentPosition . ');
            lineChart.stroke();
            lineChart.fillText("' . $currentLabel . '",' . $graph->xOrigin - 5 . ',' . $currentPosition + 4 . ');
        ';
        if ($currentLabel == 0) {
            $output .= '
            lineChart.strokeStyle="lightgrey";
            lineChart.setLineDash([5, 5]);
            lineChart.fillStyle="grey";
            ';
        }
    }

    // Draw X and Y axis
    $output .= '
        lineChart.beginPath();
        lineChart.lineWidth=3;
        lineChart.strokeStyle="black";
        lineChart.setLineDash([]);
        lineChart.moveTo(' . $graph->xOrigin . ',' . $graph->yOrigin . ');
        lineChart.lineTo(' . $graph->width . ',' . $graph->yOrigin . ');
        lineChart.stroke();
        lineChart.beginPath();
        lineChart.moveTo(' . $graph->xOrigin . ',' . $graph->yOrigin . ');
        lineChart.lineTo(' . $graph->xOffset . ',0);
        lineChart.stroke();
    ';

    // Draw chart lines
    $output .= '
        lineChart.font="16px Arial";
        lineChart.textAlign="left";
        lineChart.fillStyle="black";
        lineChart.lineCap="square";
        lineChart.lineJoin="bevel";
        lineChart.strokeStyle="green";
        lineChart.setLineDash([]);
        lineChart.lineWidth=2;
        lineChart.beginPath();
        lineChart.moveTo(' . $graph->xOrigin . ',' . $graph->yOrigin . ');
    ';
    foreach ($graph->coordinates as $point) {
        $output .= '
            lineChart.lineTo(' . $point->xPosition . ',' . $point->yPosition . ');
            lineChart.moveTo(' . $point->xPosition . ',' . $point->yPosition . ');
        ';
        if (is_numeric($point->text)) {
            $output .= 'lineChart.fillText("' . number_format($point->text, 0, '.', ',') . '",' . $point->xPosition . ',' . $point->yPosition . ');';
        } else {
            $output .= 'lineChart.fillText("' . $point->text . '",' . $point->xPosition . ',' . $point->yPosition . ');';
        }
    }
    $output .= '
        lineChart.stroke();
    ';
    

    $output .= '</script>';
    return $output;
}

?>