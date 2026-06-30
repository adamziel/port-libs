<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\DocxWriter;
use PortLibs\Pandoc\DocxWriterGoldenManifest;
use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcRelationship;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$doc = static fn (array $blocks, array $attrs = []): AstNode => new AstNode('document', $attrs, $blocks);
$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$item = static fn (array $children): AstNode => new AstNode('list_item', [], $children);
$cell = static fn (array $children = [], array $attrs = []): AstNode => new AstNode('table_cell', $attrs, $children);
$row = static fn (array $cells): AstNode => new AstNode('table_row', [], $cells);

$packageParts = static function (string $bytes): array {
    $package = ZipPackage::fromString($bytes);
    $parts = [];
    foreach ($package->entries() as $entry) {
        if (!$entry->isDirectory()) {
            $parts[$entry->name] = $package->read($entry->name);
        }
    }

    return [$package, $parts];
};
$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }

    rmdir($path);
};
$writeFile = static function (string $root, string $relativePath, string $contents): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create DOCX writer audit fixture directory');
    }

    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException('Unable to write DOCX writer audit fixture');
    }
};
$corePropertiesDocx = static function (string $title, string $created, string $modified): string {
    $xmlTitle = htmlspecialchars($title, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $xmlCreated = htmlspecialchars($created, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $xmlModified = htmlspecialchars($modified, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    return ZipPackage::build([
        [
            'name' => 'docProps/core.xml',
            'data' => '<?xml version="1.0" encoding="UTF-8"?>'
                . '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"'
                . ' xmlns:dc="http://purl.org/dc/elements/1.1/"'
                . ' xmlns:dcterms="http://purl.org/dc/terms/"'
                . ' xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
                . '<dc:title>' . $xmlTitle . '</dc:title>'
                . '<dcterms:created xsi:type="dcterms:W3CDTF">' . $xmlCreated . '</dcterms:created>'
                . '<dcterms:modified xsi:type="dcterms:W3CDTF">' . $xmlModified . '</dcterms:modified>'
                . '</cp:coreProperties>',
        ],
    ]);
};
$jpeg250x250At120Dpi = static function (): string {
    $bytes = hex2bin('FFD8FFE000104A46494600010101007800780000FFC000110800FA00FA03011100021100031100FFD9');
    if (!is_string($bytes)) {
        throw new RuntimeException('Unable to create DOCX writer JPEG fixture');
    }

    return $bytes;
};

return [
    'emits deterministic core docx package parts for writer golden comparison' => static function (TestRunner $t) use ($doc, $text, $paragraph, $plain, $item, $packageParts): void {
        $document = $doc(
            [
                new AstNode('heading', ['level' => 1], [$text('Package core')]),
                $paragraph([
                    $text('Alpha'),
                    new AstNode('space'),
                    new AstNode('strong', [], [$text('bold')]),
                    new AstNode('space'),
                    new AstNode('emph', [], [$text('italic')]),
                    $text('  tail'),
                ]),
                new AstNode('bullet_list', [], [
                    $item([$plain([$text('bullet one')])]),
                ]),
                new AstNode('ordered_list', ['start' => 3], [
                    $item([$plain([$text('step three')])]),
                ]),
                $paragraph([
                    $text('See'),
                    new AstNode('space'),
                    new AstNode('link', ['url' => 'https://example.test/audit?x=1&y=2'], [$text('audit')]),
                    $text('.'),
                ]),
            ],
            [
                'title' => 'Package core',
                'creator' => 'Port Libs',
                'description' => 'Generated for writer golden comparison',
                'created' => '2026-06-30T00:00:00Z',
            ]
        );

        $writer = new DocxWriter();
        $firstBytes = $writer->write($document);
        $secondBytes = $writer->write($document);
        [$package, $parts] = $packageParts($firstBytes);

        $t->same(hash('sha256', $firstBytes), hash('sha256', $secondBytes));
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'docProps/core.xml',
            'docProps/app.xml',
            'docProps/custom.xml',
            'word/document.xml',
            'word/_rels/document.xml.rels',
            'word/_rels/footnotes.xml.rels',
            'word/comments.xml',
            'word/footnotes.xml',
            'word/fontTable.xml',
            'word/numbering.xml',
            'word/settings.xml',
            'word/styles.xml',
            'word/theme/theme1.xml',
            'word/webSettings.xml',
        ], array_map(static fn ($entry): string => $entry->name, $package->entries()));

        $contentTypes = OpcContentTypes::fromXml($parts['[Content_Types].xml']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $contentTypes->defaults()['rels'] ?? null);
        $t->same('application/xml', $contentTypes->defaults()['xml'] ?? null);
        $t->same('application/vnd.openxmlformats-officedocument.obfuscatedFont', $contentTypes->defaults()['odttf'] ?? null);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $contentTypes->contentTypeForPart('/docProps/core.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.extended-properties+xml', $contentTypes->contentTypeForPart('/docProps/app.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.custom-properties+xml', $contentTypes->contentTypeForPart('/docProps/custom.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $contentTypes->contentTypeForPart('/word/document.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', $contentTypes->contentTypeForPart('/word/comments.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml', $contentTypes->contentTypeForPart('/word/footnotes.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml', $contentTypes->contentTypeForPart('/word/fontTable.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $contentTypes->contentTypeForPart('/word/styles.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml', $contentTypes->contentTypeForPart('/word/numbering.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml', $contentTypes->contentTypeForPart('/word/settings.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.theme+xml', $contentTypes->contentTypeForPart('/word/theme/theme1.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml', $contentTypes->contentTypeForPart('/word/webSettings.xml'));

        $rootRels = OpcRelationships::fromXml($parts['_rels/.rels'], '/');
        $rootDocument = $rootRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument');
        $t->true($rootDocument instanceof OpcRelationship, 'Root officeDocument relationship missing');
        $t->same('word/document.xml', $rootDocument?->target);
        $t->same('docProps/core.xml', $rootRels->firstOfType('http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties')?->target);
        $t->same('docProps/app.xml', $rootRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties')?->target);
        $t->same('docProps/custom.xml', $rootRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties')?->target);

        $documentRels = OpcRelationships::fromXml($parts['word/_rels/document.xml.rels'], '/word/document.xml');
        $t->same('comments.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments')?->target);
        $t->same('footnotes.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes')?->target);
        $t->same('theme/theme1.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme')?->target);
        $t->same('fontTable.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable')?->target);
        $t->same('webSettings.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings')?->target);
        $t->same('styles.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles')?->target);
        $t->same('numbering.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering')?->target);
        $t->same('settings.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings')?->target);
        $hyperlink = $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink');
        $t->true($hyperlink instanceof OpcRelationship, 'External hyperlink relationship missing');
        $t->same('rId9', $hyperlink?->id);
        $t->same('https://example.test/audit?x=1&y=2', $hyperlink?->target);
        $t->same(OpcRelationship::TARGET_MODE_EXTERNAL, $hyperlink?->targetMode);

        $documentXml = $parts['word/document.xml'];
        $t->contains('<w:pStyle w:val="Heading1"/>', $documentXml);
        $t->contains('<w:b/>', $documentXml);
        $t->contains('<w:i/>', $documentXml);
        $t->contains('<w:t xml:space="preserve">  tail</w:t>', $documentXml);
        $t->contains('<w:numId w:val="1001"/>', $documentXml);
        $t->contains('<w:numId w:val="1002"/>', $documentXml);
        $t->contains('<w:hyperlink r:id="rId9">', $documentXml);
        $t->contains('<w:sectPr><w:footnotePr><w:numRestart w:val="eachSect"/></w:footnotePr></w:sectPr>', $documentXml);

        $t->contains('<dc:title>Package core</dc:title>', $parts['docProps/core.xml']);
        $t->contains('<dc:creator>Port Libs</dc:creator>', $parts['docProps/core.xml']);
        $t->contains('<dc:description>Generated for writer golden comparison</dc:description>', $parts['docProps/core.xml']);
        $t->contains('<dcterms:created xsi:type="dcterms:W3CDTF">2026-06-30T00:00:00Z</dcterms:created>', $parts['docProps/core.xml']);
        $t->contains('<dcterms:modified xsi:type="dcterms:W3CDTF">2026-06-30T00:00:00Z</dcterms:modified>', $parts['docProps/core.xml']);
        $t->contains('<Application>Microsoft Word 12.0.0</Application>', $parts['docProps/app.xml']);
        $t->contains('<Words>83</Words>', $parts['docProps/app.xml']);
        $t->contains('<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties"', $parts['docProps/custom.xml']);
        $footnoteRels = OpcRelationships::fromXml($parts['word/_rels/footnotes.xml.rels'], '/word/footnotes.xml');
        $footnoteHyperlink = $footnoteRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink');
        $t->true($footnoteHyperlink instanceof OpcRelationship, 'Footnote hyperlink relationship mirror missing');
        $t->same('rId9', $footnoteHyperlink?->id);
        $t->same('https://example.test/audit?x=1&y=2', $footnoteHyperlink?->target);
        $t->same(OpcRelationship::TARGET_MODE_EXTERNAL, $footnoteHyperlink?->targetMode);
        $t->contains('<w:comments', $parts['word/comments.xml']);
        $t->contains('<w:separator/>', $parts['word/footnotes.xml']);
        $t->contains('<w:continuationSeparator/>', $parts['word/footnotes.xml']);
        $t->contains('<w:font w:name="Aptos"', $parts['word/fontTable.xml']);
        $t->contains('<w:font w:name="Courier New"', $parts['word/fontTable.xml']);
        $t->contains('<w:rFonts w:asciiTheme="minorHAnsi" w:cstheme="minorBidi" w:eastAsiaTheme="minorEastAsia" w:hAnsiTheme="minorHAnsi"/>', $parts['word/styles.xml']);
        $t->contains('<w:latentStyles w:count="276" w:defLockedState="0" w:defQFormat="0" w:defSemiHidden="0" w:defUIPriority="0" w:defUnhideWhenUsed="0"/>', $parts['word/styles.xml']);
        $t->contains('w:styleId="Heading1"', $parts['word/styles.xml']);
        $t->contains('w:styleId="Hyperlink"', $parts['word/styles.xml']);
        $t->contains('<w:startOverride w:val="3"/>', $parts['word/numbering.xml']);
        $t->contains('<w:settings', $parts['word/settings.xml']);
        $t->contains('<w:embedSystemFonts/>', $parts['word/settings.xml']);
        $t->contains('<w:clrSchemeMapping', $parts['word/settings.xml']);
        $t->contains('<a:theme', $parts['word/theme/theme1.xml']);
        $t->contains('<a:srgbClr val="156082"/>', $parts['word/theme/theme1.xml']);
        $t->contains('<a:latin typeface="Aptos Display" panose="02110004020202020204"/>', $parts['word/theme/theme1.xml']);
        $t->contains('<a:latin typeface="Aptos" panose="02110004020202020204"/>', $parts['word/theme/theme1.xml']);
        $t->contains('<w:allowPNG/>', $parts['word/webSettings.xml']);
    },

    'reuses external hyperlink relationships for repeated targets' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $url = 'http://example.com/';
        $document = $doc([
            $paragraph([
                new AstNode('link', ['url' => $url], [$text('first'), new AstNode('space'), $text('link')]),
                new AstNode('space'),
                new AstNode('link', ['url' => $url], [$text('second'), new AstNode('space'), $text('link')]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));

        $t->same(1, substr_count($parts['word/_rels/document.xml.rels'], 'relationships/hyperlink'));
        $t->same(1, substr_count($parts['word/_rels/footnotes.xml.rels'], 'relationships/hyperlink'));
        $t->same(2, substr_count($parts['word/document.xml'], '<w:hyperlink r:id="rId9">'));
        $t->contains('<w:hyperlink r:id="rId9"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t xml:space="preserve">first link</w:t></w:r></w:hyperlink>', $parts['word/document.xml']);
        $t->contains('<w:hyperlink r:id="rId9"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t xml:space="preserve">second link</w:t></w:r></w:hyperlink>', $parts['word/document.xml']);
        $t->contains('Target="http://example.com/"', $parts['word/_rels/document.xml.rels']);
        $t->contains('Target="http://example.com/"', $parts['word/_rels/footnotes.xml.rels']);
    },

    'keeps standalone space runs around inline boundary markup' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                $text('An'),
                $text(' '),
                new AstNode('link', ['url' => '#target'], [$text('internal'), $text(' '), $text('link')]),
                $text(' '),
                $text('and'),
                $text(' '),
                $text('a note'),
                new AstNode('note', [], [$paragraph([$text('note body')])]),
                $text(' '),
                $text('after'),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:t xml:space="preserve">An</w:t></w:r><w:r><w:t xml:space="preserve"> </w:t></w:r><w:hyperlink w:anchor="target">', $documentXml);
        $t->contains('</w:hyperlink><w:r><w:t xml:space="preserve"> </w:t></w:r><w:r><w:t xml:space="preserve">and a note</w:t></w:r>', $documentXml);
        $t->contains('<w:footnoteReference w:id="9"/></w:r><w:r><w:t xml:space="preserve"> </w:t></w:r><w:r><w:t xml:space="preserve">after</w:t></w:r>', $documentXml);
        $t->contains('<w:t xml:space="preserve">internal link</w:t>', $documentXml);
        $t->true(!str_contains($documentXml, '<w:t xml:space="preserve">An </w:t></w:r><w:hyperlink'), 'Leading boundary space was folded into the previous run');
        $t->true(!str_contains($documentXml, '</w:hyperlink><w:r><w:t xml:space="preserve"> and'), 'Trailing boundary space was folded into the following run');
    },

    'splits native inline spaces and east asian text into upstream-like runs' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $space = $text(' ');
        $document = $doc([
            $paragraph([
                $text('Hello,'),
                $space,
                $text('世界.'),
                $space,
                $text('This'),
                $space,
                $text('costs'),
                $space,
                $text('€10.'),
            ]),
            $paragraph([
                $text('An'),
                $space,
                new AstNode('link', ['url' => 'http://example.com/'], [$text('external'), $space, $text('link')]),
                $space,
                $text('to'),
                $space,
                $text('site.'),
            ]),
            $paragraph([
                new AstNode('strong', [], [
                    $text('bold'),
                    $space,
                    new AstNode('emph', [], [$text('bold'), $space, $text('italics')]),
                ]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:t xml:space="preserve">Hello, </w:t></w:r><w:r><w:rPr><w:rFonts w:hint="eastAsia"/></w:rPr><w:t xml:space="preserve">世界.</w:t></w:r><w:r><w:t xml:space="preserve"> This costs €10.</w:t>', $documentXml);
        $t->contains('<w:t xml:space="preserve">An</w:t></w:r><w:r><w:t xml:space="preserve"> </w:t></w:r><w:hyperlink r:id="rId9">', $documentXml);
        $t->contains('</w:hyperlink><w:r><w:t xml:space="preserve"> </w:t></w:r><w:r><w:t xml:space="preserve">to site.</w:t>', $documentXml);
        $t->contains('<w:rPr><w:b/><w:bCs/></w:rPr><w:t xml:space="preserve">bold</w:t>', $documentXml);
        $t->contains('<w:rPr><w:b/><w:bCs/></w:rPr><w:t xml:space="preserve"> </w:t>', $documentXml);
        $t->contains('<w:rPr><w:b/><w:bCs/><w:i/><w:iCs/></w:rPr><w:t xml:space="preserve">bold italics</w:t>', $documentXml);
    },

    'emits native document metadata in core properties' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $space = static fn (): AstNode => new AstNode('text', ['text' => ' ']);
        $metaInlines = static fn (array $children): array => ['type' => 'MetaInlines', 'value' => $children];

        $document = $doc(
            [$paragraph([$text('Testing document properties')])],
            [
                'meta' => [
                    'title' => 'Testing custom properties',
                    'author' => ['A. M.'],
                    'category' => $metaInlines([$text('My'), $space(), $text('Category')]),
                    'description' => [
                        'type' => 'MetaBlocks',
                        'value' => [
                            new AstNode('paragraph', [], [
                                $text('Long'),
                                $space(),
                                $text('description'),
                                new AstNode('softbreak'),
                                $text('spanning'),
                                $space(),
                                $text('several'),
                                $space(),
                                $text('lines.'),
                            ]),
                            new AstNode('plain', [], [
                                $text('This'),
                                $space(),
                                $text('is'),
                                $space(),
                                $text('á'),
                                $space(),
                                new AstNode('raw_html_inline', ['format' => 'html', 'text' => '<i>', 'html' => '<i>']),
                                $text('second'),
                                $space(),
                                $text('line.'),
                                new AstNode('raw_html_inline', ['format' => 'html', 'text' => '</i>', 'html' => '</i>']),
                            ]),
                        ],
                    ],
                    'lang' => $metaInlines([$text('en-US')]),
                    'subject' => $metaInlines([$text('This'), $space(), $text('is'), $space(), $text('the'), $space(), $text('subject')]),
                    'keywords' => [
                        'type' => 'MetaList',
                        'value' => [
                            $metaInlines([$text('keyword'), $space(), $text('1')]),
                            $metaInlines([$text('keyword'), $space(), $text('2')]),
                        ],
                    ],
                ],
            ]
        );

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $coreXml = $parts['docProps/core.xml'];

        $t->contains('<dc:title>Testing custom properties</dc:title>', $coreXml);
        $t->contains('<dc:creator>A. M.</dc:creator>', $coreXml);
        $t->contains('<cp:category>My Category</cp:category>', $coreXml);
        $t->contains('<dc:description>Long description spanning several lines._x000d_' . "\n" . 'This is á second line.</dc:description>', $coreXml);
        $t->contains('<dc:language>en-US</dc:language>', $coreXml);
        $t->contains('<dc:subject>This is the subject</dc:subject>', $coreXml);
        $t->contains('<cp:keywords>keyword 1, keyword 2</cp:keywords>', $coreXml);
    },

    'writer golden stable comparison ignores volatile core property timestamps only' => static function (TestRunner $t) use ($removeTree, $writeFile, $corePropertiesDocx): void {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pandoc-docx-writer-core-stable-' . bin2hex(random_bytes(6));
        if (!mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Unable to create DOCX writer stable comparison fixture root');
        }

        try {
            $writeFile($root, 'test/docx/golden/core.docx', $corePropertiesDocx(
                'Core title',
                '2025-08-04T18:53:46Z',
                '2025-08-04T18:53:46Z'
            ));
            $writeFile($root, 'generated-docx/core.docx', $corePropertiesDocx(
                'Core title',
                '1980-01-01T00:00:00Z',
                '1980-01-01T00:00:00Z'
            ));

            $matchingReport = (new DocxWriterGoldenManifest($root, 'test/docx', 8, 'generated-docx'))->report();
            $t->same('matched-stable-package-semantics', $matchingReport['packageComparison']['status']);
            $t->same(1, $matchingReport['packageComparison']['matchedPackageCount']);
            $t->same(0, $matchingReport['packageComparison']['mismatchedPackageCount']);
            $t->same('stable-match', $matchingReport['packageComparison']['comparisonRows'][0]['status']);

            $writeFile($root, 'generated-docx/core.docx', $corePropertiesDocx(
                'Changed title',
                '1980-01-01T00:00:00Z',
                '1980-01-01T00:00:00Z'
            ));

            $changedReport = (new DocxWriterGoldenManifest($root, 'test/docx', 8, 'generated-docx'))->report();
            $t->same('mismatched-stable-package-semantics', $changedReport['packageComparison']['status']);
            $t->same(0, $changedReport['packageComparison']['matchedPackageCount']);
            $t->same(1, $changedReport['packageComparison']['mismatchedPackageCount']);
            $t->same(['xml-part-semantics'], $changedReport['packageComparison']['comparisonRows'][0]['mismatchKinds']);
            $t->same('docProps/core.xml', $changedReport['packageComparison']['comparisonRows'][0]['mismatchDetails']['xmlPartDeltas']['changedXmlParts'][0]['partName']);
        } finally {
            $removeTree($root);
        }
    },

    'writer golden lists case uses reader native fixture instead of lists writer fixture' => static function (TestRunner $t) use ($removeTree, $writeFile, $corePropertiesDocx, $packageParts): void {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pandoc-docx-writer-lists-source-' . bin2hex(random_bytes(6));
        if (!mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Unable to create DOCX writer lists fixture root');
        }

        try {
            $writeFile($root, 'test/docx/golden/lists.docx', $corePropertiesDocx(
                'Lists',
                '2026-06-30T00:00:00Z',
                '2026-06-30T00:00:00Z'
            ));
            $writeFile($root, 'test/docx/lists.native', '[Para [Str "reader",Space,Str "fixture"]]');
            $writeFile($root, 'test/docx/lists_writer.native', '[Para [Str "writer",Space,Str "fixture"]]');

            $report = (new DocxWriterGoldenManifest(
                $root,
                'test/docx',
                8,
                null,
                'generated-docx',
                ['lists.docx']
            ))->report();

            $caseRow = $report['generation']['caseRows'][0] ?? [];
            $t->same('lists.native', $caseRow['nativeFile'] ?? null);
            $t->same('generated', $caseRow['status'] ?? null);
            $t->same(1, $report['generation']['generatedPackageCount']);

            $generated = file_get_contents($root . DIRECTORY_SEPARATOR . 'generated-docx' . DIRECTORY_SEPARATOR . 'lists.docx');
            if (!is_string($generated)) {
                throw new RuntimeException('Unable to read generated lists fixture');
            }
            [, $parts] = $packageParts($generated);
            $t->contains('<w:t xml:space="preserve">reader fixture</w:t>', $parts['word/document.xml']);
            $t->true(!str_contains($parts['word/document.xml'], 'writer fixture'), 'lists_writer.native should not feed lists.docx generation');
        } finally {
            $removeTree($root);
        }
    },

    'emits local image media parts with document image relationships' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts, $jpeg250x250At120Dpi): void {
        $mediaDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'port-libs-docx-writer-media-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!mkdir($mediaDir, 0777, true) && !is_dir($mediaDir)) {
            throw new RuntimeException('Unable to create DOCX writer media fixture directory');
        }
        $imageBytes = $jpeg250x250At120Dpi();
        $imagePath = $mediaDir . DIRECTORY_SEPARATOR . 'lalune.jpg';
        if (file_put_contents($imagePath, $imageBytes) === false) {
            throw new RuntimeException('Unable to write DOCX writer media fixture');
        }

        try {
            $document = $doc([
                $paragraph([
                    new AstNode('image', [
                        'id' => 'fig:testimg',
                        'url' => 'lalune.jpg',
                        'title' => 'fig:',
                    ], [$text('testimg')]),
                ]),
            ]);

            [, $parts] = $packageParts((new DocxWriter(['mediaBasePath' => $mediaDir]))->write($document));
            $contentTypes = OpcContentTypes::fromXml($parts['[Content_Types].xml']);
            $documentRels = OpcRelationships::fromXml($parts['word/_rels/document.xml.rels'], '/word/document.xml');
            $imageRel = $documentRels->byId('rId9');
            $documentXml = $parts['word/document.xml'];

            $t->same($imageBytes, $parts['word/media/rId9.jpg'] ?? null);
            $t->same('image/jpeg', $contentTypes->contentTypeForPart('/word/media/rId9.jpg'));
            $t->true($imageRel instanceof OpcRelationship, 'Image relationship rId9 missing');
            $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $imageRel?->type);
            $t->same('media/rId9.jpg', $imageRel?->target);
            $t->contains('xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"', $documentXml);
            $t->contains('<w:bookmarkStart w:id="12" w:name="fig:testimg"/>', $documentXml);
            $t->contains('<wp:extent cx="1905000" cy="1905000"/>', $documentXml);
            $t->contains('<wp:docPr descr="testimg" title="fig:" id="10" name="Picture"/>', $documentXml);
            $t->contains('<pic:cNvPr descr="lalune.jpg" id="11" name="Picture"/>', $documentXml);
            $t->contains('<a:blip r:embed="rId9"/>', $documentXml);
            $t->contains('<a:ext cx="1905000" cy="1905000"/>', $documentXml);
            $t->contains('<w:bookmarkEnd w:id="12"/>', $documentXml);
        } finally {
            @unlink($imagePath);
            @rmdir($mediaDir);
        }
    },

    'seeds heading bookmark ids after image drawing ids' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts, $jpeg250x250At120Dpi): void {
        $mediaDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'port-libs-docx-writer-media-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!mkdir($mediaDir, 0777, true) && !is_dir($mediaDir)) {
            throw new RuntimeException('Unable to create DOCX writer media fixture directory');
        }
        $imageBytes = $jpeg250x250At120Dpi();
        $imagePath = $mediaDir . DIRECTORY_SEPARATOR . 'lalune.jpg';
        if (file_put_contents($imagePath, $imageBytes) === false) {
            throw new RuntimeException('Unable to write DOCX writer media fixture');
        }

        try {
            $document = $doc([
                $paragraph([
                    new AstNode('image', [
                        'id' => 'fig:testimg',
                        'url' => 'lalune.jpg',
                        'title' => 'fig:',
                    ], [$text('testimg')]),
                ]),
                new AstNode('heading', ['id' => 'after-image', 'level' => 1], [$text('After image')]),
            ]);

            [, $parts] = $packageParts((new DocxWriter(['mediaBasePath' => $mediaDir]))->write($document));
            $documentXml = $parts['word/document.xml'];

            $t->contains('<wp:docPr descr="testimg" title="fig:" id="10" name="Picture"/>', $documentXml);
            $t->contains('<pic:cNvPr descr="lalune.jpg" id="11" name="Picture"/>', $documentXml);
            $t->contains('<w:bookmarkStart w:id="12" w:name="fig:testimg"/>', $documentXml);
            $t->contains('<w:bookmarkStart w:id="13" w:name="after-image"/>', $documentXml);
            $t->contains('<w:bookmarkEnd w:id="13"/>', $documentXml);
        } finally {
            @unlink($imagePath);
            @rmdir($mediaDir);
        }
    },

    'deduplicates inline image media and preserves later hyperlink relationship ids' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $mediaDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'port-libs-docx-writer-media-' . getmypid() . '-' . bin2hex(random_bytes(4));
        if (!mkdir($mediaDir, 0777, true) && !is_dir($mediaDir)) {
            throw new RuntimeException('Unable to create DOCX writer media fixture directory');
        }
        $imageBytes = "bounded-docx-writer-inline-jpeg-fixture\n";
        $imagePath = $mediaDir . DIRECTORY_SEPARATOR . 'lalune.jpg';
        if (file_put_contents($imagePath, $imageBytes) === false) {
            throw new RuntimeException('Unable to write DOCX writer media fixture');
        }

        try {
            $document = $doc([
                $paragraph([
                    $text('This picture '),
                    new AstNode('image', [
                        'url' => 'lalune.jpg',
                        'title' => 'First identicon',
                        'attributes' => [
                            'width' => '0.8888888888888888in',
                            'height' => '0.8888888888888888in',
                        ],
                    ], [$text('green')]),
                    new AstNode('space'),
                    new AstNode('link', ['url' => 'http://www.google.com'], [
                        $text('one '),
                        new AstNode('image', [
                            'url' => 'lalune.jpg',
                            'title' => 'Second identicon',
                            'attributes' => [
                                'width' => '0.8888888888888888in',
                                'height' => '0.8888888888888888in',
                            ],
                        ], [$text('red')]),
                    ]),
                ]),
            ]);

            [, $parts] = $packageParts((new DocxWriter(['mediaBasePath' => $mediaDir]))->write($document));
            $documentRels = OpcRelationships::fromXml($parts['word/_rels/document.xml.rels'], '/word/document.xml');
            $documentXml = $parts['word/document.xml'];

            $mediaParts = array_values(array_filter(
                array_keys($parts),
                static fn (string $name): bool => str_starts_with($name, 'word/media/')
            ));

            $t->same(['word/media/rId9.jpg'], $mediaParts);
            $t->same($imageBytes, $parts['word/media/rId9.jpg']);
            $t->same('media/rId9.jpg', $documentRels->byId('rId9')?->target);
            $t->same('http://www.google.com', $documentRels->byId('rId14')?->target);
            $t->same(OpcRelationship::TARGET_MODE_EXTERNAL, $documentRels->byId('rId14')?->targetMode);
            $t->contains('<wp:extent cx="812800" cy="812800"/>', $documentXml);
            $t->contains('<wp:docPr descr="green" title="First identicon" id="10" name="Picture"/>', $documentXml);
            $t->contains('<wp:docPr descr="red" title="Second identicon" id="12" name="Picture"/>', $documentXml);
            $t->contains('<w:t xml:space="preserve">This picture</w:t></w:r><w:r><w:t xml:space="preserve"> </w:t></w:r><w:r><w:drawing>', $documentXml);
            $t->contains('</w:drawing></w:r><w:r><w:t xml:space="preserve"> </w:t></w:r><w:hyperlink r:id="rId14">', $documentXml);
            $t->contains('<w:t xml:space="preserve">one</w:t></w:r><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t xml:space="preserve"> </w:t></w:r><w:r><w:drawing>', $documentXml);
            $t->contains('<w:hyperlink r:id="rId14">', $documentXml);
        } finally {
            @unlink($imagePath);
            @rmdir($mediaDir);
        }
    },

    'emits bounded word tables with captions spans and nested cell blocks' => static function (TestRunner $t) use ($doc, $text, $paragraph, $item, $cell, $row, $packageParts): void {
        $document = $doc([
            new AstNode('table', [
                'captionInlines' => [
                    $text('Table'),
                    new AstNode('space'),
                    new AstNode('emph', [], [$text('coverage')]),
                ],
                'widths' => [0.25, 0.75],
            ], [
                new AstNode('table_head', [], [
                    $row([
                        $cell([$text('Feature')]),
                        $cell([$text('State')]),
                    ]),
                ]),
                new AstNode('table_body', [], [
                    $row([
                        $cell([new AstNode('strong', [], [$text('table')])]),
                        $cell([
                            $paragraph([$text('paragraph cell')]),
                            new AstNode('bullet_list', [], [
                                $item([$paragraph([$text('nested list')])]),
                            ]),
                        ]),
                    ]),
                    $row([
                        $cell([$text('wide')], ['colspan' => 2]),
                    ]),
                ]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];
        $roundTrip = (new DocxReader())->read((new DocxWriter())->write($document));

        $t->contains('<w:pStyle w:val="TableCaption"/>', $documentXml);
        $t->contains('<w:tbl>', $documentXml);
        $t->contains('<w:tblStyle w:val="Table"/>', $documentXml);
        $t->contains('<w:tblW w:type="pct" w:w="5000"/>', $documentXml);
        $t->contains('<w:tblLayout w:type="fixed"/>', $documentXml);
        $t->contains('<w:tblLook w:firstRow="1"', $documentXml);
        $t->contains('<w:tblHeader w:val="on"/>', $documentXml);
        $t->contains('<w:tblGrid><w:gridCol w:w="1980"/><w:gridCol w:w="5940"/></w:tblGrid>', $documentXml);
        $t->contains('<w:pStyle w:val="Compact"/>', $documentXml);
        $t->contains('<w:pStyle w:val="FirstParagraph"/>', $documentXml);
        $t->contains('<w:t xml:space="preserve">Feature</w:t>', $documentXml);
        $t->contains('<w:b/>', $documentXml);
        $t->contains('<w:t xml:space="preserve">paragraph cell</w:t>', $documentXml);
        $t->contains('<w:numId w:val="1001"/>', $documentXml);
        $t->contains('<w:gridSpan w:val="2"/>', $documentXml);
        $t->contains('w:styleId="Table"', $parts['word/styles.xml']);
        $t->contains('w:styleId="BodyText"', $parts['word/styles.xml']);
        $t->contains('w:styleId="FirstParagraph"', $parts['word/styles.xml']);
        $t->contains('w:styleId="Compact"', $parts['word/styles.xml']);
        $t->contains('w:styleId="Caption"', $parts['word/styles.xml']);
        $t->contains('w:styleId="TableCaption"', $parts['word/styles.xml']);
        $t->same('table', $roundTrip->children[0]->type);
        $t->same('Table coverage', $roundTrip->children[0]->attr('caption'));
    },

    'emits collected footnotes and footnote hyperlink relationships' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                $text('This is a test'),
                new AstNode('note', [], [
                    $paragraph([
                        new AstNode('link', ['url' => 'http://wikipedia.org/'], [$text('http://wikipedia.org/')]),
                    ]),
                ]),
                $text('.'),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];
        $footnotesXml = $parts['word/footnotes.xml'];
        $documentRels = OpcRelationships::fromXml($parts['word/_rels/document.xml.rels'], '/word/document.xml');
        $footnoteRels = OpcRelationships::fromXml($parts['word/_rels/footnotes.xml.rels'], '/word/footnotes.xml');

        $t->contains('<w:footnoteReference w:id="9"/>', $documentXml);
        $t->contains('<w:footnote w:id="9">', $footnotesXml);
        $t->contains('<w:pStyle w:val="FootnoteText"/>', $footnotesXml);
        $t->contains('<w:footnoteRef/>', $footnotesXml);
        $t->contains('<w:hyperlink r:id="rId10">', $footnotesXml);
        $t->contains('<w:t xml:space="preserve">http://wikipedia.org/</w:t>', $footnotesXml);
        $t->same('http://wikipedia.org/', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink')?->target);
        $t->same('rId10', $footnoteRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink')?->id);
        $t->same('http://wikipedia.org/', $footnoteRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink')?->target);
    },

    'uses document dynamic ids for notes around hyperlinks and bookmarks' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                $text('First'),
                new AstNode('note', [], [$paragraph([$text('first note')])]),
                new AstNode('space'),
                new AstNode('link', ['url' => 'http://example.com/'], [$text('link')]),
                new AstNode('space'),
                $text('second'),
                new AstNode('note', [], [$paragraph([$text('second note')])]),
            ]),
            new AstNode('heading', ['id' => 'after-notes', 'level' => 2], [$text('After notes')]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];
        $footnotesXml = $parts['word/footnotes.xml'];
        $documentRels = OpcRelationships::fromXml($parts['word/_rels/document.xml.rels'], '/word/document.xml');

        $t->contains('<w:footnoteReference w:id="9"/>', $documentXml);
        $t->contains('<w:hyperlink r:id="rId10">', $documentXml);
        $t->contains('<w:footnoteReference w:id="11"/>', $documentXml);
        $t->contains('<w:bookmarkStart w:id="12" w:name="after-notes"/>', $documentXml);
        $t->contains('<w:footnote w:id="9">', $footnotesXml);
        $t->contains('<w:footnote w:id="11">', $footnotesXml);
        $t->true(!str_contains($footnotesXml, '<w:footnote w:id="10">'), 'Footnote id collided with hyperlink relationship id');
        $t->same('http://example.com/', $documentRels->byId('rId10')?->target);
    },

    'emits comment ranges and comments part records from native comment spans' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                $text('Before '),
                new AstNode('span', [
                    'classes' => ['comment-start'],
                    'attributes' => [
                        'id' => '0',
                        'author' => 'Jesse Rosenthal',
                        'date' => '2016-05-09T16:13:00Z',
                    ],
                ], [$text('I left a comment.')]),
                $text('target'),
                new AstNode('span', [
                    'classes' => ['comment-end'],
                    'attributes' => ['id' => '0'],
                ]),
                $text(' after'),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];
        $commentsXml = $parts['word/comments.xml'];

        $t->contains('<w:commentRangeStart w:id="0"/>', $documentXml);
        $t->contains('<w:commentRangeEnd w:id="0"/>', $documentXml);
        $t->contains('<w:commentReference w:id="0"/>', $documentXml);
        $t->contains('<w:t xml:space="preserve">target</w:t>', $documentXml);
        $t->true(!str_contains($documentXml, 'I left a comment.'), 'Comment body leaked into document.xml');
        $t->contains('<w:comment w:id="0" w:author="Jesse Rosenthal" w:date="2016-05-09T16:13:00Z">', $commentsXml);
        $t->contains('<w:pStyle w:val="CommentText"/>', $commentsXml);
        $t->contains('<w:annotationRef/>', $commentsXml);
        $t->contains('<w:t xml:space="preserve">I left a comment.</w:t>', $commentsXml);
    },

    'emits invisible index reference fields from native indexref spans' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                $text('David French'),
                new AstNode('span', ['classes' => ['indexref'], 'attributes' => ['entry' => 'French']]),
                $text(' Belding'),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:t xml:space="preserve">David French</w:t></w:r><w:r><w:fldChar w:fldCharType="begin"/></w:r>', $documentXml);
        $t->contains('<w:instrText xml:space="preserve"> XE "French" </w:instrText>', $documentXml);
        $t->contains('<w:r><w:fldChar w:fldCharType="end"/></w:r><w:r><w:t xml:space="preserve"> Belding</w:t></w:r>', $documentXml);
    },

    'preserves raw openxml bookmarks and hyphenated internal link anchors' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            new AstNode('heading', ['level' => 2, 'id' => 'a-section-for-testing-link-targets'], [$text('A section')]),
            $paragraph([
                new AstNode('link', ['url' => '#a-section-for-testing-link-targets'], [$text('section link')]),
            ]),
            $paragraph([
                new AstNode('span', ['id' => 'fig:testimg', 'classes' => ['anchor']]),
                new AstNode('link', ['url' => '#fig:testimg'], [$text('figure link')]),
            ]),
            $paragraph([
                new AstNode('raw_inline', ['format' => 'openxml', 'text' => '<w:bookmarkStart w:id="0" w:name="Aliquam"/>']),
                $text('Aliquam'),
                new AstNode('raw_inline', ['format' => 'openxml', 'text' => '<w:bookmarkEnd w:id="0"/>']),
            ]),
            $paragraph([
                new AstNode('raw_inline', ['format' => 'openxml', 'text' => '<w:fldSimple w:instr="REF ref_fig:testimg" />']),
            ]),
            $paragraph([
                new AstNode('raw_inline', ['format' => 'openxml', 'text' => '<w:fldSimple w:instr=" PAGEREF fig:testimg \h "><w:r><w:t>7</w:t></w:r></w:fldSimple>']),
            ]),
            $paragraph([
                new AstNode('raw_inline', ['format' => 'openxml', 'text' => '<w:r><w:fldChar w:fldCharType="begin"/></w:r><w:r><w:instrText xml:space="preserve"> REF fig:testimg \h </w:instrText></w:r><w:r><w:fldChar w:fldCharType="separate"/></w:r><w:r><w:t>testimg</w:t></w:r><w:r><w:fldChar w:fldCharType="end"/></w:r>']),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:bookmarkStart w:id="10" w:name="a-section-for-testing-link-targets"/>', $documentXml);
        $t->contains('<w:hyperlink w:anchor="a-section-for-testing-link-targets">', $documentXml);
        $t->contains('<w:bookmarkStart w:id="9" w:name="fig:testimg"/>', $documentXml);
        $t->contains('<w:hyperlink w:anchor="fig:testimg">', $documentXml);
        $t->contains('<w:bookmarkEnd w:id="10"/>', $documentXml);
        $t->contains('<w:bookmarkStart w:id="0" w:name="Aliquam"/>', $documentXml);
        $t->contains('<w:bookmarkEnd w:id="0"/>', $documentXml);
        $t->contains('<w:fldSimple w:instr="REF ref_fig:testimg"/>', $documentXml);
        $t->contains('<w:fldSimple w:instr=" PAGEREF fig:testimg \h "><w:r><w:t>7</w:t></w:r></w:fldSimple>', $documentXml);
        $t->contains('<w:instrText xml:space="preserve"> REF fig:testimg \h </w:instrText>', $documentXml);
        $t->true(!str_contains($documentXml, '&lt;w:bookmarkStart'), 'Raw bookmark was XML-escaped');
        $t->true(!str_contains($documentXml, '&lt;w:fldSimple'), 'Raw simple field was XML-escaped');
        $t->true(!str_contains($documentXml, '&lt;w:instrText'), 'Raw complex field was XML-escaped');
    },

    'omits empty raw openxml inline fragments' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                $text('Before'),
                new AstNode('raw_inline', ['format' => 'openxml', 'text' => '']),
                $text(' after'),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:t xml:space="preserve">Before</w:t></w:r><w:r><w:t xml:space="preserve"> after</w:t>', $documentXml);
        $t->true(!str_contains($documentXml, '<w:r/></w:p>'), 'Empty raw OpenXML inline emitted an empty run');
    },

    'uses hashed bookmark names for long internal anchors' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $opening = 'remote-folder-or-longlonglonglonglong-file-with-manymanymanymany-letters-inside-opening';
        $closing = 'remote-folder-or-longlonglonglonglong-file-with-manymanymanymany-letters-inside-closing';
        $openingBookmark = 'X' . substr(sha1($opening), 1);
        $closingBookmark = 'X' . substr(sha1($closing), 1);
        $unicodeAnchor = "\u{043E}\u{0433}\u{043B}\u{0430}\u{0432}\u{043B}\u{0435}\u{043D}\u{0438}\u{0435}";
        $unicodeText = "\u{041E}\u{0433}\u{043B}\u{0430}\u{0432}\u{043B}\u{0435}\u{043D}\u{0438}\u{0435}";

        $document = $doc([
            new AstNode('heading', ['level' => 1, 'id' => $unicodeAnchor], [$text($unicodeText)]),
            $paragraph([new AstNode('link', ['url' => '#' . $opening], [$text('Open remote folder')])]),
            $paragraph([new AstNode('link', ['url' => '#' . $closing], [$text('Close remote folder')])]),
            new AstNode('heading', ['level' => 2, 'id' => $opening], [$text('Open folder')]),
            new AstNode('heading', ['level' => 2, 'id' => $closing], [$text('Close folder')]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:bookmarkStart w:id="11" w:name="' . $unicodeAnchor . '"/>', $documentXml);
        $t->contains('<w:hyperlink w:anchor="' . $openingBookmark . '">', $documentXml);
        $t->contains('<w:hyperlink w:anchor="' . $closingBookmark . '">', $documentXml);
        $t->contains('<w:bookmarkStart w:id="9" w:name="' . $openingBookmark . '"/>', $documentXml);
        $t->contains('<w:bookmarkStart w:id="10" w:name="' . $closingBookmark . '"/>', $documentXml);
        $t->contains('<w:bookmarkEnd w:id="11"/>', $documentXml);
        $t->true(!str_contains($documentXml, 'w:anchor="remote-folder-or-longlonglonglonglong-fi"'), 'Long bookmark names should not collide by truncation');
    },

    'passes through raw openxml block fragments around normal paragraphs' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([$text('Cell compartments')]),
            new AstNode('raw_block', ['format' => 'openxml', 'text' => '<w:tbl><w:tr><w:tc>']),
            $paragraph([$text('Ribosome')]),
            new AstNode('raw_block', ['format' => 'openxml', 'text' => '</w:tc><w:tc>']),
            $paragraph([$text('Lysosome')]),
            new AstNode('raw_block', ['format' => 'openxml', 'text' => '</w:tc></w:tr></w:tbl>']),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:tbl><w:tr><w:tc><w:p><w:pPr><w:pStyle w:val="BodyText"/></w:pPr><w:r><w:t xml:space="preserve">Ribosome</w:t></w:r></w:p></w:tc><w:tc><w:p><w:pPr><w:pStyle w:val="BodyText"/></w:pPr><w:r><w:t xml:space="preserve">Lysosome</w:t></w:r></w:p></w:tc></w:tr></w:tbl>', $documentXml);
        $t->true(!str_contains($documentXml, '&lt;w:tbl'), 'Raw OpenXML table fragment was XML-escaped');
    },

    'emits insertion and deletion spans as tracked change markup' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                $text('Here is a '),
                new AstNode('span', [
                    'classes' => ['deletion'],
                    'attributes' => [
                        'author' => 'Author',
                    ],
                ], [$text('dummy')]),
                new AstNode('span', [
                    'classes' => ['insertion'],
                    'attributes' => [
                        'author' => 'Author',
                        'date' => '2014-06-25T10:40:00Z',
                    ],
                ], [$text('test')]),
                $text('.'),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:r><w:t xml:space="preserve">Here is a </w:t></w:r>', $documentXml);
        $t->contains('<w:del w:id="1" w:author="Author"><w:r><w:delText xml:space="preserve">dummy</w:delText></w:r></w:del>', $documentXml);
        $t->contains('<w:ins w:id="1" w:author="Author" w:date="2014-06-25T10:40:00Z"><w:r><w:t xml:space="preserve">test</w:t></w:r></w:ins>', $documentXml);
        $t->true(!str_contains($documentXml, '<w:r><w:t>dummy</w:t></w:r>'), 'Deletion text must use w:delText');
    },

    'preserves empty raw block as a table separator paragraph' => static function (TestRunner $t) use ($doc, $text, $cell, $row, $packageParts): void {
        $simpleTable = static fn (string $left, string $right): AstNode => new AstNode('table', ['widths' => [0.0, 0.0]], [
            new AstNode('table_head', [], []),
            new AstNode('table_body', [], [
                $row([
                    $cell([$text($left)]),
                    $cell([$text($right)]),
                ]),
            ]),
            new AstNode('table_foot', [], []),
        ]);

        $document = $doc([
            $simpleTable('a', 'b'),
            new AstNode('raw_tex', ['format' => 'latex', 'text' => '', 'tex' => '']),
            $simpleTable('c', 'd'),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:tblW w:type="auto" w:w="0"/>', $documentXml);
        $t->contains('<w:tblLook w:firstRow="0"', $documentXml);
        $t->contains('<w:tblGrid><w:gridCol w:w="3960"/><w:gridCol w:w="3960"/></w:tblGrid>', $documentXml);
        $t->contains('</w:tbl><w:p/><w:tbl>', $documentXml);
    },

    'preserves custom span and div styles without styling links' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                $text('Before '),
                new AstNode('span', ['attributes' => ['custom-style' => 'Emphatic']], [$text('marked')]),
                $text(' and '),
                new AstNode('span', ['attributes' => ['custom-style' => 'MyStyle']], [
                    $text('custom '),
                    new AstNode('link', ['url' => 'https://example.test/style'], [$text('link')]),
                ]),
            ]),
            new AstNode('div', ['attributes' => ['custom-style' => 'My Block Style']], [
                $paragraph([$text('styled paragraph')]),
                new AstNode('heading', ['level' => 2], [$text('unstyled heading')]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];
        $stylesXml = $parts['word/styles.xml'];

        $t->contains('<w:rStyle w:val="Emphatic"/>', $documentXml);
        $t->contains('<w:rStyle w:val="MyStyle"/>', $documentXml);
        $t->contains('<w:hyperlink r:id="rId9"><w:r><w:rPr><w:rStyle w:val="Hyperlink"/></w:rPr><w:t xml:space="preserve">link</w:t></w:r></w:hyperlink>', $documentXml);
        $t->contains('<w:pStyle w:val="MyBlockStyle"/>', $documentXml);
        $t->contains('<w:pStyle w:val="Heading2"/>', $documentXml);
        $t->contains('w:styleId="Emphatic"', $stylesXml);
        $t->contains('w:styleId="MyStyle"', $stylesXml);
        $t->contains('w:styleId="MyBlockStyle"', $stylesXml);
        $t->contains('<w:name w:val="My Block Style"/>', $stylesXml);
        $t->contains('<w:style w:type="paragraph" w:customStyle="1" w:styleId="MyBlockStyle"><w:name w:val="My Block Style"/><w:basedOn w:val="BodyText"/><w:qFormat/></w:style>', $stylesXml);
        $t->contains('<w:style w:type="character" w:customStyle="1" w:styleId="Emphatic"><w:name w:val="Emphatic"/><w:basedOn w:val="BodyTextChar"/></w:style>', $stylesXml);
        $t->contains('w:styleId="Heading9Char"', $stylesXml);
        $t->contains('w:styleId="NormalTok"', $stylesXml);
        $t->true(!str_contains($stylesXml, 'w:styleId="CommentText"'), 'Comment-only style should not be emitted for non-comment documents');
    },

    'uses upstream-style custom style ids and reuses default style definitions' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                new AstNode('span', ['attributes' => ['custom-style' => 'Review-Style 2']], [$text('reviewed')]),
                $text(' and '),
                new AstNode('span', ['attributes' => ['custom-style' => 'Hyperlink']], [$text('already styled')]),
            ]),
            new AstNode('div', ['attributes' => ['custom-style' => 'Body Text']], [
                $paragraph([$text('body style reuse')]),
            ]),
            new AstNode('div', ['attributes' => ['custom-style' => '2026 Block-Style']], [
                $paragraph([$text('new block style')]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];
        $stylesXml = $parts['word/styles.xml'];

        $t->contains('<w:rStyle w:val="Review-Style2"/>', $documentXml);
        $t->contains('<w:rStyle w:val="Hyperlink"/>', $documentXml);
        $t->contains('<w:pStyle w:val="BodyText"/>', $documentXml);
        $t->contains('<w:pStyle w:val="2026Block-Style"/>', $documentXml);
        $t->contains('<w:style w:type="character" w:customStyle="1" w:styleId="Review-Style2"><w:name w:val="Review-Style 2"/><w:basedOn w:val="BodyTextChar"/></w:style>', $stylesXml);
        $t->contains('<w:style w:type="paragraph" w:customStyle="1" w:styleId="2026Block-Style"><w:name w:val="2026 Block-Style"/><w:basedOn w:val="BodyText"/><w:qFormat/></w:style>', $stylesXml);
        $t->same(1, substr_count($stylesXml, 'w:styleId="BodyText"'));
        $t->same(1, substr_count($stylesXml, 'w:styleId="Hyperlink"'));
    },

    'keeps soft breaks as separate preserved runs in custom style output' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([
                $text('Alpha'),
                new AstNode('softbreak'),
                $text('Beta'),
                new AstNode('space'),
                new AstNode('span', ['attributes' => ['custom-style' => 'Emphatic']], [
                    $text('really'),
                    new AstNode('softbreak'),
                    $text('cool'),
                ]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:t xml:space="preserve">Alpha</w:t></w:r><w:r><w:t xml:space="preserve"> </w:t></w:r><w:r><w:t xml:space="preserve">Beta</w:t>', $documentXml);
        $t->contains('<w:r><w:rPr><w:rStyle w:val="Emphatic"/></w:rPr><w:t xml:space="preserve">really</w:t></w:r><w:r><w:rPr><w:rStyle w:val="Emphatic"/></w:rPr><w:t xml:space="preserve"> </w:t></w:r><w:r><w:rPr><w:rStyle w:val="Emphatic"/></w:rPr><w:t xml:space="preserve">cool</w:t></w:r>', $documentXml);
    },

    'reuses reference docx XML sidecars and merges generated custom package semantics' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts, $removeTree): void {
        $tmpDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'docx-writer-reference-' . bin2hex(random_bytes(4));
        if (!mkdir($tmpDir, 0777, true) && !is_dir($tmpDir)) {
            throw new RuntimeException('Unable to create DOCX writer reference fixture directory');
        }

        $referencePath = $tmpDir . DIRECTORY_SEPARATOR . 'reference.docx';
        $appXml = '<?xml version="1.0" encoding="UTF-8"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>Reference App</Application></Properties>';
        $fontTableXml = '<?xml version="1.0" encoding="UTF-8"?><w:fonts xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:font w:name="Reference Font"/></w:fonts>';
        $themeXml = '<?xml version="1.0" encoding="UTF-8"?><a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Reference Theme"><a:themeElements/></a:theme>';
        $webSettingsXml = '<?xml version="1.0" encoding="UTF-8"?><w:webSettings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:allowPNG/></w:webSettings>';
        $stylesXml = '<?xml version="1.0" encoding="UTF-8"?><w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:style w:type="paragraph" w:styleId="BodyText"><w:name w:val="Body Text"/></w:style>'
            . '<w:style w:type="character" w:customStyle="1" w:styleId="Emphatic"><w:name w:val="Emphatic"/></w:style>'
            . '<w:style w:type="paragraph" w:customStyle="1" w:styleId="MyBlockStyle"><w:name w:val="My Block Style"/></w:style>'
            . '<w:style w:type="paragraph" w:customStyle="1" w:styleId="SourceCode"><w:name w:val="Source Code"/></w:style>'
            . '</w:styles>';
        $numberingXml = '<?xml version="1.0" encoding="UTF-8"?><w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:abstractNum w:abstractNumId="0"><w:nsid w:val="AAAA0000"/></w:abstractNum>'
            . '<w:num w:numId="1"><w:abstractNumId w:val="0"/></w:num>'
            . '</w:numbering>';
        $referenceDocumentXml = '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p/><w:sectPr w:rsidR="00ABCDEF"><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/><w:cols w:space="720"/></w:sectPr></w:body></w:document>';
        $settingsXml = '<?xml version="1.0" encoding="UTF-8"?><w:settings xmlns:m="http://schemas.openxmlformats.org/officeDocument/2006/math" xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:w15="http://schemas.microsoft.com/office/word/2012/wordml">'
            . '<w:zoom w:percent="128"/><w:embedSystemFonts/><w:stylePaneFormatFilter w:val="0004" w:latentStyles="1"/><w:doNotTrackMoves/><w:defaultTabStop w:val="720"/><w:characterSpacingControl w:val="doNotCompress"/>'
            . '<w:footnotePr/><w:endnotePr/><m:mathPr/><w:themeFontLang w:val="en-US"/><w15:docId w15:val="{REFERENCE}"/></w:settings>';

        try {
            if (file_put_contents($referencePath, ZipPackage::build([
                ['name' => 'docProps/app.xml', 'data' => $appXml],
                ['name' => 'word/fontTable.xml', 'data' => $fontTableXml],
                ['name' => 'word/theme/theme1.xml', 'data' => $themeXml],
                ['name' => 'word/webSettings.xml', 'data' => $webSettingsXml],
                ['name' => 'word/styles.xml', 'data' => $stylesXml],
                ['name' => 'word/numbering.xml', 'data' => $numberingXml],
                ['name' => 'word/settings.xml', 'data' => $settingsXml],
                ['name' => 'word/document.xml', 'data' => $referenceDocumentXml],
            ])) === false) {
                throw new RuntimeException('Unable to write DOCX writer reference fixture');
            }

            $document = $doc([
                new AstNode('div', ['attributes' => ['custom-style' => 'My Block Style']], [
                    $paragraph([$text('reference block style')]),
                ]),
                new AstNode('div', ['attributes' => ['custom-style' => 'New Style']], [
                    $paragraph([$text('new block style')]),
                ]),
                $paragraph([
                    new AstNode('span', ['attributes' => ['custom-style' => 'Emphatic']], [$text('reference character style')]),
                ]),
            ]);

            [, $parts] = $packageParts((new DocxWriter(['referenceDocxPath' => $referencePath]))->write($document));

            $t->same($appXml, $parts['docProps/app.xml']);
            $t->same($fontTableXml, $parts['word/fontTable.xml']);
            $t->same($themeXml, $parts['word/theme/theme1.xml']);
            $t->same($webSettingsXml, $parts['word/webSettings.xml']);
            $t->contains('<w:pStyle w:val="MyBlockStyle"/>', $parts['word/document.xml']);
            $t->contains('<w:pStyle w:val="NewStyle"/>', $parts['word/document.xml']);
            $t->contains('<w:rStyle w:val="Emphatic"/>', $parts['word/document.xml']);
            $t->contains('<w:sectPr w:rsidR="00ABCDEF"><w:pgSz w:w="12240" w:h="15840"/><w:pgMar w:top="1440" w:right="1440" w:bottom="1440" w:left="1440"/><w:cols w:space="720"/></w:sectPr>', $parts['word/document.xml']);
            $t->same(1, substr_count($parts['word/styles.xml'], 'w:styleId="Emphatic"'));
            $t->same(1, substr_count($parts['word/styles.xml'], 'w:styleId="MyBlockStyle"'));
            $t->contains('w:styleId="NewStyle"', $parts['word/styles.xml']);
            $t->true(strpos($parts['word/styles.xml'], 'w:styleId="NewStyle"') < strpos($parts['word/styles.xml'], 'w:styleId="SourceCode"'), 'Generated custom styles should precede SourceCode');
            $t->contains('w:abstractNumId="0"', $parts['word/numbering.xml']);
            $t->contains('w:abstractNumId="990"', $parts['word/numbering.xml']);
            $t->contains('w:numId="1"', $parts['word/numbering.xml']);
            $t->contains('w:numId="1000"', $parts['word/numbering.xml']);
            $t->contains('<w:zoom w:percent="128"/>', $parts['word/settings.xml']);
            $t->contains('<w:proofState w:grammar="clean" w:spelling="clean"/>', $parts['word/settings.xml']);
            $t->contains('<w:savePreviewPicture/>', $parts['word/settings.xml']);
            $t->true(!str_contains($parts['word/settings.xml'], '<w:footnotePr'), 'Reference footnote settings should be removed from generated settings');
            $t->true(!str_contains($parts['word/settings.xml'], '<w:endnotePr'), 'Reference endnote settings should be removed from generated settings');
            $t->true(!str_contains($parts['word/settings.xml'], '<m:mathPr'), 'Reference math settings should be removed from generated settings');
            $t->true(!str_contains($parts['word/settings.xml'], 'docId'), 'Reference volatile document id should be removed from generated settings');
        } finally {
            $removeTree($tmpDir);
        }
    },

    'emits block text style for simple block quotes' => static function (TestRunner $t) use ($doc, $text, $paragraph, $plain, $item, $packageParts): void {
        $document = $doc([
            new AstNode('blockquote', [], [
                $paragraph([$text('quoted paragraph')]),
                new AstNode('bullet_list', [], [
                    $item([$plain([$text('quoted bullet')])]),
                ]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];
        $roundTrip = (new DocxReader())->read((new DocxWriter())->write($document));

        $t->contains('w:styleId="BlockText"', $parts['word/styles.xml']);
        $t->contains('<w:name w:val="Block Text"/>', $parts['word/styles.xml']);
        $t->contains('<w:pStyle w:val="BlockText"/>', $documentXml);
        $t->contains('<w:t xml:space="preserve">quoted paragraph</w:t>', $documentXml);
        $t->contains('<w:numId w:val="1001"/>', $documentXml);
        $t->contains('<w:t xml:space="preserve">quoted bullet</w:t>', $documentXml);
        $t->same('blockquote', $roundTrip->children[0]->type);
    },

    'resets top-level paragraph style after block quotes and code blocks' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            $paragraph([$text('before quote')]),
            new AstNode('blockquote', [], [
                $paragraph([$text('quoted')]),
            ]),
            $paragraph([$text('after quote')]),
            new AstNode('code_block', ['text' => "alpha\nbeta"]),
            $paragraph([$text('after code')]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:p><w:pPr><w:pStyle w:val="BlockText"/></w:pPr><w:r><w:t xml:space="preserve">quoted</w:t></w:r></w:p>', $documentXml);
        $t->contains('<w:p><w:pPr><w:pStyle w:val="FirstParagraph"/></w:pPr><w:r><w:t xml:space="preserve">after quote</w:t></w:r></w:p>', $documentXml);
        $t->contains('<w:p><w:pPr><w:pStyle w:val="SourceCode"/></w:pPr><w:r><w:rPr><w:rStyle w:val="VerbatimChar"/></w:rPr><w:t xml:space="preserve">alpha</w:t></w:r><w:r><w:br/></w:r><w:r><w:rPr><w:rStyle w:val="VerbatimChar"/></w:rPr><w:t xml:space="preserve">beta</w:t></w:r></w:p>', $documentXml);
        $t->contains('<w:p><w:pPr><w:pStyle w:val="FirstParagraph"/></w:pPr><w:r><w:t xml:space="preserve">after code</w:t></w:r></w:p>', $documentXml);
    },

    'emits definition list term and body paragraph styles' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            new AstNode('definition_list', [], [
                new AstNode('definition_item', ['term' => 'Term 1'], [
                    new AstNode('term', ['text' => 'Term 1'], [$text('Term'), new AstNode('space'), $text('1')]),
                    new AstNode('definition', [], [
                        $paragraph([$text('Definition 1')]),
                    ]),
                ]),
                new AstNode('definition_item', ['term' => 'Term 2 with inline markup'], [
                    new AstNode('term', ['text' => 'Term 2 with inline markup'], [
                        $text('Term 2 with'),
                        new AstNode('space'),
                        new AstNode('emph', [], [$text('inline markup')]),
                    ]),
                    new AstNode('definition', [], [
                        $paragraph([$text('Definition 2')]),
                        $paragraph([new AstNode('code', ['text' => '{ some code, part of Definition 2 }'])]),
                    ]),
                ]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->same(2, substr_count($documentXml, '<w:pStyle w:val="DefinitionTerm"/>'));
        $t->same(3, substr_count($documentXml, '<w:pStyle w:val="Definition"/>'));
        $t->contains('<w:p><w:pPr><w:pStyle w:val="DefinitionTerm"/></w:pPr><w:r><w:t xml:space="preserve">Term 1</w:t></w:r></w:p>', $documentXml);
        $t->contains('<w:p><w:pPr><w:pStyle w:val="DefinitionTerm"/></w:pPr><w:r><w:t xml:space="preserve">Term 2 with</w:t></w:r><w:r><w:t xml:space="preserve"> </w:t></w:r><w:r><w:rPr><w:i/><w:iCs/></w:rPr><w:t xml:space="preserve">inline markup</w:t></w:r></w:p>', $documentXml);
        $t->contains('<w:p><w:pPr><w:pStyle w:val="Definition"/></w:pPr><w:r><w:rPr><w:rStyle w:val="VerbatimChar"/></w:rPr><w:t xml:space="preserve">{ some code, part of Definition 2 }</w:t></w:r></w:p>', $documentXml);
    },

    'emits list numbering instances for starts styles delimiters and continuations' => static function (TestRunner $t) use ($doc, $text, $paragraph, $plain, $item, $packageParts): void {
        $document = $doc([
            new AstNode('ordered_list', ['start' => 1, 'style' => 'decimal', 'delimiter' => 'period'], [
                $item([$paragraph([$text('one')])]),
                $item([
                    $paragraph([$text('two')]),
                    new AstNode('ordered_list', ['start' => 1, 'style' => 'lower_alpha', 'delimiter' => 'default'], [
                        $item([$plain([$text('alpha')])]),
                    ]),
                ]),
            ]),
            new AstNode('ordered_list', ['start' => 4, 'style' => 'decimal', 'delimiter' => 'period'], [
                $item([$paragraph([$text('continued')])]),
            ]),
            new AstNode('ordered_list', ['start' => 1, 'style' => 'upper_roman', 'delimiter' => 'two_parens'], [
                $item([$paragraph([$text('roman')])]),
            ]),
            new AstNode('bullet_list', [], [
                $item([
                    $paragraph([$text('bullet')]),
                    $paragraph([$text('continuation')]),
                ]),
                $item([
                    new AstNode('bullet_list', [], [
                        $item([$paragraph([$text('nested first')])]),
                    ]),
                ]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];
        $numberingXml = $parts['word/numbering.xml'];

        $t->contains('<w:abstractNum w:abstractNumId="990">', $numberingXml);
        $t->contains('<w:abstractNum w:abstractNumId="99411">', $numberingXml);
        $t->contains('<w:abstractNum w:abstractNumId="99701">', $numberingXml);
        $t->contains('<w:abstractNum w:abstractNumId="99414">', $numberingXml);
        $t->contains('<w:abstractNum w:abstractNumId="99631">', $numberingXml);
        $t->contains('<w:abstractNum w:abstractNumId="991">', $numberingXml);
        $t->contains('<w:num w:numId="1000"><w:abstractNumId w:val="990"/></w:num>', $numberingXml);
        $t->contains('<w:num w:numId="1001"><w:abstractNumId w:val="99411"/>', $numberingXml);
        $t->contains('<w:num w:numId="1002"><w:abstractNumId w:val="99701"/>', $numberingXml);
        $t->contains('<w:num w:numId="1003"><w:abstractNumId w:val="99414"/>', $numberingXml);
        $t->contains('<w:num w:numId="1004"><w:abstractNumId w:val="99631"/>', $numberingXml);
        $t->contains('<w:num w:numId="1005"><w:abstractNumId w:val="991"/></w:num>', $numberingXml);
        $t->contains('<w:num w:numId="1006"><w:abstractNumId w:val="991"/></w:num>', $numberingXml);
        $t->contains('<w:startOverride w:val="4"/>', $numberingXml);
        $t->contains('<w:numFmt w:val="lowerLetter"/>', $numberingXml);
        $t->contains('<w:numFmt w:val="upperRoman"/>', $numberingXml);
        $t->contains('<w:lvlText w:val="(%1)"/>', $numberingXml);
        $t->contains('<w:numId w:val="1001"/>', $documentXml);
        $t->contains('<w:numId w:val="1002"/>', $documentXml);
        $t->contains('<w:numId w:val="1003"/>', $documentXml);
        $t->contains('<w:numId w:val="1004"/>', $documentXml);
        $t->contains('<w:numId w:val="1000"/>', $documentXml);
        $t->contains('<w:numId w:val="1005"/>', $documentXml);
        $t->contains('<w:numId w:val="1006"/>', $documentXml);
        $t->contains('<w:t xml:space="preserve">continuation</w:t>', $documentXml);
        $t->contains('<w:t xml:space="preserve">nested first</w:t>', $documentXml);
    },

    'emits upstream ordered list marker ids for default example and large starts' => static function (TestRunner $t) use ($doc, $text, $paragraph, $item, $packageParts): void {
        $document = $doc([
            new AstNode('ordered_list', ['start' => 1, 'style' => 'default', 'delimiter' => 'default'], [
                $item([$paragraph([$text('default marker')])]),
            ]),
            new AstNode('ordered_list', ['start' => 2, 'style' => 'example', 'delimiter' => 'two_parens'], [
                $item([$paragraph([$text('example marker')])]),
            ]),
            new AstNode('ordered_list', ['start' => 9994, 'style' => 'decimal', 'delimiter' => 'period'], [
                $item([$paragraph([$text('large start')])]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $numberingXml = $parts['word/numbering.xml'];

        $t->contains('<w:abstractNum w:abstractNumId="99201">', $numberingXml);
        $t->contains('<w:abstractNum w:abstractNumId="99332">', $numberingXml);
        $t->contains('<w:abstractNum w:abstractNumId="99419994">', $numberingXml);
        $t->contains('<w:num w:numId="1001"><w:abstractNumId w:val="99201"/>', $numberingXml);
        $t->contains('<w:num w:numId="1002"><w:abstractNumId w:val="99332"/>', $numberingXml);
        $t->contains('<w:num w:numId="1003"><w:abstractNumId w:val="99419994"/>', $numberingXml);
        $t->contains('<w:lvl w:ilvl="1"><w:start w:val="1"/><w:numFmt w:val="lowerLetter"/>', $numberingXml);
        $t->contains('<w:lvl w:ilvl="2"><w:start w:val="1"/><w:numFmt w:val="lowerRoman"/>', $numberingXml);
        $t->contains('<w:lvlText w:val="(%1)"/>', $numberingXml);
        $t->contains('<w:startOverride w:val="9994"/>', $numberingXml);
    },

    'emits compact placeholder paragraph for list items that begin with nested lists' => static function (TestRunner $t) use ($doc, $text, $paragraph, $item, $packageParts): void {
        $document = $doc([
            new AstNode('bullet_list', [], [
                $item([
                    new AstNode('bullet_list', [], [
                        $item([$paragraph([$text('nested first')])]),
                    ]),
                ]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:p><w:pPr><w:pStyle w:val="Compact"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1001"/></w:numPr></w:pPr></w:p>', $documentXml);
        $t->contains('<w:p><w:pPr><w:numPr><w:ilvl w:val="1"/><w:numId w:val="1002"/></w:numPr></w:pPr><w:r><w:t xml:space="preserve">nested first</w:t></w:r></w:p>', $documentXml);
    },

    'uses bibliography continuation style for refs div content inside lists' => static function (TestRunner $t) use ($doc, $text, $paragraph, $item, $packageParts): void {
        $document = $doc([
            new AstNode('bullet_list', [], [
                $item([
                    new AstNode('div', ['id' => 'refs'], [
                        new AstNode('heading', ['level' => 1], [$text('three')]),
                        $paragraph([$text('four')]),
                    ]),
                ]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:bookmarkStart w:id="9" w:name="refs"/>', $documentXml);
        $t->contains('<w:p><w:pPr><w:pStyle w:val="Heading1"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1001"/></w:numPr></w:pPr><w:r><w:t xml:space="preserve">three</w:t></w:r></w:p>', $documentXml);
        $t->contains('<w:p><w:pPr><w:pStyle w:val="Bibliography"/><w:numPr><w:ilvl w:val="0"/><w:numId w:val="1000"/></w:numPr></w:pPr><w:r><w:t xml:space="preserve">four</w:t></w:r></w:p>', $documentXml);
        $t->contains('<w:bookmarkEnd w:id="9"/>', $documentXml);
    },

    'emits task list checkboxes as numbering markers without duplicated text glyphs' => static function (TestRunner $t) use ($doc, $text, $paragraph, $plain, $item, $packageParts): void {
        $document = $doc([
            new AstNode('bullet_list', [], [
                $item([$paragraph([new AstNode('text', ['text' => "\u{2610}"]), new AstNode('space'), $text('Unchecked')])]),
                $item([
                    $paragraph([new AstNode('text', ['text' => "\u{2612}"]), new AstNode('space'), $text('Checked')]),
                    $paragraph([$text('with continuation')]),
                ]),
                $item([
                    $plain([new AstNode('text', ['text' => "\u{2612} Checked sublist"])]),
                ]),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];
        $numberingXml = $parts['word/numbering.xml'];

        $t->contains('<w:num w:numId="1001"><w:abstractNumId w:val="992"/></w:num>', $numberingXml);
        $t->contains('<w:num w:numId="1002"><w:abstractNumId w:val="993"/></w:num>', $numberingXml);
        $t->contains('<w:num w:numId="1003"><w:abstractNumId w:val="993"/></w:num>', $numberingXml);
        $t->contains('<w:lvlText w:val="' . "\u{2610}" . '"/>', $numberingXml);
        $t->contains('<w:lvlText w:val="' . "\u{2612}" . '"/>', $numberingXml);
        $t->contains('<w:numId w:val="1001"/>', $documentXml);
        $t->contains('<w:numId w:val="1002"/>', $documentXml);
        $t->contains('<w:numId w:val="1003"/>', $documentXml);
        $t->contains('<w:numId w:val="1000"/>', $documentXml);
        $t->contains('<w:t xml:space="preserve">Unchecked</w:t>', $documentXml);
        $t->contains('<w:t xml:space="preserve">Checked</w:t>', $documentXml);
        $t->contains('<w:t xml:space="preserve">with continuation</w:t>', $documentXml);
        $t->contains('<w:t xml:space="preserve">Checked sublist</w:t>', $documentXml);
        $t->true(!str_contains($documentXml, "\u{2610}"), 'Unchecked task glyph should be carried by numbering, not paragraph text');
        $t->true(!str_contains($documentXml, "\u{2612}"), 'Checked task glyph should be carried by numbering, not paragraph text');
    },

    'normalizes docx package part names and fixed zip timestamps' => static function (TestRunner $t) use ($doc, $text, $paragraph): void {
        $writer = new DocxWriter();
        $parts = $writer->packageParts($doc([$paragraph([$text('Stable package')])]));

        $t->same('word/document.xml', DocxWriter::normalizePackagePartName('/word/./document.xml'));
        $t->same('word/styles.xml', DocxWriter::normalizePackagePartName('word/folder/../styles.xml'));
        $t->same('[Content_Types].xml', DocxWriter::normalizePackagePartName('/[content_types].xml'));
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'docProps/core.xml',
            'docProps/app.xml',
            'docProps/custom.xml',
            'word/document.xml',
            'word/_rels/document.xml.rels',
            'word/_rels/footnotes.xml.rels',
            'word/comments.xml',
            'word/footnotes.xml',
            'word/fontTable.xml',
            'word/numbering.xml',
            'word/settings.xml',
            'word/styles.xml',
            'word/theme/theme1.xml',
            'word/webSettings.xml',
        ], array_column($parts, 'name'));
        foreach ($parts as $part) {
            $t->same(0, $part['modifiedDosTime']);
            $t->same(33, $part['modifiedDosDate']);
        }
    },
];
