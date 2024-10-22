<?php 

// Add support for different storage backends
interface StorageBackend {
    public function store(string $key, string $content): void;
    public function retrieve(string $key): ?string;
}

class S3Storage implements StorageBackend {
    private $s3Client;
    
    public function store(string $key, string $content): void {
        $this->s3Client->putObject([
            'Bucket' => 'documentation-bucket',
            'Key' => $key,
            'Body' => $content
        ]);
    }
    
    public function retrieve(string $key): ?string {
        try {
            $result = $this->s3Client->getObject([
                'Bucket' => 'documentation-bucket',
                'Key' => $key
            ]);
            return $result['Body']->getContents();
        } catch (Exception $e) {
            return null;
        }
    }
}