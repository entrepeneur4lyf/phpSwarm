<?php


class GitDocumentationManager {
    public function importFromGit(string $repository, string $branch): void {
        $tempDir = sys_get_temp_dir() . '/' . uniqid('docs_');
        exec("git clone --branch {$branch} {$repository} {$tempDir}");
        
        // Process documentation
        $this->importFramework(
            basename($repository, '.git'),
            $branch,
            $tempDir
        );
        
        // Cleanup
        exec("rm -rf {$tempDir}");
    }
}