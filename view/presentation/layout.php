<?php 

function presentationLayout($pageContent, $css, $menu = NULL) {
    $output = '';
    $output .= '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <style>
        ';
    $output .= $css;
    $output .= '
        </style>
        </head>
        <body>
        ';
    $output .= $menu;
    $output .= '
        <div id="mainwrapper">
        <div id="main" class="content">
    ';
    $output .= $pageContent;
    $output .= '
        </div>
        </div>
        </body>
        </html>
    ';
    return $output;
}

?>