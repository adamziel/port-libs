<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\HtmlWriter;
use PortLibs\Pandoc\MarkdownReader;

return [
    'maps a long lazy definition continuation as one definition block' => static function (TestRunner $t): void {
        $continuationCount = 512;
        $lines = ['term', ': first'];
        $expectedWords = ['first'];
        for ($index = 0; $index < $continuationCount; $index++) {
            $word = 'continued-' . $index;
            $lines[] = $word;
            $expectedWords[] = $word;
        }

        $document = (new MarkdownReader())->read(implode("\n", $lines));
        $list = $document->children[0] ?? new AstNode('missing');
        $item = $list->children[0] ?? new AstNode('missing');
        $definition = $item->children[1] ?? new AstNode('missing');
        $block = $definition->children[0] ?? new AstNode('missing');

        $t->same('definition_list', $list->type);
        $t->same('definition', $definition->type);
        $t->same(1, count($definition->children));
        $t->same('plain', $block->type);
        $t->same(implode(' ', $expectedWords), $block->attr('text'));
    },

    'keeps unmatched opening bracket runs literal without rejecting nested labels' => static function (TestRunner $t): void {
        $unmatched = str_repeat('[', 8192);
        $document = (new MarkdownReader())->read($unmatched);
        $paragraph = $document->children[0] ?? new AstNode('missing');

        $t->same('paragraph', $paragraph->type);
        $t->same($unmatched, $paragraph->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $paragraph->children));

        $escaped = '\\' . $unmatched;
        $escapedDocument = (new MarkdownReader())->read($escaped);
        $escapedParagraph = $escapedDocument->children[0] ?? new AstNode('missing');

        $t->same('paragraph', $escapedParagraph->type);
        $t->same($unmatched, $escapedParagraph->attr('text'));

        $nested = (new MarkdownReader())->read(implode("\n", [
            '[outer [inner] label]',
            '',
            '[outer [inner] label]: /nested "Nested label"',
        ]));
        $nestedParagraph = $nested->children[0] ?? new AstNode('missing');
        $link = $nestedParagraph->children[0] ?? new AstNode('missing');

        $t->same('link', $link->type);
        $t->same('/nested', $link->attr('url'));
        $t->same('Nested label', $link->attr('title'));

        $mathLink = (new HtmlWriter())->write(
            (new MarkdownReader())->read('[\\[x] \\] label](https://example.test/math-label)')
        );

        $t->contains('<a href="https://example.test/math-label">', $mathLink);
        $t->contains('<span class="math display">', $mathLink);
        $t->contains('label</a>', $mathLink);
    },

    'preserves same-line source after a raw HTML closing boundary' => static function (TestRunner $t): void {
        $writer = new HtmlWriter();
        $sources = [
            '<table><tr><td>x</td></tr></table> trailing',
            '<article>x</article> trailing',
            '<details>x</details> trailing',
            '<button>x</button> trailing',
            '<del>x</del> trailing',
            '<ins>x</ins> trailing',
        ];

        foreach ($sources as $source) {
            $html = $writer->write((new MarkdownReader(['format' => 'commonmark']))->read($source));

            $t->contains('trailing', $html, $source);
        }
    },

    'bounds malformed blank-terminated HTML block collection' => static function (TestRunner $t): void {
        $count = 768;
        $cases = [
            'paragraph' => [str_repeat("<p>\n\n", $count) . '</p>', 'raw_html'],
            'article' => [str_repeat("<article>\n\n", $count) . 'after', 'paragraph'],
            'section' => [str_repeat("<section>\n\n", $count) . 'after', 'paragraph'],
        ];

        foreach ($cases as $name => [$source, $lastType]) {
            $document = (new MarkdownReader())->read($source);
            $last = $document->children[array_key_last($document->children)] ?? new AstNode('missing');

            $t->same($count + 1, count($document->children), $name);
            $t->same($lastType, $last->type, $name);
        }
    },

    'recovers unclosed div chains without recursively reparsing every suffix' => static function (TestRunner $t): void {
        $count = 512;
        $reader = new MarkdownReader(['format' => 'commonmark']);
        $standalone = $reader->read(str_repeat("<div>\n\n", $count) . 'after');
        $node = $standalone->children[0] ?? new AstNode('missing');
        $depth = 0;
        while ($node->type === 'div') {
            $depth++;
            $node = $node->children[0] ?? new AstNode('missing');
        }

        $t->same($count, $depth);
        $t->same('paragraph', $node->type);
        $t->same('after', $node->attr('text'));

        $sameLine = $reader->read(str_repeat('<div>', $count) . 'after');
        $sameLineOuter = $sameLine->children[0] ?? new AstNode('missing');
        $sameLineInner = $sameLineOuter->children[0] ?? new AstNode('missing');
        $sameLineRaw = $sameLineInner->children[0] ?? new AstNode('missing');

        $t->same('div', $sameLineOuter->type);
        $t->same('div', $sameLineInner->type);
        $t->same('raw_html', $sameLineRaw->type);
        $t->same($count - 2, substr_count((string) $sameLineRaw->attr('html'), '<div>'));
        $t->contains('after', (string) $sameLineRaw->attr('html'));

        $closedCount = 800;
        $closed = (new MarkdownReader())->read(
            str_repeat("<div>\n", $closedCount)
            . "(@deep-native-div) real example\n"
            . str_repeat("</div>\n", $closedCount)
            . "\nSee (@deep-native-div)."
        );
        $closedNode = $closed->children[0] ?? new AstNode('missing');
        $closedDepth = 0;
        while ($closedNode->type === 'div') {
            $closedDepth++;
            $closedNode = $closedNode->children[0] ?? new AstNode('missing');
        }

        $t->same($closedCount, $closedDepth);
        $t->same('ordered_list', $closedNode->type);
        $t->same('See (1).', $closed->children[1]->attr('text'));

        $rawDisabled = (new MarkdownReader(['format' => 'commonmark', 'htmlRawHtml' => false]))->read('<div><div><div>after');
        $t->same('paragraph', $rawDisabled->children[0]->children[0]->children[0]->type);

        $reused = $reader->read('<div>x</div>');
        $t->same('plain', $reused->children[0]->children[0]->type);
    },

    'keeps isolated prefix nested div chains out of recursive source reparsing' => static function (TestRunner $t): void {
        $depth = 512;
        $document = (new MarkdownReader(['format' => 'commonmark']))->read(
            str_repeat("<div>\nprefix\n\n", $depth)
            . "leaf\n"
            . str_repeat("</div>\n", $depth)
        );

        $node = $document->children[0] ?? new AstNode('missing');
        $seen = 0;
        while ($node->type === 'div') {
            $prefix = $node->children[0] ?? new AstNode('missing');
            $next = $node->children[1] ?? new AstNode('missing');

            $t->same('paragraph', $prefix->type, 'prefix at depth ' . $seen);
            $t->same('prefix', $prefix->attr('text'), 'prefix text at depth ' . $seen);
            $seen++;
            $node = $next;
        }

        $t->same($depth, $seen);
        $t->same('paragraph', $node->type);
        $t->same('leaf', $node->attr('text'));

        $implicitHeading = (new MarkdownReader())->read("<div>\n# Heading\n\n<div>\n[Heading]\n</div>\n</div>");
        $implicitLink = $implicitHeading->children[0]->children[1]->children[0]->children[0] ?? new AstNode('missing');

        $t->same('link', $implicitLink->type);
        $t->same('#heading', $implicitLink->attr('url'));

        $mmdTitle = (new MarkdownReader())->read("<div>\n% Title\n\n<div>\nleaf\n</div>\n</div>");
        $mmdOuter = $mmdTitle->children[0] ?? new AstNode('missing');

        $t->same(['div'], array_map(static fn (AstNode $child): string => $child->type, $mmdOuter->children));
    },
];
