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
    'classifies comments and delimiter anchors' => static function (TestRunner $t): void {
        $tokens = (new TokenDiffer())->tokenize('items([1, /* keep */ 2])');
        $kinds = array_map(static fn ($token): string => $token->kind, $tokens);
        $t->contains('comment', implode(',', $kinds));
        $t->same('open', $tokens[1]->delimiterRole);
        $t->same('open', $tokens[2]->delimiterRole);
        $t->same('close', $tokens[8]->delimiterRole);
    },
    'diff operates on tokens rather than raw lines' => static function (TestRunner $t): void {
        $ops = (new TokenDiffer())->diff('return a + b;', 'return a - b;');
        $encoded = implode('', array_map(static fn (array $op): string => $op['op'] . $op['text'], $ops));
        $t->contains('-+', $encoded);
        $t->contains('+-', $encoded);
    },
    'matches upstream ignore comments cli behavior' => static function (TestRunner $t): void {
        $old = 'funName(1 /* foo */ , /* bar */)';
        $new = 'funName(1 /* kinda like bar */ , /* foo */)';
        $differ = new TokenDiffer();

        $t->true($differ->hasChanges($old, $new));
        $t->same(false, $differ->hasChanges($old, $new, ['ignoreComments' => true]));
    },
    'ignores trailing commas before closing delimiters' => static function (TestRunner $t): void {
        $differ = new TokenDiffer();
        $old = 'const blocks = ["core/paragraph", "core/image"];';
        $new = 'const blocks = ["core/paragraph", "core/image",];';

        $t->same(false, $differ->hasChanges($old, $new));
    },
    'wordpress render callback diff hides comment churn but keeps api changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-callback-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-render-callback-after.php');

        $ops = (new TokenDiffer())->diff($before, $after, ['ignoreComments' => true]);
        $encoded = implode('', array_map(static fn (array $op): string => $op['op'] . $op['text'], $ops));

        $t->contains('-esc_html', $encoded);
        $t->contains('+wp_kses_post', $encoded);
        $t->true(!str_contains($encoded, 'Classic template fallback'), 'Comment-only churn should be filtered.');
    },
];
