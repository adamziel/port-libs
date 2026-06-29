<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$firstInlineOfType = static function (AstNode $document, string $type): AstNode {
    foreach ($document->children as $block) {
        foreach ($block->children as $inline) {
            if ($inline->type === $type) {
                return $inline;
            }
        }
    }

    return new AstNode('missing');
};

$semanticConfigs = [
    'emphasis asterisk' => [
        'type' => 'emph',
        'open' => '*',
        'close' => '*',
        'escaped' => '\\*',
        'literal' => '*',
        'html' => '<em>%s</em>',
        'htmlStart' => '<em>',
    ],
    'emphasis underscore' => [
        'type' => 'emph',
        'open' => '_',
        'close' => '_',
        'escaped' => '\\_',
        'literal' => '_',
        'html' => '<em>%s</em>',
        'htmlStart' => '<em>',
    ],
    'strong asterisk' => [
        'type' => 'strong',
        'open' => '**',
        'close' => '**',
        'escaped' => '\\*\\*',
        'literal' => '**',
        'html' => '<strong>%s</strong>',
        'htmlStart' => '<strong>',
    ],
    'strong underscore' => [
        'type' => 'strong',
        'open' => '__',
        'close' => '__',
        'escaped' => '\\_\\_',
        'literal' => '__',
        'html' => '<strong>%s</strong>',
        'htmlStart' => '<strong>',
    ],
    'strikeout' => [
        'type' => 'strikeout',
        'open' => '~~',
        'close' => '~~',
        'escaped' => '\\~\\~',
        'literal' => '~~',
        'html' => '<del>%s</del>',
        'htmlStart' => '<del>',
    ],
    'mark' => [
        'type' => 'span',
        'open' => '==',
        'close' => '==',
        'escaped' => '\\=\\=',
        'literal' => '==',
        'html' => '<mark>%s</mark>',
        'htmlStart' => '<mark>',
        'classes' => ['mark'],
    ],
    'superscript' => [
        'type' => 'superscript',
        'open' => '^',
        'close' => '^',
        'escaped' => '\\^',
        'literal' => '^',
        'html' => '<sup>%s</sup>',
        'htmlStart' => '<sup>',
    ],
    'subscript' => [
        'type' => 'subscript',
        'open' => '~',
        'close' => '~',
        'escaped' => '\\~',
        'literal' => '~',
        'html' => '<sub>%s</sub>',
        'htmlStart' => '<sub>',
    ],
];

$escapedClosingCases = [];
foreach ($semanticConfigs as $name => $config) {
    $literal = $config['literal'];
    $escapedClosingCases[$name . ' plain escaped close'] = [
        'config' => $config,
        'markdown' => $config['open'] . 'a' . $config['escaped'] . 'b' . $config['close'],
        'text' => 'a' . $literal . 'b',
    ];
    $escapedClosingCases[$name . ' escaped close with prefix suffix'] = [
        'config' => $config,
        'markdown' => 'Before ' . $config['open'] . 'a' . $config['escaped'] . 'b' . $config['close'] . ' after.',
        'text' => 'a' . $literal . 'b',
    ];
    $escapedClosingCases[$name . ' escaped close before code span'] = [
        'config' => $config,
        'markdown' => $config['open'] . 'a' . $config['escaped'] . '`b`' . $config['close'],
        'text' => 'a' . $literal . 'b',
    ];
    $escapedClosingCases[$name . ' escaped close before link'] = [
        'config' => $config,
        'markdown' => $config['open'] . 'a' . $config['escaped'] . '[b](/target)' . $config['close'],
        'text' => 'a' . $literal . 'b',
    ];
    $escapedClosingCases[$name . ' two escaped delimiters'] = [
        'config' => $config,
        'markdown' => $config['open'] . 'a' . $config['escaped'] . 'b' . $config['escaped'] . 'c' . $config['close'],
        'text' => 'a' . $literal . 'b' . $literal . 'c',
    ];
    $escapedClosingCases[$name . ' escaped close before punctuation'] = [
        'config' => $config,
        'markdown' => $config['open'] . 'a' . $config['escaped'] . 'b!' . $config['close'],
        'text' => 'a' . $literal . 'b!',
    ];
}

$escapedClosingCases['strong asterisk escaped first closing marker char'] = [
    'config' => $semanticConfigs['strong asterisk'],
    'markdown' => '**a\\**b**',
    'text' => 'a**b',
];
$escapedClosingCases['strong underscore escaped first closing marker char'] = [
    'config' => $semanticConfigs['strong underscore'],
    'markdown' => '__a\\__b__',
    'text' => 'a__b',
];
$escapedClosingCases['strikeout escaped first closing marker char'] = [
    'config' => $semanticConfigs['strikeout'],
    'markdown' => '~~a\\~~b~~',
    'text' => 'a~~b',
];

