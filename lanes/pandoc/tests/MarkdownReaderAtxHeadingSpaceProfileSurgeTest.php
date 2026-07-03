<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$inlineText = null;
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

$strictProfiles = [
    'markdown default' => ['format' => 'markdown'],
    'pandoc alias' => ['format' => 'pandoc'],
    'upstream 3512 auto identifiers off' => ['format' => 'markdown-auto_identifiers'],
    'commonmark' => ['format' => 'commonmark'],
    'gfm' => ['format' => 'gfm'],
];

$compactParagraphCases = [
    'single compact marker' => ['markdown' => '#hi', 'text' => '#hi'],
    'double compact marker' => ['markdown' => '##two words', 'text' => '##two words'],
    'trailing hashes without space' => ['markdown' => '###three###', 'text' => '###three###'],
    'closing-looking compact marker' => ['markdown' => '####four ####', 'text' => '####four ####'],
    'numeric compact marker' => ['markdown' => '#5 bolt', 'text' => '#5 bolt'],
    'indented compact marker' => ['markdown' => '   #indented', 'text' => '#indented'],
];

$relaxedProfiles = [
    'markdown suffix' => ['format' => 'markdown-space_in_atx_header'],
    'pandoc alias suffix' => ['format' => 'pandoc-space_in_atx_header'],
    'upstream 3512 relaxed auto identifiers off' => ['format' => 'markdown-auto_identifiers-space_in_atx_header', 'autoId' => false],
    'configured array override' => ['format' => 'markdown', 'extensions' => ['space_in_atx_header' => false]],
    'configured string override' => ['format' => 'markdown', 'extensions' => '-space_in_atx_header'],
];

$compactHeadingCases = [
    'single compact marker' => ['markdown' => '#hi', 'level' => 1, 'text' => 'hi', 'id' => 'hi'],
    'double compact marker' => ['markdown' => '##two words', 'level' => 2, 'text' => 'two words', 'id' => 'two-words'],
    'trailing hashes without space' => ['markdown' => '###three###', 'level' => 3, 'text' => 'three###', 'id' => 'three'],
    'closing compact marker' => ['markdown' => '####four ####', 'level' => 4, 'text' => 'four', 'id' => 'four'],
    'attribute compact marker' => ['markdown' => '#####five {#manual}', 'level' => 5, 'text' => 'five', 'id' => 'manual'],
    'indented compact marker' => ['markdown' => '   ######six', 'level' => 6, 'text' => 'six', 'id' => 'six'],
];

$spacedProfiles = [
    'markdown default' => ['format' => 'markdown'],
    'markdown relaxed suffix' => ['format' => 'markdown-space_in_atx_header'],
    'markdown strict' => ['format' => 'markdown_strict'],
    'commonmark' => ['format' => 'commonmark'],
    'gfm' => ['format' => 'gfm'],
];

$spacedHeadingCases = [
    'empty heading' => ['markdown' => '#', 'level' => 1, 'text' => '', 'id' => 'section'],
    'simple heading' => ['markdown' => '# hi', 'level' => 1, 'text' => 'hi', 'id' => 'hi'],
    'closing fence heading' => ['markdown' => '## heading ##', 'level' => 2, 'text' => 'heading', 'id' => 'heading'],
];

$mappedCaseCount = count($strictProfiles) * count($compactParagraphCases)
    + count($relaxedProfiles) * count($compactHeadingCases)
    + count($spacedProfiles) * count($spacedHeadingCases);

return [
    'maps upstream pandoc atx heading space profile compact markers as paragraphs' =>
        static function (TestRunner $t) use ($strictProfiles, $compactParagraphCases, $inlineText): void {
            $mapped = 0;
            foreach ($strictProfiles as $profileName => $options) {
                $reader = new MarkdownReader($options);
                foreach ($compactParagraphCases as $caseName => $case) {
                    $node = $reader->read($case['markdown'])->children[0] ?? new AstNode('missing');
                    $label = $profileName . ' ' . $caseName;

                    $t->same('paragraph', $node->type, $label);
                    $t->same($case['text'], $inlineText($node), $label);
                    $mapped++;
                }
            }

            $t->same(30, $mapped);
        },
    'maps upstream pandoc atx heading space profile disabled compact markers as headings' =>
        static function (TestRunner $t) use ($relaxedProfiles, $compactHeadingCases, $inlineText): void {
            $mapped = 0;
            foreach ($relaxedProfiles as $profileName => $options) {
                $reader = new MarkdownReader($options);
                foreach ($compactHeadingCases as $caseName => $case) {
                    $heading = $reader->read($case['markdown'])->children[0] ?? new AstNode('missing');
                    $label = $profileName . ' ' . $caseName;

                    $t->same('heading', $heading->type, $label);
                    $t->same($case['level'], $heading->attr('level'), $label);
                    $t->same($case['text'], $inlineText($heading), $label);
                    $expectedId = (($options['autoId'] ?? true) === false && $case['id'] !== 'manual') ? '' : $case['id'];
                    $t->same($expectedId, $heading->attr('id', ''), $label);
                    $mapped++;
                }
            }

            $t->same(30, $mapped);
        },
    'keeps upstream pandoc atx spaced heading fixtures stable across profiles' =>
        static function (TestRunner $t) use ($spacedProfiles, $spacedHeadingCases, $inlineText): void {
            $mapped = 0;
            foreach ($spacedProfiles as $profileName => $options) {
                $reader = new MarkdownReader($options);
                foreach ($spacedHeadingCases as $caseName => $case) {
                    $heading = $reader->read($case['markdown'])->children[0] ?? new AstNode('missing');
                    $label = $profileName . ' ' . $caseName;

                    $t->same('heading', $heading->type, $label);
                    $t->same($case['level'], $heading->attr('level'), $label);
                    $t->same($case['text'], $inlineText($heading), $label);
                    $expectedId = in_array($options['format'], ['commonmark', 'markdown_strict'], true) ? '' : $case['id'];
                    $t->same($expectedId, $heading->attr('id'), $label);
                    $mapped++;
                }
            }

            $t->same(15, $mapped);
        },
    'records upstream pandoc atx heading space profile mapped-case count' =>
        static function (TestRunner $t) use ($mappedCaseCount): void {
            $t->same(75, $mappedCaseCount);
        },
];
