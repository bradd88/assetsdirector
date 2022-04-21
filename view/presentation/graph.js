class graph
{
    constructor(graphData)
    {
        this.canvas = document.getElementById("graph");
        this.context = this.canvas.getContext("2d");
        this.graphData = graphData;
    }

    draw(canvasWidth, canvasHeight)
    {
        // Calculate graph dimensions based on canvas size.
        let invertedY = true;
        this.calculateAxisPixelData(this.graphData.xAxis, canvasWidth, [100, 100], false);
        this.calculateAxisPixelData(this.graphData.yAxis, canvasHeight, [100, 100], invertedY);
        this.graphData.xAxis.gridLines = this.calculateGridLines(this.graphData.xAxis, false);
        this.graphData.yAxis.gridLines = this.calculateGridLines(this.graphData.yAxis, true);
        this.graphData.lines = this.calculateLines(this.graphData.unplottedLines, invertedY);
        // Draw the graph.
        this.drawbackground('white');
        this.drawGridlines('x', this.graphData.xAxis, this.graphData.yAxis.startPixel, this.graphData.yAxis.stopPixel);
        this.drawGridlines('y', this.graphData.yAxis, this.graphData.xAxis.startPixel, this.graphData.xAxis.stopPixel);
        for (let line of this.graphData.lines) {
            this.drawLine(line);
        }
        this.drawBorder('black', 1);
    }

    drawGraphAreaRect()
    {
        let width = this.graphData.xAxis.stopPixel - this.graphData.xAxis.startPixel;
        let height = this.graphData.yAxis.stopPixel - this.graphData.yAxis.startPixel;
        this.context.rect(this.graphData.xAxis.startPixel, this.graphData.yAxis.startPixel, width, height);
    }

    drawbackground(color)
    {
        this.drawGraphAreaRect();
        this.context.fillStyle = color;
        this.context.fill();
    }

    drawGridlines(axisName, axis, oppositeAxisStartPixel, oppositeAxisStopPixel)
    {
        this.context.lineWidth = 1;
        this.context.fillStyle = "grey";
        this.context.font = "16px Arial";
        for (let gridLine of axis.gridLines) {
            let lastGridLinesIndex = axis.gridLines.length - 1;
            if (axis.gridLines.indexOf(gridLine) !== 0 && axis.gridLines.indexOf(gridLine) !== lastGridLinesIndex) {
                this.context.beginPath();
                if (axisName === 'x') {
                    this.context.moveTo(gridLine.pixel, oppositeAxisStartPixel);
                    this.context.lineTo(gridLine.pixel, oppositeAxisStopPixel);
                }
                if (axisName === 'y') {
                    this.context.moveTo(oppositeAxisStartPixel, gridLine.pixel);
                    this.context.lineTo(oppositeAxisStopPixel, gridLine.pixel);
                }
                if (gridLine.value === 0) {
                    this.context.setLineDash([25, 25]);
                    this.context.strokeStyle = "grey";
                } else {
                    this.context.setLineDash([5, 5]);
                    this.context.strokeStyle = "lightgrey";
                }
                this.context.stroke();
            }
            let labelText = axis.labelPrefix + gridLine.value.toLocaleString("en-US") + axis.labelSuffix;
            if (axisName === 'x') {
                this.context.save();
                this.context.translate(gridLine.pixel, oppositeAxisStartPixel);
                this.context.rotate(Math.PI/3);
                this.context.textAlign = 'left';
                this.context.fillText(labelText, 5, 8);
                this.context.restore();
            }
            if (axisName === 'y') {
                this.context.textAlign = 'right';
                this.context.fillText(labelText, oppositeAxisStartPixel - 5, gridLine.pixel + 4);
            }
        }
    }

    drawLine(line)
    {
        this.context.lineWidth = 3;
        this.context.strokeStyle = line.color;
        this.context.setLineDash([]);
        this.context.beginPath();
        this.context.moveTo(line.points[0].xPos, line.points[0].yPos);
        for (let point of line.points) {
            this.context.lineTo(point.xPos, point.yPos);
            this.context.moveTo(point.xPos, point.yPos);
        }
        this.context.stroke();
    }

    drawBorder(color, lineWidth)
    {
        this.drawGraphAreaRect();
        this.context.strokeStyle = color;
        this.context.lineWidth = lineWidth;
        this.context.stroke();
    }

    calculateAxisPixelData(axis, axisCanvasSize, margins, inverted)
    {
        let axisGraphSize = axisCanvasSize - margins[0] - margins[1];
        axis.pixelToValueRatio = parseFloat((axisGraphSize / axis.valueRange).toFixed(10));
        axis.gridPixelIncrement = parseFloat((axis.pixelToValueRatio * axis.gridValueIncrement).toFixed(0));
        if (inverted === false) {
            axis.startPixel = margins[0];
            axis.stopPixel = axisCanvasSize - margins[1];
        } else {
            axis.startPixel = axisCanvasSize - margins[0];
            axis.stopPixel = margins[1];
        }
    }

    calculateValuePixelPosition(axis, value, inverted)
    {
        let valueDistanceFromStart = value - axis.startValue;
        let pixelDistanceFromStart = parseFloat((valueDistanceFromStart * axis.pixelToValueRatio).toFixed(10));
        if (inverted === false) {
            return axis.startPixel + pixelDistanceFromStart;
        } else {
            return axis.startPixel - pixelDistanceFromStart;
        }
    }

    calculateGridLines(axis, inverted)
    {
        let gridLines = [];
        let gridLineCount = 0;
        while (gridLineCount <= axis.gridCount) {
            let gridLineValue = gridLineCount * axis.gridValueIncrement;
            let gridLineValueWithOffset = gridLineValue + axis.startValue;
            let pixelPosition = this.calculateValuePixelPosition(axis, gridLineValueWithOffset, inverted);
            let gridLine = [];
            gridLine["pixel"] = pixelPosition;
            gridLine["value"] = gridLineValueWithOffset;
            gridLines.push(gridLine);
            gridLineCount++;
        }
        return gridLines;
    }

    calculateLines(unplottedLines, inverted)
    {
        let lines = [];
        for (let unplottedLine of unplottedLines) {
            let points = [];
            for (let coordinate of unplottedLine.coordinates) {
                let point = [];
                point["xPos"] = this.calculateValuePixelPosition(this.graphData.xAxis, coordinate[0], false);
                point["xValue"] = coordinate[0];
                point["yPos"] = this.calculateValuePixelPosition(this.graphData.yAxis, coordinate[1], inverted);
                point["yValue"] = coordinate[1];
                points.push(point);
            }
            let line = [];
            line["label"] = unplottedLine.label;
            line["color"] = unplottedLine.color;
            line["points"] = points;
            lines.push(line);
        }
        return lines;
    }

}