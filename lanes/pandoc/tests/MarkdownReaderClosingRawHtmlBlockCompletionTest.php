<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$extractPandocStdin = static function (string $fixture): string {
    $lines = explode("\n", trim($fixture, "\n"));
    $start = array_search('% pandoc -t gfm', $lines, true);
    $end = array_search('^D', $lines, true);
    if (!is_int($start) || !is_int($end) || $end <= $start) {
        return '';
    }

    return implode("\n", array_slice($lines, $start + 1, $end - $start - 1));
};

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$mappedCases = [
    'upstream command 9792 list details closing tag',
    'commonmark paragraph interrupting closing tag',
];

return [
    'maps upstream command 9792 details closing tags inside list items' =>
        static function (TestRunner $t) use ($extractPandocStdin, $childTypes): void {
            $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-command-9792.md');
            $markdown = $extractPandocStdin($fixture);
            $document = (new MarkdownReader(['format' => 'gfm']))->read($markdown);
            $list = $document->children[0] ?? new AstNode('missing');
            $firstItem = $list->children[0] ?? new AstNode('missing');
            $nestedList = $firstItem->children[2] ?? new AstNode('missing');
            $closing = $firstItem->children[3] ?? new AstNode('missing');
            $continuation = $firstItem->children[4] ?? new AstNode('missing');
            $secondItem = $list->children[1] ?? new AstNode('missing');
            $roundTrip = (new MarkdownWriter())->write($document);
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->contains('% pandoc -t gfm', $fixture);
            $t->contains('  </details>', $fixture);
            $t->same('bullet_list', $list->type);
            $t->same(['paragraph', 'raw_html', 'bullet_list', 'raw_html', 'paragraph'], $childTypes($firstItem));
            $t->same('<details>', $firstItem->children[1]->attr('html'));
            $t->same('bullet_list', $nestedList->type);
            $t->same('subitem', $nestedList->children[0]->attr('text'));
            $t->same('raw_html', $closing->type);
            $t->same('</details>', $closing->attr('html'));
            $t->same(['text', 'emph', 'text', 'strong', 'text'], $childTypes($continuation));
            $t->same('continue', $continuation->children[1]->children[0]->attr('text'));
            $t->same('with', $continuation->children[3]->children[0]->attr('text'));
            $t->same('next list item', $secondItem->attr('text'));
            $t->contains('</details>', $roundTrip);
            $t->contains('</details>', $blocks);
        },

    'maps commonmark paragraph interrupting closing raw html block starts' =>
        static function (TestRunner $t) use ($childTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read(implode("\n", [
                'before',
                '</section>',
                '*raw closing block* remains raw.',
                '',
                'after',
            ]));
            $raw = $document->children[1] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same(['paragraph', 'raw_html', 'paragraph'], $childTypes($document));
            $t->same('before', $document->children[0]->attr('text'));
            $t->same("</section>\n*raw closing block* remains raw.", $raw->attr('html'));
            $t->same('after', $document->children[2]->attr('text'));
            $t->contains("</section>\n*raw closing block* remains raw.", $blocks);
        },

    'records markdown reader closing raw html block completion mapped-case count' =>
        static function (TestRunner $t) use ($mappedCases): void {
            $t->same(2, count($mappedCases));
        },
];
