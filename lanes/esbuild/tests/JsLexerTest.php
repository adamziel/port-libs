<?php

declare(strict_types=1);

use PortLibs\Esbuild\JsLexer;

return [
    'javascript lexer skips comments and emits core token kinds' => static function (TestRunner $t): void {
        $tokens = (new JsLexer())->tokenize("const answer = 42; // comment\nexport default answer");
        $texts = array_map(static fn ($token): string => $token->text, $tokens);
        $t->same(['const', 'answer', '=', '42', ';', 'export', 'default', 'answer'], $texts);
        $t->same('number', $tokens[3]->kind);
    },
    'javascript lexer preserves token offsets' => static function (TestRunner $t): void {
        $tokens = (new JsLexer())->tokenize('let x = "wp";');
        $t->same(4, $tokens[1]->offset);
        $t->same('string', $tokens[3]->kind);
    },
    'javascript lexer maps esbuild numeric literal forms' => static function (TestRunner $t): void {
        $tokens = (new JsLexer())->tokenize('let n = 0b1010 + 0o17 + 0x7fff_ffff + .5e+1 + 123.;');
        $numbers = array_values(array_filter($tokens, static fn ($token): bool => $token->kind === 'number'));
        $t->same(['0b1010', '0o17', '0x7fff_ffff', '.5e+1', '123.'], array_map(static fn ($token): string => $token->text, $numbers));
        $t->same([10.0, 15.0, 2147483647.0, 5.0, 123.0], array_map(static fn ($token): ?float => $token->numberValue, $numbers));
    },
    'javascript lexer reports unterminated block comments' => static function (TestRunner $t): void {
        $lexer = new JsLexer();
        $t->throws(InvalidArgumentException::class, static fn (): array => $lexer->tokenize('/*'));
        $t->throws(InvalidArgumentException::class, static fn (): array => $lexer->tokenize('/*/'));
    },
    'javascript lexer emits hashbang at file start' => static function (TestRunner $t): void {
        $tokens = (new JsLexer())->tokenize("#!/usr/bin/env node\nlet x = 1");
        $t->same('hashbang', $tokens[0]->kind);
        $t->same('#!/usr/bin/env node', $tokens[0]->text);
        $t->same('let', $tokens[1]->text);
    },
    'javascript lexer tokenizes decorator and private identifier syntax' => static function (TestRunner $t): void {
        $tokens = (new JsLexer())->tokenize('@dec(() => 0) declare class Foo { accessor #x }');
        $texts = array_map(static fn ($token): string => $token->text, $tokens);
        $private = array_values(array_filter($tokens, static fn ($token): bool => $token->kind === 'private_identifier'));

        $t->same('@', $tokens[0]->text);
        $t->true(in_array('=>', $texts, true));
        $t->same('#x', $private[0]->text);
    },
    'javascript lexer rejects malformed base-prefixed numeric literals' => static function (TestRunner $t): void {
        $lexer = new JsLexer();
        foreach (['0b', '0b012', '0o018', '0xGFEDCBA'] as $source) {
            $t->throws(InvalidArgumentException::class, static fn (): array => $lexer->tokenize($source));
        }
    },
    'wordpress block asset fixture tokenizes without node' => static function (TestRunner $t): void {
        $source = (string) file_get_contents(__DIR__ . '/../fixtures/wordpress-block-view.js');
        $tokens = (new JsLexer())->tokenize($source);
        $texts = array_map(static fn ($token): string => $token->text, $tokens);
        $numberValues = array_values(array_filter(array_map(static fn ($token): ?float => $token->numberValue, $tokens), static fn (?float $value): bool => $value !== null));

        $t->true(in_array("'@wordpress/dom-ready'", $texts, true));
        $t->true(in_array(10.0, $numberValues, true));
        $t->true(in_array(5.0, $numberValues, true));
    },
];
