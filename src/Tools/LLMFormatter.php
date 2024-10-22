<?php

class LLMFormatter {
    public function format(array $documentation): string {
        return sprintf(
            "Framework: %s\nVersion: %s\nTopic: %s\n\nContent:\n%s",
            $documentation['framework'],
            $documentation['version'],
            $documentation['title'],
            $documentation['content']
        );
    }
}