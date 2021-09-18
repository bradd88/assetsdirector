<?php
function pageLogin($message) {
    $output = '';
    $output .= '
        <div class="login">
        <h1>Login</h1>
        <form action="./" method="post">
        <input type="text" name="username" placeholder="Username" id="username" required>
        <input type="password" name="password" placeholder="Password" id="password" required>
        <input type="submit" value="Login">
        </form>
        <span id=\'loginMessage\'>
    ';
    $output .= $message;
    $output .= '
        </span>
        </div>
    ';
    return $output;
}

?>