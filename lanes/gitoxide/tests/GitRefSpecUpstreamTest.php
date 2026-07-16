<?php

declare(strict_types=1);

use PortLibs\Gitoxide\RefSpec;

/*
 * Exact upstream tests represented:
 * - tests/refspec/parse/fetch.rs: 16
 * - tests/refspec/parse/push.rs: 11
 * - tests/refspec/parse/invalid.rs: 7
 * - tests/refspec/spec.rs: 16
 * - tests/refspec/write.rs: 6
 */

$instruction = static function (
    string $operation,
    string $instruction,
    ?string $source,
    ?string $destination,
    ?bool $allowNonFastForward = null
): array {
    $out = [
        'operation' => $operation,
        'instruction' => $instruction,
        'source' => $source,
        'destination' => $destination,
    ];

    if ($allowNonFastForward !== null) {
        $out['allowNonFastForward'] = $allowNonFastForward;
    }

    return $out;
};

$assertInstruction = static function (
    TestRunner $t,
    string $operation,
    string $input,
    string $expectedInstruction,
    ?string $source,
    ?string $destination,
    ?bool $allowNonFastForward = null
) use ($instruction): RefSpec {
    $spec = RefSpec::parse($input, $operation);

    $t->same(
        $instruction($operation, $expectedInstruction, $source, $destination, $allowNonFastForward),
        $spec->instructionIdentity(),
        "{$operation} {$input}"
    );

    return $spec;
};

$assertFetch = static function (
    TestRunner $t,
    string $input,
    string $expectedInstruction,
    ?string $source,
    ?string $destination,
    ?bool $allowNonFastForward = null
) use ($assertInstruction): RefSpec {
    return $assertInstruction($t, RefSpec::OP_FETCH, $input, $expectedInstruction, $source, $destination, $allowNonFastForward);
};

$assertPush = static function (
    TestRunner $t,
    string $input,
    string $expectedInstruction,
    ?string $source,
    ?string $destination,
    ?bool $allowNonFastForward = null
) use ($assertInstruction): RefSpec {
    return $assertInstruction($t, RefSpec::OP_PUSH, $input, $expectedInstruction, $source, $destination, $allowNonFastForward);
};

$assertParses = static function (TestRunner $t, string $operation, string $input): void {
    $t->same($operation, RefSpec::parse($input, $operation)->operation(), "{$operation} {$input} parses");
};

$assertThrows = static function (TestRunner $t, string $operation, string $input): void {
    $t->throws(InvalidArgumentException::class, static fn () => RefSpec::parse($input, $operation));
};

