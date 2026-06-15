<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return array{markdown:string, url:string, title:string}
 */
$buildFigureCaptionMarkdown = static function (array $case): array {
    $url = 'media/figure-caption-' . $case['caseId'] . '.png';
    $title = 'Figure title ' . $case['caseId'];

    $markdown = match ($case['targetMode']) {
        'inline-target' => '![' . $case['label'] . '](' . $url . ' "' . $title . '")' . $case['attributeSource'],
        'full-reference' => implode("\n", [
            '![' . $case['label'] . '][fig-' . $case['caseId'] . ']' . $case['attributeSource'],
            '',
            '[fig-' . $case['caseId'] . ']: ' . $url . ' "' . $title . '"',
        ]),
        'shortcut-reference' => implode("\n", [
            '![' . $case['label'] . ']' . $case['attributeSource'],
            '',
            '[' . $case['label'] . ']: ' . $url . ' "' . $title . '"',
        ]),
    };

    return [
        'markdown' => $markdown,
        'url' => $url,
        'title' => $title,
    ];
};

$inlineVariants = [
    'emphasis caption label' => [
        'labelTemplate' => 'Review *caption* %s',
        'plainTemplate' => 'Review caption %s',
        'htmlTemplate' => 'Review <em>caption</em> %s',
        'type' => 'emph',
    ],
    'strong caption label' => [
        'labelTemplate' => 'Review **caption** %s',
        'plainTemplate' => 'Review caption %s',
        'htmlTemplate' => 'Review <strong>caption</strong> %s',
        'type' => 'strong',
    ],
    'code caption label' => [
        'labelTemplate' => 'Review `caption` %s',
        'plainTemplate' => 'Review caption %s',
        'htmlTemplate' => 'Review <code>caption</code> %s',
        'type' => 'code',
    ],
    'strikeout caption label' => [
        'labelTemplate' => 'Review ~~caption~~ %s',
        'plainTemplate' => 'Review caption %s',
        'htmlTemplate' => 'Review <del>caption</del> %s',
        'type' => 'strikeout',
    ],
    'superscript caption label' => [
        'labelTemplate' => 'Review E=mc^2^ %s',
        'plainTemplate' => 'Review E=mc2 %s',
        'htmlTemplate' => 'Review E=mc<sup>2</sup> %s',
        'type' => 'superscript',
    ],
    'subscript caption label' => [
        'labelTemplate' => 'Review H~2~O %s',
        'plainTemplate' => 'Review H2O %s',
        'htmlTemplate' => 'Review H<sub>2</sub>O %s',
        'type' => 'subscript',
    ],
    'raw tex caption label' => [
        'labelTemplate' => 'Review \LaTeX{} %s',
        'plainTemplate' => 'Review \LaTeX{} %s',
        'htmlTemplate' => 'Review <span class="pandoc-raw-tex">\LaTeX{}</span> %s',
        'type' => 'raw_tex',
    ],
    'mark caption label' => [
        'labelTemplate' => 'Review ==caption== %s',
        'plainTemplate' => 'Review caption %s',
        'htmlTemplate' => 'Review <span class="mark">caption</span> %s',
        'type' => 'span',
    ],
    'math caption label' => [
        'labelTemplate' => 'Review $x+y$ %s',
        'plainTemplate' => 'Review x+y %s',
        'htmlTemplate' => 'Review <span class="math inline">\(x+y\)</span> %s',
        'type' => 'math',
    ],
];

$attributeVariants = [
    'identity attributes' => static function (string $caseId): array {
        return [
            'attributeSource' => '{#figure-caption-' . $caseId . ' .review-figure .case-' . $caseId . ' data-source="figure-' . $caseId . '"}',
            'expectedId' => 'figure-caption-' . $caseId,
            'expectedClasses' => ['review-figure', 'case-' . $caseId],
            'expectedAttributes' => ['data-source' => 'figure-' . $caseId],
            'expectedAlt' => null,
            'expectedFigureHtmlAttribute' => 'id="figure-caption-' . $caseId . '"',
        ];
    },
    'latex placement alt attributes' => static function (string $caseId): array {
        return [
            'attributeSource' => '{latex-placement="htbp" alt="Alt ' . $caseId . '" data-source="figure-' . $caseId . '"}',
            'expectedId' => null,
            'expectedClasses' => [],
            'expectedAttributes' => ['latex-placement' => 'htbp', 'data-source' => 'figure-' . $caseId],
            'expectedAlt' => 'Alt ' . $caseId,
            'expectedFigureHtmlAttribute' => 'data-pandoc-latex-placement="htbp"',
        ];
    },
];

