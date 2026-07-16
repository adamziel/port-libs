<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$inlineText = null;
$inlineText = static function (AstNode $node) use (&$inlineText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'math') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak') {
        return ' ';
    }
    if ($node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $inlineText($child);
    }

    return $text;
};

$escapedMarkers = [
    'asterisk' => ['escaped' => '\\*', 'literal' => '*'],
    'underscore' => ['escaped' => '\\_', 'literal' => '_'],
    'tilde' => ['escaped' => '\\~', 'literal' => '~'],
    'caret' => ['escaped' => '\\^', 'literal' => '^'],
];

$templates = [
    'leading' => ['escaped' => '%s leading marker', 'literal' => '%s leading marker'],
    'trailing' => ['escaped' => 'trailing marker %s', 'literal' => 'trailing marker %s'],
    'middle' => ['escaped' => 'alpha %s marker', 'literal' => 'alpha %s marker'],
    'paired' => ['escaped' => 'paired %s marker %s', 'literal' => 'paired %s marker %s'],
    'numeric' => ['escaped' => 'case 42 %s marker', 'literal' => 'case 42 %s marker'],
    'closing' => ['escaped' => 'closing %s marker', 'literal' => 'closing %s marker'],
    'whitespace' => ['escaped' => 'spaced %s marker text', 'literal' => 'spaced %s marker text'],
    'punctuation' => ['escaped' => 'punctuation %s marker.', 'literal' => 'punctuation %s marker.'],
    'inline-code-neighbor' => ['escaped' => '`code` %s marker', 'literal' => 'code %s marker'],
    'link-neighbor' => ['escaped' => '[link](https://example.test) %s marker', 'literal' => 'link %s marker'],
];

$cases = [];
foreach ($escapedMarkers as $markerName => $marker) {
    foreach ($templates as $templateName => $template) {
        $escapedText = sprintf($template['escaped'], $marker['escaped'], $marker['escaped']);
        $literalText = sprintf($template['literal'], $marker['literal'], $marker['literal']);

        $cases["atx {$markerName} {$templateName}"] = [
            'markdown' => "# {$escapedText} ###\n\n[{$literalText}]",
            'level' => 1,
            'text' => $literalText,
        ];

        $cases["setext {$markerName} {$templateName}"] = [
            'markdown' => "{$escapedText}\n---\n\n[{$literalText}]",
            'level' => 2,
            'text' => $literalText,
        ];
    }
}

$tests = [];

foreach ($cases as $name => $case) {
    $tests['maps upstream markdown reader heading escaped implicit reference residual ' . $name] =
        static function (TestRunner $t) use ($case, $inlineText): void {
            $document = (new MarkdownReader())->read($case['markdown']);
            $heading = $document->children[0] ?? new AstNode('missing');
            $paragraph = $document->children[1] ?? new AstNode('missing');
            $link = $paragraph->children[0] ?? new AstNode('missing');

            $t->same('heading', $heading->type);
            $t->same($case['level'], $heading->attr('level'));
            $t->same($case['text'], $inlineText($heading));
            $t->same('paragraph', $paragraph->type);
            $t->same('link', $link->type);
            $t->same('#' . $heading->attr('id'), $link->attr('url'));
            $t->same($case['text'], $inlineText($link));
        };
}

$tests['records upstream markdown reader heading paragraph residual mapped-case count'] =
    static function (TestRunner $t) use ($cases): void {
        $t->same(80, count($cases));
    };

return $tests;
