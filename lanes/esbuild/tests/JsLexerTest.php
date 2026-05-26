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
];

