<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-pack-delta-window.php';

return [
    'oldExport' => $fixture['oldExport'],
    'newExport' => $fixture['newExport'],
    'boundedWindow' => $fixture['boundedWindow'],
    'unboundedTargetStorage' => $fixture['unboundedTargetEntry']['storage'],
    'boundedTargetStorage' => $fixture['boundedTargetEntry']['storage'],
    'boundedHasDelta' => $fixture['boundedHasDelta'],
    'boundedPackChecksum' => $fixture['boundedPackChecksum'],
    'wordpressUse' => $fixture['wordpressUse'],
];
