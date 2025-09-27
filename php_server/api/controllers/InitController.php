<?php



class InitController
{
    private $conn;

    public function __construct()
    {
        require_once __DIR__ . '/../connection.php';
        require_once __DIR__ . '/../helpers/helperFunctions.php';
        $this->conn = $connpdo; // from connection.php
    }

    public function migrateFromFile($filePath = __DIR__ . '/../tables')
    {
        define('GOODREQ', true);
        if (!file_exists($filePath)) {
            die("Migration file not found: {$filePath}\n");
        }

        $sql = file_get_contents($filePath);
        return $this->runMigration($sql);
    }

    public function migrateFromString($sql)
    {
        return $this->runMigration($sql);
    }


    private function runMigration($sql)
    {
        try {
            // Wrap migration SQL with FK disable/enable
            $wrappedSql = "SET FOREIGN_KEY_CHECKS=0;\n"
                . $sql . "\n"
                . "SET FOREIGN_KEY_CHECKS=1;";

            $this->conn->exec($wrappedSql);

            echo "Migration completed successfully.\n";
            return true;
        } catch (\PDOException $e) {
            echo "Migration failed: " . $e->getMessage() . "\n";
            return false;
        }
    }
}
