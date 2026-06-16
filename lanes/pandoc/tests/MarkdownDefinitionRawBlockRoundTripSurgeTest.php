<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$paragraphText = static fn (string $value): AstNode => $paragraph([$text($value)]);
$strong = static fn (string $value): AstNode => new AstNode('strong', [], [$text($value)]);
$emph = static fn (string $value): AstNode => new AstNode('emph', [], [$text($value)]);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$definitionTerm = static fn (array $children): AstNode => new AstNode('definition_term', [], $children);
$definition = static fn (array $children): AstNode => new AstNode('definition', [], $children);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$definitionList = static fn (array $items): AstNode => new AstNode('definition_list', [], $items);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);
$definitionDocument = static fn (array $termChildren, array $definitionChildren): AstNode => $document([
    $definitionList([
        $definitionItem($definitionTerm($termChildren), [$definition($definitionChildren)]),
    ]),
]);

$bulletList = static fn (string $value): AstNode => new AstNode('bullet_list', [], [
    new AstNode('list_item', [], [$text($value)]),
]);
$blockquote = static fn (string $value): AstNode => new AstNode('blockquote', [], [$paragraphText($value)]);
$reviewDiv = static fn (array $children, array $attrs = []): AstNode => new AstNode(
    'div',
    array_replace(['classes' => ['review']], $attrs),
    $children
);
$rawHtml = static fn (string $html): AstNode => new AstNode('raw_html', ['html' => $html]);
$rawBlock = static fn (string $format, string $value): AstNode => new AstNode('raw_block', [
    'format' => $format,
    'text' => $value,
]);
$rawTex = static fn (string $value): AstNode => new AstNode('raw_tex', ['text' => $value]);

$firstDefinitionChildren = static function (AstNode $document): array {
    $definition = $document->children[0]->children[0]->children[1] ?? null;

    return $definition instanceof AstNode ? $definition->children : [];
};

$bodyCases = [
    'fenced div paragraph body' => [
        'children' => [$reviewDiv([$paragraph([$text('Div '), $strong('body')])])],
        'types' => ['div'],
        'detached' => true,
    ],
    'fenced div id class attrs' => [
        'children' => [$reviewDiv([$paragraphText('Attributed body')], [
            'id' => 'review-block',
            'classes' => ['review', 'source'],
            'attributes' => ['data-kind' => 'definition'],
        ])],
        'types' => ['div'],
        'detached' => true,
    ],
    'fenced div nested list body' => [
        'children' => [$reviewDiv([$paragraphText('Lead'), $bulletList('nested item')])],
        'types' => ['div'],
        'detached' => true,
    ],
    'single line raw html body' => [
        'children' => [$rawHtml('<section data-kind="review">Body</section>')],
        'types' => ['raw_html'],
        'detached' => true,
    ],
    'multiline raw html body' => [
        'children' => [$rawHtml("<section>\n<p>Body</p>\n</section>")],
        'types' => ['raw_html'],
        'detached' => true,
    ],
    'raw block html body' => [
        'children' => [$rawBlock('html', '<aside data-kind="review">Body</aside>')],
        'types' => ['raw_html'],
        'detached' => true,
    ],
    'raw tex environment body' => [
        'children' => [$rawTex("\\begin{note}\nBody\n\\end{note}")],
        'types' => ['raw_tex'],
        'detached' => true,
    ],
    'raw block latex body' => [
        'children' => [$rawBlock('latex', "\\begin{review}\nBody\n\\end{review}")],
        'types' => ['raw_tex'],
        'detached' => true,
    ],
    'paragraph then fenced div body' => [
        'children' => [$paragraphText('Lead paragraph'), $reviewDiv([$paragraphText('Div body')])],
        'types' => ['paragraph', 'div'],
        'detached' => false,
    ],
    'paragraph then raw html body' => [
        'children' => [$paragraphText('Lead paragraph'), $rawHtml('<section>Followup</section>')],
        'types' => ['paragraph', 'raw_html'],
        'detached' => false,
    ],
    'fenced div then paragraph body' => [
        'children' => [$reviewDiv([$paragraphText('Div body')]), $paragraphText('Followup paragraph')],
        'types' => ['div', 'paragraph'],
        'detached' => true,
    ],
    'raw html then paragraph body' => [
        'children' => [$rawHtml('<section>Lead</section>'), $paragraphText('Followup paragraph')],
        'types' => ['raw_html', 'paragraph'],
        'detached' => true,
    ],
    'blockquote then fenced div body' => [
        'children' => [$blockquote('Quoted lead'), $reviewDiv([$paragraphText('Div after quote')])],
        'types' => ['blockquote', 'div'],
        'detached' => false,
    ],
    'list then fenced div body' => [
        'children' => [$bulletList('listed lead'), $reviewDiv([$paragraphText('Div after list')])],
        'types' => ['bullet_list', 'div'],
        'detached' => false,
    ],
    'raw tex then fenced div body' => [
        'children' => [$rawTex("\\begin{note}\nBody\n\\end{note}"), $reviewDiv([$paragraphText('Div after TeX')])],
        'types' => ['raw_tex', 'div'],
        'detached' => true,
    ],
    'fenced div then raw tex body' => [
        'children' => [$reviewDiv([$paragraphText('Div before TeX')]), $rawTex("\\begin{note}\nBody\n\\end{note}")],
        'types' => ['div', 'raw_tex'],
        'detached' => true,
    ],
];

$termCases = [
    'plain term' => [$text('Term alpha')],
    'strong term' => [$text('Term '), $strong('bold')],
    'code term' => [$text('Term '), $code('code')],
    'linebreak alias term' => [$text('Primary'), new AstNode('linebreak'), $text('Alias')],
    'emphasis term' => [$text('Term '), $emph('emphasis')],
];

$tests = [];
$caseNumber = 0;
foreach ($termCases as $termName => $termChildren) {
    foreach ($bodyCases as $bodyName => $case) {
        $caseNumber++;
        $label = str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT);
        $tests['maps upstream markdown definition raw block round trip '
            . $label . ' ' . $termName . ' ' . $bodyName] =
            static function (TestRunner $t) use (
                $case,
                $definitionDocument,
                $firstDefinitionChildren,
                $termChildren
            ): void {
                $markdown = (new MarkdownWriter())->write($definitionDocument($termChildren, $case['children']));
                $t->true(!str_contains($markdown, ':   :::'), 'Fenced div body must not stay on definition marker line');
                $t->true(!str_contains($markdown, ':   <section'), 'Raw HTML body must not stay on definition marker line');
                if ($case['detached']) {
                    $t->contains("\n:\n\n    ", $markdown, 'Ambiguous leading raw/div body should use detached definition marker');
                }

                $roundTrip = (new MarkdownReader())->read($markdown);
                $children = $firstDefinitionChildren($roundTrip);
                $t->same($case['types'], array_map(static fn (AstNode $node): string => $node->type, $children));
                $t->same($markdown, (new MarkdownWriter())->write($roundTrip));
            };
    }
}

$tests['records markdown definition raw block round-trip surge mapped-case count'] =
    static function (TestRunner $t) use ($caseNumber): void {
        $t->same(80, $caseNumber);
    };

return $tests;
