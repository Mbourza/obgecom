<?php 

class DB {
    
    private static $_instance = null;
    private $_pdo,
            $_query,
            $_error = false,
			$_errorMessage = null,
            $_results,
            $_lastInsertId,
            $_count = 0,
            $_inTransaction = false;

    private function __construct() {
        try {
            $this->_pdo = new PDO(
                'mysql:host='. Config::get('mysql/host').';dbname='. Config::get('mysql/db'),
                Config::get('mysql/user'),
                Config::get('mysql/pass'),
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch(PDOException $e) {
            die($e->getMessage());
        }
    }

    public static function getInstance() {
        if(!isset(self::$_instance)) {
            self::$_instance = new DB();
        }
        return self::$_instance;
    }

    /**
     * Begin a database transaction
     * @return bool Success status
     */
    public function beginTransaction() {
        if (!$this->_inTransaction) {
            $this->_inTransaction = $this->_pdo->beginTransaction();
            return $this->_inTransaction;
        }
        return false;
    }

    /**
     * Commit the current transaction
     * @return bool Success status
     */
    public function commit() {
        if ($this->_inTransaction) {
            $this->_inTransaction = false;
            return $this->_pdo->commit();
        }
        return false;
    }

    /**
     * Rollback the current transaction
     * @return bool Success status
     */
    public function rollback() {
        if ($this->_inTransaction) {
            $this->_inTransaction = false;
            return $this->_pdo->rollBack();
        }
        return false;
    }

    /**
     * Check if currently in a transaction
     * @return bool Transaction status
     */
    public function inTransaction() {
        return $this->_inTransaction;
    }

    /**
     * Execute a query with optional parameters
     * @param string $sql SQL query
     * @param array $params Parameters to bind
     * @return $this
     */
    public function query($sql, $params = array()) {
        $this->_error = false;
        
        try {
            if ($this->_query = $this->_pdo->prepare($sql)) {
                $x = 1;
                if (count($params)) {
                    foreach ($params as $param) {
                        $this->_query->bindValue($x, $param);
                        $x++;
                    }
                }

                if ($this->_query->execute()) {
                    $this->_results = $this->_query->fetchAll(PDO::FETCH_OBJ);
                    $this->_count = $this->_query->rowCount();
                } else {
                    $this->_error = true;
                }
            }
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            if ($this->_inTransaction) {
                $this->rollback();
            }
            error_log('Query Error: ' . $e->getMessage() . ' in query: ' . $sql);
        }
        
        return $this;
    }

    /**
     * Perform database action with where clause
     * @param string $action SQL action
     * @param string $table Table name
     * @param array $where Where conditions
     * @return $this|bool
     */
    public function action($action, $table, $where = array()) {
        if (count($where) === 3) {
            $operators = array('=', '>', '<', '>=', '<=', '<>', 'LIKE', 'NOT LIKE');

            $field    = $where[0];
            $operator = $where[1];
            $value    = $where[2];

            if (in_array($operator, $operators)) {
                $sql = "{$action} FROM {$table} WHERE {$field} {$operator} ?";
                if (!$this->query($sql, array($value))->error()) {
                    return $this;
                }
            }
        }
        return false;
    }

    /**
     * Select records from table
     * @param string $table Table name
     * @param array $where Where conditions
     * @return $this|bool
     */
    public function get($table, $where) {
        return $this->action('SELECT *', $table, $where);
    }
    
    /**
     * Delete records from table
     * @param string $table Table name
     * @param array $where Where conditions
     * @return $this|bool
     */
    public function delete($table, $where) {
        return $this->action('DELETE', $table, $where);
    }

    /**
     * Execute a direct delete query with parameters
     * @param string $sql SQL delete query
     * @param array $params Query parameters
     * @return bool Success status
     */
    public function delete_this($sql, $params = []) {
        $this->_error = false;
        
        try {
            if ($this->_query = $this->_pdo->prepare($sql)) {
                // Bind parameters
                if (count($params)) {
                    for ($i = 0; $i < count($params); $i++) {
                        $this->_query->bindValue($i + 1, $params[$i]);
                    }
                }
                
                // Execute the query
                if ($this->_query->execute()) {
                    $this->_count = $this->_query->rowCount();
                    return true;
                } else {
                    $this->_error = true;
                }
            }
        } catch (PDOException $e) {
            $this->_error = true;
            $this->_errorMessage = $e->getMessage();
            error_log('Delete This Error: ' . $e->getMessage() . ' in query: ' . $sql);
            if ($this->_inTransaction) {
                $this->rollback();
            }
        }
        
        return false;
    }

    /**
     * Insert record into table
     * @param string $table Table name
     * @param array $fields Fields and values
     * @return bool Success status
     */
    public function insert($table, $fields = array()) {
        if (count($fields)) {
            $keys = array_keys($fields);
            $values = "";
            $x = 1;

            foreach ($fields as $field) {
                $values .= "?";
                if ($x < count($fields)) {
                    $values .= ', ';
                }
                $x++;
            }

            $sql = "INSERT INTO {$table} (`" . implode('`, `', $keys) . "`) VALUES ({$values})";

            if (!$this->query($sql, $fields)->error()) {
                $this->_lastInsertId = $this->_pdo->lastInsertId();
                return true;
            }
        }

        return false;
    }

	/**
	 * Execute a direct insert query with parameters
	 * @param string $sql SQL insert query
	 * @param array $params Query parameters
	 * @return int|bool Last insert ID on success, false on failure
	*/
	public function insert_this($sql, $params = []) {
		$this->_error = false;
		
		try {
			if ($this->_query = $this->_pdo->prepare($sql)) {
				// Bind parameters
				if (count($params)) {
					for ($i = 0; $i < count($params); $i++) {
						$this->_query->bindValue($i + 1, $params[$i]);
					}
				}
				
				// Execute the query
				if ($this->_query->execute()) {
					$this->_lastInsertId = $this->_pdo->lastInsertId();
					$this->_count = $this->_query->rowCount();
					return $this->_lastInsertId;
				} else {
					$this->_error = true;
				}
			}
		} catch (PDOException $e) {
			$this->_error = true;
			$this->_errorMessage = $e->getMessage();
			error_log('Insert This Error: ' . $e->getMessage() . ' in query: ' . $sql);
			if ($this->_inTransaction) {
				$this->rollback();
			}
		}
		
		return false;
	}

    /**
     * Update record by ID
     * @param string $table Table name
     * @param int $id Record ID
     * @param array $fields Fields to update
     * @return bool Success status
     */
    public function update($table, $id, $fields) {
        $set = '';
        $x = 1;

        foreach ($fields as $name => $value) {
            $set .= "{$name} = ?";
            if ($x < count($fields)) {
                $set .= ', ';
            }
            $x++;
        }

        $sql = "UPDATE {$table} SET {$set} WHERE id = ?";
        $fields[] = $id; // Add ID to params

        return !$this->query($sql, $fields)->error();
    }

    /**
     * Update records by multiple field conditions
     * @param string $table Table name
     * @param array $identifiers Identifying fields
     * @param array $fields Fields to update
     * @return bool Success status
     */
    public function updateByFields($table, $identifiers, $fields) {
        // Construct the SET clause
        $set = '';
        $where = '';
        $x = 1;
        $params = array();
    
        foreach ($fields as $name => $value) {
            $set .= "{$name} = ?";
            if ($x < count($fields)) {
                $set .= ', ';
            }
            $params[] = $value;
            $x++;
        }
    
        // Construct the WHERE clause
        $y = 1;
        foreach ($identifiers as $field => $value) {
            $where .= "{$field} = ?";
            if ($y < count($identifiers)) {
                $where .= ' AND ';
            }
            $params[] = $value;
            $y++;
        }
    
        // Construct the full SQL statement
        $sql = "UPDATE {$table} SET {$set} WHERE {$where}";
    
        return !$this->query($sql, $params)->error();
    }
    
    /**
     * Update records by conditions
     * @param string $table Table name
     * @param array $fields Fields to update
     * @param array $conditions Where conditions
     * @return bool Success status
     */
    public function updateByConditions($table, $fields = [], $conditions = []) {
        if (empty($fields) || empty($conditions)) {
            return false;
        }
        
        // Building the SET part
        $setPart = '';
        $params = [];
        $x = 1;
    
        foreach ($fields as $field => $value) {
            $setPart .= "{$field} = ?";
            $params[] = $value;
            if ($x < count($fields)) {
                $setPart .= ', ';
            }
            $x++;
        }
    
        // Building the WHERE part
        $wherePart = '';
        $y = 1;
    
        foreach ($conditions as $field => $value) {
            $wherePart .= "{$field} = ?";
            $params[] = $value;
            if ($y < count($conditions)) {
                $wherePart .= ' AND ';
            }
            $y++;
        }
    
        $sql = "UPDATE {$table} SET {$setPart} WHERE {$wherePart}";
    
        return !$this->query($sql, $params)->error();
    }
	
	/**
	 * Execute a direct update query with parameters
	 * @param string $sql SQL update query
	 * @param array $params Query parameters
	 * @return bool Success status
	 */
	public function update_this($sql, $params = []) {
		$this->_error = false;
		
		try {
			if ($this->_query = $this->_pdo->prepare($sql)) {
				// Bind parameters
				if (count($params)) {
					for ($i = 0; $i < count($params); $i++) {
						$this->_query->bindValue($i + 1, $params[$i]);
					}
				}
				
				// Execute the query
				if ($this->_query->execute()) {
					$this->_count = $this->_query->rowCount();
					return true;
				} else {
					$this->_error = true;
				}
			}
		} catch (PDOException $e) {
			$this->_error = true;
			$this->_errorMessage = $e->getMessage();
			error_log('Update This Error: ' . $e->getMessage() . ' in query: ' . $sql);
			if ($this->_inTransaction) {
				$this->rollback();
			}
		}
		
		return false;
	}

    /**
     * Execute a raw query with parameters
     * @param string $sql SQL query
     * @param array $params Query parameters
     * @return bool Success status
     */
    public function executeQuery($sql, $params = array()) {
        $this->_error = false;
        try {
            if ($this->_query = $this->_pdo->prepare($sql)) {
                $x = 1;
                if (count($params)) {
                    foreach ($params as $param) {
                        $this->_query->bindValue($x, $param);
                        $x++;
                    }
                }
        
                if ($this->_query->execute()) {
                    $this->_results = $this->_query->fetchAll(PDO::FETCH_OBJ);
                    $this->_count = $this->_query->rowCount();
                    return true;
                } else {
                    $this->_error = true;
                }
            }
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Execute Query Error: ' . $e->getMessage());
            if ($this->_inTransaction) {
                $this->rollback();
            }
        }
        return false;
    }

    /**
     * Get all records from table
     * @param string $table Table name
     * @return array Results
     */
    public function getTable($table) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table}");
            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Table Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get record by field
     * @param string $table Table name
     * @param string $in Field name
     * @param mixed $value Field value
     * @return object|null Result
     */
    public function getIOrN($table, $in, $value) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} WHERE {$in} = ?");
            $this->_query->bindValue(1, $value);
            $this->_query->execute();
            $this->_results = $this->_query->fetchObject();
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get IOrN Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get records by field value
     * @param string $table Table name
     * @param string $filed Field name
     * @param mixed $value Field value
     * @return array Results
     */
    public function getFiled($table, $filed, $value) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} WHERE {$filed} = ?");
            $this->_query->bindValue(1, $value);
            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Filed Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get records with limit
     * @param string $table Table name
     * @param int $limit Result limit
     * @return array Results
     */
    public function getTableByLimit($table, $limit) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} ORDER BY id ASC LIMIT ?");
            $this->_query->bindValue(1, $limit, PDO::PARAM_INT);
            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Table By Limit Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Execute a custom SQL query with bound parameters.
     *
     * @param string $sql The SQL query with placeholders.
     * @param array $params Indexed array of values to bind (starts at 0).
     * @return array|false Returns result set as associative array or false on error.
     */
    public function getThisQuery(string $sql, array $params = []) {
        try {
            $this->_query = $this->_pdo->prepare($sql);

            // Bind values to placeholders
            foreach ($params as $index => $value) {
                $type = PDO::PARAM_STR;

                if (is_int($value)) {
                    $type = PDO::PARAM_INT;
                } elseif (is_bool($value)) {
                    $type = PDO::PARAM_BOOL;
                } elseif (is_null($value)) {
                    $type = PDO::PARAM_NULL;
                }

                // Note: PDO uses 1-based indexing for bindValue
                $this->_query->bindValue($index + 1, $value, $type);
            }

            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            $this->_error = false;
            $this->_errorMessage = null;

            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
            $this->_errorMessage = $e->getMessage();

            // Optionally log or handle errors based on environment
            error_log('[DB ERROR] ' . $e->getMessage());

            return false;
        }
    }

    
    /**
     * Execute custom query with values, limit and offset
     * @param string $exuquery SQL query
     * @param array $values Query parameters
     * @param int|null $limit Result limit
     * @param int|null $offset Result offset
     * @return array Results
     */
    public function getThisQueryWith($exuquery, $values = [], $limit = null, $offset = null) {
        try {
            // Build the query with placeholders for limit and offset
            $query = $exuquery;
            
            // Append LIMIT and OFFSET to the query if provided
            if ($limit !== null) {
                $query .= " LIMIT ?";
                $values[] = (int)$limit;
            }
        
            if ($offset !== null) {
                $query .= " OFFSET ?";
                $values[] = (int)$offset;
            }
        
            // Prepare and execute the query
            $this->_query = $this->_pdo->prepare($query);
        
            // Bind values
            if (!empty($values)) {
                $x = 1;
                foreach ($values as $value) {
                    $this->_query->bindValue($x, $value);
                    $x++;
                }
            }
        
            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get This Query With Error: ' . $e->getMessage());
            return [];
        }
    }
    
    /**
     * Get specific fields from table
     * @param string $table Table name
     * @param array $fileds Fields to retrieve
     * @return array Results
     */
    public function getFields($table, $fileds) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT " . implode(", ", $fileds) . " FROM {$table}");
            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Fields Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get specific field from table by condition
     * @param string $table Table name
     * @param string $champ Field to retrieve
     * @param string $where Condition field
     * @param mixed $value Condition value
     * @return object|null Result
     */
    public function getThisChamp($table, $champ, $where, $value) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT {$champ} FROM {$table} WHERE {$where} = ?");
            $this->_query->bindValue(1, $value);
            $this->_query->execute();
            $this->_results = $this->_query->fetchObject();
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get This Champ Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get specific field by two conditions
     * @param string $table Table name
     * @param string $champ Field to retrieve
     * @param string $field1 First condition field
     * @param mixed $value1 First condition value
     * @param string $field2 Second condition field
     * @param mixed $value2 Second condition value
     * @return object|null Result
     */
    public function getThisChampBy2($table, $champ, $field1, $value1, $field2, $value2) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT {$champ} FROM {$table} WHERE {$field1} = ? AND {$field2} = ?");
            $this->_query->bindValue(1, $value1);
            $this->_query->bindValue(2, $value2);
            $this->_query->execute();
            $this->_results = $this->_query->fetchObject();
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get This Champ By2 Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get last record from table
     * @param string $table Table name
     * @return object|null Result
     */
    public function getLast($table) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} ORDER BY id DESC LIMIT 1");
            $this->_query->execute();
            $this->_results = $this->_query->fetchObject();
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Last Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get records by field with value greater than or equal to
     * @param string $table Table name
     * @param string $filed Field name
     * @param mixed $value Minimum value
     * @param int $limit Result limit
     * @return array Results
     */
    public function getFiledByLimit($table, $filed, $value, $limit) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} WHERE {$filed} >= ? ORDER BY id ASC LIMIT ?");
            $this->_query->bindValue(1, $value);
            $this->_query->bindValue(2, $limit, PDO::PARAM_INT);
            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Filed By Limit Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get records ordered by field with limit
     * @param string $table Table name
     * @param string $field Field to order by
     * @param int $limit Result limit
     * @return array Results
     */
    public function getQuery($table, $field, $limit) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} ORDER BY {$field} DESC LIMIT ?");
            $this->_query->bindValue(1, $limit, PDO::PARAM_INT);
            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Query Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get records with LIKE condition
     * @param string $table Table name
     * @param string $filed Field to search
     * @param string $value Search value
     * @return array Results
     */
    public function getLike($table, $filed, $value) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} WHERE {$filed} LIKE ?");
            $this->_query->bindValue(1, '%' . $value . '%');
            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Like Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get first record by field value
     * @param string $table Table name
     * @param string $field Field name
     * @param mixed $value Field value
     * @return object|null Result
     */
    public function getFirstByField($table, $field, $value) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} WHERE {$field} = ? ORDER BY id ASC LIMIT 1");
            $this->_query->bindValue(1, $value);
            $this->_query->execute();
            $this->_results = $this->_query->fetchObject();
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get First By Field Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get last record by field value
     * @param string $table Table name
     * @param string $field Field name
     * @param mixed $value Field value
     * @return object|null Result
     */
    public function getLastByField($table, $field, $value) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} WHERE {$field} = ? ORDER BY id DESC LIMIT 1");
            $this->_query->bindValue(1, $value);
            $this->_query->execute();
            $this->_results = $this->_query->fetchObject();
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Last By Field Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get records by two field conditions
     * @param string $table Table name
     * @param string $field1 First field name
     * @param mixed $value1 First field value
     * @param string $field2 Second field name
     * @param mixed $value2 Second field value
     * @return array Results
     */
    public function getBy2Champs($table, $field1, $value1, $field2, $value2) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT * FROM {$table} WHERE {$field1} = ? AND {$field2} = ? ORDER BY id ASC");
            $this->_query->bindValue(1, $value1);
            $this->_query->bindValue(2, $value2);
            $this->_query->execute();
            $this->_results = $this->_query->fetchAll(PDO::FETCH_ASSOC);
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get By 2 Champs Error: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get field by three conditions
     * @param string $table Table name
     * @param string $champ Field to retrieve
     * @param string $field1 First field name
     * @param mixed $value1 First field value
     * @param string $field2 Second field name
     * @param mixed $value2 Second field value
     * @param string $field3 Third field name
     * @param mixed $value3 Third field value
     * @return object|null Result
     */
    public function getFieldBy3($table, $champ, $field1, $value1, $field2, $value2, $field3, $value3) {
        try {
            $this->_query = $this->_pdo->prepare("SELECT {$champ} FROM {$table} WHERE {$field1} = ? AND {$field2} = ? AND {$field3} = ? ORDER BY id ASC");
            $this->_query->bindValue(1, $value1);
            $this->_query->bindValue(2, $value2);
            $this->_query->bindValue(3, $value3);
            $this->_query->execute();
            $this->_results = $this->_query->fetchObject();
            $this->_count = $this->_query->rowCount();
            return $this->_results;
        } catch (PDOException $e) {
            $this->_error = true;
			$this->_errorMessage = $e->getMessage();
            error_log('Get Field By 3 Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get first result
     * @return mixed First result
     */
    public function results() {
        return isset($this->_results[0]) ? $this->_results[0] : null;
    }

    /**
     * Get first result
     * @return mixed First result
     */
    public function first() {
        return isset($this->_results[0]) ? $this->_results[0] : null;
    }

    /**
     * Get result at index
     * @param int $i Result index
     * @return mixed Result at index
     */
    public function firsts($i) {
        return isset($this->_results[$i]) ? $this->_results[$i] : null;
    }

    /**
     * Get last insert ID
     * @return mixed Last insert ID
     */
    public function getLastInsertId() {
        return $this->_lastInsertId;
    }

    /**
     * Get error status
     * @return bool Error status
     */
    public function error() {
        return $this->_error;
    }

	/**
	 * Get the last error message
	 * @return string|null Error message or null if no error
	 */
	public function getError() {
		return $this->_errorMessage;
	}

    /**
     * Get result count
     * @return int Result count
     */
    public function count() {
        return $this->_count;
    }
}