<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\PptxReader;
use PortLibs\Pandoc\PptxWriter;
use PortLibs\Pandoc\ZipPackage;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$heading = static fn (string $value): AstNode => new AstNode('heading', ['level' => 2], [$text($value)]);
$listItem = static fn (string $value): AstNode => new AstNode('list_item', [], [$plain([$text($value)])]);
$cell = static fn (string $value, array $attrs = []): AstNode => new AstNode('table_cell', ['text' => $value] + $attrs, [$plain([$text($value)])]);
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

$document = static function () use ($text, $paragraph, $heading, $listItem, $cell, $row): AstNode {
    return new AstNode('document', [
        'meta' => [
            'title' => 'Quarterly Plan',
            'author' => 'Ada Lovelace',
            'keywords' => ['slides', 'pptx'],
        ],
    ], [
        $heading('Roadmap'),
        $paragraph([
            $text('Ship '),
            new AstNode('strong', [], [$text('PPTX')]),
            $text(' writer '),
            new AstNode('link', ['url' => 'https://example.test/spec'], [$text('spec')]),
        ]),
        new AstNode('bullet_list', [], [
            $listItem('Native package assembly'),
            $listItem('Reader round trip'),
        ]),
        new AstNode('ordered_list', ['start' => 3], [
            $listItem('Draft'),
            $listItem('Review'),
        ]),
        new AstNode('table', ['caption' => 'Metrics'], [
            new AstNode('table_head', [], [
                $row([$cell('Metric'), $cell('Value')]),
            ]),
            new AstNode('table_body', [], [
                $row([$cell('Coverage'), $cell('PPTX')]),
            ]),
        ]),
        $paragraph([
            new AstNode('image', ['url' => 'images/pixel.png', 'alt' => 'Architecture diagram', 'title' => 'Architecture']),
        ]),
        $heading('Second'),
        $paragraph([$text('Follow-up slide')]),
    ]);
};

$mediaOptions = [
    'modified' => '2026-07-01T00:00:00Z',
    'mediaResources' => [
        'images/pixel.png' => [
            'data' => "\x89PNG\r\n\x1a\nfake",
            'mimeType' => 'image/png',
        ],
    ],
];

$collectText = static function (AstNode $node) use (&$collectText): string {
    $text = '';
    if (isset($node->attrs['text']) && is_scalar($node->attrs['text'])) {
        $text .= (string) $node->attrs['text'];
    }
    if ($node->type === 'image') {
        $text .= (string) $node->attr('alt', '');
    }
    foreach ($node->children as $child) {
        $text .= ' ' . $collectText($child);
    }

    return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
};

$findNodes = static function (AstNode $node, string $type) use (&$findNodes): array {
    $nodes = $node->type === $type ? [$node] : [];
    foreach ($node->children as $child) {
        foreach ($findNodes($child, $type) as $found) {
            $nodes[] = $found;
        }
    }

    return $nodes;
};

return [
    'writes deterministic presentation package parts' => static function (TestRunner $t) use ($document, $mediaOptions): void {
        $bytes = (new PptxWriter($mediaOptions))->write($document());
        $package = ZipPackage::fromString($bytes);
        $names = $package->names();

        foreach ([
            '[Content_Types].xml',
            '_rels/.rels',
            'docProps/core.xml',
            'docProps/app.xml',
            'ppt/presentation.xml',
            'ppt/_rels/presentation.xml.rels',
            'ppt/slides/slide1.xml',
            'ppt/slides/slide2.xml',
            'ppt/slides/_rels/slide1.xml.rels',
            'ppt/slideLayouts/slideLayout1.xml',
            'ppt/slideMasters/slideMaster1.xml',
            'ppt/theme/theme1.xml',
            'ppt/tableStyles.xml',
            'ppt/media/image1.png',
        ] as $partName) {
            $t->true(in_array($partName, $names, true), "Missing PPTX part {$partName}");
        }

        $contentTypes = $package->read('[Content_Types].xml');
        $t->contains('presentationml.presentation.main+xml', $contentTypes);
        $t->contains('presentationml.slide+xml', $contentTypes);
        $t->contains('Extension="png" ContentType="image/png"', $contentTypes);

        $presentation = $package->read('ppt/presentation.xml');
        $t->contains('r:id="rId1"', $presentation);
        $t->contains('r:id="rId2"', $presentation);

        $slide = $package->read('ppt/slides/slide1.xml');
        $t->contains('<a:buChar', $slide);
        $t->contains('<a:buAutoNum type="arabicPeriod" startAt="3"', $slide);
        $t->contains('drawingml/2006/table', $slide);
        $t->contains('<p:pic>', $slide);
        $t->contains('Architecture diagram', $slide);

        $slideRelationships = $package->read('ppt/slides/_rels/slide1.xml.rels');
        $t->contains('relationships/image', $slideRelationships);
        $t->contains('../media/image1.png', $slideRelationships);
        $t->contains('relationships/hyperlink', $slideRelationships);
        $t->contains('TargetMode="External"', $slideRelationships);

        $coreProperties = $package->read('docProps/core.xml');
        $t->contains('<dc:title>Quarterly Plan</dc:title>', $coreProperties);
        $t->contains('<dc:creator>Ada Lovelace</dc:creator>', $coreProperties);
    },

    'round trips generated pptx through native reader' => static function (TestRunner $t) use ($document, $mediaOptions, $collectText, $findNodes): void {
        $bytes = (new PptxWriter($mediaOptions))->write($document());
        $roundTrip = (new PptxReader())->read($bytes);

        $t->same('pptx', $roundTrip->attr('sourceFormat'));
        $pptx = $roundTrip->attr('pptx');
        $t->same(2, $pptx['slideCount']);
        $text = $collectText($roundTrip);
        foreach ([
            'Roadmap',
            'Ship PPTX writer spec',
            'Native package assembly',
            'Reader round trip',
            'Draft',
            'Review',
            'Metric',
            'Coverage',
            'Architecture diagram',
            'Second',
            'Follow-up slide',
        ] as $needle) {
            $t->contains($needle, $text);
        }

        $tables = $findNodes($roundTrip, 'table');
        $t->same(1, count($tables));
        $t->same(true, $tables[0]->attr('pptxTable'));

        $images = $findNodes($roundTrip, 'image');
        $t->same(1, count($images));
        $t->same('ppt/media/image1.png', $images[0]->attr('url'));
        $t->same('Architecture diagram', $images[0]->attr('alt'));
    },

    'registers pptx writer through converter' => static function (TestRunner $t) use ($document, $mediaOptions): void {
        $t->same(true, PandocConverter::canWrite('pptx'));
        $bytes = PandocConverter::write($document(), 'pptx', $mediaOptions);
        $package = ZipPackage::fromString($bytes);

        $t->contains('presentationml.presentation.main+xml', $package->read('[Content_Types].xml'));
        $t->contains('Roadmap', $package->read('ppt/slides/slide1.xml'));
    },

    'rejects non-document roots' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn () => (new PptxWriter())->write(new AstNode('paragraph')));
    },
];
