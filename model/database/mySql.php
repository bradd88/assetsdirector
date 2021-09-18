<?php 

/**
 * Connect to the MySql database.
 * Uses settings from config.ini
 *
 * @return object Connection object.
 */
function mySqlConnect() {
    // Retrieve the connection settings frrom the config.
    $servername = $GLOBALS['config']['mySql']['host'];
    $dbname = $GLOBALS['config']['mySql']['database'];
    $username = $GLOBALS['config']['mySql']['user'];
    $password = $GLOBALS['config']['mySql']['password'];
    
    // Create the connection.
    $investmentdb = new mysqli($servername, $username, $password, $dbname);
    if ($investmentdb->connect_error) {
        die("Connection failed: " . $investmentdb->connect_error);
    }

    return $investmentdb;
}

/**
 * Performs a MySql query.
 * Retrieves connection details in addition to the query response.
 *
 * @param string $query MySql query string.
 * @return object Contains query response and connection details.
 */
function mySqlQuery($query) {
    // Connect to the database.
    $output = new stdClass();
    $conn = mySqlConnect();

    // // Perform the query, get the response, and store connection details.
    $result = $conn->query($query);
    if ($result == FALSE) {
        $output->success = FALSE;
        $output->error = $conn->error;
    } else {
        $output->success = TRUE;
        $output->result = $result;
        $output->affected_rows = $conn->affected_rows;
        $output->insert_id = $conn->insert_id;
    }
    
    $conn->close();
    return $output;
}

/**
 * Create a new user.
 * Uses a prepared statement to add new user to the MySql database.
 *
 * @param string $username User provided username.
 * @param string $password User provided password.
 * @return string Success or failure message.
 */
function mySqlCreateUser($username, $password) {
    // Use a prepared statement to check if the requested username already exists.
    $conn = mySqlConnect();
    $accountExists = $conn->prepare('SELECT `id` FROM `accounts` WHERE `username` = ?');
    $accountExists->bind_param('s', $username);
    $accountExists->execute();
    $accountExists->store_result();
    $successMessage = 'Account created.';
    $failMessage = 'Account already exists.';
    
    // Create the new account if the username isn't taken.
    if ($accountExists->num_rows == 0) {
        $created = time();
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $conn = mySqlConnect();
        $createUser = $conn->prepare('INSERT INTO `accounts` (`username`, `hash`, `created`) VALUES (?, ?, ?)');
        $createUser->bind_param('ssi', $username, $hash, $created);
        $createUser->execute();
        $output = $successMessage;
    } else {
        $output = $failMessage;
    }
    return $output;
}

/**
 * Verify account login info.
 * Hashes the provided password and compares it with the username/hash in the database.
 *
 * @param string $username User provided username.
 * @param string $password User provided password.
 * @return int|bool User ID on success, or FALSE on failure
 */
function mySqlVerifyLogin($username, $password) {
    // Use a prepared statement to retrieve account info for a matching username.
    $conn = mySqlConnect();
    $verifyLogin = $conn->prepare('SELECT `id`, `hash` FROM `accounts` WHERE `username` = ?');
    $verifyLogin->bind_param('s', $username);
    $verifyLogin->execute();
    $verifyLogin->store_result();
    
    // Check the account exists, then retrieve the account ID and password hash.
    if ($verifyLogin->num_rows == 1) {
        $id = '';
        $hash = '';
        $verifyLogin->bind_result($id, $hash);
        $verifyLogin->fetch();
        
        // Start a session if the supplied password matches the hash.
        if (password_verify($password, $hash) == TRUE) {
            $output = $id;
        } else {
            // Password mismatch.
            $output = FALSE;
        }
    } else {
        // Account doesn't exist.
        $output = FALSE;
    }
    
    $verifyLogin->close();
    return $output;
}

/**
 * Create a new row in the MySql database.
 *
 * @param string $tableName MySql table name to perform query on.
 * @param array $insertArray Must be an associative array. Array keys are used for MySql columns, and array values for MySql values.
 * @return int|string Insert_id of the new row, or a failure message.
 */
function mySqlCreate($tableName, $insertArray) {
    // Convert the associative insertArray into columns and values strings for the mySql query.
    $columnsOutput = '';
    $valuesOutput = '';
    foreach ($insertArray as $key => $value) {
        if ($key === array_key_first($insertArray)) {
            $columnsOutput .= '(`' . $key . '`, ';
            $valuesOutput .= '(\'' . $value . '\', ';
        } elseif ($key === array_key_last($insertArray)) {
            $columnsOutput .= '`' . $key . '`)';
            $valuesOutput .= '\'' . $value . '\')';
        } else {
            $columnsOutput .= '`' . $key . '`, ';
            $valuesOutput .= '\'' . $value . '\', ';
        }
    }
    $query = 'INSERT INTO `' . $tableName . '` ' . $columnsOutput . ' VALUES ' . $valuesOutput . ' ON DUPLICATE KEY UPDATE `id`=`id`';
    
    // Perform the query and get the response.
    $response = mySqlQuery($query);
    if ($response->success == TRUE) {
        $output = $response->insert_id;
    } else {
        $output = 'Query: ' . $query . '\nError: ' . $response->error;
    }
    
    return $output;
}

