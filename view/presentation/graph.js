function drawGraph(graph) { 
    // Setup the canvas and variables
    let canvas = document.getElementById("graph");
    let lineChart = canvas.getContext("2d");
    let lineHeight = 0;
    let currentPosition = 0;
    let currentLabel = 0;

    // Draw X axis gridlines and labels
    lineChart.moveTo(graph.xOrigin, graph.yOrigin);
    lineChart.lineWidth = 1;
    lineChart.strokeStyle = "lightgrey";
    lineChart.setLineDash([5, 5]);
    lineChart.font = "12px Arial";
    lineChart.textAlign = "left";
    lineChart.fillStyle = "grey";
    lineHeight = 15;
    currentPosition = graph.xOrigin;
    currentLabel = 0;
    while (currentPosition < graph.width) {
        currentPosition += Number(graph.xGridPixelIncrement);
        currentLabel += Number(graph.xGridValueIncrement);
        lineChart.beginPath();
        lineChart.moveTo(currentPosition, graph.yOrigin);
        lineChart.lineTo(currentPosition, 0);
        lineChart.stroke();
        lineChart.save();
        lineChart.translate(currentPosition, graph.yOrigin);
        lineChart.rotate(Math.PI/3);
        lineChart.fillText(currentLabel, 5, lineHeight/2);
        lineChart.restore();
    }

    // Draw Y axis gridlines and labels
    lineChart.moveTo(graph.xOrigin, graph.yOrigin);
    lineChart.lineWidth = 1;
    lineChart.strokeStyle = "lightgrey";
    lineChart.setLineDash([5, 5]);
    lineChart.font = "12px Arial";
    lineChart.textAlign = "right";
    lineChart.fillStyle = "grey";
    currentPosition = graph.yOrigin;
    currentLabel = Number(graph.yStart);
    while (currentPosition > 0) {
        currentPosition -= Number(graph.yGridPixelIncrement);
        currentLabel += Number(graph.yGridValueIncrement);
        if (currentLabel == 0) {
            lineChart.strokeStyle = "grey";
            lineChart.setLineDash([10, 10]);
            lineChart.fillStyle = "black";
        }
        lineChart.beginPath();
        lineChart.moveTo(graph.xOrigin, currentPosition);
        lineChart.lineTo(graph.width, currentPosition);
        lineChart.stroke();
        lineChart.fillText(currentLabel, graph.xOrigin - 5, currentPosition + 4);
        if (currentLabel == 0) {
            lineChart.strokeStyle = "lightgrey";
            lineChart.setLineDash([5, 5]);
            lineChart.fillStyle = "grey";
        }
    }

    // Draw X and Y axis
    lineChart.beginPath();
    lineChart.lineWidth = 3;
    lineChart.strokeStyle = "black";
    lineChart.setLineDash([]);
    lineChart.moveTo(graph.xOrigin, graph.yOrigin);
    lineChart.lineTo(graph.width, graph.yOrigin);
    lineChart.stroke();
    lineChart.beginPath();
    lineChart.moveTo(graph.xOrigin, graph.yOrigin);
    lineChart.lineTo(graph.xOffset, 0);
    lineChart.stroke();

    // Draw chart lines
    lineChart.font = "16px Arial";
    lineChart.textAlign = "left";
    lineChart.fillStyle = "black";
    lineChart.lineCap = "square";
    lineChart.lineJoin = "bevel";
    lineChart.strokeStyle = "green";
    lineChart.setLineDash([]);
    lineChart.lineWidth = 2;
    lineChart.beginPath();
    lineChart.moveTo(graph.xOrigin, graph.yOrigin);
    for (point of graph.coordinates) {
        lineChart.lineTo(point.xPosition, point.yPosition);
        lineChart.moveTo(point.xPosition, point.yPosition);
    }
    lineChart.stroke();
} 