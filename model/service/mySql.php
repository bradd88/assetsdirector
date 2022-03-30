<?php 

/** This class performs queries the MySQL database. Supports CRUD operations as well as prepared statements. */
class MySql
{

    private object $dbSettings;
    private Log $log;
    private mysqli $connection;

    public function __construct(Config $config, Log $log)
    {
        $this->dbSettings = $config->getSettings('mySql');
        $this->log = $log;
        $this->connect();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /** Create a new database connection using information from the config file. */
    private function connect(): void
    {
        $this->connection = new mysqli($this->dbSettings->host, $this->dbSettings->user, $this->dbSettings->password, $this->dbSettings->database);
        if ($this->connection->connect_error) {
            die("Connection failed: " . $this->connection->connect_error);
        }
    }

    /** Explicitly close the existing database connection. */
    private function disconnect(): void
    {
        $this->connection->close();
    }

    /** Run a supplied query and return the appropriate information from the results.
     * 
     * @return mixed
     * INSERT returns the ID of the inserted row.
     * SELECT returns an array of objects representing table rows.
     * UPDATE/DELTE/TRUNCATE returns the number of affected rows.
     * If there is an errror with the query, a string containing error data is returned instead.
     */
    private function query(string $queryType, string $queryString): mixed
    {
        // Perform the requested query and return the relevant information from the response.
        $timerStart = microtime(TRUE);
        $queryResponse = $this->connection->query($queryString);
        $timerStop = microtime(TRUE);
        $executeTime = bcsub("$timerStop", "$timerStart", 5);
        $this->log->save('database_queries', 'Query took ' . $executeTime . ' seconds: ' . $queryString);
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

    /** Execute a prepared statement string, and record execution times in the logs.
     * 
     * @return mysqli_stmt|false Returns the statement object, or bool FALSE if there is an error.
     */
    private function preparedStatement(string $queryString, array $bindParams): mixed
    {
        $preparedStatement = $this->connection->prepare($queryString);
        $preparedStatement->bind_param(...$bindParams);
        $timerStart = microtime(TRUE);
        $preparedStatement->execute();
        $timerStop = microtime(TRUE);
        $executeTime = bcsub("$timerStop", "$timerStart", 5);
        $preparedStatement->store_result();
        $this->log->save('database_queries', 'Query took ' . $executeTime . ' seconds: ' . $queryString);
        return $preparedStatement;
    }

    /** Create a new user in the database using a prepared statement. */
    public function createUser(string $username, string $password): string
    {
        // Check if the username already exists.
        $queryString = 'SELECT `account_id` FROM `accounts` WHERE `username` = ?';
        $bindParams = array('s', $username);
        $checkUsername = $this->preparedStatement($queryString, $bindParams);
        
        // Create the new account if the username isn't taken.
        if ($checkUsername->num_rows === 0) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $created = time();
            $queryString = 'INSERT INTO `accounts` (`username`, `hash`, `created`) VALUES (?, ?, ?)';
            $bindParams = array('ssi', $username, $hash, $created);
            $this->preparedStatement($queryString, $bindParams);
            return 'Account created.';
        } else {
            return 'Account already exists.';
        }
    }

    /** Verify that provided user credentials are good.
     * 
     * @return int|false Returns the verified account ID if username/password are good. Returns FALSE if the username/password are bad.
    */
    public function verifyLogin(string $username, string $password): mixed
    {
        // Retrieve the account information.
        $queryString = 'SELECT `account_id`, `hash` FROM `accounts` WHERE `username` = ?';
        $bindParams = array('s', $username);
        $verifyLogin = $this->preparedStatement($queryString, $bindParams);

        // If an account matching the username exists, then store the account ID and password hash.
        if ($verifyLogin->num_rows === 1) {
            $account_id = '';
            $hash = '';
            $verifyLogin->bind_result($account_id, $hash);
            $verifyLogin->fetch();
            $verifyStatus = (password_verify($password, $hash) === TRUE) ? $account_id : FALSE;
            return $verifyStatus;
        } else {
            return FALSE;
        }
    }

    /** Convert an array into a Columns/Values string for use in the WHERE, WHERE/BETWEEN, SET, or INSERT INTO/VALUES portions of a query. */
    private function arrayToQueryString(array $array, string $type): string
    {

        $concatenator = ($type === 'where') ? ' AND ' : ', ';
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

    /** Create rows in the database using Key/Value pairs from $insertArray. */
    public function create(string $tableName, array $insertArray): mixed
    {
        $columnString = $this->arrayToQueryString($insertArray, 'insertColumn');
        $valueString = $this->arrayToQueryString($insertArray, 'insertValue');
        $queryString = 'INSERT INTO `' . $tableName . '` (' . $columnString . ') VALUES (' . $valueString . ') ON DUPLICATE KEY UPDATE `id`=`id`';
        return $this->query('INSERT', $queryString);
    }

    /** Read rows from the database using Key/Value pairs from $whereArray. */
    public function read(string $tableName, array $whereArray = NULL, string $orderBy = NULL): mixed
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

    /** Update rows in the database using Key/Value pairs from $whereArray and $setArray. */
    public function update(string $tableName, array $whereArray, array $setArray): mixed
    {
        $whereString = $this->arrayToQueryString($whereArray, 'where');
        $setString = $this->arrayToQueryString($setArray, 'set');
        $queryString = 'UPDATE `' . $tableName . '` SET ' . $setString . ' WHERE ' . $whereString;
        return $this->query('UPDATE', $queryString);
    }

    /** Delete selected rows from the database using Key/Value pairs from $whereArray. */
    public function delete(string $tableName, array $whereArray): mixed
    {
        $whereString = $this->arrayToQueryString($whereArray, ' AND ');
        $queryString = 'DELETE FROM `' . $tableName . '` WHERE ' . $whereString;
        return $this->query('DELETE', $queryString);
    }

    /** Delete an entire selected table from the database. */
    public function truncate(string $tableName): mixed
    {
        $queryString = 'TRUNCATE `' . $this->dbSettings->database . '`.`' . $tableName . '`';
        return $this->query('TRUNCATE', $queryString);

    }

}

?>