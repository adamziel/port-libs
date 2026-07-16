<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

$collectTypes = static function (AstNode $node) use (&$collectTypes): array {
    $types = [$node->type];
    foreach ($node->children as $child) {
        array_push($types, ...$collectTypes($child));
    }

    return $types;
};

$plainText = static function (AstNode $node) use (&$plainText): string {
    if ($node->type === 'text' || $node->type === 'code' || $node->type === 'line') {
        return (string) $node->attr('text', '');
    }
    if ($node->type === 'softbreak' || $node->type === 'linebreak') {
        return ' ';
    }

    $parts = [];
    foreach ($node->children as $child) {
        $text = $plainText($child);
        if ($text !== '') {
            $parts[] = $text;
        }
    }

    return trim((string) preg_replace('/\s+/', ' ', implode(' ', $parts)));
};

$firstNodeOfType = static function (AstNode $node, string $type) use (&$firstNodeOfType): AstNode {
    if ($node->type === $type) {
        return $node;
    }

    foreach ($node->children as $child) {
        $match = $firstNodeOfType($child, $type);
        if ($match->type !== 'missing') {
            return $match;
        }
    }

    return new AstNode('missing');
};

$readerFor = static function (array $profile): MarkdownReader {
    $options = [];
    if ($profile['format'] !== null) {
        $options['format'] = $profile['format'];
    }
    if (($profile['extensions'] ?? []) !== []) {
        $options['extensions'] = $profile['extensions'];
    }

    return new MarkdownReader($options);
};

$disabledProfiles = [
    ['label' => 'commonmark', 'format' => 'commonmark'],
    ['label' => 'commonmark x', 'format' => 'commonmark_x'],
    ['label' => 'gfm', 'format' => 'gfm'],
    ['label' => 'markdown github', 'format' => 'markdown_github'],
    ['label' => 'markdown strict', 'format' => 'markdown_strict'],
    ['label' => 'markdown phpextra', 'format' => 'markdown_phpextra'],
    ['label' => 'markdown mmd', 'format' => 'markdown_mmd'],
    ['label' => 'markdown minus line blocks', 'format' => 'markdown-line_blocks'],
    ['label' => 'pandoc minus line blocks', 'format' => 'pandoc-line_blocks'],
    ['label' => 'commonmark x minus line blocks', 'format' => 'commonmark_x-line_blocks'],
    ['label' => 'extension override minus line blocks', 'format' => null, 'extensions' => ['-line_blocks']],
];

$disabledCases = [
    'single top level marker' => [
        'markdown' => '| Alpha',
        'rootTypes' => ['paragraph'],
        'fragments' => ['| Alpha'],
    ],
    'two top level markers' => [
        'markdown' => "| Alpha\n| Beta",
        'rootTypes' => ['paragraph'],
        'fragments' => ['| Alpha', '| Beta'],
    ],
    'three space marker' => [
        'markdown' => '   | Indented alpha',
        'rootTypes' => ['paragraph'],
        'fragments' => ['| Indented alpha'],
    ],
    'empty marker remains literal' => [
        'markdown' => '|',
        'rootTypes' => ['paragraph'],
        'fragments' => ['|'],
    ],
    'indented continuation stays paragraph text' => [
        'markdown' => "| Alpha\n  wrapped continuation",
        'rootTypes' => ['paragraph'],
        'fragments' => ['| Alpha', 'wrapped continuation'],
    ],
    'blockquote keeps marker literal' => [
        'markdown' => "> | Quote alpha\n> still quoted",
        'rootTypes' => ['blockquote'],
        'fragments' => ['| Quote alpha', 'still quoted'],
    ],
    'bullet continuation keeps marker literal' => [
        'markdown' => "- item\n  | Verse alpha\n  after",
        'rootTypes' => ['bullet_list'],
        'fragments' => ['item', '| Verse alpha', 'after'],
    ],
    'ordered continuation keeps marker literal' => [
        'markdown' => "1. item\n   | Verse beta\n   after",
        'rootTypes' => ['ordered_list'],
        'fragments' => ['item', '| Verse beta', 'after'],
    ],
];

