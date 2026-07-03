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
        'markdownLabelTemplate' => 'Review `\LaTeX{}`{=tex} %s',
        'plainTemplate' => 'Review \LaTeX{} %s',
        'htmlTemplate' => 'Review <span class="pandoc-raw-tex">\LaTeX{}</span> %s',
        'type' => 'raw_tex',
    ],
    'mark caption label' => [
        'labelTemplate' => 'Review ==caption== %s',
        'plainTemplate' => 'Review caption %s',
        'htmlTemplate' => 'Review <mark>caption</mark> %s',
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
                'expectedMarkdownLabel' => sprintf($inlineVariant['markdownLabelTemplate'] ?? $inlineVariant['labelTemplate'], $caseId),
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
        $t->contains('![' . $case['expectedMarkdownLabel'] . '](' . $source['url'], $markdown);
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
                    'expectedCaption' => 'Review figure ' . $caseId,
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
        $t->same($case['expectedCaption'], $figure->attr('caption'));
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

$imageFixtures = [
    'inline' => [
        'markdown' => '![Source image](images/source.png "Source title")',
        'tail' => '',
        'url' => 'images/source.png',
        'title' => 'Source title',
        'imageAlt' => 'Source image',
    ],
    'attributed' => [
        'markdown' => '![Review image](images/review.png){#image-source .from-image width="640" height="480" alt="Editorial alt"}',
        'tail' => '',
        'url' => 'images/review.png',
        'title' => '',
        'imageAlt' => 'Editorial alt',
    ],
    'reference' => [
        'markdown' => '![Reference image][figure-ref]',
        'tail' => "\n\n[figure-ref]: media/reference.png \"Reference title\"",
        'url' => 'media/reference.png',
        'title' => 'Reference title',
        'imageAlt' => 'Reference image',
    ],
];

$captionedFigureMarkdown = static function (array $fixture, string $position, string $marker, string $caption): string {
    $captionLine = $marker . ($caption === '' ? '' : ' ' . $caption);
    $body = $position === 'before-figure'
        ? $captionLine . "\n\n" . $fixture['markdown']
        : $fixture['markdown'] . "\n\n" . $captionLine;

    return $body . $fixture['tail'];
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

$assertFigureImage = static function (TestRunner $t, AstNode $figure, array $fixture): void {
    $t->same('figure', $figure->type);
    $t->same(1, count($figure->children));
    $image = $figure->children[0];
    $t->same('image', $image->type);
    $t->same($fixture['url'], $image->attr('url'));
    $t->same($fixture['imageAlt'], $image->attr('alt'));
    if ($fixture['title'] !== '') {
        $t->same($fixture['title'], $image->attr('title'));
    }
};

$caseNumber = 1;

foreach ($imageFixtures as $imageName => $fixture) {
    foreach (['before-figure' => 'Figure:', 'after-figure' => ':'] as $position => $marker) {
        foreach (['plain' => 'Short label', 'formatted' => 'Short **label**'] as $style => $short) {
            $number = $caseNumber++;
            $tests["maps upstream markdown reader figure short caption {$imageName} {$position} {$style}"] =
                static function (TestRunner $t) use ($captionedFigureMarkdown, $firstFigure, $inlineTypes, $assertFigureImage, $fixture, $position, $marker, $short, $style, $number): void {
                    $caption = "[{$short} {$number}] Long **figure** caption {$number}";
                    $document = (new MarkdownReader())->read($captionedFigureMarkdown($fixture, $position, $marker, $caption));
                    $figure = $firstFigure($document);

                    $assertFigureImage($t, $figure, $fixture);
                    $t->same("Long figure caption {$number}", $figure->attr('caption'));
                    $t->same("Short label {$number}", $figure->attr('shortCaption'));
                    $t->same($style === 'formatted' ? ['text', 'strong', 'text'] : ['text'], $inlineTypes($figure->attr('shortCaptionInlines')));
                    $t->same(['text', 'strong', 'text'], $inlineTypes($figure->attr('captionInlines')));
                    $t->same($position, $figure->attr('captionSource')['position'] ?? null);
                    $t->same(true, $figure->attr('renderCaptionInlines'));
                };
        }
    }
}

foreach ($imageFixtures as $imageName => $fixture) {
    foreach (['before-figure' => 'Caption:', 'after-figure' => 'Figure:'] as $position => $marker) {
        foreach (['data' => 'data-source', 'lang' => 'lang'] as $style => $attributeName) {
            $number = $caseNumber++;
            $id = "fig-{$imageName}-{$position}-{$style}";
            $tests["maps upstream markdown reader figure caption attributes {$imageName} {$position} {$style}"] =
                static function (TestRunner $t) use ($captionedFigureMarkdown, $firstFigure, $assertFigureImage, $fixture, $position, $marker, $imageName, $attributeName, $number, $id): void {
                    $caption = $attributeName === 'data-source'
                        ? "Attributed figure {$number} {#{$id} .review-figure .{$imageName} data-source=\"batch-{$number}\" title=\"Review &amp; figure\"}"
                        : "Attributed figure {$number} {#{$id} .review-figure .{$imageName} lang=\"en-US\" title=\"{$imageName} figure\"}";
                    $document = (new MarkdownReader())->read($captionedFigureMarkdown($fixture, $position, $marker, $caption));
                    $figure = $firstFigure($document);
                    $markdown = (new MarkdownWriter())->write(new AstNode('document', [], [$figure]));

                    $assertFigureImage($t, $figure, $fixture);
                    $t->same("Attributed figure {$number}", $figure->attr('caption'));
                    $t->same($id, $figure->attr('id'));
                    $t->same($attributeName === 'data-source' ? "batch-{$number}" : 'en-US', $figure->attr('attributes')[$attributeName] ?? null);
                    $t->contains('review-figure', implode(',', $figure->attr('classes')));
                    $t->contains("#{$id}", $markdown);
                    $t->contains('.review-figure', $markdown);
                };
        }
    }
}

foreach ($imageFixtures as $imageName => $fixture) {
    foreach (['before-figure', 'after-figure'] as $position) {
        foreach (['Figure:', 'Fig.:', 'Image:'] as $marker) {
            $number = $caseNumber++;
            $tests["maps upstream markdown reader figure caption source {$imageName} {$position} {$marker}"] =
                static function (TestRunner $t) use ($captionedFigureMarkdown, $firstFigure, $assertFigureImage, $fixture, $position, $marker, $number): void {
                    $document = (new MarkdownReader())->read($captionedFigureMarkdown($fixture, $position, $marker, "Source marker figure {$number}"));
                    $figure = $firstFigure($document);
                    $source = $figure->attr('captionSource');

                    $assertFigureImage($t, $figure, $fixture);
                    $t->same("Source marker figure {$number}", $figure->attr('caption'));
                    $t->same('markdown-figure-caption', $source['element'] ?? null);
                    $t->same($position, $source['position'] ?? null);
                    $t->same($marker, $source['marker'] ?? null);
                    $t->same($position === 'before-figure' ? 'top' : 'bottom', $source['captionSide'] ?? null);
                    $t->same('markdown-figure-caption-position', $source['captionSideSource'] ?? null);
                };
        }
    }
}

foreach ($imageFixtures as $imageName => $fixture) {
    foreach (['before-figure' => 'Figure:', 'after-figure' => ':'] as $position => $marker) {
        foreach (['link-continuation', 'code-continuation'] as $style) {
            $number = $caseNumber++;
            $tests["maps upstream markdown reader multiline figure caption {$imageName} {$position} {$style}"] =
                static function (TestRunner $t) use ($captionedFigureMarkdown, $firstFigure, $inlineTypes, $assertFigureImage, $fixture, $position, $marker, $style, $number): void {
                    $continuation = $style === 'link-continuation'
                        ? "  continuation with [review link](/figure-review-{$number})"
                        : "  continuation with `code {$number}`";
                    $caption = "Multiline **figure** {$number}\n{$continuation}";
                    $document = (new MarkdownReader())->read($captionedFigureMarkdown($fixture, $position, $marker, $caption));
                    $figure = $firstFigure($document);
                    $types = $inlineTypes($figure->attr('captionInlines'));

                    $assertFigureImage($t, $figure, $fixture);
                    $t->contains("Multiline figure {$number}\ncontinuation", $figure->attr('caption'));
                    $t->contains('strong', implode(',', $types));
                    $t->contains($style === 'link-continuation' ? 'link' : 'code', implode(',', $types));
                    $t->same($position === 'before-figure' ? 'top' : 'bottom', $figure->attr('captionSource')['captionSide'] ?? null);
                };
        }
    }
}

foreach ($imageFixtures as $imageName => $fixture) {
    foreach (['before-figure' => 'Caption:', 'after-figure' => ':'] as $position => $marker) {
        $number = $caseNumber++;
        $tests["maps upstream markdown reader figure caption wordpress handoff {$imageName} {$position}"] =
            static function (TestRunner $t) use ($captionedFigureMarkdown, $firstFigure, $fixture, $position, $marker, $imageName, $number): void {
                $caption = "[Short {$number}] Handoff **figure** {$number} {#handoff-{$imageName}-{$position} .handoff-figure data-source=\"figure-surge\"}";
                $document = (new MarkdownReader())->read($captionedFigureMarkdown($fixture, $position, $marker, $caption));
                $figure = $firstFigure($document);
                $blocks = (new WordPressBlockWriter())->write($document);

                $t->same("Short {$number}", $figure->attr('shortCaption'));
                $t->contains('id="handoff-' . $imageName . '-' . $position . '"', $blocks);
                $t->contains('wp-block-image', $blocks);
                $t->contains('handoff-figure', $blocks);
                $t->contains('data-source="figure-surge"', $blocks);
                $t->contains('Handoff <strong>figure</strong> ' . $number, $blocks);
            };
    }
}

$tests['records upstream markdown figure caption surge mapped-case count'] = static function (TestRunner $t) use ($cases): void {
    $t->same(54, count($cases));
};

return $tests;
