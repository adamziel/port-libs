<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\WordPressBlockWriter;

/**
 * @return array{markdown:string, url:string, title:string}
 */
$buildFencedFigureMarkdown = static function (array $case): array {
    $url = 'media/native-figure-' . $case['caseId'] . '.png';
    $title = 'Image title ' . $case['caseId'];
    $label = 'Image alt ' . $case['caseId'];
    $caption = $case['caption'];
    $definition = null;
    $imageMarkdown = match ($case['targetMode']) {
        'inline-target' => '![' . $label . '](' . $url . ' "' . $title . '")',
        'full-reference' => '![' . $label . '][native-figure-' . $case['caseId'] . ']',
        'shortcut-reference' => '![' . $label . ']',
    };
    if ($case['targetMode'] === 'full-reference') {
        $definition = '[native-figure-' . $case['caseId'] . ']: ' . $url . ' "' . $title . '"';
    } elseif ($case['targetMode'] === 'shortcut-reference') {
        $definition = '[' . $label . ']: ' . $url . ' "' . $title . '"';
    }

    $body = $case['captionPosition'] === 'before-image'
        ? [$caption, '', $imageMarkdown]
        : [$imageMarkdown, '', $caption];
    $markdown = implode("\n", array_merge(
        ['::: {#native-figure-' . $case['caseId'] . ' .figure .surge-figure data-source="lqbop-' . $case['caseId'] . '" data-review="caption"}'],
        $body,
        [':::']
    ));
    if ($definition !== null) {
        $markdown .= "\n\n" . $definition;
    }

    return [
        'markdown' => $markdown,
        'url' => $url,
        'title' => $title,
    ];
};

$captionVariants = [
    'emphasis caption block' => [
        'captionTemplate' => 'Figure *caption* %s',
        'plainTemplate' => 'Figure caption %s',
        'htmlTemplate' => 'Figure <em>caption</em> %s',
        'type' => 'emph',
    ],
    'strong caption block' => [
        'captionTemplate' => 'Figure **caption** %s',
        'plainTemplate' => 'Figure caption %s',
        'htmlTemplate' => 'Figure <strong>caption</strong> %s',
        'type' => 'strong',
    ],
    'code caption block' => [
        'captionTemplate' => 'Figure `caption` %s',
        'plainTemplate' => 'Figure caption %s',
        'htmlTemplate' => 'Figure <code>caption</code> %s',
        'type' => 'code',
    ],
    'strikeout caption block' => [
        'captionTemplate' => 'Figure ~~caption~~ %s',
        'plainTemplate' => 'Figure caption %s',
        'htmlTemplate' => 'Figure <del>caption</del> %s',
        'type' => 'strikeout',
    ],
    'superscript caption block' => [
        'captionTemplate' => 'Figure E=mc^2^ %s',
        'plainTemplate' => 'Figure E=mc2 %s',
        'htmlTemplate' => 'Figure E=mc<sup>2</sup> %s',
        'type' => 'superscript',
    ],
    'subscript caption block' => [
        'captionTemplate' => 'Figure H~2~O %s',
        'plainTemplate' => 'Figure H2O %s',
        'htmlTemplate' => 'Figure H<sub>2</sub>O %s',
        'type' => 'subscript',
    ],
    'raw tex caption block' => [
        'captionTemplate' => 'Figure \LaTeX{} %s',
        'plainTemplate' => 'Figure \LaTeX{} %s',
        'htmlTemplate' => 'Figure <span class="pandoc-raw-tex">\LaTeX{}</span> %s',
        'type' => 'raw_tex',
    ],
    'mark caption block' => [
        'captionTemplate' => 'Figure ==caption== %s',
        'plainTemplate' => 'Figure caption %s',
        'htmlTemplate' => 'Figure <span class="mark">caption</span> %s',
        'type' => 'span',
    ],
    'math caption block' => [
        'captionTemplate' => 'Figure $x+y$ %s',
        'plainTemplate' => 'Figure x+y %s',
        'htmlTemplate' => 'Figure <span class="math inline">\(x+y\)</span> %s',
        'type' => 'math',
    ],
];