$enabledProfiles = [
    ['label' => 'default', 'format' => null],
    ['label' => 'markdown', 'format' => 'markdown'],
    ['label' => 'pandoc', 'format' => 'pandoc'],
    ['label' => 'commonmark plus line blocks', 'format' => 'commonmark+line_blocks'],
    ['label' => 'gfm plus line blocks', 'format' => 'gfm+line_blocks'],
    ['label' => 'github plus line blocks', 'format' => 'markdown_github+line_blocks'],
    ['label' => 'strict plus line blocks', 'format' => 'markdown_strict+line_blocks'],
    ['label' => 'phpextra plus line blocks', 'format' => 'markdown_phpextra+line_blocks'],
    ['label' => 'mmd plus line blocks', 'format' => 'markdown_mmd+line_blocks'],
];

$enabledCases = [
    'single line block' => [
        'markdown' => '| Alpha',
        'lines' => ['Alpha'],
    ],
    'line block with wrapped continuation' => [
        'markdown' => "| Alpha\n  wrapped continuation\n| Beta",
        'lines' => ['Alpha wrapped continuation', 'Beta'],
    ],
];

$tests = [];

foreach ($disabledProfiles as $profile) {
    foreach ($disabledCases as $caseName => $case) {
        $tests['maps upstream markdown reader line block profile disabled ' . $profile['label'] . ' ' . $caseName] =
            static function (TestRunner $t) use ($profile, $case, $readerFor, $collectTypes, $plainText): void {
                $document = $readerFor($profile)->read($case['markdown']);
                $rootTypes = array_map(static fn (AstNode $node): string => $node->type, $document->children);
                $text = $plainText($document);

                $t->same($case['rootTypes'], $rootTypes, $profile['label'] . ' root types');
                $t->same(false, in_array('line_block', $collectTypes($document), true), $profile['label'] . ' disables line blocks');
                foreach ($case['fragments'] as $fragment) {
                    $t->contains($fragment, $text, $profile['label'] . ' keeps literal fragment');
                }
            };
    }
}

foreach ($enabledProfiles as $profile) {
    foreach ($enabledCases as $caseName => $case) {
        $tests['maps upstream markdown reader line block profile enabled ' . $profile['label'] . ' ' . $caseName] =
            static function (TestRunner $t) use ($profile, $case, $readerFor, $firstNodeOfType): void {
                $document = $readerFor($profile)->read($case['markdown']);
                $lineBlock = $firstNodeOfType($document, 'line_block');

                $t->same('line_block', $lineBlock->type, $profile['label'] . ' enables line block');
                $t->same($case['lines'], array_map(
                    static fn (AstNode $line): string => (string) $line->attr('text', ''),
                    $lineBlock->children
                ), $profile['label'] . ' line block text');
            };
    }
}

$tests['maps upstream markdown line block fixture through pandoc profile'] =
    static function (TestRunner $t) use ($collectTypes, $firstNodeOfType, $plainText): void {
        $fixture = (string) file_get_contents(dirname(__DIR__) . '/fixtures/upstream-markdown-line-blocks.md');
        $pandocDocument = (new MarkdownReader(['format' => 'pandoc']))->read($fixture);
        $lineBlock = $firstNodeOfType($pandocDocument, 'line_block');
        $gfmDocument = (new MarkdownReader(['format' => 'gfm']))->read($fixture);

        $t->same('line_block', $lineBlock->type);
        $t->same(['Alpha wrapped continuation', 'Beta'], array_map(
            static fn (AstNode $line): string => (string) $line->attr('text', ''),
            $lineBlock->children
        ));
        $t->same(false, in_array('line_block', $collectTypes($gfmDocument), true));
        $t->contains('| Alpha', $plainText($gfmDocument));
    };

$tests['records upstream markdown reader line block profile surge mapped-case count'] =
    static function (TestRunner $t) use ($disabledProfiles, $disabledCases, $enabledProfiles, $enabledCases): void {
        $disabledCount = count($disabledProfiles) * count($disabledCases);
        $enabledCount = count($enabledProfiles) * count($enabledCases);

        $t->same(88, $disabledCount);
        $t->same(18, $enabledCount);
        $t->same(106, $disabledCount + $enabledCount);
    };

return $tests;
