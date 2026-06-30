<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxReader;
use PortLibs\Pandoc\DocxWriter;
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
        $t->contains('<w:numId w:val="1"/>', $documentXml);
        $t->contains('<w:numId w:val="10"/>', $documentXml);
        $t->contains('<w:hyperlink r:id="rId9">', $documentXml);
        $t->contains('<w:sectPr>', $documentXml);

        $t->contains('<dc:title>Package core</dc:title>', $parts['docProps/core.xml']);
        $t->contains('<dc:creator>Port Libs</dc:creator>', $parts['docProps/core.xml']);
        $t->contains('<dc:description>Generated for writer golden comparison</dc:description>', $parts['docProps/core.xml']);
        $t->contains('<dcterms:created xsi:type="dcterms:W3CDTF">2026-06-30T00:00:00Z</dcterms:created>', $parts['docProps/core.xml']);
        $t->contains('<dcterms:modified xsi:type="dcterms:W3CDTF">2026-06-30T00:00:00Z</dcterms:modified>', $parts['docProps/core.xml']);
        $t->contains('<Application>pandoc</Application>', $parts['docProps/app.xml']);
        $t->contains('<HeadingPairs><vt:vector size="2" baseType="variant">', $parts['docProps/app.xml']);
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
        $t->contains('<w:font w:name="Calibri"', $parts['word/fontTable.xml']);
        $t->contains('w:styleId="Heading1"', $parts['word/styles.xml']);
        $t->contains('w:styleId="Hyperlink"', $parts['word/styles.xml']);
        $t->contains('<w:startOverride w:val="3"/>', $parts['word/numbering.xml']);
        $t->contains('<w:settings', $parts['word/settings.xml']);
        $t->contains('<w:compatSetting', $parts['word/settings.xml']);
        $t->contains('<a:theme', $parts['word/theme/theme1.xml']);
        $t->contains('<w:allowPNG/>', $parts['word/webSettings.xml']);
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

        $t->contains('<w:pStyle w:val="Caption"/>', $documentXml);
        $t->contains('<w:tbl>', $documentXml);
        $t->contains('<w:tblGrid><w:gridCol w:w="2160"/><w:gridCol w:w="6480"/></w:tblGrid>', $documentXml);
        $t->contains('<w:t>Feature</w:t>', $documentXml);
        $t->contains('<w:b/>', $documentXml);
        $t->contains('<w:t>paragraph cell</w:t>', $documentXml);
        $t->contains('<w:numId w:val="1"/>', $documentXml);
        $t->contains('<w:gridSpan w:val="2"/>', $documentXml);
        $t->contains('w:styleId="Caption"', $parts['word/styles.xml']);
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
        $t->same('http://wikipedia.org/', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink')?->target);
        $t->same('rId10', $footnoteRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink')?->id);
        $t->same('http://wikipedia.org/', $footnoteRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink')?->target);
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
        $t->contains('<w:t>target</w:t>', $documentXml);
        $t->true(!str_contains($documentXml, 'I left a comment.'), 'Comment body leaked into document.xml');
        $t->contains('<w:comment w:id="0" w:author="Jesse Rosenthal" w:date="2016-05-09T16:13:00Z">', $commentsXml);
        $t->contains('<w:pStyle w:val="CommentText"/>', $commentsXml);
        $t->contains('<w:annotationRef/>', $commentsXml);
        $t->contains('<w:t>I left a comment.</w:t>', $commentsXml);
    },

    'preserves raw openxml bookmarks and hyphenated internal link anchors' => static function (TestRunner $t) use ($doc, $text, $paragraph, $packageParts): void {
        $document = $doc([
            new AstNode('heading', ['level' => 2, 'id' => 'a-section-for-testing-link-targets'], [$text('A section')]),
            $paragraph([
                new AstNode('link', ['url' => '#a-section-for-testing-link-targets'], [$text('section link')]),
            ]),
            $paragraph([
                new AstNode('raw_inline', ['format' => 'openxml', 'text' => '<w:bookmarkStart w:id="0" w:name="Aliquam"/>']),
                $text('Aliquam'),
                new AstNode('raw_inline', ['format' => 'openxml', 'text' => '<w:bookmarkEnd w:id="0"/>']),
            ]),
        ]);

        [, $parts] = $packageParts((new DocxWriter())->write($document));
        $documentXml = $parts['word/document.xml'];

        $t->contains('<w:bookmarkStart w:id="11" w:name="a-section-for-testing-link-targets"/>', $documentXml);
        $t->contains('<w:hyperlink w:anchor="a-section-for-testing-link-targets">', $documentXml);
        $t->contains('<w:bookmarkStart w:id="0" w:name="Aliquam"/>', $documentXml);
        $t->contains('<w:bookmarkEnd w:id="0"/>', $documentXml);
        $t->true(!str_contains($documentXml, '&lt;w:bookmarkStart'), 'Raw bookmark was XML-escaped');
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
        $t->contains('<w:t>quoted paragraph</w:t>', $documentXml);
        $t->contains('<w:numId w:val="1"/>', $documentXml);
        $t->contains('<w:t>quoted bullet</w:t>', $documentXml);
        $t->same('blockquote', $roundTrip->children[0]->type);
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

        $t->contains('<w:num w:numId="10"><w:abstractNumId w:val="2"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="1"/></w:lvlOverride></w:num>', $numberingXml);
        $t->contains('<w:num w:numId="12"><w:abstractNumId w:val="2"/><w:lvlOverride w:ilvl="0"><w:startOverride w:val="4"/></w:lvlOverride></w:num>', $numberingXml);
        $t->contains('<w:numFmt w:val="lowerLetter"/>', $numberingXml);
        $t->contains('<w:numFmt w:val="upperRoman"/>', $numberingXml);
        $t->contains('<w:lvlText w:val="(%1)"/>', $numberingXml);
        $t->contains('<w:numId w:val="10"/>', $documentXml);
        $t->contains('<w:numId w:val="11"/>', $documentXml);
        $t->contains('<w:numId w:val="12"/>', $documentXml);
        $t->contains('<w:numId w:val="13"/>', $documentXml);
        $t->contains('<w:numId w:val="3"/></w:numPr></w:pPr><w:r><w:t>continuation</w:t></w:r>', $documentXml);
        $t->contains('<w:numId w:val="1"/></w:numPr></w:pPr></w:p>', $documentXml);
        $t->contains('<w:t>nested first</w:t>', $documentXml);
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

        $t->contains('<w:num w:numId="4"><w:abstractNumId w:val="4"/></w:num>', $numberingXml);
        $t->contains('<w:num w:numId="5"><w:abstractNumId w:val="5"/></w:num>', $numberingXml);
        $t->contains('<w:lvlText w:val="&#9744;"/>', $numberingXml);
        $t->contains('<w:lvlText w:val="&#9746;"/>', $numberingXml);
        $t->contains('<w:numId w:val="4"/></w:numPr></w:pPr><w:r><w:t>Unchecked</w:t></w:r>', $documentXml);
        $t->contains('<w:numId w:val="5"/></w:numPr></w:pPr><w:r><w:t>Checked</w:t></w:r>', $documentXml);
        $t->contains('<w:numId w:val="3"/></w:numPr></w:pPr><w:r><w:t>with continuation</w:t></w:r>', $documentXml);
        $t->contains('<w:numId w:val="5"/></w:numPr></w:pPr><w:r><w:t>Checked sublist</w:t></w:r>', $documentXml);
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
