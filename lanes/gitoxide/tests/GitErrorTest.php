<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitError;

$debugString = static fn (?GitError $error): ?string => $error?->debugString();
$displayWithLocation = static fn (GitError $error): string => $error->displayWithLocation();

return [
    'upstream auto_chain_error.rs from_exn_error cfg-not-tree-error' => static function (TestRunner $t) use ($debugString): void {
        $err = GitError::fromExnMessage('one');

        $t->same('one', $err->display());
        $t->same('Message("one")', $err->debugString());
        $t->same("Message(\n    \"one\",\n)", $err->debugPrettyString());
        $t->same(null, $debugString($err->source()));
    },

    'upstream auto_chain_error.rs from_exn_error_tree cfg-not-tree-error' => static function (TestRunner $t) use ($debugString, $displayWithLocation): void {
        $err = GitError::fromNewTreeError();

        $t->same('topmost', $err->display());
        $t->same([
            'topmost, at gix-error/tests/auto_chain_error.rs:23',
            'E6, at gix-error/tests/auto_chain_error.rs:87',
            'E5, at gix-error/tests/auto_chain_error.rs:79',
            'E4, at gix-error/tests/auto_chain_error.rs:82',
            'E8, at gix-error/tests/auto_chain_error.rs:85',
            'E3, at gix-error/tests/auto_chain_error.rs:71',
            'E10, at gix-error/tests/auto_chain_error.rs:74',
            'E12, at gix-error/tests/auto_chain_error.rs:77',
            'E2, at gix-error/tests/auto_chain_error.rs:81',
            'E7, at gix-error/tests/auto_chain_error.rs:84',
            'E1, at gix-error/tests/auto_chain_error.rs:70',
            'E9, at gix-error/tests/auto_chain_error.rs:73',
            'E11, at gix-error/tests/auto_chain_error.rs:76',
        ], array_map($displayWithLocation, $err->sources()));
        $t->same('Message("E6")', $debugString($err->source()), 'The source is the first child');
        $t->same('E6', $err->probableCause()->display(), 'we get the top-most error that has most causes');
    },

    'upstream auto_chain_error.rs from_any_error' => static function (TestRunner $t) use ($debugString): void {
        $err = GitError::fromAnyError('one');

        $t->same('one', $err->display());
        $t->same('Message("one")', $err->debugString());
        $t->same("Message(\n    \"one\",\n)", $err->debugPrettyString());
        $t->same(null, $debugString($err->source()));
        $t->same('one', $err->probableCause()->display());
    },
];
