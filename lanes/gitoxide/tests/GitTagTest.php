<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitObject;
use PortLibs\Gitoxide\GitTag;

return [
    'parses sha256 annotated tags with signature split at line boundary' => static function (TestRunner $t): void {
        $target = 'abcdef0123456789abcdef0123456789abcdef0123456789abcdef0123456789';
        $body = "object ABCDEF0123456789abcdef0123456789abcdef0123456789abcdef0123456789\n"
            . "type commit\n"
            . "tag v2.0.0-sha256\n"
            . "tagger Release Bot <release@example.com> 1710007200 +0530\n"
            . "\n"
            . "Release v2.0.0\n\n"
            . "- ship sha256 object support\n"
            . "- include annotated tag signatures\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "sha256-tag-signature\n"
            . "-----END PGP SIGNATURE-----\n";

        $tag = GitTag::parse($body, 'sha256');
        $tagger = $tag->taggerSignature();

        $t->same($target, $tag->target);
        $t->same('commit', $tag->targetKind);
        $t->same('v2.0.0-sha256', $tag->name);
        $t->same('Release Bot', $tagger?->name);
        $t->same(19800, $tagger?->offsetSeconds());
        $t->same("Release v2.0.0\n\n- ship sha256 object support\n- include annotated tag signatures", $tag->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\nsha256-tag-signature\n-----END PGP SIGNATURE-----\n", $tag->pgpSignature);

        $object = GitObject::fromStorageBytes('tag ' . strlen($body) . "\0" . $body);
        $t->same('v2.0.0-sha256', GitTag::parse($object->body, 'sha256')->name);
    },
    'tag signature marker follows gitoxide line boundary semantics' => static function (TestRunner $t): void {
        $prefix = "object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type commit\n";

        $notAtLineStart = GitTag::parse($prefix
            . "tag pgp-marker-in-message\n\n"
            . "message text\n"
            . "not-a-signature -----BEGIN PGP SIGNATURE-----\n"
            . "body\n"
            . "-----END PGP SIGNATURE-----");
        $t->same("message text\nnot-a-signature -----BEGIN PGP SIGNATURE-----\nbody\n-----END PGP SIGNATURE-----", $notAtLineStart->message);
        $t->same(null, $notAtLineStart->pgpSignature);

        $withTrailingText = GitTag::parse($prefix
            . "tag pgp-signature-with-trailing-text\n\n"
            . "message text\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "body\n"
            . "-----END PGP SIGNATURE-----\n"
            . "trailing text");
        $t->same('message text', $withTrailingText->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\nbody\n-----END PGP SIGNATURE-----\ntrailing text", $withTrailingText->pgpSignature);

        $withoutEndMarker = GitTag::parse($prefix
            . "tag pgp-signature-without-end-marker\n\n"
            . "message text\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "body");
        $t->same('message text', $withoutEndMarker->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\nbody", $withoutEndMarker->pgpSignature);

        $atBodyStart = GitTag::parse($prefix
            . "tag pgp-signature-at-body-start\n\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "body");
        $t->same('', $atBodyStart->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\nbody", $atBodyStart->pgpSignature);
    },
    'parses optional taggers empty messages and tree tags' => static function (TestRunner $t): void {
        $treeTag = GitTag::parse("object c39ae07f393806ccf406ef966e9a15afc43cc36a\n"
            . "type tree\n"
            . "tag v2.6.11-tree\n"
            . "\n"
            . "This is the 2.6.11 tree object.\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "tree-signature\n");
        $t->same('tree', $treeTag->targetKind);
        $t->same(null, $treeTag->tagger);
        $t->same('This is the 2.6.11 tree object.', $treeTag->message);
        $t->same("-----BEGIN PGP SIGNATURE-----\ntree-signature\n", $treeTag->pgpSignature);

        $empty = GitTag::parse("object 01dd4e2a978a9f5bd773dae6da7aa4a5ac1cdbbc\n"
            . "type commit\n"
            . "tag empty\n"
            . "tagger Sebastian Thiel <sebastian.thiel@icloud.com> 1592381636 +0800\n"
            . "\n");
        $t->same("\n", $empty->message);

        $withoutTimestamp = GitTag::parse("object 4fcd840c4935e4c7a5ea3552710a0f26b9178c24\n"
            . "type commit\n"
            . "tag ChangeLog\n"
            . "tagger shemminger <shemminger>\n");
        $t->same('', $withoutTimestamp->message);
        $t->same('', $withoutTimestamp->taggerSignature()?->time);
    },
    'tag writer roundtrips upstream body shapes and exposes iterator tokens' => static function (TestRunner $t): void {
        $signed = "object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type commit\n"
            . "tag 1.0.0\n"
            . "tagger Sebastian Thiel <byronimo@gmail.com> 1528473343 +0230\n"
            . "\n"
            . "for the signature\n"
            . "-----BEGIN PGP SIGNATURE-----\n"
            . "signed-release-tag\n"
            . "-----END PGP SIGNATURE-----";
        $tag = GitTag::parse($signed);

        $t->same($signed, $tag->storageBytes());
        $t->same(strlen($signed), $tag->size());
        $t->same('tag', $tag->object()->type);
        $t->same($signed, $tag->object()->body);
        $t->same([
            ['type' => 'target', 'id' => 'ffa700b4aca13b80cb6b98a078e7c96804f8e0ec'],
            ['type' => 'targetKind', 'kind' => 'commit'],
            ['type' => 'name', 'name' => '1.0.0'],
            ['type' => 'tagger', 'signature' => 'Sebastian Thiel <byronimo@gmail.com> 1528473343 +0230'],
            [
                'type' => 'body',
                'message' => 'for the signature',
                'pgpSignature' => "-----BEGIN PGP SIGNATURE-----\nsigned-release-tag\n-----END PGP SIGNATURE-----",
            ],
        ], $tag->tokens());

        $empty = "object 01dd4e2a978a9f5bd773dae6da7aa4a5ac1cdbbc\n"
            . "type commit\n"
            . "tag empty\n"
            . "tagger Sebastian Thiel <sebastian.thiel@icloud.com> 1592381636 +0800\n"
            . "\n";
        $t->same($empty, GitTag::parse($empty)->storageBytes());

        $emptyMissingNewline = "object 01dd4e2a978a9f5bd773dae6da7aa4a5ac1cdbbc\n"
            . "type commit\n"
            . "tag empty\n"
            . "tagger Sebastian Thiel <sebastian.thiel@icloud.com> 1592381636 +0800\n";
        $t->same($emptyMissingNewline, GitTag::parse($emptyMissingNewline)->storageBytes());

        $owned = new GitTag('FFA700B4ACA13B80CB6B98A078E7C96804F8E0EC', 'commit', 'owned-normalized', null, 'release notes');
        $t->same('ffa700b4aca13b80cb6b98a078e7c96804f8e0ec', $owned->target);
        $t->same('ffa700b4aca13b80cb6b98a078e7c96804f8e0ec', $owned->rawTarget);
        $t->contains("object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n", $owned->storageBytes());
    },
    'tag parser preserves raw target bytes and iterator surfaces partial errors' => static function (TestRunner $t): void {
        $uppercase = "object FFA700B4ACA13B80CB6B98A078E7C96804F8E0EC\n"
            . "type commit\n"
            . "tag uppercase-target\n"
            . "\n"
            . "message";
        $tag = GitTag::parse($uppercase);

        $t->same('ffa700b4aca13b80cb6b98a078e7c96804f8e0ec', $tag->target);
        $t->same('FFA700B4ACA13B80CB6B98A078E7C96804F8E0EC', $tag->rawTarget);
        $t->same($uppercase, $tag->storageBytes());

        $owned = $tag->toOwned();
        $t->same('ffa700b4aca13b80cb6b98a078e7c96804f8e0ec', $owned->target);
        $t->same('ffa700b4aca13b80cb6b98a078e7c96804f8e0ec', $owned->rawTarget);
        $t->contains("object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n", $owned->storageBytes());
        $t->same(false, str_contains($owned->storageBytes(), "object FFA700B4ACA13B80CB6B98A078E7C96804F8E0EC\n"));

        $tokens = GitTag::iterateTokens($uppercase);
        $t->same([
            'ok' => true,
            'token' => [
                'type' => 'target',
                'id' => 'ffa700b4aca13b80cb6b98a078e7c96804f8e0ec',
                'rawId' => 'FFA700B4ACA13B80CB6B98A078E7C96804F8E0EC',
            ],
        ], $tokens[0]);
        $t->same(['target', 'targetKind', 'name', 'tagger', 'body'], array_map(static fn (array $result): ?string => $result['token']['type'] ?? null, $tokens));

        $withoutTagger = GitTag::iterateTokens("object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type tree\n"
            . "tag no-tagger\n"
            . "\n"
            . "body");
        $t->same('tagger', $withoutTagger[3]['token']['type']);
        $t->same(null, $withoutTagger[3]['token']['signature']);

        $partial = "object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type commit\n"
            . "tag partial\n"
            . "tagger Broken <broken@example.test> 1700000000 +0000";
        $partialTokens = GitTag::iterateTokens($partial);
        $t->same(['target', 'targetKind', 'name', null], array_map(static fn (array $result): ?string => $result['token']['type'] ?? null, $partialTokens));
        $t->same(false, $partialTokens[3]['ok']);
        $t->contains('tagger header is not newline terminated', $partialTokens[3]['error'] ?? '');
    },
    'tag writer follows gitoxide tag name validation' => static function (TestRunner $t): void {
        $target = 'ffa700b4aca13b80cb6b98a078e7c96804f8e0ec';

        foreach (['v1.0.0', '0.2.1', '0-alpha1', 'release/2026.05'] as $name) {
            $t->same(true, GitTag::isValidName($name));
            GitTag::validateName($name);
            $tag = new GitTag($target, 'commit', $name, null, 'release notes');
            $t->contains("tag {$name}\n", $tag->storageBytes());
        }

        foreach (['-', '-hello', '.hidden', 'bad..range', 'bad lock', 'bad.lock', 'foo.lock/baz.lock/bar', 'bad/@{reflog', 'bad?name', "bad\rsuffix", 'bad*suffix'] as $name) {
            $t->same(false, GitTag::isValidName($name), "invalid {$name}");
            $t->throws(InvalidArgumentException::class, static fn () => GitTag::validateName($name));
            $t->throws(InvalidArgumentException::class, static fn () => (new GitTag($target, 'commit', $name, null, 'release notes'))->storageBytes());
        }
    },
    'tag name sanitizer follows gix validate byte rules' => static function (TestRunner $t): void {
        $target = 'ffa700b4aca13b80cb6b98a078e7c96804f8e0ec';

        $cases = [
            '@' => '@',
            'hello@foo' => 'hello@foo',
            '你好吗' => '你好吗',
            '😅🙌' => '😅🙌',
            'file.lock.ext' => 'file.lock.ext',
            'this_{is-fine}_too' => 'this_{is-fine}_too',
            'this_{@is-fine@}_too' => 'this_{@is-fine@}_too',
            'token.other' => 'token.other',
            'hello/world' => 'hello/world',
            'this_looks_like_a_@{reflog}' => 'this_looks_like_a_@-reflog}',
            '......' => '-',
            '//....///....///' => '-/-',
            'prefix.lock' => 'prefix',
            'prefix.lock.lock' => 'prefix',
            'prefix//suffix' => 'prefix/suffix',
            'prefix/' => 'prefix',
            '/suffix' => 'suffix',
            '.lock' => '-lock',
            'foo.lock/baz.lock/bar' => 'foo/baz/bar',
            'foo.lock/baz.lock/bar.lock' => 'foo/baz/bar',
            'foo.lock.lock/baz.lock.lock/bar.lock.lock' => 'foo/baz/bar',
            '...lock/..lock//lock' => '-lock/lock',
            'with..double-dot' => 'with.double-dot',
            '..with-double-dot' => '-with-double-dot',
            'with-double-dot..' => 'with-double-dot-',
            '*suffix' => '-suffix',
            'prefix*' => 'prefix-',
            'prefix*suffix' => 'prefix-suffix',
            "prefix\0suffix" => 'prefix-suffix',
            "prefix\x07suffix" => 'prefix-suffix',
            "prefix\x08suffix" => 'prefix-suffix',
            "prefix\x0bsuffix" => 'prefix-suffix',
            "prefix\x0csuffix" => 'prefix-suffix',
            "prefix\x1asuffix" => 'prefix-suffix',
            "prefix\x1bsuffix" => 'prefix-suffix',
            'prefix:suffix' => 'prefix-suffix',
            'prefix?suffix' => 'prefix-suffix',
            'prefix[suffix' => 'prefix-suffix',
            'prefix\\suffix' => 'prefix-suffix',
            'prefix^suffix' => 'prefix-suffix',
            'prefix~suffix' => 'prefix-suffix',
            'prefix suffix' => 'prefix-suffix',
            "prefix\tsuffix" => 'prefix-suffix',
            "prefix\nsuffix" => 'prefix-suffix',
            "prefix\rsuffix" => 'prefix-suffix',
            '.with-dot' => '-with-dot',
            'with-dot.' => 'with-dot-',
            '' => '-',
        ];

        foreach ($cases as $input => $expected) {
            $t->same($expected, GitTag::sanitizeName($input), "sanitize {$input}");
        }

        $sanitized = GitTag::sanitizeName('WordPress Export: v2026.05? beta.lock');
        $t->same(false, GitTag::isValidName('WordPress Export: v2026.05? beta.lock'));
        $t->same(true, GitTag::isValidName($sanitized));
        $tag = new GitTag($target, 'commit', $sanitized, null, 'release notes');
        $t->same('WordPress-Export--v2026.05--beta', $sanitized);
        $t->contains("tag {$sanitized}\n", $tag->storageBytes());
    },
    'tag parser rejects malformed annotated tags' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => GitTag::parse("object 00000066666666666684666666666666666299297\n"
            . "type commit\n"
            . "tag bad\n"));
        $t->throws(InvalidArgumentException::class, static fn () => GitTag::parse("object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type invalid\n"
            . "tag bad\n"));
        $t->throws(InvalidArgumentException::class, static fn () => GitTag::parse("object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type commit\n"
            . "tag \n"));
        $t->throws(InvalidArgumentException::class, static fn () => GitTag::parse("object ffa700b4aca13b80cb6b98a078e7c96804f8e0ec\n"
            . "type commit\n"
            . "tag partial\n"
            . "message without separator"));
    },
    'wordpress fixture roundtrips signed annotated release tags' => static function (TestRunner $t): void {
        $fixture = require dirname(__DIR__) . '/fixtures/wordpress-annotated-tag.php';
        $summary = require dirname(__DIR__) . '/examples/wordpress-annotated-tag.php';

        $t->same($fixture['expectedName'], $summary['name']);
        $t->same($fixture['draftReleaseName'], $summary['draftReleaseName']);
        $t->same(false, $summary['draftReleaseNameValid']);
        $t->same($fixture['expectedSanitizedDraftReleaseName'], $summary['sanitizedDraftReleaseName']);
        $t->same(true, $summary['sanitizedDraftReleaseNameValid']);
        $t->same(true, $summary['sanitizedDraftReleaseStorageHasName']);
        $t->same($fixture['expectedSanitizedDraftReleaseTarget'], $summary['sanitizedDraftReleaseTarget']);
        $t->same($fixture['expectedSanitizedDraftReleaseTarget'], $summary['sanitizedDraftReleaseRawTarget']);
        $t->same(true, $summary['sanitizedDraftReleaseStorageHasNormalizedTarget']);
        $t->same(false, $summary['sanitizedDraftReleaseStorageHasRawParsedTarget']);
        $t->same($fixture['expectedOwnedReleaseTarget'], $summary['ownedReleaseTarget']);
        $t->same($fixture['expectedOwnedReleaseTarget'], $summary['ownedReleaseRawTarget']);
        $t->same(true, $summary['ownedReleaseStorageHasNormalizedTarget']);
        $t->same(false, $summary['ownedReleaseStorageHasRawParsedTarget']);
        $t->same($fixture['expectedTarget'], $summary['target']);
        $t->same($fixture['expectedRawTarget'], $summary['rawTarget']);
        $t->same($fixture['expectedKind'], $summary['targetKind']);
        $t->same($fixture['expectedTagger'], $summary['tagger']['name']);
        $t->same($fixture['expectedMessage'], $summary['message']);
        $t->same($fixture['expectedSignature'], $summary['pgpSignature']);
        $t->same($fixture['expectedStorageSha1'], $summary['storageSha1']);
        $t->same($fixture['expectedObjectSha1'], $summary['objectSha1']);
        $t->same($fixture['expectedSize'], $summary['size']);
        $t->same(true, $summary['roundTripMatches']);
        $t->same('body', $summary['tokens'][4]['type']);
        $t->same($fixture['expectedSignature'], $summary['tokens'][4]['pgpSignature']);
        $t->same($fixture['expectedRawTarget'], $summary['tokenResults'][0]['token']['rawId']);
    },
];
