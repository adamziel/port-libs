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
    'splits words like upstream words rs' => static function (TestRunner $t): void {
        $differ = new TokenDiffer();

        $t->same(['example', '.', 'com'], $differ->splitWords('example.com'));
        $t->same(['example', '.', '.'], $differ->splitWords('example..'));
        $t->same(['foo123bar'], $differ->splitWords('foo123bar'));
        $t->same(['example', '.', "\n", 'com'], $differ->splitWords("example.\ncom"));
        $t->same(['a', ' ', 'ö', ' ', 'b'], $differ->splitWords('a ö b'));
        $t->same(['a', ' ', '💝', ' ', 'b'], $differ->splitWords('a 💝 b'));
        $t->same(['a', ' ', 'xöy', ' ', 'b'], $differ->splitWords('a xöy b'));
    },
    'splits words and numbers like upstream words rs' => static function (TestRunner $t): void {
        $differ = new TokenDiffer();

        $t->same(['a', '123', 'b'], $differ->splitWordsAndNumbers('a123b'));
        $t->same(['foo', ' ', 'bar'], $differ->splitWordsAndNumbers('foo bar'));
    },
    'maps upstream hyphen subwords fixture as a focused deletion' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-hyphen-subwords-1.json');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-hyphen-subwords-2.json');
        $deletions = array_values(array_map(
            static fn (array $op): string => $op['text'],
            array_filter((new TokenDiffer())->diffWords($before, $after, ['splitNumbers' => true]), static fn (array $op): bool => $op['op'] === '-')
        ));

        $t->same(['foo', '-'], $deletions);
    },
    'maps upstream contiguous sample as syntax list insertions' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-contiguous-1.js');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-contiguous-2.js');
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after, ['ignoreComments' => true]);

        $insertions = array_values(array_filter($changes, static fn (array $change): bool => $change['op'] === '+'));
        $t->same('$[0][2]', $insertions[0]['path']);
        $t->same('"A"', $insertions[0]['text']);
        $t->same('$[0][3]', $insertions[1]['path']);
        $t->same('"B"', $insertions[1]['text']);
    },
    'recurses into nested wordpress registration arrays' => static function (TestRunner $t): void {
        $before = "register_block_type('demo/card', ['supports' => ['html' => false, 'align' => ['wide']], 'render_callback' => 'old_card']);";
        $after = "register_block_type('demo/card', ['supports' => ['html' => true, 'align' => ['wide', 'full']], 'render_callback' => 'old_card']);";
        $changes = (new TokenDiffer())->diffSyntaxLists($before, $after);
        $encoded = implode("\n", array_map(
            static fn (array $change): string => $change['op'] . ' ' . $change['path'] . ' ' . ($change['text'] ?? $change['old'] ?? '') . ' ' . ($change['new'] ?? ''),
            $changes
        ));

        $t->contains('$[0][1]/[0][0]/[0][0]', $encoded);
        $t->contains('- $[0][1]/[0][0]/[0][0] \'html\'=>false', $encoded);
        $t->contains('+ $[0][1]/[0][0]/[0][0] \'html\'=>true', $encoded);
        $t->contains('+ $[0][1]/[0][0]/[0][1]/[0][1] \'full\'', $encoded);
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
    'wordpress block style slug diff reports subword changes' => static function (TestRunner $t): void {
        $before = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-before.php');
        $after = (string) file_get_contents(dirname(__DIR__) . '/fixtures/wordpress-block-style-after.php');
        $ops = (new TokenDiffer())->diffWords($before, $after, ['splitNumbers' => true]);
        $encoded = implode('', array_map(static fn (array $op): string => $op['op'] . $op['text'], $ops));

        $t->contains('-legacy', $encoded);
        $t->contains('+modern', $encoded);
        $t->contains('-2', $encoded);
        $t->contains('+3', $encoded);
    },
];
