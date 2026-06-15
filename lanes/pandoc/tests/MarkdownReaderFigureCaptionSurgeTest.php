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

$makeAdjacentFigureCaptionImage = static function (array $case): string {
    return match ($case['syntax']) {
        'inline' => '![' . $case['sourceLabel'] . '](' . $case['url'] . ' "' . $case['title'] . '"){alt="' . $case['alt'] . '"}',
        'reference' => '![' . $case['sourceLabel'] . '][fig-ref-' . $case['caseId'] . ']',
        'shortcut' => '![' . $case['sourceLabel'] . '][]',
    };
};

$makeAdjacentFigureCaptionReference = static function (array $case): string {
    return match ($case['syntax']) {
        'reference' => "\n\n" . '[fig-ref-' . $case['caseId'] . ']: ' . $case['url'] . ' "' . $case['title'] . '"',
        'shortcut' => "\n\n" . '[' . $case['sourceLabel'] . ']: ' . $case['url'] . ' "' . $case['title'] . '"',
        default => '',
    };
};

$makeAdjacentFigureCaptionMarkdown = static function (array $case) use (
    $makeAdjacentFigureCaptionImage,
    $makeAdjacentFigureCaptionReference
): string {
    $caption = $case['marker'] . ' [' . $case['shortCaption'] . '] ' . $case['caption']
        . ' {#' . $case['id'] . ' .figure-surge .' . $case['caseClass']
        . ' ' . $case['attributeSource'] . '}';
    $image = $makeAdjacentFigureCaptionImage($case);
    $reference = $makeAdjacentFigureCaptionReference($case);

    return $case['position'] === 'leading'
        ? $caption . "\n\n" . $image . $reference
        : $image . "\n\n" . $caption . $reference;
};

$adjacentFigureCaptionCases = [];
$adjacentFigureCaptionCaseNumber = 1;
foreach (['inline', 'reference', 'shortcut'] as $syntax) {
    foreach (['trailing', 'leading'] as $position) {
        foreach (['Figure:', 'Fig:', 'Caption:'] as $marker) {
            for ($variant = 1; $variant <= 3; $variant++) {
                $caseId = str_pad((string) $adjacentFigureCaptionCaseNumber, 3, '0', STR_PAD_LEFT);
                $attributeSet = match ($variant) {
                    1 => [
                        'source' => 'data-source="upstream-figure-' . $caseId . '" lang="en"',
                        'attributes' => ['data-source' => 'upstream-figure-' . $caseId, 'lang' => 'en'],
                        'wordpress' => 'data-source="upstream-figure-' . $caseId . '" lang="en"',
                    ],
                    2 => [
                        'source' => 'role="figure" title="Review figure ' . $caseId . '"',
                        'attributes' => ['role' => 'figure', 'title' => 'Review figure ' . $caseId],
                        'wordpress' => 'role="figure" title="Review figure ' . $caseId . '"',
                    ],
                    default => [
                        'source' => 'aria-label="Review figure ' . $caseId . '" dir="ltr"',
                        'attributes' => ['aria-label' => 'Review figure ' . $caseId, 'dir' => 'ltr'],
                        'wordpress' => 'aria-label="Review figure ' . $caseId . '" dir="ltr"',
                    ],
                };

                $syntaxLabel = ucfirst($syntax);
                $adjacentFigureCaptionCases[] = [
                    'caseId' => $caseId,
                    'id' => 'md-figure-caption-surge-' . $caseId,
                    'caseClass' => 'case-' . $caseId,
                    'attributeSource' => $attributeSet['source'],
                    'attributes' => $attributeSet['attributes'],
                    'wordpressAttributeFragment' => $attributeSet['wordpress'],
                    'syntax' => $syntax,
                    'position' => $position,
                    'marker' => $marker,
                    'caption' => 'Review *figure* ' . $caseId,
                    'shortCaption' => 'Queue ' . $caseId,
                    'sourceLabel' => $syntaxLabel . ' source ' . $caseId,
                    'alt' => $syntax === 'inline' ? $syntaxLabel . ' alt ' . $caseId : $syntaxLabel . ' source ' . $caseId,
                    'url' => 'media/' . $syntax . '-figure-' . $caseId . '.png',
                    'title' => $syntaxLabel . ' title ' . $caseId,
                    'name' => sprintf(
                        'maps upstream markdown adjacent figure caption surge case %s %s %s %s',
                        $caseId,
                        $syntax,
                        $position,
                        strtolower(rtrim($marker, ':'))
                    ),
                ];
                $adjacentFigureCaptionCaseNumber++;
            }
        }
    }
}

