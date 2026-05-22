<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-credential-prompt.php';

return [
    'identity' => $fixture['identity'],
    'promptModes' => $fixture['promptModes'],
    'nextActionContainsUrl' => str_contains($fixture['nextActionBytes'], "url=https://git.example.test/wp-content.git\n"),
    'shellOutUsed' => $fixture['shellOutUsed'],
    'wordpressUse' => $fixture['wordpressUse'],
];
