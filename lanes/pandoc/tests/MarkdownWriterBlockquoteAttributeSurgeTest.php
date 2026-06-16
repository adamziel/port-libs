<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$codeBlock = static fn (string $value, array $attrs = []): AstNode => new AstNode(
    'code_block',
    array_merge(['text' => $value], $attrs)
);
$listItem = static fn (array $children, array $attrs = []): AstNode => new AstNode('list_item', $attrs, $children);
$bulletList = static fn (array $items, array $attrs = []): AstNode => new AstNode('bullet_list', $attrs, $items);
$blockquote = static fn (array $children, array $attrs = []): AstNode => new AstNode('blockquote', $attrs, $children);
$definition = static fn (array $children): AstNode => new AstNode('definition', [], $children);
$definitionTerm = static fn (string $value): AstNode => new AstNode('definition_term', [], [$text($value)]);
$definitionItem = static fn (AstNode $term, array $definitions): AstNode => new AstNode(
    'definition_item',
    [],
    array_merge([$term], $definitions)
);
$definitionList = static fn (array $items): AstNode => new AstNode('definition_list', [], $items);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);

$attributeCases = [
    '01 id class and data attribute' => [
        'attrs' => [
            'id' => 'quote-one',
            'classes' => ['review-quote', 'highlighted'],
            'attributes' => ['data-review' => 'source'],
        ],
        'expectedAttrs' => ' id="quote-one" class="review-quote highlighted" data-review="source"',
    ],
    '02 data import only' => [
        'attrs' => ['attributes' => ['data-import' => 'packet-a']],
        'expectedAttrs' => ' data-import="packet-a"',
    ],
    '03 aria and language' => [
        'attrs' => ['attributes' => ['aria-label' => 'Reviewer quote', 'lang' => 'en']],
        'expectedAttrs' => ' aria-label="Reviewer quote" lang="en"',
    ],
    '04 title escaping' => [
        'attrs' => ['attributes' => ['title' => 'Quote "review" & source']],
        'expectedAttrs' => ' title="Quote &quot;review&quot; &amp; source"',
    ],
    '05 html class merges source classes' => [
        'attrs' => [
            'htmlAttributes' => ['class' => 'source-quote legacy'],
            'classes' => ['review-quote', 'source-quote'],
        ],
        'expectedAttrs' => ' class="source-quote legacy review-quote"',
    ],
    '06 xml language and role' => [
        'attrs' => ['attributes' => ['xml:lang' => 'fr-CA', 'role' => 'doc-epigraph']],
        'expectedAttrs' => ' xml:lang="fr-CA" role="doc-epigraph"',
    ],
    '07 safe style survives' => [
        'attrs' => ['attributes' => ['style' => 'border-left: 4px solid #ccc']],
        'expectedAttrs' => ' style="border-left: 4px solid #ccc"',
    ],
    '08 event handler is dropped beside data' => [
        'attrs' => ['attributes' => ['data-safe' => 'yes', 'onclick' => 'alert(1)']],
        'expectedAttrs' => ' data-safe="yes"',
        'absent' => ['onclick'],
    ],
    '09 unsafe style is dropped beside title' => [
        'attrs' => ['attributes' => ['title' => 'Unsafe style source', 'style' => 'background:url(javascript:alert(1))']],
        'expectedAttrs' => ' title="Unsafe style source"',
        'absent' => ['javascript:', 'background:url'],
    ],
    '10 html attributes precede source attributes' => [
        'attrs' => [
            'htmlAttributes' => ['data-origin' => 'html', 'aria-describedby' => 'quote-note'],
            'attributes' => ['data-origin' => 'source', 'data-extra' => 'kept'],
        ],
        'expectedAttrs' => ' data-origin="html" aria-describedby="quote-note" data-extra="kept"',
    ],
    '11 id from html attributes wins' => [
        'attrs' => [
            'htmlAttributes' => ['id' => 'html-id'],
            'id' => 'source-id',
            'attributes' => ['data-id-source' => 'source'],
        ],
        'expectedAttrs' => ' id="html-id" data-id-source="source"',
    ],
    '12 className fallback' => [
        'attrs' => ['className' => ['handoff-quote', 'packet-quote']],
        'expectedAttrs' => ' class="handoff-quote packet-quote"',
    ],
    '13 tabindex and dir' => [
        'attrs' => ['attributes' => ['tabindex' => '0', 'dir' => 'rtl']],
        'expectedAttrs' => ' tabindex="0" dir="rtl"',
    ],
    '14 item metadata' => [
        'attrs' => ['attributes' => ['itemprop' => 'citation', 'itemtype' => 'https://schema.org/Quotation']],
        'expectedAttrs' => ' itemprop="citation" itemtype="https://schema.org/Quotation"',
    ],
    '15 hidden and data state' => [
        'attrs' => ['attributes' => ['hidden' => 'hidden', 'data-state' => 'collapsed']],
        'expectedAttrs' => ' hidden="hidden" data-state="collapsed"',
    ],
];

$bodyCases = [
    'paragraph body' => [
        'children' => [$paragraph('Quoted <review> & source')],
        'expected' => '<p>Quoted &lt;review&gt; &amp; source</p>',
    ],
    'nested blockquote body' => [
        'children' => [
            $paragraph('Outer quote'),
            $blockquote([$paragraph('Inner quote')]),
        ],
        'expected' => '<p>Outer quote</p><blockquote><p>Inner quote</p></blockquote>',
    ],
    'task list body' => [
        'children' => [
            $bulletList([
                $listItem([$text('Review source')], ['taskChecked' => false]),
                $listItem([$text('Publish quote')], ['taskChecked' => true]),
            ]),
        ],
        'expected' => '<ul class="task-list"><li><input type="checkbox" />Review source</li><li><input type="checkbox" checked="" />Publish quote</li></ul>',
    ],
    'code block body' => [
        'children' => [$codeBlock('echo "quote";')],
        'expected' => '<pre><code>echo &quot;quote&quot;;</code></pre>',
    ],
    'definition list body' => [
        'children' => [
            $definitionList([
                $definitionItem($definitionTerm('Source'), [$definition([$paragraph('Imported definition')])]),
            ]),
        ],
        'expected' => '<dl><dt>Source</dt><dd><p>Imported definition</p></dd></dl>',
    ],
];

$tests = [];
$caseNumber = 0;

foreach ($attributeCases as $attributeName => $attributeCase) {
    foreach ($bodyCases as $bodyName => $bodyCase) {
        $caseNumber++;
        $tests['maps upstream markdown writer attributed blockquote html fallback surge '
            . str_pad((string) $caseNumber, 2, '0', STR_PAD_LEFT)
            . ' ' . $attributeName . ' ' . $bodyName] =
            static function (TestRunner $t) use ($attributeCase, $bodyCase, $blockquote, $document): void {
                $markdown = (new MarkdownWriter())->write($document([
                    $blockquote($bodyCase['children'], $attributeCase['attrs']),
                ]));

                $expected = '<blockquote' . $attributeCase['expectedAttrs'] . '>'
                    . $bodyCase['expected']
                    . '</blockquote>';

                $t->same($expected, $markdown);
                $t->true(!str_starts_with($markdown, '>'), 'Attributed blockquote should use HTML fallback');

                foreach ($attributeCase['absent'] ?? [] as $needle) {
                    $t->true(!str_contains($markdown, $needle), $needle . ' should not survive blockquote HTML fallback');
                }
            };
    }
}

$tests['records markdown writer attributed blockquote html fallback surge mapped-case count'] =
    static function (TestRunner $t) use ($caseNumber): void {
        $t->same(75, $caseNumber);
    };

return $tests;
