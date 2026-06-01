<?php

declare(strict_types=1);

use PortLibs\Gitoxide\ProtocolCapabilities;
use PortLibs\Gitoxide\PushCommand;
use PortLibs\Gitoxide\PushUpdate;

$packetPayloads = static function (string $bytes): array {
    $payloads = [];
    $offset = 0;
    $length = strlen($bytes);
    while ($offset + 4 <= $length) {
        $size = hexdec(substr($bytes, $offset, 4));
        $offset += 4;
        if ($size === 0) {
            break;
        }
        $payloadLength = $size - 4;
        $payloads[] = substr($bytes, $offset, $payloadLength);
        $offset += $payloadLength;
    }

    return [$payloads, substr($bytes, $offset)];
};

return [
    'push updates format create update and delete ref commands' => static function (TestRunner $t): void {
        $old = 'AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
        $new = 'BBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBBB';

        $create = PushUpdate::create($new, 'refs/heads/main');
        $update = PushUpdate::update($old, $new, 'refs/heads/main');
        $delete = PushUpdate::delete($old, 'refs/tags/wp-release');

        $t->same(true, $create->isCreate());
        $t->same(false, $create->isDelete());
        $t->same('0000000000000000000000000000000000000000 bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb refs/heads/main', $create->commandLine());
        $t->same('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb refs/heads/main', $update->commandLine());
        $t->same(true, $delete->isDelete());
        $t->same('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa 0000000000000000000000000000000000000000 refs/tags/wp-release', $delete->commandLine());
        $t->throws(InvalidArgumentException::class, static fn () => PushUpdate::create('bad', 'refs/heads/main'));
        $t->throws(InvalidArgumentException::class, static fn () => PushUpdate::create($new, 'main'));
    },
    'push command builds receive-pack update request with first-line capabilities' => static function (TestRunner $t) use ($packetPayloads): void {
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0report-status report-status-v2 side-band side-band-64k object-format=sha1 atomic push-options")['capabilities'];
        $command = PushCommand::create($capabilities, 'port-libs/0.1');
        $command->useAtomic();
        $command->updateRef(
            '7C09BA0C4C3680AF369BDA4FC8E3C58D3FCCDC76',
            '32690D87D3943C7C0DDA81246D0CDE344CA7E633',
            'refs/heads/main'
        );
        $command->deleteRef('1111111111111111111111111111111111111111', 'refs/tags/old-release');
        $command->validate();

        $t->same([
            'report-status-v2',
            'side-band-64k',
            'object-format=sha1',
            'agent=port-libs/0.1',
            'atomic',
        ], $command->features());
        $lines = $command->commandLines();
        $t->same("7c09ba0c4c3680af369bda4fc8e3c58d3fccdc76 32690d87d3943c7c0dda81246d0cde344ca7e633 refs/heads/main\0 report-status-v2 side-band-64k object-format=sha1 agent=port-libs/0.1 atomic", $lines[0]);
        $t->same('1111111111111111111111111111111111111111 0000000000000000000000000000000000000000 refs/tags/old-release', $lines[1]);

        [$payloads, $remaining] = $packetPayloads($command->requestBytes('PACK'));
        $t->same($lines, $payloads);
        $t->same('PACK', $remaining);
    },
    'push command sends push options after update flush' => static function (TestRunner $t) use ($packetPayloads): void {
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0report-status side-band push-options")['capabilities'];
        $command = PushCommand::create($capabilities);
        $command->createRef(str_repeat('a', 40), 'refs/heads/feature/wp-import');
        $command->addPushOption('ci.skip');
        $command->addPushOption('deploy=staging');

        [$commands, $afterCommands] = $packetPayloads($command->requestBytes());
        [$options, $remaining] = $packetPayloads($afterCommands);

        $t->same(['report-status', 'side-band', 'push-options'], $command->features());
        $t->same(1, count($commands));
        $t->same(['ci.skip', 'deploy=staging'], $options);
        $t->same('', $remaining);
    },
    'push command caps request packet lines at upstream gix-packetline maximum' => static function (TestRunner $t) use ($packetPayloads): void {
        $maxPayloadLength = 65516;
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0report-status push-options")['capabilities'];
        $commandPrefix = str_repeat('0', 40)
            . ' '
            . str_repeat('a', 40)
            . ' refs/heads/main'
            . "\0 report-status agent=";
        $agentLength = $maxPayloadLength - strlen($commandPrefix);
        $command = PushCommand::create($capabilities, str_repeat('a', $agentLength));
        $command->createRef(str_repeat('a', 40), 'refs/heads/main');

        $requestBytes = $command->requestBytes();
        [$commands] = $packetPayloads($requestBytes);
        $t->same('fff0', substr($requestBytes, 0, 4));
        $t->same($maxPayloadLength, strlen($commands[0]));

        $tooLongCommand = PushCommand::create($capabilities, str_repeat('a', $agentLength + 1));
        $tooLongCommand->createRef(str_repeat('a', 40), 'refs/heads/main');
        $t->throws(InvalidArgumentException::class, static fn () => $tooLongCommand->requestBytes());

        $maxOptionCommand = PushCommand::create($capabilities);
        $maxOptionCommand->createRef(str_repeat('b', 40), 'refs/heads/main');
        $maxOptionCommand->addPushOption(str_repeat('p', $maxPayloadLength));
        [, $afterCommands] = $packetPayloads($maxOptionCommand->requestBytes());
        [$options] = $packetPayloads($afterCommands);
        $t->same($maxPayloadLength, strlen($options[0]));

        $tooLongOptionCommand = PushCommand::create($capabilities);
        $tooLongOptionCommand->createRef(str_repeat('c', 40), 'refs/heads/main');
        $tooLongOptionCommand->addPushOption(str_repeat('p', $maxPayloadLength + 1));
        $t->throws(InvalidArgumentException::class, static fn () => $tooLongOptionCommand->requestBytes());
    },
    'push command guards unsupported capabilities and empty updates' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0report-status")['capabilities'];
        $command = PushCommand::create($capabilities);

        $t->throws(InvalidArgumentException::class, static fn () => $command->validate());
        $t->throws(LogicException::class, static fn () => $command->useAtomic());
        $t->throws(LogicException::class, static fn () => $command->addPushOption('ci.skip'));
        $t->throws(InvalidArgumentException::class, static fn () => PushCommand::create($capabilities, "bad\nagent"));

        $sha1Only = ProtocolCapabilities::fromV1Bytes("\0object-format=sha1")['capabilities'];
        $t->throws(InvalidArgumentException::class, static fn () => PushCommand::create($sha1Only, null, 'sha256'));
    },
    'wordpress fixture builds deploy branch and release tag push request' => static function (TestRunner $t) use ($packetPayloads): void {
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0report-status-v2 side-band-64k object-format=sha1")['capabilities'];
        $command = PushCommand::create($capabilities, 'port-libs/wordpress');

        $command->updateRef(
            '58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a',
            '7b333369de1221f9bfbbe03a3a13e9a09bc1c907',
            'refs/heads/main'
        );
        $command->createRef('7b333369de1221f9bfbbe03a3a13e9a09bc1c907', 'refs/tags/wp-release');

        [$payloads] = $packetPayloads($command->requestBytes('PACK'));

        $t->same(true, str_contains($payloads[0], 'refs/heads/main'));
        $t->same(true, str_contains($payloads[0], "\0 report-status-v2 side-band-64k object-format=sha1 agent=port-libs/wordpress"));
        $t->same('0000000000000000000000000000000000000000 7b333369de1221f9bfbbe03a3a13e9a09bc1c907 refs/tags/wp-release', $payloads[1]);
    },
];
