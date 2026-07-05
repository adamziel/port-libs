<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownFormatProfile;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (string $value): AstNode => new AstNode('paragraph', [], [$text($value)]);

$profileCases = [
    ['format' => 'markdown', 'canonical' => 'markdown', 'yamlMetadata' => true, 'titleBlock' => true, 'rawAttribute' => true, 'rawTex' => true, 'footnotes' => true],
    ['format' => 'pandoc', 'canonical' => 'markdown', 'yamlMetadata' => true, 'titleBlock' => true, 'rawAttribute' => true, 'rawTex' => true, 'footnotes' => true],
    ['format' => 'commonmark', 'canonical' => 'commonmark', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => false],
    ['format' => 'commonmark_x', 'canonical' => 'commonmark_x', 'yamlMetadata' => true, 'titleBlock' => false, 'rawAttribute' => true, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'commonmark-x', 'canonical' => 'commonmark_x', 'yamlMetadata' => true, 'titleBlock' => false, 'rawAttribute' => true, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'gfm', 'canonical' => 'gfm', 'yamlMetadata' => true, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => true],
    ['format' => 'markdown_github', 'canonical' => 'gfm', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => true],
    ['format' => 'markdown-github', 'canonical' => 'gfm', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => true],
    ['format' => 'markdown+github', 'canonical' => 'gfm', 'yamlMetadata' => true, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => true],
    ['format' => 'markdown_mmd', 'canonical' => 'markdown_mmd', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => true, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'markdown-mmd', 'canonical' => 'markdown_mmd', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => true, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'markdown+mmd', 'canonical' => 'markdown_mmd', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => true, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'markdown+multimarkdown', 'canonical' => 'markdown_mmd', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => true, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'markdown_phpextra', 'canonical' => 'markdown_phpextra', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'markdown-php-extra', 'canonical' => 'markdown_phpextra', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'markdown+php_extra', 'canonical' => 'markdown_phpextra', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'markdown+php-extra', 'canonical' => 'markdown_phpextra', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'markdown+phpextra', 'canonical' => 'markdown_phpextra', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => true, 'writerRawTex' => true],
    ['format' => 'markdown_strict', 'canonical' => 'markdown_strict', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => false, 'writerRawTex' => true],
    ['format' => 'markdown-strict', 'canonical' => 'markdown_strict', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => false, 'writerRawTex' => true],
    ['format' => 'markdown+strict', 'canonical' => 'markdown_strict', 'yamlMetadata' => false, 'titleBlock' => false, 'rawAttribute' => false, 'rawTex' => false, 'footnotes' => false, 'writerRawTex' => true],
];

$slug = static function (string $format): string {
    return trim((string) preg_replace('/[^A-Za-z0-9]+/', '-', strtolower($format)), '-');
};

$inlineTypes = static function (AstNode $document): array {
    $paragraph = $document->children[0] ?? new AstNode('missing');

    return array_map(static fn (AstNode $node): string => $node->type, $paragraph->children);
};

$tests = [];

