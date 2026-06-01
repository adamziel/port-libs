<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-credential-program.php';

return [
    'helperKinds' => $fixture['helperKinds'],
    'cacheCommand' => implode(' ', $fixture['commands']['cacheGet']),
    'tenantCommand' => implode(' ', $fixture['commands']['tenantErase']),
    'platformDefaults' => $fixture['platformDefaults'],
    'wordpressUse' => $fixture['wordpressUse'],
];
