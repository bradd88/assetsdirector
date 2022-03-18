<?php 

class MySql
{

    private object $dbSettings;
    private Log $logs;
    private object $connection;

    public function __construct(Config $config, Log $logs)
    {
        $this->dbSettings = $config->getSettings('mySql');
        $this->logs = $logs;
        $this->connect();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    // Create a new database connection using information from the config file.
    private function connect()
    {
        $this->connection = new mysqli($this->dbSettings->host, $this->dbSettings->user, $this->dbSettings->password, $this->dbSettings->database);
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
    }

    private function disconnect()
    {
        $this->connection->close();
    }

    // Run a supplied query and return the appropriate information from the results.
    private function query($queryType, $queryString)
    {
        // Perform the requested query and return the relevant information from the response.
        $queryResponse = $this->connection->query($queryString);
        if ($queryResponse === FALSE) {
            return 'Query: ' . $queryString . '\nError: ' . $this->connection->error;
        } else {
            if ($queryType === 'INSERT') {
                return $this->connection->insert_id;
            } elseif ($queryType === 'SELECT') {
                return json_decode(json_encode($queryResponse->fetch_all(MYSQLI_ASSOC)));
            } elseif ($queryType === 'UPDATE' || $queryType === 'DELETE' || $queryType === 'TRUNCATE') {
                return $this->connection->affected_rows;
            }
        }
    }

    // Create a new user in the database using a prepared statement.
    public function createUser(string $username, string $password)
    {
        // Check if the username already exists.
        $checkUsername = $this->connection->prepare('SELECT `account_id` FROM `accounts` WHERE `username` = ?');
        $checkUsername->bind_param('s', $username);
        $checkUsername->execute();
        $checkUsername->store_result();
        
        // Create the new account if the username isn't taken.
        if ($checkUsername->num_rows === 0) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $created = time();
            $createUser = $this->connection->prepare('INSERT INTO `accounts` (`username`, `hash`, `created`) VALUES (?, ?, ?)');
            $createUser->bind_param('ssi', $username, $hash, $created);
            $createUser->execute();
            return 'Account created.';
        } else {
            return 'Account already exists.';
        }
    }

    // Verify user credentials using a prepared statement.
    public function verifyLogin(string $username, string $password)
    {
        // Retrieve the account information.
        $verifyLogin = $this->connection->prepare('SELECT `account_id`, `hash` FROM `accounts` WHERE `username` = ?');
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
    private function arrayToQueryString(array $array, string $type)
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
    public function create(string $tableName, array $insertArray)
    {
        $columnString = $this->arrayToQueryString($insertArray, 'insertColumn');
        $valueString = $this->arrayToQueryString($insertArray, 'insertValue');
        $queryString = 'INSERT INTO `' . $tableName . '` (' . $columnString . ') VALUES (' . $valueString . ') ON DUPLICATE KEY UPDATE `id`=`id`';
        return $this->query('INSERT', $queryString);
    }

    /**
     * MySQL Read
     * Read rows from the database using selected column values.
     * 
     * E.G. The following code:
     *     read('accounts', ['test' => [89675, 456787], 'herp' => 'lerp'], 'Price DESC')
     * Will run the following query:
     *     SELECT * FROM accounts WHERE test BETWEEN 89675 AND 456787 AND herp = lerp ORDER BY Price DESC
     * 
     * @param string $tableName
     * @param array $whereArray The Key/Value pairs of the $whereArray are mapped to database Column/Value pairs. If a Value is input as an array then a BETWEEN search will be used.
     * @param string $orderBy
     * @return array Returns an array(table) of objects(rows).
     */
    public function read(string $tableName, array $whereArray = NULL, string $orderBy = NULL)
    {
        $queryString = 'SELECT * FROM `' . $tableName . '`';
        if ($whereArray !== NULL) {
            $whereString = $this->arrayToQueryString($whereArray, 'where');
            $queryString .= ' WHERE ' . $whereString;
        }
        if ($orderBy !== NULL) {
            $queryString .= ' ORDER BY ' . $orderBy;
        }
        return $this->query('SELECT', $queryString);
    }

    // Update rows with new values using selected column values.
    public function update(string $tableName, array $whereArray, array $setArray)
    {
        $whereString = $this->arrayToQueryString($whereArray, 'where');
        $setString = $this->arrayToQueryString($setArray, 'set');
        $queryString = 'UPDATE `' . $tableName . '` SET ' . $setString . ' WHERE ' . $whereString;
        return $this->query('UPDATE', $queryString);
    }

    // Delete selected rows from the database using selected column values.
    public function delete(string $tableName, array $whereArray)
    {
        $whereString = $this->arrayToQueryString($whereArray, ' AND ');
        $queryString = 'DELETE FROM `' . $tableName . '` WHERE ' . $whereString;
        return $this->query('DELETE', $queryString);
    }

    // Delete an entire selected table from the database.
    public function truncate(string $tableName)
    {
        $queryString = 'TRUNCATE `' . $this->dbSettings->database . '`.`' . $tableName . '`';
        return $this->query('TRUNCATE', $queryString);

    }

}

?>