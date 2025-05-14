<?php
require_once 'config.php';

/**
 * Database connection class
 */
class Database
{
    private $host = "localhost";
    private $username = "root";
    private $password = "";
    private $database = "airport_management";
    private $conn;

    /**
     * Create a new database connection
     */
    public function __construct()
    {
        $this->connect();
    }

    /**
     * Connect to the database
     */
    private function connect()
    {
        try {
            $this->conn = new mysqli($this->host, $this->username, $this->password, $this->database);
            
            // Check connection
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            // Set charset to utf8
            $this->conn->set_charset("utf8");
        } catch (Exception $e) {
            // Log error
            error_log("Database connection error: " . $e->getMessage());
            die("Database connection failed. Please contact support.");
        }
    }

    /**
     * Get the database connection
     */
    public function getConnection()
    {
        return $this->conn;
    }

    /**
     * Close the database connection
     */
    public function closeConnection()
    {
        if ($this->conn) {
            $this->conn->close();
        }
    }

    /**
     * Execute a query with prepared statements
     * 
     * @param string $query SQL query with placeholders
     * @param string $types Types of parameters (i: integer, d: double, s: string, b: blob)
     * @param array $params Array of parameters to bind
     * @return array|bool Query result as an associative array or false on failure
     */
    public function executeQuery($query, $types = null, $params = [])
    {
        try {
            $stmt = $this->conn->prepare($query);
            
            if (!$stmt) {
                throw new Exception("Query preparation failed: " . $this->conn->error);
            }
            
            // Bind parameters if any
            if ($types && $params) {
                $stmt->bind_param($types, ...$params);
            }
            
            // Execute the query
            if (!$stmt->execute()) {
                throw new Exception("Query execution failed: " . $stmt->error);
            }
            
            // Get the result
            $result = $stmt->get_result();
            
            // If the query was a SELECT
            if ($result) {
                $data = [];
                while ($row = $result->fetch_assoc()) {
                    $data[] = $row;
                }
                $stmt->close();
                return $data;
            } 
            // For INSERT, UPDATE, DELETE queries
            else {
                $affected_rows = $stmt->affected_rows;
                $insert_id = $stmt->insert_id;
                $stmt->close();
                return [
                    'affected_rows' => $affected_rows,
                    'insert_id' => $insert_id
                ];
            }
        } catch (Exception $e) {
            // Log error
            error_log("Query error: " . $e->getMessage());
            return false;
        }
    }
}

// Create a global database instance
$db = new Database();
$conn = $db->getConnection();

// Register a shutdown function to close the connection
register_shutdown_function(function() use ($db) {
    $db->closeConnection();
});
?>