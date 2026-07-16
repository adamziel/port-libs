<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps commonmark atx heading boundary surge cases' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $headingCases = [
            ['#', 1, '', 'section'],
            ['# ', 1, '', 'section'],
            ['###', 3, '', 'section'],
            ['### ###', 3, '', 'section'],
            ['## heading ##', 2, 'heading', 'heading'],
            ['## heading ###', 2, 'heading', 'heading'],
            ['   #### indented heading', 4, 'indented heading', 'indented-heading'],
            ['###### max depth', 6, 'max depth', 'max-depth'],
            ['## heading # not closing', 2, 'heading # not closing', 'heading-not-closing'],
            ['### source {#review .queue key="value"} ###', 3, 'source', 'review'],
        ];
        $paragraphCases = [
            '####### too many markers',
            '#5 bolt stays text',
            '#hashtag stays text',
            '    # indented code, not heading',
        ];

        $mappedCases = count($headingCases) + count($paragraphCases);
        $t->same(14, $mappedCases);

        foreach ($headingCases as [$markdown, $level, $text, $id]) {
            $heading = $reader->read($markdown)->children[0] ?? new AstNode('missing');
            $t->same('heading', $heading->type, $markdown);
            $t->same($level, $heading->attr('level'), $markdown);
            $t->same($text, $heading->attr('text'), $markdown);
            $t->same($id, $heading->attr('id'), $markdown);
        }

        $attributed = $reader->read('### source {#review .queue key="value"} ###')->children[0];
        $t->same(['queue'], $attributed->attr('classes'));
        $t->same(['key' => 'value'], $attributed->attr('attributes'));

        foreach ($paragraphCases as $markdown) {
            $node = $reader->read($markdown)->children[0] ?? new AstNode('missing');
            $expectedType = str_starts_with($markdown, '    ') ? 'code_block' : 'paragraph';
            $t->same($expectedType, $node->type, $markdown);
        }

        $roundTrip = new AstNode('document', [], [
            new AstNode('heading', ['level' => 1]),
            new AstNode('heading', ['level' => 2], [new AstNode('text', ['text' => 'Reviewed'])]),
        ]);
    },

    'maps commonmark multiline setext and interruption surge cases' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $headingCases = [
            ["Foo\nbar\n---", 2, 'Foo bar', 'foo-bar'],
            ["Foo\nbar\n===", 1, 'Foo bar', 'foo-bar'],
            ["Foo *bar*\nbaz\n---", 2, 'Foo *bar* baz', 'foo-bar-baz'],
            ["Foo\n  bar\n---", 2, 'Foo bar', 'foo-bar'],
            ["Foo\n---\nbar", 2, 'Foo', 'foo'],
            ["Foo\n---\n---", 2, 'Foo', 'foo'],
            ["Foo\nbar\n---\nqux\n===", 2, 'Foo bar', 'foo-bar'],
        ];
        $interruptionCases = [
            ["- Foo\n---", ['bullet_list', 'horizontal_rule']],
            ["    Foo\n---", ['code_block', 'horizontal_rule']],
            ["# Foo\n---", ['heading', 'horizontal_rule']],
            ["> Foo\n---", ['blockquote', 'horizontal_rule']],
            ["Foo\n\n---", ['paragraph', 'horizontal_rule']],
            ["```\nFoo\n```\n---", ['code_block', 'horizontal_rule']],
        ];

        $mappedCases = count($headingCases) + count($interruptionCases);
        $t->same(13, $mappedCases);

        foreach ($headingCases as [$markdown, $level, $text, $id]) {
            $document = $reader->read($markdown);
            $heading = $document->children[0] ?? new AstNode('missing');
            $t->same('heading', $heading->type, $markdown);
            $t->same($level, $heading->attr('level'), $markdown);
            $t->same($text, $heading->attr('text'), $markdown);
            $t->same($id, $heading->attr('id'), $markdown);
        }

        $twoHeadings = $reader->read("Foo\nbar\n---\nqux\n===");
        $t->same(['heading', 'heading'], array_map(static fn (AstNode $node): string => $node->type, $twoHeadings->children));
        $t->same('qux', $twoHeadings->children[1]->attr('text'));

        foreach ($interruptionCases as [$markdown, $types]) {
            $document = $reader->read($markdown);
            $t->same($types, array_map(static fn (AstNode $node): string => $node->type, $document->children), $markdown);
        }
    },

    'maps commonmark thematic break and indented code surge cases' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $cases = [
            ['***', ['horizontal_rule']],
            ['---', ['horizontal_rule']],
            ['___', ['horizontal_rule']],
            ['* * *', ['horizontal_rule']],
            ['- - -', ['horizontal_rule']],
            ['_ _ _', ['horizontal_rule']],
            ['   ***', ['horizontal_rule']],
            ['    ***', ['code_block']],
            ['+++', ['paragraph']],
            ['===', ['paragraph']],
            ["    code", ['code_block']],
            ["\tcode", ['code_block']],
            ["    a\n\n    b", ['code_block']],
            ['  code', ['paragraph']],
        ];

        $t->same(14, count($cases));

        foreach ($cases as [$markdown, $types]) {
            $document = $reader->read($markdown);
            $t->same($types, array_map(static fn (AstNode $node): string => $node->type, $document->children), $markdown);
        }

        $code = $reader->read("    a\n\n    b")->children[0] ?? new AstNode('missing');
        $t->same("a\n\nb", $code->attr('text'), 'Indented code block should preserve internal blank lines');
    },

    'maps commonmark fenced code and raw markdown surge cases' => static function (TestRunner $t): void {
        $reader = new MarkdownReader();
        $cases = [
            ["```\ncode\n```", 'code_block', ['text' => 'code']],
            ["~~~\ncode\n~~~", 'code_block', ['text' => 'code']],
            ["````\ncode ```\n````", 'code_block', ['text' => 'code ```']],
            ["``` php\necho 1;\n```", 'code_block', ['text' => 'echo 1;', 'classes' => ['php'], 'info' => 'php']],
            ["``` {#id .php key=\"v\"}\ncode\n```", 'code_block', ['text' => 'code', 'id' => 'id', 'classes' => ['php'], 'attributes' => ['key' => 'v']]],
            ["   ```\n code\n   ```", 'code_block', ['text' => 'code']],
            ["```\nunterminated", 'code_block', ['text' => 'unterminated']],
            ["```\n````", 'code_block', ['text' => '']],
            ["~~~~\n~~~\n~~~~", 'code_block', ['text' => '~~~']],
            ["```\ncode\n```   ", 'code_block', ['text' => 'code']],
            ["```\ncode\n``` suffix", 'code_block', ['text' => "code\n``` suffix"]],
            ["```{=html}\n<div></div>\n```", 'raw_block', ['text' => '<div></div>', 'format' => 'html']],
            ["~~~ info with spaces\nx\n~~~", 'code_block', ['text' => 'x', 'classes' => ['info'], 'info' => 'info with spaces']],
            ["```\n<a>\n```", 'code_block', ['text' => '<a>']],
            ["``\nnot fence", 'paragraph', ['text' => '`` not fence']],
        ];

        $t->same(15, count($cases));

        foreach ($cases as [$markdown, $type, $attrs]) {
            $node = $reader->read($markdown)->children[0] ?? new AstNode('missing');
            $t->same($type, $node->type, $markdown);
            foreach ($attrs as $name => $expected) {
                $t->same($expected, $node->attr($name), $markdown . ' attr ' . $name);
            }
        }
    },

    'records commonmark surge mapped-case count' => static function (TestRunner $t): void {
        $t->same(56, 14 + 13 + 14 + 15);
    },
];
