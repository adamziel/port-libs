<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

$template = static fn (string $value, int $number): string => str_replace('{n}', (string) $number, $value);

$firstFigure = static function (AstNode $document): AstNode {
    foreach ($document->children as $node) {
        if ($node->type === 'figure') {
            return $node;
        }
    }

    return new AstNode('missing');
};

$captionVariants = [
    'strong' => [
        'source' => '**Bold {n}** caption {n}',
        'html' => '<strong>Bold {n}</strong> caption {n}',
        'types' => ['strong', 'text'],
    ],
    'emph' => [
        'source' => '*Emph {n}* caption {n}',
        'html' => '<em>Emph {n}</em> caption {n}',
        'types' => ['emph', 'text'],
    ],
    'code' => [
        'source' => '`code {n}` caption {n}',
        'html' => '<code>code {n}</code> caption {n}',
        'types' => ['code', 'text'],
    ],
    'strikeout' => [
        'source' => '~~Gone {n}~~ caption {n}',
        'html' => '<del>Gone {n}</del> caption {n}',
        'types' => ['strikeout', 'text'],
    ],
    'superscript' => [
        'source' => 'x^{n}^ caption {n}',
        'html' => 'x<sup>{n}</sup> caption {n}',
        'types' => ['text', 'superscript', 'text'],
    ],
    'subscript' => [
        'source' => 'H~{n}~O caption {n}',
        'html' => 'H<sub>{n}</sub>O caption {n}',
        'types' => ['text', 'subscript', 'text'],
    ],
    'small-caps' => [
        'source' => '[Small {n}]{.smallcaps} caption {n}',
        'html' => '<span style="font-variant:small-caps">Small {n}</span> caption {n}',
        'types' => ['small_caps', 'text'],
    ],
    'underline' => [
        'source' => '[Insert {n}]{.underline} caption {n}',
        'html' => '<u>Insert {n}</u> caption {n}',
        'types' => ['underline', 'text'],
    ],
    'mark' => [
        'source' => '==Marked {n}== caption {n}',
        'html' => '<span class="mark">Marked {n}</span> caption {n}',
        'types' => ['span', 'text'],
    ],
    'span-attributes' => [
        'source' => '[Span {n}]{.review data-kind="caption"} caption {n}',
        'html' => '<span class="review" data-kind="caption">Span {n}</span> caption {n}',
        'types' => ['span', 'text'],
    ],
    'math' => [
        'source' => 'Math $x_{n}$ caption {n}',
        'html' => 'Math <span class="math inline">\(x_{n}\)</span> caption {n}',
        'types' => ['text', 'math', 'text'],
    ],
    'linebreak' => [
        'source' => "Line one  \nLine two caption {n}",
        'html' => 'Line one<br/>Line two caption {n}',
        'types' => ['text', 'linebreak', 'text'],
    ],
];

$contexts = [
    'direct' => static fn (string $label, int $number): string =>
        '![%s](media/figure-%d.png){alt="Plain alt %d"}',
    'titled' => static fn (string $label, int $number): string =>
        '![%s](media/figure-%d.png "Source title %d"){alt="Plain alt %d"}',
    'attributed' => static fn (string $label, int $number): string =>
        '![%s](media/figure-%d.png){#fig-%d .review data-source="surge" alt="Plain alt %d"}',
    'reference' => static fn (string $label, int $number): string =>
        '![%s][fig-%d]{alt="Plain alt %d"}' . "\n\n" . '[fig-%d]: media/figure-%d.png "Source title %d"',
    'surrounded' => static fn (string $label, int $number): string =>
        'Before figure ' . $number . "\n\n" . '![%s](media/figure-%d.png){alt="Plain alt %d"}' . "\n\n" . 'After figure ' . $number,
];

$tests = [];
$caseNumber = 1;

foreach ($contexts as $contextName => $sourcePattern) {
    foreach ($captionVariants as $variantName => $variant) {
        $number = $caseNumber++;
        $tests["maps upstream markdown figure caption inlines {$contextName} {$variantName}"] =
            static function (TestRunner $t) use ($template, $firstFigure, $sourcePattern, $contextName, $variant, $number): void {
                $label = $template($variant['source'], $number);
                $expectedHtml = $template($variant['html'], $number);
                $args = match ($contextName) {
                    'direct', 'surrounded' => [$label, $number, $number],
                    'titled' => [$label, $number, $number, $number],
                    'attributed' => [$label, $number, $number, $number],
                    'reference' => [$label, $number, $number, $number, $number, $number],
                };
                $markdown = sprintf($sourcePattern($label, $number), ...$args);
                $document = (new MarkdownReader())->read($markdown);
                $figure = $firstFigure($document);
                $image = $figure->children[0] ?? null;
                $captionInlines = $figure->attr('captionInlines', []);
                $blocks = (new WordPressBlockWriter())->write($document);
                $roundTrip = (new MarkdownWriter())->write(new AstNode('document', [], [$figure]));

                $t->same('figure', $figure->type);
                $t->true($image instanceof AstNode && $image->type === 'image', 'Promoted Markdown figure should contain the source image');
                $t->same('Plain alt ' . $number, $image->attr('alt'));
                $t->same(true, $figure->attr('renderCaptionInlines'));
                $t->same($variant['types'], array_map(static fn (AstNode $node): string => $node->type, $captionInlines));
                $t->contains('<figcaption>' . $expectedHtml . '</figcaption>', $blocks);
                $t->contains('alt="Plain alt ' . $number . '"', $blocks);
                $t->contains('media/figure-' . $number . '.png', $roundTrip);
                $t->contains('alt="Plain alt ' . $number . '"', $roundTrip);

                if ($contextName === 'attributed') {
                    $t->same('fig-' . $number, $figure->attr('id'));
                    $t->same(['review'], $figure->attr('classes'));
                    $t->same('surge', $figure->attr('attributes')['data-source'] ?? null);
                    $t->contains('<figure class="wp-block-image review" id="fig-' . $number . '" data-source="surge">', $blocks);
                }

                if ($contextName === 'titled' || $contextName === 'reference') {
                    $t->same('Source title ' . $number, $image->attr('title'));
                }
            };
    }
}

return $tests;
