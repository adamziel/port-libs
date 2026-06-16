<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$document = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$writeText = static fn (string $value, array $options = []): string => (new MarkdownWriter($options))->write(
    $document([$paragraph([$text($value)])])
);

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code') {
        return (string) $node->attr('text', '');
    }

    if ($node->type === 'space') {
        return ' ';
    }

    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return "\n";
    }

    $text = '';
    foreach ($node->children as $child) {
        $text .= $plainText($child);
    }

    return $text;
};

$smartLiterals = [
    'spaced en dash trigger' => ['alpha -- beta', 'alpha -- beta', 'alpha \\-- beta'],
    'compact em dash trigger' => ['alpha---beta', 'alpha---beta', 'alpha\\-\\-\\-beta'],
    'ellipsis trigger' => ['alpha...beta', 'alpha...beta', 'alpha\\...beta'],
    'long dot run trigger' => ['alpha....beta', 'alpha....beta', 'alpha\\.\\.\\.\\.beta'],
    'double quote literal' => ['alpha "quote" beta', 'alpha "quote" beta', 'alpha \\"quote\\" beta'],
    'single quote literal' => ["alpha 'quote' beta", "alpha 'quote' beta", "alpha \\'quote\\' beta"],
    'apostrophe and dash literal' => ["can't -- won't", "can't -- won't", "can\\'t \\-- won\\'t"],
    'mixed quote ellipsis dash literal' => ['"alpha"... -- \'beta\'', '"alpha"... -- \'beta\'', '\\"alpha\\"\\... \\-- \\\'beta\\\''],
];

$smartProfiles = [
    'markdown default smart' => ['options' => [], 'smart' => true],
    'commonmark smart disabled' => ['options' => ['format' => 'commonmark'], 'smart' => false],
    'gfm smart disabled' => ['options' => ['format' => 'gfm'], 'smart' => false],
    'markdown strict smart disabled' => ['options' => ['format' => 'markdown_strict'], 'smart' => false],
    'markdown php extra smart disabled' => ['options' => ['format' => 'markdown_phpextra'], 'smart' => false],
    'markdown mmd smart disabled' => ['options' => ['format' => 'markdown_mmd'], 'smart' => false],
    'markdown minus smart disables smart' => ['options' => ['format' => 'markdown-smart'], 'smart' => false],
    'markdown extension disables smart' => ['options' => ['format' => 'markdown', 'extensions' => ['smart' => false]], 'smart' => false],
    'commonmark plus smart enables smart' => ['options' => ['format' => 'commonmark+smart'], 'smart' => true],
    'gfm plus smart enables smart' => ['options' => ['format' => 'gfm+smart'], 'smart' => true],
];

$cases = [];
foreach ($smartProfiles as $profileLabel => $profile) {
    foreach ($smartLiterals as $literalLabel => [$source, $smartDisabledExpected, $smartEnabledExpected]) {
        $cases["smart profile {$profileLabel} {$literalLabel}"] = [
            'source' => $source,
            'expected' => $profile['smart'] ? $smartEnabledExpected : $smartDisabledExpected,
            'options' => $profile['options'],
            'roundTrip' => true,
        ];
    }
}

$entityCases = [
    'uppercase hex A' => ['&#X41;', '&amp;#X41;'],
    'uppercase hex less-than' => ['&#X3C;', '&amp;#X3C;'],
    'uppercase padded hex less-than' => ['&#X00003C;', '&amp;#X00003C;'],
    'lowercase padded hex less-than' => ['&#x00003c;', '&amp;#x00003c;'],
    'decimal comma' => ['&#44;', '&amp;#44;'],
    'decimal replacement zero' => ['&#0;', '&amp;#0;'],
    'decimal replacement surrogate' => ['&#55296;', '&amp;#55296;'],
    'decimal max scalar' => ['&#1114111;', '&amp;#1114111;'],
    'named ampersand' => ['AT&amp;T', 'AT&amp;amp;T'],
    'named copy' => ['&copy; packet', '&amp;copy; packet'],
    'named long html5' => ['&CounterClockwiseContourIntegral;', '&amp;CounterClockwiseContourIntegral;'],
    'mixed entity run' => ['&#44;&#x44;&#X44;', '&amp;#44;&amp;#x44;&amp;#X44;'],
    'missing semicolon stays literal' => ['&copy packet', '&copy packet'],
    'empty numeric stays literal' => ['&#; packet', '&#; packet'],
    'empty hex stays literal' => ['&#x; packet', '&#x; packet'],
    'overlong decimal stays literal' => ['&#12345678; packet', '&#12345678; packet'],
    'overlong hex stays literal' => ['&#x1234567; packet', '&#x1234567; packet'],
    'overlong uppercase hex stays literal' => ['&#X1234567; packet', '&#X1234567; packet'],
];

foreach ($entityCases as $label => [$source, $expected]) {
    $cases["entity guard {$label}"] = [
        'source' => $source,
        'expected' => $expected,
        'options' => [],
        'roundTrip' => false,
    ];
}

$tests = [
    'records markdown writer entity smart profile final harvest mapped case count' => static function (TestRunner $t) use ($cases): void {
        $t->same(98, count($cases));
    },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer entity smart profile final harvest ' . $label] =
        static function (TestRunner $t) use ($case, $plainText, $writeText): void {
            $markdown = $writeText($case['source'], $case['options']);

            $t->same($case['expected'], $markdown);

            if ($case['roundTrip'] === true) {
                $roundTrip = (new MarkdownReader($case['options']))->read($markdown);
                $paragraph = $roundTrip->children[0] ?? null;

                $t->same($case['source'], $paragraph instanceof AstNode ? $plainText($paragraph) : '');
            }
        };
}

return $tests;
