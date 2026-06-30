<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxWriter;
use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcRelationship;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$doc = static fn (array $blocks): AstNode => new AstNode('document', [], $blocks);
$text = static fn (string $value): AstNode => new AstNode('text', ['text' => $value]);
$paragraph = static fn (array $children): AstNode => new AstNode('paragraph', [], $children);
$plain = static fn (array $children): AstNode => new AstNode('plain', [], $children);
$item = static fn (array $children): AstNode => new AstNode('list_item', [], $children);

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
        $document = $doc([
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
        ]);

        $writer = new DocxWriter();
        $firstBytes = $writer->write($document);
        $secondBytes = $writer->write($document);
        [$package, $parts] = $packageParts($firstBytes);

        $t->same(hash('sha256', $firstBytes), hash('sha256', $secondBytes));
        $t->same([
            '[Content_Types].xml',
            '_rels/.rels',
            'word/document.xml',
            'word/_rels/document.xml.rels',
            'word/styles.xml',
            'word/numbering.xml',
            'word/settings.xml',
        ], array_map(static fn ($entry): string => $entry->name, $package->entries()));

        $contentTypes = OpcContentTypes::fromXml($parts['[Content_Types].xml']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $contentTypes->defaults()['rels'] ?? null);
        $t->same('application/xml', $contentTypes->defaults()['xml'] ?? null);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $contentTypes->contentTypeForPart('/word/document.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $contentTypes->contentTypeForPart('/word/styles.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml', $contentTypes->contentTypeForPart('/word/numbering.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml', $contentTypes->contentTypeForPart('/word/settings.xml'));

        $rootRels = OpcRelationships::fromXml($parts['_rels/.rels'], '/');
        $rootDocument = $rootRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument');
        $t->true($rootDocument instanceof OpcRelationship, 'Root officeDocument relationship missing');
        $t->same('word/document.xml', $rootDocument?->target);

        $documentRels = OpcRelationships::fromXml($parts['word/_rels/document.xml.rels'], '/word/document.xml');
        $t->same('styles.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles')?->target);
        $t->same('numbering.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering')?->target);
        $t->same('settings.xml', $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings')?->target);
        $hyperlink = $documentRels->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink');
        $t->true($hyperlink instanceof OpcRelationship, 'External hyperlink relationship missing');
        $t->same('rId4', $hyperlink?->id);
        $t->same('https://example.test/audit?x=1&y=2', $hyperlink?->target);
        $t->same(OpcRelationship::TARGET_MODE_EXTERNAL, $hyperlink?->targetMode);

        $documentXml = $parts['word/document.xml'];
        $t->contains('<w:pStyle w:val="Heading1"/>', $documentXml);
        $t->contains('<w:b/>', $documentXml);
        $t->contains('<w:i/>', $documentXml);
        $t->contains('<w:t xml:space="preserve">  tail</w:t>', $documentXml);
        $t->contains('<w:numId w:val="1"/>', $documentXml);
        $t->contains('<w:numId w:val="10"/>', $documentXml);
        $t->contains('<w:hyperlink r:id="rId4">', $documentXml);
        $t->contains('<w:sectPr>', $documentXml);

        $t->contains('w:styleId="Heading1"', $parts['word/styles.xml']);
        $t->contains('w:styleId="Hyperlink"', $parts['word/styles.xml']);
        $t->contains('<w:startOverride w:val="3"/>', $parts['word/numbering.xml']);
        $t->contains('<w:settings', $parts['word/settings.xml']);
        $t->contains('<w:compatSetting', $parts['word/settings.xml']);
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
            'word/document.xml',
            'word/_rels/document.xml.rels',
            'word/styles.xml',
            'word/numbering.xml',
            'word/settings.xml',
        ], array_column($parts, 'name'));
        foreach ($parts as $part) {
            $t->same(0, $part['modifiedDosTime']);
            $t->same(33, $part['modifiedDosDate']);
        }
    },
];