$targetModes = ['inline-target', 'full-reference', 'shortcut-reference'];
$captionPositions = ['after-image', 'before-image'];
$cases = [];
$caseNumber = 1;
foreach ($captionVariants as $variantName => $variant) {
    foreach ($targetModes as $targetMode) {
        foreach ($captionPositions as $captionPosition) {
            $caseId = str_pad((string) $caseNumber, 3, '0', STR_PAD_LEFT);
            $cases[] = [
                'caseId' => $caseId,
                'name' => sprintf(
                    'maps upstream markdown fenced div figure caption surge %s %s %s %s',
                    $caseId,
                    $variantName,
                    str_replace('-', ' ', $targetMode),
                    str_replace('-', ' ', $captionPosition)
                ),
                'caption' => sprintf($variant['captionTemplate'], $caseId),
                'plainCaption' => sprintf($variant['plainTemplate'], $caseId),
                'htmlCaption' => sprintf($variant['htmlTemplate'], $caseId),
                'expectedType' => $variant['type'],
                'targetMode' => $targetMode,
                'captionPosition' => $captionPosition,
            ];
            $caseNumber++;
        }
    }
}

$tests = [];

foreach ($cases as $case) {
    $tests[$case['name']] = static function (TestRunner $t) use ($case, $buildFencedFigureMarkdown): void {
        $source = $buildFencedFigureMarkdown($case);
        $document = (new MarkdownReader())->read($source['markdown']);
        $figure = $document->children[0] ?? new AstNode('missing');
        $image = $figure->children[0] ?? new AstNode('missing');
        $captionInlines = $figure->attr('captionInlines', []);
        $captionSource = $figure->attr('captionSource', []);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->same(1, count($document->children), $case['caseId'] . ' should parse to one native figure block');
        $t->same('figure', $figure->type, $case['caseId'] . ' figure type');
        $t->same('native-figure-' . $case['caseId'], $figure->attr('id'), $case['caseId'] . ' figure id');
        $t->same(['figure', 'surge-figure'], $figure->attr('classes'), $case['caseId'] . ' figure classes');
        $t->same(
            ['data-source' => 'lqbop-' . $case['caseId'], 'data-review' => 'caption'],
            $figure->attr('attributes'),
            $case['caseId'] . ' figure data attributes'
        );
        $t->same($case['plainCaption'], $figure->attr('caption'), $case['caseId'] . ' plain figure caption');
        $t->same(true, $figure->attr('renderCaptionInlines'), $case['caseId'] . ' render caption inlines flag');
        $t->true(is_array($captionInlines) && $captionInlines !== [], $case['caseId'] . ' caption inlines are present');
        $t->true(
            in_array($case['expectedType'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines), true),
            $case['caseId'] . ' caption inline type ' . $case['expectedType'] . ' is preserved'
        );
        $t->true(is_array($captionSource), $case['caseId'] . ' caption source is structured');
        $t->same('markdown-fenced-div-figure', $captionSource['element'] ?? null, $case['caseId'] . ' caption source element');
        $t->same('figure', $captionSource['class'] ?? null, $case['caseId'] . ' caption source class');
        $t->same($case['captionPosition'], $captionSource['position'] ?? null, $case['caseId'] . ' caption source position');
        $t->same('image', $image->type, $case['caseId'] . ' child image type');
        $t->same($source['url'], $image->attr('url'), $case['caseId'] . ' child image URL');
        $t->same($source['title'], $image->attr('title'), $case['caseId'] . ' child image title');
        $t->same('Image alt ' . $case['caseId'], $image->attr('alt'), $case['caseId'] . ' child image alt');
        $t->true($case['plainCaption'] !== $image->attr('alt'), $case['caseId'] . ' image alt is independent from caption');
        $t->contains('<figure class="wp-block-image figure surge-figure" id="native-figure-' . $case['caseId'] . '"', $blocks);
        $t->contains('<img src="' . $source['url'] . '" alt="Image alt ' . $case['caseId'] . '" title="' . $source['title'] . '"/>', $blocks);
        $t->contains('<figcaption>' . $case['htmlCaption'] . '</figcaption>', $blocks);
    };
}

$tests['records upstream markdown fenced div figure caption surge mapped-case count'] = static function (TestRunner $t) use ($cases): void {
    $t->same(54, count($cases));
};

return $tests;
