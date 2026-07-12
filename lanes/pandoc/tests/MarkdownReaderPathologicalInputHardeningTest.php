<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
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
    },
];