$targetModes = ['inline-target', 'full-reference', 'shortcut-reference'];
$cases = [];
$caseNumber = 1;
foreach ($inlineVariants as $inlineName => $inlineVariant) {
    foreach ($targetModes as $targetMode) {
        foreach ($attributeVariants as $attributeName => $attributeBuilder) {
            $caseId = str_pad((string) $caseNumber, 3, '0', STR_PAD_LEFT);
            $attribute = $attributeBuilder($caseId);
            $label = sprintf($inlineVariant['labelTemplate'], $caseId);
            $plain = sprintf($inlineVariant['plainTemplate'], $caseId);
            $cases[] = [
                'caseId' => $caseId,
                'name' => sprintf(
                    'maps upstream markdown figure caption surge %s %s %s',
                    $caseId,
                    $inlineName,
                    str_replace('-', ' ', $targetMode . ' ' . $attributeName)
                ),
                'label' => $label,
                'plainCaption' => $plain,
                'htmlCaption' => sprintf($inlineVariant['htmlTemplate'], $caseId),
                'expectedType' => $inlineVariant['type'],
                'targetMode' => $targetMode,
                ...$attribute,
            ];
            $caseNumber++;
        }
    }
}

$tests = [];

foreach ($cases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $buildFigureCaptionMarkdown): void {
        $source = $buildFigureCaptionMarkdown($case);
        $document = (new MarkdownReader())->read($source['markdown']);
        $figure = $document->children[0] ?? new AstNode('missing');
        $image = $figure->children[0] ?? new AstNode('missing');
        $captionInlines = $figure->attr('captionInlines', []);
        $captionSource = $figure->attr('captionSource', []);
        $blocks = (new WordPressBlockWriter())->write($document);
        $markdown = (new MarkdownWriter())->write($document);

        $t->same(1, count($document->children), $case['caseId'] . ' should parse to one figure block');
        $t->same('figure', $figure->type, $case['caseId'] . ' figure type');
        $t->same($case['plainCaption'], $figure->attr('caption'), $case['caseId'] . ' plain figure caption');
        $t->same(true, $figure->attr('renderCaptionInlines'), $case['caseId'] . ' render caption inlines flag');
        $t->true(is_array($captionInlines) && $captionInlines !== [], $case['caseId'] . ' figure caption inlines are present');
        $t->true(
            in_array($case['expectedType'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines), true),
            $case['caseId'] . ' caption inline type ' . $case['expectedType'] . ' is preserved'
        );
        $t->same('markdown-image-label', $captionSource['element'] ?? null, $case['caseId'] . ' caption source element');
        $t->same('implicit-figure', $captionSource['position'] ?? null, $case['caseId'] . ' caption source position');
        $t->same('image', $image->type, $case['caseId'] . ' child image type');
        $t->same($source['url'], $image->attr('url'), $case['caseId'] . ' child image URL');
        $t->same($source['title'], $image->attr('title'), $case['caseId'] . ' child image title');
        $t->same($case['expectedAlt'] ?? $case['plainCaption'], $image->attr('alt'), $case['caseId'] . ' child image alt');
        $t->same($case['plainCaption'], $image->attr('caption'), $case['caseId'] . ' child image plain caption');
        if ($case['expectedId'] !== null) {
            $t->same($case['expectedId'], $figure->attr('id'), $case['caseId'] . ' figure id');
        }
        if ($case['expectedClasses'] !== []) {
            $t->same($case['expectedClasses'], $figure->attr('classes'), $case['caseId'] . ' figure classes');
        }
        $t->same($case['expectedAttributes'], $figure->attr('attributes'), $case['caseId'] . ' figure attributes');
        $t->contains('<figcaption>' . $case['htmlCaption'] . '</figcaption>', $blocks);
        $t->contains($case['expectedFigureHtmlAttribute'], $blocks);
        $t->contains('![' . $case['label'] . '](' . $source['url'], $markdown);
    };
}

