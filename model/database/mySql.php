<?php 

class MySql {

    private static $connection;

    // Create a new database connection using information from the config file.
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

    // Run a supplied query and return the appropriate information from the results.
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

    // Create a new user in the database using a prepared statement.
    public static function createUser($username, $password)
    {
        // Check if the username already exists.
        $checkUsername = self::$connection->prepare('SELECT `account_id` FROM `accounts` WHERE `username` = ?');
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

    // Verify user credentials using a prepared statement.
    public static function verifyLogin($username, $password)
    {
        // Retrieve the account information.
        $verifyLogin = self::$connection->prepare('SELECT `account_id`, `hash` FROM `accounts` WHERE `username` = ?');
        $verifyLogin->bind_param('s', $username);
        $verifyLogin->execute();
        $verifyLogin->store_result();

        // If an account matching the username exists, then store the account ID and password hash.
        if ($verifyLogin->num_rows === 1) {
            $account_id = '';
            $hash = '';
            $verifyLogin->bind_result($account_id, $hash);
            $verifyLogin->fetch();
            
            // Verify the supplied password matches the account password hash.
            if (password_verify($password, $hash) === TRUE) {
                // Correct password
                return $account_id;
            } else {
                // Incorrect password.
                return FALSE;
            }
        } else {
            // Account doesn't exist.
            return FALSE;
        }
    }

        // Convert an array into a Columns/Values string for use in the WHERE, WHERE/BETWEEN, SET, or INSERT INTO/VALUES portions of a query.
        private static function arrayToQueryString($array, $type)
        {
            // Set the appropriate string to glue segments together.
            if ($type == 'where') {
                $concatenator = ' AND ';
            } else {
                $concatenator = ', ';
            }

            // Use the key/value pairs from the array to create the string.
            $string = '';
            foreach ($array as $key => $value) {
                if ($type == 'where') {
                    if (!is_array($value)) {
                        $content = '`' . $key . '` = \'' . $value . '\'';
                    } else {
                        $content = '`' . $key . '` BETWEEN \'' . $value[0] . '\' AND \'' . $value[1] . '\'';
                    }
                }elseif ($type == 'set') {
                    $content = '`' . $key . '` = \'' . $value . '\'';
                } elseif ($type == 'insertColumn') {
                    $content = '`' . $key . '`';
                } elseif ($type == 'insertValue') {
                    $content = '\'' . $value . '\'';
                }
                $string .= $content;
                if ($key !== array_key_last($array)) {
                    $string .= $concatenator;
                }
            }
            return $string;
        }

    // Create rows in the database from an array of columns and values.
    public static function create($tableName, $insertArray)
    {
        $columnString = self::arrayToQueryString($insertArray, 'insertColumn');
        $valueString = self::arrayToQueryString($insertArray, 'insertValue');
        $queryString = 'INSERT INTO `' . $tableName . '` (' . $columnString . ') VALUES (' . $valueString . ') ON DUPLICATE KEY UPDATE `id`=`id`';
        return self::query('INSERT', $queryString);
    }

    /**
     * MySQL Read
     * Read rows from the database using selected column values.
     * 
     * E.G. The following code:
     *     MySql::read('accounts', ['test' => [89675, 456787], 'herp' => 'lerp'], 'Price DESC')
     * Will run the following query:
     *     SELECT * FROM accounts WHERE test BETWEEN 89675 AND 456787 AND herp = lerp ORDER BY Price DESC
     * 
     * @param string $tableName
     * @param array $whereArray The Key/Value pairs of the $whereArray are mapped to database Column/Value pairs. If a Value is input as an array then a BETWEEN search will be used.
     * @param string $orderBy
     * @return array Returns an array(table) of objects(rows).
     */
    public static function read($tableName, $whereArray = NULL, $orderBy = NULL)
    {
        $queryString = 'SELECT * FROM `' . $tableName . '`';
        if ($whereArray !== NULL) {
            $whereString = self::arrayToQueryString($whereArray, 'where');
            $queryString .= ' WHERE ' . $whereString;
        }
        if ($orderBy !== NULL) {
            $queryString .= ' ORDER BY ' . $orderBy;
        }
        return self::query('SELECT', $queryString);
    }

    // Update rows with new values using selected column values.
    public static function update($tableName, $whereArray, $setArray)
    {
        $whereString = self::arrayToQueryString($whereArray, 'where');
        $setString = self::arrayToQueryString($setArray, 'set');
        $queryString = 'UPDATE `' . $tableName . '` SET ' . $setString . ' WHERE ' . $whereString;
        return self::query('UPDATE', $queryString);
    }

    // Delete selected rows from the database using selected column values.
    public static function delete($tableName, $whereArray)
    {
        $whereString = self::arrayToQueryString($whereArray, ' AND ');
        $queryString = 'DELETE FROM `' . $tableName . '` WHERE ' . $whereString;
        return self::query('DELETE', $queryString);
    }

    // Delete an entire selected table from the database.
    public static function truncate($tableName)
    {
        $queryString = 'TRUNCATE `' . $GLOBALS['config']['mySql']['database'] . '`.`' . $tableName . '`';
        return self::query('TRUNCATE', $queryString);

    }

}

?>