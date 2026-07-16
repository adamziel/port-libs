<?php

declare(strict_types=1);

require __DIR__ . '/../../../tools/bootstrap.php';

use PortLibs\Gitoxide\ProtocolCapabilities;
use PortLibs\Gitoxide\PushCommand;

$capabilities = ProtocolCapabilities::fromV1Bytes(
    "\0report-status report-status-v2 side-band side-band-64k object-format=sha1 atomic push-options"
)['capabilities'];

$oldDeployment = '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a';
$newDeployment = '7b333369de1221f9bfbbe03a3a13e9a09bc1c907';

$command = PushCommand::create($capabilities, 'port-libs/wordpress');
$command->useAtomic();
$command->updateRef($oldDeployment, $newDeployment, 'refs/heads/main');
$command->createRef($newDeployment, 'refs/tags/wp-release');
$command->addPushOption('ci.skip');

$requestBytes = $command->requestBytes('PACK');

return [
    'features' => $command->features(),
    'commandLines' => $command->commandLines(),
    'pushOptions' => $command->pushOptions(),
    'requestByteLength' => strlen($requestBytes),
    'startsWithFirstPacket' => substr($requestBytes, 0, 4),
];
