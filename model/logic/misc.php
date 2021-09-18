<?php

/**
 * Covert an array into an object.
 *
 * @param array $array
 * @return object
 */
function arrayToObject($array) {
    $output = json_decode(json_encode($array));
    return $output;
}

/**
 * Convert an object into an associative array.
 *
 * @param object $object
 * @return array
 */
function objectToArray($object) {
    $output = json_decode(json_encode($object), TRUE);
    return $output;
}

/**
 * Flatten an array or object.
 *
 * @param array|object $parent
 * @return array Associative array with no depth.
 */
function flatten($parent) {
    $output = [];
    
    // Iterate through parent array.
    foreach ($parent as $key => $value) {
        if (is_array($value) || is_object($value)) {
            // Recursivly flatten child objects and arrays. Convert the child data to an associative array, and merge with the output.
            $child = flatten($value);
            $output = array_merge($output, $child);
        } else {
            // Add key value pairs to the output.
            $output[$key] = $value;
        }
    }
    
    return $output;
}

/**
 * Strip whitespacing and line returns from HTML or CSS;
 *
 * @param string $str HTML/CSS code.
 * @return string
 */
function minifyHtml($str) {
    $str = str_replace("\n", "", $str);
    $str = str_replace("\r", "", $str);
    $str = preg_replace('( {4})', '', $str);
    return $str;
}

/**
 * Calculate the timespan between two timestamps and output in a readable format.
 * Output example: 11 months, 30 days
 *
 * @param int $start Unix timestamp for date/time start.
 * @param int $end Unix timestamp for date/time end.
 * @param int $length Number of time intervals to display. Largest intervals are displayed first.
 * @return string Human readable timespan.
 */
function calculateTimespan($start, $end, $length) {
    // Define the seconds length of each common time interval.
    $intervalLength = ["year" => 31557600, "month" => 2629800, "day" => 86400, "hour" => 3600, "minute" => 60];

    // Calculate the quantity of each time interval by taking the quantity remainder of the next largest interval and diving by the seconds in a single length of the desired interval, then round down.
    $timespan = $end - $start;
    $intervalQuantity = [
        "year" => floor($timespan/$intervalLength['year']),
        "month" => floor(($timespan%$intervalLength['year'])/$intervalLength['month']),
        "day" => floor(($timespan%$intervalLength['month'])/$intervalLength['day']),
        "hour" => floor(($timespan%$intervalLength['day'])/$intervalLength['hour']),
        "minute" => floor(($timespan%$intervalLength['hour'])/$intervalLength['minute']),
        "second" => floor($timespan%$intervalLength['minute']),
    ];

    // Output the number of time intervals requested, starting with the largest.
    $output = '';
    $outputCounter = 0;
    foreach ($intervalQuantity as $intervalName => $intervalValue) {
        if ($outputCounter < $length && $intervalValue > 0) {
            // Make sure each time interval is properly pluralized.
            $output .= $intervalValue . ' ' . $intervalName . (($intervalValue > 1) ? 's' : '');
            $outputCounter++;
            // Add commas between time intervals.
            if ($outputCounter < $length && $intervalName != array_key_last($intervalQuantity)) {
                $output .= ', ';
            }
        }
    }
    return $output;
}

?>