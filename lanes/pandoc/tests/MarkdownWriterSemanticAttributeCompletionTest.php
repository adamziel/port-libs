<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$inline = static fn (string $type, array $children, array $attrs = []): AstNode => new AstNode($type, $attrs, $children);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $children): AstNode => new AstNode('document', [], [$paragraph($children)]);
$case = static fn (AstNode $node, string $expected, array $options = []): array => [
    'document' => $document([$node]),
    'expected' => $expected,
    'options' => $options,
];

$cases = [
    'small caps preserves id class and data attributes' => $case(
        $inline('small_caps', [$text('Caps')], [
            'id' => 'caps',
            'classes' => ['source'],
            'attributes' => ['data-kind' => 'term'],
        ]),
        '[Caps]{#caps .smallcaps .source data-kind="term"}'
    ),
    'small caps deduplicates existing semantic class' => $case(
        $inline('small_caps', [$text('Caps')], ['classes' => ['smallcaps', 'source']]),
        '[Caps]{.smallcaps .source}'
    ),
    'underline preserves language attribute' => $case(
        $inline('underline', [$text('insert')], ['attributes' => ['lang' => 'en']]),
        '[insert]{.underline lang="en"}'
    ),
    'strikeout preserves review class and data attribute' => $case(
        $inline('strikeout', [$text('gone')], [
            'classes' => ['edit'],
            'attributes' => ['data-kind' => 'delete'],
        ]),
        '[gone]{.strikeout .edit data-kind="delete"}'
    ),
    'superscript preserves id and data attribute' => $case(
        $inline('superscript', [$text('2')], [
            'id' => 'pow',
            'attributes' => ['data-kind' => 'power'],
        ]),
        '[2]{#pow .superscript data-kind="power"}'
    ),
    'subscript preserves data attribute' => $case(
        $inline('subscript', [$text('n')], ['attributes' => ['data-kind' => 'chem']]),
        '[n]{.subscript data-kind="chem"}'
    ),
    'small caps raw html fallback preserves merged attributes' => $case(
        $inline('small_caps', [$text('Caps')], [
            'id' => 'caps',
            'classes' => ['source'],
            'attributes' => ['data-kind' => 'term'],
        ]),
        '<span id="caps" class="smallcaps source" data-kind="term">Caps</span>',
        ['bracketedSpans' => false]
    ),
];

$tests = [
    'records markdown writer semantic attribute completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(7, count($cases));
        },
];

foreach ($cases as $label => $item) {
    $tests['maps upstream markdown writer semantic attribute completion ' . $label] =
        static function (TestRunner $t) use ($item): void {
            $markdown = (new MarkdownWriter($item['options']))->write($item['document']);

            $t->same($item['expected'], $markdown);
        };
}

return $tests;
