<?php

declare(strict_types=1);

use PortLibs\Gitoxide\FetchCommand;
use PortLibs\Gitoxide\ProtocolCapabilities;

return [
    'v1 default features choose best sideband and multi_ack variants' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0multi_ack side-band side-band-64k multi_ack_detailed")['capabilities'];

        $t->same([
            'side-band-64k',
            'multi_ack_detailed',
        ], FetchCommand::defaultFeatures(FetchCommand::PROTOCOL_V1, $capabilities));
    },
    'v1 default features include supported fetch capabilities but leave no-progress disabled' => static function (TestRunner $t): void {
        $githubCapabilities = 'multi_ack thin-pack side-band ofs-delta shallow deepen-since deepen-not deepen-relative no-progress include-tag '
            . 'allow-tip-sha1-in-want allow-reachable-sha1-in-want no-done symref=HEAD:refs/heads/main filter agent=git/github';
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0{$githubCapabilities}")['capabilities'];

        $t->same([
            'multi_ack',
            'thin-pack',
            'side-band',
            'ofs-delta',
            'shallow',
            'deepen-since',
            'deepen-not',
            'deepen-relative',
            'include-tag',
            'allow-tip-sha1-in-want',
            'allow-reachable-sha1-in-want',
            'no-done',
            'filter',
        ], FetchCommand::defaultFeatures(FetchCommand::PROTOCOL_V1, $capabilities));
    },
    'v2 default features and initial arguments follow advertised fetch values' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV2Lines("version 2\nfetch=shallow filter ref-in-want sideband-all packfile-uris\n");
        $command = FetchCommand::createV2($capabilities);

        $t->same([
            'shallow',
            'filter',
            'ref-in-want',
            'sideband-all',
            'packfile-uris',
        ], $command->features());
        $t->same(['thin-pack', 'ofs-delta', 'sideband-all'], $command->arguments());
        $t->same(['thin-pack', 'ofs-delta', 'sideband-all'], FetchCommand::initialV2Arguments($command->features()));
        $t->same(true, $command->canUsePackfileUris());
    },
    'v1 first want bakes default features and include-tag only when enabled' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0include-tag thin-pack ofs-delta shallow")['capabilities'];
        $withoutIncludeTag = FetchCommand::createV1($capabilities);
        $withoutIncludeTag->want('FF333369DE1221F9BFBBE03A3A13E9A09BC1FFFF');

        $withIncludeTag = FetchCommand::createV1($capabilities);
        $withIncludeTag->useIncludeTag();
        $withIncludeTag->want('ff333369de1221f9bfbbe03a3a13e9a09bc1ffff');

        $t->same([
            'want ff333369de1221f9bfbbe03a3a13e9a09bc1ffff thin-pack ofs-delta shallow',
        ], $withoutIncludeTag->requestArguments());
        $t->same([
            'want ff333369de1221f9bfbbe03a3a13e9a09bc1ffff thin-pack ofs-delta shallow include-tag',
        ], $withIncludeTag->requestArguments());
    },
    'v1 request arguments put first want before shallow and have lines' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV1Bytes("\0feature-a shallow deepen-since deepen-not")['capabilities'];
        $command = FetchCommand::createV1($capabilities);

        $command->deepen(1);
        $command->shallow('7b333369de1221f9bfbbe03a3a13e9a09bc1c9ff');
        $command->want('7b333369de1221f9bfbbe03a3a13e9a09bc1c907');
        $command->deepenSince(12345);
        $command->deepenNot('refs/heads/main');
        $command->have('0000000000000000000000000000000000000000');
        $command->validate();

        $t->same([
            'want 7b333369de1221f9bfbbe03a3a13e9a09bc1c907 shallow deepen-since deepen-not',
            'deepen 1',
            'shallow 7b333369de1221f9bfbbe03a3a13e9a09bc1c9ff',
            'deepen-since 12345',
            'deepen-not refs/heads/main',
            'have 0000000000000000000000000000000000000000',
            'done',
        ], $command->requestArguments(true));
    },
    'v2 builds shallow blobless ref-in-want negotiation arguments' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV2Lines("version 2\nfetch=shallow filter ref-in-want sideband-all packfile-uris\n");
        $command = FetchCommand::createV2($capabilities);

        $command->useIncludeTag();
        $command->addFeature('no-progress');
        $command->wantRef('refs/heads/main');
        $command->deepen(1);
        $command->deepenSince(12345);
        $command->deepenRelative();
        $command->deepenNot('refs/tags/wp-release');
        $command->filter('blob:none');
        $command->want('7b333369de1221f9bfbbe03a3a13e9a09bc1c907');
        $command->have('1111111111111111111111111111111111111111');
        $command->validate();

        $t->same(false, $command->isEmpty());
        $t->same(true, $command->isStateless(false));
        $t->same([
            'thin-pack',
            'ofs-delta',
            'sideband-all',
            'include-tag',
            'no-progress',
            'want-ref refs/heads/main',
            'deepen 1',
            'deepen-since 12345',
            'deepen-relative',
            'deepen-not refs/tags/wp-release',
            'filter blob:none',
            'want 7b333369de1221f9bfbbe03a3a13e9a09bc1c907',
            'have 1111111111111111111111111111111111111111',
            'done',
        ], $command->requestArguments(true));
    },
    'guards unsupported fetch feature-backed arguments' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV2Lines("version 2\nfetch=shallow\n");
        $command = FetchCommand::createV2($capabilities);

        $t->same(false, $command->canUseFilter());
        $t->same(false, $command->canUseRefInWant());
        $t->throws(LogicException::class, static fn () => $command->filter('blob:none'));
        $t->throws(LogicException::class, static fn () => $command->wantRef('refs/heads/main'));

        $badArgument = (static function () use ($capabilities): FetchCommand {
            return new FetchCommand(FetchCommand::PROTOCOL_V2, $capabilities, ['shallow'], ['thin-pack', 'want-ref refs/heads/main'], null);
        })->bindTo(null, FetchCommand::class);
        $t->throws(InvalidArgumentException::class, static fn () => $badArgument()->validate());
    },
    'validates unknown fetch arguments and unsupported capabilities' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV2Lines("version 2\nfetch=shallow\n");

        $badArgument = (static function () use ($capabilities): FetchCommand {
            return new FetchCommand(FetchCommand::PROTOCOL_V2, $capabilities, ['shallow'], ['definitely-nothing-we-know'], null);
        })->bindTo(null, FetchCommand::class);
        $t->throws(InvalidArgumentException::class, static fn () => $badArgument()->validate());

        $badFeature = (static function () use ($capabilities): FetchCommand {
            return new FetchCommand(FetchCommand::PROTOCOL_V2, $capabilities, ['filter'], ['thin-pack'], null);
        })->bindTo(null, FetchCommand::class);
        $t->throws(InvalidArgumentException::class, static fn () => $badFeature()->validate());
    },
    'wordpress fixture builds a blobless shallow fetch request for deployment refs' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v2-fetch.php';
        $capabilities = ProtocolCapabilities::fromV2Lines($fixture['capabilities']);
        $command = FetchCommand::createV2($capabilities);

        $command->wantRef($fixture['targetRef']);
        $command->deepen($fixture['depth']);
        $command->filter($fixture['filter']);
        $command->have($fixture['installedObject']);
        $command->validate();

        $t->same(true, $command->canUseRefInWant());
        $t->same(true, $command->canUseFilter());
        $t->same(true, $command->canUseShallow());
        $t->same([
            'thin-pack',
            'ofs-delta',
            'sideband-all',
            'want-ref refs/heads/main',
            'deepen 1',
            'filter blob:none',
            'have 58f4f2be1f149a49f7234f4bbd3b1b8c92a6d61a',
            'done',
        ], $command->requestArguments(true));
    },
];
