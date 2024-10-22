<?php

use OpenAI\Client;

class VectorEmbedding {
    private $openai;
    
    public function generateEmbedding(string $text): array {
        $response = $this->openai->embeddings()->create([
            'model' => 'text-embedding-ada-002',
            'input' => $text
        ]);
        
        return $response['data'][0]['embedding'];
    }
}