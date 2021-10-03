<?php 

class MySql {

    private static $connection;

    public static function connect()
    {
        $servername = $GLOBALS['config']['mySql']['host'];
        $dbname = $GLOBALS['config']['mySql']['database'];
        $username = $GLOBALS['config']['mySql']['user'];
        $password = $GLOBALS['config']['mySql']['password'];
        self::$connection = new mysqli($servername, $username, $password, $dbname);
        if (self::$connection->connect_error) {
            die("Connection failed: " . self::$connection->connect_error);
        }
    }

    public static function disconnect()
    {
        self::$connection->close();
    }

    private static function query($queryType, $queryString)
    {
        // Perform the requested query and return the relevant information from the response.
        $queryResponse = self::$connection->query($queryString);
        if ($queryResponse === FALSE) {
            return 'Query: ' . $queryString . '\nError: ' . self::$connection->error;
        } else {
            if ($queryType === 'INSERT') {
                return self::$connection->insert_id;
            } elseif ($queryType === 'SELECT') {
                return json_decode(json_encode($queryResponse->fetch_all(MYSQLI_ASSOC)));
            } elseif ($queryType === 'UPDATE' || $queryType === 'DELETE' || $queryType === 'TRUNCATE') {
                return self::$connection->affected_rows;
            }
        }
    }

    public static function createUser($username, $password)
    {
        // Check if the username already exists.
        $checkUsername = self::$connection->prepare('SELECT `id` FROM `accounts` WHERE `username` = ?');
        $checkUsername->bind_param('s', $username);
        $checkUsername->execute();
        $checkUsername->store_result();
        
        // Create the new account if the username isn't taken.
        if ($checkUsername->num_rows === 0) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $created = time();
            $createUser = self::$connection->prepare('INSERT INTO `accounts` (`username`, `hash`, `created`) VALUES (?, ?, ?)');
            $createUser->bind_param('ssi', $username, $hash, $created);
            $createUser->execute();
            return 'Account created.';
        } else {
            return 'Account already exists.';
        }
    }

    public static function verifyLogin($username, $password)
    {
        // Retrieve the account information.
        $verifyLogin = self::$connection->prepare('SELECT `id`, `hash` FROM `accounts` WHERE `username` = ?');
        $verifyLogin->bind_param('s', $username);
        $verifyLogin->execute();
        $verifyLogin->store_result();

        // If an account matching the username exists, then store the account ID and password hash.
        if ($verifyLogin->num_rows === 1) {
            $id = '';
            $hash = '';
            $verifyLogin->bind_result($id, $hash);
            $verifyLogin->fetch();
            
            // Verify the supplied password matches the account password hash.
            if (password_verify($password, $hash) === TRUE) {
                // Correct password
                return $id;
            } else {
                // Incorrect password.
                return FALSE;
            }
        } else {
            // Account doesn't exist.
            return FALSE;
        }
    }

    public static function create($tableName, $insertArray)
    {
        // Convert the insertArray key/value pairs into column/value strings for the query.
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
        $queryString = 'INSERT INTO `' . $tableName . '` ' . $columnsOutput . ' VALUES ' . $valuesOutput . ' ON DUPLICATE KEY UPDATE `id`=`id`';
        return self::query('INSERT', $queryString);
    }

    public static function read($tableName, $whereColumn = NULL, $whereValue = NULL, $betweenStart = NULL, $betweenEnd = NULL)
    {
        // Set the query string.
        if ($betweenStart !== NULL && $betweenEnd !== NULL) {
            $queryString = 'SELECT * FROM `' . $tableName . '` WHERE `' . $whereColumn . '` BETWEEN \'' . $betweenStart . '\' AND \'' . $betweenEnd . '\'';
        } elseif ($whereColumn !== NULL && $whereValue !== NULL) {
            $queryString = 'SELECT * FROM `' . $tableName . '` WHERE `' . $whereColumn . '` = \'' . $whereValue . '\'';
        } else {
            $queryString = 'SELECT * FROM `' . $tableName . '`';
        }
        return self::query('SELECT', $queryString);
    }

    public static function update($tableName, $setArray, $whereColumn, $whereValue)
    {
        // Convert the setArray key/value pairs into a column/value string for the query.
        $setString = '';
        foreach ($setArray as $key => $value) {
            if ($key === array_key_last($setArray)) {
                $setString .= '`' . $key . '` = \'' . $value . '\'';
            } else {
                $setString .= '`' . $key . '` = \'' . $value . '\', ';
            }
        }
        $queryString = 'UPDATE `' . $tableName . '` SET ' . $setString . ' WHERE `' . $whereColumn . '` = \'' . $whereValue . '\'';
        return self::query('UPDATE', $queryString);
    }

    public static function delete($tableName, $whereColumn, $whereValue)
    {
        $queryString = 'DELETE FROM `' . $tableName . '` WHERE `' . $whereColumn . '` = \'' . $whereValue . '\'';
        return self::query('DELETE', $queryString);
    }

    public static function truncate($tableName)
    {
        $queryString = 'TRUNCATE `' . $GLOBALS['config']['mySql']['database'] . '`.`' . $tableName . '`';
        return self::query('TRUNCATE', $queryString);

    }

}

?>