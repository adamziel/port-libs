<?php

declare(strict_types=1);

use PortLibs\Gitoxide\SmartHttpReceivePackTransport;

$requesterCalls = 0;
$error = null;

$transport = new SmartHttpReceivePackTransport(
    'http://deploy:s3cret@git.example.test/wp-content.git',
    static function () use (&$requesterCalls): array {
        $requesterCalls++;

        return ['status' => 500, 'headers' => [], 'body' => ''];
    }
);

try {
    $transport->readAdvertisement();
} catch (RuntimeException $exception) {
    $error = $exception->getMessage();
}

return [
    'requesterCalls' => $requesterCalls,
    'error' => $error,
    'requesterReached' => $requesterCalls > 0,
    'wordpressUse' => 'A WordPress deployment tool must not leak deployment credentials from an http:// repository URL while trying to discover smart HTTP receive-pack refs.',
];
