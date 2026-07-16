<?php

declare(strict_types=1);

use PortLibs\Gitoxide\GitQuote;

return [
    'upstream quote.rs single::empty' => static function (TestRunner $t): void {
        $t->same("''", GitQuote::single(''));
    },

    'upstream quote.rs single::unquoted_becomes_quoted' => static function (TestRunner $t): void {
        $t->same("'a'", GitQuote::single('a'));
        $t->same("'a b'", GitQuote::single('a b'));
        $t->same("'a\nb'", GitQuote::single("a\nb"), 'newlines play no role');
    },

    'upstream quote.rs single::existing_exclamation_mark_gets_escaped' => static function (TestRunner $t): void {
        $t->same("'a'\\!'b'", GitQuote::single('a!b'));
        $t->same("''\\!''", GitQuote::single('!'));
        $t->same("'\\'\\!''", GitQuote::single('\\!'));
    },

    'upstream quote.rs single::existing_quote_gets_escaped' => static function (TestRunner $t): void {
        $t->same("'a'\\''b'", GitQuote::single("a'b"));
        $t->same("''\\'''", GitQuote::single("'"));
        $t->same("''\\''\\'\\'''\\'''", GitQuote::single("'\\''"));
    },

    'upstream quote.rs single::complex' => static function (TestRunner $t): void {
        $expected = "'\0cmd `arg` \$var\\" . "'\\''" . 'ring\\// arg "quoted' . "'\\!'" . '"\'';
        $input = "\0cmd `arg` \$var\\'ring\\// arg \"quoted!\"";
        $t->same($expected, GitQuote::single($input));
    },

    'upstream quote.rs ansi_c::undo::unquoted_remains_unchanged' => static function (TestRunner $t): void {
        $t->same(['value' => 'hello', 'consumed' => 5], GitQuote::ansiCUndo('hello'));
    },

    'upstream quote.rs ansi_c::undo::empty_surrounded_by_quotes' => static function (TestRunner $t): void {
        $t->same(['value' => '', 'consumed' => 2], GitQuote::ansiCUndo('""'));
    },

    'upstream quote.rs ansi_c::undo::surrounded_only_by_quotes' => static function (TestRunner $t): void {
        $t->same(['value' => 'hello', 'consumed' => 7], GitQuote::ansiCUndo('"hello"'));
    },

    'upstream quote.rs ansi_c::undo::typical_escapes' => static function (TestRunner $t): void {
        $t->same(['value' => "\n\r\t", 'consumed' => 8], GitQuote::ansiCUndo('"\\n\\r\\t"'));
    },

    'upstream quote.rs ansi_c::undo::untypical_escapes' => static function (TestRunner $t): void {
        $t->same(['value' => "\x07\x08\x0c\x0b", 'consumed' => 10], GitQuote::ansiCUndo('"\\a\\b\\f\\v"'));
    },

    'upstream quote.rs ansi_c::undo::literal_escape_and_double_quote' => static function (TestRunner $t): void {
        $t->same(['value' => "\"\\", 'consumed' => 6], GitQuote::ansiCUndo("\x22\x5c\x22\x5c\x5c\x22"));
    },

    'upstream quote.rs ansi_c::undo::unicode_byte_escapes_by_number' => static function (TestRunner $t): void {
        $input = '"\\346\\277\\261\\351\\207\\216\\t\\347\\264\\224"';
        $expected = hex2bin('e6bfb1e9878e09e7b494');

        $t->same(['value' => $expected, 'consumed' => 40], GitQuote::ansiCUndo($input));
    },

    'upstream quote.rs ansi_c::undo::exclamation_and_tilde_survive_an_escape_with_double_escaping' => static function (TestRunner $t): void {
        $t->same(
            ['value' => '\\!\\#hello there/file.ext', 'consumed' => 28],
            GitQuote::ansiCUndo('"\\\\!\\\\#hello there/file.ext"')
        );
    },

    'upstream quote.rs ansi_c::undo::out_of_quote_characters_can_be_passed_and_will_not_be_consumed' => static function (TestRunner $t): void {
        $input = '"hello there" out of quote';
        $result = GitQuote::ansiCUndo($input);

        $t->same('hello there', $result['value']);
        $t->same(' out of quote', substr($input, $result['consumed']));
    },

    'upstream quote.rs ansi_c::undo::fuzzed' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => GitQuote::ansiCUndo(hex2bin('225c')));
        $t->throws(
            InvalidArgumentException::class,
            static fn () => GitQuote::ansiCUndo('"Q' . "\x02" . 'QT' . str_repeat('\\', 20) . "\0\0\\")
        );
    },
];
