<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitUtils;
use PortLibs\Gitoxide\GitUtilsIntegerParseException;

$expectedQuadraticTillSecond = [
    1, 4, 9, 16, 25, 36, 49, 64, 81, 100, 121, 144, 169, 196, 225, 256, 289,
    324, 361, 400, 441, 484, 529, 576, 625, 676, 729, 784, 841, 900, 961, 1000, 1000,
];

$assertParseError = static function (TestRunner $t, string $kind, callable $callback): void {
    try {
        $callback();
    } catch (GitUtilsIntegerParseException $exception) {
        $t->same($kind, $exception->kind());
        return;
    }

    throw new RuntimeException("Expected integer parse error {$kind}");
};

return [
    'upstream gix-utils backoff random_quadratic_produces_values_in_the_correct_range' => static function (TestRunner $t) use ($expectedQuadraticTillSecond): void {
        $numIdentities = 0;
        $actualValues = GitUtils::quadraticBackoff(count($expectedQuadraticTillSecond), true);

        foreach ($actualValues as $index => $actual) {
            $expected = $expectedQuadraticTillSecond[$index];
            if ($actual === $expected) {
                $numIdentities++;
            }

            $t->true($actual * 1000 >= ($expected - 1) * 750, "value too small: {$actual} < {$expected}");
            $t->true($actual * 1000 <= ($expected + 1) * 1250, "value too big: {$actual} > {$expected}");
        }

        $t->true($numIdentities < count($expectedQuadraticTillSecond), "too many untransformed values: {$numIdentities}");
    },

    'upstream gix-utils backoff how_many_iterations_for_a_second_of_waittime' => static function (TestRunner $t): void {
        $values = GitUtils::quadraticBackoffUntilNoRemaining(1000);

        $t->same(14, count($values));
        $t->same(1015, array_sum($values), 'a little overshoot');
    },

    'upstream gix-utils backoff output_with_default_settings' => static function (TestRunner $t) use ($expectedQuadraticTillSecond): void {
        $t->same($expectedQuadraticTillSecond, GitUtils::quadraticBackoff(33));
    },

    'upstream gix-utils btoi binary_to_unsigned' => static function (TestRunner $t) use ($assertParseError): void {
        $t->same(12345, GitUtils::toUnsigned('12345'));
        $assertParseError($t, 'invalid_digit', static fn () => GitUtils::toUnsigned('+1', 'u8'));
        $assertParseError($t, 'overflow', static fn () => GitUtils::toUnsigned('256', 'u8'));
    },

    'upstream gix-utils btoi binary_to_unsigned_radix' => static function (TestRunner $t): void {
        $t->same(255, GitUtils::toUnsignedWithRadix('ff', 16));
        $t->same(42, GitUtils::toUnsignedWithRadix('101010', 2));
    },

    'upstream gix-utils btoi binary_to_integer_radix' => static function (TestRunner $t): void {
        $t->same(10, GitUtils::toSignedWithRadix('a', 16));
        $t->same(10, GitUtils::toSignedWithRadix('+a', 16));
        $t->same(-42, GitUtils::toSignedWithRadix('-101010', 2));
    },

    'upstream gix-utils btoi binary_to_integer' => static function (TestRunner $t) use ($assertParseError): void {
        $t->same(123, GitUtils::toSigned('123'));
        $t->same(123, GitUtils::toSigned('+123'));
        $t->same(-123, GitUtils::toSigned('-123'));
        $assertParseError($t, 'overflow', static fn () => GitUtils::toSigned('123456789', 'u8'));
        $assertParseError($t, 'underflow', static fn () => GitUtils::toSigned('-1', 'u64'));
        $assertParseError($t, 'invalid_digit', static fn () => GitUtils::toSigned(' 42', 'i32'));
    },

    'upstream gix-utils buffers lifecycle' => static function (TestRunner $t): void {
        $buffers = GitUtils::buffers();
        $buffers = $buffers->useForeignSrc('a');

        $t->same('a', $buffers->readOnlySource());

        $pair = $buffers->srcAndDest();
        $t->same('a', $pair['src']);
        $t->same(0, strlen($pair['dest']));
        $buffers->appendDestination('b');

        $buffers->swap();

        $t->same('b', $buffers->sourceBuffer());
        $t->same(null, $buffers->readOnlySource());

        $pair = $buffers->srcAndDest();
        $t->same('b', $pair['src']);
        $t->same(0, strlen($pair['dest']), 'the original previously empty source buffer was swapped in');
        $buffers->appendDestination('c');

        $buffers->swap();
        $pair = $buffers->srcAndDest();
        $t->same('c', $pair['src']);
        $t->same(0, strlen($pair['dest']), 'dest always starting out empty');
    },

    'upstream gix-utils str decompose precomposed_unicode_is_decomposed' => static function (TestRunner $t): void {
        $precomposed = "\xC3\xA4";
        $decomposed = "a\xCC\x88";
        $actual = GitUtils::decompose($precomposed);

        $t->same(true, $actual['copied'], 'new data is produced');
        $t->same($decomposed, $actual['value']);
    },

    'upstream gix-utils str decompose already_decomposed_does_not_copy' => static function (TestRunner $t): void {
        $decomposed = "a\xCC\x88";
        $actual = GitUtils::decompose($decomposed);

        $t->same(false, $actual['copied'], 'pass-through as nothing needs to be done');
        $t->same($decomposed, $actual['value']);
    },

    'upstream gix-utils str precompose decomposed_unicode_is_precomposed' => static function (TestRunner $t): void {
        $precomposed = "\xC3\xA4";
        $decomposed = "a\xCC\x88";
        $actual = GitUtils::precompose($decomposed);

        $t->same(true, $actual['copied'], 'new data is produced');
        $t->same($precomposed, $actual['value']);
    },

    'upstream gix-utils str precompose already_precomposed_does_not_copy' => static function (TestRunner $t): void {
        $precomposed = "\xC3\xA4";
        $actual = GitUtils::precompose($precomposed);

        $t->same(false, $actual['copied'], 'pass-through as nothing needs to be done');
        $t->same($precomposed, $actual['value']);
    },
];
