function drawGraph(graph) { 

    console.log(graph);
    let canvas = document.getElementById("graph");
    let context = canvas.getContext("2d");

    // Draw a border around the graph
    let graphWidth = graph.xAxis.stopPixel - graph.xAxis.startPixel;
    let graphHeight = graph.yAxis.stopPixel - graph.yAxis.startPixel;
    context.rect(graph.xAxis.startPixel, graph.yAxis.startPixel, graphWidth, graphHeight);
    context.lineWidth = 2;
    context.fillStyle = "white";
    context.fill();
    context.strokeStyle = "grey";
    context.stroke();

    // Draw the x and y axis lines
    context.lineWidth = 2;
    context.strokeStyle = "black";
    context.beginPath();
    context.moveTo(graph.xAxis.startPixel, graph.origin.yPixel);
    context.lineTo(graph.xAxis.stopPixel, graph.origin.yPixel);
    context.stroke();
    context.beginPath();
    context.moveTo(graph.origin.xPixel, graph.yAxis.startPixel);
    context.lineTo(graph.origin.xPixel, graph.yAxis.stopPixel);
    context.stroke();


    // Set gridline style
    context.setLineDash([5, 5]);
    context.lineWidth = 1;
    context.strokeStyle = "lightgrey";
    context.fillStyle = "grey";
    context.font = "12px Arial";
    context.textAlign = "left";

    // Draw vertical gridlines and labels
    gridCount = 0;
    currentPixel = graph.xAxis.startPixel;
    currentValue = graph.xAxis.startValue;
    while (gridCount < graph.xAxis.gridCount) {
        // Draw the gridline
        context.beginPath();
        context.moveTo(currentPixel, graph.yAxis.startPixel);
        context.lineTo(currentPixel, graph.yAxis.stopPixel);
        context.stroke();
        // Add the rotated label.
        context.save();
        context.translate(currentPixel, graph.yAxis.startPixel);
        context.rotate(Math.PI/3);
        context.textAlign = 'left';
        let labelText = graph.xAxis.labelPrefix + currentValue + graph.xAxis.labelSuffix;
        context.fillText(labelText, 5, 8);
        context.restore();
        // Move to the next gridline
        gridCount += 1;
        currentPixel += graph.xAxis.gridPixelIncrement;
        currentValue += graph.xAxis.gridValueIncrement;
    }

    // Draw horizontal gridlines and labels
    gridCount = 0;
    currentPixel = graph.yAxis.startPixel;
    currentValue = graph.yAxis.startValue;
    while (gridCount < graph.yAxis.gridCount) {
        // Draw the gridline
        context.beginPath();
        context.moveTo(graph.xAxis.startPixel, currentPixel);
        context.lineTo(graph.xAxis.stopPixel, currentPixel);
        context.stroke();
        // Add the label
        context.textAlign = 'right';
        let labelText = graph.yAxis.labelPrefix + currentValue + graph.yAxis.labelSuffix;
        context.fillText(labelText, graph.xAxis.startPixel - 5, currentPixel + 4);
        // Move to the next gridline
        gridCount += 1;
        currentPixel -= graph.yAxis.gridPixelIncrement;
        currentValue += graph.yAxis.gridValueIncrement;
    }

    // Draw lines
    context.font = "16px Arial";
    context.textAlign = "left";
    context.fillStyle = "black";
    context.lineCap = "square";
    context.lineJoin = "bevel";
    context.setLineDash([]);
    context.lineWidth = 2;
    for (line of graph.lines) {
        context.strokeStyle = line.color;
        context.beginPath();
        context.moveTo(graph.origin.xPixel, graph.origin.yPixel);
        for (point of line.points) {
            context.lineTo(point.xPos, point.yPos);
            context.moveTo(point.xPos, point.yPos);
        }
        context.stroke();
    }

} 