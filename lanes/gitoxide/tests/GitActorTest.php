<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CommitIdentity;
use PortLibs\Gitoxide\CommitSignature;

$assertSignature = static function (
    TestRunner $t,
    CommitSignature $signature,
    string $name,
    string $email,
    string $time,
): void {
    $t->same($name, $signature->name);
    $t->same($email, $signature->email);
    $t->same($time, $signature->time);
};

return [
    'upstream actor identity round_trip' => static function (TestRunner $t): void {
        foreach ([
            'Sebastian Thiel <byronimo@gmail.com>',
            'Sebastian Thiel < byronimo@gmail.com>',
            'Sebastian Thiel <byronimo@gmail.com  >',
            "Sebastian Thiel <\tbyronimo@gmail.com \t >",
            ".. \u{263A}\u{FE0F}Sebastian \u{738B}\u{77E5}\u{660E} Thiel\u{1F64C} .. <byronimo@gmail.com>",
            ".. whitespace  \t  is explicitly allowed    - unicode aware trimming must be done elsewhere  <byronimo@gmail.com>",
        ] as $input) {
            $identity = CommitIdentity::parse($input);
            $t->same($input, $identity->storageBytes(), "identity round trip {$input}");
            $t->same(strlen($input), $identity->size(), "identity size {$input}");
        }
    },
    'upstream actor identity lenient_parsing' => static function (TestRunner $t): void {
        foreach ([
            [
                'First Last<<fl <First Last<fl@openoffice.org >> >',
                'fl <First Last<fl@openoffice.org >> ',
            ],
            [
                "First Last<fl <First Last<fl@openoffice.org>>\n",
                'fl <First Last<fl@openoffice.org',
            ],
        ] as [$input, $expectedEmail]) {
            $identity = CommitIdentity::parse($input);
            $t->same('First Last', $identity->name);
            $t->same($expectedEmail, $identity->email);
            $t->throws(InvalidArgumentException::class, static fn () => $identity->storageBytes());
        }
    },
    'upstream actor signature write_to invalid name' => static function (TestRunner $t): void {
        $signature = new CommitSignature('invalid < middlename', 'ok', '0 +0000');

        $t->throws(InvalidArgumentException::class, static fn () => $signature->storageBytes());
    },
    'upstream actor signature write_to invalid email' => static function (TestRunner $t): void {
        $signature = new CommitSignature('ok', 'server>.example.com', '0 +0000');

        $t->throws(InvalidArgumentException::class, static fn () => $signature->storageBytes());
    },
    'upstream actor signature write_to invalid name_with_newline' => static function (TestRunner $t): void {
        $signature = new CommitSignature("hello\nnewline", 'name@example.com', '0 +0000');

        $t->throws(InvalidArgumentException::class, static fn () => $signature->storageBytes());
    },
    'upstream actor signature trim' => static function (TestRunner $t): void {
        $signature = CommitSignature::parse(" \t hello there \t < \t email \t > 1 -0030")->trimmed();

        $t->same('hello there', $signature->name);
        $t->same('email', $signature->email);
    },
    'upstream actor signature round_trip' => static function (TestRunner $t): void {
        foreach ([
            'Sebastian Thiel <byronimo@gmail.com> 1 -0030',
            'Sebastian Thiel <byronimo@gmail.com> -1500 -0030',
            ".. \u{263A}\u{FE0F}Sebastian \u{738B}\u{77E5}\u{660E} Thiel\u{1F64C} .. <byronimo@gmail.com> 1528473343 +0230",
            ".. whitespace  \t  is explicitly allowed    - unicode aware trimming must be done elsewhere  <byronimo@gmail.com> 1528473343 +0230",
        ] as $input) {
            $signature = CommitSignature::parse($input);
            $t->same($input, $signature->storageBytes(), "signature round trip {$input}");
        }
    },
    'upstream actor signature signature_ref_round_trips_with_seconds_in_offset' => static function (TestRunner $t): void {
        $input = 'Sebastian Thiel <byronimo@gmail.com> 1313584730 +051800';
        $signature = CommitSignature::parse($input);

        $t->same($input, $signature->storageBytes());
    },
    'upstream actor signature parse_timestamp_with_trailing_digits' => static function (TestRunner $t) use ($assertSignature): void {
        $assertSignature(
            $t,
            CommitSignature::parse('first last <name@example.com> 1312735823 +051800'),
            'first last',
            'name@example.com',
            '1312735823 +051800',
        );
        $assertSignature(
            $t,
            CommitSignature::parse('first last <name@example.com> 1312735823 +0518'),
            'first last',
            'name@example.com',
            '1312735823 +0518',
        );
    },
    'upstream actor signature parse_missing_timestamp' => static function (TestRunner $t) use ($assertSignature): void {
        $assertSignature(
            $t,
            CommitSignature::parse('first last <name@example.com>'),
            'first last',
            'name@example.com',
            '',
        );
    },
    'upstream actor signature decode tz_minus' => static function (TestRunner $t) use ($assertSignature): void {
        $signature = CommitSignature::parseConsuming('Sebastian Thiel <byronimo@gmail.com> 1528473343 -0230')['signature'];

        $assertSignature($t, $signature, 'Sebastian Thiel', 'byronimo@gmail.com', '1528473343 -0230');
        $t->same(1528473343, $signature->seconds());
        $t->same(['seconds' => 1528473343, 'offset' => -9000], $signature->time());
    },
    'upstream actor signature decode tz_plus' => static function (TestRunner $t) use ($assertSignature): void {
        $assertSignature(
            $t,
            CommitSignature::parseConsuming('Sebastian Thiel <byronimo@gmail.com> 1528473343 +0230')['signature'],
            'Sebastian Thiel',
            'byronimo@gmail.com',
            '1528473343 +0230',
        );
    },
    'upstream actor signature decode email_with_space' => static function (TestRunner $t) use ($assertSignature): void {
        $assertSignature(
            $t,
            CommitSignature::parseConsuming("Sebastian Thiel <\tbyronimo@gmail.com > 1528473343 +0230")['signature'],
            'Sebastian Thiel',
            "\tbyronimo@gmail.com ",
            '1528473343 +0230',
        );
    },
    'upstream actor signature decode negative_offset_0000' => static function (TestRunner $t) use ($assertSignature): void {
        $assertSignature(
            $t,
            CommitSignature::parseConsuming('Sebastian Thiel <byronimo@gmail.com> 1528473343 -0000')['signature'],
            'Sebastian Thiel',
            'byronimo@gmail.com',
            '1528473343 -0000',
        );
    },
    'upstream actor signature decode negative_offset_double_dash' => static function (TestRunner $t) use ($assertSignature): void {
        $assertSignature(
            $t,
            CommitSignature::parseConsuming('name <name@example.com> 1288373970 --700')['signature'],
            'name',
            'name@example.com',
            '1288373970 --700',
        );
    },
    'upstream actor signature decode empty_name_and_email' => static function (TestRunner $t) use ($assertSignature): void {
        $assertSignature(
            $t,
            CommitSignature::parseConsuming(' <> 12345 -1215')['signature'],
            '',
            '',
            '12345 -1215',
        );
    },
    'upstream actor signature decode invalid_signature' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => CommitSignature::parseConsuming('hello < 12345 -1215'));
    },
    'upstream actor signature decode invalid_time' => static function (TestRunner $t) use ($assertSignature): void {
        $parsed = CommitSignature::parseConsuming('hello <> abc -1215');

        $assertSignature($t, $parsed['signature'], 'hello', '', '');
        $t->same('abc -1215', $parsed['rest']);
    },
];