foreach ($profileCases as $case) {
    $tests['maps upstream markdown format profile yaml metadata gate ' . $case['format']] =
        static function (TestRunner $t) use ($case, $slug): void {
            $label = $slug($case['format']);
            $document = (new MarkdownReader(['format' => $case['format']]))->read(implode("\n", [
                '---',
                'title: YAML profile ' . $label,
                'review: {format: "' . $case['format'] . '", kind: yaml}',
                '...',
                '',
                'Body ' . $label . '.',
            ]));
            $meta = $document->attr('meta', []);

            $t->same($case['canonical'], MarkdownFormatProfile::canonicalFormat($case['format']));
            $t->same($case['yamlMetadata'], ($meta['title'] ?? null) === 'YAML profile ' . $label);
            if ($case['yamlMetadata']) {
                $t->same('yaml', $meta['review']['kind'] ?? null);
                $t->same('paragraph', ($document->children[0] ?? new AstNode('missing'))->type);
            } else {
                $t->same(false, array_key_exists('review', $meta));
            }
        };

    $tests['maps upstream markdown format profile title block gate ' . $case['format']] =
        static function (TestRunner $t) use ($case, $slug): void {
            $label = $slug($case['format']);
            $document = (new MarkdownReader(['format' => $case['format']]))->read(implode("\n", [
                '% Title profile ' . $label,
                '% Author ' . $label,
                '% 2026-06-15',
                '',
                'Body ' . $label . '.',
            ]));
            $meta = $document->attr('meta', []);

            $t->same($case['titleBlock'], ($meta['title'] ?? null) === 'Title profile ' . $label);
            if ($case['titleBlock']) {
                $t->same(['Author ' . $label], $meta['author'] ?? null);
                $t->same('2026-06-15', $meta['date'] ?? null);
                $t->same('paragraph', ($document->children[0] ?? new AstNode('missing'))->type);
            } else {
                $t->same(false, array_key_exists('author', $meta));
            }
        };

    $tests['maps upstream markdown format profile raw attribute inline gate ' . $case['format']] =
        static function (TestRunner $t) use ($case, $slug, $inlineTypes): void {
            $label = $slug($case['format']);
            $raw = '<span data-profile="' . $label . '">inline</span>';
            $document = (new MarkdownReader(['format' => $case['format']]))->read(
                'Before `' . $raw . '`{=html} after.'
            );
            $types = $inlineTypes($document);

            $t->same($case['rawAttribute'], in_array('raw_html_inline', $types, true));
            if ($case['rawAttribute']) {
                $rawInline = ($document->children[0] ?? new AstNode('missing'))->children[1] ?? new AstNode('missing');
                $t->same('html', $rawInline->attr('format'));
                $t->same($raw, $rawInline->attr('text'));
            } else {
                $t->same(true, in_array('code', $types, true));
            }
        };

    $tests['maps upstream markdown format profile raw attribute block gate ' . $case['format']] =
        static function (TestRunner $t) use ($case, $slug): void {
            $label = $slug($case['format']);
            $raw = '<section data-profile="' . $label . '">block</section>';
            $document = (new MarkdownReader(['format' => $case['format']]))->read(implode("\n", [
                '```{=html}',
                $raw,
                '```',
                '',
                'After.',
            ]));
            $first = $document->children[0] ?? new AstNode('missing');

            $t->same($case['rawAttribute'] ? 'raw_block' : 'code_block', $first->type);
            if ($case['rawAttribute']) {
                $t->same('html', $first->attr('format'));
                $t->same($raw, $first->attr('text'));
            } else {
                $t->same('{=html}', $first->attr('info'));
            }
        };

    $tests['maps upstream markdown format profile raw tex inline gate ' . $case['format']] =
        static function (TestRunner $t) use ($case, $slug, $inlineTypes): void {
            $label = $slug($case['format']);
            $document = (new MarkdownReader(['format' => $case['format']]))->read(
                'Before \\textbf{' . $label . '} after.'
            );
            $types = $inlineTypes($document);

            $t->same($case['rawTex'], in_array('raw_tex_inline', $types, true));
            if (!$case['rawTex']) {
                $t->same(false, in_array('raw_inline', $types, true));
            }
        };

    $tests['maps upstream markdown format profile raw tex block gate ' . $case['format']] =
        static function (TestRunner $t) use ($case, $slug): void {
            $label = $slug($case['format']);
            $document = (new MarkdownReader(['format' => $case['format']]))->read(implode("\n", [
                '\\begin{center}',
                'profile-' . $label,
                '\\end{center}',
            ]));
            $first = $document->children[0] ?? new AstNode('missing');

            $t->same($case['rawTex'] ? 'raw_tex' : 'paragraph', $first->type);
            if ($case['rawTex']) {
                $t->contains('profile-' . $label, $first->attr('tex'));
            }
        };

    $tests['maps upstream markdown format profile footnote gate ' . $case['format']] =
        static function (TestRunner $t) use ($case, $slug): void {
            $label = $slug($case['format']);
            $document = (new MarkdownReader(['format' => $case['format']]))->read(implode("\n", [
                'Ref[^' . $label . '].',
                '',
                '[^' . $label . ']: Footnote profile ' . $label . '.',
            ]));
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $hasNote = false;
            foreach ($paragraph->children as $inline) {
                if ($inline->type === 'note') {
                    $hasNote = true;
                    break;
                }
            }

            $t->same($case['footnotes'], $hasNote);
        };

    $tests['maps upstream markdown writer format profile yaml metadata default ' . $case['format']] =
        static function (TestRunner $t) use ($case, $slug, $paragraph): void {
            $label = $slug($case['format']);
            $document = new AstNode('document', [
                'meta' => [
                    'title' => 'Writer profile ' . $label,
                    'review' => ['format' => $case['format'], 'kind' => 'writer-yaml'],
                ],
            ], [
                $paragraph('Body ' . $label . '.'),
            ]);
            $markdown = (new MarkdownWriter(['format' => $case['format']]))->write($document);

            $t->same($case['yamlMetadata'], str_starts_with($markdown, "---\n"));
            if ($case['yamlMetadata']) {
                $t->contains('title: "Writer profile ' . $label . '"', $markdown);
                $t->contains('kind: writer-yaml', $markdown);
            } else {
                $t->same('Body ' . $label . '.', $markdown);
            }
        };

    $tests['maps upstream markdown writer format profile raw family policy ' . $case['format']] =
        static function (TestRunner $t) use ($case, $slug, $text): void {
            $label = $slug($case['format']);
            $document = new AstNode('document', [], [
                new AstNode('paragraph', [], [
                    $text('Inline ' . $label . ': '),
                    new AstNode('raw_html_inline', ['html' => '<span data-profile="' . $label . '">html</span>']),
                    $text(' '),
                    new AstNode('raw_tex', ['tex' => '\\LaTeX{}']),
                    $text(' '),
                    new AstNode('raw_markdown', ['markdown' => '**raw-' . $label . '**']),
                ]),
                new AstNode('raw_html', ['html' => '<section data-profile="' . $label . '">html block</section>']),
                new AstNode('raw_tex', ['tex' => '\\begin{center}' . "\n" . $label . "\n" . '\\end{center}']),
                new AstNode('raw_markdown', ['markdown' => '> raw ' . $label]),
            ]);
            $markdown = (new MarkdownWriter(['format' => $case['format']]))->write($document);
            $writerRawTex = $case['writerRawTex'] ?? $case['rawTex'];

            $t->contains('<span data-profile="' . $label . '">html</span>', $markdown);
            $t->contains('<section data-profile="' . $label . '">html block</section>', $markdown);
            $t->contains('**raw-' . $label . '**', $markdown);
            $t->contains('> raw ' . $label, $markdown);
            $t->same($writerRawTex, str_contains($markdown, '\\LaTeX{}'));
            $t->same($writerRawTex, str_contains($markdown, '\\begin{center}'));
        };
}

