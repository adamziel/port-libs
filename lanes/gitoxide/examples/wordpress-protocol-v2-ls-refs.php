<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\LsRefsCommand;
use PortLibs\Gitoxide\ProtocolCapabilities;

$fixture = require __DIR__ . '/../fixtures/wordpress-protocol-v2-ls-refs.php';
$capabilities = ProtocolCapabilities::fromV2PacketLines($fixture['capabilityAdvertisement']);
$command = LsRefsCommand::create($fixture['refPrefixes'], $capabilities, 'port-libs/0.1');
$command->validate();
$refs = LsRefsCommand::parseV2PacketLines($fixture['responseAdvertisement']);

$byName = [];
foreach ($refs as $ref) {
    $byName[$ref->name] = $ref;
}

return [
    'capabilities' => $capabilities->names(),
    'supportsUnborn' => $capabilities->capability('ls-refs')?->supports('unborn'),
    'arguments' => $command->arguments(),
    'requestBytesMatchFixture' => $command->requestBytes() === $fixture['requestBytes'],
    'refCount' => count($refs),
    'headTarget' => $byName['HEAD']->target,
    'mainObject' => $byName['refs/heads/main']->object,
    'releaseTagObject' => $byName['refs/tags/wp-release']->object,
    'releaseTagTarget' => $byName['refs/tags/wp-release']->tag,
    'nextReleaseKind' => $byName['refs/heads/next-release']->kind,
];
