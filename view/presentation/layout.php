<?php 

function presentationLayout($pageContent, $css, $menu = NULL) {
    $output = '
        <!DOCTYPE html>
        <html>
        <head>
        <meta charset="UTF-8">
        <style>
        ' . $css . '
        </style>
        </head>
        <body>
        ' . $menu . '
        <div id="mainwrapper">
        <div id="main" class="content">
        ' . $pageContent . '
        </div>
        </div>
        </body>
        </html>
    ';
    return $output;
}

?>