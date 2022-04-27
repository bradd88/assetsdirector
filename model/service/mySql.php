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
            throw new Exception("Connection failed: " . $this->connection->connect_error);
        }
    }

    /** Explicitly close the existing database connection. */
    private function disconnect(): void
    {
        $this->connection->close();
    }

    /** Run a query on the database. */
    private function query(string $queryString): mysqli
    {
        $timerStart = microtime(TRUE);
        $queryResponse = $this->connection->real_query($queryString);
        $timerStop = microtime(TRUE);
        $executeTime = bcsub("$timerStop", "$timerStart", 5);
        $this->log->save('database_queries', 'Query took ' . $executeTime . ' seconds: ' . $queryString);
        if ($queryResponse === FALSE) {
            throw new Exception('Query: ' . $queryString . '\nError: ' . $this->connection->error);
        }
        return $this->connection;
    }

    /** Execute a prepared statement and returns the mysqli_stmt object. */
    private function preparedStatement(string $queryString, array $bindParams): mysqli_stmt
    {
        $timerStart = microtime(TRUE);
        $preparedStatement = $this->connection->prepare($queryString);
        $preparedStatement->bind_param(...$bindParams);
        $preparedStatement->execute();
        $preparedStatement->store_result();
        $timerStop = microtime(TRUE);
        $executeTime = bcsub("$timerStop", "$timerStart", 5);
        $this->log->save('database_queries', 'Query took ' . $executeTime . ' seconds: ' . $queryString);
        if ($preparedStatement === FALSE) {
            throw new Exception('Query: ' . $queryString . '\nError: ' . $this->connection->error);
        }
        return $preparedStatement;
    }

    /** Attempt to create a new user entry in the database. */
    public function createUser(string $username, string $password): bool
    {
        $queryString = 'SELECT `account_id` FROM `accounts` WHERE `username` = ?';
        $bindParams = array('s', $username);
        $checkUsername = $this->preparedStatement($queryString, $bindParams);
        if ($checkUsername->num_rows === 0) {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $created = time();
            $queryString = 'INSERT INTO `accounts` (`username`, `hash`, `created`) VALUES (?, ?, ?)';
            $bindParams = array('ssi', $username, $hash, $created);
            $this->preparedStatement($queryString, $bindParams);
            return TRUE;
        } else {
            return FALSE;
        }
    }

    /** Verify that provided user credentials are good and return the account ID. */
    public function verifyLogin(string $username, string $password): int|false
    {
        $queryString = 'SELECT `account_id`, `hash` FROM `accounts` WHERE `username` = ?';
        $bindParams = array('s', $username);
        $account = $this->preparedStatement($queryString, $bindParams);
        if ($account->num_rows === 1) {
            $account->bind_result($account_id, $hash);
            $account->fetch();
            if (password_verify($password, $hash)) {
                return $account_id;
            }
        }
        return FALSE;
    }

    /** Convert an array into a Columns/Values string for use in the WHERE, WHERE/BETWEEN, SET, or INSERT INTO/VALUES portions of a query. */
    private function arrayToQueryString(array $array, string $type): string
    {
        $concatenator = ($type === 'where') ? ' AND ' : ', ';
        $string = '';
        foreach ($array as $column => $value) {
            if ($type === 'where') {
                if (is_array($value)) {
                    $string .= '`' . $column . '` BETWEEN \'' . $value[0] . '\' AND \'' . $value[1] . '\'';
                } elseif (is_null($value)) {
                    $string .= '`' . $column . '` IS NULL';
                } else {
                    $string .= '`' . $column . '` = \'' . $value . '\'';
                }
            } elseif ($type === 'set') {
                if (is_null($value)) {
                    $string .= '`' . $column . '` = NULL';
                } else {
                    $string .= '`' . $column . '` = \'' . $value . '\'';
                }
            } elseif ($type === 'insertColumn') {
                $string .= '`' . $column . '`';
            } elseif ($type === 'insertValue') {
                $string .= '\'' . $value . '\'';
            }
            if ($column !== array_key_last($array)) {
                $string .= $concatenator;
            }
        }
        return $string;
    }

    /** Create rows in the database using Key/Value pairs from $insertArray. */
    public function create(string $tableName, array $insertArray): string
    {
        $columnString = $this->arrayToQueryString($insertArray, 'insertColumn');
        $valueString = $this->arrayToQueryString($insertArray, 'insertValue');
        $queryString = 'INSERT INTO `' . $tableName . '` (' . $columnString . ') VALUES (' . $valueString . ') ON DUPLICATE KEY UPDATE `id`=`id`';
        return $this->query($queryString)->insert_id;
    }

    /** Read from the database and return the results an array of objects representing the rows. */
    public function read(string $tableName, array $whereArray = NULL, string $orderBy = NULL): array
    {
        $queryString = 'SELECT * FROM `' . $tableName . '`';
        if ($whereArray !== NULL) {
            $whereString = $this->arrayToQueryString($whereArray, 'where');
            $queryString .= ' WHERE ' . $whereString;
        }
        if ($orderBy !== NULL) {
            $queryString .= ' ORDER BY ' . $orderBy;
        }
        $results = $this->query($queryString)->store_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($results as &$result) {
            $result = (object) $result;
        }
        return $results;
    }

    /** Update rows in the database using Key/Value pairs from $whereArray and $setArray. */
    public function update(string $tableName, array $whereArray, array $setArray): int
    {
        $whereString = $this->arrayToQueryString($whereArray, 'where');
        $setString = $this->arrayToQueryString($setArray, 'set');
        $queryString = 'UPDATE `' . $tableName . '` SET ' . $setString . ' WHERE ' . $whereString;
        return $this->query($queryString)->affected_rows;
    }

    /** Delete selected rows from the database using Key/Value pairs from $whereArray. */
    public function delete(string $tableName, array $whereArray): int
    {
        $whereString = $this->arrayToQueryString($whereArray, 'where');
        $queryString = 'DELETE FROM `' . $tableName . '` WHERE ' . $whereString;
        return $this->query($queryString)->affected_rows;
    }

    /** Delete an entire selected table from the database. */
    public function truncate(string $tableName): int
    {
        $queryString = 'TRUNCATE `' . $this->dbSettings->database . '`.`' . $tableName . '`';
        return $this->query($queryString)->affected_rows;

    }

}

?>