$tests['records upstream markdown figure caption surge mapped-case count'] = static function (TestRunner $t) use ($cases): void {
    $t->same(54, count($cases));
};

$firstFigure = static function (AstNode $document): AstNode {
    foreach ($document->children as $node) {
        if ($node->type === 'figure') {
            return $node;
        }
    }

    return new AstNode('missing');
};

$inlineTypes = static fn (array $nodes): array => array_values(array_map(
    static fn (AstNode $node): string => $node->type,
    $nodes
));

$captionSourceCases = [
    'emphasis' => [
        'label' => '*emphasis* caption',
        'plain' => 'emphasis caption',
        'types' => ['emph', 'text'],
        'html' => '<em>emphasis</em> caption',
    ],
    'strong' => [
        'label' => '**strong** caption',
        'plain' => 'strong caption',
        'types' => ['strong', 'text'],
        'html' => '<strong>strong</strong> caption',
    ],
    'code' => [
        'label' => '`code` caption',
        'plain' => 'code caption',
        'types' => ['code', 'text'],
        'html' => '<code>code</code> caption',
    ],
    'strikeout' => [
        'label' => '~~removed~~ caption',
        'plain' => 'removed caption',
        'types' => ['strikeout', 'text'],
        'html' => '<del>removed</del> caption',
    ],
    'superscript' => [
        'label' => '^2^ caption',
        'plain' => '2 caption',
        'types' => ['superscript', 'text'],
        'html' => '<sup>2</sup> caption',
    ],
    'subscript' => [
        'label' => '~n~ caption',
        'plain' => 'n caption',
        'types' => ['subscript', 'text'],
        'html' => '<sub>n</sub> caption',
    ],
    'smallcaps' => [
        'label' => '[small caps]{.smallcaps} caption',
        'plain' => 'small caps caption',
        'types' => ['small_caps', 'text'],
        'html' => '<span style="font-variant:small-caps">small caps</span> caption',
    ],
    'underline' => [
        'label' => '[underlined]{.underline} caption',
        'plain' => 'underlined caption',
        'types' => ['underline', 'text'],
        'html' => '<u>underlined</u> caption',
    ],
    'mark' => [
        'label' => '==marked== caption',
        'plain' => 'marked caption',
        'types' => ['span', 'text'],
        'html' => '<span class="mark">marked</span> caption',
    ],
];

