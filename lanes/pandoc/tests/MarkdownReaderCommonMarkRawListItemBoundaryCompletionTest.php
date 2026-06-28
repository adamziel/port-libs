<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

$firstListItem = static function (AstNode $document): AstNode {
    $list = $document->children[0] ?? new AstNode('missing');

    return $list->children[0] ?? new AstNode('missing');
};

$childTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$inlineTypes = static fn (AstNode $node): array => array_map(
    static fn (AstNode $child): string => $child->type,
    $node->children
);

$cases = [
    'section blank boundary' => [
        'markdown' => implode("\n", [
            '- <section data-review="list-boundary">',
            '  **raw section** source',
            '',
            '  After **section** boundary.',
        ]),
        'raw' => "<section data-review=\"list-boundary\">\n**raw section** source",
        'afterTypes' => ['text', 'strong', 'text'],
    ],
    'comment marker boundary' => [
        'markdown' => implode("\n", [
            '- <!--',
            '  - not a list',
            '  -->',
            '',
            '  After **comment** boundary.',
        ]),
        'raw' => "<!--\n- not a list\n-->",
        'afterTypes' => ['text', 'strong', 'text'],
    ],
    'script closing boundary' => [
        'markdown' => implode("\n", [
            '- <script type="application/json">',
            '  {"review": true}',
            '  </script>',
            '',
            '  After **script** boundary.',
        ]),
        'raw' => "<script type=\"application/json\">\n{\"review\": true}\n</script>",
        'afterTypes' => ['text', 'strong', 'text'],
    ],
    'custom tag blank boundary' => [
        'markdown' => implode("\n", [
            '- <review-block data-source="custom">',
            '  *raw custom* source',
            '',
            '  After **custom** boundary.',
        ]),
        'raw' => "<review-block data-source=\"custom\">\n*raw custom* source",
        'afterTypes' => ['text', 'strong', 'text'],
    ],
];

$tests = [
    'records commonmark initial list raw html boundary mapped-case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(4, count($cases));
        },
];

foreach ($cases as $name => $case) {
    $tests['maps commonmark initial list raw html boundary ' . $name] =
        static function (TestRunner $t) use ($case, $firstListItem, $childTypes, $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read($case['markdown']);
            $item = $firstListItem($document);
            $raw = $item->children[0] ?? new AstNode('missing');
            $after = $item->children[1] ?? new AstNode('missing');
            $blocks = (new WordPressBlockWriter())->write($document);

            $t->same('bullet_list', ($document->children[0] ?? new AstNode('missing'))->type);
            $t->same(['raw_html', 'paragraph'], $childTypes($item));
            $t->same($case['raw'], $raw->attr('html'));
            $t->same($case['afterTypes'], $inlineTypes($after));
            $t->contains($case['raw'], $blocks);
            $t->contains('<strong>', $blocks);
        };
}

return $tests;
