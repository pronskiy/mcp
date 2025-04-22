<?php

use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;

function sendRequest(Process $process, InputStream $input, array $request, $waitForResponse = true)
{
    $requestStr = json_encode($request) . "\n";

    // Send the request
    $input->write($requestStr);

    // Return immediately for notifications
    if (!$waitForResponse) {
        return null;
    }

    // Read the response
    $responseStr = '';
    $startTime = microtime(true);

    while (empty($responseStr) && (microtime(true) - $startTime) < 5.0) {
        $responseStr = $process->getIncrementalOutput();
        if (empty($responseStr)) {
            usleep(100000); // 100ms
        }
    }

    $responseStr = trim($responseStr);

    // Parse and return the response
    return json_decode($responseStr, true, 512, JSON_THROW_ON_ERROR);
}

it('can initialize', function () {
    $process = new Process([
        'php',
        '-r',
        /** @lang PHP */ 
        'require "vendor/autoload.php"; 
         $server = new \Pronskiy\Mcp\Server("test-mcp-server"); 
         $server->run();'
    ]);
    $input = new InputStream();
    $process->setInput($input);
    $process->setTty(false);
    $process->setTimeout(null);
    $process->start();

    // @todo test with different protocol versions
    $protocolVersion = '2025';
    if ($protocolVersion === '2024-11-05') {
        $capabilities = [
            'supports' => [
                'filesystem' => true,
                'resources' => true,
                'utilities' => true,
                'prompt' => true
            ]
        ];
    } else {  // 2025-03-26
        $capabilities = [
            'tools' => [
                'listChanged' => true
            ],
            'resources' => true,
            'prompt' => [
                'streaming' => true
            ],
            'utilities' => true
        ];
    }

    $initializeRequest = [
        'jsonrpc' => '2.0',
        'id' => 'test-init',
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => $protocolVersion,
            'capabilities' => $capabilities,
            'clientInfo' => [
                'name' => 'TestClient',
                'version' => '1.0.0'
            ]
        ]
    ];

    $initResponse = sendRequest($process,$input, $initializeRequest);

    expect($initResponse)
        ->toBeArray()
        ->and(isset($initResponse['result']))->toBeTrue()
        ->and(isset($initResponse['result']['protocolVersion']))->toBeTrue()
    ;
});