foreach ($adjacentFigureCaptionCases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $makeAdjacentFigureCaptionMarkdown): void {
        $document = (new MarkdownReader())->read($makeAdjacentFigureCaptionMarkdown($case));
        $figure = $document->children[0] ?? new AstNode('missing');
        $image = $figure->children[0] ?? new AstNode('missing');
        $captionInlines = $figure->attr('captionInlines', []);
        $shortCaptionInlines = $figure->attr('shortCaptionInlines', []);
        $attributes = $figure->attr('attributes', []);
        $htmlAttributes = $figure->attr('htmlAttributes', []);
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children));
        $t->same('figure', $figure->type);
        $t->same('image', $image->type);
        $t->same($case['caption'], $figure->attr('caption'));
        $t->same($case['shortCaption'], $figure->attr('shortCaption'));
        $t->same(true, $figure->attr('renderCaptionInlines'));
        $t->same(true, $figure->attr('renderShortCaptionAttribute'));
        $t->same($case['id'], $figure->attr('id'));
        $t->same(['figure-surge', $case['caseClass']], $figure->attr('classes'));
        $t->same($case['attributes'], $attributes);
        $t->same($case['id'], $htmlAttributes['id'] ?? null);
        $t->same('figure-surge ' . $case['caseClass'], $htmlAttributes['class'] ?? null);
        $t->same($case['url'], $image->attr('url'));
        $t->same($case['title'], $image->attr('title'));
        $t->same($case['alt'], $image->attr('alt'));
        $t->same($case['sourceLabel'], $image->attr('caption'));
        $t->same(['text', 'emph', 'text'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines));
        $t->same('figure', $captionInlines[1]->children[0]->attr('text'));
        $t->same(['text'], array_map(static fn (AstNode $node): string => $node->type, $shortCaptionInlines));
        $t->same($case['shortCaption'], $shortCaptionInlines[0]->attr('text'));

        $t->contains('![Review *figure* ' . $case['caseId'] . '](' . $case['url'] . ' "' . $case['title'] . '")', $markdown);
        $t->contains('{#' . $case['id'] . ' .figure-surge .' . $case['caseClass'], $markdown);
        $t->contains($case['attributeSource'], $markdown);
        if ($case['alt'] !== 'Review figure ' . $case['caseId']) {
            $t->contains('alt="' . $case['alt'] . '"', $markdown);
        }

        $t->contains('<!-- wp:image -->', $blocks);
        $t->contains(
            '<figure class="wp-block-image figure-surge ' . $case['caseClass'] . '" id="' . $case['id'] . '" '
                . $case['wordpressAttributeFragment']
                . ' data-pandoc-short-caption="' . $case['shortCaption'] . '">',
            $blocks
        );
        $t->contains(
            '<img src="' . $case['url'] . '" alt="' . $case['alt'] . '" title="' . $case['title'] . '"/>',
            $blocks
        );
        $t->contains('<figcaption>Review <em>figure</em> ' . $case['caseId'] . '</figcaption>', $blocks);
    };
}

$tests['records upstream markdown figure caption surge mapped-case count'] = static function (TestRunner $t) use ($cases): void {
    $t->same(54, count($cases));
};

return $tests;
