<?php

namespace App\Controllers;

use Dotenv\Dotenv;

class InitController extends BaseController
{
    private $dotenv;

    public function __construct()
    {
        parent::__construct();
        
        $this->dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
        $this->dotenv->load();
    }

    /**
     * Run database migration from uploaded SQL file
     */
    public function migrateFromFile()
    {
        pp("Migration from file started");
        
        try {
            // Check if file was uploaded
            if (!isset($_FILES['sql_file'])) {
                $this->sendValidationError('No SQL file uploaded', ['sql_file' => 'SQL file is required']);
                return;
            }
            
            $file = $_FILES['sql_file'];
            
            // Validate file upload
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $this->sendError('File upload failed: ' . $this->getUploadErrorMessage($file['error']), 400);
                return;
            }
            
            // Validate file type
            $allowedExtensions = ['sql'];
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            
            if (!in_array($extension, $allowedExtensions)) {
                $this->sendValidationError('Invalid file type. Only .sql files are allowed', ['sql_file' => 'Must be a .sql file']);
                return;
            }
            
            // Read SQL content
            $sqlContent = file_get_contents($file['tmp_name']);
            
            if ($sqlContent === false) {
                $this->sendError('Failed to read uploaded file', 500);
                return;
            }
            
            pp("SQL file content read successfully");
            ppp("File size: " . strlen($sqlContent) . " bytes");
            
            // Execute SQL migration
            $result = $this->executeSQLMigration($sqlContent, $file['name']);
            
            if ($result['success']) {
                $this->sendSuccess('Migration completed successfully', $result);
            } else {
                $this->sendError('Migration failed: ' . $result['error'], 500, $result);
            }
            
        } catch (\Exception $e) {
            pp("Migration exception caught:");
            ppp($e->getMessage());
            
            $this->sendServerError('Migration failed: ' . $e->getMessage());
        }
    }

    /**
     * Execute SQL migration
     */
    private function executeSQLMigration(string $sqlContent, string $filename): array
    {
        pp("Executing SQL migration");
        
        try {
            // Split SQL into individual statements
            $statements = $this->splitSQLStatements($sqlContent);
            
            pp("Found " . count($statements) . " SQL statements");
            
            $results = [];
            $successCount = 0;
            $errorCount = 0;
            
            // Begin transaction
            $this->conn->beginTransaction();
            
            foreach ($statements as $index => $statement) {
                $statement = trim($statement);
                
                if (empty($statement)) {
                    continue;
                }
                
                pp("Executing statement " . ($index + 1));
                
                try {
                    $result = RunQuery([
                        'conn' => $this->conn,
                        'query' => $statement,
                        'withSuccess' => true
                    ]);
                    
                    if (isset($result['error'])) {
                        throw new \Exception($result['error']);
                    }
                    
                    $results[] = [
                        'statement' => $index + 1,
                        'success' => true,
                        'affected_rows' => $result['affected_rows'] ?? 0
                    ];
                    
                    $successCount++;
                    
                } catch (\Exception $e) {
                    $results[] = [
                        'statement' => $index + 1,
                        'success' => false,
                        'error' => $e->getMessage(),
                        'sql' => substr($statement, 0, 100) . '...'
                    ];
                    
                    $errorCount++;
                    pp("Statement " . ($index + 1) . " failed: " . $e->getMessage());
                }
            }
            
            if ($errorCount > 0) {
                // Rollback transaction on any error
                $this->conn->rollBack();
                
                return [
                    'success' => false,
                    'error' => "Migration failed with $errorCount errors",
                    'total_statements' => count($statements),
                    'success_count' => $successCount,
                    'error_count' => $errorCount,
                    'results' => $results
                ];
            } else {
                // Commit transaction
                $this->conn->commit();
                
                return [
                    'success' => true,
                    'message' => 'All statements executed successfully',
                    'filename' => $filename,
                    'total_statements' => count($statements),
                    'success_count' => $successCount,
                    'results' => $results
                ];
            }
            
        } catch (\Exception $e) {
            // Rollback transaction on exception
            $this->conn->rollBack();
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Split SQL content into individual statements
     */
    private function splitSQLStatements(string $sqlContent): array
    {
        // Remove comments and split by semicolon
        $sqlContent = preg_replace('/--.*$/m', '', $sqlContent);
        $sqlContent = preg_replace('/\/\*.*?\*\//s', '', $sqlContent);
        
        $statements = explode(';', $sqlContent);
        
        // Filter out empty statements
        return array_filter(array_map('trim', $statements), function($stmt) {
            return !empty($stmt);
        });
    }

    /**
     * Get upload error message
     */
    private function getUploadErrorMessage(int $errorCode): string
    {
        switch ($errorCode) {
            case UPLOAD_ERR_INI_SIZE:
                return 'The uploaded file exceeds the upload_max_filesize directive in php.ini';
            case UPLOAD_ERR_FORM_SIZE:
                return 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form';
            case UPLOAD_ERR_PARTIAL:
                return 'The uploaded file was only partially uploaded';
            case UPLOAD_ERR_NO_FILE:
                return 'No file was uploaded';
            case UPLOAD_ERR_NO_TMP_DIR:
                return 'Missing a temporary folder';
            case UPLOAD_ERR_CANT_WRITE:
                return 'Failed to write file to disk';
            case UPLOAD_ERR_EXTENSION:
                return 'A PHP extension stopped the file upload';
            default:
                return 'Unknown upload error';
        }
    }
}
