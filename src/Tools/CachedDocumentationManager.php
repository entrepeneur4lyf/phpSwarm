<?php

class CachedDocumentationManager {
    private $cache;
    private $manager;
    
    public function getDocumentation(string $framework, string $path): ?array {
        $cacheKey = "doc:{$framework}:{$path}";
        
        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }
        
        $doc = $this->manager->getDocumentation($framework, $path);
        if ($doc) {
            $this->cache->set($cacheKey, $doc, 3600);
        }
        
        return $doc;
    }
}