$overrideCases = [
    [
        'name' => 'commonmark raw tex extension leaves inline raw tex literal',
        'assert' => static function (TestRunner $t, callable $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'commonmark+raw_tex']))->read('Before \\textbf{raw} after.');
            $types = $inlineTypes($document);
            $t->same(false, in_array('raw_tex', $types, true));
            $t->same(['text'], $types);
        },
    ],
    [
        'name' => 'markdown raw tex extension disables inline raw tex',
        'assert' => static function (TestRunner $t, callable $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown-raw_tex']))->read('Before \\textbf{raw} after.');
            $t->same(false, in_array('raw_tex', $inlineTypes($document), true));
        },
    ],
    [
        'name' => 'gfm raw attribute extension enables html raw attribute',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'gfm+raw_attribute']))->read('Before `<b>raw</b>`{=html} after.');
            $raw = $document->children[0]->children[1] ?? new AstNode('missing');
            $t->same('raw_html_inline', $raw->type);
            $t->same('html', $raw->attr('format'));
            $t->same('<b>raw</b>', $raw->attr('html'));
        },
    ],
    [
        'name' => 'markdown raw attribute extension disables html raw attribute',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'markdown-raw_attribute']))->read('Before `<b>raw</b>`{=html} after.');
            $types = array_map(static fn (AstNode $node): string => $node->type, $document->children[0]->children);
            $t->same(false, in_array('raw_html_inline', $types, true));
        },
    ],
    [
        'name' => 'commonmark yaml extension enables metadata',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'commonmark+yaml_metadata_block']))->read("---\ntitle: CommonMark YAML\n...\n\nBody.");
            $t->same('CommonMark YAML', $document->attr('meta', [])['title'] ?? null);
        },
    ],
    [
        'name' => 'markdown yaml extension disables metadata',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'markdown-yaml_metadata_block']))->read("---\ntitle: Disabled YAML\n...\n\nBody.");
            $t->same(false, array_key_exists('title', $document->attr('meta', [])));
        },
    ],
    [
        'name' => 'commonmark title extension enables title block',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'commonmark+pandoc_title_block']))->read("% CommonMark title\n% Reviewer\n% Today\n\nBody.");
            $t->same('CommonMark title', $document->attr('meta', [])['title'] ?? null);
        },
    ],
    [
        'name' => 'markdown title extension disables title block',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'markdown-pandoc_title_block']))->read("% Disabled title\n% Reviewer\n% Today\n\nBody.");
            $t->same(false, array_key_exists('title', $document->attr('meta', [])));
        },
    ],
    [
        'name' => 'markdown raw html extension disables inline raw html',
        'assert' => static function (TestRunner $t, callable $inlineTypes): void {
            $document = (new MarkdownReader(['format' => 'markdown-raw_html']))->read('Before <span>raw</span> after.');
            $t->same(false, in_array('raw_html_inline', $inlineTypes($document), true));
        },
    ],
    [
        'name' => 'commonmark raw html extension disables block raw html',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'commonmark-raw_html']))->read('<section>raw</section>');
            $t->same(false, in_array(($document->children[0] ?? new AstNode('missing'))->type, ['raw_html', 'raw_block'], true));
        },
    ],
    [
        'name' => 'commonmark disabled footnotes keeps caret note syntax literal',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'commonmark']))->read("Ref[^a].\n\n[^a]: note body");
            $t->same(2, count($document->children));
            $t->same('Ref[^a].', ($document->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('[^a]: note body', ($document->children[1] ?? new AstNode('missing'))->attr('text'));
        },
    ],
    [
        'name' => 'gfm footnotes extension disable keeps caret note syntax literal',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'gfm-footnotes']))->read("Ref[^a].\n\n[^a]: note body");
            $t->same(2, count($document->children));
            $t->same('Ref[^a].', ($document->children[0] ?? new AstNode('missing'))->attr('text'));
            $t->same('[^a]: note body', ($document->children[1] ?? new AstNode('missing'))->attr('text'));
        },
    ],
    [
        'name' => 'markdown footnotes extension disable treats caret label as reference',
        'assert' => static function (TestRunner $t): void {
            $document = (new MarkdownReader(['format' => 'markdown-footnotes']))->read("Ref[^a].\n\n[^a]: note body");
            $paragraph = $document->children[0] ?? new AstNode('missing');
            $link = $paragraph->children[1] ?? new AstNode('missing');

            $t->same(1, count($document->children));
            $t->same('link', $link->type);
            $t->same('note%20body', $link->attr('url'));
        },
    ],
];

foreach ($overrideCases as $case) {
    $tests['maps upstream markdown format profile override ' . $case['name']] =
        static function (TestRunner $t) use ($case, $inlineTypes): void {
            $case['assert']($t, $inlineTypes);
        };
}

$tests['records upstream markdown format profile surge mapped-case count'] =
    static function (TestRunner $t) use ($profileCases, $overrideCases): void {
        $t->same(202, count($profileCases) * 9 + count($overrideCases));
    };

return $tests;
