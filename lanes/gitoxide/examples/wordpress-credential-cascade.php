<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

$fixture = require __DIR__ . '/../fixtures/wordpress-credential-cascade.php';

return [
    'identity' => $fixture['identity'],
    'contextPath' => $fixture['contextPath'],
    'nextActionPath' => $fixture['nextActionContext']['path'],
    'verbatimHelperPath' => $fixture['verbatimHelperContext']['path'],
    'storedCredentialContexts' => count($fixture['storePayloads']),
    'erasedCredentialContexts' => count($fixture['erasePayloads']),
    'completeQuitIdentity' => $fixture['completeQuitIdentity'],
    'completeQuitPropagated' => $fixture['completeQuitPropagated'],
    'secretsInDiagnosticLog' => $fixture['secretsInDiagnosticLog'],
    'wordpressUse' => $fixture['wordpressUse'],
];
