<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$rawHtml = static fn (string $html): AstNode => new AstNode('raw_html', ['html' => $html]);
$rawBlock = static fn (string $html, string $format): AstNode => new AstNode(
    'raw_block',
    ['format' => $format, 'text' => $html, 'html' => $html]
);

$payloadCases = [
    'html comment block' => [
        'html' => '<!-- writer comment -->',
        'format' => 'raw_html',
    ],
    'multiline html comment block' => [
        'html' => "<!--\nwriter comment\n-->",
        'format' => 'raw-html',
    ],
    'processing instruction block' => [
        'html' => '<?writer target?>',
        'format' => 'html',
    ],
    'stylesheet processing instruction block' => [
        'html' => '<?xml-stylesheet type="text/css" href="style.css"?>',
        'format' => 'html5',
    ],
    'doctype declaration block' => [
        'html' => '<!DOCTYPE html>',
        'format' => 'xhtml',
    ],
    'single line cdata block' => [
        'html' => '<![CDATA[source < raw & data]]>',
        'format' => 'raw_html',
    ],
    'multiline cdata block' => [
        'html' => "<![CDATA[\nline < one\nline & two\n]]>",
        'format' => 'raw-html',
    ],
    'div html block' => [
        'html' => '<div data-kind="block">Raw</div>',
        'format' => 'html',
    ],
    'section html block' => [
        'html' => "<section>\n<p>Raw paragraph</p>\n</section>",
        'format' => 'html5',
    ],
    'script json block' => [
        'html' => '<script type="application/json">{"ok":true}</script>',
        'format' => 'xhtml',
    ],
    'multiline script block' => [
        'html' => "<script>\nwindow.__review = '<raw>';\n</script>",
        'format' => 'raw_html',
    ],
    'style html block' => [
        'html' => '<style>body { color: #333; }</style>',
        'format' => 'raw-html',
    ],
    'table html block' => [
        'html' => "<table>\n<tr><td>Raw</td></tr>\n</table>",
        'format' => 'html',
    ],
    'self closing html block' => [
        'html' => '<hr />',
        'format' => 'html5',
    ],
    'custom element html block' => [
        'html' => '<custom-element data-x="1">Raw</custom-element>',
        'format' => 'xhtml',
    ],
];

$contextCases = [
    'markdown top level raw_html trims boundary blanks' =>
        static fn (string $html, string $format): array => [
            'document' => $document([$rawHtml("\n" . $html . "\n")]),
            'expected' => $html,
            'options' => ['format' => 'markdown'],
        ],
    'commonmark paragraph before raw_html trims boundary blanks' =>
        static fn (string $html, string $format): array => [
            'document' => $document([$paragraph('Before'), $rawHtml("\n\n" . $html)]),
            'expected' => 'Before' . "\n\n" . $html,
            'options' => ['format' => 'commonmark'],
        ],
    'gfm raw_html before paragraph trims boundary blanks' =>
        static fn (string $html, string $format): array => [
            'document' => $document([$rawHtml($html . "\n\n"), $paragraph('After')]),
            'expected' => $html . "\n\n" . 'After',
            'options' => ['format' => 'gfm'],
        ],
    'commonmark_x raw_html between paragraphs owns one blank boundary' =>
        static fn (string $html, string $format): array => [
            'document' => $document([$paragraph('Before'), $rawHtml("\n" . $html . "\n"), $paragraph('After')]),
            'expected' => 'Before' . "\n\n" . $html . "\n\n" . 'After',
            'options' => ['format' => 'commonmark_x'],
        ],
    'gfm raw_block html alias trims boundary blanks' =>
        static fn (string $html, string $format): array => [
            'document' => $document([$rawBlock("\n" . $html . "\n", $format)]),
            'expected' => $html,
            'options' => ['format' => 'gfm'],
        ],
];

$tests = [];
$caseCount = 0;

foreach ($payloadCases as $payloadLabel => $payload) {
    foreach ($contextCases as $contextLabel => $context) {
        $caseCount++;
        $tests['maps upstream markdown writer raw html boundary surge '
            . str_pad((string) $caseCount, 2, '0', STR_PAD_LEFT)
            . ' ' . $payloadLabel . ' ' . $contextLabel] =
            static function (TestRunner $t) use ($context, $payload): void {
                $case = $context($payload['html'], $payload['format']);
                $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

                $t->same($case['expected'], $markdown);
                $t->true(!str_contains($markdown, "\n\n\n"), 'Raw HTML block boundary should not contain triple blank lines');
            };
    }
}

$tests['records markdown writer raw html boundary surge mapped-case count'] =
    static function (TestRunner $t) use ($caseCount): void {
        $t->same(75, $caseCount);
    };

return $tests;
