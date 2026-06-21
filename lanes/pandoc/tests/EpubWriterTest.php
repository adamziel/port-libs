<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubPackage;
use PortLibs\Pandoc\EpubWriter;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\ZipPackage;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);

return [
    'writes a valid bounded epub3 package from the shared ast' => static function (TestRunner $t) use ($text, $paragraph): void {
        $document = new AstNode('document', [
            'meta' => [
                'title' => 'EPUB Writer Demo',
                'author' => 'Port Libs',
                'lang' => 'en-US',
                'identifier' => 'urn:uuid:11111111-2222-3333-4444-555555555555',
            ],
        ], [
            new AstNode('heading', ['level' => 1, 'id' => 'start'], [$text('EPUB Writer Demo')]),
            $paragraph([
                $text('A bounded '),
                new AstNode('strong', [], [$text('EPUB3')]),
                $text(' package with a '),
                new AstNode('link', ['url' => 'https://example.test/'], [$text('link')]),
                $text('.'),
            ]),
            new AstNode('table', ['alignments' => ['left', 'right']], [
                new AstNode('table_head', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [$paragraph([$text('Name')])]),
                        new AstNode('table_cell', [], [$paragraph([$text('Value')])]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    new AstNode('table_row', [], [
                        new AstNode('table_cell', [], [$paragraph([$text('Format')])]),
                        new AstNode('table_cell', [], [$paragraph([$text('EPUB3')])]),
                    ]),
                ]),
            ]),
        ]);

        $bytes = (new EpubWriter(['modified' => '2026-06-21T08:30:00Z']))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $zip->assertStoredFirstEntry('mimetype', EpubPackage::EPUB_MIMETYPE);
        $t->same([
            'mimetype',
            'META-INF/container.xml',
            'EPUB/package.opf',
            'EPUB/nav.xhtml',
            'EPUB/text/chapter.xhtml',
            'EPUB/styles/stylesheet.css',
        ], $zip->names());

        $epub = EpubPackage::fromString($bytes);
        $assetSummary = $epub->assetSummary();
        $validation = $epub->validationReport();

        $t->same('/EPUB/package.opf', $epub->opfPartName());
        $t->same(['/EPUB/text/chapter.xhtml'], $assetSummary['readingOrderParts']);
        $t->same('/EPUB/nav.xhtml', $assetSummary['navigationPart']);
        $t->same(['/EPUB/styles/stylesheet.css'], $assetSummary['stylesheetParts']);
        $t->same(true, $validation['epub3']);
        $t->same('EPUB Writer Demo', $epub->metadata()['title']);
        $t->same('Port Libs', $epub->metadata()['creators'][0]);
        $t->same('en-US', $epub->metadata()['languages'][0]);
        $t->same('2026-06-21T08:30:00Z', $epub->metadata()['modified']);

        $navigation = $epub->navigation();
        $t->same('nav', $navigation['type']);
        $t->same('/EPUB/nav.xhtml', $navigation['partName']);
        $t->same('EPUB Writer Demo', $navigation['entries'][0]['label']);
        $t->same('text/chapter.xhtml#start', $navigation['entries'][0]['href']);
        $t->contains('<strong>EPUB3</strong>', $zip->read('EPUB/text/chapter.xhtml'));
        $t->contains('<table>', $zip->read('EPUB/text/chapter.xhtml'));
    },
    'writes epub through the registered converter alias and reads it back' => static function (TestRunner $t) use ($text, $paragraph): void {
        $document = new AstNode('document', [
            'meta' => ['title' => 'Converter EPUB', 'author' => 'Port Libs', 'lang' => 'en'],
        ], [
            new AstNode('heading', ['level' => 1], [$text('Converter EPUB')]),
            $paragraph([$text('Round trip body.')]),
        ]);

        $bytes = PandocConverter::write($document, 'epub', ['modified' => '2026-06-21T08:31:00Z']);
        $roundTrip = PandocConverter::read($bytes, 'epub');
        $meta = $roundTrip->attr('meta');

        $t->same('Converter EPUB', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('en', $meta['lang']);
        $t->same(['EPUB/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(1, $meta['epubTocEntryCount']);
        $t->same('heading', $roundTrip->children[0]->type);
        $t->same('Converter EPUB', $roundTrip->children[0]->attr('text'));
    },
    'packages media resources and marks a configured cover image' => static function (TestRunner $t) use ($text): void {
        $coverBytes = "cover image bytes\n";
        $gifBytes = "GIF89a tiny image\n";
        $gifUri = 'data:image/gif;base64,' . base64_encode($gifBytes);
        $document = new AstNode('document', [
            'meta' => ['title' => 'Media EPUB', 'author' => 'Port Libs', 'lang' => 'en'],
        ], [
            new AstNode('heading', ['level' => 1], [$text('Media EPUB')]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => 'images/cover.png',
                    'alt' => 'Cover image',
                ], [$text('Cover image')]),
            ]),
            new AstNode('paragraph', [], [
                new AstNode('image', [
                    'url' => $gifUri,
                    'alt' => 'Inline image',
                ], [$text('Inline image')]),
            ]),
        ]);

        $bytes = (new EpubWriter([
            'modified' => '2026-06-21T08:32:00Z',
            'coverImage' => 'images/cover.png',
            'mediaResources' => [
                'images/cover.png' => [
                    'contents' => $coverBytes,
                    'mimeType' => 'image/png',
                ],
            ],
        ]))->write($document);

        $zip = ZipPackage::fromString($bytes);
        $epub = EpubPackage::fromString($bytes);
        $assetSummary = $epub->assetSummary();
        $chapter = $zip->read('EPUB/text/chapter.xhtml');

        $t->true(in_array('/EPUB/text/media/images/cover.png', $assetSummary['imageParts'], true));
        $t->same('/EPUB/text/media/images/cover.png', $assetSummary['coverImagePart']);
        $t->same($coverBytes, $zip->read('EPUB/text/media/images/cover.png'));
        $t->contains('src="media/images/cover.png"', $chapter);
        $t->contains('data-pandoc-media-source="images/cover.png"', $chapter);

        $mediaNames = array_values(array_filter(
            $zip->names(),
            static fn (string $name): bool => str_starts_with($name, 'EPUB/text/media/')
        ));
        sort($mediaNames);
        $t->same(2, count($mediaNames));
        $t->true(str_ends_with($mediaNames[0], '.gif') || str_ends_with($mediaNames[1], '.gif'), 'Data URI GIF must be packaged as a GIF media entry');
        $t->contains('src="media/', $chapter);
        $t->true(!str_contains($chapter, 'src="data:image/gif'), 'Data URI image src should be replaced by packaged media');

        $coverItems = array_values(array_filter(
            $epub->manifestItems(),
            static fn (array $item): bool => in_array('cover-image', $item['properties'], true)
        ));
        $t->same(1, count($coverItems));
        $t->same('text/media/images/cover.png', $coverItems[0]['href']);
        $t->same('image/png', $coverItems[0]['mediaType']);

        $roundTrip = PandocConverter::read($bytes, 'epub');
        $roundTripMeta = $roundTrip->attr('meta');
        $t->true(in_array('EPUB/text/media/images/cover.png', $roundTripMeta['epubImageResources'], true));
        $t->true(in_array('EPUB/text/media/images/cover.png', $roundTripMeta['epubReferencedResources'], true));
    },
];
