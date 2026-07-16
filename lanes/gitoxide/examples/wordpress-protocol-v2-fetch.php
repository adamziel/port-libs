<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\FetchCommand;
use PortLibs\Gitoxide\ProtocolCapabilities;

$fixture = require __DIR__ . '/../fixtures/wordpress-protocol-v2-fetch.php';
$capabilities = ProtocolCapabilities::fromV2Lines($fixture['capabilities']);
$command = FetchCommand::createV2($capabilities);

$command->wantRef($fixture['targetRef']);
$command->deepen($fixture['depth']);
$command->filter($fixture['filter']);
$command->have($fixture['installedObject']);
$command->validate();

return [
    'features' => $command->features(),
    'supportsRefInWant' => $command->canUseRefInWant(),
    'supportsBloblessFilter' => $command->canUseFilter(),
    'supportsShallow' => $command->canUseShallow(),
    'initialArguments' => FetchCommand::initialV2Arguments($command->features()),
    'requestArguments' => $command->requestArguments(true),
    'isStateless' => $command->isStateless(false),
];
