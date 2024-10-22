<?php

class DatabaseManager
{
    private ?SQLite3 $db = null;
    private string $dbPath;
    private array $errors = [];

    public function __construct(string $dbPath)
    {
        $this->dbPath = $dbPath;
    }

    public function connect(): bool
    {
        try {
            // Check if directory exists, if not create it
            $directory = dirname($this->dbPath);
            if (!is_dir($directory)) {
                mkdir($directory, 0755, true);
            }

            // Create/Connect to SQLite database
            $this->db = new SQLite3($this->dbPath);
            
            // Enable foreign keys
            $this->db->exec('PRAGMA foreign_keys = ON');
            
            // Set secure file permissions
            chmod($this->dbPath, 0640);
            
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Connection error: " . $e->getMessage();
            return false;
        }
    }

    public function initializeTables(): bool
    {
        if (!$this->db) {
            $this->errors[] = "Database connection not established";
            return false;
        }

        try {
            // Begin transaction
            $this->db->exec('BEGIN TRANSACTION');

            // Create tables
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL UNIQUE,
                    email TEXT NOT NULL UNIQUE,
                    password_hash TEXT NOT NULL,
                    created_at INTEGER NOT NULL,
                    updated_at INTEGER NOT NULL
                );

                CREATE INDEX IF NOT EXISTS idx_users_email ON users(email);
                CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

                CREATE TABLE IF NOT EXISTS settings (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    setting_key TEXT NOT NULL,
                    setting_value TEXT,
                    created_at INTEGER NOT NULL,
                    updated_at INTEGER NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE(user_id, setting_key)
                );

                CREATE INDEX IF NOT EXISTS idx_settings_user ON settings(user_id);
            ");

            // Commit transaction
            $this->db->exec('COMMIT');
            return true;

        } catch (Exception $e) {
            // Rollback on error
            $this->db->exec('ROLLBACK');
            $this->errors[] = "Table creation error: " . $e->getMessage();
            return false;
        }
    }

    public function checkTableExists(string $tableName): bool
    {
        if (!$this->db) {
            $this->errors[] = "Database connection not established";
            return false;
        }

        try {
            $result = $this->db->querySingle(
                "SELECT name FROM sqlite_master WHERE type='table' AND name=:name",
                ['name' => $tableName]
            );
            return !empty($result);
        } catch (Exception $e) {
            $this->errors[] = "Table check error: " . $e->getMessage();
            return false;
        }
    }

    public function getTableInfo(string $tableName): array
    {
        if (!$this->db) {
            $this->errors[] = "Database connection not established";
            return [];
        }

        try {
            $result = $this->db->query("PRAGMA table_info(" . SQLite3::escapeString($tableName) . ")");
            $columns = [];
            while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
                $columns[] = $row;
            }
            return $columns;
        } catch (Exception $e) {
            $this->errors[] = "Table info error: " . $e->getMessage();
            return [];
        }
    }

    public function backup(string $backupPath): bool
    {
        if (!$this->db) {
            $this->errors[] = "Database connection not established";
            return false;
        }

        try {
            // Close the current connection
            $this->db->close();

            // Copy the database file
            if (!copy($this->dbPath, $backupPath)) {
                throw new Exception("Failed to create backup");
            }

            // Reconnect to the database
            $this->connect();

            return true;
        } catch (Exception $e) {
            $this->errors[] = "Backup error: " . $e->getMessage();
            return false;
        }
    }

    public function vacuum(): bool
    {
        if (!$this->db) {
            $this->errors[] = "Database connection not established";
            return false;
        }

        try {
            $this->db->exec('VACUUM');
            return true;
        } catch (Exception $e) {
            $this->errors[] = "Vacuum error: " . $e->getMessage();
            return false;
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getDatabase(): ?SQLite3
    {
        return $this->db;
    }

    public function __destruct()
    {
        if ($this->db) {
            $this->db->close();
        }
    }
}

// Usage example
try {
    // Create database manager instance
    $dbManager = new DatabaseManager(__DIR__ . '/data/myapp.sqlite');

    // Connect to database (creates it if it doesn't exist)
    if (!$dbManager->connect()) {
        echo "Failed to connect to database:\n";
        print_r($dbManager->getErrors());
        exit(1);
    }

    // Initialize tables
    if (!$dbManager->initializeTables()) {
        echo "Failed to initialize tables:\n";
        print_r($dbManager->getErrors());
        exit(1);
    }

    // Check if a table exists
    if ($dbManager->checkTableExists('users')) {
        echo "Users table exists!\n";
        
        // Get table information
        $tableInfo = $dbManager->getTableInfo('users');
        echo "Users table structure:\n";
        print_r($tableInfo);
    }

    // Create a backup
    $backupPath = __DIR__ . '/data/myapp_backup_' . date('Y-m-d') . '.sqlite';
    if ($dbManager->backup($backupPath)) {
        echo "Backup created successfully at: $backupPath\n";
    }

    // Optimize the database
    if ($dbManager->vacuum()) {
        echo "Database optimized successfully\n";
    }

    // Get database instance for direct queries
    $db = $dbManager->getDatabase();
    if ($db) {
        // Example query
        $result = $db->query('SELECT COUNT(*) as count FROM users');
        $count = $result->fetchArray(SQLITE3_ASSOC)['count'];
        echo "Number of users: $count\n";
    }

} catch (Exception $e) {
    echo "An error occurred: " . $e->getMessage() . "\n";
}

function createDatabase(string $dbPath): ?SQLite3 {
    try {
        // Create directory if it doesn't exist
        $directory = dirname($dbPath);
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        // Create/Connect to database
        $db = new SQLite3($dbPath);
        
        // Enable foreign keys
        $db->exec('PRAGMA foreign_keys = ON');
        
        // Set secure permissions
        chmod($dbPath, 0640);
        
        return $db;
    } catch (Exception $e) {
        echo "Error creating database: " . $e->getMessage() . "\n";
        return null;
    }
}

// Usage
$db = createDatabase(__DIR__ . '/data/simple.sqlite');
if ($db) {
    echo "Database created successfully!\n";
}