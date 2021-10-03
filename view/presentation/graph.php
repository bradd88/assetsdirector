<?php 

function presentationGraph($graph) {
    // Create the canvas html and pass the graph data to a JS function to handle the drawing.
    $rootDir = $GLOBALS['config']['application']['root'];
    $graphJs = file_get_contents($rootDir . '/view/presentation/graph.js');
    $output = '
        <canvas id="graph" width="' . $graph->width . '" height="' . $graph->height . '" style="border:1px solid #d3d3d3;"> Unsupported browser.</canvas>
        <script type="text/javascript">
        let graphData = ' . json_encode($graph) . ';
        ' . $graphJs . '
        drawGraph(graphData);
        </script>
    ';    
    return $output;
}

?>