$sourceCases = [
    'inline' => static function (string $slug, string $label, int $number): array {
        return [
            'markdown' => "![{$label}](media/{$slug}.png)",
            'url' => "media/{$slug}.png",
            'title' => '',
            'alt' => null,
            'id' => null,
            'classes' => [],
            'attributes' => [],
            'writerContains' => "](media/{$slug}.png)",
        ];
    },
    'inline-title' => static function (string $slug, string $label, int $number): array {
        return [
            'markdown' => "![{$label}](media/{$slug}.png \"Figure title {$number}\")",
            'url' => "media/{$slug}.png",
            'title' => "Figure title {$number}",
            'alt' => null,
            'id' => null,
            'classes' => [],
            'attributes' => [],
            'writerContains' => "\"Figure title {$number}\"",
        ];
    },
    'inline-attributes' => static function (string $slug, string $label, int $number): array {
        return [
            'markdown' => "![{$label}](media/{$slug}.png \"Figure title {$number}\"){#fig-{$slug} .review-figure data-source=\"surge-{$number}\"}",
            'url' => "media/{$slug}.png",
            'title' => "Figure title {$number}",
            'alt' => null,
            'id' => "fig-{$slug}",
            'classes' => ['review-figure'],
            'attributes' => ['data-source' => "surge-{$number}"],
            'writerContains' => "{#fig-{$slug} .review-figure data-source=\"surge-{$number}\"}",
        ];
    },
    'inline-alt-override' => static function (string $slug, string $label, int $number): array {
        return [
            'markdown' => "![{$label}](media/{$slug}.png){alt=\"Alt override {$number}\"}",
            'url' => "media/{$slug}.png",
            'title' => '',
            'alt' => "Alt override {$number}",
            'id' => null,
            'classes' => [],
            'attributes' => [],
            'writerContains' => "alt=\"Alt override {$number}\"",
        ];
    },
    'reference' => static function (string $slug, string $label, int $number): array {
        return [
            'markdown' => "![{$label}][fig-{$slug}]\n\n[fig-{$slug}]: media/{$slug}.png \"Figure title {$number}\"",
            'url' => "media/{$slug}.png",
            'title' => "Figure title {$number}",
            'alt' => null,
            'id' => null,
            'classes' => [],
            'attributes' => [],
            'writerContains' => "media/{$slug}.png \"Figure title {$number}\"",
        ];
    },
    'reference-attributes' => static function (string $slug, string $label, int $number): array {
        return [
            'markdown' => "![{$label}][fig-{$slug}]{#ref-{$slug} .reference-figure data-source=\"reference-{$number}\"}\n\n[fig-{$slug}]: media/{$slug}.png \"Figure title {$number}\"",
            'url' => "media/{$slug}.png",
            'title' => "Figure title {$number}",
            'alt' => null,
            'id' => "ref-{$slug}",
            'classes' => ['reference-figure'],
            'attributes' => ['data-source' => "reference-{$number}"],
            'writerContains' => "{#ref-{$slug} .reference-figure data-source=\"reference-{$number}\"}",
        ];
    },
    'dimension-attributes' => static function (string $slug, string $label, int $number): array {
        return [
            'markdown' => "![{$label}](media/{$slug}.png){width=\"640\" height=\"480\" data-source=\"dimension-{$number}\"}",
            'url' => "media/{$slug}.png",
            'title' => '',
            'alt' => null,
            'id' => null,
            'classes' => [],
            'attributes' => ['width' => '640', 'height' => '480', 'data-source' => "dimension-{$number}"],
            'writerContains' => "width=\"640\" height=\"480\" data-source=\"dimension-{$number}\"",
        ];
    },
];

$sourceCaseCount = 0;
$caseNumber = 1;
foreach ($captionSourceCases as $captionName => $captionCase) {
    foreach ($sourceCases as $sourceName => $sourceFactory) {
        $number = $caseNumber++;
        $sourceCaseCount++;
        $slug = "{$captionName}-{$sourceName}-{$number}";
        $source = $sourceFactory($slug, $captionCase['label'], $number);

        $tests["maps upstream markdown implicit figure source caption {$captionName} {$sourceName}"] =
            static function (TestRunner $t) use ($firstFigure, $inlineTypes, $captionCase, $source): void {
                $document = (new MarkdownReader())->read($source['markdown']);
                $figure = $firstFigure($document);
                $image = $figure->children[0] ?? new AstNode('missing');
                $captionInlines = $figure->attr('captionInlines', []);
                $captionSource = $figure->attr('captionSource', []);
                $blocks = (new WordPressBlockWriter())->write($document);
                $markdown = (new MarkdownWriter())->write($document);

                $t->same('figure', $figure->type);
                $t->same('image', $image->type);
                $t->same($source['url'], $image->attr('url'));
                $t->same($source['alt'] ?? $captionCase['plain'], $image->attr('alt'));
                $t->same($source['title'], $image->attr('title', ''));
                $t->same($captionCase['plain'], $figure->attr('caption'));
                $t->same(true, $figure->attr('renderCaptionInlines'));
                $t->same($captionCase['types'], $inlineTypes($captionInlines));
                $t->same('markdown-image-label', $captionSource['element'] ?? null);
                $t->same('implicit-figure', $captionSource['position'] ?? null);
                $t->same($source['id'], $figure->attr('id'));
                $t->same($source['classes'], $figure->attr('classes', []));
                $t->same($source['attributes'], $figure->attr('attributes', []));
                $t->contains('<figcaption>' . $captionCase['html'] . '</figcaption>', $blocks);
                $t->contains($source['writerContains'], $markdown);
                $t->contains('media/', $markdown);
            };
    }
}

$tests['records upstream markdown implicit figure source caption mapped-case count'] =
    static function (TestRunner $t) use ($sourceCaseCount): void {
        $t->same(63, $sourceCaseCount);
    };

return $tests;
