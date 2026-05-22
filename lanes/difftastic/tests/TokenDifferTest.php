<?php

declare(strict_types=1);

use PortLibs\Difftastic\TokenDiffer;

return [
    'tokenizes identifiers numbers strings and punctuation separately' => static function (TestRunner $t): void {
        $tokens = (new TokenDiffer())->tokenize('fn add(x, 2) { return "ok"; }');
        $t->same('identifier', $tokens[0]->kind);
        $t->same('fn', $tokens[0]->text);
        $t->same('number', $tokens[5]->kind);
        $t->same('string', $tokens[9]->kind);
    },
    'diff operates on tokens rather than raw lines' => static function (TestRunner $t): void {
        $ops = (new TokenDiffer())->diff('return a + b;', 'return a - b;');
        $encoded = implode('', array_map(static fn (array $op): string => $op['op'] . $op['text'], $ops));
        $t->contains('-+', $encoded);
        $t->contains('+-', $encoded);
    },
];
