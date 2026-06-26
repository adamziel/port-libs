<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitHash;

$assertThrowsMessage = static function (TestRunner $t, string $expectedMessage, callable $callback): void {
    try {
        $callback();
    } catch (Throwable $throwable) {
        $t->same($expectedMessage, $throwable->getMessage());
        return;
    }

    throw new RuntimeException('Expected exception was not thrown');
};

return [
    'gix-hash kind::from_str parses supported sha spellings' => static function (TestRunner $t): void {
        foreach (['sha1', 'SHA1', 'SHA-1'] as $input) {
            $t->same(GitHash::SHA1, GitHash::parseKind($input), $input);
        }
        foreach (['sha256', 'SHA256', 'SHA-256'] as $input) {
            $t->same(GitHash::SHA256, GitHash::parseKind($input), $input);
        }
    },
    'gix-hash kind display uses core.objectFormat names' => static function (TestRunner $t): void {
        $t->same('sha1', GitHash::displayKind(GitHash::SHA1));
        $t->same('sha256', GitHash::displayKind(GitHash::SHA256));
    },
    'gix-hash kind::from_hex_len picks the fitting hash kind' => static function (TestRunner $t): void {
        $t->same(GitHash::SHA1, GitHash::kindFromHexLength(0));
        $t->same(GitHash::SHA1, GitHash::kindFromHexLength(10));
        $t->same(GitHash::SHA1, GitHash::kindFromHexLength(20));
        $t->same(GitHash::SHA1, GitHash::kindFromHexLength(40));
        $t->same(GitHash::SHA256, GitHash::kindFromHexLength(41));
        $t->same(GitHash::SHA256, GitHash::kindFromHexLength(64));
    },
    'gix-hash kind::from_hex_len returns none without a fit' => static function (TestRunner $t): void {
        $t->same(null, GitHash::kindFromHexLength(65));
    },
    'gix-hash kind empty_blob returns the object id helper value' => static function (TestRunner $t): void {
        $t->same('e69de29bb2d1d6434b8b29ae775ad8c2e48c5391', GitHash::emptyBlobId(GitHash::SHA1));
    },
    'gix-hash kind empty_tree returns the object id helper value' => static function (TestRunner $t): void {
        $t->same('4b825dc642cb6eb9a060e54bf8d69288fbee4904', GitHash::emptyTreeId(GitHash::SHA1));
    },
    'gix-hash kind shortest prefers sha1 when sha1 and sha256 are available' => static function (TestRunner $t): void {
        $t->same(GitHash::SHA1, GitHash::shortestKind());
    },
    'gix-hash kind longest prefers sha256 when sha1 and sha256 are available' => static function (TestRunner $t): void {
        $t->same(GitHash::SHA256, GitHash::longestKind());
    },
    'gix-hash kind all returns sha1 then sha256' => static function (TestRunner $t): void {
        $t->same([GitHash::SHA1, GitHash::SHA256], GitHash::allKinds());
    },
    'gix-hash object_id::from_hex accepts lowercase sha1' => static function (TestRunner $t): void {
        $t->same('1234567890abcdefaaaaaaaaaaaaaaaaaaaaaaaa', GitHash::objectIdFromHex('1234567890abcdefaaaaaaaaaaaaaaaaaaaaaaaa'));
    },
    'gix-hash object_id::from_hex accepts uppercase sha1' => static function (TestRunner $t): void {
        $t->same('1234567890abcdefaaaaaaaaaaaaaaaaaaaaaaaa', GitHash::objectIdFromHex('1234567890ABCDEFAAAAAAAAAAAAAAAAAAAAAAAA'));
    },
    'gix-hash object_id::from_hex rejects non-hex characters' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'Invalid character encountered',
            static fn () => GitHash::objectIdFromHex('zzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzzz')
        );
    },
    'gix-hash object_id::from_hex rejects too-short ids' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'A hash sized 4 hexadecimal characters is invalid',
            static fn () => GitHash::objectIdFromHex('abcd')
        );
    },
    'gix-hash object_id::from_hex rejects too-long ids' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'A hash sized 41 hexadecimal characters is invalid',
            static fn () => GitHash::objectIdFromHex('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaf')
        );
    },
    'gix-hash object_id::from_bytes_or_panic accepts sha1 bytes' => static function (TestRunner $t): void {
        $t->same(GitHash::nullId(GitHash::SHA1), GitHash::objectIdFromBytes(str_repeat("\0", 20)));
    },
    'gix-hash object_id::from_bytes_or_panic accepts sha256 bytes' => static function (TestRunner $t): void {
        $t->same(GitHash::nullId(GitHash::SHA256), GitHash::objectIdFromBytes(str_repeat("\0", 32)));
    },
    'gix-hash object_id sha1 empty_blob matches git hashing' => static function (TestRunner $t): void {
        $actual = GitHash::emptyBlobId(GitHash::SHA1);
        $t->same(hash('sha1', "blob 0\0"), $actual);
        $t->same('e69de29bb2d1d6434b8b29ae775ad8c2e48c5391', $actual);
    },
    'gix-hash object_id sha1 empty_tree matches git hashing' => static function (TestRunner $t): void {
        $t->same(hash('sha1', "tree 0\0"), GitHash::emptyTreeId(GitHash::SHA1));
    },
    'gix-hash object_id sha256 empty_blob matches git hashing' => static function (TestRunner $t): void {
        $actual = GitHash::emptyBlobId(GitHash::SHA256);
        $t->same(hash('sha256', "blob 0\0"), $actual);
        $t->same('473a0f4c3be8a93681a267e3b1e9a7dcda1185436fe141f7749120a303721813', $actual);
    },
    'gix-hash object_id sha256 empty_tree matches git hashing' => static function (TestRunner $t): void {
        $t->same(hash('sha256', "tree 0\0"), GitHash::emptyTreeId(GitHash::SHA256));
    },
    'gix-hash prefix cmp_oid detects inequality for sha1' => static function (TestRunner $t): void {
        $prefix = GitHash::prefixNew('b920bbb055e1efb9080592a409d3975738b6efb3', 7);
        $t->same(1, GitHash::prefixCompareObjectId($prefix, 'a920bbb055e1efb9080592a409d3975738b6efb3'));
        $t->same(-1, GitHash::prefixCompareObjectId($prefix, 'b920bbf055e1efb9080592a409d3975738b6efb3'));
        $t->same('b920bbb', GitHash::prefixToHex($prefix));
    },
    'gix-hash prefix cmp_oid detects inequality for sha256' => static function (TestRunner $t): void {
        $prefix = GitHash::prefixNew('b920bbb055e1efb9080592a409d3975738b6efb338b6efb338b6efb338b6efb3', 7);
        $t->same(1, GitHash::prefixCompareObjectId($prefix, 'a920bbb055e1efb9080592a409d3975738b6efb338b6efb338b6efb338b6efb3'));
        $t->same(-1, GitHash::prefixCompareObjectId($prefix, 'b920bbf055e1efb9080592a409d3975738b6efb338b6efb338b6efb338b6efb3'));
        $t->same('b920bbb', GitHash::prefixToHex($prefix));
    },
    'gix-hash prefix cmp orders sha256 prefixes after sha1 prefixes' => static function (TestRunner $t): void {
        $prefixSha1 = GitHash::prefixNew('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 7);
        $prefixSha256 = GitHash::prefixNew('aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa', 7);
        $t->same(1, GitHash::prefixCompare($prefixSha256, $prefixSha1));
        $t->same(GitHash::prefixToHex($prefixSha1), GitHash::prefixToHex($prefixSha256));
    },
    'gix-hash prefix cmp_oid detects equality for sha1' => static function (TestRunner $t): void {
        $id = 'a920bbb055e1efb9080592a409d3975738b6efb3';
        $prefix = GitHash::prefixNew($id, 6);
        $t->same(0, GitHash::prefixCompareObjectId($prefix, $id));
        $t->same(0, GitHash::prefixCompareObjectId($prefix, 'a920bbffffffffffffffffffffffffffffffffff'));
        $t->same('a920bb', GitHash::prefixToHex($prefix));
    },
    'gix-hash prefix cmp_oid detects equality for sha256 and shorter candidates' => static function (TestRunner $t): void {
        $id = 'a920bbb055e1efb9080592a409d3975738b6efb338b6efb338b6efb338b6efb3';
        $prefix = GitHash::prefixNew($id, 6);
        $t->same(0, GitHash::prefixCompareObjectId($prefix, $id));
        $t->same(0, GitHash::prefixCompareObjectId($prefix, 'a920bbffffffffffffffffffffffffffffffffff'));
        $t->same(0, GitHash::prefixCompareObjectId($prefix, 'a920bbffffffffffffffffffffffffffffffffffffffffffffffffffffffffff'));
        $t->same('a920bb', GitHash::prefixToHex($prefix));
    },
    'gix-hash prefix::new accepts valid sha1 prefix lengths' => static function (TestRunner $t): void {
        $oid = 'abcdefabcdefabcdefabcdefabcdefabcdefabcd';
        for ($hexLength = 4; $hexLength < GitHash::kindLengthInHex(GitHash::SHA1); $hexLength++) {
            $expected = substr($oid, 0, $hexLength) . str_repeat('0', GitHash::kindLengthInHex(GitHash::SHA1) - $hexLength);
            $prefix = GitHash::prefixNew($oid, $hexLength);
            $t->same($expected, GitHash::prefixAsObjectId($prefix), (string) $hexLength);
            $t->same($hexLength, GitHash::prefixHexLength($prefix));
            $t->same(0, GitHash::prefixCompareObjectId($prefix, $oid));
        }
    },
    'gix-hash prefix::new accepts valid sha256 prefix lengths' => static function (TestRunner $t): void {
        $oid = 'abcdefabcdefabcdefabcdefabcdefabcdefabcdedabcdedabcdedabcdedabcd';
        for ($hexLength = 4; $hexLength < GitHash::kindLengthInHex(GitHash::SHA256); $hexLength++) {
            $expected = substr($oid, 0, $hexLength) . str_repeat('0', GitHash::kindLengthInHex(GitHash::SHA256) - $hexLength);
            $prefix = GitHash::prefixNew($oid, $hexLength);
            $t->same($expected, GitHash::prefixAsObjectId($prefix), (string) $hexLength);
            $t->same($hexLength, GitHash::prefixHexLength($prefix));
            $t->same(0, GitHash::prefixCompareObjectId($prefix, $oid));
        }
    },
    'gix-hash prefix::new rejects lengths longer than the object id' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'An object of kind sha1 cannot be larger than 40 in hex, but 41 was requested',
            static fn () => GitHash::prefixNew(GitHash::nullId(GitHash::SHA1), 41)
        );
    },
    'gix-hash prefix::new rejects too-short lengths' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'The minimum hex length of a short object id is 4, got 3',
            static fn () => GitHash::prefixNew(GitHash::nullId(GitHash::SHA1), 3)
        );
    },
    'gix-hash prefix try_from accepts 6-character ids' => static function (TestRunner $t): void {
        $prefix = GitHash::prefixFromHex('abcdef');
        $t->same(0, GitHash::prefixCompareObjectId($prefix, 'abcdefabcdefabcdefabcdefabcdefabcdefabcd'));
    },
    'gix-hash prefix try_from accepts 7-character ids' => static function (TestRunner $t): void {
        $prefix = GitHash::prefixFromHex('abcdefa');
        $t->same(0, GitHash::prefixCompareObjectId($prefix, 'abcdefabcdefabcdefabcdefabcdefabcdefabcd'));
    },
    'gix-hash prefix try_from rejects ids shorter than four chars' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'The minimum hex length of a short object id is 4, got 2',
            static fn () => GitHash::prefixFromHex('ab')
        );
    },
    'gix-hash prefix try_from rejects ids longer than every hash kind' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'An id cannot be larger than 64 chars in hex, but 70 was requested',
            static fn () => GitHash::prefixFromHex('abcdefabcdefabcdefabcdefabcdefabcdefabcd123123123123123123123123123123')
        );
    },
    'gix-hash prefix try_from rejects invalid chars' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'Invalid hex character',
            static fn () => GitHash::prefixFromHex('abcdfOsd')
        );
    },
    'gix-hash prefix::from_hex_nonempty accepts 6-character ids' => static function (TestRunner $t): void {
        $prefix = GitHash::prefixFromHexNonEmpty('abcdef');
        $t->same(0, GitHash::prefixCompareObjectId($prefix, 'abcdefabcdefabcdefabcdefabcdefabcdefabcd'));
    },
    'gix-hash prefix::from_hex_nonempty accepts 7-character ids' => static function (TestRunner $t): void {
        $prefix = GitHash::prefixFromHexNonEmpty('abcdefa');
        $t->same(0, GitHash::prefixCompareObjectId($prefix, 'abcdefabcdefabcdefabcdefabcdefabcdefabcd'));
    },
    'gix-hash prefix::from_hex_nonempty accepts one and two character ids' => static function (TestRunner $t): void {
        $oid = 'abcdefabcdefabcdefabcdefabcdefabcdefabcd';
        $t->same(0, GitHash::prefixCompareObjectId(GitHash::prefixFromHexNonEmpty('ab'), $oid));
        $t->same(0, GitHash::prefixCompareObjectId(GitHash::prefixFromHexNonEmpty('a'), $oid));
    },
    'gix-hash prefix::from_hex_nonempty rejects empty ids' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'The minimum hex length of a short object id is 4, got 0',
            static fn () => GitHash::prefixFromHexNonEmpty('')
        );
    },
    'gix-hash prefix::from_hex_nonempty rejects ids longer than every hash kind' => static function (TestRunner $t) use ($assertThrowsMessage): void {
        $assertThrowsMessage(
            $t,
            'An id cannot be larger than 64 chars in hex, but 70 was requested',
            static fn () => GitHash::prefixFromHexNonEmpty('abcdefabcdefabcdefabcdefabcdefabcdefabcd123123123123123123123123123123')
        );
    },
];
