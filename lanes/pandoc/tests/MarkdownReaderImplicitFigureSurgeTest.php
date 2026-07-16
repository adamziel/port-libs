<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return array{markdown:string, url:string, title:string, expectedMarkdownFragment:string}
 */
$buildImplicitFigureMarkdown = static function (array $case): array {
    $url = 'media/implicit-figure-' . $case['caseId'] . '.png';
    $title = 'Implicit figure title ' . $case['caseId'];
    $label = $case['label'];
    $expectedMarkdownLabel = $case['expectedMarkdownLabel'] ?? $label;
    $attributes = $case['attributeSource'];

    return match ($case['targetMode']) {
        'inline-target' => [
            'markdown' => '![' . $label . '](' . $url . ' "' . $title . '")' . $attributes,
            'url' => $url,
            'title' => $title,
            'expectedMarkdownFragment' => '![' . $expectedMarkdownLabel . '](' . $url,
        ],
        'full-reference' => [
            'markdown' => implode("\n", [
                '![' . $label . '][implicit-fig-' . $case['caseId'] . ']' . $attributes,
                '',
                '[implicit-fig-' . $case['caseId'] . ']: ' . $url . ' "' . $title . '"',
            ]),
            'url' => $url,
            'title' => $title,
            'expectedMarkdownFragment' => '![' . $expectedMarkdownLabel . '](' . $url,
        ],
        'shortcut-reference' => [
            'markdown' => implode("\n", [
                '![' . $label . ']' . $attributes,
                '',
                '[' . $label . ']: ' . $url . ' "' . $title . '"',
            ]),
            'url' => $url,
            'title' => $title,
            'expectedMarkdownFragment' => '![' . $expectedMarkdownLabel . '](' . $url,
        ],
    };
};

$inlineVariants = [
    'emphasis image label' => [
        'labelTemplate' => 'Implicit *figure* %s',
        'plainTemplate' => 'Implicit figure %s',
        'htmlTemplate' => 'Implicit <em>figure</em> %s',
        'type' => 'emph',
    ],
    'strong image label' => [
        'labelTemplate' => 'Implicit **figure** %s',
        'plainTemplate' => 'Implicit figure %s',
        'htmlTemplate' => 'Implicit <strong>figure</strong> %s',
        'type' => 'strong',
    ],
    'code image label' => [
        'labelTemplate' => 'Implicit `figure` %s',
        'plainTemplate' => 'Implicit figure %s',
        'htmlTemplate' => 'Implicit <code>figure</code> %s',
        'type' => 'code',
    ],
    'strikeout image label' => [
        'labelTemplate' => 'Implicit ~~figure~~ %s',
        'plainTemplate' => 'Implicit figure %s',
        'htmlTemplate' => 'Implicit <del>figure</del> %s',
        'type' => 'strikeout',
    ],
    'mark image label' => [
        'labelTemplate' => 'Implicit ==figure== %s',
        'plainTemplate' => 'Implicit figure %s',
        'htmlTemplate' => 'Implicit <mark>figure</mark> %s',
        'type' => 'span',
    ],
    'math image label' => [
        'labelTemplate' => 'Implicit $x+y$ %s',
        'plainTemplate' => 'Implicit x+y %s',
        'htmlTemplate' => 'Implicit <span class="math inline">\(x+y\)</span> %s',
        'type' => 'math',
    ],
    'subscript image label' => [
        'labelTemplate' => 'Implicit H~2~O %s',
        'plainTemplate' => 'Implicit H2O %s',
        'htmlTemplate' => 'Implicit H<sub>2</sub>O %s',
        'type' => 'subscript',
    ],
    'superscript image label' => [
        'labelTemplate' => 'Implicit E=mc^2^ %s',
        'plainTemplate' => 'Implicit E=mc2 %s',
        'htmlTemplate' => 'Implicit E=mc<sup>2</sup> %s',
        'type' => 'superscript',
    ],
    'underline image label' => [
        'labelTemplate' => 'Implicit [figure]{.underline data-source="caption-%s"}',
        'plainTemplate' => 'Implicit figure',
        'htmlTemplate' => 'Implicit <u data-source="caption-%s">figure</u>',
        'type' => 'underline',
    ],
    'raw tex image label' => [
        'labelTemplate' => 'Implicit \LaTeX{} %s',
        'markdownLabelTemplate' => 'Implicit `\LaTeX{}`{=tex} %s',
        'plainTemplate' => 'Implicit \LaTeX{} %s',
        'htmlTemplate' => 'Implicit <span class="pandoc-raw-tex">\LaTeX{}</span> %s',
        'type' => 'raw_tex',
    ],
];

