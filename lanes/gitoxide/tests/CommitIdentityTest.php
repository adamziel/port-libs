<?php

declare(strict_types=1);

use PortLibs\Gitoxide\CommitIdentity;
use PortLibs\Gitoxide\CommitSignature;

return [
    'identity parser round trips upstream actor identity bytes' => static function (TestRunner $t): void {
        foreach ([
            'Sebastian Thiel <byronimo@gmail.com>',
            'Sebastian Thiel < byronimo@gmail.com>',
            "Sebastian Thiel <\tbyronimo@gmail.com \t >",
            'Sebastian Thiel <byronimo@gmail.com  >',
            ".. whitespace  \t  is explicitly allowed    - unicode aware trimming must be done elsewhere  <byronimo@gmail.com>",
        ] as $input) {
            $identity = CommitIdentity::parse($input);
            $t->same($input, $identity->storageBytes(), "roundtrip {$input}");
            $t->same(strlen($input), $identity->size(), "size {$input}");
        }
    },
    'identity parser follows gitoxide lenient delimiter behavior' => static function (TestRunner $t): void {
        $identity = CommitIdentity::parse('First Last<<fl <First Last<fl@openoffice.org >> >');
        $t->same('First Last', $identity->name);
        $t->same('fl <First Last<fl@openoffice.org >> ', $identity->email);
        $t->throws(InvalidArgumentException::class, static fn () => $identity->storageBytes());

        $newline = CommitIdentity::parse("First Last<fl <First Last<fl@openoffice.org>>\nignored");
        $t->same('First Last', $newline->name);
        $t->same('fl <First Last<fl@openoffice.org', $newline->email);
        $t->throws(InvalidArgumentException::class, static fn () => $newline->storageBytes());
    },
    'identity trim and signature actor access match gix actor boundaries' => static function (TestRunner $t): void {
        $identity = CommitIdentity::parse(" \t hello there \t < \t email \t >")->trimmed();
        $t->same('hello there', $identity->name);
        $t->same('email', $identity->email);
        $t->same('hello there <email>', $identity->storageBytes());

        $fromSignature = CommitIdentity::parse('Release Bot <release@example.test> 1711398853 +0800');
        $t->same('Release Bot <release@example.test>', $fromSignature->storageBytes());

        $signature = CommitSignature::parse('Release Bot <release@example.test> 1711398853 +0800');
        $t->same('Release Bot <release@example.test>', $signature->identity()->storageBytes());

        $t->throws(InvalidArgumentException::class, static fn () => CommitIdentity::parse('Release Bot release@example.test>'));
        $t->throws(InvalidArgumentException::class, static fn () => CommitIdentity::parse('Release Bot <release@example.test'));
        $t->throws(InvalidArgumentException::class, static fn () => (new CommitIdentity("bad\nname", 'ok'))->storageBytes());
    },
];
