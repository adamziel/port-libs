<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$emph = static fn (array $children): AstNode => new AstNode('emph', [], $children);
$code = static fn (string $value): AstNode => new AstNode('code', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);
$document = static fn (array $meta, string $body = 'Body metadata preamble.'): AstNode => new AstNode('document', ['meta' => $meta], [
    $paragraph($body),
]);

$cases = [
    'default markdown title and date inlines use pandoc title block' => [
        'document' => $document([
            'titleInlines' => [$text('Profile '), $emph([$text('Title')])],
            'dateInlines' => [$text('Built '), $code('2026-06-30')],
        ]),
        'options' => ['format' => 'markdown'],
        'expected' => "% Profile *Title*\n%\n% Built `2026-06-30`\n\nBody metadata preamble.",
    ],
    'commonmark opt-in keeps plural authors as title-block continuation lines' => [
        'document' => $document([
            'title' => 'CommonMark packet',
            'authors' => ['Ada Lovelace', 'Grace Hopper'],
        ]),
        'options' => ['format' => 'commonmark+pandoc_title_block'],
        'expected' => "% CommonMark packet\n% Ada Lovelace\n  Grace Hopper\n\nBody metadata preamble.",
        'roundTripMeta' => ['title' => 'CommonMark packet', 'author' => ['Ada Lovelace', 'Grace Hopper']],
    ],
    'gfm extension option enables title block' => [
        'document' => $document(['title' => 'GFM extension packet']),
        'options' => ['format' => 'gfm', 'extensions' => ['+pandoc_title_block']],
        'expected' => "% GFM extension packet\n\nBody metadata preamble.",
        'roundTripMeta' => ['title' => 'GFM extension packet'],
    ],
    'explicit title block option emits metadata without a format' => [
        'document' => $document([
            'title' => 'Explicit option packet',
            'author' => [[$text('Ada '), $emph([$text('Lovelace')])], [$text('Grace Hopper')]],
        ]),
        'options' => ['titleBlock' => true, 'yamlMetadata' => false],
        'expected' => "% Explicit option packet\n% Ada *Lovelace*; Grace Hopper\n\nBody metadata preamble.",
        'roundTripMeta' => ['title' => 'Explicit option packet', 'author' => ['Ada *Lovelace*', 'Grace Hopper']],
    ],
    'default gfm omits metadata when neither title block nor yaml is enabled' => [
        'document' => $document(['title' => 'Omitted packet']),
        'options' => ['format' => 'gfm'],
        'expected' => 'Body metadata preamble.',
    ],
    'disabled title block falls back to yaml metadata when available' => [
        'document' => $document(['title' => 'Disabled title packet']),
        'options' => ['format' => 'markdown-pandoc_title_block'],
        'contains' => ["---\n", 'title: "Disabled title packet"', "\n...\n\nBody metadata preamble."],
        'notContains' => ['% Disabled title packet'],
    ],
    'richer markdown metadata falls back to yaml metadata' => [
        'document' => $document([
            'title' => 'Richer metadata packet',
            'review' => ['status' => 'needs-yaml'],
        ]),
        'options' => ['format' => 'markdown'],
        'contains' => ["---\n", 'title: "Richer metadata packet"', 'review:', 'status: needs-yaml'],
        'notContains' => ['% Richer metadata packet'],
    ],
    'explicit yaml extension wins over title block when both are enabled' => [
        'document' => $document([
            'title' => 'Explicit YAML packet',
            'author' => ['Metadata Reviewer'],
        ]),
        'options' => ['format' => 'commonmark+pandoc_title_block+yaml_metadata_block'],
        'contains' => ["---\n", 'title: "Explicit YAML packet"', 'author:'],
        'notContains' => ['% Explicit YAML packet'],
    ],
    'semicolon author scalar remains yaml to avoid author splitting ambiguity' => [
        'document' => $document([
            'title' => 'Ambiguous author packet',
            'author' => 'One; Two',
        ]),
        'options' => ['format' => 'markdown'],
        'contains' => ["---\n", 'author: "One; Two"'],
        'notContains' => ['% One; Two'],
    ],
    'title scalar multiline renders continuation lines' => [
        'document' => $document(['title' => "Line one\nLine two"]),
        'options' => ['format' => 'pandoc-yaml_metadata_block'],
        'expected' => "% Line one\n  Line two\n\nBody metadata preamble.",
        'roundTripMeta' => ['title' => 'Line one Line two'],
    ],
];

$tests = [
    'records markdown writer metadata preamble profile completion mapped case count' =>
        static function (TestRunner $t) use ($cases): void {
            $t->same(10, count($cases));
        },
];

foreach ($cases as $label => $case) {
    $tests['maps upstream markdown writer metadata preamble profile completion ' . $label] =
        static function (TestRunner $t) use ($case, $label): void {
            $markdown = (new MarkdownWriter($case['options']))->write($case['document']);

            if (isset($case['expected'])) {
                $t->same($case['expected'], $markdown, 'expected markdown for ' . $label);
            }

            foreach ($case['contains'] ?? [] as $needle) {
                $t->contains($needle, $markdown, 'contains ' . $needle . ' for ' . $label);
            }

            foreach ($case['notContains'] ?? [] as $needle) {
                $t->true(!str_contains($markdown, $needle), 'does not contain ' . $needle . ' for ' . $label);
            }

            if (isset($case['roundTripMeta'])) {
                $roundTrip = (new MarkdownReader($case['options']))->read($markdown);
                $meta = $roundTrip->attr('meta', []);
                foreach ($case['roundTripMeta'] as $key => $value) {
                    $t->same($value, $meta[$key] ?? null, 'round-trip meta ' . $key . ' for ' . $label);
                }
            }
        };
}

return $tests;
