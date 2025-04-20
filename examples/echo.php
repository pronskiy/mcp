<?php

require __DIR__ . '/../vendor/autoload.php';

(new \Pronskiy\Mcp\Server('echo-server'))
    ->tool('echo', 'Echoes text', function(string $text) {
        return $text;
    })
    ->run();