$attributeVariants = [
    'identity attributes' => static function (string $caseId): array {
        return [
            'attributeSource' => '{#implicit-figure-' . $caseId . ' .implicit-figure .case-' . $caseId . ' data-source="implicit-' . $caseId . '"}',
            'expectedId' => 'implicit-figure-' . $caseId,
            'expectedClasses' => ['implicit-figure', 'case-' . $caseId],
            'expectedAttributes' => ['data-source' => 'implicit-' . $caseId],
            'expectedAlt' => null,
            'expectedFigureHtmlAttribute' => 'id="implicit-figure-' . $caseId . '"',
        ];
    },
    'placement alt attributes' => static function (string $caseId): array {
        return [
            'attributeSource' => '{latex-placement="tbp" alt="Alt implicit ' . $caseId . '" data-source="implicit-' . $caseId . '"}',
            'expectedId' => null,
            'expectedClasses' => [],
            'expectedAttributes' => ['latex-placement' => 'tbp', 'data-source' => 'implicit-' . $caseId],
            'expectedAlt' => 'Alt implicit ' . $caseId,
            'expectedFigureHtmlAttribute' => 'data-pandoc-latex-placement="tbp"',
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
            $cases[] = [
                'caseId' => $caseId,
                'name' => sprintf(
                    'maps upstream markdown implicit figure surge %s %s %s',
                    $caseId,
                    $inlineName,
                    str_replace('-', ' ', $targetMode . ' ' . $attributeName)
                ),
                'label' => $label,
                'expectedMarkdownLabel' => sprintf($inlineVariant['markdownLabelTemplate'] ?? $inlineVariant['labelTemplate'], $caseId),
                'plainCaption' => sprintf($inlineVariant['plainTemplate'], $caseId),
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
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $buildImplicitFigureMarkdown): void {
        $source = $buildImplicitFigureMarkdown($case);
        $document = (new MarkdownReader())->read($source['markdown']);
        $figure = $document->children[0] ?? new AstNode('missing');
        $image = $figure->children[0] ?? new AstNode('missing');
        $captionInlines = $figure->attr('captionInlines', []);
        $captionSource = $figure->attr('captionSource', []);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children), $case['caseId'] . ' should parse to one implicit figure block');
        $t->same('figure', $figure->type, $case['caseId'] . ' implicit figure type');
        $t->same('image', $image->type, $case['caseId'] . ' child image type');
        $t->same($case['plainCaption'], $figure->attr('caption'), $case['caseId'] . ' plain figure caption');
        $t->same(true, $figure->attr('renderCaptionInlines'), $case['caseId'] . ' render caption inlines flag');
        $t->true(is_array($captionInlines) && $captionInlines !== [], $case['caseId'] . ' implicit figure caption inlines are present');
        $t->true(
            in_array($case['expectedType'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines), true),
            $case['caseId'] . ' caption inline type ' . $case['expectedType'] . ' is preserved'
        );
        $t->same('markdown-implicit-figure', $captionSource['element'] ?? null, $case['caseId'] . ' implicit caption source element');
        $t->same('image-label', $captionSource['position'] ?? null, $case['caseId'] . ' implicit caption source position');
        $t->same('standalone-image', $captionSource['marker'] ?? null, $case['caseId'] . ' implicit caption source marker');
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
    };
}

$captionMarkerCases = [];
$captionMarkerNumber = 1;
foreach (['Picture:', 'Photo:', 'Illustration:', 'Plate:', 'Diagram:', 'Image:'] as $marker) {
    foreach (['leading', 'trailing'] as $position) {
        $caseId = str_pad((string) $captionMarkerNumber, 3, '0', STR_PAD_LEFT);
        $captionMarkerCases[] = [
            'caseId' => $caseId,
            'marker' => $marker,
            'position' => $position,
            'caption' => 'Marker caption ' . $caseId,
            'captionMarkdown' => 'Marker *caption* ' . $caseId,
            'image' => '![' . $position . ' source ' . $caseId . '](media/marker-' . $caseId . '.png "Marker title ' . $caseId . '")',
            'name' => sprintf(
                'maps upstream markdown expanded figure caption marker %s %s %s',
                $caseId,
                strtolower(rtrim($marker, ':')),
                $position
            ),
        ];
        $captionMarkerNumber++;
    }
}

foreach ($captionMarkerCases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case): void {
        $caption = $case['marker'] . ' ' . $case['captionMarkdown'];
        $markdown = $case['position'] === 'leading'
            ? $caption . "\n\n" . $case['image']
            : $case['image'] . "\n\n" . $caption;
        $document = (new MarkdownReader())->read($markdown);
        $figure = $document->children[0] ?? new AstNode('missing');
        $captionSource = $figure->attr('captionSource', []);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children));
        $t->same('figure', $figure->type);
        $t->same($case['caption'], $figure->attr('caption'));
        $t->same('markdown-figure-caption', $captionSource['element'] ?? null);
        $t->same($case['marker'], $captionSource['marker'] ?? null);
        $t->contains('<figcaption>Marker <em>caption</em> ' . $case['caseId'] . '</figcaption>', $blocks);
    };
}

$tests['records upstream markdown implicit figure surge mapped-case count'] = static function (TestRunner $t) use ($cases): void {
    $t->same(60, count($cases));
};

return $tests;
