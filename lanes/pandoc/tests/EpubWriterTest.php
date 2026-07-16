<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubPackage;
use PortLibs\Pandoc\EpubWriter;
use PortLibs\Pandoc\PandocConverter;
use PortLibs\Pandoc\ZipPackage;

$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$link = static fn (string $url, array $children): AstNode => new AstNode('link', ['url' => $url], $children);

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
            'EPUB/toc.ncx',
            'EPUB/nav.xhtml',
            'EPUB/text/title_page.xhtml',
            'EPUB/text/chapter.xhtml',
            'EPUB/styles/stylesheet.css',
        ], $zip->names());

        $epub = EpubPackage::fromString($bytes);
        $assetSummary = $epub->assetSummary();
        $validation = $epub->validationReport();
        $opf = $zip->read('EPUB/package.opf');
        $ncx = $zip->read('EPUB/toc.ncx');

        $t->same('/EPUB/package.opf', $epub->opfPartName());
        $t->same(['/EPUB/text/title_page.xhtml', '/EPUB/text/chapter.xhtml'], $assetSummary['readingOrderParts']);
        $t->same('/EPUB/nav.xhtml', $assetSummary['navigationPart']);
        $t->same(null, $assetSummary['ncxPart']);
        $t->same(['/EPUB/styles/stylesheet.css'], $assetSummary['stylesheetParts']);
        $t->same(true, $validation['epub3']);
        $t->same('ncx', $epub->spineMetadata()['tocId']);
        $t->same('available', $validation['ncx']['bindingStatus']);
        $t->same(1, $validation['ncx']['manifestNcxItemCount']);
        $t->same('EPUB Writer Demo', $epub->metadata()['title']);
        $t->same('Port Libs', $epub->metadata()['creators'][0]);
        $t->same('en-US', $epub->metadata()['languages'][0]);
        $t->same('2026-06-21T08:30:00Z', $epub->metadata()['modified']);

        $navigation = $epub->navigation();
        $t->same('nav', $navigation['type']);
        $t->same('/EPUB/nav.xhtml', $navigation['partName']);
        $t->same('EPUB Writer Demo', $navigation['entries'][0]['label']);
        $t->same('text/chapter.xhtml#start', $navigation['entries'][0]['href']);
        $t->same(['title_page_xhtml', 'chapter'], array_column($epub->spine(), 'idref'));
        $t->same([true, true], array_column($epub->spine(), 'linear'));
        $t->same(['toc', 'landmarks'], array_column($epub->navigationSections(), 'type'));
        $t->same(['Title Page', 'Table of Contents'], array_column($epub->navigationSections()[1]['entries'], 'label'));
        $t->contains('<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>', $opf);
        $t->contains('<spine toc="ncx">', $opf);
        $t->contains('<item id="title_page_xhtml" href="text/title_page.xhtml" media-type="application/xhtml+xml"/>', $opf);
        $t->contains('<itemref idref="title_page_xhtml" linear="yes"/>', $opf);
        $t->contains('<a href="text/title_page.xhtml" epub:type="titlepage">Title Page</a>', $zip->read('EPUB/nav.xhtml'));
        $t->contains('<meta name="dtb:uid" content="urn:uuid:11111111-2222-3333-4444-555555555555"/>', $ncx);
        $t->contains('<content src="text/title_page.xhtml"/>', $ncx);
        $t->contains('<content src="text/chapter.xhtml#start"/>', $ncx);
        $t->contains('<section id="titlepage" epub:type="titlepage" role="doc-titlepage">', $zip->read('EPUB/text/title_page.xhtml'));
        $t->contains('<h1 class="title">EPUB Writer Demo</h1>', $zip->read('EPUB/text/title_page.xhtml'));
        $t->contains('<p class="author">Port Libs</p>', $zip->read('EPUB/text/title_page.xhtml'));
        $t->contains('<strong>EPUB3</strong>', $zip->read('EPUB/text/chapter.xhtml'));
        $t->contains('<table>', $zip->read('EPUB/text/chapter.xhtml'));
    },
    'writes epub3 opf metadata fields and page progression direction' => static function (TestRunner $t) use ($text, $paragraph): void {
        $document = new AstNode('document', [
            'meta' => [
                'identifier' => ['text' => 'urn:isbn:9780000000002', 'scheme' => 'ISBN-13'],
                'title' => ['text' => 'Metadata EPUB', 'file-as' => 'EPUB, Metadata', 'type' => 'main'],
                'creator' => [
                    ['text' => 'Primary Author', 'role' => 'aut', 'file-as' => 'Author, Primary'],
                ],
                'contributor' => [
                    ['text' => 'Editor Name', 'role' => 'edt', 'file-as' => 'Name, Editor'],
                    'Reviewer Name',
                ],
                'subject' => [
                    ['text' => 'WordPress', 'authority' => 'local', 'term' => 'wp'],
                    'Migration',
                ],
                'lang' => 'pl',
                'date' => '2026-06-20',
                'description' => 'Detailed summary',
                'type' => 'Text',
                'format' => 'application/epub+zip',
                'publisher' => 'Open Source Press',
                'source' => 'source-id',
                'relation' => 'related-id',
                'coverage' => 'Global',
                'rights' => 'CC-BY',
                'belongs-to-collection' => 'Port Libs Series',
                'group-position' => '2',
                'page-progression-direction' => 'rtl',
                'accessModes' => ['textual', 'visual'],
                'accessModeSufficient' => ['textual,visual'],
                'accessibilityFeatures' => ['tableOfContents'],
                'accessibilityHazards' => ['none'],
                'accessibilitySummary' => 'Readable with generated navigation.',
            ],
        ], [
            new AstNode('heading', ['level' => 1, 'id' => 'meta'], [$text('Metadata EPUB')]),
            $paragraph([$text('Metadata body.')]),
        ]);

        $bytes = (new EpubWriter([
            'modified' => '2026-06-21T08:37:00Z',
            'writerEpubTitlePage' => false,
        ]))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $epub = EpubPackage::fromString($bytes);
        $opf = $zip->read('EPUB/package.opf');
        $metadata = $epub->metadata();
        $spineMetadata = $epub->spineMetadata();

        $t->contains('<metadata xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:opf="http://www.idpf.org/2007/opf">', $opf);
        $t->contains('<dc:identifier id="bookid">urn:isbn:9780000000002</dc:identifier>', $opf);
        $t->contains('<dc:title id="epub-title-1">Metadata EPUB</dc:title>', $opf);
        $t->contains('<meta refines="#epub-title-1" property="file-as">EPUB, Metadata</meta>', $opf);
        $t->contains('<meta refines="#epub-title-1" property="title-type">main</meta>', $opf);
        $t->contains('<dc:date id="epub-date">2026-06-20</dc:date>', $opf);
        $t->contains('<dc:creator id="epub-creator-1">Primary Author</dc:creator>', $opf);
        $t->contains('<meta refines="#epub-creator-1" property="file-as">Author, Primary</meta>', $opf);
        $t->contains('<meta refines="#epub-creator-1" property="role" scheme="marc:relators">aut</meta>', $opf);
        $t->contains('<dc:contributor id="epub-contributor-1">Editor Name</dc:contributor>', $opf);
        $t->contains('<meta refines="#epub-contributor-1" property="file-as">Name, Editor</meta>', $opf);
        $t->contains('<meta refines="#epub-contributor-1" property="role" scheme="marc:relators">edt</meta>', $opf);
        $t->contains('<dc:subject id="subject-1">WordPress</dc:subject>', $opf);
        $t->contains('<meta refines="#subject-1" property="authority">local</meta>', $opf);
        $t->contains('<meta refines="#subject-1" property="term">wp</meta>', $opf);
        $t->contains('<dc:description>Detailed summary</dc:description>', $opf);
        $t->contains('<dc:type>Text</dc:type>', $opf);
        $t->contains('<dc:format>application/epub+zip</dc:format>', $opf);
        $t->contains('<dc:publisher>Open Source Press</dc:publisher>', $opf);
        $t->contains('<dc:source>source-id</dc:source>', $opf);
        $t->contains('<dc:relation>related-id</dc:relation>', $opf);
        $t->contains('<dc:coverage>Global</dc:coverage>', $opf);
        $t->contains('<dc:rights>CC-BY</dc:rights>', $opf);
        $t->contains('<meta property="belongs-to-collection" id="epub-collection-1">Port Libs Series</meta>', $opf);
        $t->contains('<meta refines="#epub-collection-1" property="collection-type">series</meta>', $opf);
        $t->contains('<meta refines="#epub-collection-1" property="group-position">2</meta>', $opf);
        $t->contains('<meta property="schema:accessMode">textual</meta>', $opf);
        $t->contains('<meta property="schema:accessMode">visual</meta>', $opf);
        $t->contains('<meta property="schema:accessModeSufficient">textual,visual</meta>', $opf);
        $t->contains('<meta property="schema:accessibilityFeature">tableOfContents</meta>', $opf);
        $t->contains('<meta property="schema:accessibilityHazard">none</meta>', $opf);
        $t->contains('<meta property="schema:accessibilitySummary">Readable with generated navigation.</meta>', $opf);
        $t->contains('<spine toc="ncx" page-progression-direction="rtl">', $opf);

        $t->same('Metadata EPUB', $metadata['title']);
        $t->same('Metadata EPUB', $metadata['mainTitle']['text']);
        $t->same('EPUB, Metadata', $metadata['titleDetails'][0]['fileAs']);
        $t->same('main', $metadata['titleDetails'][0]['titleType']);
        $t->same('Primary Author', $metadata['creators'][0]);
        $t->same('Author, Primary', $metadata['creatorDetails'][0]['fileAs']);
        $t->same('aut', $metadata['creatorDetails'][0]['primaryRole']);
        $t->same(['Editor Name', 'Reviewer Name'], $metadata['contributors']);
        $t->same('Name, Editor', $metadata['contributorDetails'][0]['fileAs']);
        $t->same('edt', $metadata['contributorDetails'][0]['primaryRole']);
        $t->same(['WordPress', 'Migration'], $metadata['subjects']);
        $t->same('local', $metadata['subjectDetails'][0]['authority']);
        $t->same('wp', $metadata['subjectDetails'][0]['term']);
        $t->same('Detailed summary', $metadata['description']);
        $t->same('Open Source Press', $metadata['publisher']);
        $t->same(['CC-BY'], $metadata['rights']);
        $t->same('Port Libs Series', $metadata['metaProperties']['belongs-to-collection'][0]['text']);
        $t->same(['textual', 'visual'], $metadata['properties']['schema:accessMode']);
        $t->same(['textual,visual'], $metadata['properties']['schema:accessModeSufficient']);
        $t->same(['tableOfContents'], $metadata['properties']['schema:accessibilityFeature']);
        $t->same('rtl', $spineMetadata['pageProgressionDirection']);
        $t->same('right-to-left', $spineMetadata['readingProgression']);
    },
    'writes epub3 manifest properties for mathml and svg spine xhtml' => static function (TestRunner $t) use ($text, $paragraph): void {
        $document = new AstNode('document', [
            'meta' => ['title' => 'Manifest Properties EPUB', 'author' => 'Port Libs', 'lang' => 'en'],
        ], [
            new AstNode('heading', ['level' => 1], [$text('Manifest Properties EPUB')]),
            $paragraph([$text('Features below.')]),
            new AstNode('raw_html', [
                'format' => 'epub3',
                'html' => '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10"><circle cx="5" cy="5" r="4"></circle></svg><math xmlns="http://www.w3.org/1998/Math/MathML"><mi>x</mi></math>',
            ]),
        ]);

        $bytes = (new EpubWriter([
            'modified' => '2026-06-21T08:38:00Z',
            'writerEpubTitlePage' => false,
        ]))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $epub = EpubPackage::fromString($bytes);
        $opf = $zip->read('EPUB/package.opf');
        $chapter = $zip->read('EPUB/text/chapter.xhtml');
        $resourceProperties = $epub->resourceProperties();

        $t->contains('<item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" properties="mathml svg"/>', $opf);
        $t->contains('<svg xmlns="http://www.w3.org/2000/svg"', $chapter);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML"', $chapter);
        $t->same(['mathml', 'svg'], $epub->manifestItem('chapter')['properties']);
        $t->same(1, $resourceProperties['summary']['mathmlCount']);
        $t->same(1, $resourceProperties['summary']['svgCount']);
        $t->same('chapter', $resourceProperties['itemsByProperty']['mathml'][0]['id']);
        $t->same('chapter', $resourceProperties['itemsByProperty']['svg'][0]['id']);
    },
    'keeps malformed plainmath fallback valid in generated epub xhtml' => static function (TestRunner $t) use ($text, $paragraph): void {
        $document = new AstNode('document', [
            'meta' => ['title' => 'PlainMath Fallback EPUB', 'author' => 'Port Libs', 'lang' => 'en'],
        ], [
            new AstNode('heading', ['level' => 1], [$text('PlainMath Fallback EPUB')]),
            $paragraph([
                $text('Inline '),
                new AstNode('math', ['text' => '\frac{a}{b}', 'display' => false]),
                $text(' then malformed '),
                new AstNode('math', ['text' => '\frac{a}{', 'display' => false]),
                $text('.'),
            ]),
            $paragraph([
                new AstNode('math', ['text' => '\begin{pmatrix}a&b', 'display' => true]),
            ]),
        ]);

        $bytes = (new EpubWriter([
            'modified' => '2026-06-25T14:20:00Z',
            'writerEpubTitlePage' => false,
        ]))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $opf = $zip->read('EPUB/package.opf');
        $chapter = $zip->read('EPUB/text/chapter.xhtml');
        $dom = new DOMDocument('1.0', 'UTF-8');

        $t->true($dom->loadXML($chapter, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING));
        $t->contains('<item id="chapter" href="text/chapter.xhtml" media-type="application/xhtml+xml" properties="mathml"/>', $opf);
        $t->contains('<math xmlns="http://www.w3.org/1998/Math/MathML" display="inline">', $chapter);
        $t->contains('<mfrac><mi>a</mi><mi>b</mi></mfrac>', $chapter);
        $t->contains('<span class="math inline">\frac{a}{</span>', $chapter);
        $t->contains('<span class="math display">\begin{pmatrix}a&amp;b</span>', $chapter);
        $t->true(!str_contains($chapter, '<mi>\\frac</mi>'));
        $t->true(!str_contains($chapter, '<mi>\\begin</mi>'));
    },
    'packages configured epub stylesheets and links every xhtml surface' => static function (TestRunner $t) use ($text, $paragraph): void {
        $document = new AstNode('document', [
            'meta' => [
                'title' => 'Styled EPUB',
                'author' => 'Port Libs',
                'lang' => 'en',
                'css' => ['styles/book.css', 'styles/print.css'],
            ],
        ], [
            new AstNode('heading', ['level' => 1, 'id' => 'styled'], [$text('Styled EPUB')]),
            $paragraph([$text('Styled body.')]),
        ]);

        $bytes = (new EpubWriter([
            'modified' => '2026-06-21T08:40:00Z',
            'writerEpubTitlePage' => false,
            'cssResources' => [
                'styles/book.css' => "body { color: #123456; }\n",
                'styles/print.css' => "h1 { break-before: page; }\n",
            ],
        ]))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $epub = EpubPackage::fromString($bytes);
        $opf = $zip->read('EPUB/package.opf');
        $chapter = $zip->read('EPUB/text/chapter.xhtml');
        $navXml = $zip->read('EPUB/nav.xhtml');

        $t->true(!in_array('EPUB/styles/stylesheet.css', $zip->names(), true), 'Custom EPUB CSS should replace the default stylesheet package entry');
        $t->same("body { color: #123456; }\n", $zip->read('EPUB/styles/stylesheet1.css'));
        $t->same("h1 { break-before: page; }\n", $zip->read('EPUB/styles/stylesheet2.css'));
        $t->same(['/EPUB/styles/stylesheet1.css', '/EPUB/styles/stylesheet2.css'], $epub->assetSummary()['stylesheetParts']);
        $t->contains('<item id="stylesheet1" href="styles/stylesheet1.css" media-type="text/css"/>', $opf);
        $t->contains('<item id="stylesheet2" href="styles/stylesheet2.css" media-type="text/css"/>', $opf);
        $t->contains('<link rel="stylesheet" type="text/css" href="../styles/stylesheet1.css" />', $chapter);
        $t->contains('<link rel="stylesheet" type="text/css" href="../styles/stylesheet2.css" />', $chapter);
        $t->contains('<link rel="stylesheet" type="text/css" href="styles/stylesheet1.css" />', $navXml);
        $t->contains('<link rel="stylesheet" type="text/css" href="styles/stylesheet2.css" />', $navXml);
    },
    'writes epub through the registered converter alias and reads it back' => static function (TestRunner $t) use ($text, $paragraph): void {
        $document = new AstNode('document', [
            'meta' => ['title' => 'Converter EPUB', 'author' => 'Port Libs', 'lang' => 'en'],
        ], [
            new AstNode('heading', ['level' => 1], [$text('Converter EPUB')]),
            $paragraph([$text('Round trip body.')]),
        ]);

        $bytes = PandocConverter::write($document, 'epub', [
            'modified' => '2026-06-21T08:31:00Z',
            'writerEpubTitlePage' => false,
        ]);
        $roundTrip = PandocConverter::read($bytes, 'epub');
        $meta = $roundTrip->attr('meta');

        $t->same('Converter EPUB', $meta['title']);
        $t->same('Port Libs', $meta['author']);
        $t->same('en', $meta['lang']);
        $t->same(['EPUB/text/chapter.xhtml'], $meta['epubReadableResources']);
        $t->same(['EPUB/toc.ncx', 'EPUB/nav.xhtml'], $meta['epubTocResources']);
        $t->same(1, $meta['epubTocEntryCount']);
        $t->same('paragraph', $roundTrip->children[0]->type);
        $t->same('span', $roundTrip->children[0]->children[0]->type);
        $t->same('chapter.xhtml', $roundTrip->children[0]->children[0]->attr('id'));
        $t->same('heading', $roundTrip->children[1]->type);
        $t->same('Converter EPUB', $roundTrip->children[1]->attr('text'));
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
        $coverPage = $zip->read('EPUB/text/cover.xhtml');
        $opf = $zip->read('EPUB/package.opf');
        $navXml = $zip->read('EPUB/nav.xhtml');

        $t->true(in_array('/EPUB/text/media/images/cover.png', $assetSummary['imageParts'], true));
        $t->same('/EPUB/text/media/images/cover.png', $assetSummary['coverImagePart']);
        $t->same(['/EPUB/text/cover.xhtml', '/EPUB/text/title_page.xhtml', '/EPUB/text/chapter.xhtml'], $assetSummary['readingOrderParts']);
        $t->same(['cover_xhtml', 'title_page_xhtml', 'chapter'], array_column($epub->spine(), 'idref'));
        $t->same($coverBytes, $zip->read('EPUB/text/media/images/cover.png'));
        $t->contains('<item id="cover_xhtml" href="text/cover.xhtml" media-type="application/xhtml+xml"/>', $opf);
        $t->contains('<itemref idref="cover_xhtml"/>', $opf);
        $t->contains('<reference type="cover" title="Cover" href="text/cover.xhtml"/>', $opf);
        $t->contains('<section id="cover" epub:type="cover" role="doc-cover">', $coverPage);
        $t->contains('<img src="media/images/cover.png" alt="Cover" />', $coverPage);
        $t->contains('<a href="text/cover.xhtml" epub:type="cover">Cover</a>', $navXml);
        $t->same(['Title Page', 'Cover', 'Table of Contents'], array_column($epub->navigationSections()[1]['entries'], 'label'));
        $t->same(['toc', 'cover'], array_column($epub->guideReferences(), 'type'));
        $t->same('/EPUB/text/cover.xhtml', $epub->guideReferences()[1]['partName']);
        $t->same('cover_xhtml', $epub->guideReferences()[1]['manifestId']);
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
    'packages a metadata cover image resource without a body image reference' => static function (TestRunner $t) use ($text, $paragraph): void {
        $coverBytes = "standalone cover bytes\n";
        $document = new AstNode('document', [
            'meta' => [
                'title' => 'Standalone Cover EPUB',
                'author' => 'Port Libs',
                'lang' => 'en',
                'cover-image' => 'images/standalone-cover.png',
            ],
        ], [
            new AstNode('heading', ['level' => 1], [$text('Standalone Cover EPUB')]),
            $paragraph([$text('Cover is metadata only.')]),
        ]);

        $bytes = (new EpubWriter([
            'modified' => '2026-06-21T08:39:00Z',
            'writerEpubTitlePage' => false,
            'mediaResources' => [
                'images/standalone-cover.png' => [
                    'contents' => $coverBytes,
                    'mimeType' => 'image/png',
                ],
            ],
        ]))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $epub = EpubPackage::fromString($bytes);
        $opf = $zip->read('EPUB/package.opf');
        $chapter = $zip->read('EPUB/text/chapter.xhtml');
        $coverPage = $zip->read('EPUB/text/cover.xhtml');

        $t->same($coverBytes, $zip->read('EPUB/text/media/images/standalone-cover.png'));
        $t->same('/EPUB/text/media/images/standalone-cover.png', $epub->assetSummary()['coverImagePart']);
        $t->same(['/EPUB/text/cover.xhtml', '/EPUB/text/chapter.xhtml'], $epub->assetSummary()['readingOrderParts']);
        $t->same(['cover_xhtml', 'chapter'], array_column($epub->spine(), 'idref'));
        $t->contains('<item id="cover_xhtml" href="text/cover.xhtml" media-type="application/xhtml+xml"/>', $opf);
        $t->contains('<item id="media1" href="text/media/images/standalone-cover.png" media-type="image/png" properties="cover-image"/>', $opf);
        $t->contains('<img src="media/images/standalone-cover.png" alt="Cover" />', $coverPage);
        $t->true(!str_contains($chapter, '<img'), 'Metadata-only cover image should not be injected into body content');
        $t->same(['toc', 'cover'], array_column($epub->guideReferences(), 'type'));
    },
    'writes nested epub nav entries from heading levels' => static function (TestRunner $t) use ($text): void {
        $document = new AstNode('document', [
            'meta' => ['title' => 'Nested Nav EPUB', 'author' => 'Port Libs', 'lang' => 'en'],
        ], [
            new AstNode('heading', ['level' => 1, 'id' => 'intro'], [$text('Introduction')]),
            new AstNode('paragraph', [], [$text('Intro body.')]),
            new AstNode('heading', ['level' => 2, 'id' => 'install'], [$text('Install')]),
            new AstNode('heading', ['level' => 3, 'id' => 'details'], [$text('Details')]),
            new AstNode('heading', ['level' => 1, 'id' => 'appendix'], [$text('Appendix')]),
        ]);

        $bytes = (new EpubWriter(['modified' => '2026-06-21T08:33:00Z']))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $epub = EpubPackage::fromString($bytes);
        $navigation = $epub->navigation();
        $navXml = $zip->read('EPUB/nav.xhtml');

        $t->contains('<li><a href="text/ch1.xhtml#intro">Introduction</a>', $navXml);
        $t->contains('<li><a href="text/ch1.xhtml#install">Install</a>', $navXml);
        $t->contains('<li><a href="text/ch1.xhtml#details">Details</a>', $navXml);
        $t->contains('<li><a href="text/ch2.xhtml#appendix">Appendix</a>', $navXml);
        $t->same(['Introduction', 'Install', 'Details', 'Appendix'], array_column($navigation['entries'], 'label'));
        $t->same(['text/ch1.xhtml#intro', 'text/ch1.xhtml#install', 'text/ch1.xhtml#details', 'text/ch2.xhtml#appendix'], array_column($navigation['entries'], 'href'));
        $t->same([1, 2, 3, 1], array_column($navigation['entries'], 'depth'));

        $roundTrip = PandocConverter::read($bytes, 'epub');
        $tocEntries = $roundTrip->attr('meta')['epubTocEntries'];
        $t->same(['Introduction', 'Install', 'Details', 'Appendix'], array_column($tocEntries, 'text'));
        $t->same([1, 2, 3, 1], array_column($tocEntries, 'level'));
    },
    'splits epub spine documents at writer split level and rewrites chapter links' => static function (TestRunner $t) use ($text, $paragraph, $link): void {
        $document = new AstNode('document', [
            'meta' => ['title' => 'Split EPUB', 'author' => 'Port Libs', 'lang' => 'en'],
        ], [
            new AstNode('heading', ['level' => 1, 'id' => 'intro'], [$text('Intro')]),
            $paragraph([$text('See '), $link('#appendix', [$text('appendix')]), $text('.')]),
            new AstNode('heading', ['level' => 2, 'id' => 'setup'], [$text('Setup')]),
            $paragraph([$text('Setup body.')]),
            new AstNode('heading', ['level' => 1, 'id' => 'appendix'], [$text('Appendix')]),
            $paragraph([$text('Appendix body.')]),
            new AstNode('heading', ['level' => 2, 'id' => 'details'], [$text('Details')]),
        ]);

        $bytes = (new EpubWriter(['modified' => '2026-06-21T08:34:00Z', 'writerSplitLevel' => 1]))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $epub = EpubPackage::fromString($bytes);
        $assetSummary = $epub->assetSummary();
        $opf = $zip->read('EPUB/package.opf');
        $navXml = $zip->read('EPUB/nav.xhtml');
        $chapter1 = $zip->read('EPUB/text/ch1.xhtml');
        $chapter2 = $zip->read('EPUB/text/ch2.xhtml');

        $t->same([
            'mimetype',
            'META-INF/container.xml',
            'EPUB/package.opf',
            'EPUB/toc.ncx',
            'EPUB/nav.xhtml',
            'EPUB/text/title_page.xhtml',
            'EPUB/text/ch1.xhtml',
            'EPUB/text/ch2.xhtml',
            'EPUB/styles/stylesheet.css',
        ], $zip->names());
        $t->same(['/EPUB/text/title_page.xhtml', '/EPUB/text/ch1.xhtml', '/EPUB/text/ch2.xhtml'], $assetSummary['readingOrderParts']);
        $t->contains('<item id="ncx" href="toc.ncx" media-type="application/x-dtbncx+xml"/>', $opf);
        $t->contains('<spine toc="ncx">', $opf);
        $t->contains('<item id="title_page_xhtml" href="text/title_page.xhtml" media-type="application/xhtml+xml"/>', $opf);
        $t->contains('<item id="chapter1" href="text/ch1.xhtml" media-type="application/xhtml+xml"/>', $opf);
        $t->contains('<item id="chapter2" href="text/ch2.xhtml" media-type="application/xhtml+xml"/>', $opf);
        $t->contains('<itemref idref="title_page_xhtml" linear="yes"/>', $opf);
        $t->contains('<itemref idref="chapter1"/>', $opf);
        $t->contains('<itemref idref="chapter2"/>', $opf);
        $t->contains('<a href="text/title_page.xhtml" epub:type="titlepage">Title Page</a>', $navXml);
        $t->contains('<li><a href="text/ch1.xhtml#intro">Intro</a>', $navXml);
        $t->contains('<li><a href="text/ch1.xhtml#setup">Setup</a>', $navXml);
        $t->contains('<li><a href="text/ch2.xhtml#appendix">Appendix</a>', $navXml);
        $t->contains('<li><a href="text/ch2.xhtml#details">Details</a>', $navXml);
        $t->contains('<h1 id="intro">Intro</h1>', $chapter1);
        $t->contains('<a href="ch2.xhtml#appendix">appendix</a>', $chapter1);
        $t->contains('<h2 id="setup">Setup</h2>', $chapter1);
        $t->true(!str_contains($chapter1, 'Appendix body.'), 'First split chapter should not contain second level-1 section body');
        $t->contains('<h1 id="appendix">Appendix</h1>', $chapter2);
        $t->contains('<h2 id="details">Details</h2>', $chapter2);

        $roundTrip = PandocConverter::read($bytes, 'epub');
        $roundTripMeta = $roundTrip->attr('meta');
        $t->same(['EPUB/text/title_page.xhtml', 'EPUB/text/ch1.xhtml', 'EPUB/text/ch2.xhtml'], $roundTripMeta['epubReadableResources']);
        $t->same(['EPUB/toc.ncx', 'EPUB/nav.xhtml'], $roundTripMeta['epubTocResources']);
        $t->same(['Intro', 'Setup', 'Appendix', 'Details'], array_column($roundTripMeta['epubTocEntries'], 'text'));
    },
    'allows epub chapter splitting to be disabled for a single spine document' => static function (TestRunner $t) use ($text): void {
        $document = new AstNode('document', [
            'meta' => ['title' => 'No Split EPUB', 'author' => 'Port Libs', 'lang' => 'en'],
        ], [
            new AstNode('heading', ['level' => 1, 'id' => 'one'], [$text('One')]),
            new AstNode('heading', ['level' => 1, 'id' => 'two'], [$text('Two')]),
        ]);

        $bytes = (new EpubWriter([
            'modified' => '2026-06-21T08:35:00Z',
            'writerSplitLevel' => 0,
            'writerEpubTitlePage' => false,
        ]))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $epub = EpubPackage::fromString($bytes);
        $navXml = $zip->read('EPUB/nav.xhtml');

        $t->same(['/EPUB/text/chapter.xhtml'], $epub->assetSummary()['readingOrderParts']);
        $t->true(in_array('EPUB/text/chapter.xhtml', $zip->names(), true));
        $t->true(!in_array('EPUB/text/title_page.xhtml', $zip->names(), true), 'Disabled EPUB title page should not be packaged');
        $t->contains('<li><a href="text/chapter.xhtml#one">One</a>', $navXml);
        $t->contains('<li><a href="text/chapter.xhtml#two">Two</a>', $navXml);
    },
    'marks inferred epub title pages non-linear without fallback author text' => static function (TestRunner $t) use ($text): void {
        $document = new AstNode('document', [
            'meta' => ['lang' => 'en'],
        ], [
            new AstNode('heading', ['level' => 1, 'id' => 'start'], [$text('Inferred Title')]),
            new AstNode('paragraph', [], [$text('Body.')]),
        ]);

        $bytes = (new EpubWriter(['modified' => '2026-06-21T08:36:00Z']))->write($document);
        $zip = ZipPackage::fromString($bytes);
        $epub = EpubPackage::fromString($bytes);
        $opf = $zip->read('EPUB/package.opf');
        $titlePage = $zip->read('EPUB/text/title_page.xhtml');

        $t->same('Inferred Title', $epub->metadata()['title']);
        $t->same(['title_page_xhtml', 'chapter'], array_column($epub->spine(), 'idref'));
        $t->same([false, true], array_column($epub->spine(), 'linear'));
        $t->contains('<itemref idref="title_page_xhtml" linear="no"/>', $opf);
        $t->contains('<h1 class="title">Inferred Title</h1>', $titlePage);
        $t->true(!str_contains($titlePage, '<p class="author">Port Libs</p>'), 'Fallback author should stay package metadata only');
    },
];
