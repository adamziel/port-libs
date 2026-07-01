<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$listItem = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$plainItem = static fn (string $value): AstNode => $listItem([$plain([$text($value)])]);
$bulletList = static fn (string $value): AstNode => new AstNode('bullet_list', [], [$plainItem($value)]);
$orderedList = static fn (string $value): AstNode => new AstNode('ordered_list', [], [$plainItem($value)]);
$definitionTerm = static fn (string $value): AstNode => new AstNode('definition_term', [], [$text($value)]);
$definition = static fn (string $value): AstNode => new AstNode('definition', [], [$paragraph($value)]);
$definitionItem = static fn (string $term, string $body): AstNode => new AstNode(
    'definition_item',
    [],
    [$definitionTerm($term), $definition($body)]
);
$definitionList = static fn (string $term, string $body): AstNode => new AstNode(
    'definition_list',
    [],
    [$definitionItem($term, $body)]
);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$sameTypeListSeparatorCases = [
    'bullet list guard' => [
        'doc' => $document([$bulletList('first bullet'), $bulletList('second bullet')]),
        'expected' => "- first bullet\n\n<!-- -->\n\n- second bullet",
        'types' => ['bullet_list', 'raw_html', 'bullet_list'],
    ],
    'ordered list guard' => [
        'doc' => $document([$orderedList('first ordered'), $orderedList('second ordered')]),
        'expected' => "1.  first ordered\n\n<!-- -->\n\n1.  second ordered",
        'types' => ['ordered_list', 'raw_html', 'ordered_list'],
    ],
    'definition list guard' => [
        'doc' => $document([
            $definitionList('First term', 'first definition'),
            $definitionList('Second term', 'second definition'),
        ]),
        'expected' => "First term\n\n:   first definition\n\n<!-- -->\n\nSecond term\n\n:   second definition",
        'types' => ['definition_list', 'raw_html', 'definition_list'],
    ],
];

$tests = [];

$tests['records upstream markdown writer same-type list separator guard fixture count'] =
    static function (TestRunner $t) use ($sameTypeListSeparatorCases): void {
        $t->same(3, count($sameTypeListSeparatorCases));
    };

foreach ($sameTypeListSeparatorCases as $name => $case) {
    $tests['maps upstream markdown writer same-type list separator ' . $name] =
        static function (TestRunner $t) use ($case): void {
            $markdown = (new MarkdownWriter())->write($case['doc']);

            $t->same($case['expected'], $markdown);
            $t->same(1, substr_count($markdown, '<!-- -->'), $markdown);

            $roundTrip = (new MarkdownReader())->read($markdown);
            $t->same($case['types'], array_map(
                static fn (AstNode $node): string => $node->type,
                $roundTrip->children
            ), $markdown);
        };
}

$tests['maps upstream markdown writer same-type list separator raw-html-disabled fallback'] =
    static function (TestRunner $t) use ($bulletList, $document): void {
        $markdown = (new MarkdownWriter(['rawHtml' => false]))->write($document([
            $bulletList('first fallback bullet'),
            $bulletList('second fallback bullet'),
        ]));

        $t->same("- first fallback bullet\n\n&nbsp;\n\n- second fallback bullet", $markdown);
        $t->same(0, substr_count($markdown, '<!-- -->'), $markdown);
        $t->same(1, substr_count($markdown, '&nbsp;'), $markdown);
    };

return $tests;