/**
 * Read rows from the MySql database.
 * If only a table name is supplied then the whole table will be returned.
 *
 * @param string $tableName MySql table name to perform query on.
 * @param string $whereColumn Optional. MySql search column.
 * @param string $whereValue Optional. MySql search value.
 * @return array|string Array of objects, or a failure message.
 */
function mySqlRead($tableName, $whereColumn = NULL, $whereValue = NULL) {
    // Set the query string.
    if ($whereColumn == NULL) {
        $query = 'SELECT * FROM `' . $tableName . '`';
    } else {
        $query = 'SELECT * FROM `' . $tableName . '` WHERE `' . $whereColumn . '` = \'' . $whereValue . '\'';
    }
    
    // Perform the query and get the response.
    $response = mySqlQuery($query);
    if ($response->success == TRUE) {
        $output = json_decode(json_encode($response->result->fetch_all(MYSQLI_ASSOC)));
    } else {
        $output = $response->error;
    }
    
    return $output;
}

/**
 * Read rows from the MySql database using BETWEEN operator.
 *
 * @param string $tableName MySql table name to perform query on.
 * @param string $whereColumn MySql search column.
 * @param string $betweenStart Between operator range start.
 * @param string $betweenEnd Between operator range end.
 * @return array|string Array of objects, or a failure message.
 */
function mySqlReadBetween($tableName, $whereColumn, $betweenStart, $betweenEnd) {
    // Set the query string.
    $query = 'SELECT * FROM `' . $tableName . '` WHERE `' . $whereColumn . '` BETWEEN \'' . $betweenStart . '\' AND \'' . $betweenEnd . '\'';
    
    // Perform the query and get the response.
    $response = mySqlQuery($query);
    if ($response->success == TRUE) {
        $output = json_decode(json_encode($response->result->fetch_all(MYSQLI_ASSOC)));
    } else {
        $output = $response->error;
    }
    
    return $output;
}

/**
 * Update rows in the MySql database.
 *
 * @param string $tableName MySql table name to perform query on.
 * @param array $setArray Must be an associative array. Array keys are used for MySql columns, and array values for MySql values.
 * @param string $whereColumn MySql search column.
 * @param string $whereValue MySql search value.
 * @return int|string Number of affected rows, or a failure message.
 */
function mySqlUpdate($tableName, $setArray, $whereColumn, $whereValue) {
    // Convert the associative setArray into a string for the mySql query.
    $setString = '';
    foreach ($setArray as $key => $value) {
        if ($key === array_key_last($setArray)) {
            $setString .= '`' . $key . '` = \'' . $value . '\'';
        } else {
            $setString .= '`' . $key . '` = \'' . $value . '\', ';
        }
    }
    $query = 'UPDATE `' . $tableName . '` SET ' . $setString . ' WHERE `' . $whereColumn . '` = \'' . $whereValue . '\'';
    
    // Perform the query and get the response.
    $response = mySqlQuery($query);
    if ($response->success == TRUE) {
        $output = $response->affected_rows;
    } else {
        $output = 'Query: ' . $query . '\nError: ' . $response->error;
    }
    
    return $output;
}

/**
 * Delete rows in the MySql database.
 *
 * @param string $tableName MySql table name to perform query on.
 * @param string $whereColumn MySql search column.
 * @param string $whereValue MySql search value.
 * @return int|string Number of deleted rows, or a failure message.
 */
function mySqlDelete($tableName, $whereColumn, $whereValue) {
    // Set the query string.
    $query = 'DELETE FROM `' . $tableName . '` WHERE `' . $whereColumn . '` = \'' . $whereValue . '\'';
    
    // Perform the query and get the response.
    $response = mySqlQuery($query);
    if ($response->success == TRUE) {
        $output = $response->affected_rows;
    } else {
        $output = 'Query: ' . $query . '\nError: ' . $response->error;
    }
    
    return $output;
}

/**
 * Truncate a table in the MySql database.
 *
 * @param string $tableName MySql table to truncate.
 * @return int|string Number of deleted rows, or a failure message.
 */
function MySqlTruncate($tableName) {
    // Set the query string.
    $query = 'TRUNCATE `' . $GLOBALS['config']['mySql']['database'] . '`.`' . $tableName . '`';

    // Perform the query and get the response.
    $response = mySqlQuery($query);
    if ($response->success == TRUE) {
        $output = $response->affected_rows;
    } else {
        $output = 'Query: ' . $query . '\nError: ' . $response->error;
    }
    
    return $output;
}

?>