$literalOpeningCases = [
    'escaped emphasis opener remains text' => ['markdown' => '\\*literal*', 'text' => '*literal*', 'absent' => 'emph'],
    'escaped underscore emphasis opener remains text' => ['markdown' => '\\_literal_', 'text' => '_literal_', 'absent' => 'emph'],
    'escaped strong opener remains text' => ['markdown' => '\\*\\*literal**', 'text' => '**literal**', 'absent' => 'strong'],
    'escaped underscore strong opener remains text' => ['markdown' => '\\_\\_literal__', 'text' => '__literal__', 'absent' => 'strong'],
    'escaped strikeout opener remains text' => ['markdown' => '\\~\\~literal~~', 'text' => '~~literal~~', 'absent' => 'strikeout'],
    'escaped mark opener remains text' => ['markdown' => '\\=\\=literal==', 'text' => '==literal==', 'absent' => 'span'],
    'escaped superscript opener remains text' => ['markdown' => 'H\\^2^ packet', 'text' => 'H^2^ packet', 'absent' => 'superscript'],
    'escaped subscript opener remains text' => ['markdown' => 'H\\~2~ packet', 'text' => 'H~2~ packet', 'absent' => 'subscript'],
    'escaped emphasis pair before real emphasis' => ['markdown' => '\\*literal\\* and *real*', 'text' => '*literal* and real', 'present' => 'emph'],
    'escaped strikeout pair before real strikeout' => ['markdown' => '\\~\\~literal\\~\\~ and ~~real~~', 'text' => '~~literal~~ and real', 'present' => 'strikeout'],
    'escaped script pair before real script' => ['markdown' => 'H\\^2\\^ and E^3^', 'text' => 'H^2^ and E3', 'present' => 'superscript'],
    'escaped subscript pair before real subscript' => ['markdown' => 'H\\~2\\~ and H~3~', 'text' => 'H~2~ and H3', 'present' => 'subscript'],
];

return [
    'maps upstream markdown escaped closing delimiter surge cases' => static function (TestRunner $t) use ($escapedClosingCases, $inlineText, $firstInlineOfType): void {
        $reader = new MarkdownReader();
        $mapped = 0;

        foreach ($escapedClosingCases as $label => $case) {
            $document = $reader->read($case['markdown']);
            $node = $firstInlineOfType($document, $case['config']['type']);
            $blocks = (new WordPressBlockWriter())->write($document);
            $markdown = (new MarkdownWriter())->write($document);
            $roundTripped = $firstInlineOfType($reader->read($markdown), $case['config']['type']);

            $t->same($case['config']['type'], $node->type, $label);
            $t->same($case['text'], $inlineText($node), $label);
            $t->same($case['config']['type'], $roundTripped->type, $label . ' writer round trip type');
            $t->same($case['text'], $inlineText($roundTripped), $label . ' writer round trip text');
            if (isset($case['config']['classes'])) {
                $t->same($case['config']['classes'], $node->attr('classes'), $label);
            }
            $t->contains($case['config']['htmlStart'], $blocks, $label);
            $t->contains(htmlspecialchars($case['config']['literal'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $blocks, $label);
            $mapped++;
        }

        $t->same(51, $mapped);
    },
    'maps upstream markdown escaped opening delimiter boundary cases' => static function (TestRunner $t) use ($literalOpeningCases, $inlineText, $firstInlineOfType): void {
        $reader = new MarkdownReader();
        $mapped = 0;

        foreach ($literalOpeningCases as $label => $case) {
            $document = $reader->read($case['markdown']);
            $paragraph = $document->children[0] ?? new AstNode('missing');

            $t->same('paragraph', $paragraph->type, $label);
            $t->same($case['text'], $inlineText($paragraph), $label);
            if (isset($case['absent'])) {
                $t->same('missing', $firstInlineOfType($document, $case['absent'])->type, $label);
            }
            if (isset($case['present'])) {
                $t->same($case['present'], $firstInlineOfType($document, $case['present'])->type, $label);
            }
            $mapped++;
        }

        $t->same(12, $mapped);
    },
    'records upstream markdown delimiter escape surge mapped-case count' => static function (TestRunner $t): void {
        $t->same(63, 51 + 12);
    },
];
