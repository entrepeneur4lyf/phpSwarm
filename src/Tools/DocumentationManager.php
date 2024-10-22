<?php

use SQLite3;

class DocumentationManager
{
    private SQLite3 $db;
    private array $supportedFormats = ['md', 'txt', 'html'];

    public function __construct(string $dbPath = 'documentation.sqlite')
    {
        $this->db = new SQLite3($dbPath);
        $this->initializeDatabase();
    }

    private function initializeDatabase(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS frameworks (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL UNIQUE,
                version TEXT NOT NULL,
                created_at INTEGER NOT NULL
            );

            CREATE TABLE IF NOT EXISTS documentation (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                framework_id INTEGER,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                path TEXT NOT NULL,
                format TEXT NOT NULL,
                last_updated INTEGER NOT NULL,
                embedding_vector TEXT,
                FOREIGN KEY (framework_id) REFERENCES frameworks(id)
            );

            CREATE VIRTUAL TABLE IF NOT EXISTS documentation_fts USING fts5(
                title, 
                content,
                content='documentation',
                content_rowid='id'
            );

            CREATE INDEX IF NOT EXISTS idx_framework_name ON frameworks(name);
            CREATE INDEX IF NOT EXISTS idx_doc_path ON documentation(path);
        ");
    }

    public function importFramework(
        string $name,
        string $version,
        string $docsPath
    ): void {
        // Begin transaction for better performance
        $this->db->exec('BEGIN TRANSACTION');

        try {
            // Insert framework
            $stmt = $this->db->prepare('
                INSERT INTO frameworks (name, version, created_at)
                VALUES (:name, :version, :created_at)
            ');

            $stmt->bindValue(':name', $name, SQLITE3_TEXT);
            $stmt->bindValue(':version', $version, SQLITE3_TEXT);
            $stmt->bindValue(':created_at', time(), SQLITE3_INTEGER);
            $stmt->execute();

            $frameworkId = $this->db->lastInsertRowID();

            // Process documentation files
            $this->processDocumentationFiles($frameworkId, $docsPath);

            $this->db->exec('COMMIT');
        } catch (Exception $e) {
            $this->db->exec('ROLLBACK');
            throw $e;
        }
    }

    private function processDocumentationFiles(int $frameworkId, string $path): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && 
                in_array($file->getExtension(), $this->supportedFormats)) {
                
                $content = file_get_contents($file->getPathname());
                $relativePath = str_replace($path, '', $file->getPathname());
                
                $stmt = $this->db->prepare('
                    INSERT INTO documentation (
                        framework_id, 
                        title, 
                        content, 
                        path, 
                        format, 
                        last_updated
                    )
                    VALUES (
                        :framework_id,
                        :title,
                        :content,
                        :path,
                        :format,
                        :last_updated
                    )
                ');

                $stmt->bindValue(':framework_id', $frameworkId, SQLITE3_INTEGER);
                $stmt->bindValue(':title', $file->getBasename('.' . $file->getExtension()), SQLITE3_TEXT);
                $stmt->bindValue(':content', $content, SQLITE3_TEXT);
                $stmt->bindValue(':path', $relativePath, SQLITE3_TEXT);
                $stmt->bindValue(':format', $file->getExtension(), SQLITE3_TEXT);
                $stmt->bindValue(':last_updated', time(), SQLITE3_INTEGER);
                
                $stmt->execute();

                // Insert into FTS table
                $docId = $this->db->lastInsertRowID();
                $this->db->exec("
                    INSERT INTO documentation_fts(rowid, title, content)
                    SELECT id, title, content FROM documentation
                    WHERE id = $docId
                ");
            }
        }
    }

    public function search(
        string $query,
        ?string $framework = null,
        int $limit = 10
    ): array {
        $sql = "
            SELECT 
                d.id,
                f.name as framework,
                d.title,
                d.content,
                d.path,
                highlight(documentation_fts, 0, '<mark>', '</mark>') as title_highlight,
                highlight(documentation_fts, 1, '<mark>', '</mark>') as content_highlight
            FROM documentation_fts
            JOIN documentation d ON documentation_fts.rowid = d.id
            JOIN frameworks f ON d.framework_id = f.id
            WHERE documentation_fts MATCH :query
        ";

        if ($framework) {
            $sql .= " AND f.name = :framework";
        }

        $sql .= " ORDER BY rank LIMIT :limit";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':query', $query, SQLITE3_TEXT);
        if ($framework) {
            $stmt->bindValue(':framework', $framework, SQLITE3_TEXT);
        }
        $stmt->bindValue(':limit', $limit, SQLITE3_INTEGER);

        $result = $stmt->execute();
        $matches = [];

        while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
            $matches[] = $row;
        }

        return $matches;
    }

    public function getDocumentation(
        string $framework,
        string $path
    ): ?array {
        $stmt = $this->db->prepare('
            SELECT 
                d.title,
                d.content,
                d.format,
                d.last_updated,
                f.name as framework,
                f.version
            FROM documentation d
            JOIN frameworks f ON d.framework_id = f.id
            WHERE f.name = :framework AND d.path = :path
        ');

        $stmt->bindValue(':framework', $framework, SQLITE3_TEXT);
        $stmt->bindValue(':path', $path, SQLITE3_TEXT);
        
        $result = $stmt->execute();
        return $result->fetchArray(SQLITE3_ASSOC);
    }
}

// Example usage
$manager = new DocumentationManager();

// Import a framework's documentation
$manager->importFramework(
    'Laravel',
    '10.x',
    '/path/to/laravel/docs'
);

// Search documentation
$results = $manager->search('middleware', 'Laravel');
foreach ($results as $result) {
    echo "Found in {$result['framework']}: {$result['title']}\n";
    echo $result['content_highlight'] . "\n\n";
}

// Get specific documentation
$doc = $manager->getDocumentation('Laravel', '/middleware/basic.md');
if ($doc) {
    echo "Documentation for {$doc['title']}:\n";
    echo $doc['content'] . "\n";
}