return [
    'upstream parse fetch.rs deterministic tests' => static function (TestRunner $t) use ($assertFetch, $assertThrows): void {
        foreach (['main~1', '^@^{}', 'HEAD:main~1'] as $spec) {
            $assertThrows($t, RefSpec::OP_FETCH, $spec);
        }

        $hash = 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391';
        $assertFetch($t, $hash . ':', RefSpec::INSTRUCTION_FETCH_ONLY, $hash, null);
        $assertFetch($t, 'a:' . $hash, RefSpec::INSTRUCTION_FETCH_AND_UPDATE, 'a', $hash, false);

        $assertThrows($t, RefSpec::OP_FETCH, '^');
        $assertThrows($t, RefSpec::OP_FETCH, '^' . $hash);

        foreach (['^a:b', '^a:', '^:', '^:b'] as $spec) {
            $assertThrows($t, RefSpec::OP_FETCH, $spec);
        }

        $assertThrows($t, RefSpec::OP_FETCH, '^a');
        $assertThrows($t, RefSpec::OP_FETCH, '^a*');
        $assertFetch($t, '^refs/heads/a', RefSpec::INSTRUCTION_FETCH_EXCLUDE, 'refs/heads/a', null);

        $assertFetch($t, '@', RefSpec::INSTRUCTION_FETCH_ONLY, 'HEAD', null);
        $assertFetch($t, '+@', RefSpec::INSTRUCTION_FETCH_ONLY, 'HEAD', null);
        $assertFetch($t, '^@', RefSpec::INSTRUCTION_FETCH_EXCLUDE, 'HEAD', null);

        $assertFetch($t, 'src:', RefSpec::INSTRUCTION_FETCH_ONLY, 'src', null);
        $assertFetch($t, '+src:', RefSpec::INSTRUCTION_FETCH_ONLY, 'src', null);

        $assertFetch($t, 'a:b', RefSpec::INSTRUCTION_FETCH_AND_UPDATE, 'a', 'b', false);
        $assertFetch($t, '+a:b', RefSpec::INSTRUCTION_FETCH_AND_UPDATE, 'a', 'b', true);
        $assertFetch($t, 'a/*:b/*', RefSpec::INSTRUCTION_FETCH_AND_UPDATE, 'a/*', 'b/*', false);
        $assertFetch($t, '+a/*:b/*', RefSpec::INSTRUCTION_FETCH_AND_UPDATE, 'a/*', 'b/*', true);

        $assertFetch($t, ':a', RefSpec::INSTRUCTION_FETCH_AND_UPDATE, 'HEAD', 'a', false);
        $assertFetch($t, '+:a', RefSpec::INSTRUCTION_FETCH_AND_UPDATE, 'HEAD', 'a', true);

        $assertFetch($t, ':', RefSpec::INSTRUCTION_FETCH_ONLY, 'HEAD', null);
        $assertFetch($t, '+:', RefSpec::INSTRUCTION_FETCH_ONLY, 'HEAD', null);

        $assertFetch($t, '@:', RefSpec::INSTRUCTION_FETCH_ONLY, 'HEAD', null);
        $assertFetch($t, '@:HEAD', RefSpec::INSTRUCTION_FETCH_AND_UPDATE, 'HEAD', 'HEAD', false);

        $assertFetch($t, '', RefSpec::INSTRUCTION_FETCH_ONLY, 'HEAD', null);

        $assertFetch($t, 'refs/*/foo/*', RefSpec::INSTRUCTION_FETCH_ONLY, 'refs/*/foo/*', null);
        $assertFetch($t, '+refs/heads/*/release/*', RefSpec::INSTRUCTION_FETCH_ONLY, 'refs/heads/*/release/*', null);
        $assertFetch($t, 'refs/*/*/branch', RefSpec::INSTRUCTION_FETCH_ONLY, 'refs/*/*/branch', null);

        foreach (['refs/*/foo/*:refs/remotes/origin/*', 'refs/*/*:refs/remotes/*', 'a/*/c/*:b/*'] as $spec) {
            $assertThrows($t, RefSpec::OP_FETCH, $spec);
        }
    },

    'upstream parse push.rs deterministic tests' => static function (TestRunner $t) use ($assertPush, $assertThrows): void {
        $hash = 'e69de29bb2d1d6434b8b29ae775ad8c2e48c5391';

        $assertThrows($t, RefSpec::OP_PUSH, '^');
        $assertThrows($t, RefSpec::OP_PUSH, '^' . $hash);

        foreach (['^a:b', '^a:', '^:', '^:b'] as $spec) {
            $assertThrows($t, RefSpec::OP_PUSH, $spec);
        }

        $assertThrows($t, RefSpec::OP_PUSH, '^a');
        $assertThrows($t, RefSpec::OP_PUSH, '^a*');
        $assertPush($t, '^refs/heads/a', RefSpec::INSTRUCTION_PUSH_EXCLUDE, 'refs/heads/a', null);

        $assertPush($t, 'main~1:b', RefSpec::INSTRUCTION_PUSH_MATCHING, 'main~1', 'b', false);
        $assertPush($t, '+main~1:b', RefSpec::INSTRUCTION_PUSH_MATCHING, 'main~1', 'b', true);

        $assertThrows($t, RefSpec::OP_PUSH, 'a~1:b~1');
        $assertThrows($t, RefSpec::OP_PUSH, 'a~1');

        $assertPush($t, '@', RefSpec::INSTRUCTION_PUSH_MATCHING, 'HEAD', 'HEAD', false);
        $assertPush($t, '+@', RefSpec::INSTRUCTION_PUSH_MATCHING, 'HEAD', 'HEAD', true);

        $assertPush($t, 'a:b', RefSpec::INSTRUCTION_PUSH_MATCHING, 'a', 'b', false);
        $assertPush($t, '+a:b', RefSpec::INSTRUCTION_PUSH_MATCHING, 'a', 'b', true);
        $assertPush($t, 'a/*:b/*', RefSpec::INSTRUCTION_PUSH_MATCHING, 'a/*', 'b/*', false);
        $assertPush($t, '+a/*:b/*', RefSpec::INSTRUCTION_PUSH_MATCHING, 'a/*', 'b/*', true);

        $assertPush($t, ':', RefSpec::INSTRUCTION_PUSH_ALL_MATCHING_BRANCHES, null, null, false);
        $assertPush($t, '+:', RefSpec::INSTRUCTION_PUSH_ALL_MATCHING_BRANCHES, null, null, true);

        $assertPush($t, ':a', RefSpec::INSTRUCTION_PUSH_DELETE, null, 'a');
        $assertPush($t, '+:a', RefSpec::INSTRUCTION_PUSH_DELETE, null, 'a');
    },

    'upstream parse invalid.rs deterministic tests' => static function (TestRunner $t) use ($assertParses, $assertThrows): void {
        $assertThrows($t, RefSpec::OP_PUSH, '');
        $assertThrows($t, RefSpec::OP_FETCH, 'refs/heads/test:refs/remotes//test');
        $assertThrows($t, RefSpec::OP_FETCH, 'refs/heads/test:refs/remotes/ /test');

        foreach ([RefSpec::OP_FETCH, RefSpec::OP_PUSH] as $operation) {
            $assertParses($t, $operation, 'a/*/c/*');
        }

        foreach ([RefSpec::OP_FETCH, RefSpec::OP_PUSH] as $operation) {
            foreach (['a/*/c/*:x/*/y/*', 'a**:**b', '+:**/'] as $spec) {
                $assertThrows($t, $operation, $spec);
            }
        }

        $assertThrows($t, RefSpec::OP_FETCH, '^*/*');

        foreach ([RefSpec::OP_FETCH, RefSpec::OP_PUSH] as $operation) {
            foreach ([':a/*', '+:a/*', 'a*:b/c', 'a:b/*'] as $spec) {
                $assertThrows($t, $operation, $spec);
            }
        }

        foreach ([RefSpec::OP_FETCH, RefSpec::OP_PUSH] as $operation) {
            $assertParses($t, $operation, 'refs/*/a');
        }

        $assertThrows($t, RefSpec::OP_PUSH, 'HEAD:');

        $fuzzed = "\x00\x8f7@{412591259\x0dday\x0dago}:\xdb) E";
        $assertThrows($t, RefSpec::OP_FETCH, $fuzzed);
        $assertThrows($t, RefSpec::OP_PUSH, $fuzzed);
    },

    'upstream spec.rs prefix tests' => static function (TestRunner $t): void {
        $t->same('HEAD', RefSpec::parseFetch('HEAD')->prefix());
        $t->same(null, RefSpec::parseFetch('main')->prefix());
        $t->same(null, RefSpec::parseFetch('^refs/heads/main')->prefix());
        $t->same('refs/short', RefSpec::parseFetch('refs/short')->prefix());
        $t->same('refs/remote/main', RefSpec::parsePush('refs/local/main:refs/remote/main')->prefix());

        $t->same('refs/heads/main', RefSpec::parseFetch('refs/heads/main')->prefix());
        $t->same('refs/foo/bar', RefSpec::parseFetch('refs/foo/bar')->prefix());
        $t->same('refs/namespaces/foo/refs/heads/main', RefSpec::parseFetch('refs/namespaces/foo/refs/heads/main')->prefix());
        $t->same('refs/heads/', RefSpec::parseFetch('refs/heads/*:refs/remotes/origin/*')->prefix());
        $t->same('refs/namespaces/', RefSpec::parseFetch('refs/namespaces/*:refs/remotes/origin/*')->prefix());

        $t->same(null, RefSpec::parseFetch('refs/*/main:refs/*/main')->prefix());
        $t->same(null, RefSpec::parseFetch('refs/*/foo/*')->prefix());
        $t->same(null, RefSpec::parseFetch('refs/heads/[a-z.]/release/*')->prefix());

        $t->same(null, RefSpec::parseFetch('e69de29bb2d1d6434b8b29ae775ad8c2e48c5391')->prefix());
        $t->same(null, RefSpec::parseFetch('b071221ea854da2958fba3a37527ca5cf32c4ebcd71ab0b68b6b8f10f04e93ad')->prefix());
    },

    'upstream spec.rs expand_prefixes tests' => static function (TestRunner $t): void {
        $t->same(['HEAD'], RefSpec::parseFetch('HEAD')->expandPrefixes());
        $t->same([
            'main',
            'refs/main',
            'refs/tags/main',
            'refs/heads/main',
            'refs/remotes/main',
            'refs/remotes/main/HEAD',
        ], RefSpec::parseFetch('main')->expandPrefixes());
        $t->same([], RefSpec::parseFetch('^refs/heads/main')->expandPrefixes());
        $t->same(['refs/short'], RefSpec::parseFetch('refs/short')->expandPrefixes());

        $t->same(['refs/heads/main'], RefSpec::parseFetch('refs/heads/main')->expandPrefixes());
        $t->same(['refs/foo/bar'], RefSpec::parseFetch('refs/foo/bar')->expandPrefixes());
        $t->same(['refs/namespaces/foo/refs/heads/main'], RefSpec::parseFetch('refs/namespaces/foo/refs/heads/main')->expandPrefixes());
        $t->same(['refs/heads/'], RefSpec::parseFetch('refs/heads/*:refs/remotes/origin/*')->expandPrefixes());
        $t->same(['refs/namespaces/'], RefSpec::parseFetch('refs/namespaces/*:refs/remotes/origin/*')->expandPrefixes());
        $t->same(['refs/namespaces/foo/refs/heads/'], RefSpec::parseFetch('refs/namespaces/foo/refs/heads/*:refs/remotes/origin/*')->expandPrefixes());

        $t->same(['refs/remote/main'], RefSpec::parsePush('refs/local/main:refs/remote/main')->expandPrefixes());

        $t->same([], RefSpec::parseFetch('refs/*/main:refs/*/main')->expandPrefixes());
        $t->same([], RefSpec::parseFetch('refs/*/foo/*')->expandPrefixes());
        $t->same([], RefSpec::parseFetch('refs/heads/[a-z.]/release/*')->expandPrefixes());

        $t->same([], RefSpec::parseFetch('e69de29bb2d1d6434b8b29ae775ad8c2e48c5391')->expandPrefixes());
        $t->same([], RefSpec::parseFetch('b071221ea854da2958fba3a37527ca5cf32c4ebcd71ab0b68b6b8f10f04e93ad')->expandPrefixes());
    },

    'upstream write.rs instruction serialization tests' => static function (TestRunner $t): void {
        $t->same(':', RefSpec::pushAllMatchingBranches()->toString());
        $t->same('+:', RefSpec::pushAllMatchingBranches(true)->toString());
        $t->same(':for-deletion', RefSpec::pushDelete('for-deletion')->toString());
        $t->same('from:to', RefSpec::pushMatching('from', 'to')->toString());
        $t->same('+from:to', RefSpec::pushMatching('from', 'to', true)->toString());

        $t->same('refs/heads/main', RefSpec::fetchOnly('refs/heads/main')->toString());
        $t->same('^excluded', RefSpec::fetchExclude('excluded')->toString());
        $t->same('from:to', RefSpec::fetchAndUpdate('from', 'to')->toString());
        $t->same('+from:to', RefSpec::fetchAndUpdate('from', 'to', true)->toString());
    },
];
