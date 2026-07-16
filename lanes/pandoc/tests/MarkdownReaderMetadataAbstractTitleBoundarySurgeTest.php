<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;

/**
 * @return list<string>
 */
$yamlBlockLines = static function (string $source): array {
    $lines = [];
    foreach (explode("\n", $source) as $line) {
        $lines[] = '  ' . $line;
    }

    return $lines;
};

$readerProfiles = [
    'default pandoc reader' => [],
    'markdown profile' => ['format' => 'markdown'],
    'pandoc profile' => ['format' => 'pandoc'],
    'explicit title block option' => ['titleBlock' => true],
    'commonmark with metadata title extension' => ['format' => 'commonmark+yaml_metadata_block+pandoc_title_block'],
    'commonmark x with metadata title extension' => ['format' => 'commonmark_x+yaml_metadata_block+pandoc_title_block'],
    'gfm with metadata title extension' => ['format' => 'gfm+yaml_metadata_block+pandoc_title_block'],
    'strict markdown with metadata title extension' => ['format' => 'markdown_strict+yaml_metadata_block+pandoc_title_block'],
    'php extra with metadata title extension' => ['format' => 'markdown_phpextra+yaml_metadata_block+pandoc_title_block'],
    'mmd with metadata title extension' => ['format' => 'markdown_mmd+yaml_metadata_block+pandoc_title_block'],
];

$abstractSources = [
    'plain percent opener' => [
        'source' => static fn (string $label): string => "% Abstract boundary {$label}\nBody {$label}.",
        'prefix' => static fn (string $label): string => "% Abstract boundary {$label}",
        'raw' => static fn (string $label): string => "% Abstract boundary {$label}",
    ],
    'bold percent opener' => [
        'source' => static fn (string $label): string => "% **Bold abstract {$label}**\nBody {$label}.",
        'prefix' => static fn (string $label): string => "% Bold abstract {$label}",
        'raw' => static fn (string $label): string => "% **Bold abstract {$label}**",
    ],
    'code percent opener' => [
        'source' => static fn (string $label): string => "% `Code {$label}`\nBody {$label}.",
        'prefix' => static fn (string $label): string => "% Code {$label}",
        'raw' => static fn (string $label): string => "% `Code {$label}`",
    ],
    'link percent opener' => [
        'source' => static fn (string $label): string => "% [Link {$label}](/abstract-{$label})\nBody {$label}.",
        'prefix' => static fn (string $label): string => "% Link {$label}",
        'raw' => static fn (string $label): string => "% [Link {$label}](/abstract-{$label})",
    ],
    'multiple percent opener lines' => [
        'source' => static fn (string $label): string => "% First {$label}\n% Second {$label}\nBody {$label}.",
        'prefix' => static fn (string $label): string => "% First {$label} % Second {$label}",
        'raw' => static fn (string $label): string => "% Second {$label}",
    ],
];

$slug = static function (string $value): string {
    return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($value)), '-');
};

$tests = [];

foreach ($readerProfiles as $profileName => $options) {
    foreach ($abstractSources as $sourceName => $case) {
        $tests['maps upstream markdown reader metadata abstract title boundary '
            . $profileName . ' '
            . $sourceName] = static function (TestRunner $t) use (
                $case,
                $options,
                $profileName,
                $slug,
                $sourceName,
                $yamlBlockLines
            ): void {
                $label = $slug($profileName . '-' . $sourceName);
                $source = $case['source']($label);
                $document = (new MarkdownReader($options))->read(implode("\n", array_merge(
                    [
                        '---',
                        'title: Abstract boundary ' . $label,
                        'abstract: |',
                    ],
                    $yamlBlockLines($source),
                    [
                        '...',
                        '',
                        'Main body ' . $label . '.',
                    ]
                )));

                $meta = $document->attr('meta', []);
                $abstractBlocks = $meta['abstractBlocks'] ?? [];
                $firstAbstractBlock = $abstractBlocks[0] ?? new AstNode('missing');
                $body = $document->children[0] ?? new AstNode('missing');
                $firstText = (string) $firstAbstractBlock->attr('text', '');

                $t->same('Abstract boundary ' . $label, $meta['title'] ?? null);
                $t->contains($case['raw']($label), (string) ($meta['abstract'] ?? ''));
                $t->same('paragraph', $firstAbstractBlock->type);
                $t->true(
                    str_starts_with($firstText, $case['prefix']($label)),
                    'Abstract block should keep percent-leading source text for ' . $label
                );
                $t->contains('Body ' . $label . '.', $firstText);
                $t->same('paragraph', $body->type);
                $t->same('Main body ' . $label . '.', $body->attr('text'));
            };
    }
}

$tests['records upstream markdown reader metadata abstract title boundary mapped-case count'] =
    static function (TestRunner $t) use ($readerProfiles, $abstractSources): void {
        $t->same(50, count($readerProfiles) * count($abstractSources));
    };

return $tests;
