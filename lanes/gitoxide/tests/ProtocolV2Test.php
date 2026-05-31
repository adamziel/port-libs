<?php

declare(strict_types=1);

use PortLibs\Gitoxide\LsRefsCommand;
use PortLibs\Gitoxide\ProtocolCapabilities;

$packet = static fn (string $payload): string => sprintf('%04x', strlen($payload) + 4) . $payload;
$delimiter = '0001';
$flush = '0000';

return [
    'parses v1 capability bytes like gix-transport' => static function (TestRunner $t): void {
        $input = "7814e8a05a59c0cf5fb186661d1551c75d1299b5 HEAD\0"
            . 'multi_ack thin-pack side-band side-band-64k ofs-delta shallow deepen-since deepen-not deepen-relative '
            . 'no-progress include-tag multi_ack_detailed symref=HEAD:refs/heads/master object-format=sha1 agent=git/2.28.0';

        $parsed = ProtocolCapabilities::fromV1Bytes($input);
        $capabilities = $parsed['capabilities'];

        $t->same(45, $parsed['delimiterPosition']);
        $t->same([
            'multi_ack',
            'thin-pack',
            'side-band',
            'side-band-64k',
            'ofs-delta',
            'shallow',
            'deepen-since',
            'deepen-not',
            'deepen-relative',
            'no-progress',
            'include-tag',
            'multi_ack_detailed',
            'symref',
            'object-format',
            'agent',
        ], $capabilities->names());
        $t->same(true, $capabilities->capability('object-format')?->supports('sha1'));
        $t->same(false, $capabilities->capability('object-format')?->supports('sha2'));
        $t->same('HEAD:refs/heads/master', $capabilities->symrefs()[0]->value);
    },
    'parses protocol v2 packet-line capability advertisements like gix-transport' => static function (TestRunner $t) use ($packet, $flush): void {
        $capabilities = ProtocolCapabilities::fromV2PacketLines(
            $packet("version 2\n")
                . $packet("agent=git/github-gdf51a71f0236\n")
                . $packet("ls-refs\n")
                . $packet("fetch=shallow filter\n")
                . $packet("server-option\n")
                . $flush
        );

        $t->same(['agent', 'ls-refs', 'fetch', 'server-option'], $capabilities->names());
        $t->same('git/github-gdf51a71f0236', $capabilities->capability('agent')?->value);
        $t->same(['shallow', 'filter'], $capabilities->capability('fetch')?->values());

        $noNewlines = ProtocolCapabilities::fromV2PacketLines(
            $packet('version 2')
                . $packet('ls-refs')
                . $packet('fetch=filter ref-in-want sideband-all packfile-uris wait-for-done shallow')
                . $packet('session-id')
                . $flush
        );

        $t->same(['ls-refs', 'fetch', 'session-id'], $noNewlines->names());
        $t->same(true, $noNewlines->capability('fetch')?->supports('wait-for-done'));
        $t->throws(RuntimeException::class, static fn () => ProtocolCapabilities::fromV2PacketLines($packet("ERR repository unavailable\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => ProtocolCapabilities::fromV2PacketLines('0003'));
    },
    'parses v2 capabilities and builds ls-refs command arguments' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV2Lines("version 2\nls-refs=unborn\nfetch=shallow filter ref-in-want sideband-all packfile-uris\nagent=git/2.44.0\n");
        $command = LsRefsCommand::create([
            'refs/tags',
            'HEAD',
            'main',
            'refs/heads/main',
            'refs/tags',
            'HEAD',
            'refs/heads/feature',
            'refs/heads/main',
        ], $capabilities, 'port-libs/0.1');

        $t->same(['ls-refs', 'fetch', 'agent'], $capabilities->names());
        $t->same(true, $capabilities->capability('ls-refs')?->supports('unborn'));
        $t->same(['agent'], $command->features());
        $t->same([
            'symrefs',
            'peel',
            'unborn',
            'ref-prefix refs/tags',
            'ref-prefix HEAD',
            'ref-prefix main',
            'ref-prefix refs/heads/main',
            'ref-prefix refs/heads/feature',
        ], $command->arguments());
        $command->validate();
    },
    'builds upstream-shaped ls-refs protocol v2 request packet lines' => static function (TestRunner $t) use ($packet, $delimiter, $flush): void {
        $capabilities = ProtocolCapabilities::fromV2PacketLines(
            $packet("version 2\n")
                . $packet("ls-refs=unborn\n")
                . $packet("fetch=shallow filter\n")
                . $flush
        );
        $command = LsRefsCommand::create(['HEAD', 'refs/heads/', 'refs/tags'], $capabilities, 'git/2.28.0');

        $t->same(['agent=git/2.28.0'], $command->requestFeatureLines());
        $t->same(
            $packet("command=ls-refs\n")
                . $packet("agent=git/2.28.0\n")
                . $delimiter
                . $packet("symrefs\n")
                . $packet("peel\n")
                . $packet("unborn\n")
                . $packet("ref-prefix HEAD\n")
                . $packet("ref-prefix refs/heads/\n")
                . $packet("ref-prefix refs/tags\n")
                . $flush,
            $command->requestBytes()
        );

        $badAgent = LsRefsCommand::create(['HEAD'], $capabilities, "bad\nagent");
        $t->throws(InvalidArgumentException::class, static fn () => $badAgent->requestBytes());
    },
    'validates ls-refs argument prefixes and unsupported features' => static function (TestRunner $t): void {
        $capabilities = ProtocolCapabilities::fromV2Lines("version 2\nother=do-not-matter\n");

        LsRefsCommand::create(['hello/'], $capabilities)->validate();

        $badArgument = (static function () use ($capabilities): LsRefsCommand {
            return new LsRefsCommand($capabilities, [], ['definitely-nothing-we-know']);
        })->bindTo(null, LsRefsCommand::class);
        $t->throws(InvalidArgumentException::class, static fn () => $badArgument()->validate());

        $badFeature = (static function () use ($capabilities): LsRefsCommand {
            return new LsRefsCommand($capabilities, ['some-feature-that-does-not-exist'], []);
        })->bindTo(null, LsRefsCommand::class);
        $t->throws(InvalidArgumentException::class, static fn () => $badFeature()->validate());
    },
    'parses protocol v2 ls-refs response lines' => static function (TestRunner $t): void {
        $refs = LsRefsCommand::parseV2Refs(
            "808e50d724f604f69ab93c6da2919c014667bedb HEAD symref-target:refs/heads/main\n"
                . "808e50d724f604f69ab93c6da2919c014667bedb MISSING_NAMESPACE_TARGET symref-target:(null)\n"
                . "unborn HEAD symref-target:refs/heads/main\n"
                . "unborn refs/heads/symbolic symref-target:refs/heads/target\n"
                . "808e50d724f604f69ab93c6da2919c014667bedb refs/heads/main\n"
                . "7fe1b98b39423b71e14217aa299a03b7c937d656 refs/tags/foo peeled:808e50d724f604f69ab93c6da2919c014667bedb\n"
                . "7fe1b98b39423b71e14217aa299a03b7c937d6ff refs/tags/blaz\n"
                . "978f927e6397113757dfec6332e7d9c7e356ac25 refs/heads/symbolic symref-target:refs/tags/v1.0 peeled:4d979abcde5cea47b079c38850828956c9382a56\n"
        );

        $t->same(8, count($refs));
        $t->same('symbolic', $refs[0]->kind);
        $t->same('refs/heads/main', $refs[0]->target);
        $t->same('direct', $refs[1]->kind);
        $t->same('unborn', $refs[2]->kind);
        $t->same('refs/heads/main', $refs[2]->target);
        $t->same('direct', $refs[4]->kind);
        $t->same('peeled', $refs[5]->kind);
        $t->same('7fe1b98b39423b71e14217aa299a03b7c937d656', $refs[5]->tag);
        $t->same('808e50d724f604f69ab93c6da2919c014667bedb', $refs[5]->object);
        $t->same('symbolic', $refs[7]->kind);
        $t->same('978f927e6397113757dfec6332e7d9c7e356ac25', $refs[7]->tag);
        $t->same('4d979abcde5cea47b079c38850828956c9382a56', $refs[7]->object);
    },
    'parses protocol v2 ls-refs packet-line advertisements and remote errors' => static function (TestRunner $t) use ($packet, $flush): void {
        $refs = LsRefsCommand::parseV2PacketLines(
            $packet("808e50d724f604f69ab93c6da2919c014667bedb HEAD symref-target:refs/heads/main\n")
                . $packet('unborn refs/heads/next-release symref-target:refs/heads/main')
                . $flush
        );

        $t->same(2, count($refs));
        $t->same('symbolic', $refs[0]->kind);
        $t->same('refs/heads/main', $refs[0]->target);
        $t->same('unborn', $refs[1]->kind);
        $t->same('refs/heads/main', $refs[1]->target);
        $t->throws(RuntimeException::class, static fn () => LsRefsCommand::parseV2PacketLines($packet("ERR repository unavailable\n") . $flush));
        $t->throws(InvalidArgumentException::class, static fn () => LsRefsCommand::parseV2PacketLines('000x'));
    },
    'parses protocol v2 ls-refs sha256 advertisements like gix hash object ids' => static function (TestRunner $t) use ($packet, $flush): void {
        $head = '9b0fc92260312ce44e74ef369f5f4b4d6fd6672f54064da57e9450051e7f5a3c';
        $tag = '3b7a0f55a20c3fe6c451bb9f551e2f7f0d4091b6bb0d0ad0a39fc4fcd4b6d1a9';
        $target = '6d0f02a4db7bc9a514f45a0bb3ee4a25e0f6be41832fe3482077a9f6cf2c04d8';
        $capabilities = ProtocolCapabilities::fromV2PacketLines(
            $packet("version 2\n")
                . $packet("ls-refs=unborn\n")
                . $packet("object-format=sha256\n")
                . $flush
        );

        $refs = LsRefsCommand::parseV2PacketLines(
            $packet(strtoupper($head) . " HEAD symref-target:refs/heads/main\n")
                . $packet("{$tag} refs/tags/wp-release peeled:{$target}\n")
                . $packet("unborn refs/heads/staging symref-target:refs/heads/main\n")
                . $flush
        );

        $t->same(true, $capabilities->capability('object-format')?->supports('sha256'));
        $t->same('symbolic', $refs[0]->kind);
        $t->same($head, $refs[0]->object);
        $t->same(64, strlen($refs[0]->object ?? ''));
        $t->same('peeled', $refs[1]->kind);
        $t->same($tag, $refs[1]->tag);
        $t->same($target, $refs[1]->object);
        $t->same('unborn', $refs[2]->kind);
        $t->throws(InvalidArgumentException::class, static fn () => LsRefsCommand::parseV2RefLine(substr($head, 0, 63) . ' refs/heads/bad'));
        $t->throws(InvalidArgumentException::class, static fn () => LsRefsCommand::parseV2RefLine($tag . ' refs/tags/bad peeled:' . $target . 'f'));
    },
    'rejects malformed protocol v2 ref lines' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => LsRefsCommand::parseV2RefLine('808e50d724f604f69ab93c6da2919c014667bedb'));
        $t->throws(InvalidArgumentException::class, static fn () => LsRefsCommand::parseV2RefLine('808e50d724f604f69ab93c6da2919c014667bedb HEAD unknown:value'));
        $t->throws(RuntimeException::class, static fn () => LsRefsCommand::parseV2RefLine('unborn HEAD'));
    },
    'wordpress fixture discovers active branch release tag and unborn staging ref' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-protocol-v2-ls-refs.php';
        $capabilities = ProtocolCapabilities::fromV2PacketLines($fixture['capabilityAdvertisement']);
        $command = LsRefsCommand::create($fixture['refPrefixes'], $capabilities, 'port-libs/0.1');
        $command->validate();
        $refs = LsRefsCommand::parseV2PacketLines($fixture['responseAdvertisement']);

        $byName = [];
        foreach ($refs as $ref) {
            $byName[$ref->name] = $ref;
        }

        $t->same(true, $capabilities->capability('ls-refs')?->supports('unborn'));
        $t->same([
            'symrefs',
            'peel',
            'unborn',
            'ref-prefix HEAD',
            'ref-prefix refs/heads/main',
            'ref-prefix refs/tags/wp-release',
        ], $command->arguments());
        $t->same($fixture['requestBytes'], $command->requestBytes());
        $t->same('refs/heads/main', $byName['HEAD']->target);
        $t->same($fixture['objects']['main'], $byName['refs/heads/main']->object);
        $t->same('peeled', $byName['refs/tags/wp-release']->kind);
        $t->same($fixture['objects']['releaseObject'], $byName['refs/tags/wp-release']->object);
        $t->same('unborn', $byName['refs/heads/next-release']->kind);
    },
];
