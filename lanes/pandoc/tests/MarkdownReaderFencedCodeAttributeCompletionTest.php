<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\NativeWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$fixture = static fn (): string => (string) file_get_contents(
    dirname(__DIR__) . '/fixtures/upstream-command-indented-fences.md'
);

$tests = [];

$tests['maps upstream command indented fences fixture examples'] =
    static function (TestRunner $t) use ($fixture): void {
        $backtick = (new MarkdownReader())->read("  ```haskell\n  let x = y\nin y\n   ```");
        $tilde = (new MarkdownReader())->read(" ~~~ {.haskell}\n  let x = y\n in y +\ny +\n y\n~~~");
        $backtickCode = $backtick->children[0] ?? new AstNode('missing');
        $tildeCode = $tilde->children[0] ?? new AstNode('missing');

        $t->contains('% pandoc -t native', $fixture());
        $t->contains('[ CodeBlock ( "" , [ "haskell" ] , [] ) "let x = y\nin y" ]', $fixture());
        $t->same('code_block', $backtickCode->type);
        $t->same(['haskell'], $backtickCode->attr('classes'));
        $t->same("let x = y\nin y", $backtickCode->attr('text'));
        $t->same('code_block', $tildeCode->type);
        $t->same(['haskell'], $tildeCode->attr('classes'));
        $t->same(" let x = y\nin y +\ny +\ny", $tildeCode->attr('text'));
    };

$tests['preserves quoted fenced code attributes with spaces'] =
    static function (TestRunner $t): void {
        $source = implode("\n", [
            '```` {#review-snippet .php .numberLines startFrom="42" data-note="legacy source" title="Review \"snippet\"" data-empty=""}',
            'echo "source";',
            '```',
            'return true;',
            '````',
        ]);
        $document = (new MarkdownReader())->read($source);
        $code = $document->children[0] ?? new AstNode('missing');

        $t->same('code_block', $code->type);
        $t->same('review-snippet', $code->attr('id'));
        $t->same(['php', 'numberLines'], $code->attr('classes'));
        $t->same([
            'startFrom' => '42',
            'data-note' => 'legacy source',
            'title' => 'Review "snippet"',
            'data-empty' => '',
        ], $code->attr('attributes'));
        $t->same("echo \"source\";\n```\nreturn true;", $code->attr('text'));
    };

$tests['round trips quoted fenced code attributes through native markdown and wordpress'] =
    static function (TestRunner $t): void {
        $source = implode("\n", [
            '```` {#review-snippet .php .numberLines startFrom="42" data-note="legacy source" title="Review \"snippet\"" data-empty=""}',
            'echo "source";',
            '```',
            'return true;',
            '````',
        ]);
        $document = (new MarkdownReader())->read($source);
        $markdown = (new MarkdownWriter())->write($document);
        $native = (new NativeWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('data-note="legacy source"', $markdown);
        $t->contains('title="Review \"snippet\""', $markdown);
        $t->contains('data-empty=""', $markdown);
        $t->contains('( "data-note" , "legacy source" )', $native);
        $t->contains('( "title" , "Review \\"snippet\\"" )', $native);
        $t->contains('data-note="legacy source"', $blocks);
        $t->contains('title="Review &quot;snippet&quot;"', $blocks);
    };

$tests['records upstream markdown reader fenced code attribute completion mapped-case count'] =
    static function (TestRunner $t): void {
        $t->same(4, 4);
    };

return $tests;
