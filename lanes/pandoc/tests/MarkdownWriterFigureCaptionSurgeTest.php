<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$document = static fn (array $children): AstNode => new AstNode('document', [], $children);

$captionVariants = [
    'emphasis caption' => static fn (string $caseId): array => [
        'plain' => 'Review caption ' . $caseId,
        'markdown' => 'Review *caption* ' . $caseId,
        'inlines' => [$text('Review '), new AstNode('emph', [], [$text('caption')]), $text(' ' . $caseId)],
    ],
    'strong caption' => static fn (string $caseId): array => [
        'plain' => 'Review caption ' . $caseId,
        'markdown' => 'Review **caption** ' . $caseId,
        'inlines' => [$text('Review '), new AstNode('strong', [], [$text('caption')]), $text(' ' . $caseId)],
    ],
    'code caption' => static fn (string $caseId): array => [
        'plain' => 'Review caption ' . $caseId,
        'markdown' => 'Review `caption` ' . $caseId,
        'inlines' => [$text('Review '), new AstNode('code', ['text' => 'caption']), $text(' ' . $caseId)],
    ],
];

$attributeVariants = [
    'identity data attributes' => static fn (string $caseId): array => [
        'figureAttrs' => [
            'id' => 'wrapped-figure-' . $caseId,
            'classes' => ['wrapped', 'case-' . $caseId],
            'attributes' => ['data-source' => 'wrapped-' . $caseId],
        ],
        'tokens' => [
            '#wrapped-figure-' . $caseId,
            '.wrapped',
            '.case-' . $caseId,
            'data-source="wrapped-' . $caseId . '"',
        ],
        'roundTripAttrs' => ['data-source' => 'wrapped-' . $caseId],
    ],
    'placement aria attributes' => static fn (string $caseId): array => [
        'figureAttrs' => [
            'attributes' => ['latex-placement' => 'htbp', 'aria-label' => 'Figure ' . $caseId],
        ],
        'tokens' => [
            'latex-placement="htbp"',
            'aria-label="Figure ' . $caseId . '"',
        ],
        'roundTripAttrs' => ['latex-placement' => 'htbp', 'aria-label' => 'Figure ' . $caseId],
    ],
    'class language title attributes' => static fn (string $caseId): array => [
        'figureAttrs' => [
            'classes' => ['media'],
            'attributes' => ['lang' => 'en', 'title' => 'Figure attr ' . $caseId, 'data-review' => 'wrapped'],
        ],
        'tokens' => [
            '.media',
            'lang="en"',
            'title="Figure attr ' . $caseId . '"',
            'data-review="wrapped"',
        ],
        'roundTripAttrs' => ['lang' => 'en', 'title' => 'Figure attr ' . $caseId, 'data-review' => 'wrapped'],
    ],
];

$imageVariants = [
    'distinct alt text' => static fn (string $caseId, string $plainCaption): array => [
        'attrs' => ['alt' => 'Alt ' . $caseId],
        'altToken' => 'alt="Alt ' . $caseId . '"',
        'roundTripAlt' => 'Alt ' . $caseId,
    ],
    'empty image alt' => static fn (string $caseId, string $plainCaption): array => [
        'attrs' => [],
        'altToken' => null,
        'roundTripAlt' => $plainCaption,
    ],
    'matching image alt' => static fn (string $caseId, string $plainCaption): array => [
        'attrs' => ['alt' => $plainCaption],
        'altToken' => null,
        'roundTripAlt' => $plainCaption,
    ],
];

$blockVariants = [
    'plain wrapped image' => 'plain',
    'paragraph wrapped image' => 'paragraph',
];

$tests = [];
$cases = [];
$caseNumber = 1;
foreach ($blockVariants as $blockLabel => $blockType) {
    foreach ($captionVariants as $captionLabel => $captionBuilder) {
        foreach ($attributeVariants as $attributeLabel => $attributeBuilder) {
            foreach ($imageVariants as $imageLabel => $imageBuilder) {
                $caseId = str_pad((string) $caseNumber, 3, '0', STR_PAD_LEFT);
                $caption = $captionBuilder($caseId);
                $attribute = $attributeBuilder($caseId);
                $image = $imageBuilder($caseId, $caption['plain']);
                $cases[] = [
                    'id' => $caseId,
                    'name' => sprintf(
                        'maps upstream markdown writer wrapped figure caption surge %s %s %s %s %s',
                        $caseId,
                        $blockLabel,
                        $captionLabel,
                        $attributeLabel,
                        $imageLabel
                    ),
                    'blockType' => $blockType,
                    'caption' => $caption,
                    'attribute' => $attribute,
                    'image' => $image,
                    'url' => 'media/wrapped-figure-' . $caseId . '.png',
                    'title' => 'Wrapped figure ' . $caseId,
                ];
                $caseNumber++;
            }
        }
    }
}

foreach ($cases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $document): void {
        $imageAttrs = array_replace([
            'url' => $case['url'],
            'title' => $case['title'],
        ], $case['image']['attrs']);
        $figureAttrs = array_replace($case['attribute']['figureAttrs'], [
            'caption' => $case['caption']['plain'],
            'captionInlines' => $case['caption']['inlines'],
            'renderCaptionInlines' => true,
        ]);
        $image = new AstNode('image', $imageAttrs);
        $figure = new AstNode('figure', $figureAttrs, [
            new AstNode($case['blockType'], [], [$image]),
        ]);
        $markdown = (new MarkdownWriter())->write($document([$figure]));
        $roundTrip = (new MarkdownReader())->read($markdown);
        $roundTripFigure = $roundTrip->children[0] ?? new AstNode('missing');
        $roundTripImage = $roundTripFigure->children[0] ?? new AstNode('missing');

        $t->contains('![' . $case['caption']['markdown'] . '](' . $case['url'] . ' "' . $case['title'] . '")', $markdown);
        foreach ($case['attribute']['tokens'] as $token) {
            $t->contains($token, $markdown);
        }
        if ($case['image']['altToken'] === null) {
            $t->true(!str_contains($markdown, ' alt='), $case['id'] . ' matching or empty alt should not be duplicated');
        } else {
            $t->contains($case['image']['altToken'], $markdown);
        }

        $t->same('figure', $roundTripFigure->type, $case['id'] . ' roundtrips as a figure');
        $t->same($case['caption']['plain'], $roundTripFigure->attr('caption'), $case['id'] . ' roundtrip figure caption');
        $t->same($case['attribute']['roundTripAttrs'], $roundTripFigure->attr('attributes', []), $case['id'] . ' roundtrip figure attributes');
        $t->same('image', $roundTripImage->type, $case['id'] . ' roundtrip child image');
        $t->same($case['image']['roundTripAlt'], $roundTripImage->attr('alt'), $case['id'] . ' roundtrip image alt');
    };
}

$tests['records upstream markdown writer wrapped figure caption surge mapped-case count'] = static function (TestRunner $t) use ($cases): void {
    $t->same(54, count($cases));
};

return $tests;
