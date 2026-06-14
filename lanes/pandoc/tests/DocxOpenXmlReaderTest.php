<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\MarkdownWriter;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

return [
    'maps docx core properties styles hyperlinks numbering and media from package parts' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_openxml_reader_fixture_parts());

        $meta = $document->attr('meta');
        $docx = $document->attr('docx');
        $heading = $document->children[0];
        $paragraph = $document->children[1];
        $ordered = $document->children[2];
        $bullet = $document->children[3];
        $imageParagraph = $document->children[4];
        $table = $document->children[5];

        $t->same('Imported DOCX Batch', $meta['title']);
        $t->same(['Migration Editor'], $meta['authors']);
        $t->same('word/document.xml', $docx['documentPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $docx['contentTypes']['overrides']['word/document.xml']);
        $t->same('docProps/core.xml', $docx['corePropertiesPart']);
        $t->same('word/styles.xml', $docx['stylesPart']);
        $t->same('rCore', $docx['corePropertiesRelationship']['id']);
        $t->same('docProps/core.xml', $docx['corePropertiesRelationship']['targetPart']);
        $t->true(!isset($docx['stylesRelationship']), 'conventional DOCX styles fallback should not invent relationship metadata');
        $t->same('word/numbering.xml', $docx['numberingPart']);
        $t->true(!isset($docx['numberingRelationship']), 'conventional DOCX numbering fallback should not invent relationship metadata');
        $t->same('image/png', $docx['media']['word/media/review.png']['contentType']);
        $t->same(strlen('fake png bytes'), $docx['media']['word/media/review.png']['size']);

        $t->same('heading', $heading->type);
        $t->same(1, $heading->attr('level'));
        $t->same('imported-docx-heading', $heading->attr('id'));
        $t->same('Heading 1', $heading->attr('docxStyleName'));
        $t->same('strong', $paragraph->children[1]->type);
        $t->same('emph', $paragraph->children[3]->type);
        $t->same('underline', $paragraph->children[5]->type);
        $t->same('strikeout', $paragraph->children[7]->type);
        $t->same('superscript', $paragraph->children[9]->type);
        $t->same('link', $paragraph->children[11]->type);
        $t->same('https://example.test/source?post=42', $paragraph->children[11]->attr('url'));
        $t->same('rLink', $paragraph->children[11]->attr('relationshipId'));

        $t->same('ordered_list', $ordered->type);
        $t->same(3, $ordered->attr('start'));
        $t->same('upper_roman', $ordered->attr('style'));
        $t->same('one_paren', $ordered->attr('delimiter'));
        $t->same('First review step', $ordered->children[0]->children[0]->attr('text'));
        $t->same('Second review step', $ordered->children[1]->children[0]->attr('text'));
        $t->same('bullet_list', $bullet->type);
        $t->same('•', $bullet->attr('bulletChar'));
        $t->same('Bullet media note', $bullet->children[0]->children[0]->attr('text'));

        $image = $imageParagraph->children[1];
        $t->same('image', $image->type);
        $t->same('word/media/review.png', $image->attr('url'));
        $t->same('Review screenshot', $image->attr('alt'));
        $t->same('Review image', $image->attr('title'));
        $t->same('image/png', $image->attr('contentType'));
        $t->same('table', $table->type);
        $t->same('Reviewer', $table->children[0]->children[0]->children[0]->attr('text'));
        $t->same(2, $table->children[0]->children[0]->children[1]->attr('colspan'));
    },
    'exposes docx package content type relationship and part inventory provenance' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/customXml/item1.xml" ContentType="application/xml"/>' . "\n" .
            '  <Override PartName="/word/missing-comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            'Target="word/document.xml"',
            'Target="word/document.xml?doc=main#body"',
            $parts['_rels/.rels']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml?slot=1#payload"/>' . "\n" .
            '  <Relationship Id="rRemote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/reference.xml?x=1#frag" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['customXml/item1.xml'] = '<root/>';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $rootRelationshipsPart = $package['relationshipParts']['_rels/.rels'];
        $documentRelationshipsPart = $package['relationshipParts']['word/_rels/document.xml.rels'];
        $customXmlRelationship = $documentRelationshipsPart['relationships']['rCustomXml'];
        $remoteRelationship = $documentRelationshipsPart['relationships']['rRemote'];
        $inventory = $package['parts'];

        $t->same('[Content_Types].xml', $contentTypesPart['partName']);
        $t->same(true, $contentTypesPart['exists']);
        $t->same(strlen($parts['[Content_Types].xml']), $contentTypesPart['bytes']);
        $t->same('application/xml', $contentTypesPart['defaults']['xml']['contentType']);
        $t->same('application/xml', $contentTypesPart['overrides']['customXml/item1.xml']['contentType']);
        $t->same(true, $contentTypesPart['overrides']['customXml/item1.xml']['exists']);
        $t->same(false, $contentTypesPart['overrides']['word/missing-comments.xml']['exists']);

        $t->same('word/document.xml', $package['documentPart']);
        $t->same('/', $rootRelationshipsPart['sourcePart']);
        $t->same(true, $rootRelationshipsPart['exists']);
        $t->same('word/document.xml?doc=main#body', $rootRelationshipsPart['relationships']['rDoc']['resolvedTarget']);
        $t->same('word/document.xml', $rootRelationshipsPart['relationships']['rDoc']['targetPart']);
        $t->same('?doc=main#body', $rootRelationshipsPart['relationships']['rDoc']['targetReferenceSuffix']);
        $t->same(true, $rootRelationshipsPart['relationships']['rDoc']['exists']);
        $t->same('override', $rootRelationshipsPart['relationships']['rDoc']['contentTypeSource']);
        $t->same('word/document.xml', $documentRelationshipsPart['sourcePart']);
        $t->same(true, $documentRelationshipsPart['exists']);
        $t->same(4, $documentRelationshipsPart['relationshipCount']);
        $t->same('customXml/item1.xml?slot=1#payload', $customXmlRelationship['resolvedTarget']);
        $t->same('customXml/item1.xml', $customXmlRelationship['targetPart']);
        $t->same('?slot=1#payload', $customXmlRelationship['targetReferenceSuffix']);
        $t->same('slot=1', $customXmlRelationship['targetQuery']);
        $t->same('payload', $customXmlRelationship['targetFragment']);
        $t->same(true, $customXmlRelationship['exists']);
        $t->same('override', $customXmlRelationship['contentTypeSource']);
        $t->same(true, $remoteRelationship['external']);
        $t->same(null, $remoteRelationship['targetPart']);
        $t->same('x=1', $remoteRelationship['targetQuery']);
        $t->same('frag', $remoteRelationship['targetFragment']);
        $t->same('?x=1#frag', $remoteRelationship['targetReferenceSuffix']);
        $t->same(false, $remoteRelationship['exists']);

        $t->same('office-document', $inventory['word/document.xml']['roles'][0]);
        $t->true(in_array('root-relationship-target', $inventory['word/document.xml']['roles'], true), 'document root relationship role missing');
        $t->same('word/document.xml', $inventory['word/_rels/document.xml.rels']['relationshipSourcePart']);
        $t->same(true, $inventory['word/_rels/document.xml.rels']['isRelationshipPart']);
        $t->same('default', $inventory['word/_rels/document.xml.rels']['contentTypeSource']);
        $t->true(in_array('document-relationship-target', $inventory['customXml/item1.xml']['roles'], true), 'custom XML relationship role missing');
        $t->same('override', $inventory['customXml/item1.xml']['contentTypeSource']);
        $t->same('package-part', $inventory['word/styles.xml']['roles'][0]);
    },
    'preserves docx source zip entry provenance across package ingestion' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $zipParts = [
            ['name' => 'word/media/', 'data' => '', 'compressionMethod' => 0],
        ];
        foreach ($parts as $name => $data) {
            $zipParts[] = [
                'name' => $name,
                'data' => $data,
                'compressionMethod' => $name === '[Content_Types].xml' ? 0 : 8,
            ];
        }

        $zip = ZipPackage::fromParts($zipParts, 'docx zip provenance fixture');
        $document = (new DocxOpenXmlReader())->readZipPackage($zip);
        $package = $document->attr('docx')['packageProvenance'];
        $zipPackage = $package['zipPackage'];
        $inventory = $package['parts'];
        $summary = $package['summary'];
        $orderNames = array_column($zipParts, 'name');
        $methodBuckets = [];
        foreach ($summary['zipCompressionMethods'] as $bucket) {
            $methodBuckets[$bucket['compressionMethod']] = $bucket;
        }
        $deflatedDocument = gzdeflate($parts['word/document.xml']);

        $t->true(is_string($deflatedDocument), 'fixture document XML should deflate');
        $t->same(true, $zipPackage['present']);
        $t->same(count($parts) + 1, $zipPackage['entryCount']);
        $t->same(count($parts), $zipPackage['fileEntryCount']);
        $t->same(1, $zipPackage['directoryEntryCount']);
        $t->same(count($parts), $zipPackage['loadedPartCount']);
        $t->same(0, $zipPackage['unsupportedCompressionMethodCount']);
        $t->same(true, $zipPackage['centralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same($orderNames, $zipPackage['centralDirectoryOrderNames']);
        $t->same($orderNames, $zipPackage['localHeaderOrderNames']);
        $t->same(['word/media/'], $zipPackage['directoryPackagePaths']);
        $t->true(in_array('word/document.xml', $zipPackage['loadedPartNames'], true), 'document part missing from zip loaded parts');
        $t->same('docx-zip-entry-metadata-only', $zipPackage['byteExposurePolicy']);
        $t->same(false, $zipPackage['canExposeBytes']);

        $directory = $zipPackage['byPackagePath']['word/media/'];
        $t->same(true, $directory['isDirectory']);
        $t->same(null, $directory['partName']);
        $t->same(false, $directory['loadedPart']);
        $t->same(['zip-directory'], $directory['roles']);
        $t->true(!isset($inventory['word/media/']), 'directory entries must stay out of loaded DOCX parts');

        $contentTypesEntry = $zipPackage['byPackagePath']['[Content_Types].xml'];
        $t->same(0, $contentTypesEntry['compressionMethod']);
        $t->same('stored', $contentTypesEntry['compressionMethodName']);
        $t->same(true, $contentTypesEntry['loadedPart']);
        $t->true(in_array('content-types', $contentTypesEntry['roles'], true), 'content-types ZIP role missing');
        $t->same(sprintf('%08x', crc32($parts['[Content_Types].xml'])), $contentTypesEntry['crc32']);

        $documentEntry = $zipPackage['byPackagePath']['word/document.xml'];
        $t->same(8, $documentEntry['compressionMethod']);
        $t->same('deflated', $documentEntry['compressionMethodName']);
        $t->same(strlen($parts['word/document.xml']), $documentEntry['byteLength']);
        $t->same(strlen($deflatedDocument), $documentEntry['compressedByteLength']);
        $t->same(sprintf('%08x', crc32($parts['word/document.xml'])), $documentEntry['crc32']);
        $t->same(true, $documentEntry['matchesCentralDirectoryOrder']);

        $t->same(true, $inventory['word/document.xml']['zipEntryPresent']);
        $t->same($documentEntry['centralDirectoryIndex'], $inventory['word/document.xml']['centralDirectoryIndex']);
        $t->same($documentEntry['localHeaderOrder'], $inventory['word/document.xml']['localHeaderOrder']);
        $t->same($documentEntry['localHeaderOffset'], $inventory['word/document.xml']['localHeaderOffset']);
        $t->same(8, $inventory['word/document.xml']['compressionMethod']);
        $t->same('deflated', $inventory['word/document.xml']['compressionMethodName']);
        $t->same(strlen($deflatedDocument), $inventory['word/document.xml']['compressedByteLength']);
        $t->same($documentEntry['crc32'], $inventory['word/document.xml']['zipCrc32']);
        $t->same('docx-zip-entry-metadata-only', $inventory['word/document.xml']['zipByteExposurePolicy']);
        $t->same(false, $inventory['word/document.xml']['zipCanExposeBytes']);

        $t->same(true, $summary['zipPackagePresent']);
        $t->same(count($parts) + 1, $summary['zipEntryCount']);
        $t->same(count($parts), $summary['zipFileEntryCount']);
        $t->same(1, $summary['zipDirectoryEntryCount']);
        $t->same(count($parts), $summary['zipLoadedPartCount']);
        $t->same(0, $summary['zipUnsupportedCompressionMethodCount']);
        $t->same(true, $summary['zipCentralDirectoryOrderMatchesLocalHeaderOrder']);
        $t->same(2, $methodBuckets[0]['entryCount']);
        $t->same(count($parts) - 1, $methodBuckets[8]['entryCount']);
    },
    'preserves docx package inventory CRC32 provenance for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['customXml/raw-review.bin'] = 'raw custom payload bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $inventory = $package['parts'];
        $summary = $package['summary'];

        $t->same(sprintf('%08x', crc32($parts['[Content_Types].xml'])), $inventory['[Content_Types].xml']['crc32']);
        $t->same(sprintf('%08x', crc32($parts['_rels/.rels'])), $inventory['_rels/.rels']['crc32']);
        $t->same(sprintf('%08x', crc32($parts['word/document.xml'])), $inventory['word/document.xml']['crc32']);
        $t->same(sprintf('%08x', crc32($parts['word/_rels/document.xml.rels'])), $inventory['word/_rels/document.xml.rels']['crc32']);
        $t->same(sprintf('%08x', crc32($parts['word/media/review.png'])), $inventory['word/media/review.png']['crc32']);
        $t->same(sprintf('%08x', crc32($parts['customXml/raw-review.bin'])), $inventory['customXml/raw-review.bin']['crc32']);

        $t->same('missing', $inventory['customXml/raw-review.bin']['contentTypeSource']);
        $t->same('bin', $inventory['customXml/raw-review.bin']['defaultExtension']);
        $t->same(['package-part'], $inventory['customXml/raw-review.bin']['roles']);
        $t->same(1, $summary['missingContentTypePartCount']);
        $t->same('customXml/raw-review.bin', $summary['partsWithoutContentType'][0]['partName']);
        $t->same(strlen($parts['customXml/raw-review.bin']), $summary['partsWithoutContentType'][0]['bytes']);
        $t->same($inventory['customXml/raw-review.bin']['crc32'], $summary['partsWithoutContentType'][0]['crc32']);
    },
    'preserves docx package inventory SHA-256 provenance for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['customXml/raw-review.bin'] = str_repeat('B', 20000);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $inventory = $package['parts'];
        $summary = $package['summary'];
        $largest = $summary['largestParts'][0];
        $missingContentType = $summary['partsWithoutContentType'][0];

        $t->same(hash('sha256', $parts['[Content_Types].xml']), $inventory['[Content_Types].xml']['sha256']);
        $t->same(hash('sha256', $parts['_rels/.rels']), $inventory['_rels/.rels']['sha256']);
        $t->same(hash('sha256', $parts['word/document.xml']), $inventory['word/document.xml']['sha256']);
        $t->same(hash('sha256', $parts['word/_rels/document.xml.rels']), $inventory['word/_rels/document.xml.rels']['sha256']);
        $t->same(hash('sha256', $parts['word/media/review.png']), $inventory['word/media/review.png']['sha256']);
        $t->same(hash('sha256', $parts['customXml/raw-review.bin']), $inventory['customXml/raw-review.bin']['sha256']);

        $t->same('customXml/raw-review.bin', $largest['partName']);
        $t->same(20000, $largest['bytes']);
        $t->same($inventory['customXml/raw-review.bin']['sha256'], $largest['sha256']);
        $t->same($largest, $summary['largestPart']);
        $t->same('customXml/raw-review.bin', $missingContentType['partName']);
        $t->same($inventory['customXml/raw-review.bin']['sha256'], $missingContentType['sha256']);
    },
    'summarizes largest docx package parts for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/media/full-resolution-review.png'] = str_repeat('P', 20000);
        $parts['customXml/raw-review.bin'] = str_repeat('B', 15000);

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $largestParts = $summary['largestParts'];

        $t->same(array_sum(array_map('strlen', $parts)), $summary['packageByteLength']);
        $t->same(5, $summary['largestPartCount']);
        $t->same('word/media/full-resolution-review.png', $summary['largestPartName']);
        $t->same(20000, $summary['largestPartBytes']);
        $t->same($largestParts[0], $summary['largestPart']);

        $t->same('word/media/full-resolution-review.png', $largestParts[0]['partName']);
        $t->same('word/media', $largestParts[0]['directory']);
        $t->same('full-resolution-review.png', $largestParts[0]['baseName']);
        $t->same('png', $largestParts[0]['partExtension']);
        $t->same(20000, $largestParts[0]['bytes']);
        $t->same(sprintf('%08x', crc32($parts['word/media/full-resolution-review.png'])), $largestParts[0]['crc32']);
        $t->same('image/png', $largestParts[0]['contentType']);
        $t->same('image/png', $largestParts[0]['contentTypeBase']);
        $t->same('default', $largestParts[0]['contentTypeSource']);
        $t->same('png', $largestParts[0]['defaultExtension']);
        $t->same(null, $largestParts[0]['overridePartName']);
        $t->same(false, $largestParts[0]['isRelationshipPart']);
        $t->same(['package-part'], $largestParts[0]['roles']);

        $t->same('customXml/raw-review.bin', $largestParts[1]['partName']);
        $t->same('customXml', $largestParts[1]['directory']);
        $t->same('raw-review.bin', $largestParts[1]['baseName']);
        $t->same('bin', $largestParts[1]['partExtension']);
        $t->same(15000, $largestParts[1]['bytes']);
        $t->same(sprintf('%08x', crc32($parts['customXml/raw-review.bin'])), $largestParts[1]['crc32']);
        $t->same('', $largestParts[1]['contentType']);
        $t->same('', $largestParts[1]['contentTypeBase']);
        $t->same('missing', $largestParts[1]['contentTypeSource']);
        $t->same('bin', $largestParts[1]['defaultExtension']);
        $t->same(['package-part'], $largestParts[1]['roles']);
    },
    'summarizes docx package part directories for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['customXml/raw-review.bin'] = 'raw custom payload bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $directories = $summary['partDirectories'];
        $byDirectory = [];
        foreach ($directories as $directory) {
            $byDirectory[$directory['directory']] = $directory;
        }

        $t->same(7, $summary['partDirectoryCount']);
        $t->same(['/', '_rels', 'customXml', 'docProps', 'word', 'word/_rels', 'word/media'], array_column($directories, 'directory'));
        $t->same('/', $inventory['[Content_Types].xml']['directory']);
        $t->same('[Content_Types].xml', $inventory['[Content_Types].xml']['baseName']);
        $t->same('word', $inventory['word/document.xml']['directory']);
        $t->same('document.xml', $inventory['word/document.xml']['baseName']);
        $t->same('customXml', $inventory['customXml/raw-review.bin']['directory']);
        $t->same('raw-review.bin', $inventory['customXml/raw-review.bin']['baseName']);

        $t->same(1, $byDirectory['/']['partCount']);
        $t->same(['[Content_Types].xml'], $byDirectory['/']['partNames']);
        $t->same(['content-types' => 1], $byDirectory['/']['roleCounts']);
        $t->same(['default' => 1], $byDirectory['/']['contentTypeSourceCounts']);

        $t->same(1, $byDirectory['_rels']['partCount']);
        $t->same(1, $byDirectory['_rels']['relationshipPartCount']);
        $t->same(['package-relationships' => 1, 'relationship-part' => 1], $byDirectory['_rels']['roleCounts']);

        $t->same(3, $byDirectory['word']['partCount']);
        $t->same(
            strlen($parts['word/styles.xml']) + strlen($parts['word/numbering.xml']) + strlen($parts['word/document.xml']),
            $byDirectory['word']['byteLength']
        );
        $t->same(['word/styles.xml', 'word/numbering.xml', 'word/document.xml'], $byDirectory['word']['partNames']);
        $t->same(['default' => 2, 'override' => 1], $byDirectory['word']['contentTypeSourceCounts']);
        $t->same(['office-document' => 1, 'package-part' => 2, 'root-relationship-target' => 1], $byDirectory['word']['roleCounts']);

        $t->same(1, $byDirectory['word/_rels']['partCount']);
        $t->same(1, $byDirectory['word/_rels']['relationshipPartCount']);
        $t->same(['office-document-relationships' => 1, 'relationship-part' => 1], $byDirectory['word/_rels']['roleCounts']);

        $t->same(1, $byDirectory['word/media']['partCount']);
        $t->same(['document-relationship-target' => 1], $byDirectory['word/media']['roleCounts']);
        $t->same(['default' => 1], $byDirectory['word/media']['contentTypeSourceCounts']);

        $t->same(1, $byDirectory['customXml']['partCount']);
        $t->same(1, $byDirectory['customXml']['missingContentTypePartCount']);
        $t->same(['missing' => 1], $byDirectory['customXml']['contentTypeSourceCounts']);
        $t->same(['package-part' => 1], $byDirectory['customXml']['roleCounts']);
    },
    'summarizes docx package part extensions for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Default Extension="TIFF" ContentType="image/tiff; profile=scan"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/media/scan.TIFF'] = 'scan tiff bytes';
        $parts['customXml/raw-review.bin'] = 'raw custom payload bytes';
        $parts['customXml/no-extension'] = 'extensionless payload';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $byExtension = [];
        foreach ($summary['partExtensions'] as $extension) {
            $byExtension[$extension['extension'] ?? '(none)'] = $extension;
        }

        $t->same(6, $summary['partExtensionCount']);
        $t->same('xml', $inventory['[Content_Types].xml']['partExtension']);
        $t->same(true, $inventory['[Content_Types].xml']['partExtensionDefaultDeclared']);
        $t->same('application/xml', $inventory['[Content_Types].xml']['partExtensionDefaultContentType']);
        $t->same('rels', $inventory['_rels/.rels']['partExtension']);
        $t->same(true, $inventory['_rels/.rels']['partExtensionDefaultDeclared']);
        $t->same('tiff', $inventory['word/media/scan.TIFF']['partExtension']);
        $t->same(true, $inventory['word/media/scan.TIFF']['partExtensionDefaultDeclared']);
        $t->same('image/tiff; profile=scan', $inventory['word/media/scan.TIFF']['partExtensionDefaultContentType']);
        $t->same('image/tiff; profile=scan', $inventory['word/media/scan.TIFF']['contentType']);
        $t->same('bin', $inventory['customXml/raw-review.bin']['partExtension']);
        $t->same(false, $inventory['customXml/raw-review.bin']['partExtensionDefaultDeclared']);
        $t->same(null, $inventory['customXml/raw-review.bin']['partExtensionDefaultContentType']);
        $t->same(null, $inventory['customXml/no-extension']['partExtension']);
        $t->same(false, $inventory['customXml/no-extension']['partExtensionDefaultDeclared']);

        $t->same(5, $byExtension['xml']['partCount']);
        $t->same(0, $byExtension['xml']['missingContentTypePartCount']);
        $t->same(['default' => 3, 'override' => 2], $byExtension['xml']['contentTypeSourceCounts']);
        $t->true(in_array('word/document.xml', $byExtension['xml']['partNames'], true), 'document XML extension bucket missing main part');

        $t->same(2, $byExtension['rels']['partCount']);
        $t->same(2, $byExtension['rels']['relationshipPartCount']);
        $t->same(['default' => 2], $byExtension['rels']['contentTypeSourceCounts']);
        $t->same(['office-document-relationships' => 1, 'package-relationships' => 1, 'relationship-part' => 2], $byExtension['rels']['roleCounts']);

        $t->same(1, $byExtension['tiff']['partCount']);
        $t->same(true, $byExtension['tiff']['defaultDeclared']);
        $t->same('image/tiff; profile=scan', $byExtension['tiff']['defaultContentType']);
        $t->same(['default' => 1], $byExtension['tiff']['contentTypeSourceCounts']);
        $t->same(['word/media/scan.TIFF'], $byExtension['tiff']['partNames']);

        $t->same(1, $byExtension['bin']['partCount']);
        $t->same(false, $byExtension['bin']['defaultDeclared']);
        $t->same(1, $byExtension['bin']['missingContentTypePartCount']);
        $t->same(['missing' => 1], $byExtension['bin']['contentTypeSourceCounts']);
        $t->same(['customXml/raw-review.bin'], $byExtension['bin']['partNames']);

        $t->same(1, $byExtension['(none)']['partCount']);
        $t->same(null, $byExtension['(none)']['extension']);
        $t->same(false, $byExtension['(none)']['defaultDeclared']);
        $t->same(1, $byExtension['(none)']['missingContentTypePartCount']);
        $t->same(['customXml/no-extension'], $byExtension['(none)']['partNames']);
    },
    'preserves docx content type parameters across package provenance' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            [
                '<Default Extension="xml" ContentType="application/xml"/>',
                '<Default Extension="png" ContentType="image/png"/>',
                '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>',
            ],
            [
                '<Default Extension="xml" ContentType="application/xml; charset=UTF-8; profile=package-default"/>',
                '<Default Extension="png" ContentType="image/png; profile=media-default"/>',
                '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml; charset=&quot;utf-8&quot;; profile=main-doc"/>',
            ],
            $parts['[Content_Types].xml']
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $xmlDefault = $contentTypesPart['defaults']['xml'];
        $pngDefault = $contentTypesPart['defaults']['png'];
        $documentOverride = $contentTypesPart['overrides']['word/document.xml'];
        $rootDocumentRelationship = $package['relationshipParts']['_rels/.rels']['relationships']['rDoc'];
        $documentInventory = $package['parts']['word/document.xml'];
        $mediaInventory = $package['parts']['word/media/review.png'];
        $mediaRelationship = $package['relationshipParts']['word/_rels/document.xml.rels']['relationships']['rImage'];
        $image = $document->children[4]->children[1];

        $t->same(true, $contentTypesPart['valid']);
        $t->same(3, $contentTypesPart['parameterizedContentTypeCount']);
        $t->same(['default', 'default', 'override'], array_column($contentTypesPart['parameterizedContentTypes'], 'kind'));
        $t->same('application/xml; charset=UTF-8; profile=package-default', $xmlDefault['contentType']);
        $t->same('application/xml', $xmlDefault['contentTypeBase']);
        $t->same(true, $xmlDefault['contentTypeHasParameters']);
        $t->same(2, $xmlDefault['contentTypeParameterCount']);
        $t->same('charset', $xmlDefault['contentTypeParameters'][0]['name']);
        $t->same('UTF-8', $xmlDefault['contentTypeParameters'][0]['value']);
        $t->same(['charset' => 'UTF-8', 'profile' => 'package-default'], $xmlDefault['contentTypeParameterMap']);
        $t->same('image/png', $pngDefault['contentTypeBase']);
        $t->same(['profile' => 'media-default'], $pngDefault['contentTypeParameterMap']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml; charset="utf-8"; profile=main-doc', $documentOverride['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $documentOverride['contentTypeBase']);
        $t->same(['charset' => 'utf-8', 'profile' => 'main-doc'], $documentOverride['contentTypeParameterMap']);

        $t->same($documentOverride['contentType'], $rootDocumentRelationship['contentType']);
        $t->same($documentOverride['contentTypeBase'], $rootDocumentRelationship['contentTypeBase']);
        $t->same(true, $rootDocumentRelationship['contentTypeHasParameters']);
        $t->same(2, $rootDocumentRelationship['contentTypeParameterCount']);
        $t->same(['charset' => 'utf-8', 'profile' => 'main-doc'], $rootDocumentRelationship['contentTypeParameterMap']);
        $t->same($documentOverride['contentTypeBase'], $docx['packageProvenance']['parts']['word/document.xml']['contentTypeBase']);
        $t->same($rootDocumentRelationship['contentTypeParameterMap'], $documentInventory['contentTypeParameterMap']);
        $t->same('override', $documentInventory['contentTypeSource']);

        $t->same('image/png; profile=media-default', $mediaInventory['contentType']);
        $t->same('image/png', $mediaInventory['contentTypeBase']);
        $t->same(['profile' => 'media-default'], $mediaInventory['contentTypeParameterMap']);
        $t->same('image/png', $mediaRelationship['contentTypeBase']);
        $t->same(['profile' => 'media-default'], $mediaRelationship['contentTypeParameterMap']);
        $t->same('image/png; profile=media-default', $docx['media']['word/media/review.png']['contentType']);
        $t->same('image/png; profile=media-default', $image->attr('contentType'));
    },
    'reports docx content type declaration collisions without aborting package ingestion' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Default Extension="xml" ContentType="application/xml"/>',
            '  <Default Extension="XML" ContentType="application/vnd.review+xml"/>' . "\n" .
            '  <Default Extension="xml" ContentType="application/xml"/>' . "\n" .
            '  <Default Extension="bad" ContentType="not a mime"/>',
            $parts['[Content_Types].xml']
        );
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>',
            '  <Override PartName="word/malformed-relative.xml" ContentType="text/html"/>' . "\n" .
            '  <Override PartName="/WORD/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' . "\n" .
            '  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/missing-preview.xml" ContentType=""/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $preflight = $contentTypesPart['preflight'];
        $summary = $package['summary'];
        $invalidRecords = $contentTypesPart['invalidContentTypeRecords'];

        $t->same('Imported DOCX Batch', $document->attr('meta')['title']);
        $t->same('word/document.xml', $docx['documentPart']);
        $t->same('Imported DOCX Heading', $document->children[0]->attr('text'));
        $t->same('override', $package['relationshipParts']['_rels/.rels']['relationships']['rDoc']['contentTypeSource']);
        $t->same('default', $package['relationshipParts']['word/_rels/document.xml.rels']['relationships']['rImage']['contentTypeSource']);
        $t->same(4, $summary['relationshipCount']);
        $t->same(4, $summary['relationshipRecordCount']);
        $t->same(6, $summary['contentTypeSourceCounts']['default']);
        $t->same(2, $summary['contentTypeSourceCounts']['override']);
        $t->same(false, $contentTypesPart['valid']);
        $t->same(false, $preflight['valid']);
        $t->same(10, $contentTypesPart['recordCount']);
        $t->same(5, $contentTypesPart['declaredDefaultRecordCount']);
        $t->same(5, $contentTypesPart['declaredOverrideRecordCount']);
        $t->same(5, $preflight['defaultCount']);
        $t->same(5, $preflight['overrideCount']);
        $t->same(7, $contentTypesPart['invalidRecordCount']);
        $t->same(1, $contentTypesPart['duplicateDefaultExtensionCount']);
        $t->same(1, $contentTypesPart['duplicateOverridePartNameCount']);
        $t->same(['xml'], $contentTypesPart['duplicateDefaultExtensions']);
        $t->same(['/word/document.xml'], $contentTypesPart['duplicateOverridePartNames']);
        $t->same(['XML', 'xml'], $contentTypesPart['duplicateDefaultExtensionGroups']['xml']);
        $t->same(['/WORD/document.xml', '/word/document.xml'], $contentTypesPart['duplicateOverridePartNameGroups']['/word/document.xml']);
        $t->same([
            'duplicate-default-extension' => 2,
            'duplicate-override-part-name' => 2,
            'invalid-content-type' => 1,
            'invalid-override-part-name' => 1,
            'missing-content-type' => 1,
        ], $contentTypesPart['issueCounts']);
        $t->same($contentTypesPart['issueCounts'], $contentTypesPart['invalidContentTypeRecordIssueCounts']);
        $t->same([
            'duplicate-default-extension',
            'duplicate-override-part-name',
            'invalid-content-type',
            'invalid-override-part-name',
            'missing-content-type',
        ], $contentTypesPart['invalidContentTypeRecordIssueCodes']);
        $t->same('application/xml', $contentTypesPart['defaults']['xml']['contentType']);
        $t->same('not a mime', $contentTypesPart['defaults']['bad']['contentType']);
        $t->same(true, $contentTypesPart['overrides']['word/document.xml']['exists']);
        $t->same(false, $contentTypesPart['overrides']['word/malformed-relative.xml']['exists']);
        $t->same('', $contentTypesPart['overrides']['word/missing-preview.xml']['contentType']);
        $t->same([1, 2, 3, 5, 6, 7, 9], array_column($invalidRecords, 'recordIndex'));
        $t->same(['Default', 'Default', 'Default', 'Override', 'Override', 'Override', 'Override'], array_column($invalidRecords, 'kind'));
        $t->same(['duplicate-default-extension'], $invalidRecords[0]['issues']);
        $t->same(['duplicate-default-extension'], $invalidRecords[1]['issues']);
        $t->same(['invalid-content-type'], $invalidRecords[2]['issues']);
        $t->same(['invalid-override-part-name'], $invalidRecords[3]['issues']);
        $t->same('word/malformed-relative.xml', $invalidRecords[3]['partName']);
        $t->same(null, $invalidRecords[3]['normalizedPartName']);
        $t->same(['duplicate-override-part-name'], $invalidRecords[4]['issues']);
        $t->same(['duplicate-override-part-name'], $invalidRecords[5]['issues']);
        $t->same(['missing-content-type'], $invalidRecords[6]['issues']);
        $t->same('/word/missing-preview.xml', $invalidRecords[6]['partName']);
        $t->same('/word/document.xml', $contentTypesPart['invalidContentTypeRecordIssueBuckets']['duplicate-override-part-name'][1]['normalizedPartName']);

        $t->same($contentTypesPart['recordCount'], $summary['contentTypeRecordCount']);
        $t->same($contentTypesPart['invalidRecordCount'], $summary['contentTypeInvalidRecordCount']);
        $t->same($contentTypesPart['declaredDefaultRecordCount'], $summary['contentTypeDeclaredDefaultRecordCount']);
        $t->same($contentTypesPart['declaredOverrideRecordCount'], $summary['contentTypeDeclaredOverrideRecordCount']);
        $t->same($contentTypesPart['duplicateDefaultExtensionCount'], $summary['contentTypeDuplicateDefaultExtensionCount']);
        $t->same($contentTypesPart['duplicateOverridePartNameCount'], $summary['contentTypeDuplicateOverridePartNameCount']);
        $t->same($contentTypesPart['duplicateDefaultExtensions'], $summary['contentTypeDuplicateDefaultExtensions']);
        $t->same($contentTypesPart['duplicateOverridePartNames'], $summary['contentTypeDuplicateOverridePartNames']);
        $t->same($contentTypesPart['duplicateDefaultExtensionGroups'], $summary['contentTypeDuplicateDefaultExtensionGroups']);
        $t->same($contentTypesPart['duplicateOverridePartNameGroups'], $summary['contentTypeDuplicateOverridePartNameGroups']);
        $t->same($contentTypesPart['issueCounts'], $summary['contentTypeRecordIssueCounts']);
        $t->same($contentTypesPart['invalidContentTypeRecordIssueBuckets'], $summary['invalidContentTypeRecordIssueBuckets']);
        $t->same($invalidRecords, $summary['invalidContentTypeRecords']);
    },
    'summarizes docx content type override declarations for package review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/override-source.xml" ContentType="application/xml; profile=exact-review"/>' . "\n" .
            '  <Override PartName="/word/missing-preview.xml" ContentType="application/xml"/>' . "\n" .
            '  <Override PartName="/word/_rels/missing-preview.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n" .
            '  <Override PartName="/word/not-relationships.xml" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>' . "\n" .
            '  <Override PartName="/[Content_Types].xml" ContentType="application/xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/override-source.xml'] = '<review/>';
        $parts['word/not-relationships.xml'] = '<not-rels/>';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $contentTypesPart = $package['contentTypesPart'];
        $summary = $package['summary'];
        $declarations = [];
        foreach ($contentTypesPart['overrideDeclarations'] as $declaration) {
            $declarations[$declaration['partName']] = $declaration;
        }

        $t->same('Imported DOCX Batch', $document->attr('meta')['title']);
        $t->same(7, $contentTypesPart['overrideDeclarationCount']);
        $t->same(5, $contentTypesPart['usedOverrideDeclarationCount']);
        $t->same(2, $contentTypesPart['unusedOverrideDeclarationCount']);
        $t->same(4, $contentTypesPart['invalidOverrideDeclarationCount']);
        $t->same(['word/_rels/missing-preview.xml.rels', 'word/missing-preview.xml'], $contentTypesPart['unusedOverridePartNames']);
        $t->same([
            'content-types-override-target' => 1,
            'override-target-missing-part' => 2,
            'relationship-content-type-on-non-relationship-part' => 1,
            'relationship-override-source-missing' => 1,
        ], $contentTypesPart['overrideDeclarationIssueCounts']);
        $t->same([
            'content-types-override-target',
            'override-target-missing-part',
            'relationship-content-type-on-non-relationship-part',
            'relationship-override-source-missing',
        ], $contentTypesPart['overrideDeclarationIssues']);

        $t->same(7, $summary['contentTypeOverrideDeclarationCount']);
        $t->same(5, $summary['contentTypeUsedOverrideDeclarationCount']);
        $t->same(2, $summary['contentTypeUnusedOverrideDeclarationCount']);
        $t->same(4, $summary['contentTypeInvalidOverrideDeclarationCount']);
        $t->same($contentTypesPart['unusedOverridePartNames'], $summary['contentTypeUnusedOverridePartNames']);
        $t->same($contentTypesPart['overrideDeclarationIssueCounts'], $summary['contentTypeOverrideDeclarationIssueCounts']);

        $t->same('exact', $declarations['word/override-source.xml']['matchKind']);
        $t->same(true, $declarations['word/override-source.xml']['exists']);
        $t->same('application/xml', $declarations['word/override-source.xml']['contentTypeBase']);
        $t->same([], $declarations['word/override-source.xml']['issues']);

        $t->same('missing', $declarations['word/missing-preview.xml']['matchKind']);
        $t->same(false, $declarations['word/missing-preview.xml']['exists']);
        $t->same(['override-target-missing-part'], $declarations['word/missing-preview.xml']['issues']);

        $t->same(true, $declarations['word/_rels/missing-preview.xml.rels']['relationshipPart']);
        $t->same('word/missing-preview.xml', $declarations['word/_rels/missing-preview.xml.rels']['relationshipSource']);
        $t->same(false, $declarations['word/_rels/missing-preview.xml.rels']['relationshipSourceExists']);
        $t->same([
            'override-target-missing-part',
            'relationship-override-source-missing',
        ], $declarations['word/_rels/missing-preview.xml.rels']['issues']);

        $t->same(false, $declarations['word/not-relationships.xml']['relationshipPart']);
        $t->same(['relationship-content-type-on-non-relationship-part'], $declarations['word/not-relationships.xml']['issues']);
        $t->same(['content-types-override-target'], $declarations['[Content_Types].xml']['issues']);
    },
    'indexes docx relationship parts beyond root and document relationships' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/header1.xml'] = '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header</w:t></w:r></w:p></w:hdr>';
        $parts['word/_rels/header1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHeaderImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/header.png?slot=header#logo"/>
  <Relationship Id="rHeaderRemote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/header-review" TargetMode="External"/>
</Relationships>
XML;
        $parts['word/media/header.png'] = 'header png bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $headerRelationshipsPart = $package['relationshipParts']['word/_rels/header1.xml.rels'];
        $headerImage = $headerRelationshipsPart['relationships']['rHeaderImage'];
        $headerRemote = $headerRelationshipsPart['relationships']['rHeaderRemote'];
        $inventory = $package['parts'];

        $t->same(3, count($package['relationshipParts']));
        $t->same('word/_rels/header1.xml.rels', $headerRelationshipsPart['partName']);
        $t->same('word/header1.xml', $headerRelationshipsPart['sourcePart']);
        $t->same(true, $headerRelationshipsPart['sourceExists']);
        $t->same(true, $headerRelationshipsPart['exists']);
        $t->same(2, $headerRelationshipsPart['relationshipCount']);
        $t->same('media/header.png?slot=header#logo', $headerImage['target']);
        $t->same('word/media/header.png?slot=header#logo', $headerImage['resolvedTarget']);
        $t->same('word/media/header.png', $headerImage['targetPart']);
        $t->same('slot=header', $headerImage['targetQuery']);
        $t->same('logo', $headerImage['targetFragment']);
        $t->same('?slot=header#logo', $headerImage['targetReferenceSuffix']);
        $t->same(true, $headerImage['exists']);
        $t->same('image/png', $headerImage['contentType']);
        $t->same('default', $headerImage['contentTypeSource']);
        $t->same(true, $headerRemote['external']);
        $t->same(null, $headerRemote['targetPart']);
        $t->same(false, $headerRemote['exists']);
        $t->same('word/header1.xml', $inventory['word/_rels/header1.xml.rels']['relationshipSourcePart']);
        $t->same(true, $inventory['word/_rels/header1.xml.rels']['relationshipSourceExists']);
        $t->true(in_array('relationship-part', $inventory['word/_rels/header1.xml.rels']['roles'], true), 'header relationship part role missing');
        $t->true(in_array('relationship-target', $inventory['word/media/header.png']['roles'], true), 'header image relationship target role missing');
    },
    'summarizes docx header and footer parts from document relationships' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>' . "\n" .
            '  <Override PartName="/word/footer1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rHeaderDefault" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml?slot=default#hdr"/>' . "\n" .
            '  <Relationship Id="rFooterFirst" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            '  </w:body>',
            '    <w:sectPr>' . "\n" .
            '      <w:headerReference w:type="default" r:id="rHeaderDefault"/>' . "\n" .
            '      <w:footerReference w:type="first" r:id="rFooterFirst"/>' . "\n" .
            '    </w:sectPr>' . "\n" .
            '  </w:body>',
            $parts['word/document.xml']
        );
        $parts['word/header1.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:p>
    <w:r><w:t xml:space="preserve">Review header </w:t></w:r>
    <w:hyperlink r:id="rHeaderLink"><w:r><w:t>portal</w:t></w:r></w:hyperlink>
  </w:p>
  <w:p>
    <w:r>
      <w:drawing>
        <wp:inline>
          <wp:docPr id="7" name="Header logo" title="Header logo" descr="Header logo"/>
          <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rHeaderImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
        </wp:inline>
      </w:drawing>
    </w:r>
  </w:p>
</w:hdr>
XML;
        $parts['word/_rels/header1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHeaderLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/header-portal" TargetMode="External"/>
  <Relationship Id="rHeaderImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/header-logo.png"/>
</Relationships>
XML;
        $parts['word/footer1.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:ftr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:p><w:r><w:t>Footer page label</w:t></w:r></w:p>
</w:ftr>
XML;
        $parts['word/media/header-logo.png'] = 'header logo bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $header = $docx['headers']['byRelationshipId']['rHeaderDefault'];
        $footer = $docx['footers']['byRelationshipId']['rFooterFirst'];
        $headerImage = $docx['packageProvenance']['relationshipParts']['word/_rels/header1.xml.rels']['relationships']['rHeaderImage'];
        $inventory = $docx['packageProvenance']['parts'];

        $t->same(1, $docx['headers']['count']);
        $t->same(1, $docx['headers']['existingCount']);
        $t->same(0, $docx['headers']['missingCount']);
        $t->same(1, $docx['headers']['referencedCount']);
        $t->same(['rHeaderDefault'], $docx['headers']['relationshipIds']);
        $t->same(['word/header1.xml'], $docx['headers']['partNames']);
        $t->same('header', $header['sourceType']);
        $t->same(true, $header['referenced']);
        $t->same(['default'], $header['referenceTypes']);
        $t->same(true, $header['exists']);
        $t->same(true, $header['validRoot']);
        $t->same('hdr', $header['rootName']);
        $t->same(2, $header['blockCount']);
        $t->same('Review header portal Header logo', $header['text']);
        $t->same('word/_rels/header1.xml.rels', $header['relationshipsPart']);
        $t->same(2, $header['relationshipCount']);
        $t->same('rHeaderDefault', $header['relationship']['id']);
        $t->same('word/document.xml', $header['relationship']['sourcePart']);
        $t->same('word/header1.xml', $header['relationship']['targetPart']);
        $t->same('slot=default', $header['relationship']['targetQuery']);
        $t->same('hdr', $header['relationship']['targetFragment']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml', $header['relationship']['contentType']);
        $t->same('override', $header['relationship']['contentTypeSource']);
        $t->true(in_array('header-part', $inventory['word/header1.xml']['roles'], true), 'header inventory role missing');
        $t->same('word/media/header-logo.png', $headerImage['targetPart']);
        $t->same(true, $headerImage['exists']);

        $t->same(1, $docx['footers']['count']);
        $t->same(1, $docx['footers']['existingCount']);
        $t->same(0, $docx['footers']['missingCount']);
        $t->same(1, $docx['footers']['referencedCount']);
        $t->same(['rFooterFirst'], $docx['footers']['relationshipIds']);
        $t->same('footer', $footer['sourceType']);
        $t->same(['first'], $footer['referenceTypes']);
        $t->same(true, $footer['validRoot']);
        $t->same('ftr', $footer['rootName']);
        $t->same(1, $footer['blockCount']);
        $t->same('Footer page label', $footer['text']);
        $t->same('word/footer1.xml', $footer['relationship']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml', $footer['relationship']['contentType']);
        $t->same('override', $footer['relationship']['contentTypeSource']);
        $t->true(in_array('footer-part', $inventory['word/footer1.xml']['roles'], true), 'footer inventory role missing');
    },
    'preserves docx header and footer reference issue provenance' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/footers/missing.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rHeaderExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="https://example.test/header.xml?slot=even#hdr" TargetMode="External"/>' . "\n" .
            '  <Relationship Id="rFooterMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footers/missing.xml?slot=first#ftr"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            '  </w:body>',
            '    <w:sectPr>' . "\n" .
            '      <w:headerReference w:type="even" r:id="rHeaderExternal"/>' . "\n" .
            '      <w:headerReference w:type="first" r:id="rMissingHeader"/>' . "\n" .
            '      <w:headerReference r:id="rImage"/>' . "\n" .
            '      <w:footerReference w:type="first" r:id="rFooterMissing"/>' . "\n" .
            '    </w:sectPr>' . "\n" .
            '  </w:body>',
            $parts['word/document.xml']
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $headers = $docx['headers'];
        $footers = $docx['footers'];
        $summary = $docx['packageProvenance']['summary'];
        $headerExternal = $headers['byRelationshipId']['rHeaderExternal'];
        $headerMissing = $headers['byRelationshipId']['rMissingHeader'];
        $headerWrongType = $headers['byRelationshipId']['rImage'];
        $footerMissing = $footers['byRelationshipId']['rFooterMissing'];

        $t->same(3, $headers['count']);
        $t->same(0, $headers['existingCount']);
        $t->same(0, $headers['missingCount']);
        $t->same(1, $headers['externalCount']);
        $t->same(3, $headers['referencedCount']);
        $t->same(1, $headers['unresolvedCount']);
        $t->same(1, $headers['unexpectedRelationshipTypeCount']);
        $t->same(3, $headers['issueCount']);
        $t->same(['rHeaderExternal', 'rMissingHeader', 'rImage'], $headers['relationshipIds']);
        $t->same(['external-header', 'unexpected-relationship-type', 'unknown-relationship'], $headers['issueCodes']);
        $t->same(['https://example.test/header.xml?slot=even#hdr'], $headers['externalTargets']);

        $t->same('header', $headerExternal['sourceType']);
        $t->same(['even'], $headerExternal['referenceTypes']);
        $t->same(true, $headerExternal['external']);
        $t->same(null, $headerExternal['partName']);
        $t->same('slot=even', $headerExternal['targetQuery']);
        $t->same('hdr', $headerExternal['targetFragment']);
        $t->same('?slot=even#hdr', $headerExternal['targetReferenceSuffix']);
        $t->same(['external-header'], $headerExternal['issues']);

        $t->same(['first'], $headerMissing['referenceTypes']);
        $t->same(null, $headerMissing['relationship']);
        $t->same(['unknown-relationship'], $headerMissing['issues']);

        $t->same(['default'], $headerWrongType['referenceTypes']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $headerWrongType['relationshipType']);
        $t->same('word/media/review.png', $headerWrongType['partName']);
        $t->same(true, $headerWrongType['exists']);
        $t->same(['unexpected-relationship-type'], $headerWrongType['issues']);

        $t->same(1, $footers['count']);
        $t->same(0, $footers['existingCount']);
        $t->same(1, $footers['missingCount']);
        $t->same(0, $footers['externalCount']);
        $t->same(1, $footers['referencedCount']);
        $t->same(0, $footers['unresolvedCount']);
        $t->same(1, $footers['issueCount']);
        $t->same(['missing-in-package'], $footers['issueCodes']);
        $t->same('word/footers/missing.xml', $footerMissing['partName']);
        $t->same('slot=first', $footerMissing['targetQuery']);
        $t->same('ftr', $footerMissing['targetFragment']);
        $t->same('override', $footerMissing['relationship']['contentTypeSource']);
        $t->same(['missing-in-package'], $footerMissing['issues']);

        $t->same(3, $summary['headerPartCount']);
        $t->same(1, $summary['headerExternalTargetCount']);
        $t->same(1, $summary['headerUnresolvedReferenceCount']);
        $t->same(3, $summary['headerIssueCount']);
        $t->same(['external-header', 'unexpected-relationship-type', 'unknown-relationship'], $summary['headerIssueCodes']);
        $t->same(1, $summary['footerPartCount']);
        $t->same(1, $summary['footerMissingPartCount']);
        $t->same(1, $summary['footerIssueCount']);
    },
    'preserves docx section property review metadata' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rHeaderEven" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header-even.xml"/>' . "\n" .
            '  <Relationship Id="rFooterDefault" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer-default.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            '  </w:body>',
            '    <w:sectPr>' . "\n" .
            '      <w:type w:val="continuous"/>' . "\n" .
            '      <w:headerReference w:type="even" r:id="rHeaderEven"/>' . "\n" .
            '      <w:footerReference r:id="rFooterDefault"/>' . "\n" .
            '      <w:footerReference w:type="odd"/>' . "\n" .
            '      <w:pgSz w:w="16838" w:h="11906" w:orient="landscape" w:code="9"/>' . "\n" .
            '      <w:pgMar w:top="720" w:right="900" w:bottom="720" w:left="900" w:header="360" w:footer="360" w:gutter="0"/>' . "\n" .
            '      <w:cols w:num="2" w:space="360" w:sep="1"/>' . "\n" .
            '      <w:pgNumType w:start="3" w:fmt="decimal"/>' . "\n" .
            '      <w:docGrid w:type="lines" w:linePitch="360" w:charSpace="0"/>' . "\n" .
            '      <w:titlePg/>' . "\n" .
            '    </w:sectPr>' . "\n" .
            '  </w:body>',
            $parts['word/document.xml']
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $sections = $docx['sections'];
        $section = $sections['items'][0];
        $summary = $docx['packageProvenance']['summary'];

        $t->same(1, $sections['count']);
        $t->same(['continuous'], $sections['types']);
        $t->same(['landscape'], $sections['orientations']);
        $t->same(1, $sections['headerReferenceCount']);
        $t->same(2, $sections['footerReferenceCount']);
        $t->same(1, $sections['landscapeCount']);
        $t->same(1, $sections['titlePageCount']);
        $t->same(1, $sections['columnLayoutCount']);
        $t->same(2, $sections['issueCount']);
        $t->same(['invalid-footer-reference-type', 'missing-footer-reference-id'], $sections['issueCodes']);

        $t->same('continuous', $section['type']);
        $t->same('rHeaderEven', $section['headerReferences'][0]['relationshipId']);
        $t->same('even', $section['headerReferences'][0]['type']);
        $t->same('rFooterDefault', $section['footerReferences'][0]['relationshipId']);
        $t->same('default', $section['footerReferences'][0]['type']);
        $t->same('', $section['footerReferences'][1]['relationshipId']);
        $t->same('odd', $section['footerReferences'][1]['type']);
        $t->same(['missing-footer-reference-id', 'invalid-footer-reference-type'], $section['footerReferences'][1]['issues']);
        $t->same(16838, $section['pageSize']['widthTwips']);
        $t->same(11906, $section['pageSize']['heightTwips']);
        $t->same('landscape', $section['pageSize']['orientation']);
        $t->same(9, $section['pageSize']['code']);
        $t->same(720, $section['pageMargins']['topTwips']);
        $t->same(900, $section['pageMargins']['rightTwips']);
        $t->same(2, $section['columns']['count']);
        $t->same(360, $section['columns']['spaceTwips']);
        $t->same(true, $section['columns']['separator']);
        $t->same(3, $section['pageNumbering']['start']);
        $t->same('decimal', $section['pageNumbering']['format']);
        $t->same('lines', $section['documentGrid']['type']);
        $t->same(360, $section['documentGrid']['linePitch']);
        $t->same(true, $section['titlePage']);
        $t->same(true, $section['landscape']);

        $t->same(1, $summary['sectionCount']);
        $t->same(1, $summary['sectionHeaderReferenceCount']);
        $t->same(2, $summary['sectionFooterReferenceCount']);
        $t->same(1, $summary['sectionLandscapeCount']);
        $t->same(1, $summary['sectionTitlePageCount']);
        $t->same(1, $summary['sectionColumnLayoutCount']);
        $t->same(2, $summary['sectionIssueCount']);
        $t->same(['invalid-footer-reference-type', 'missing-footer-reference-id'], $summary['sectionIssueCodes']);
    },
    'flags docx relationship sidecars whose source part is missing' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/_rels/missing-header.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rOrphanImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/orphan.png"/>
</Relationships>
XML;
        $parts['word/media/orphan.png'] = 'orphan image bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $relationshipPart = $package['relationshipParts']['word/_rels/missing-header.xml.rels'];
        $inventory = $package['parts'];

        $t->same('word/missing-header.xml', $relationshipPart['sourcePart']);
        $t->same(false, $relationshipPart['sourceExists']);
        $t->same(true, $relationshipPart['exists']);
        $t->same('word/media/orphan.png', $relationshipPart['relationships']['rOrphanImage']['targetPart']);
        $t->same(true, $relationshipPart['relationships']['rOrphanImage']['exists']);
        $t->same('word/missing-header.xml', $inventory['word/_rels/missing-header.xml.rels']['relationshipSourcePart']);
        $t->same(false, $inventory['word/_rels/missing-header.xml.rels']['relationshipSourceExists']);
        $t->true(in_array('relationship-target', $inventory['word/media/orphan.png']['roles'], true), 'orphan relationship target role missing');
    },
    'summarizes docx orphan relationship sidecars for package review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/_rels/missing-review.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rOrphanImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/orphan.png?source=sidecar#img"/>
  <Relationship Id="rOrphanLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/orphan-sidecar" TargetMode="External"/>
</Relationships>
XML;
        $parts['word/media/orphan.png'] = 'orphan sidecar image bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $relationshipPart = $package['relationshipParts']['word/_rels/missing-review.xml.rels'];
        $orphanImage = $relationshipPart['relationships']['rOrphanImage'];
        $orphanLink = $relationshipPart['relationships']['rOrphanLink'];

        $t->same(1, $summary['relationshipPartMissingSourceCount']);
        $t->same(['word/_rels/missing-review.xml.rels'], $summary['relationshipPartsWithMissingSources']);
        $t->same('word/_rels/missing-review.xml.rels', $summary['relationshipsFromMissingSources'][0]['relationshipsPart']);
        $t->same('word/missing-review.xml', $summary['relationshipsFromMissingSources'][0]['sourcePart']);
        $t->same(2, $summary['relationshipsFromMissingSources'][0]['relationshipCount']);
        $t->same('word/missing-review.xml', $relationshipPart['sourcePart']);
        $t->same(false, $relationshipPart['sourceExists']);
        $t->same(2, $relationshipPart['relationshipCount']);
        $t->same('word/media/orphan.png', $orphanImage['targetPart']);
        $t->same('source=sidecar', $orphanImage['targetQuery']);
        $t->same('img', $orphanImage['targetFragment']);
        $t->same(true, $orphanImage['exists']);
        $t->same(true, $orphanLink['external']);
        $t->same(null, $orphanLink['targetPart']);
    },
    'summarizes docx missing-source relationship targets for package review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/_rels/missing-review.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rOrphanImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/orphan.png?source=sidecar#img"/>
  <Relationship Id="rOrphanMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="media/missing-orphan.bin?missing=1#raw"/>
  <Relationship Id="rOrphanExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/orphan-sidecar?remote=1#link" TargetMode="External"/>
</Relationships>
XML;
        $parts['word/media/orphan.png'] = 'orphan image bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $missingSource = $summary['relationshipsFromMissingSources'][0];
        $relationships = $missingSource['relationships'];

        $t->same(1, $summary['relationshipPartMissingSourceCount']);
        $t->same(3, $summary['relationshipFromMissingSourceCount']);
        $t->same('word/_rels/missing-review.xml.rels', $missingSource['relationshipsPart']);
        $t->same('word/missing-review.xml', $missingSource['sourcePart']);
        $t->same(false, $missingSource['sourceExists']);
        $t->same(3, $missingSource['relationshipCount']);
        $t->same(3, $missingSource['relationshipRecordCount']);
        $t->same(2, $missingSource['internalTargetCount']);
        $t->same(1, $missingSource['externalTargetCount']);
        $t->same(1, $missingSource['existingTargetCount']);
        $t->same(1, $missingSource['missingTargetCount']);
        $t->same(1, $missingSource['missingContentTypeCount']);
        $t->same(['rOrphanImage', 'rOrphanMissing', 'rOrphanExternal'], $missingSource['relationshipIds']);
        $t->same(['word/media/orphan.png', 'word/media/missing-orphan.bin'], $missingSource['targetParts']);
        $t->same(['word/media/orphan.png'], $missingSource['existingTargetParts']);
        $t->same(['word/media/missing-orphan.bin'], $missingSource['missingTargetParts']);
        $t->same(['word/media/missing-orphan.bin'], $missingSource['missingContentTypeTargetParts']);
        $t->same(['https://example.test/orphan-sidecar?remote=1#link'], $missingSource['externalTargets']);
        $t->same(['?source=sidecar#img', '?missing=1#raw', '?remote=1#link'], $missingSource['targetReferenceSuffixes']);

        $t->same('rOrphanImage', $relationships[0]['id']);
        $t->same('word/media/orphan.png', $relationships[0]['targetPart']);
        $t->same('source=sidecar', $relationships[0]['targetQuery']);
        $t->same('img', $relationships[0]['targetFragment']);
        $t->same(true, $relationships[0]['exists']);
        $t->same('image/png', $relationships[0]['contentType']);
        $t->same('default', $relationships[0]['contentTypeSource']);

        $t->same('rOrphanMissing', $relationships[1]['id']);
        $t->same('word/media/missing-orphan.bin', $relationships[1]['targetPart']);
        $t->same(false, $relationships[1]['exists']);
        $t->same('missing', $relationships[1]['contentTypeSource']);
        $t->same('bin', $relationships[1]['defaultExtension']);

        $t->same('rOrphanExternal', $relationships[2]['id']);
        $t->same(true, $relationships[2]['external']);
        $t->same(null, $relationships[2]['targetPart']);
        $t->same('remote=1', $relationships[2]['targetQuery']);
        $t->same('link', $relationships[2]['targetFragment']);
    },
    'summarizes docx relationships by type for package review' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Default Extension="png" ContentType="image/png"/>',
            '  <Default Extension="png" ContentType="image/png; profile=relationship-type-summary"/>' . "\n" .
            '  <Default Extension="mp3" ContentType="audio/mpeg"/>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rPreview" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="https://example.test/review.png?kind=thumb#cover" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['_rels/.rels']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png?review=missing#img"/>' . "\n" .
            '  <Relationship Id="rNarration" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/audio" Target="media/narration.mp3"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/header1.xml'] = '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header</w:t></w:r></w:p></w:hdr>';
        $parts['word/_rels/header1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHeaderImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/header.png"/>
</Relationships>
XML;
        $parts['word/media/header.png'] = 'header png bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $types = $document->attr('docx')['packageProvenance']['relationshipTypes'];
        $imageType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $hyperlinkType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
        $audioType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/audio';
        $thumbnailType = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail';
        $image = $types[$imageType];
        $audio = $types[$audioType];
        $hyperlink = $types[$hyperlinkType];
        $thumbnail = $types[$thumbnailType];

        $t->same('image', $image['label']);
        $t->same(3, $image['count']);
        $t->same(3, $image['internalCount']);
        $t->same(0, $image['externalCount']);
        $t->same(2, $image['existingTargetCount']);
        $t->same(1, $image['missingTargetCount']);
        $t->true(in_array('word/_rels/document.xml.rels', $image['relationshipParts'], true), 'document image relationship bucket missing');
        $t->true(in_array('word/_rels/header1.xml.rels', $image['relationshipParts'], true), 'header image relationship bucket missing');
        $t->true(in_array('word/media/review.png', $image['existingTargetParts'], true), 'existing document image target missing');
        $t->true(in_array('word/media/header.png', $image['existingTargetParts'], true), 'existing header image target missing');
        $t->same(['word/media/missing.png'], $image['missingTargetParts']);
        $t->same('rMissingImage', $image['relationships'][1]['id']);
        $t->same(false, $image['relationships'][1]['exists']);
        $t->same('media/missing.png?review=missing#img', $image['relationships'][1]['target']);
        $t->same('review=missing', $image['relationships'][1]['targetQuery']);
        $t->same('img', $image['relationships'][1]['targetFragment']);
        $t->same('image/png; profile=relationship-type-summary', $image['relationships'][1]['contentType']);
        $t->same('image/png', $image['relationships'][1]['contentTypeBase']);
        $t->same(true, $image['relationships'][1]['contentTypeHasParameters']);
        $t->same(1, $image['relationships'][1]['contentTypeParameterCount']);
        $t->same(['profile' => 'relationship-type-summary'], $image['relationships'][1]['contentTypeParameterMap']);
        $t->same('default', $image['relationships'][1]['contentTypeSource']);
        $t->same('png', $image['relationships'][1]['defaultExtension']);
        $t->same(null, $image['relationships'][1]['overridePartName']);

        $t->same('audio', $audio['label']);
        $t->same(1, $audio['missingTargetCount']);
        $t->same(['word/media/narration.mp3'], $audio['missingTargetParts']);
        $t->same(['audio/mpeg'], $audio['contentTypes']);
        $t->same('rNarration', $audio['relationships'][0]['id']);

        $t->same(1, $hyperlink['externalCount']);
        $t->same(['https://example.test/source?post=42'], $hyperlink['externalTargets']);
        $t->same(null, $hyperlink['relationships'][0]['targetPart']);
        $t->same('?post=42', $hyperlink['relationships'][0]['targetReferenceSuffix']);
        $t->same('post=42', $hyperlink['relationships'][0]['targetQuery']);
        $t->same(null, $hyperlink['relationships'][0]['targetFragment']);

        $t->same('thumbnail', $thumbnail['label']);
        $t->same(1, $thumbnail['externalCount']);
        $t->same(['https://example.test/review.png?kind=thumb#cover'], $thumbnail['externalTargets']);
        $t->same(['_rels/.rels'], $thumbnail['relationshipParts']);
        $t->same('?kind=thumb#cover', $thumbnail['relationships'][0]['targetReferenceSuffix']);
        $t->same('kind=thumb', $thumbnail['relationships'][0]['targetQuery']);
        $t->same('cover', $thumbnail['relationships'][0]['targetFragment']);
    },
    'summarizes docx relationship target path shape for package review' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSelfDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="document.xml?self=1#source"/>' . "\n" .
            '  <Relationship Id="rCoreParent" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="../docProps/core.xml?audit=up#core"/>' . "\n" .
            '  <Relationship Id="rAbsoluteImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="/word/media/review.png?absolute=1#root"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/header/header1.xml'] = '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header package path audit</w:t></w:r></w:p></w:hdr>';
        $parts['word/header/_rels/header1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHeaderParentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/review.png?scope=header#media"/>
  <Relationship Id="rHeaderSelf" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="header1.xml#self"/>
</Relationships>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $documentRelationships = $package['relationshipParts']['word/_rels/document.xml.rels']['relationships'];
        $headerRelationships = $package['relationshipParts']['word/header/_rels/header1.xml.rels']['relationships'];
        $selfDocument = $documentRelationships['rSelfDocument'];
        $coreParent = $documentRelationships['rCoreParent'];
        $absoluteImage = $documentRelationships['rAbsoluteImage'];
        $headerParent = $headerRelationships['rHeaderParentImage'];
        $headerSelf = $headerRelationships['rHeaderSelf'];

        $t->same(2, $summary['relationshipTargetParentTraversalCount']);
        $t->same(2, $summary['relationshipTargetParentTraversalSegmentCount']);
        $t->same(2, $summary['sameSourceRelationshipCount']);
        $t->same(['word/_rels/document.xml.rels', 'word/header/_rels/header1.xml.rels'], $summary['relationshipPartsWithParentTraversalTargets']);
        $t->same(['word/_rels/document.xml.rels', 'word/header/_rels/header1.xml.rels'], $summary['relationshipPartsWithSameSourceTargets']);
        $t->same(['rCoreParent', 'rHeaderParentImage'], array_column($summary['relationshipTargetsWithParentTraversal'], 'id'));
        $t->same(['rSelfDocument', 'rHeaderSelf'], array_column($summary['relationshipsWithSameSourceTargets'], 'id'));

        $t->same('word/document.xml', $selfDocument['targetPart']);
        $t->same(true, $selfDocument['sameSourcePart']);
        $t->same(0, $selfDocument['targetParentTraversalCount']);
        $t->same(false, $selfDocument['targetHasParentTraversal']);
        $t->same(false, $selfDocument['targetStartsAtPackageRoot']);

        $t->same('../docProps/core.xml?audit=up#core', $coreParent['target']);
        $t->same('docProps/core.xml', $coreParent['targetPart']);
        $t->same(1, $coreParent['targetParentTraversalCount']);
        $t->same(true, $coreParent['targetHasParentTraversal']);
        $t->same(false, $coreParent['sameSourcePart']);
        $t->same('?audit=up#core', $coreParent['targetReferenceSuffix']);

        $t->same('/word/media/review.png?absolute=1#root', $absoluteImage['target']);
        $t->same('word/media/review.png', $absoluteImage['targetPart']);
        $t->same(true, $absoluteImage['targetStartsAtPackageRoot']);
        $t->same(false, $absoluteImage['targetHasParentTraversal']);

        $t->same('word/media/review.png', $headerParent['targetPart']);
        $t->same(1, $headerParent['targetParentTraversalCount']);
        $t->same(true, $headerParent['targetHasParentTraversal']);
        $t->same(false, $headerParent['sameSourcePart']);
        $t->same('word/header/header1.xml', $headerSelf['targetPart']);
        $t->same(true, $headerSelf['sameSourcePart']);

        $imageType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $coreType = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties';
        $customXmlType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
        $hyperlinkType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';
        $t->same(1, $package['relationshipTypes'][$imageType]['parentTraversalTargetCount']);
        $t->same(0, $package['relationshipTypes'][$imageType]['sameSourceTargetCount']);
        $t->same(1, $package['relationshipTypes'][$coreType]['parentTraversalTargetCount']);
        $t->same(1, $package['relationshipTypes'][$customXmlType]['sameSourceTargetCount']);
        $t->same(1, $package['relationshipTypes'][$hyperlinkType]['sameSourceTargetCount']);
        $t->same(true, $package['relationshipTypes'][$imageType]['relationships'][1]['targetStartsAtPackageRoot']);
        $t->same(true, $package['relationshipTypes'][$imageType]['relationships'][2]['targetHasParentTraversal']);
    },
    'reports docx package thumbnail provenance as metadata only' => static function (TestRunner $t): void {
        $thumbnailType = 'http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail';
        $thumbnailBytes = 'jpeg thumbnail bytes';
        $badThumbnailBytes = '<not-image/>';
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Default Extension="png" ContentType="image/png"/>',
            '  <Default Extension="png" ContentType="image/png"/>' . "\n" .
            '  <Default Extension="jpeg" ContentType="image/jpeg; profile=package-thumbnail"/>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rPackageThumb" Type="' . $thumbnailType . '" Target="docProps/thumbnail-review.jpeg?size=small#cover"/>' . "\n" .
            '  <Relationship Id="rMissingThumb" Type="' . $thumbnailType . '" Target="docProps/missing-thumbnail.png"/>' . "\n" .
            '  <Relationship Id="rExternalThumb" Type="' . $thumbnailType . '" Target="https://example.test/thumb.png?review=1#preview" TargetMode="External"/>' . "\n" .
            '  <Relationship Id="rBadThumb" Type="' . $thumbnailType . '" Target="docProps/bad-thumbnail.xml"/>' . "\n" .
            '</Relationships>',
            $parts['_rels/.rels']
        );
        $parts['docProps/thumbnail-review.jpeg'] = $thumbnailBytes;
        $parts['docProps/bad-thumbnail.xml'] = $badThumbnailBytes;
        $parts['docProps/_rels/thumbnail-review.jpeg.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rThumbAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="thumbnail-audit.png"/>
</Relationships>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $thumbnails = $package['packageThumbnails'];
        $items = $thumbnails['byRelationshipId'];
        $packageThumb = $items['rPackageThumb'];
        $missingThumb = $items['rMissingThumb'];
        $externalThumb = $items['rExternalThumb'];
        $badThumb = $items['rBadThumb'];
        $relationshipType = $package['relationshipTypes'][$thumbnailType];

        $t->same($thumbnails, $docx['packageThumbnails']);
        $t->same(4, $thumbnails['count']);
        $t->same(2, $thumbnails['readableCount']);
        $t->same(1, $thumbnails['missingCount']);
        $t->same(1, $thumbnails['externalCount']);
        $t->same(4, $thumbnails['invalidCount']);
        $t->same(4, $thumbnails['issueCount']);
        $t->same([
            'external-thumbnail-target',
            'invalid-thumbnail-content-type',
            'missing-in-package',
            'multiple-thumbnail-relationships-for-source',
            'thumbnail-target-has-relationships',
        ], $thumbnails['issueCodes']);
        $t->same(['rPackageThumb', 'rMissingThumb', 'rExternalThumb', 'rBadThumb'], $thumbnails['relationshipIds']);
        $t->same(['docProps/thumbnail-review.jpeg', 'docProps/missing-thumbnail.png', 'docProps/bad-thumbnail.xml'], $thumbnails['targetParts']);
        $t->same(['https://example.test/thumb.png?review=1#preview'], $thumbnails['externalTargets']);
        $t->same(['image/jpeg; profile=package-thumbnail', 'image/png', 'application/xml'], $thumbnails['contentTypes']);

        $t->same('docProps/thumbnail-review.jpeg?size=small#cover', $packageThumb['target']);
        $t->same('docProps/thumbnail-review.jpeg?size=small#cover', $packageThumb['resolvedTarget']);
        $t->same('docProps/thumbnail-review.jpeg', $packageThumb['targetPart']);
        $t->same('?size=small#cover', $packageThumb['targetReferenceSuffix']);
        $t->same('size=small', $packageThumb['targetQuery']);
        $t->same('cover', $packageThumb['targetFragment']);
        $t->same('image/jpeg; profile=package-thumbnail', $packageThumb['contentType']);
        $t->same('image/jpeg', $packageThumb['contentTypeBase']);
        $t->same(true, $packageThumb['contentTypeHasParameters']);
        $t->same(['profile' => 'package-thumbnail'], $packageThumb['contentTypeParameterMap']);
        $t->same(false, $packageThumb['external']);
        $t->same(true, $packageThumb['exists']);
        $t->same(strlen($thumbnailBytes), $packageThumb['byteLength']);
        $t->same(sprintf('%08x', crc32($thumbnailBytes)), $packageThumb['crc32']);
        $t->same(null, $packageThumb['storedByteLength']);
        $t->same(null, $packageThumb['storedCrc32']);
        $t->same(false, $packageThumb['canExposeAsDocumentMedia']);
        $t->same('package-thumbnail-metadata-only', $packageThumb['reviewPolicy']);
        $t->same('docProps/_rels/thumbnail-review.jpeg.rels', $packageThumb['targetRelationshipsPart']);
        $t->same(true, $packageThumb['targetHasRelationships']);
        $t->same(false, $packageThumb['valid']);
        $t->same(['multiple-thumbnail-relationships-for-source', 'thumbnail-target-has-relationships'], $packageThumb['issues']);

        $t->same('docProps/missing-thumbnail.png', $missingThumb['targetPart']);
        $t->same('image/png', $missingThumb['contentType']);
        $t->same(false, $missingThumb['exists']);
        $t->same(null, $missingThumb['byteLength']);
        $t->same(['multiple-thumbnail-relationships-for-source', 'missing-in-package'], $missingThumb['issues']);

        $t->same(true, $externalThumb['external']);
        $t->same(null, $externalThumb['targetPart']);
        $t->same('review=1', $externalThumb['targetQuery']);
        $t->same('preview', $externalThumb['targetFragment']);
        $t->same(['multiple-thumbnail-relationships-for-source', 'external-thumbnail-target'], $externalThumb['issues']);

        $t->same('docProps/bad-thumbnail.xml', $badThumb['targetPart']);
        $t->same('application/xml', $badThumb['contentType']);
        $t->same(false, $badThumb['valid']);
        $t->same(['multiple-thumbnail-relationships-for-source', 'invalid-thumbnail-content-type'], $badThumb['issues']);

        $t->same(4, $summary['packageThumbnailCount']);
        $t->same(2, $summary['packageThumbnailReadableCount']);
        $t->same(1, $summary['packageThumbnailMissingCount']);
        $t->same(1, $summary['packageThumbnailExternalCount']);
        $t->same(4, $summary['packageThumbnailInvalidCount']);
        $t->same(4, $summary['packageThumbnailIssueCount']);
        $t->same($thumbnails['issueCodes'], $summary['packageThumbnailIssueCodes']);
        $t->same(4, $summary['relationshipTypeCounts'][$thumbnailType]);
        $t->same('thumbnail', $relationshipType['label']);
        $t->same(4, $relationshipType['count']);
        $t->same(3, $relationshipType['internalCount']);
        $t->same(1, $relationshipType['externalCount']);
        $t->same(2, $relationshipType['existingTargetCount']);
        $t->same(1, $relationshipType['missingTargetCount']);
        $t->same(['docProps/thumbnail-review.jpeg', 'docProps/bad-thumbnail.xml'], $relationshipType['existingTargetParts']);
        $t->same(['docProps/missing-thumbnail.png'], $relationshipType['missingTargetParts']);
        $t->true(in_array('root-relationship-target', $package['parts']['docProps/thumbnail-review.jpeg']['roles'], true), 'thumbnail root target role missing');
        $t->true(!isset($docx['media']['docProps/thumbnail-review.jpeg']), 'package thumbnail should not be exposed as document media');
    },
    'reports docx digital signature package provenance as metadata only' => static function (TestRunner $t): void {
        $originType = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin';
        $signatureType = 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature';
        $originBytes = 'signature origin bytes';
        $signatureXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:SignedInfo>
    <ds:CanonicalizationMethod Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
    <ds:SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
    <ds:Reference URI="/word/document.xml?review=1#body">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform"/>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>abc=</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="#manifestPackageParts">
      <ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
      <ds:DigestValue>manifestdigest</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="https://example.test/signature-source.xml?remote=1#sig">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha512"/>
    </ds:Reference>
    <ds:Reference URI="customXml/item1.xml?slot=1#payload">
      <ds:DigestValue>relative-digest</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
  <ds:SignatureValue>signed</ds:SignatureValue>
</ds:Signature>
XML;

        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>' . "\n" .
            '  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml; profile=package-signature"/>' . "\n" .
            '  <Override PartName="/_xmlsignatures/bad-signature.xml" ContentType="application/xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSignatureOrigin" Type="' . $originType . '" Target="_xmlsignatures/origin.sigs?audit=1#origin"/>' . "\n" .
            '  <Relationship Id="rMissingSignatureOrigin" Type="' . $originType . '" Target="_xmlsignatures/missing-origin.sigs"/>' . "\n" .
            '  <Relationship Id="rExternalSignatureOrigin" Type="' . $originType . '" Target="https://example.test/origin.sigs" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['_rels/.rels']
        );
        $parts['_xmlsignatures/origin.sigs'] = $originBytes;
        $parts['_xmlsignatures/_rels/origin.sigs.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rSignature1" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml?slot=1#sig"/>
  <Relationship Id="rMissingSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="missing.sig"/>
  <Relationship Id="rExternalSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="https://example.test/sig.xml" TargetMode="External"/>
  <Relationship Id="rBadSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="bad-signature.xml"/>
</Relationships>
XML;
        $parts['_xmlsignatures/sig1.xml'] = $signatureXml;
        $parts['_xmlsignatures/bad-signature.xml'] = '<notSignature xmlns="urn:example"/>';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $signatures = $package['digitalSignatures'];
        $origin = $signatures['byOriginRelationshipId']['rSignatureOrigin'];
        $missingOrigin = $signatures['byOriginRelationshipId']['rMissingSignatureOrigin'];
        $externalOrigin = $signatures['byOriginRelationshipId']['rExternalSignatureOrigin'];
        $signature = $signatures['bySignatureRelationshipId']['rSignature1'];
        $missingSignature = $signatures['bySignatureRelationshipId']['rMissingSignature'];
        $externalSignature = $signatures['bySignatureRelationshipId']['rExternalSignature'];
        $badSignature = $signatures['bySignatureRelationshipId']['rBadSignature'];
        $originRelationshipType = $package['relationshipTypes'][$originType];
        $signatureRelationshipType = $package['relationshipTypes'][$signatureType];

        $t->same($signatures, $docx['digitalSignatures']);
        $t->same(true, $signatures['present']);
        $t->same(3, $signatures['originCount']);
        $t->same(1, $signatures['existingOriginCount']);
        $t->same(1, $signatures['missingOriginCount']);
        $t->same(1, $signatures['externalOriginCount']);
        $t->same(4, $signatures['signatureCount']);
        $t->same(2, $signatures['existingSignatureCount']);
        $t->same(1, $signatures['missingSignatureCount']);
        $t->same(1, $signatures['externalSignatureCount']);
        $t->same(0, $signatures['invalidSignatureXmlCount']);
        $t->same(1, $signatures['unexpectedSignatureRootCount']);
        $t->same(5, $signatures['issueCount']);
        $t->same([
            'external-signature-origin',
            'external-signature-target',
            'missing-origin-content-type',
            'missing-origin-part',
            'missing-signature-content-type',
            'missing-signature-part',
            'unexpected-signature-content-type',
            'unexpected-signature-root',
        ], $signatures['issueCodes']);
        $t->same(['rSignatureOrigin', 'rMissingSignatureOrigin', 'rExternalSignatureOrigin'], $signatures['originRelationshipIds']);
        $t->same(['rSignature1', 'rMissingSignature', 'rExternalSignature', 'rBadSignature'], $signatures['signatureRelationshipIds']);
        $t->same(['_xmlsignatures/origin.sigs', '_xmlsignatures/missing-origin.sigs'], $signatures['originParts']);
        $t->same(['_xmlsignatures/sig1.xml', '_xmlsignatures/missing.sig', '_xmlsignatures/bad-signature.xml'], $signatures['signatureParts']);
        $t->same(['https://example.test/sig.xml', 'https://example.test/origin.sigs'], $signatures['externalTargets']);
        $t->same(false, $signatures['cryptographicValidation']);
        $t->same('digital-signature-metadata-only', $signatures['reviewPolicy']);

        $t->same('_xmlsignatures/origin.sigs', $origin['targetPart']);
        $t->same('?audit=1#origin', $origin['targetReferenceSuffix']);
        $t->same('audit=1', $origin['targetQuery']);
        $t->same('origin', $origin['targetFragment']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-origin', $origin['contentTypeBase']);
        $t->same(strlen($originBytes), $origin['byteLength']);
        $t->same(sprintf('%08x', crc32($originBytes)), $origin['crc32']);
        $t->same('_xmlsignatures/_rels/origin.sigs.rels', $origin['originRelationshipsPart']);
        $t->same(4, $origin['originRelationshipCount']);
        $t->same(4, $origin['signatureCount']);
        $t->same([], $origin['issues']);

        $t->same('_xmlsignatures/missing-origin.sigs', $missingOrigin['targetPart']);
        $t->same(false, $missingOrigin['exists']);
        $t->same(['missing-origin-part', 'missing-origin-content-type'], $missingOrigin['issues']);
        $t->same(true, $externalOrigin['external']);
        $t->same(null, $externalOrigin['targetPart']);
        $t->same(['external-signature-origin'], $externalOrigin['issues']);

        $t->same('_xmlsignatures/sig1.xml', $signature['targetPart']);
        $t->same('?slot=1#sig', $signature['targetReferenceSuffix']);
        $t->same('slot=1', $signature['targetQuery']);
        $t->same('sig', $signature['targetFragment']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml; profile=package-signature', $signature['contentType']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml', $signature['contentTypeBase']);
        $t->same(['profile' => 'package-signature'], $signature['contentTypeParameterMap']);
        $t->same(true, $signature['validXml']);
        $t->same(true, $signature['validRoot']);
        $t->same('http://www.w3.org/2000/09/xmldsig#', $signature['rootNamespace']);
        $t->same('Signature', $signature['rootLocalName']);
        $t->same(4, $signature['referenceCount']);
        $t->same([
            '/word/document.xml?review=1#body',
            '#manifestPackageParts',
            'https://example.test/signature-source.xml?remote=1#sig',
            'customXml/item1.xml?slot=1#payload',
        ], $signature['referenceUris']);
        $t->same(['external' => 1, 'package-part' => 1, 'relative' => 1, 'same-document' => 1], $signature['referenceUriKindCounts']);
        $t->same(1, $signature['packageReferenceCount']);
        $t->same(1, $signature['sameDocumentReferenceCount']);
        $t->same(1, $signature['externalReferenceCount']);
        $t->same(1, $signature['relativeReferenceCount']);
        $t->same(0, $signature['emptyReferenceCount']);
        $t->same(2, $signature['referenceTransformCount']);
        $t->same([
            'http://schemas.openxmlformats.org/package/2006/RelationshipTransform',
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
        ], $signature['referenceTransformAlgorithms']);
        $t->same(3, $signature['referenceDigestValueCount']);
        $t->same(1, $signature['referenceDigestValueMissingCount']);
        $t->same([
            'http://www.w3.org/2001/04/xmlenc#sha256',
            'http://www.w3.org/2000/09/xmldsig#sha1',
            'http://www.w3.org/2001/04/xmlenc#sha512',
        ], $signature['digestMethodAlgorithms']);
        $t->same('package-part', $signature['references'][0]['uriKind']);
        $t->same('word/document.xml', $signature['references'][0]['targetPart']);
        $t->same('review=1', $signature['references'][0]['targetQuery']);
        $t->same('body', $signature['references'][0]['targetFragment']);
        $t->same('?review=1#body', $signature['references'][0]['targetReferenceSuffix']);
        $t->same(true, $signature['references'][0]['startsAtPackageRoot']);
        $t->same(2, $signature['references'][0]['transformCount']);
        $t->same('http://www.w3.org/2001/04/xmlenc#sha256', $signature['references'][0]['digestMethodAlgorithm']);
        $t->same(true, $signature['references'][0]['digestValuePresent']);
        $t->same(4, $signature['references'][0]['digestValueLength']);
        $t->same('same-document', $signature['references'][1]['uriKind']);
        $t->same(true, $signature['references'][1]['sameDocument']);
        $t->same('#manifestPackageParts', $signature['references'][1]['targetReferenceSuffix']);
        $t->same('external', $signature['references'][2]['uriKind']);
        $t->same(true, $signature['references'][2]['external']);
        $t->same('remote=1', $signature['references'][2]['targetQuery']);
        $t->same(false, $signature['references'][2]['digestValuePresent']);
        $t->same('relative', $signature['references'][3]['uriKind']);
        $t->same(null, $signature['references'][3]['targetPart']);
        $t->same('slot=1', $signature['references'][3]['targetQuery']);
        $t->same(null, $signature['references'][3]['digestMethodAlgorithm']);
        $t->same(15, $signature['references'][3]['digestValueLength']);
        $t->same(['http://www.w3.org/2001/04/xmldsig-more#rsa-sha256'], $signature['signatureMethodAlgorithms']);
        $t->same(['http://www.w3.org/TR/2001/REC-xml-c14n-20010315'], $signature['canonicalizationMethodAlgorithms']);
        $t->same(true, $signature['hasSignatureValue']);
        $t->same(false, $signature['cryptographicValidation']);
        $t->same('digital-signature-metadata-only', $signature['reviewPolicy']);
        $t->same([], $signature['issues']);

        $t->same('_xmlsignatures/missing.sig', $missingSignature['targetPart']);
        $t->same(false, $missingSignature['exists']);
        $t->same(['missing-signature-part', 'missing-signature-content-type'], $missingSignature['issues']);
        $t->same(true, $externalSignature['external']);
        $t->same(null, $externalSignature['targetPart']);
        $t->same(['external-signature-target'], $externalSignature['issues']);
        $t->same('_xmlsignatures/bad-signature.xml', $badSignature['targetPart']);
        $t->same('application/xml', $badSignature['contentTypeBase']);
        $t->same(false, $badSignature['validRoot']);
        $t->same(['unexpected-signature-content-type', 'unexpected-signature-root'], $badSignature['issues']);

        $t->same(3, $summary['digitalSignatureOriginCount']);
        $t->same(1, $summary['digitalSignatureExistingOriginCount']);
        $t->same(1, $summary['digitalSignatureMissingOriginCount']);
        $t->same(1, $summary['digitalSignatureExternalOriginCount']);
        $t->same(4, $summary['digitalSignatureSignatureCount']);
        $t->same(2, $summary['digitalSignatureExistingSignatureCount']);
        $t->same(1, $summary['digitalSignatureMissingSignatureCount']);
        $t->same(1, $summary['digitalSignatureExternalSignatureCount']);
        $t->same(0, $summary['digitalSignatureInvalidXmlCount']);
        $t->same(1, $summary['digitalSignatureUnexpectedRootCount']);
        $t->same(5, $summary['digitalSignatureIssueCount']);
        $t->same($signatures['issueCodes'], $summary['digitalSignatureIssueCodes']);
        $t->same(3, $summary['relationshipTypeCounts'][$originType]);
        $t->same(4, $summary['relationshipTypeCounts'][$signatureType]);

        $t->same('origin', $originRelationshipType['label']);
        $t->same(3, $originRelationshipType['count']);
        $t->same(2, $originRelationshipType['internalCount']);
        $t->same(1, $originRelationshipType['externalCount']);
        $t->same(['_xmlsignatures/origin.sigs'], $originRelationshipType['existingTargetParts']);
        $t->same(['_xmlsignatures/missing-origin.sigs'], $originRelationshipType['missingTargetParts']);
        $t->same('signature', $signatureRelationshipType['label']);
        $t->same(4, $signatureRelationshipType['count']);
        $t->same(3, $signatureRelationshipType['internalCount']);
        $t->same(1, $signatureRelationshipType['externalCount']);
        $t->same(['_xmlsignatures/sig1.xml', '_xmlsignatures/bad-signature.xml'], $signatureRelationshipType['existingTargetParts']);
        $t->same(['_xmlsignatures/missing.sig'], $signatureRelationshipType['missingTargetParts']);
        $t->true(in_array('root-relationship-target', $package['parts']['_xmlsignatures/origin.sigs']['roles'], true), 'signature origin root target role missing');
        $t->true(in_array('relationship-target', $package['parts']['_xmlsignatures/sig1.xml']['roles'], true), 'signature XML relationship target role missing');
    },
    'summarizes docx package relationship targets for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            [
                '  <Default Extension="png" ContentType="image/png"/>',
                '</Types>',
            ],
            [
                '  <Default Extension="png" ContentType="image/png; profile=missing-summary"/>',
                '  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml; profile=summary-comments"/>' . "\n" .
                '</Types>',
            ],
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rMissingComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml?thread=review#c1"/>' . "\n" .
            '  <Relationship Id="rRemoteTemplate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate" Target="https://example.test/templates/review.dotx?version=2026#template" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/header1.xml'] = '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header</w:t></w:r></w:p></w:hdr>';
        $parts['word/_rels/header1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingHeaderImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-header.png?slot=header#logo"/>
</Relationships>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];

        $t->same(10, $summary['partCount']);
        $t->same(3, $summary['relationshipPartCount']);
        $t->same(7, $summary['relationshipCount']);
        $t->same(5, $summary['internalRelationshipCount']);
        $t->same(2, $summary['externalRelationshipCount']);
        $t->same(3, $summary['existingRelationshipTargetCount']);
        $t->same(2, $summary['missingRelationshipTargetCount']);
        $t->same(5, $summary['uniqueRelationshipTargetPartCount']);
        $t->same(3, $summary['contentTypeDefaultCount']);
        $t->same(3, $summary['contentTypeOverrideCount']);
        $t->same(2, $summary['contentTypeSourceCounts']['override']);
        $t->same(8, $summary['contentTypeSourceCounts']['default']);
        $t->same(1, $summary['roleCounts']['office-document']);
        $t->same(3, $summary['roleCounts']['relationship-part']);
        $t->same(2, $summary['roleCounts']['root-relationship-target']);
        $t->same(3, $summary['roleCounts']['package-part']);
        $t->same(1, $summary['relationshipTypeCounts']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments']);
        $t->same(2, $summary['relationshipTypeCounts']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/image']);
        $t->same(['word/_rels/document.xml.rels', 'word/_rels/header1.xml.rels'], $summary['relationshipPartsWithMissingTargets']);
        $t->same('rMissingComments', $summary['missingRelationshipTargets'][0]['id']);
        $t->same('word/comments.xml', $summary['missingRelationshipTargets'][0]['targetPart']);
        $t->same('?thread=review#c1', $summary['missingRelationshipTargets'][0]['targetReferenceSuffix']);
        $t->same('thread=review', $summary['missingRelationshipTargets'][0]['targetQuery']);
        $t->same('c1', $summary['missingRelationshipTargets'][0]['targetFragment']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml; profile=summary-comments', $summary['missingRelationshipTargets'][0]['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', $summary['missingRelationshipTargets'][0]['contentTypeBase']);
        $t->same(true, $summary['missingRelationshipTargets'][0]['contentTypeHasParameters']);
        $t->same(1, $summary['missingRelationshipTargets'][0]['contentTypeParameterCount']);
        $t->same(['profile' => 'summary-comments'], $summary['missingRelationshipTargets'][0]['contentTypeParameterMap']);
        $t->same('override', $summary['missingRelationshipTargets'][0]['contentTypeSource']);
        $t->same(null, $summary['missingRelationshipTargets'][0]['defaultExtension']);
        $t->same('word/comments.xml', $summary['missingRelationshipTargets'][0]['overridePartName']);
        $t->same('rMissingHeaderImage', $summary['missingRelationshipTargets'][1]['id']);
        $t->same('word/media/missing-header.png', $summary['missingRelationshipTargets'][1]['targetPart']);
        $t->same('?slot=header#logo', $summary['missingRelationshipTargets'][1]['targetReferenceSuffix']);
        $t->same('slot=header', $summary['missingRelationshipTargets'][1]['targetQuery']);
        $t->same('logo', $summary['missingRelationshipTargets'][1]['targetFragment']);
        $t->same('image/png; profile=missing-summary', $summary['missingRelationshipTargets'][1]['contentType']);
        $t->same('image/png', $summary['missingRelationshipTargets'][1]['contentTypeBase']);
        $t->same(true, $summary['missingRelationshipTargets'][1]['contentTypeHasParameters']);
        $t->same(1, $summary['missingRelationshipTargets'][1]['contentTypeParameterCount']);
        $t->same(['profile' => 'missing-summary'], $summary['missingRelationshipTargets'][1]['contentTypeParameterMap']);
        $t->same('default', $summary['missingRelationshipTargets'][1]['contentTypeSource']);
        $t->same('png', $summary['missingRelationshipTargets'][1]['defaultExtension']);
        $t->same(null, $summary['missingRelationshipTargets'][1]['overridePartName']);
        $t->same('rLink', $summary['externalRelationshipTargets'][0]['id']);
        $t->same('rRemoteTemplate', $summary['externalRelationshipTargets'][1]['id']);
        $t->same(null, $summary['externalRelationshipTargets'][1]['targetPart']);
        $t->same('https://example.test/templates/review.dotx?version=2026#template', $summary['externalRelationshipTargets'][1]['resolvedTarget']);
        $t->same('?version=2026#template', $summary['externalRelationshipTargets'][1]['targetReferenceSuffix']);
        $t->same('version=2026', $summary['externalRelationshipTargets'][1]['targetQuery']);
        $t->same('template', $summary['externalRelationshipTargets'][1]['targetFragment']);
    },
    'preserves docx relationship type target suffix provenance for package review' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/customXml/item1.xml" ContentType="application/vnd.example.review+xml"/>' . "\n" .
            '  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rCustomXmlReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml?slot=package#payload"/>' . "\n" .
            '  <Relationship Id="rMissingComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml?thread=review#c1"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/header1.xml'] = '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header</w:t></w:r></w:p></w:hdr>';
        $parts['word/_rels/header1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHeaderImageReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/header.png?density=2#logo"/>
</Relationships>
XML;
        $parts['customXml/item1.xml'] = '<review/>';
        $parts['word/media/header.png'] = 'header png bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $types = $document->attr('docx')['packageProvenance']['relationshipTypes'];
        $customXmlType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
        $commentsType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments';
        $imageType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $customXml = $types[$customXmlType];
        $comments = $types[$commentsType];
        $imageRelationships = [];
        foreach ($types[$imageType]['relationships'] as $relationship) {
            $imageRelationships[$relationship['id']] = $relationship;
        }
        $headerImage = $imageRelationships['rHeaderImageReview'];

        $t->same('customXml', $customXml['label']);
        $t->same(1, $customXml['count']);
        $t->same(['customXml/item1.xml'], $customXml['existingTargetParts']);
        $t->same('rCustomXmlReview', $customXml['relationships'][0]['id']);
        $t->same('../customXml/item1.xml?slot=package#payload', $customXml['relationships'][0]['target']);
        $t->same('customXml/item1.xml?slot=package#payload', $customXml['relationships'][0]['resolvedTarget']);
        $t->same('customXml/item1.xml', $customXml['relationships'][0]['targetPart']);
        $t->same('slot=package', $customXml['relationships'][0]['targetQuery']);
        $t->same('payload', $customXml['relationships'][0]['targetFragment']);
        $t->same('?slot=package#payload', $customXml['relationships'][0]['targetReferenceSuffix']);
        $t->same('override', $customXml['relationships'][0]['contentTypeSource']);
        $t->same(null, $customXml['relationships'][0]['defaultExtension']);
        $t->same('customXml/item1.xml', $customXml['relationships'][0]['overridePartName']);

        $t->same('comments', $comments['label']);
        $t->same(1, $comments['missingTargetCount']);
        $t->same('rMissingComments', $comments['relationships'][0]['id']);
        $t->same('word/comments.xml?thread=review#c1', $comments['relationships'][0]['resolvedTarget']);
        $t->same('word/comments.xml', $comments['relationships'][0]['targetPart']);
        $t->same('thread=review', $comments['relationships'][0]['targetQuery']);
        $t->same('c1', $comments['relationships'][0]['targetFragment']);
        $t->same('?thread=review#c1', $comments['relationships'][0]['targetReferenceSuffix']);
        $t->same(false, $comments['relationships'][0]['exists']);
        $t->same('override', $comments['relationships'][0]['contentTypeSource']);

        $t->same('word/_rels/header1.xml.rels', $headerImage['relationshipsPart']);
        $t->same('word/header1.xml', $headerImage['sourcePart']);
        $t->same('media/header.png?density=2#logo', $headerImage['target']);
        $t->same('word/media/header.png?density=2#logo', $headerImage['resolvedTarget']);
        $t->same('word/media/header.png', $headerImage['targetPart']);
        $t->same('density=2', $headerImage['targetQuery']);
        $t->same('logo', $headerImage['targetFragment']);
        $t->same('?density=2#logo', $headerImage['targetReferenceSuffix']);
        $t->same(true, $headerImage['exists']);
        $t->same('default', $headerImage['contentTypeSource']);
        $t->same('png', $headerImage['defaultExtension']);
        $t->same(null, $headerImage['overridePartName']);
    },
    'summarizes docx package relationship target suffix provenance for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['_rels/.rels'] = str_replace(
            'Target="word/document.xml"',
            'Target="word/document.xml?doc=main#body"',
            $parts['_rels/.rels']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rMissingComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml?thread=review#c1"/>' . "\n" .
            '  <Relationship Id="rRemoteTemplate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate" Target="https://example.test/templates/review.dotx?version=2026#template" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/header1.xml'] = '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header</w:t></w:r></w:p></w:hdr>';
        $parts['word/_rels/header1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rHeaderImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/header.png?slot=header#logo"/>
</Relationships>
XML;
        $parts['word/media/header.png'] = 'header png bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $suffixTargets = $summary['relationshipTargetsWithReferenceSuffix'];

        $t->same(5, $summary['relationshipTargetReferenceSuffixCount']);
        $t->same(5, $summary['relationshipTargetQueryCount']);
        $t->same(4, $summary['relationshipTargetFragmentCount']);
        $t->same([
            '_rels/.rels',
            'word/_rels/document.xml.rels',
            'word/_rels/header1.xml.rels',
        ], $summary['relationshipPartsWithTargetReferenceSuffix']);
        $t->same([
            'rDoc',
            'rLink',
            'rMissingComments',
            'rRemoteTemplate',
            'rHeaderImage',
        ], array_column($suffixTargets, 'id'));

        $t->same('/', $suffixTargets[0]['sourcePart']);
        $t->same('word/document.xml', $suffixTargets[0]['targetPart']);
        $t->same('?doc=main#body', $suffixTargets[0]['targetReferenceSuffix']);
        $t->same('doc=main', $suffixTargets[0]['targetQuery']);
        $t->same('body', $suffixTargets[0]['targetFragment']);
        $t->same('word/document.xml', $suffixTargets[2]['sourcePart']);
        $t->same('word/comments.xml', $suffixTargets[2]['targetPart']);
        $t->same(false, $summary['relationshipTargetsWithReferenceSuffix'][2]['contentTypeHasParameters']);
        $t->same('thread=review', $suffixTargets[2]['targetQuery']);
        $t->same('c1', $suffixTargets[2]['targetFragment']);
        $t->same(true, $suffixTargets[3]['external']);
        $t->same(null, $suffixTargets[3]['targetPart']);
        $t->same('?version=2026#template', $suffixTargets[3]['targetReferenceSuffix']);
        $t->same('word/_rels/header1.xml.rels', $suffixTargets[4]['relationshipsPart']);
        $t->same('word/media/header.png', $suffixTargets[4]['targetPart']);
        $t->same('slot=header', $suffixTargets[4]['targetQuery']);
        $t->same('logo', $suffixTargets[4]['targetFragment']);
        $t->same('image/png', $suffixTargets[4]['contentType']);
        $t->same(true, $suffixTargets[4]['exists']);
    },
    'preserves duplicate docx relationship ids for package review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rDuplicate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png?copy=one#img"/>' . "\n" .
            '  <Relationship Id="rDuplicate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/duplicate?copy=two#link" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $relationshipPart = $package['relationshipParts']['word/_rels/document.xml.rels'];
        $duplicate = $relationshipPart['duplicateRelationshipIdItems'][0];
        $records = array_values(array_filter(
            $relationshipPart['relationshipRecords'],
            static fn (array $record): bool => $record['id'] === 'rDuplicate',
        ));

        $t->same(4, $relationshipPart['relationshipRecordCount']);
        $t->same(3, $relationshipPart['relationshipCount']);
        $t->same(1, $relationshipPart['duplicateRelationshipIdCount']);
        $t->same(2, $relationshipPart['duplicateRelationshipRecordCount']);
        $t->same(['rDuplicate'], $relationshipPart['duplicateRelationshipIds']);
        $t->same('rDuplicate', $duplicate['id']);
        $t->same('word/_rels/document.xml.rels', $duplicate['relationshipsPart']);
        $t->same('word/document.xml', $duplicate['sourcePart']);
        $t->same([2, 3], $duplicate['ordinals']);
        $t->same([
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
        ], $duplicate['types']);
        $t->same([
            'media/review.png?copy=one#img',
            'https://example.test/duplicate?copy=two#link',
        ], $duplicate['targets']);
        $t->same(['', 'External'], $duplicate['targetModes']);
        $t->same(['word/media/review.png?copy=one#img', 'https://example.test/duplicate?copy=two#link'], $duplicate['resolvedTargets']);
        $t->same(['word/media/review.png', null], $duplicate['targetParts']);
        $t->same(['?copy=one#img', '?copy=two#link'], $duplicate['targetReferenceSuffixes']);
        $t->same([false, true], $duplicate['externalValues']);
        $t->same([true, false], $duplicate['existsValues']);
        $t->same(['image/png', ''], $duplicate['contentTypes']);

        $t->same([true, true], array_column($records, 'duplicateId'));
        $t->same([2, 3], array_column($records, 'ordinal'));
        $t->same('word/media/review.png', $records[0]['targetPart']);
        $t->same('copy=one', $records[0]['targetQuery']);
        $t->same('img', $records[0]['targetFragment']);
        $t->same(null, $records[1]['targetPart']);
        $t->same(true, $records[1]['external']);
        $t->same('copy=two', $records[1]['targetQuery']);
        $t->same('link', $records[1]['targetFragment']);
        $t->same(true, $relationshipPart['relationships']['rDuplicate']['external']);
        $t->same('https://example.test/duplicate?copy=two#link', $relationshipPart['relationships']['rDuplicate']['resolvedTarget']);

        $t->same(6, $summary['relationshipRecordCount']);
        $t->same(5, $summary['relationshipCount']);
        $t->same(1, $summary['duplicateRelationshipIdCount']);
        $t->same(2, $summary['duplicateRelationshipRecordCount']);
        $t->same(['rDuplicate'], $summary['duplicateRelationshipIds']);
        $t->same(['word/_rels/document.xml.rels'], $summary['relationshipPartsWithDuplicateRelationshipIds']);
        $t->same($duplicate, $summary['duplicateRelationshipIdItems'][0]);
    },
    'reports malformed docx relationship declarations without aborting package ingestion' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-id.png?audit=1#img"/>' . "\n" .
            '  <Relationship Id="rMissingTarget" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"/>' . "\n" .
            '  <Relationship Id="rMissingType" Target="media/review.png?copy=notyped#img"/>' . "\n" .
            '  <Relationship Id="rUnexpectedMode" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="review/source.html" TargetMode="Sidecar"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $relationshipPart = $package['relationshipParts']['word/_rels/document.xml.rels'];
        $records = $relationshipPart['relationshipRecords'];
        $missingId = $records[2];
        $missingTarget = $records[3];
        $missingType = $records[4];
        $unexpectedMode = $records[5];
        $missingTypeBucket = $package['relationshipTypes']['(missing-type)'];

        $t->same('document', $document->type);
        $t->same('Imported DOCX Batch', $document->attr('meta')['title']);
        $t->same(6, $relationshipPart['relationshipRecordCount']);
        $t->same(4, $relationshipPart['relationshipCount']);
        $t->same(4, $relationshipPart['invalidRelationshipRecordCount']);
        $t->same(4, $relationshipPart['relationshipRecordIssueCount']);
        $t->same([
            'missing-relationship-id',
            'missing-relationship-target',
            'missing-relationship-type',
            'unexpected-relationship-target-mode',
        ], $relationshipPart['relationshipRecordIssueCodes']);

        $t->same('', $missingId['id']);
        $t->same(2, $missingId['ordinal']);
        $t->same('word/media/missing-id.png', $missingId['targetPart']);
        $t->same('audit=1', $missingId['targetQuery']);
        $t->same(false, $missingId['valid']);
        $t->same(['missing-relationship-id'], $missingId['issues']);
        $t->same('rMissingTarget', $missingTarget['id']);
        $t->same(null, $missingTarget['targetPart']);
        $t->same('', $missingTarget['resolvedTarget']);
        $t->same(false, $missingTarget['valid']);
        $t->same(['missing-relationship-target'], $missingTarget['issues']);
        $t->true(!isset($relationshipPart['relationships']['rMissingTarget']), 'missing relationship target must not enter lookup map');

        $t->same('rMissingType', $missingType['id']);
        $t->same('', $missingType['type']);
        $t->same('word/media/review.png', $missingType['targetPart']);
        $t->same(true, $missingType['exists']);
        $t->same(['missing-relationship-type'], $missingType['issues']);
        $t->same('rMissingType', $relationshipPart['relationships']['rMissingType']['id']);
        $t->same('missing-type', $missingTypeBucket['label']);
        $t->same(1, $missingTypeBucket['count']);
        $t->same(['word/media/review.png'], $missingTypeBucket['existingTargetParts']);

        $t->same('rUnexpectedMode', $unexpectedMode['id']);
        $t->same('Sidecar', $unexpectedMode['targetMode']);
        $t->same(false, $unexpectedMode['valid']);
        $t->same(['unexpected-relationship-target-mode'], $unexpectedMode['issues']);
        $t->same(8, $summary['relationshipRecordCount']);
        $t->same(6, $summary['relationshipCount']);
        $t->same(4, $summary['invalidRelationshipRecordCount']);
        $t->same(4, $summary['relationshipRecordIssueCount']);
        $t->same($relationshipPart['relationshipRecordIssueCodes'], $summary['relationshipRecordIssueCodes']);
        $t->same(['word/_rels/document.xml.rels'], $summary['relationshipPartsWithInvalidRecords']);
        $t->same(['', 'rMissingTarget', 'rMissingType', 'rUnexpectedMode'], array_column($summary['invalidRelationshipRecords'], 'id'));
        $t->same(['missing-relationship-id'], $summary['invalidRelationshipRecords'][0]['issues']);
        $t->same(1, $summary['relationshipTypeCounts']['(missing-type)']);
    },
    'summarizes docx relationship target mode declarations for package review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['_rels/.rels'] = str_replace(
            'Target="word/document.xml"',
            'Target="word/document.xml" TargetMode="Internal"',
            $parts['_rels/.rels']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rExplicitInternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/explicit-internal.png" TargetMode="Internal"/>' . "\n" .
            '  <Relationship Id="rUnexpectedMode" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/unexpected-mode.png" TargetMode="Sidecar"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/media/explicit-internal.png'] = 'explicit internal image bytes';
        $parts['word/media/unexpected-mode.png'] = 'unexpected mode image bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $rootRelationship = $package['relationshipParts']['_rels/.rels']['relationships']['rDoc'];
        $documentRelationship = $package['relationshipParts']['word/_rels/document.xml.rels']['relationships']['rExplicitInternal'];
        $unexpectedRecord = $summary['relationshipsWithUnexpectedTargetMode'][0];

        $t->same(6, $summary['relationshipRecordCount']);
        $t->same(6, $summary['relationshipCount']);
        $t->same(5, $summary['internalRelationshipCount']);
        $t->same(1, $summary['externalRelationshipCount']);
        $t->same([
            '(implicit-internal)' => 2,
            'External' => 1,
            'Internal' => 2,
            'Sidecar' => 1,
        ], $summary['relationshipRecordTargetModeCounts']);
        $t->same(2, $summary['relationshipRecordImplicitInternalTargetModeCount']);
        $t->same(2, $summary['relationshipRecordExplicitInternalTargetModeCount']);
        $t->same(1, $summary['relationshipRecordExplicitExternalTargetModeCount']);
        $t->same(1, $summary['relationshipRecordUnexpectedTargetModeCount']);
        $t->same(['_rels/.rels', 'word/_rels/document.xml.rels'], $summary['relationshipPartsWithExplicitInternalTargetMode']);
        $t->same(['word/_rels/document.xml.rels'], $summary['relationshipPartsWithUnexpectedTargetMode']);
        $t->same(['rDoc', 'rExplicitInternal'], array_column($summary['relationshipsWithExplicitInternalTargetMode'], 'id'));
        $t->same(['rUnexpectedMode'], array_column($summary['relationshipsWithUnexpectedTargetMode'], 'id'));

        $t->same('Internal', $rootRelationship['targetMode']);
        $t->same(false, $rootRelationship['external']);
        $t->same('word/document.xml', $rootRelationship['targetPart']);
        $t->same(true, $rootRelationship['exists']);
        $t->same('Internal', $documentRelationship['targetMode']);
        $t->same(false, $documentRelationship['external']);
        $t->same('word/media/explicit-internal.png', $documentRelationship['targetPart']);
        $t->same(true, $documentRelationship['exists']);
        $t->same('rUnexpectedMode', $unexpectedRecord['id']);
        $t->same('Sidecar', $unexpectedRecord['targetMode']);
        $t->same(false, $unexpectedRecord['valid']);
        $t->same(['unexpected-relationship-target-mode'], $unexpectedRecord['issues']);
        $t->same('word/media/unexpected-mode.png', $unexpectedRecord['targetPart']);
        $t->same(true, $unexpectedRecord['exists']);
    },
    'summarizes docx package parts without content type coverage' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rUntypedMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/source.bin?audit=1#raw"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/media/source.bin'] = 'binary review media';
        $parts['customXml/untyped-payload.bin'] = 'opaque custom payload';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $summary = $package['summary'];
        $untypedMedia = $package['parts']['word/media/source.bin'];
        $untypedPayload = $package['parts']['customXml/untyped-payload.bin'];
        $relationship = $package['relationshipParts']['word/_rels/document.xml.rels']['relationships']['rUntypedMedia'];
        $relationshipType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';

        $t->same(2, $summary['missingContentTypePartCount']);
        $t->same(1, $summary['relationshipTargetMissingContentTypeCount']);
        $t->same(['word/_rels/document.xml.rels'], $summary['relationshipPartsWithMissingContentTypes']);
        $t->same(['word/media/source.bin', 'customXml/untyped-payload.bin'], array_column($summary['partsWithoutContentType'], 'partName'));
        $t->same('word/media/source.bin', $summary['partsWithoutContentType'][0]['partName']);
        $t->same(strlen('binary review media'), $summary['partsWithoutContentType'][0]['bytes']);
        $t->same('bin', $summary['partsWithoutContentType'][0]['defaultExtension']);
        $t->same(['document-relationship-target'], $summary['partsWithoutContentType'][0]['roles']);
        $t->same('customXml/untyped-payload.bin', $summary['partsWithoutContentType'][1]['partName']);
        $t->same(['package-part'], $summary['partsWithoutContentType'][1]['roles']);

        $t->same('rUntypedMedia', $summary['relationshipTargetsWithoutContentType'][0]['id']);
        $t->same('word/document.xml', $summary['relationshipTargetsWithoutContentType'][0]['sourcePart']);
        $t->same('word/media/source.bin', $summary['relationshipTargetsWithoutContentType'][0]['targetPart']);
        $t->same('?audit=1#raw', $summary['relationshipTargetsWithoutContentType'][0]['targetReferenceSuffix']);
        $t->same('audit=1', $summary['relationshipTargetsWithoutContentType'][0]['targetQuery']);
        $t->same('raw', $summary['relationshipTargetsWithoutContentType'][0]['targetFragment']);
        $t->same('', $summary['relationshipTargetsWithoutContentType'][0]['contentType']);
        $t->same('', $summary['relationshipTargetsWithoutContentType'][0]['contentTypeBase']);
        $t->same(false, $summary['relationshipTargetsWithoutContentType'][0]['contentTypeHasParameters']);
        $t->same(0, $summary['relationshipTargetsWithoutContentType'][0]['contentTypeParameterCount']);
        $t->same([], $summary['relationshipTargetsWithoutContentType'][0]['contentTypeParameterMap']);
        $t->same('missing', $summary['relationshipTargetsWithoutContentType'][0]['contentTypeSource']);
        $t->same('bin', $summary['relationshipTargetsWithoutContentType'][0]['defaultExtension']);
        $t->same(null, $summary['relationshipTargetsWithoutContentType'][0]['overridePartName']);
        $t->same(2, $summary['contentTypeSourceCounts']['missing']);
        $t->same(2, $summary['relationshipTypeCounts'][$relationshipType]);

        $t->same('missing', $untypedMedia['contentTypeSource']);
        $t->same('bin', $untypedMedia['defaultExtension']);
        $t->same('', $untypedMedia['contentType']);
        $t->same(['document-relationship-target'], $untypedMedia['roles']);
        $t->same('missing', $untypedPayload['contentTypeSource']);
        $t->same('bin', $untypedPayload['defaultExtension']);
        $t->same(['package-part'], $untypedPayload['roles']);

        $t->same('word/media/source.bin?audit=1#raw', $relationship['resolvedTarget']);
        $t->same('word/media/source.bin', $relationship['targetPart']);
        $t->same('audit=1', $relationship['targetQuery']);
        $t->same('raw', $relationship['targetFragment']);
        $t->same(true, $relationship['exists']);
        $t->same('missing', $relationship['contentTypeSource']);
        $t->same('bin', $relationship['defaultExtension']);
    },
    'summarizes docx package part content type buckets for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            [
                '<Default Extension="xml" ContentType="application/xml"/>',
                '<Default Extension="png" ContentType="image/png"/>',
                '</Types>',
            ],
            [
                '<Default Extension="xml" ContentType="application/xml; charset=UTF-8"/>',
                '<Default Extension="png" ContentType="image/png; profile=package-bucket"/>',
                '  <Override PartName="/customXml/review.json" ContentType="application/vnd.review+json; profile=content-bucket"/>' . "\n" .
                '</Types>',
            ],
            $parts['[Content_Types].xml']
        );
        $parts['customXml/review.json'] = '{"review":true}';
        $parts['customXml/no-type.bin'] = 'missing type payload';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $buckets = [];
        foreach ($summary['partContentTypes'] as $bucket) {
            $buckets[$bucket['contentTypeKey']] = $bucket;
        }

        $xml = $buckets['application/xml'];
        $relationships = $buckets['application/vnd.openxmlformats-package.relationships+xml'];
        $documentType = $buckets['application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'];
        $json = $buckets['application/vnd.review+json'];
        $image = $buckets['image/png'];
        $missing = $buckets['(missing)'];

        $t->same(7, $summary['partContentTypeCount']);
        $t->same([
            '(missing)',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/vnd.openxmlformats-package.core-properties+xml',
            'application/vnd.openxmlformats-package.relationships+xml',
            'application/vnd.review+json',
            'application/xml',
            'image/png',
        ], array_column($summary['partContentTypes'], 'contentTypeKey'));

        $t->same(3, $xml['partCount']);
        $t->same(
            strlen($parts['[Content_Types].xml']) + strlen($parts['word/styles.xml']) + strlen($parts['word/numbering.xml']),
            $xml['byteLength']
        );
        $t->same(3, $xml['parameterizedPartCount']);
        $t->same(['application/xml; charset=UTF-8'], $xml['contentTypes']);
        $t->same(['default' => 3], $xml['contentTypeSourceCounts']);
        $t->same(['xml'], $xml['defaultExtensions']);
        $t->same(['content-types' => 1, 'package-part' => 2], $xml['roleCounts']);
        $t->same(['[Content_Types].xml', 'word/styles.xml', 'word/numbering.xml'], $xml['partNames']);

        $t->same(2, $relationships['partCount']);
        $t->same(2, $relationships['relationshipPartCount']);
        $t->same(['application/vnd.openxmlformats-package.relationships+xml'], $relationships['contentTypes']);
        $t->same(['default' => 2], $relationships['contentTypeSourceCounts']);
        $t->same(['rels'], $relationships['defaultExtensions']);
        $t->same(['office-document-relationships' => 1, 'package-relationships' => 1, 'relationship-part' => 2], $relationships['roleCounts']);

        $t->same(1, $documentType['partCount']);
        $t->same(['override' => 1], $documentType['contentTypeSourceCounts']);
        $t->same(['word/document.xml'], $documentType['overridePartNames']);
        $t->same(['office-document' => 1, 'root-relationship-target' => 1], $documentType['roleCounts']);

        $t->same(1, $json['partCount']);
        $t->same(strlen($parts['customXml/review.json']), $json['byteLength']);
        $t->same(1, $json['parameterizedPartCount']);
        $t->same(['application/vnd.review+json; profile=content-bucket'], $json['contentTypes']);
        $t->same(['override' => 1], $json['contentTypeSourceCounts']);
        $t->same(['customXml/review.json'], $json['overridePartNames']);
        $t->same('customXml/review.json', $json['largestPart']['partName']);
        $t->same(hash('sha256', $parts['customXml/review.json']), $json['largestPart']['sha256']);

        $t->same(1, $image['partCount']);
        $t->same(1, $image['parameterizedPartCount']);
        $t->same(['image/png; profile=package-bucket'], $image['contentTypes']);
        $t->same(['default' => 1], $image['contentTypeSourceCounts']);
        $t->same(['png'], $image['defaultExtensions']);
        $t->same(['document-relationship-target' => 1], $image['roleCounts']);

        $t->same('', $missing['contentTypeBase']);
        $t->same(1, $missing['partCount']);
        $t->same(1, $missing['missingContentTypePartCount']);
        $t->same(0, $missing['parameterizedPartCount']);
        $t->same([], $missing['contentTypes']);
        $t->same(['missing' => 1], $missing['contentTypeSourceCounts']);
        $t->same(['bin'], $missing['defaultExtensions']);
        $t->same(['package-part' => 1], $missing['roleCounts']);
        $t->same('customXml/no-type.bin', $missing['largestPart']['partName']);
    },
    'summarizes docx embedded object package relationships for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $packageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';
        $oleRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject';
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/embeddings/review.xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; profile=embedded-workbook"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rEmbeddedWorkbook" Type="' . $packageRel . '" Target="embeddings/review.xlsx?sheet=1#ole"/>' . "\n" .
            '  <Relationship Id="rMissingOle" Type="' . $oleRel . '" Target="embeddings/missing.bin"/>' . "\n" .
            '  <Relationship Id="rRemoteOle" Type="' . $oleRel . '" Target="https://example.test/ole.bin?remote=1#object" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"',
            'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:o="urn:schemas-microsoft-com:office:office"',
            $parts['word/document.xml']
        );
        $parts['word/document.xml'] = str_replace(
            '    <w:tbl>',
            '    <w:p><w:r><w:object><o:OLEObject r:id="rEmbeddedWorkbook" ProgID="Excel.Sheet.12" ShapeID="_x0000_i1025" DrawAspect="Content" ObjectID="_review1" UpdateMode="OnCall"/></w:object></w:r></w:p>' . "\n" .
            '    <w:p><w:r><w:object><o:OLEObject r:id="rMissingOle" ProgID="Package" ShapeID="_x0000_i1026" DrawAspect="Icon" ObjectID="_missing1"/></w:object></w:r></w:p>' . "\n" .
            '    <w:p><w:r><w:object><o:OLEObject r:id="rUnknownOle" ProgID="Package"/></w:object></w:r></w:p>' . "\n" .
            '    <w:tbl>',
            $parts['word/document.xml']
        );
        $parts['word/embeddings/review.xlsx'] = 'fake embedded workbook bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $embedded = $docx['embeddedObjects'];
        $workbook = $embedded['byRelationshipId']['rEmbeddedWorkbook'];
        $missing = $embedded['byRelationshipId']['rMissingOle'];
        $unknown = $embedded['byRelationshipId']['rUnknownOle'];
        $remote = $embedded['byRelationshipId']['rRemoteOle'];
        $relationshipTypes = $docx['packageProvenance']['relationshipTypes'];

        $t->same(4, $embedded['count']);
        $t->same(3, $embedded['relationshipCount']);
        $t->same(3, $embedded['referencedCount']);
        $t->same(1, $embedded['unreferencedRelationshipCount']);
        $t->same(1, $embedded['existingCount']);
        $t->same(1, $embedded['missingCount']);
        $t->same(1, $embedded['externalCount']);
        $t->same(1, $embedded['unresolvedCount']);
        $t->same(1, $embedded['missingContentTypeCount']);
        $t->same(['rEmbeddedWorkbook', 'rMissingOle', 'rUnknownOle', 'rRemoteOle'], $embedded['relationshipIds']);
        $t->same(['rEmbeddedWorkbook', 'rMissingOle', 'rUnknownOle'], $embedded['referencedRelationshipIds']);
        $t->same(['rRemoteOle'], $embedded['unreferencedRelationshipIds']);
        $t->same(['word/embeddings/review.xlsx', 'word/embeddings/missing.bin'], $embedded['partNames']);

        $t->same('Excel.Sheet.12', $workbook['progId']);
        $t->same('_x0000_i1025', $workbook['shapeId']);
        $t->same('Content', $workbook['drawAspect']);
        $t->same('_review1', $workbook['objectId']);
        $t->same('OnCall', $workbook['updateMode']);
        $t->same($packageRel, $workbook['relationshipType']);
        $t->same('word/embeddings/review.xlsx?sheet=1#ole', $workbook['resolvedTarget']);
        $t->same('word/embeddings/review.xlsx', $workbook['targetPart']);
        $t->same('sheet=1', $workbook['targetQuery']);
        $t->same('ole', $workbook['targetFragment']);
        $t->same('?sheet=1#ole', $workbook['targetReferenceSuffix']);
        $t->same(true, $workbook['exists']);
        $t->same(strlen('fake embedded workbook bytes'), $workbook['bytes']);
        $t->same('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet; profile=embedded-workbook', $workbook['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $workbook['contentTypeBase']);
        $t->same(['profile' => 'embedded-workbook'], $workbook['contentTypeParameterMap']);
        $t->same('override', $workbook['contentTypeSource']);
        $t->same('word/embeddings/_rels/review.xlsx.rels', $workbook['relationshipsPart']);
        $t->same(0, $workbook['relationshipCount']);
        $t->same([], $workbook['issues']);

        $t->same('Package', $missing['progId']);
        $t->same('Icon', $missing['drawAspect']);
        $t->same($oleRel, $missing['relationshipType']);
        $t->same('word/embeddings/missing.bin', $missing['targetPart']);
        $t->same(false, $missing['exists']);
        $t->same('missing', $missing['contentTypeSource']);
        $t->same('bin', $missing['defaultExtension']);
        $t->same(['missing-in-package', 'missing-content-type'], $missing['issues']);

        $t->same(true, $unknown['referenced']);
        $t->same(null, $unknown['relationshipType']);
        $t->same(['unknown-relationship'], $unknown['issues']);

        $t->same(false, $remote['referenced']);
        $t->same(true, $remote['external']);
        $t->same('https://example.test/ole.bin?remote=1#object', $remote['target']);
        $t->same(null, $remote['targetPart']);
        $t->same(['external-embedded-object'], $remote['issues']);

        $t->same(1, $relationshipTypes[$packageRel]['existingTargetCount']);
        $t->same(['word/embeddings/review.xlsx'], $relationshipTypes[$packageRel]['existingTargetParts']);
        $t->same(2, $relationshipTypes[$oleRel]['count']);
        $t->same(1, $relationshipTypes[$oleRel]['externalCount']);
        $t->same(['word/embeddings/missing.bin'], $relationshipTypes[$oleRel]['missingTargetParts']);
    },
    'carries docx altchunk and embedded object imports into package provenance' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $altChunkRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk';
        $packageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';
        $oleRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject';
        $htmlChunk = '<article><h2>Reviewer HTML chunk</h2></article>';
        $workbookBytes = 'fake embedded workbook bytes';
        $oleBytes = 'fake ole object bytes';

        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/chunks/review.html" ContentType="text/html; charset=utf-8"/>' . "\n" .
            '  <Override PartName="/word/embeddings/review.xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>' . "\n" .
            '  <Override PartName="/word/embeddings/review.bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rAltHtml" Type="' . $altChunkRel . '" Target="chunks/review.html?slot=body#chunk"/>' . "\n" .
            '  <Relationship Id="rAltRemote" Type="' . $altChunkRel . '" Target="https://example.test/review.html?remote=1#chunk" TargetMode="External"/>' . "\n" .
            '  <Relationship Id="rEmbeddedWorkbook" Type="' . $packageRel . '" Target="embeddings/review.xlsx?sheet=1#ole"/>' . "\n" .
            '  <Relationship Id="rOleExisting" Type="' . $oleRel . '" Target="embeddings/review.bin"/>' . "\n" .
            '  <Relationship Id="rOleMissing" Type="' . $oleRel . '" Target="embeddings/missing.bin"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"',
            'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:o="urn:schemas-microsoft-com:office:office"',
            $parts['word/document.xml']
        );
        $parts['word/document.xml'] = str_replace(
            '    <w:tbl>',
            '    <w:altChunk r:id="rAltHtml"/>' . "\n" .
            '    <w:altChunk r:id="rAltMissing"/>' . "\n" .
            '    <w:p><w:r><w:object><o:OLEObject r:id="rEmbeddedWorkbook" ProgID="Excel.Sheet.12"/></w:object></w:r></w:p>' . "\n" .
            '    <w:p><w:r><w:object><o:OLEObject r:id="rOleExisting" ProgID="Package"/></w:object></w:r></w:p>' . "\n" .
            '    <w:p><w:r><w:object><o:OLEObject r:id="rOleMissing" ProgID="Package"/></w:object></w:r></w:p>' . "\n" .
            '    <w:tbl>',
            $parts['word/document.xml']
        );
        $parts['word/chunks/review.html'] = $htmlChunk;
        $parts['word/embeddings/review.xlsx'] = $workbookBytes;
        $parts['word/embeddings/review.bin'] = $oleBytes;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $alternativeFormats = $package['alternativeFormats'];
        $embeddedObjects = $package['embeddedObjects'];
        $html = $alternativeFormats['byRelationshipId']['rAltHtml'];
        $workbook = $embeddedObjects['byRelationshipId']['rEmbeddedWorkbook'];
        $ole = $embeddedObjects['byRelationshipId']['rOleExisting'];
        $missingOle = $embeddedObjects['byRelationshipId']['rOleMissing'];
        $inventory = $package['parts'];

        $t->same($docx['alternativeFormats'], $alternativeFormats);
        $t->same(3, $summary['alternativeFormatCount']);
        $t->same(2, $summary['alternativeFormatRelationshipCount']);
        $t->same(2, $summary['alternativeFormatReferencedCount']);
        $t->same(1, $summary['alternativeFormatExistingCount']);
        $t->same(0, $summary['alternativeFormatMissingCount']);
        $t->same(1, $summary['alternativeFormatExternalCount']);
        $t->same(2, $summary['alternativeFormatIssueCount']);
        $t->same(['external-altchunk', 'unknown-relationship'], $summary['alternativeFormatIssueCodes']);
        $t->same('alternative-format-import-bytes-blocked', $alternativeFormats['byteExposurePolicy']);
        $t->same('alternative-format-import-metadata-only', $alternativeFormats['reviewPolicy']);
        $t->same(strlen($htmlChunk), $html['bytes']);
        $t->same(sprintf('%08x', crc32($htmlChunk)), $html['crc32']);
        $t->same(hash('sha256', $htmlChunk), $html['sha256']);
        $t->same('text/html', $html['contentTypeBase']);
        $t->same(['charset' => 'utf-8'], $html['contentTypeParameterMap']);
        $t->same('alternative-format-import-bytes-blocked', $html['byteExposurePolicy']);
        $t->true(in_array('alternative-format-import', $inventory['word/chunks/review.html']['roles'], true), 'altChunk inventory role missing');

        $t->same($docx['embeddedObjects'], $embeddedObjects);
        $t->same(3, $summary['embeddedObjectCount']);
        $t->same(3, $summary['embeddedObjectRelationshipCount']);
        $t->same(3, $summary['embeddedObjectReferencedCount']);
        $t->same(2, $summary['embeddedObjectExistingCount']);
        $t->same(1, $summary['embeddedObjectMissingCount']);
        $t->same(0, $summary['embeddedObjectExternalCount']);
        $t->same(1, $summary['embeddedObjectIssueCount']);
        $t->same(['missing-content-type', 'missing-in-package'], $summary['embeddedObjectIssueCodes']);
        $t->same('embedded-object-bytes-blocked', $embeddedObjects['byteExposurePolicy']);
        $t->same('embedded-object-metadata-only', $embeddedObjects['reviewPolicy']);
        $t->same(strlen($workbookBytes), $workbook['bytes']);
        $t->same(sprintf('%08x', crc32($workbookBytes)), $workbook['crc32']);
        $t->same(hash('sha256', $workbookBytes), $workbook['sha256']);
        $t->same($packageRel, $workbook['relationshipType']);
        $t->same($oleRel, $ole['relationshipType']);
        $t->same('embedded-object-bytes-blocked', $ole['byteExposurePolicy']);
        $t->same(['missing-in-package', 'missing-content-type'], $missingOle['issues']);
        $t->true(in_array('embedded-package', $inventory['word/embeddings/review.xlsx']['roles'], true), 'embedded package inventory role missing');
        $t->true(in_array('embedded-object', $inventory['word/embeddings/review.bin']['roles'], true), 'embedded object inventory role missing');
    },
    'reports docx activex control package provenance as metadata only' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $controlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/control';
        $binaryRel = 'http://schemas.microsoft.com/office/2006/relationships/activeXControlBinary';
        $controlXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ax:ocx xmlns:ax="http://schemas.microsoft.com/office/2006/activeX" ax:classid="{11111111-2222-3333-4444-555555555555}" ax:persistence="persistPropertyBag"/>
XML;
        $unreferencedControlXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ax:ocx xmlns:ax="http://schemas.microsoft.com/office/2006/activeX" ax:classid="{22222222-3333-4444-5555-666666666666}" ax:persistence="persistStream"/>
XML;
        $badControlXml = '<notOcx xmlns="urn:example:activex-review"/>';
        $binaryBytes = 'activex binary state bytes';
        $badBinaryBytes = 'bad activex binary bytes';

        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/activeX/activeX1.xml" ContentType="application/vnd.ms-office.activeX+xml; profile=control"/>' . "\n" .
            '  <Override PartName="/word/activeX/missing.xml" ContentType="application/vnd.ms-office.activeX+xml"/>' . "\n" .
            '  <Override PartName="/word/activeX/bad.xml" ContentType="application/xml"/>' . "\n" .
            '  <Override PartName="/word/activeX/unreferenced.xml" ContentType="application/vnd.ms-office.activeX+xml"/>' . "\n" .
            '  <Override PartName="/word/activeX/activeX1.bin" ContentType="application/vnd.ms-office.activeX; profile=state"/>' . "\n" .
            '  <Override PartName="/word/activeX/bad.bin" ContentType="application/octet-stream"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rActiveX" Type="' . $controlRel . '" Target="activeX/activeX1.xml?control=1#ocx"/>' . "\n" .
            '  <Relationship Id="rMissingActiveX" Type="' . $controlRel . '" Target="activeX/missing.xml"/>' . "\n" .
            '  <Relationship Id="rExternalActiveX" Type="' . $controlRel . '" Target="https://example.test/activeX.xml?remote=1#control" TargetMode="External"/>' . "\n" .
            '  <Relationship Id="rBadActiveX" Type="' . $controlRel . '" Target="activeX/bad.xml"/>' . "\n" .
            '  <Relationship Id="rUnreferencedActiveX" Type="' . $controlRel . '" Target="activeX/unreferenced.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            '    <w:tbl>',
            '    <w:p><w:r><w:object><w:control r:id="rActiveX" w:name="ReviewButton" w:shapeid="_x0000_s2048"/></w:object></w:r></w:p>' . "\n" .
            '    <w:p><w:r><w:object><w:control r:id="rMissingActiveX" w:name="MissingControl"/></w:object></w:r></w:p>' . "\n" .
            '    <w:p><w:r><w:object><w:control r:id="rExternalActiveX" w:name="RemoteControl"/></w:object></w:r></w:p>' . "\n" .
            '    <w:p><w:r><w:object><w:control r:id="rBadActiveX" w:name="BadControl"/></w:object></w:r></w:p>' . "\n" .
            '    <w:p><w:r><w:object><w:control r:id="rUnknownActiveX" w:name="UnknownControl"/></w:object></w:r></w:p>' . "\n" .
            '    <w:tbl>',
            $parts['word/document.xml']
        );
        $parts['word/activeX/activeX1.xml'] = $controlXml;
        $parts['word/activeX/unreferenced.xml'] = $unreferencedControlXml;
        $parts['word/activeX/bad.xml'] = $badControlXml;
        $parts['word/activeX/_rels/activeX1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rActiveXBinary" Type="http://schemas.microsoft.com/office/2006/relationships/activeXControlBinary" Target="activeX1.bin?payload=1#bin"/>
  <Relationship Id="rMissingActiveXBinary" Type="http://schemas.microsoft.com/office/2006/relationships/activeXControlBinary" Target="missing.bin"/>
  <Relationship Id="rExternalActiveXBinary" Type="http://schemas.microsoft.com/office/2006/relationships/activeXControlBinary" Target="https://example.test/activeX.bin" TargetMode="External"/>
  <Relationship Id="rBadActiveXBinary" Type="http://schemas.microsoft.com/office/2006/relationships/activeXControlBinary" Target="bad.bin"/>
</Relationships>
XML;
        $parts['word/activeX/activeX1.bin'] = $binaryBytes;
        $parts['word/activeX/bad.bin'] = $badBinaryBytes;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $activeX = $docx['activeXControls'];
        $packageActiveX = $docx['packageProvenance']['activeXControls'];
        $summary = $docx['packageProvenance']['summary'];
        $control = $activeX['byRelationshipId']['rActiveX'];
        $missing = $activeX['byRelationshipId']['rMissingActiveX'];
        $external = $activeX['byRelationshipId']['rExternalActiveX'];
        $bad = $activeX['byRelationshipId']['rBadActiveX'];
        $unknown = $activeX['byRelationshipId']['rUnknownActiveX'];
        $unreferenced = $activeX['byRelationshipId']['rUnreferencedActiveX'];
        $binary = $control['binaries']['byRelationshipId']['rActiveXBinary'];
        $missingBinary = $control['binaries']['byRelationshipId']['rMissingActiveXBinary'];
        $externalBinary = $control['binaries']['byRelationshipId']['rExternalActiveXBinary'];
        $badBinary = $control['binaries']['byRelationshipId']['rBadActiveXBinary'];
        $relationshipTypes = $docx['packageProvenance']['relationshipTypes'];
        $inventory = $docx['packageProvenance']['parts'];

        $t->same($activeX, $packageActiveX);
        $t->same(6, $activeX['count']);
        $t->same(5, $activeX['relationshipCount']);
        $t->same(5, $activeX['referencedCount']);
        $t->same(1, $activeX['unreferencedRelationshipCount']);
        $t->same(3, $activeX['existingCount']);
        $t->same(1, $activeX['missingCount']);
        $t->same(1, $activeX['externalCount']);
        $t->same(1, $activeX['unresolvedCount']);
        $t->same(1, $activeX['unexpectedRootCount']);
        $t->same(1, $activeX['unexpectedContentTypeCount']);
        $t->same(4, $activeX['binaryCount']);
        $t->same(2, $activeX['existingBinaryCount']);
        $t->same(1, $activeX['missingBinaryCount']);
        $t->same(1, $activeX['externalBinaryCount']);
        $t->same(1, $activeX['unexpectedBinaryContentTypeCount']);
        $t->same(5, $activeX['issueCount']);
        $t->same([
            'external-activex-binary',
            'external-activex-control',
            'missing-activex-binary',
            'missing-binary-content-type',
            'missing-control-part',
            'unexpected-binary-content-type',
            'unexpected-control-content-type',
            'unexpected-control-root',
            'unknown-relationship',
        ], $activeX['issueCodes']);
        $t->same(['rActiveX', 'rMissingActiveX', 'rExternalActiveX', 'rBadActiveX', 'rUnknownActiveX', 'rUnreferencedActiveX'], $activeX['relationshipIds']);
        $t->same(['word/activeX/activeX1.xml', 'word/activeX/missing.xml', 'word/activeX/bad.xml', 'word/activeX/unreferenced.xml'], $activeX['partNames']);
        $t->same(['word/activeX/activeX1.bin', 'word/activeX/missing.bin', 'word/activeX/bad.bin'], $activeX['binaryPartNames']);
        $t->same('activex-bytes-blocked', $activeX['byteExposurePolicy']);
        $t->same('activex-metadata-only', $activeX['reviewPolicy']);

        $t->same('ReviewButton', $control['controlName']);
        $t->same('_x0000_s2048', $control['shapeId']);
        $t->same('activeX/activeX1.xml?control=1#ocx', $control['target']);
        $t->same('word/activeX/activeX1.xml?control=1#ocx', $control['resolvedTarget']);
        $t->same('word/activeX/activeX1.xml', $control['targetPart']);
        $t->same('control=1', $control['targetQuery']);
        $t->same('ocx', $control['targetFragment']);
        $t->same('?control=1#ocx', $control['targetReferenceSuffix']);
        $t->same(strlen($controlXml), $control['byteLength']);
        $t->same(sprintf('%08x', crc32($controlXml)), $control['crc32']);
        $t->same(hash('sha256', $controlXml), $control['sha256']);
        $t->same('application/vnd.ms-office.activeX+xml; profile=control', $control['contentType']);
        $t->same('application/vnd.ms-office.activex+xml', $control['contentTypeBase']);
        $t->same(['profile' => 'control'], $control['contentTypeParameterMap']);
        $t->same(true, $control['validXml']);
        $t->same(true, $control['validRoot']);
        $t->same('http://schemas.microsoft.com/office/2006/activeX', $control['rootNamespace']);
        $t->same('ocx', $control['rootLocalName']);
        $t->same('word/activeX/_rels/activeX1.xml.rels', $control['controlRelationshipsPart']);
        $t->same(4, $control['controlRelationshipCount']);
        $t->same([], $control['issues']);
        $t->same(false, $control['valid']);

        $t->same(4, $control['binaries']['count']);
        $t->same(['rActiveXBinary', 'rMissingActiveXBinary', 'rExternalActiveXBinary', 'rBadActiveXBinary'], $control['binaries']['relationshipIds']);
        $t->same('word/activeX/activeX1.bin?payload=1#bin', $binary['resolvedTarget']);
        $t->same('?payload=1#bin', $binary['targetReferenceSuffix']);
        $t->same(strlen($binaryBytes), $binary['byteLength']);
        $t->same(sprintf('%08x', crc32($binaryBytes)), $binary['crc32']);
        $t->same(hash('sha256', $binaryBytes), $binary['sha256']);
        $t->same('application/vnd.ms-office.activeX; profile=state', $binary['contentType']);
        $t->same('application/vnd.ms-office.activex', $binary['contentTypeBase']);
        $t->same(['profile' => 'state'], $binary['contentTypeParameterMap']);
        $t->same('activex-binary-bytes-blocked', $binary['byteExposurePolicy']);
        $t->same([], $binary['issues']);
        $t->same(['missing-activex-binary', 'missing-binary-content-type'], $missingBinary['issues']);
        $t->same(['external-activex-binary'], $externalBinary['issues']);
        $t->same(['unexpected-binary-content-type'], $badBinary['issues']);

        $t->same(['missing-control-part'], $missing['issues']);
        $t->same(['external-activex-control'], $external['issues']);
        $t->same(['unexpected-control-content-type', 'unexpected-control-root'], $bad['issues']);
        $t->same('notOcx', $bad['rootLocalName']);
        $t->same(['unknown-relationship'], $unknown['issues']);
        $t->same(false, $unreferenced['referenced']);
        $t->same(true, $unreferenced['exists']);
        $t->same([], $unreferenced['issues']);

        $t->same(6, $summary['activeXControlCount']);
        $t->same(5, $summary['activeXControlRelationshipCount']);
        $t->same(4, $summary['activeXBinaryCount']);
        $t->same($activeX['issueCodes'], $summary['activeXIssueCodes']);
        $t->same('control', $relationshipTypes[$controlRel]['label']);
        $t->same(5, $relationshipTypes[$controlRel]['count']);
        $t->same(4, $relationshipTypes[$controlRel]['internalCount']);
        $t->same(1, $relationshipTypes[$controlRel]['externalCount']);
        $t->same(['word/activeX/activeX1.xml', 'word/activeX/bad.xml', 'word/activeX/unreferenced.xml'], $relationshipTypes[$controlRel]['existingTargetParts']);
        $t->same('activeXControlBinary', $relationshipTypes[$binaryRel]['label']);
        $t->same(4, $relationshipTypes[$binaryRel]['count']);
        $t->same(['word/activeX/activeX1.bin', 'word/activeX/bad.bin'], $relationshipTypes[$binaryRel]['existingTargetParts']);
        $t->true(in_array('activex-control', $inventory['word/activeX/activeX1.xml']['roles'], true), 'ActiveX control inventory role missing');
        $t->true(in_array('activex-binary', $inventory['word/activeX/activeX1.bin']['roles'], true), 'ActiveX binary inventory role missing');
        $t->true(!isset($docx['media']['word/activeX/activeX1.bin']), 'ActiveX binary should not be exposed as document media');
    },
    'reports docx vba project package provenance as metadata only' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $projectRel = 'http://schemas.microsoft.com/office/2006/relationships/vbaProject';
        $signatureRel = 'http://schemas.microsoft.com/office/2006/relationships/vbaProjectSignature';
        $dataRel = 'http://schemas.microsoft.com/office/2006/relationships/wordVbaData';
        $projectBytes = 'vba project binary bytes';
        $signatureBytes = 'vba project signature bytes';
        $dataXml = '<wne:vbaSuppData xmlns:wne="http://schemas.microsoft.com/office/word/2006/wordml"/>';
        $badDataXml = '<review>not vba data</review>';

        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/vbaProject.bin" ContentType="application/vnd.ms-office.vbaProject; profile=macro-review"/>' . "\n" .
            '  <Override PartName="/word/vbaProjectSignature.bin" ContentType="application/vnd.ms-office.vbaProjectSignature; profile=signed"/>' . "\n" .
            '  <Override PartName="/word/vbaData.xml" ContentType="application/vnd.ms-word.vbaData+xml; profile=vba-data"/>' . "\n" .
            '  <Override PartName="/word/badVbaData.xml" ContentType="application/xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rVbaProject" Type="' . $projectRel . '" Target="vbaProject.bin?macro=1#project"/>' . "\n" .
            '  <Relationship Id="rMissingVbaProject" Type="' . $projectRel . '" Target="missingVbaProject.bin"/>' . "\n" .
            '  <Relationship Id="rRemoteVbaProject" Type="' . $projectRel . '" Target="https://example.test/vbaProject.bin?remote=1#project" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/vbaProject.bin'] = $projectBytes;
        $parts['word/_rels/vbaProject.bin.rels'] = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rVbaSignature" Type="{$signatureRel}" Target="vbaProjectSignature.bin?sig=1#signature"/>
  <Relationship Id="rMissingVbaSignature" Type="{$signatureRel}" Target="missingVbaProjectSignature.bin"/>
  <Relationship Id="rRemoteVbaSignature" Type="{$signatureRel}" Target="https://example.test/vbaProjectSignature.bin" TargetMode="External"/>
  <Relationship Id="rVbaData" Type="{$dataRel}" Target="vbaData.xml?slot=1#data"/>
  <Relationship Id="rBadVbaData" Type="{$dataRel}" Target="badVbaData.xml"/>
</Relationships>
XML;
        $parts['word/vbaProjectSignature.bin'] = $signatureBytes;
        $parts['word/vbaData.xml'] = $dataXml;
        $parts['word/badVbaData.xml'] = $badDataXml;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $vba = $docx['vbaProjects'];
        $summary = $docx['packageProvenance']['summary'];
        $project = $vba['byRelationshipId']['rVbaProject'];
        $missingProject = $vba['byRelationshipId']['rMissingVbaProject'];
        $remoteProject = $vba['byRelationshipId']['rRemoteVbaProject'];
        $signature = $project['signatureParts']['byRelationshipId']['rVbaSignature'];
        $missingSignature = $project['signatureParts']['byRelationshipId']['rMissingVbaSignature'];
        $remoteSignature = $project['signatureParts']['byRelationshipId']['rRemoteVbaSignature'];
        $data = $project['dataParts']['byRelationshipId']['rVbaData'];
        $badData = $project['dataParts']['byRelationshipId']['rBadVbaData'];
        $relationshipTypes = $docx['packageProvenance']['relationshipTypes'];
        $inventory = $docx['packageProvenance']['parts'];

        $t->same($vba, $docx['packageProvenance']['vbaProjects']);
        $t->same(3, $vba['count']);
        $t->same(3, $vba['relationshipCount']);
        $t->same(1, $vba['existingCount']);
        $t->same(1, $vba['missingCount']);
        $t->same(1, $vba['externalCount']);
        $t->same(1, $vba['missingContentTypeCount']);
        $t->same(3, $vba['signatureCount']);
        $t->same(1, $vba['existingSignatureCount']);
        $t->same(1, $vba['missingSignatureCount']);
        $t->same(1, $vba['externalSignatureCount']);
        $t->same(2, $vba['dataPartCount']);
        $t->same(2, $vba['existingDataPartCount']);
        $t->same(0, $vba['missingDataPartCount']);
        $t->same(0, $vba['externalDataPartCount']);
        $t->same(3, $vba['issueCount']);
        $t->same([
            'external-vba-project',
            'external-vba-project-signature',
            'missing-project-content-type',
            'missing-vba-project',
            'missing-vba-project-signature',
            'missing-vba-project-signature-content-type',
            'unexpected-vba-data-content-type',
        ], $vba['issueCodes']);
        $t->same(['rVbaProject', 'rMissingVbaProject', 'rRemoteVbaProject'], $vba['relationshipIds']);
        $t->same(['word/vbaProject.bin', 'word/missingVbaProject.bin'], $vba['partNames']);
        $t->same(['word/vbaProjectSignature.bin', 'word/missingVbaProjectSignature.bin'], $vba['signaturePartNames']);
        $t->same(['word/vbaData.xml', 'word/badVbaData.xml'], $vba['dataPartNames']);
        $t->same('vba-project-bytes-blocked', $vba['byteExposurePolicy']);
        $t->same('vba-project-metadata-only', $vba['reviewPolicy']);

        $t->same($projectRel, $project['relationshipType']);
        $t->same('vbaProject.bin?macro=1#project', $project['target']);
        $t->same('word/vbaProject.bin?macro=1#project', $project['resolvedTarget']);
        $t->same('word/vbaProject.bin', $project['targetPart']);
        $t->same('macro=1', $project['targetQuery']);
        $t->same('project', $project['targetFragment']);
        $t->same('?macro=1#project', $project['targetReferenceSuffix']);
        $t->same(true, $project['exists']);
        $t->same(strlen($projectBytes), $project['byteLength']);
        $t->same(sprintf('%08x', crc32($projectBytes)), $project['crc32']);
        $t->same(hash('sha256', $projectBytes), $project['sha256']);
        $t->same('application/vnd.ms-office.vbaProject; profile=macro-review', $project['contentType']);
        $t->same('application/vnd.ms-office.vbaproject', $project['contentTypeBase']);
        $t->same(['profile' => 'macro-review'], $project['contentTypeParameterMap']);
        $t->same('word/_rels/vbaProject.bin.rels', $project['projectRelationshipsPart']);
        $t->same(5, $project['projectRelationshipCount']);
        $t->same([], $project['issues']);
        $t->same(false, $project['valid']);

        $t->same(3, $project['signatureParts']['count']);
        $t->same(['rVbaSignature', 'rMissingVbaSignature', 'rRemoteVbaSignature'], $project['signatureParts']['relationshipIds']);
        $t->same('word/vbaProjectSignature.bin?sig=1#signature', $signature['resolvedTarget']);
        $t->same('?sig=1#signature', $signature['targetReferenceSuffix']);
        $t->same(strlen($signatureBytes), $signature['byteLength']);
        $t->same(sprintf('%08x', crc32($signatureBytes)), $signature['crc32']);
        $t->same(hash('sha256', $signatureBytes), $signature['sha256']);
        $t->same('application/vnd.ms-office.vbaProjectSignature; profile=signed', $signature['contentType']);
        $t->same('application/vnd.ms-office.vbaprojectsignature', $signature['contentTypeBase']);
        $t->same(['profile' => 'signed'], $signature['contentTypeParameterMap']);
        $t->same('vba-project-signature-bytes-blocked', $signature['byteExposurePolicy']);
        $t->same([], $signature['issues']);
        $t->same(['missing-vba-project-signature', 'missing-vba-project-signature-content-type'], $missingSignature['issues']);
        $t->same(['external-vba-project-signature'], $remoteSignature['issues']);

        $t->same(2, $project['dataParts']['count']);
        $t->same('word/vbaData.xml?slot=1#data', $data['resolvedTarget']);
        $t->same('?slot=1#data', $data['targetReferenceSuffix']);
        $t->same(strlen($dataXml), $data['byteLength']);
        $t->same(hash('sha256', $dataXml), $data['sha256']);
        $t->same('application/vnd.ms-word.vbaData+xml; profile=vba-data', $data['contentType']);
        $t->same('application/vnd.ms-word.vbadata+xml', $data['contentTypeBase']);
        $t->same('vba-data-bytes-blocked', $data['byteExposurePolicy']);
        $t->same([], $data['issues']);
        $t->same(['unexpected-vba-data-content-type'], $badData['issues']);

        $t->same(['missing-vba-project', 'missing-project-content-type'], $missingProject['issues']);
        $t->same(['external-vba-project'], $remoteProject['issues']);
        $t->same(3, $summary['vbaProjectCount']);
        $t->same(3, $summary['vbaProjectRelationshipCount']);
        $t->same(3, $summary['vbaProjectSignatureCount']);
        $t->same(2, $summary['vbaDataPartCount']);
        $t->same($vba['issueCodes'], $summary['vbaProjectIssueCodes']);

        $t->same('vbaProject', $relationshipTypes[$projectRel]['label']);
        $t->same(3, $relationshipTypes[$projectRel]['count']);
        $t->same(2, $relationshipTypes[$projectRel]['internalCount']);
        $t->same(1, $relationshipTypes[$projectRel]['externalCount']);
        $t->same(['word/vbaProject.bin'], $relationshipTypes[$projectRel]['existingTargetParts']);
        $t->same(['word/missingVbaProject.bin'], $relationshipTypes[$projectRel]['missingTargetParts']);
        $t->same('vbaProjectSignature', $relationshipTypes[$signatureRel]['label']);
        $t->same(3, $relationshipTypes[$signatureRel]['count']);
        $t->same(['word/vbaProjectSignature.bin'], $relationshipTypes[$signatureRel]['existingTargetParts']);
        $t->same('wordVbaData', $relationshipTypes[$dataRel]['label']);
        $t->same(2, $relationshipTypes[$dataRel]['count']);
        $t->true(in_array('vba-project', $inventory['word/vbaProject.bin']['roles'], true), 'VBA project inventory role missing');
        $t->true(in_array('vba-project-signature', $inventory['word/vbaProjectSignature.bin']['roles'], true), 'VBA signature inventory role missing');
        $t->true(in_array('vba-data', $inventory['word/vbaData.xml']['roles'], true), 'VBA data inventory role missing');
        $t->true(!isset($docx['media']['word/vbaProject.bin']), 'VBA project bytes should not be exposed as document media');
    },
    'summarizes docx custom xml data store package parts for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $customXmlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
        $customXmlPropsRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps';
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/customXml/item1.xml" ContentType="application/xml; profile=review-data"/>' . "\n" .
            '  <Override PartName="/customXml/itemProps1.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>' . "\n" .
            '  <Override PartName="/customXml/missing-item.xml" ContentType="application/xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rCustomXml" Type="' . $customXmlRel . '" Target="../customXml/item1.xml?slot=1#payload"/>' . "\n" .
            '  <Relationship Id="rMissingCustomXml" Type="' . $customXmlRel . '" Target="../customXml/missing-item.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['customXml/item1.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<review:payload xmlns:review="urn:example:review">
  <review:title>Editorial review packet</review:title>
</review:payload>
XML;
        $parts['customXml/_rels/item1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rItemProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps1.xml#props"/>
</Relationships>
XML;
        $parts['customXml/itemProps1.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:datastoreItem ds:itemID="{11111111-2222-3333-4444-555555555555}" xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml">
  <ds:schemaRefs>
    <ds:schemaRef ds:uri="urn:example:review-schema"/>
    <ds:schemaRef ds:uri="urn:example:review-extra"/>
  </ds:schemaRefs>
</ds:datastoreItem>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $customXml = $docx['customXmlParts'];
        $packageCustomXml = $docx['packageProvenance']['customXmlParts'];
        $summary = $docx['packageProvenance']['summary'];
        $item = $customXml['byRelationshipId']['rCustomXml'];
        $missing = $customXml['byRelationshipId']['rMissingCustomXml'];
        $properties = $item['propertiesParts']['byRelationshipId']['rItemProps'];
        $relationshipTypes = $docx['packageProvenance']['relationshipTypes'];

        $t->same($customXml, $packageCustomXml);
        $t->same(2, $customXml['count']);
        $t->same(2, $customXml['relationshipCount']);
        $t->same(1, $customXml['existingCount']);
        $t->same(1, $customXml['missingCount']);
        $t->same(0, $customXml['externalCount']);
        $t->same(0, $customXml['invalidXmlCount']);
        $t->same(0, $customXml['missingContentTypeCount']);
        $t->same(0, $customXml['missingPropertiesRelationshipCount']);
        $t->same(1, $customXml['propertiesPartCount']);
        $t->same(1, $customXml['existingPropertiesPartCount']);
        $t->same(0, $customXml['missingPropertiesPartCount']);
        $t->same(1, $customXml['issueCount']);
        $t->same(['rCustomXml', 'rMissingCustomXml'], $customXml['relationshipIds']);
        $t->same(['customXml/item1.xml', 'customXml/missing-item.xml'], $customXml['partNames']);
        $t->same(2, $summary['customXmlPartCount']);
        $t->same(1, $summary['customXmlIssueCount']);

        $t->same('customXml/item1.xml', $item['partName']);
        $t->same('../customXml/item1.xml?slot=1#payload', $item['target']);
        $t->same('customXml/item1.xml?slot=1#payload', $item['resolvedTarget']);
        $t->same('?slot=1#payload', $item['targetReferenceSuffix']);
        $t->same(true, $item['exists']);
        $t->same(strlen($parts['customXml/item1.xml']), $item['bytes']);
        $t->same('application/xml; profile=review-data', $item['contentType']);
        $t->same('application/xml', $item['contentTypeBase']);
        $t->same(['profile' => 'review-data'], $item['contentTypeParameterMap']);
        $t->same(true, $item['validXml']);
        $t->same('urn:example:review', $item['rootNamespace']);
        $t->same('payload', $item['rootLocalName']);
        $t->same('customXml/_rels/item1.xml.rels', $item['relationshipsPart']);
        $t->same(1, $item['relationshipCount']);
        $t->same([], $item['issues']);

        $t->same(1, $item['propertiesParts']['count']);
        $t->same(['rItemProps'], $item['propertiesParts']['relationshipIds']);
        $t->same('customXml/itemProps1.xml', $properties['partName']);
        $t->same('itemProps1.xml#props', $properties['target']);
        $t->same('customXml/itemProps1.xml#props', $properties['resolvedTarget']);
        $t->same('#props', $properties['targetReferenceSuffix']);
        $t->same(true, $properties['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.customXmlProperties+xml', $properties['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.customxmlproperties+xml', $properties['contentTypeBase']);
        $t->same(true, $properties['contentTypeMatchesExpected']);
        $t->same(true, $properties['validXml']);
        $t->same(true, $properties['validRoot']);
        $t->same('datastoreItem', $properties['rootLocalName']);
        $t->same('{11111111-2222-3333-4444-555555555555}', $properties['itemId']);
        $t->same(['urn:example:review-schema', 'urn:example:review-extra'], $properties['schemaRefs']);
        $t->same([], $properties['issues']);

        $t->same('customXml/missing-item.xml', $missing['partName']);
        $t->same(false, $missing['exists']);
        $t->same('application/xml', $missing['contentType']);
        $t->same(null, $missing['validXml']);
        $t->same(0, $missing['propertiesParts']['count']);
        $t->same(['missing-item-part'], $missing['issues']);

        $t->same('customXml', $relationshipTypes[$customXmlRel]['label']);
        $t->same(2, $relationshipTypes[$customXmlRel]['count']);
        $t->same(['customXml/item1.xml'], $relationshipTypes[$customXmlRel]['existingTargetParts']);
        $t->same(['customXml/missing-item.xml'], $relationshipTypes[$customXmlRel]['missingTargetParts']);
        $t->same('customXmlProps', $relationshipTypes[$customXmlPropsRel]['label']);
        $t->same(['customXml/itemProps1.xml'], $relationshipTypes[$customXmlPropsRel]['existingTargetParts']);
    },
    'summarizes docx custom xml properties schema refs and diagnostics for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $customXmlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
        $customXmlPropsRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps';
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/customXml/item1.xml" ContentType="application/xml"/>' . "\n" .
            '  <Override PartName="/customXml/item2.xml" ContentType="application/xml"/>' . "\n" .
            '  <Override PartName="/customXml/itemProps1.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml; profile=schemas"/>' . "\n" .
            '  <Override PartName="/customXml/itemPropsNoId.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>' . "\n" .
            '  <Override PartName="/customXml/itemPropsBad.xml" ContentType="application/xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rCustomXmlA" Type="' . $customXmlRel . '" Target="../customXml/item1.xml"/>' . "\n" .
            '  <Relationship Id="rCustomXmlB" Type="' . $customXmlRel . '" Target="../customXml/item2.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['customXml/item1.xml'] = '<review><title>Schema packet</title></review>';
        $parts['customXml/item2.xml'] = '<review><title>Missing id packet</title></review>';
        $parts['customXml/_rels/item1.xml.rels'] = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rPropsSchemas" Type="{$customXmlPropsRel}" Target="itemProps1.xml?slot=main#props"/>
  <Relationship Id="rPropsMissing" Type="{$customXmlPropsRel}" Target="missingProps.xml"/>
  <Relationship Id="rPropsExternal" Type="{$customXmlPropsRel}" Target="https://example.test/itemProps.xml?remote=1#props" TargetMode="External"/>
  <Relationship Id="rPropsBad" Type="{$customXmlPropsRel}" Target="itemPropsBad.xml"/>
</Relationships>
XML;
        $parts['customXml/_rels/item2.xml.rels'] = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rPropsNoId" Type="{$customXmlPropsRel}" Target="itemPropsNoId.xml"/>
</Relationships>
XML;
        $parts['customXml/itemProps1.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:datastoreItem ds:itemID="{44444444-5555-6666-7777-888888888888}" xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml">
  <ds:schemaRefs>
    <ds:schemaRef ds:uri="urn:example:schema-a"/>
    <ds:schemaRef ds:uri="urn:example:schema-a"/>
    <ds:schemaRef ds:uri="urn:example:schema-b"/>
  </ds:schemaRefs>
</ds:datastoreItem>
XML;
        $parts['customXml/itemPropsNoId.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<ds:datastoreItem xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml">
  <ds:schemaRefs>
    <ds:schemaRef ds:uri="urn:example:schema-c"/>
  </ds:schemaRefs>
</ds:datastoreItem>
XML;
        $parts['customXml/itemPropsBad.xml'] = '<badProps xmlns="urn:example:bad-props"/>';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $customXml = $docx['customXmlParts'];
        $summary = $docx['packageProvenance']['summary'];
        $inventory = $docx['packageProvenance']['parts'];
        $relationshipTypes = $docx['packageProvenance']['relationshipTypes'];
        $schemas = $customXml['byRelationshipId']['rCustomXmlA']['propertiesParts']['byRelationshipId']['rPropsSchemas'];
        $missing = $customXml['byRelationshipId']['rCustomXmlA']['propertiesParts']['byRelationshipId']['rPropsMissing'];
        $external = $customXml['byRelationshipId']['rCustomXmlA']['propertiesParts']['byRelationshipId']['rPropsExternal'];
        $bad = $customXml['byRelationshipId']['rCustomXmlA']['propertiesParts']['byRelationshipId']['rPropsBad'];
        $noId = $customXml['byRelationshipId']['rCustomXmlB']['propertiesParts']['byRelationshipId']['rPropsNoId'];

        $t->same(2, $customXml['count']);
        $t->same(5, $customXml['propertiesPartCount']);
        $t->same(3, $customXml['existingPropertiesPartCount']);
        $t->same(1, $customXml['missingPropertiesPartCount']);
        $t->same(1, $customXml['externalPropertiesPartCount']);
        $t->same(0, $customXml['invalidPropertiesXmlCount']);
        $t->same(1, $customXml['invalidPropertiesRootCount']);
        $t->same(1, $customXml['missingPropertiesStoreItemIdCount']);
        $t->same(4, $customXml['schemaRefCount']);
        $t->same(3, $customXml['uniqueSchemaRefCount']);
        $t->same(['urn:example:schema-a'], $customXml['duplicateSchemaRefs']);
        $t->same(['urn:example:schema-a' => 2, 'urn:example:schema-b' => 1, 'urn:example:schema-c' => 1], $customXml['schemaRefCounts']);
        $t->same(7, $customXml['propertiesIssueCount']);
        $t->same([
            'duplicate-schema-ref',
            'external-properties',
            'missing-properties-part',
            'missing-store-item-id',
            'unexpected-content-type',
            'unexpected-root',
        ], $customXml['propertiesIssueCodes']);
        $t->same($customXml['propertiesIssueCodes'], $customXml['issueCodes']);

        $t->same(5, $summary['customXmlPropertiesPartCount']);
        $t->same(3, $summary['customXmlPropertiesExistingPartCount']);
        $t->same(1, $summary['customXmlPropertiesMissingPartCount']);
        $t->same(1, $summary['customXmlPropertiesExternalPartCount']);
        $t->same(1, $summary['customXmlPropertiesInvalidRootCount']);
        $t->same(1, $summary['customXmlPropertiesMissingStoreItemIdCount']);
        $t->same(4, $summary['customXmlSchemaRefCount']);
        $t->same(3, $summary['customXmlUniqueSchemaRefCount']);
        $t->same(1, $summary['customXmlDuplicateSchemaRefCount']);
        $t->same(['urn:example:schema-a'], $summary['customXmlDuplicateSchemaRefs']);
        $t->same(7, $summary['customXmlIssueCount']);
        $t->same(7, $summary['customXmlPropertiesIssueCount']);
        $t->same($customXml['propertiesIssueCodes'], $summary['customXmlPropertiesIssueCodes']);

        $t->same('customXml/itemProps1.xml', $schemas['partName']);
        $t->same('?slot=main#props', $schemas['targetReferenceSuffix']);
        $t->same('slot=main', $schemas['targetQuery']);
        $t->same('props', $schemas['targetFragment']);
        $t->same('application/vnd.openxmlformats-officedocument.customXmlProperties+xml; profile=schemas', $schemas['contentType']);
        $t->same(['profile' => 'schemas'], $schemas['contentTypeParameterMap']);
        $t->same(3, $schemas['schemaRefCount']);
        $t->same(['urn:example:schema-a', 'urn:example:schema-b'], $schemas['uniqueSchemaRefs']);
        $t->same(['urn:example:schema-a'], $schemas['duplicateSchemaRefs']);
        $t->same(['duplicate-schema-ref'], $schemas['issues']);

        $t->same('customXml/missingProps.xml', $missing['partName']);
        $t->same(false, $missing['exists']);
        $t->same(['unexpected-content-type', 'missing-properties-part'], $missing['issues']);
        $t->same(true, $external['external']);
        $t->same(null, $external['partName']);
        $t->same('remote=1', $external['targetQuery']);
        $t->same(['external-properties'], $external['issues']);
        $t->same('customXml/itemPropsBad.xml', $bad['partName']);
        $t->same('application/xml', $bad['contentTypeBase']);
        $t->same(false, $bad['validRoot']);
        $t->same(['unexpected-content-type', 'unexpected-root'], $bad['issues']);
        $t->same('customXml/itemPropsNoId.xml', $noId['partName']);
        $t->same(null, $noId['itemId']);
        $t->same(['urn:example:schema-c'], $noId['schemaRefs']);
        $t->same(['missing-store-item-id'], $noId['issues']);

        $t->true(in_array('custom-xml-part', $inventory['customXml/item1.xml']['roles'], true), 'custom XML item inventory role missing');
        $t->true(in_array('custom-xml-properties', $inventory['customXml/itemProps1.xml']['roles'], true), 'custom XML properties inventory role missing');
        $t->same(2, $summary['roleCounts']['custom-xml-part']);
        $t->same(3, $summary['roleCounts']['custom-xml-properties']);
        $t->same(5, $relationshipTypes[$customXmlPropsRel]['count']);
        $t->same(4, $relationshipTypes[$customXmlPropsRel]['internalCount']);
        $t->same(1, $relationshipTypes[$customXmlPropsRel]['externalCount']);
        $t->same(['customXml/itemProps1.xml', 'customXml/itemPropsBad.xml', 'customXml/itemPropsNoId.xml'], $relationshipTypes[$customXmlPropsRel]['existingTargetParts']);
        $t->same(['customXml/missingProps.xml'], $relationshipTypes[$customXmlPropsRel]['missingTargetParts']);
    },
    'summarizes duplicate docx custom xml store item ids for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $customXmlRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml';
        $customXmlPropsRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps';
        $duplicateItemId = '{22222222-3333-4444-5555-666666666666}';
        $uniqueItemId = '{33333333-4444-5555-6666-777777777777}';
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/customXml/item1.xml" ContentType="application/xml"/>' . "\n" .
            '  <Override PartName="/customXml/item2.xml" ContentType="application/xml"/>' . "\n" .
            '  <Override PartName="/customXml/item3.xml" ContentType="application/xml"/>' . "\n" .
            '  <Override PartName="/customXml/itemProps1.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>' . "\n" .
            '  <Override PartName="/customXml/itemProps2.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>' . "\n" .
            '  <Override PartName="/customXml/itemProps3.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rCustomXmlA" Type="' . $customXmlRel . '" Target="../customXml/item1.xml"/>' . "\n" .
            '  <Relationship Id="rCustomXmlB" Type="' . $customXmlRel . '" Target="../customXml/item2.xml"/>' . "\n" .
            '  <Relationship Id="rCustomXmlC" Type="' . $customXmlRel . '" Target="../customXml/item3.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['customXml/item1.xml'] = '<review><title>Duplicate A</title></review>';
        $parts['customXml/item2.xml'] = '<review><title>Duplicate B</title></review>';
        $parts['customXml/item3.xml'] = '<review><title>Unique C</title></review>';
        $parts['customXml/_rels/item1.xml.rels'] = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rPropsA" Type="{$customXmlPropsRel}" Target="itemProps1.xml"/>
</Relationships>
XML;
        $parts['customXml/_rels/item2.xml.rels'] = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rPropsB" Type="{$customXmlPropsRel}" Target="itemProps2.xml"/>
</Relationships>
XML;
        $parts['customXml/_rels/item3.xml.rels'] = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rPropsC" Type="{$customXmlPropsRel}" Target="itemProps3.xml"/>
</Relationships>
XML;
        $parts['customXml/itemProps1.xml'] = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ds:datastoreItem ds:itemID="{$duplicateItemId}" xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml"/>
XML;
        $parts['customXml/itemProps2.xml'] = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ds:datastoreItem ds:itemID="{$duplicateItemId}" xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml"/>
XML;
        $parts['customXml/itemProps3.xml'] = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<ds:datastoreItem ds:itemID="{$uniqueItemId}" xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml"/>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $customXml = $docx['customXmlParts'];
        $summary = $docx['packageProvenance']['summary'];
        $duplicateRefs = $customXml['duplicateStoreItemIdReferences'][$duplicateItemId];
        $first = $customXml['byRelationshipId']['rCustomXmlA'];
        $second = $customXml['byRelationshipId']['rCustomXmlB'];
        $third = $customXml['byRelationshipId']['rCustomXmlC'];

        $t->same($customXml, $docx['packageProvenance']['customXmlParts']);
        $t->same(3, $customXml['count']);
        $t->same(3, $customXml['propertiesPartCount']);
        $t->same([$duplicateItemId, $uniqueItemId], $customXml['storeItemIds']);
        $t->same(1, $customXml['duplicateStoreItemIdCount']);
        $t->same([$duplicateItemId], $customXml['duplicateStoreItemIds']);
        $t->same(4, $customXml['issueCount']);
        $t->same(1, $summary['customXmlDuplicateStoreItemIdCount']);
        $t->same([$duplicateItemId], $summary['customXmlDuplicateStoreItemIds']);

        $t->same(2, count($duplicateRefs));
        $t->same(['rCustomXmlA', 'rCustomXmlB'], array_column($duplicateRefs, 'customXmlRelationshipId'));
        $t->same(['customXml/item1.xml', 'customXml/item2.xml'], array_column($duplicateRefs, 'customXmlPartName'));
        $t->same(['rPropsA', 'rPropsB'], array_column($duplicateRefs, 'propertiesRelationshipId'));
        $t->same(['customXml/itemProps1.xml', 'customXml/itemProps2.xml'], array_column($duplicateRefs, 'propertiesPartName'));

        $t->same(['duplicate-store-item-id'], $first['issues']);
        $t->same(['duplicate-store-item-id'], $second['issues']);
        $t->same([], $third['issues']);
        $t->same(['duplicate-store-item-id'], $first['propertiesParts']['byRelationshipId']['rPropsA']['issues']);
        $t->same(['duplicate-store-item-id'], $second['propertiesParts']['byRelationshipId']['rPropsB']['issues']);
        $t->same([], $third['propertiesParts']['byRelationshipId']['rPropsC']['issues']);
    },
    'resolves docx numbering from the document relationship target' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/review-numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="review-numbering.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/review-numbering.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="30">
    <w:lvl w:ilvl="0"><w:start w:val="4"/><w:numFmt w:val="upperLetter"/><w:lvlText w:val="%1."/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="40">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="-"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7"><w:abstractNumId w:val="30"/></w:num>
  <w:num w:numId="8"><w:abstractNumId w:val="40"/></w:num>
</w:numbering>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $ordered = $document->children[2];
        $bullet = $document->children[3];

        $t->same('word/review-numbering.xml', $docx['numberingPart']);
        $t->same('rNumbering', $docx['numberingRelationship']['id']);
        $t->same('word/document.xml', $docx['numberingRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['numberingRelationship']['relationshipsPart']);
        $t->same('review-numbering.xml', $docx['numberingRelationship']['target']);
        $t->same('word/review-numbering.xml', $docx['numberingRelationship']['targetPart']);
        $t->same(true, $docx['numberingRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml', $docx['numberingRelationship']['contentType']);
        $t->same('ordered_list', $ordered->type);
        $t->same(4, $ordered->attr('start'));
        $t->same('upper_alpha', $ordered->attr('style'));
        $t->same('period', $ordered->attr('delimiter'));
        $t->same('bullet_list', $bullet->type);
        $t->same('-', $bullet->attr('bulletChar'));
    },
    'resolves docx styles and core properties from relationship targets' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/customXml/review-core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/theme/review-styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            'Target="docProps/core.xml"',
            'Target="customXml/review-core.xml"',
            $parts['_rels/.rels']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="theme/review-styles.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['customXml/review-core.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/">
  <dc:title>Relationship DOCX Batch</dc:title>
  <dc:creator>Relationship Editor</dc:creator>
</cp:coreProperties>
XML;
        $parts['word/theme/review-styles.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="Relationship Heading"/>
    <w:pPr><w:outlineLvl w:val="1"/></w:pPr>
  </w:style>
</w:styles>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $meta = $document->attr('meta');
        $docx = $document->attr('docx');
        $heading = $document->children[0];

        $t->same('Relationship DOCX Batch', $meta['title']);
        $t->same(['Relationship Editor'], $meta['authors']);
        $t->same('customXml/review-core.xml', $docx['corePropertiesPart']);
        $t->same('rCore', $docx['corePropertiesRelationship']['id']);
        $t->same('/', $docx['corePropertiesRelationship']['sourcePart']);
        $t->same('_rels/.rels', $docx['corePropertiesRelationship']['relationshipsPart']);
        $t->same('customXml/review-core.xml', $docx['corePropertiesRelationship']['target']);
        $t->same('customXml/review-core.xml', $docx['corePropertiesRelationship']['targetPart']);
        $t->same(true, $docx['corePropertiesRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $docx['corePropertiesRelationship']['contentType']);
        $t->same('word/theme/review-styles.xml', $docx['stylesPart']);
        $t->same('rStyles', $docx['stylesRelationship']['id']);
        $t->same('word/document.xml', $docx['stylesRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['stylesRelationship']['relationshipsPart']);
        $t->same('theme/review-styles.xml', $docx['stylesRelationship']['target']);
        $t->same('word/theme/review-styles.xml', $docx['stylesRelationship']['targetPart']);
        $t->same(true, $docx['stylesRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $docx['stylesRelationship']['contentType']);
        $t->same(2, $heading->attr('level'));
        $t->same('Relationship Heading', $heading->attr('docxStyleName'));
    },
    'reports docx extended and custom package properties from root relationships' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/customXml/review-app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>' . "\n" .
            '  <Override PartName="/docProps/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.custom-properties+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rApp" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="customXml/review-app.xml?profile=review#extended"/>' . "\n" .
            '  <Relationship Id="rCustom" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="docProps/custom.xml#custom"/>' . "\n" .
            '</Relationships>',
            $parts['_rels/.rels']
        );
        $parts['customXml/review-app.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"
  xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <Template>Review.dotm</Template>
  <Manager>Migration Lead</Manager>
  <Company>WordPress Migration Desk</Company>
  <Pages>12</Pages>
  <Words>3456</Words>
  <Characters>12000</Characters>
  <CharactersWithSpaces>13025</CharactersWithSpaces>
  <Lines>123</Lines>
  <Paragraphs>48</Paragraphs>
  <DocSecurity>4</DocSecurity>
  <Application>Microsoft Word</Application>
  <AppVersion>16.0000</AppVersion>
  <HyperlinkBase>https://example.test/review/</HyperlinkBase>
  <ScaleCrop>false</ScaleCrop>
  <LinksUpToDate>false</LinksUpToDate>
  <SharedDoc>0</SharedDoc>
  <HyperlinksChanged>true</HyperlinksChanged>
  <HeadingPairs>
    <vt:vector size="4" baseType="variant">
      <vt:variant><vt:lpstr>Title</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>2</vt:i4></vt:variant>
      <vt:variant><vt:lpstr>Heading 1</vt:lpstr></vt:variant>
      <vt:variant><vt:i4>4</vt:i4></vt:variant>
    </vt:vector>
  </HeadingPairs>
  <TitlesOfParts>
    <vt:vector size="2" baseType="lpstr">
      <vt:lpstr>DOCX source packet</vt:lpstr>
      <vt:lpstr>Reviewer checklist</vt:lpstr>
    </vt:vector>
  </TitlesOfParts>
</Properties>
XML;
        $parts['docProps/custom.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties"
  xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="2" name="ImportStatus"><vt:lpwstr>needs-media-review</vt:lpwstr></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="3" name="ReviewBatch"><vt:i4>42</vt:i4></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="4" name="Approved"><vt:bool>false</vt:bool></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="5" name="ReviewTimestamp"><vt:filetime>2026-06-07T00:00:00Z</vt:filetime></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="6" name="ImportStatus"><vt:lpwstr>approved-for-staging</vt:lpwstr></property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="7" name=""><vt:lpwstr>ignored-empty-name</vt:lpwstr></property>
</Properties>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $meta = $document->attr('meta');
        $extended = $docx['extendedProperties'];
        $custom = $docx['customProperties'];

        $t->same('customXml/review-app.xml', $docx['extendedPropertiesPart']);
        $t->same('rApp', $docx['extendedPropertiesRelationship']['id']);
        $t->same('/', $docx['extendedPropertiesRelationship']['sourcePart']);
        $t->same('_rels/.rels', $docx['extendedPropertiesRelationship']['relationshipsPart']);
        $t->same('customXml/review-app.xml?profile=review#extended', $docx['extendedPropertiesRelationship']['target']);
        $t->same('customXml/review-app.xml?profile=review#extended', $docx['extendedPropertiesRelationship']['resolvedTarget']);
        $t->same('customXml/review-app.xml', $docx['extendedPropertiesRelationship']['targetPart']);
        $t->same(true, $docx['extendedPropertiesRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.extended-properties+xml', $docx['extendedPropertiesRelationship']['contentType']);
        $t->same('Review.dotm', $extended['template']);
        $t->same('Migration Lead', $extended['manager']);
        $t->same('WordPress Migration Desk', $extended['company']);
        $t->same(12, $extended['pages']);
        $t->same(3456, $extended['words']);
        $t->same(12000, $extended['characters']);
        $t->same(13025, $extended['charactersWithSpaces']);
        $t->same(123, $extended['lines']);
        $t->same(48, $extended['paragraphs']);
        $t->same(4, $extended['docSecurity']);
        $t->same('Microsoft Word', $extended['application']);
        $t->same('16.0000', $extended['appVersion']);
        $t->same('https://example.test/review/', $extended['hyperlinkBase']);
        $t->same(false, $extended['scaleCrop']);
        $t->same(false, $extended['linksUpToDate']);
        $t->same(false, $extended['sharedDoc']);
        $t->same(true, $extended['hyperlinksChanged']);
        $t->same('Title', $extended['headingPairs'][0]['name']);
        $t->same(2, $extended['headingPairs'][0]['count']);
        $t->same('Heading 1', $extended['headingPairs'][1]['name']);
        $t->same(4, $extended['headingPairs'][1]['count']);
        $t->same(['DOCX source packet', 'Reviewer checklist'], $extended['titlesOfParts']);
        $t->same('Microsoft Word', $meta['docxExtendedProperties']['application']);

        $t->same('docProps/custom.xml', $docx['customPropertiesPart']);
        $t->same('rCustom', $docx['customPropertiesRelationship']['id']);
        $t->same('docProps/custom.xml#custom', $docx['customPropertiesRelationship']['target']);
        $t->same('docProps/custom.xml', $docx['customPropertiesRelationship']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.custom-properties+xml', $docx['customPropertiesRelationship']['contentType']);
        $t->same(5, $custom['count']);
        $t->same(1, $custom['duplicateNameCount']);
        $t->same(['ImportStatus'], $custom['duplicateNames']);
        $t->same('needs-media-review', $custom['byName']['ImportStatus']);
        $t->same(42, $custom['byName']['ReviewBatch']);
        $t->same(false, $custom['byName']['Approved']);
        $t->same('2026-06-07T00:00:00Z', $custom['byName']['ReviewTimestamp']);
        $t->same($custom['byName'], $meta['customProperties']);
        $t->same('ImportStatus', $custom['items'][0]['name']);
        $t->same(2, $custom['items'][0]['pid']);
        $t->same('lpwstr', $custom['items'][0]['valueType']);
        $t->same(false, $custom['items'][0]['duplicate']);
        $t->same('ReviewBatch', $custom['items'][1]['name']);
        $t->same('i4', $custom['items'][1]['valueType']);
        $t->same(42, $custom['items'][1]['value']);
        $t->same('Approved', $custom['items'][2]['name']);
        $t->same('bool', $custom['items'][2]['valueType']);
        $t->same(false, $custom['items'][2]['value']);
        $t->same('ImportStatus', $custom['items'][4]['name']);
        $t->same(true, $custom['items'][4]['duplicate']);
        $t->same('approved-for-staging', $custom['items'][4]['value']);
    },
    'preserves docx custom property vector and array values from package properties' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/docProps/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.custom-properties+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rCustomVectors" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="docProps/custom.xml"/>' . "\n" .
            '</Relationships>',
            $parts['_rels/.rels']
        );
        $parts['docProps/custom.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/custom-properties"
  xmlns:vt="http://schemas.openxmlformats.org/officeDocument/2006/docPropsVTypes">
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="2" name="ReviewTags">
    <vt:vector size="3" baseType="lpwstr">
      <vt:lpwstr>needs-media-review</vt:lpwstr>
      <vt:lpwstr>legal</vt:lpwstr>
      <vt:lpwstr>priority</vt:lpwstr>
    </vt:vector>
  </property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="3" name="MixedReview">
    <vt:vector size="4" baseType="variant">
      <vt:variant><vt:lpwstr>stage</vt:lpwstr></vt:variant>
      <vt:variant><vt:i4>7</vt:i4></vt:variant>
      <vt:variant><vt:bool>true</vt:bool></vt:variant>
      <vt:variant><vt:filetime>2026-06-11T20:03:56Z</vt:filetime></vt:variant>
    </vt:vector>
  </property>
  <property fmtid="{D5CDD505-2E9C-101B-9397-08002B2CF9AE}" pid="4" name="ReviewerIds">
    <vt:array lBound="0" size="2" baseType="i4">
      <vt:i4>42</vt:i4>
      <vt:i4>84</vt:i4>
    </vt:array>
  </property>
</Properties>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $meta = $document->attr('meta');
        $custom = $docx['customProperties'];
        $tags = $custom['items'][0];
        $mixed = $custom['items'][1];
        $reviewerIds = $custom['items'][2];

        $t->same('docProps/custom.xml', $docx['customPropertiesPart']);
        $t->same('rCustomVectors', $docx['customPropertiesRelationship']['id']);
        $t->same(3, $custom['count']);
        $t->same(0, $custom['duplicateNameCount']);
        $t->same(['needs-media-review', 'legal', 'priority'], $custom['byName']['ReviewTags']);
        $t->same(['stage', 7, true, '2026-06-11T20:03:56Z'], $custom['byName']['MixedReview']);
        $t->same([42, 84], $custom['byName']['ReviewerIds']);

        $t->same('ReviewTags', $tags['name']);
        $t->same('vector', $tags['valueType']);
        $t->same('lpwstr', $tags['valueBaseType']);
        $t->same(3, $tags['declaredValueCount']);
        $t->same(3, $tags['valueCount']);
        $t->same(['lpwstr', 'lpwstr', 'lpwstr'], $tags['valueItemTypes']);
        $t->same(['needs-media-review', 'legal', 'priority'], $tags['value']);

        $t->same('MixedReview', $mixed['name']);
        $t->same('variant', $mixed['valueBaseType']);
        $t->same(4, $mixed['declaredValueCount']);
        $t->same(['lpwstr', 'i4', 'bool', 'filetime'], $mixed['valueItemTypes']);
        $t->same(['stage', 7, true, '2026-06-11T20:03:56Z'], $mixed['value']);

        $t->same('ReviewerIds', $reviewerIds['name']);
        $t->same('array', $reviewerIds['valueType']);
        $t->same('i4', $reviewerIds['valueBaseType']);
        $t->same(0, $reviewerIds['lowerBound']);
        $t->same(2, $reviewerIds['declaredValueCount']);
        $t->same(2, $reviewerIds['valueCount']);
        $t->same(['i4', 'i4'], $reviewerIds['valueItemTypes']);
        $t->same([42, 84], $reviewerIds['value']);

        $t->same($custom['byName'], $meta['customProperties']);
        $t->same($custom, $meta['docxCustomProperties']);
    },
    'resolves docx settings and font table from relationship targets' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/docSettings/review-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>' . "\n" .
            '  <Override PartName="/word/fonts/review-fonts.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="../docSettings/review-settings.xml?profile=team#settings"/>' . "\n" .
            '  <Relationship Id="rFontTable" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable" Target="fonts/review-fonts.xml#fontTable"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['docSettings/review-settings.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:trackRevisions/>
  <w:doNotTrackMoves w:val="true"/>
  <w:doNotTrackFormatting w:val="0"/>
  <w:evenAndOddHeaders/>
  <w:updateFields w:val="1"/>
  <w:defaultTabStop w:val="720"/>
  <w:decimalSymbol w:val=","/>
  <w:listSeparator w:val=";"/>
  <w:zoom w:percent="125" w:val="bestFit"/>
  <w:documentProtection w:edit="readOnly" w:enforcement="1" w:cryptProviderType="rsaFull" w:cryptAlgorithmClass="hash" w:cryptAlgorithmType="typeAny" w:cryptAlgorithmSid="14" w:cryptSpinCount="100000"/>
  <w:compat>
    <w:compatSetting w:name="compatibilityMode" w:uri="http://schemas.microsoft.com/office/word" w:val="15"/>
  </w:compat>
  <w:docVars>
    <w:docVar w:name="ReviewBatch" w:val="wp-import"/>
  </w:docVars>
</w:settings>
XML;
        $parts['word/fonts/review-fonts.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:fonts xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:font w:name="Aptos">
    <w:altName w:val="Body Font"/>
    <w:charset w:val="00"/>
    <w:family w:val="swiss"/>
    <w:pitch w:val="variable"/>
    <w:panose1 w:val="020F0502020204030204"/>
  </w:font>
  <w:font w:name="Courier New">
    <w:charset w:val="00"/>
    <w:family w:val="modern"/>
    <w:pitch w:val="fixed"/>
  </w:font>
</w:fonts>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $settings = $docx['settings'];
        $fontTable = $docx['fontTable'];

        $t->same('docSettings/review-settings.xml', $docx['settingsPart']);
        $t->same('rSettings', $docx['settingsRelationship']['id']);
        $t->same('word/document.xml', $docx['settingsRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['settingsRelationship']['relationshipsPart']);
        $t->same('../docSettings/review-settings.xml?profile=team#settings', $docx['settingsRelationship']['target']);
        $t->same('docSettings/review-settings.xml?profile=team#settings', $docx['settingsRelationship']['resolvedTarget']);
        $t->same('docSettings/review-settings.xml', $docx['settingsRelationship']['targetPart']);
        $t->same(true, $docx['settingsRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml', $docx['settingsRelationship']['contentType']);
        $t->same(true, $settings['trackRevisions']);
        $t->same(true, $settings['doNotTrackMoves']);
        $t->same(false, $settings['doNotTrackFormatting']);
        $t->same(true, $settings['evenAndOddHeaders']);
        $t->same(true, $settings['updateFields']);
        $t->same(720, $settings['defaultTabStopTwips']);
        $t->same(',', $settings['decimalSymbol']);
        $t->same(';', $settings['listSeparator']);
        $t->same(125, $settings['zoom']['percent']);
        $t->same('bestFit', $settings['zoom']['value']);
        $t->same('readOnly', $settings['documentProtection']['edit']);
        $t->same(true, $settings['documentProtection']['enforcement']);
        $t->same('rsaFull', $settings['documentProtection']['cryptProviderType']);
        $t->same(14, $settings['documentProtection']['cryptAlgorithmSid']);
        $t->same(100000, $settings['documentProtection']['cryptSpinCount']);
        $t->same('compatibilityMode', $settings['compatibility'][0]['name']);
        $t->same('15', $settings['compatibility'][0]['value']);
        $t->same(['ReviewBatch' => 'wp-import'], $settings['documentVariables']);

        $t->same('word/fonts/review-fonts.xml', $docx['fontTablePart']);
        $t->same('rFontTable', $docx['fontTableRelationship']['id']);
        $t->same('fonts/review-fonts.xml#fontTable', $docx['fontTableRelationship']['target']);
        $t->same('word/fonts/review-fonts.xml#fontTable', $docx['fontTableRelationship']['resolvedTarget']);
        $t->same('word/fonts/review-fonts.xml', $docx['fontTableRelationship']['targetPart']);
        $t->same(true, $docx['fontTableRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml', $docx['fontTableRelationship']['contentType']);
        $t->same(2, $fontTable['fontCount']);
        $t->same(['Aptos', 'Courier New'], $fontTable['declaredNames']);
        $t->same('Body Font', $fontTable['byName']['Aptos']['alternateName']);
        $t->same('swiss', $fontTable['byName']['Aptos']['family']);
        $t->same('variable', $fontTable['byName']['Aptos']['pitch']);
        $t->same('020F0502020204030204', $fontTable['byName']['Aptos']['panose1']);
        $t->same('modern', $fontTable['byName']['Courier New']['family']);
        $t->same('fixed', $fontTable['byName']['Courier New']['pitch']);
    },
    'reports docx mail merge settings package relationships for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>' . "\n" .
            '  <Override PartName="/mailmerge/header-source.xml" ContentType="application/xml; profile=mail-merge-header"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/_rels/settings.xml.rels'] = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMergeSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/mailMergeSource" Target="file:///C:/legacy/review-source.xlsx" TargetMode="External"/>
  <Relationship Id="rMergeHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/mailMergeHeaderSource" Target="../mailmerge/header-source.xml?source=header#fields"/>
</Relationships>
XML;
        $connectString = 'Provider=Microsoft.ACE.OLEDB.12.0;Data Source=C:\legacy\review-source.xlsx;Mode=Read';
        $parts['word/settings.xml'] = <<<'XML'
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"
  xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:mailMerge>
    <w:mainDocumentType w:val="email"/>
    <w:destination w:val="email"/>
    <w:dataType w:val="native"/>
    <w:connectString w:val="Provider=Microsoft.ACE.OLEDB.12.0;Data Source=C:\legacy\review-source.xlsx;Mode=Read"/>
    <w:query w:val="SELECT * FROM [SourcePackets$] WHERE [NeedsReview] = 1"/>
    <w:dataSource r:id="rMergeSource"/>
    <w:headerSource r:id="rMergeHeader"/>
    <w:viewMergedData/>
    <w:linkToQuery/>
    <w:doNotSuppressBlankLines w:val="0"/>
    <w:activeRecord w:val="2"/>
    <w:checkErrors w:val="1"/>
    <w:mailSubject w:val="Review import packet"/>
  </w:mailMerge>
</w:settings>
XML;
        $parts['mailmerge/header-source.xml'] = '<headers><field name="ReviewerEmail"/><field name="SourcePacket"/></headers>';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $mailMerge = $docx['settings']['mailMerge'];
        $summary = $docx['packageProvenance']['summary'];
        $inventory = $docx['packageProvenance']['parts'];
        $headerSourceBytes = $parts['mailmerge/header-source.xml'];

        $t->same('email', $mailMerge['mainDocumentType']);
        $t->same('email', $mailMerge['destination']);
        $t->same('native', $mailMerge['dataType']);
        $t->same('SELECT * FROM [SourcePackets$] WHERE [NeedsReview] = 1', $mailMerge['query']);
        $t->same('Review import packet', $mailMerge['mailSubject']);
        $t->same(true, $mailMerge['connectStringPresent']);
        $t->same(strlen($connectString), $mailMerge['connectStringLength']);
        $t->same(hash('sha256', $connectString), $mailMerge['connectStringSha256']);
        $t->true(!isset($mailMerge['connectString']), 'DOCX mail merge metadata must not expose raw connection strings');
        $t->same(true, $mailMerge['viewMergedData']);
        $t->same(true, $mailMerge['linkToQuery']);
        $t->same(false, $mailMerge['doNotSuppressBlankLines']);
        $t->same(2, $mailMerge['activeRecord']);
        $t->same(1, $mailMerge['checkErrors']);
        $t->same(2, $mailMerge['relationshipCount']);
        $t->same(1, $mailMerge['issueCount']);
        $t->same(['external-target-unsafe-scheme'], $mailMerge['issueCodes']);

        $dataSource = $mailMerge['dataSource'];
        $t->same('rMergeSource', $dataSource['id']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/mailMergeSource', $dataSource['relationshipType']);
        $t->same('file:///C:/legacy/review-source.xlsx', $dataSource['target']);
        $t->same('External', $dataSource['targetMode']);
        $t->same(true, $dataSource['external']);
        $t->same(null, $dataSource['targetPart']);
        $t->same(false, $dataSource['exists']);
        $t->same('absolute-uri', $dataSource['externalTargetKind']);
        $t->same('file', $dataSource['externalTargetScheme']);
        $t->same(false, $dataSource['externalTargetAllowed']);
        $t->same(['external-target-unsafe-scheme'], $dataSource['issues']);

        $headerSource = $mailMerge['headerSource'];
        $t->same('rMergeHeader', $headerSource['id']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/mailMergeHeaderSource', $headerSource['relationshipType']);
        $t->same('../mailmerge/header-source.xml?source=header#fields', $headerSource['target']);
        $t->same('mailmerge/header-source.xml?source=header#fields', $headerSource['resolvedTarget']);
        $t->same('mailmerge/header-source.xml', $headerSource['targetPart']);
        $t->same('source=header', $headerSource['targetQuery']);
        $t->same('fields', $headerSource['targetFragment']);
        $t->same('?source=header#fields', $headerSource['targetReferenceSuffix']);
        $t->same(false, $headerSource['external']);
        $t->same(true, $headerSource['exists']);
        $t->same(strlen($headerSourceBytes), $headerSource['byteLength']);
        $t->same(sprintf('%08x', crc32($headerSourceBytes)), $headerSource['crc32']);
        $t->same(hash('sha256', $headerSourceBytes), $headerSource['sha256']);
        $t->same('application/xml; profile=mail-merge-header', $headerSource['contentType']);
        $t->same('application/xml', $headerSource['contentTypeBase']);
        $t->same(['profile' => 'mail-merge-header'], $headerSource['contentTypeParameterMap']);
        $t->same('override', $headerSource['contentTypeSource']);
        $t->same('mail-merge-header-source-metadata-only', $headerSource['reviewPolicy']);
        $t->same([], $headerSource['issues']);

        $t->same(2, $summary['mailMergeRelationshipCount']);
        $t->same(1, $summary['mailMergeIssueCount']);
        $t->same(['external-target-unsafe-scheme'], $summary['mailMergeIssueCodes']);
        $t->true(in_array('mail-merge-header-source', $inventory['mailmerge/header-source.xml']['roles'], true), 'mail merge header-source inventory role missing');
        $t->true(!isset($docx['media']['mailmerge/header-source.xml']), 'Mail merge header source must remain metadata-only');
    },
    'preserves docx settings review protection hyphenation and save policy metadata' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/docSettings/review-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="../docSettings/review-settings.xml?policy=review#settings"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['docSettings/review-settings.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:documentProtection w:edit="trackedChanges" w:enforcement="false" w:algorithmName="SHA-1" w:hashValue="doc-hash" w:saltValue="doc-salt" w:spinCount="5000"/>
  <w:writeProtection w:recommended="1" w:algorithmName="SHA-512" w:hashValue="write-hash" w:saltValue="write-salt" w:spinCount="100000"/>
  <w:revisionView w:markup="1" w:comments="0" w:insDel="true" w:formatting="false" w:inkAnnotations="on"/>
  <w:proofState w:spelling="clean" w:grammar="dirty"/>
  <w:autoHyphenation w:val="1"/>
  <w:doNotHyphenateCaps w:val="0"/>
  <w:consecutiveHyphenLimit w:val="3"/>
  <w:hyphenationZone w:val="360"/>
  <w:saveFormsData w:val="true"/>
  <w:savePreviewPicture/>
  <w:doNotEmbedSmartTags/>
  <w:embedTrueTypeFonts w:val="1"/>
  <w:embedSystemFonts w:val="0"/>
  <w:saveSubsetFonts w:val="true"/>
</w:settings>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $settings = $docx['settings'];
        $package = $docx['packageProvenance'];
        $inventory = $package['parts']['docSettings/review-settings.xml'];
        $selectedSettings = $package['selectedXmlParts']['byKind']['settings'];

        $t->same('docSettings/review-settings.xml', $docx['settingsPart']);
        $t->same('../docSettings/review-settings.xml?policy=review#settings', $docx['settingsRelationship']['target']);
        $t->same('docSettings/review-settings.xml', $docx['settingsRelationship']['targetPart']);
        $t->same('policy=review', $docx['settingsRelationship']['targetQuery']);
        $t->same('settings', $docx['settingsRelationship']['targetFragment']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml', $docx['settingsRelationship']['contentType']);
        $t->same('settings', $selectedSettings['rootLocalName']);
        $t->same(true, $selectedSettings['contentTypeMatchesExpected']);
        $t->true(in_array('settings', $inventory['roles'], true), 'settings inventory role missing');
        $t->same(1, $package['summary']['roleCounts']['settings']);

        $t->same('trackedChanges', $settings['documentProtection']['edit']);
        $t->same(false, $settings['documentProtection']['enforcement']);
        $t->same('SHA-1', $settings['documentProtection']['algorithmName']);
        $t->same('doc-hash', $settings['documentProtection']['hashValue']);
        $t->same('doc-salt', $settings['documentProtection']['saltValue']);
        $t->same(5000, $settings['documentProtection']['spinCount']);

        $t->same(true, $settings['writeProtection']['recommended']);
        $t->same('SHA-512', $settings['writeProtection']['algorithmName']);
        $t->same('write-hash', $settings['writeProtection']['hashValue']);
        $t->same('write-salt', $settings['writeProtection']['saltValue']);
        $t->same(100000, $settings['writeProtection']['spinCount']);

        $t->same(true, $settings['revisionView']['markup']);
        $t->same(false, $settings['revisionView']['comments']);
        $t->same(true, $settings['revisionView']['insDel']);
        $t->same(false, $settings['revisionView']['formatting']);
        $t->same(true, $settings['revisionView']['inkAnnotations']);
        $t->same('clean', $settings['proofing']['spelling']);
        $t->same('dirty', $settings['proofing']['grammar']);

        $t->same(true, $settings['hyphenation']['autoHyphenation']);
        $t->same(false, $settings['hyphenation']['doNotHyphenateCaps']);
        $t->same(3, $settings['hyphenation']['consecutiveHyphenLimit']);
        $t->same(360, $settings['hyphenation']['hyphenationZoneTwips']);
        $t->same(true, $settings['savePolicy']['saveFormsData']);
        $t->same(true, $settings['savePolicy']['savePreviewPicture']);
        $t->same(true, $settings['savePolicy']['doNotEmbedSmartTags']);
        $t->same(true, $settings['savePolicy']['embedTrueTypeFonts']);
        $t->same(false, $settings['savePolicy']['embedSystemFonts']);
        $t->same(true, $settings['savePolicy']['saveSubsetFonts']);
    },
    'preflights docx font table embedded font package relationships' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $fontKey = '{00112233-4455-6677-8899-AABBCCDDEEFF}';
        $fontBytes = 'OBFUSCATEDFONT';
        $badFontBytes = 'not an obfuscated font';
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/fonts/review-fonts.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml"/>' . "\n" .
            '  <Override PartName="/word/fonts/aptos-Regular.odttf" ContentType="application/vnd.openxmlformats-officedocument.obfuscatedFont"/>' . "\n" .
            '  <Override PartName="/word/fonts/bad.ttf" ContentType="font/ttf"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rFontTable" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable" Target="fonts/review-fonts.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/fonts/review-fonts.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:fonts xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:font w:name="Aptos">
    <w:family w:val="swiss"/>
    <w:embedRegular r:id="rRegular" w:fontKey="{00112233-4455-6677-8899-AABBCCDDEEFF}"/>
    <w:embedBold r:id="rMissingBold" w:fontKey="{11112233-4455-6677-8899-AABBCCDDEEFF}"/>
    <w:embedItalic r:id="rExternalItalic"/>
    <w:embedBoldItalic r:id="rWrongType"/>
  </w:font>
  <w:font w:name="No Relationship">
    <w:embedRegular w:fontKey="{22222222-4455-6677-8899-AABBCCDDEEFF}"/>
  </w:font>
</w:fonts>
XML;
        $parts['word/fonts/_rels/review-fonts.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rRegular" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/font" Target="aptos-Regular.odttf?style=regular#font"/>
  <Relationship Id="rMissingBold" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/font" Target="missing-bold.odttf"/>
  <Relationship Id="rExternalItalic" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/font" Target="https://fonts.example.test/aptos-Italic.ttf" TargetMode="External"/>
  <Relationship Id="rWrongType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="bad.ttf"/>
</Relationships>
XML;
        $parts['word/fonts/aptos-Regular.odttf'] = $fontBytes;
        $parts['word/fonts/bad.ttf'] = $badFontBytes;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $fontTable = $docx['fontTable'];
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $inventory = $package['parts'];
        $aptos = $fontTable['byName']['Aptos'];
        $regular = $aptos['embeddedFonts'][0];
        $missing = $aptos['embeddedFonts'][1];
        $external = $aptos['embeddedFonts'][2];
        $wrongType = $aptos['embeddedFonts'][3];
        $missingRelationship = $fontTable['byName']['No Relationship']['embeddedFonts'][0];

        $t->same('word/fonts/review-fonts.xml', $docx['fontTablePart']);
        $t->same('word/fonts/_rels/review-fonts.xml.rels', $fontTable['relationshipsPart']);
        $t->same(4, $fontTable['relationshipCount']);
        $t->same(2, $fontTable['fontCount']);
        $t->same(5, $fontTable['embeddedFontRelationshipCount']);
        $t->same(2, $fontTable['embeddedFontExistingCount']);
        $t->same(1, $fontTable['embeddedFontMissingCount']);
        $t->same(1, $fontTable['embeddedFontExternalCount']);
        $t->same(4, $fontTable['embeddedFontIssueCount']);
        $t->same([
            'external-embedded-font',
            'missing-content-type',
            'missing-in-package',
            'missing-relationship-id',
            'unexpected-embedded-font-content-type',
            'unexpected-relationship-type',
        ], $fontTable['embeddedFontIssueCodes']);
        $t->same(4, $aptos['embeddedFontCount']);

        $t->same('regular', $regular['style']);
        $t->same('rRegular', $regular['id']);
        $t->same('word/fonts/review-fonts.xml', $regular['sourcePart']);
        $t->same('word/fonts/_rels/review-fonts.xml.rels', $regular['relationshipsPart']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/font', $regular['relationshipType']);
        $t->same('aptos-Regular.odttf?style=regular#font', $regular['target']);
        $t->same('word/fonts/aptos-Regular.odttf?style=regular#font', $regular['resolvedTarget']);
        $t->same('word/fonts/aptos-Regular.odttf', $regular['targetPart']);
        $t->same('style=regular', $regular['targetQuery']);
        $t->same('font', $regular['targetFragment']);
        $t->same('?style=regular#font', $regular['targetReferenceSuffix']);
        $t->same('application/vnd.openxmlformats-officedocument.obfuscatedFont', $regular['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.obfuscatedfont', $regular['contentTypeBase']);
        $t->same(false, $regular['external']);
        $t->same(true, $regular['exists']);
        $t->same(strlen($fontBytes), $regular['byteLength']);
        $t->same(sprintf('%08x', crc32($fontBytes)), $regular['crc32']);
        $t->same(true, $regular['fontKeyPresent']);
        $t->same(hash('sha256', $fontKey), $regular['fontKeySha256']);
        $t->true(!isset($regular['fontKey']), 'Raw embedded font key should not be exposed');
        $t->same('embedded-font-bytes-blocked', $regular['byteExposurePolicy']);
        $t->same('embedded-font-metadata-only', $regular['reviewPolicy']);
        $t->same([], $regular['issues']);
        $t->same(true, $regular['valid']);

        $t->same('bold', $missing['style']);
        $t->same('word/fonts/missing-bold.odttf', $missing['targetPart']);
        $t->same(false, $missing['external']);
        $t->same(false, $missing['exists']);
        $t->same(['missing-in-package', 'missing-content-type'], $missing['issues']);

        $t->same('italic', $external['style']);
        $t->same('https://fonts.example.test/aptos-Italic.ttf', $external['target']);
        $t->same(true, $external['external']);
        $t->same(null, $external['targetPart']);
        $t->same(['external-embedded-font'], $external['issues']);

        $t->same('bold-italic', $wrongType['style']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $wrongType['relationshipType']);
        $t->same('word/fonts/bad.ttf', $wrongType['targetPart']);
        $t->same('font/ttf', $wrongType['contentType']);
        $t->same(['unexpected-relationship-type', 'unexpected-embedded-font-content-type'], $wrongType['issues']);

        $t->same('regular', $missingRelationship['style']);
        $t->same('', $missingRelationship['id']);
        $t->same(true, $missingRelationship['fontKeyPresent']);
        $t->same(['missing-relationship-id'], $missingRelationship['issues']);

        $t->true(in_array('font-table', $inventory['word/fonts/review-fonts.xml']['roles'], true), 'font table inventory role missing');
        $t->true(in_array('embedded-font', $inventory['word/fonts/aptos-Regular.odttf']['roles'], true), 'embedded font inventory role missing');
        $t->same('font', $package['relationshipTypes']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/font']['label']);
        $t->same(3, $package['relationshipTypes']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/font']['count']);
        $t->same(5, $summary['fontTableEmbeddedFontCount']);
        $t->same(2, $summary['fontTableEmbeddedFontExistingCount']);
        $t->same(1, $summary['fontTableEmbeddedFontMissingCount']);
        $t->same(1, $summary['fontTableEmbeddedFontExternalCount']);
        $t->same(4, $summary['fontTableEmbeddedFontIssueCount']);
        $t->same($fontTable['embeddedFontIssueCodes'], $summary['fontTableEmbeddedFontIssueCodes']);
    },
    'summarizes docx attached template relationships from settings part' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/docSettings/review-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>' . "\n" .
            '  <Override PartName="/docSettings/templates/review-template.dotx" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml; profile=attached-template"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="../docSettings/review-settings.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['docSettings/review-settings.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:updateFields w:val="true"/>
  <w:attachedTemplate r:id="rTemplate"/>
  <w:attachedTemplate r:id="rUnknown"/>
</w:settings>
XML;
        $parts['docSettings/_rels/review-settings.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rTemplate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate" Target="templates/review-template.dotx?rev=7#template"/>
  <Relationship Id="rExternalTemplate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate" Target="file:///C:/Templates/team.dotx" TargetMode="External"/>
</Relationships>
XML;
        $parts['docSettings/templates/review-template.dotx'] = 'template package bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $attached = $docx['attachedTemplates'];
        $template = $attached['byRelationshipId']['rTemplate'];
        $unknown = $attached['byRelationshipId']['rUnknown'];
        $external = $attached['byRelationshipId']['rExternalTemplate'];
        $package = $docx['packageProvenance'];
        $settingsRelationshipsPart = $package['relationshipParts']['docSettings/_rels/review-settings.xml.rels'];
        $templateRelationshipType = $package['relationshipTypes']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate'];
        $templateInventory = $package['parts']['docSettings/templates/review-template.dotx'];

        $t->same('docSettings/review-settings.xml', $docx['settingsPart']);
        $t->same('docSettings/_rels/review-settings.xml.rels', $docx['settingsRelationshipsPart']);
        $t->same('templates/review-template.dotx?rev=7#template', $docx['settingsRelationships']['rTemplate']['target']);
        $t->same('docSettings/templates/review-template.dotx?rev=7#template', $docx['settingsRelationships']['rTemplate']['resolvedTarget']);
        $t->same(true, $docx['settings']['updateFields']);

        $t->same(3, $attached['count']);
        $t->same(2, $attached['relationshipCount']);
        $t->same(2, $attached['referencedCount']);
        $t->same(1, $attached['unreferencedRelationshipCount']);
        $t->same(1, $attached['internalCount']);
        $t->same(1, $attached['externalCount']);
        $t->same(1, $attached['existingCount']);
        $t->same(0, $attached['missingCount']);
        $t->same(1, $attached['unresolvedCount']);
        $t->same(0, $attached['unexpectedRelationshipTypeCount']);
        $t->same(0, $attached['missingContentTypeCount']);
        $t->same(1, $attached['issueCount']);
        $t->same(['rTemplate', 'rUnknown', 'rExternalTemplate'], $attached['relationshipIds']);
        $t->same(['rTemplate', 'rUnknown'], $attached['referencedRelationshipIds']);
        $t->same(['rExternalTemplate'], $attached['unreferencedRelationshipIds']);
        $t->same(['docSettings/templates/review-template.dotx'], $attached['partNames']);

        $t->same(0, $template['index']);
        $t->same(true, $template['referenced']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate', $template['relationshipType']);
        $t->same('templates/review-template.dotx?rev=7#template', $template['target']);
        $t->same('docSettings/templates/review-template.dotx?rev=7#template', $template['resolvedTarget']);
        $t->same('docSettings/templates/review-template.dotx', $template['targetPart']);
        $t->same('rev=7', $template['targetQuery']);
        $t->same('template', $template['targetFragment']);
        $t->same('?rev=7#template', $template['targetReferenceSuffix']);
        $t->same(true, $template['exists']);
        $t->same(strlen('template package bytes'), $template['bytes']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml; profile=attached-template', $template['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml', $template['contentTypeBase']);
        $t->same(['profile' => 'attached-template'], $template['contentTypeParameterMap']);
        $t->same('override', $template['contentTypeSource']);
        $t->same('docSettings/review-settings.xml', $template['settingsPart']);
        $t->same('docSettings/_rels/review-settings.xml.rels', $template['settingsRelationshipsPart']);
        $t->same([], $template['issues']);

        $t->same(1, $unknown['index']);
        $t->same(true, $unknown['referenced']);
        $t->same(null, $unknown['relationshipType']);
        $t->same(false, $unknown['exists']);
        $t->same(['unknown-relationship'], $unknown['issues']);

        $t->same(2, $external['index']);
        $t->same(false, $external['referenced']);
        $t->same(true, $external['external']);
        $t->same('file:///C:/Templates/team.dotx', $external['target']);
        $t->same('External', $external['targetMode']);
        $t->same(null, $external['targetPart']);
        $t->same(false, $external['exists']);
        $t->same('', $external['contentType']);
        $t->same([], $external['issues']);

        $t->same('docSettings/review-settings.xml', $settingsRelationshipsPart['sourcePart']);
        $t->same(true, $settingsRelationshipsPart['sourceExists']);
        $t->same(true, $settingsRelationshipsPart['exists']);
        $t->same(2, $settingsRelationshipsPart['relationshipCount']);
        $t->same('docSettings/templates/review-template.dotx', $settingsRelationshipsPart['relationships']['rTemplate']['targetPart']);
        $t->same(true, $settingsRelationshipsPart['relationships']['rTemplate']['exists']);
        $t->same(true, $settingsRelationshipsPart['relationships']['rExternalTemplate']['external']);

        $t->same(2, $templateRelationshipType['count']);
        $t->same(1, $templateRelationshipType['internalCount']);
        $t->same(1, $templateRelationshipType['externalCount']);
        $t->same(['docSettings/templates/review-template.dotx'], $templateRelationshipType['targetParts']);
        $t->same(['file:///C:/Templates/team.dotx'], $templateRelationshipType['externalTargets']);
        $t->true(in_array('relationship-target', $templateInventory['roles'], true), 'attached template inventory role missing');
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml', $templateInventory['contentTypeBase']);
        $t->same(3, $package['summary']['attachedTemplateCount']);
        $t->same(1, $package['summary']['attachedTemplateExternalCount']);
        $t->same(1, $package['summary']['attachedTemplateIssueCount']);
    },
    'preserves docx selected relationship target suffix provenance' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/docSettings/review-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="../docSettings/review-settings.xml?profile=team#settings"/>' . "\n" .
            '  <Relationship Id="rFontTable" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable" Target="fonts/default-fonts.xml#fontTable"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['docSettings/review-settings.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="true"/>
</w:settings>
XML;
        $parts['word/fonts/default-fonts.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:fonts xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:font w:name="Review Sans"/>
</w:fonts>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');

        $t->same('docSettings/review-settings.xml', $docx['settingsPart']);
        $t->same(true, $docx['settings']['updateFields']);
        $t->same('../docSettings/review-settings.xml?profile=team#settings', $docx['settingsRelationship']['target']);
        $t->same('docSettings/review-settings.xml?profile=team#settings', $docx['settingsRelationship']['resolvedTarget']);
        $t->same('profile=team', $docx['settingsRelationship']['targetQuery']);
        $t->same('settings', $docx['settingsRelationship']['targetFragment']);
        $t->same('?profile=team#settings', $docx['settingsRelationship']['targetReferenceSuffix']);
        $t->same('override', $docx['settingsRelationship']['contentTypeSource']);
        $t->same(null, $docx['settingsRelationship']['defaultExtension']);
        $t->same('docSettings/review-settings.xml', $docx['settingsRelationship']['overridePartName']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml', $docx['settingsRelationship']['contentType']);
        $t->same('word/fonts/default-fonts.xml', $docx['fontTablePart']);
        $t->same(['Review Sans'], $docx['fontTable']['declaredNames']);
        $t->same('fonts/default-fonts.xml#fontTable', $docx['fontTableRelationship']['target']);
        $t->same('word/fonts/default-fonts.xml#fontTable', $docx['fontTableRelationship']['resolvedTarget']);
        $t->same(null, $docx['fontTableRelationship']['targetQuery']);
        $t->same('fontTable', $docx['fontTableRelationship']['targetFragment']);
        $t->same('#fontTable', $docx['fontTableRelationship']['targetReferenceSuffix']);
        $t->same('default', $docx['fontTableRelationship']['contentTypeSource']);
        $t->same('xml', $docx['fontTableRelationship']['defaultExtension']);
        $t->same(null, $docx['fontTableRelationship']['overridePartName']);
        $t->same('application/xml', $docx['fontTableRelationship']['contentType']);
    },
    'tracks docx selected openxml part root and content type provenance' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>' . "\n" .
            '  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>' . "\n" .
            '  <Override PartName="/docSettings/review-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>' . "\n" .
            '  <Override PartName="/word/web/missing-web-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml"/>' . "\n" .
            '  <Override PartName="/word/theme/review-theme.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="../docSettings/review-settings.xml?profile=team#settings"/>' . "\n" .
            '  <Relationship Id="rWebSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings" Target="web/missing-web-settings.xml?profile=browser#web"/>' . "\n" .
            '  <Relationship Id="rTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/review-theme.xml#theme"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['docSettings/review-settings.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:updateFields w:val="1"/>
</w:settings>
XML;
        $parts['word/theme/review-theme.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="Review Theme">
  <a:themeElements/>
</a:theme>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $selected = $document->attr('docx')['packageProvenance']['selectedXmlParts'];
        $summary = $document->attr('docx')['packageProvenance']['summary'];
        $byKind = $selected['byKind'];

        $t->same(15, $selected['count']);
        $t->same(6, $selected['existingCount']);
        $t->same(5, $selected['relationshipSelectedCount']);
        $t->same(1, $selected['missingRequiredOrReferencedCount']);
        $t->same(6, $selected['validRootCount']);
        $t->same(0, $selected['invalidRootCount']);
        $t->same(0, $selected['unexpectedContentTypeCount']);
        $t->same(1, $selected['issueCount']);
        $t->same(['webSettings'], $selected['issueKinds']);
        $t->same(15, $summary['selectedXmlPartCount']);
        $t->same(1, $summary['selectedXmlPartIssueCount']);
        $t->same(['webSettings'], $summary['selectedXmlPartIssueKinds']);

        $t->same('relationship', $byKind['document']['selectionSource']);
        $t->same('rDoc', $byKind['document']['relationshipId']);
        $t->same('word/document.xml', $byKind['document']['partName']);
        $t->same('document', $byKind['document']['rootLocalName']);
        $t->same('http://schemas.openxmlformats.org/wordprocessingml/2006/main', $byKind['document']['rootNamespace']);
        $t->same(true, $byKind['document']['validRoot']);
        $t->same(true, $byKind['document']['contentTypeMatchesExpected']);

        $t->same('conventional-part', $byKind['styles']['selectionSource']);
        $t->same('word/styles.xml', $byKind['styles']['partName']);
        $t->same('styles', $byKind['styles']['rootLocalName']);
        $t->same(true, $byKind['styles']['contentTypeMatchesExpected']);

        $t->same('relationship', $byKind['settings']['selectionSource']);
        $t->same('rSettings', $byKind['settings']['relationshipId']);
        $t->same('word/_rels/document.xml.rels', $byKind['settings']['relationshipsPart']);
        $t->same('docSettings/review-settings.xml', $byKind['settings']['partName']);
        $t->same('?profile=team#settings', $byKind['settings']['targetReferenceSuffix']);
        $t->same('settings', $byKind['settings']['rootLocalName']);
        $t->same(true, $byKind['settings']['validRoot']);
        $t->same(true, $byKind['settings']['contentTypeMatchesExpected']);

        $t->same('relationship', $byKind['theme']['selectionSource']);
        $t->same('theme', $byKind['theme']['rootLocalName']);
        $t->same('http://schemas.openxmlformats.org/drawingml/2006/main', $byKind['theme']['rootNamespace']);
        $t->same(true, $byKind['theme']['contentTypeMatchesExpected']);

        $t->same('relationship', $byKind['webSettings']['selectionSource']);
        $t->same('rWebSettings', $byKind['webSettings']['relationshipId']);
        $t->same('word/web/missing-web-settings.xml', $byKind['webSettings']['partName']);
        $t->same(false, $byKind['webSettings']['exists']);
        $t->same(null, $byKind['webSettings']['validRoot']);
        $t->same(true, $byKind['webSettings']['contentTypeMatchesExpected']);
        $t->same(['missing-relationship-target'], $byKind['webSettings']['issues']);

        $t->same('conventional-fallback', $byKind['comments']['selectionSource']);
        $t->same(null, $byKind['comments']['contentTypeMatchesExpected']);
        $t->same([], $byKind['comments']['issues']);
    },
    'accepts docx main document template and macro-enabled content types' => static function (TestRunner $t): void {
        $acceptedDocumentContentTypes = [
            ['application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'],
            ['application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.template.main+xml'],
            ['application/vnd.ms-word.document.macroEnabled.main+xml', 'application/vnd.ms-word.document.macroenabled.main+xml'],
            ['application/vnd.ms-word.template.macroEnabledTemplate.main+xml', 'application/vnd.ms-word.template.macroenabledtemplate.main+xml'],
        ];
        $expectedContentTypeBases = array_column($acceptedDocumentContentTypes, 1);

        foreach ($acceptedDocumentContentTypes as [$contentType, $contentTypeBase]) {
            $parts = docx_openxml_reader_fixture_parts();
            $parts['[Content_Types].xml'] = str_replace(
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
                $contentType,
                $parts['[Content_Types].xml']
            );

            $document = (new DocxOpenXmlReader())->readPackage($parts);
            $docx = $document->attr('docx');
            $package = $docx['packageProvenance'];
            $selectedDocument = $package['selectedXmlParts']['byKind']['document'];
            $documentInventory = $package['parts']['word/document.xml'];
            $documentRelationship = $package['relationshipParts']['_rels/.rels']['relationships']['rDoc'];

            $t->same('Imported DOCX Batch', $document->attr('meta')['title']);
            $t->same($contentType, $selectedDocument['contentType']);
            $t->same($contentTypeBase, $selectedDocument['contentTypeBase']);
            $t->same(true, $selectedDocument['contentTypeMatchesExpected']);
            $t->same($expectedContentTypeBases, $selectedDocument['expectedContentTypeBases']);
            $t->same([], $selectedDocument['issues']);
            $t->true(!in_array('document', $package['summary']['selectedXmlPartIssueKinds'], true), 'document main content type should not be reported as a selected XML issue');
            $t->same($contentTypeBase, $documentInventory['contentTypeBase']);
            $t->same($contentTypeBase, $documentRelationship['contentTypeBase']);
        }
    },
    'tracks docx glossary document package relationship provenance' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>' . "\n" .
            '  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>' . "\n" .
            '  <Override PartName="/word/glossary/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml; profile=building-blocks"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rGlossary" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument" Target="glossary/document.xml?source=building-blocks#glossary"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/glossary/document.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:glossaryDocument xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:docParts>
    <w:docPart>
      <w:docPartPr><w:name w:val="Review Boilerplate"/></w:docPartPr>
      <w:docPartBody><w:p><w:r><w:t>Reusable review text</w:t></w:r></w:p></w:docPartBody>
    </w:docPart>
  </w:docParts>
</w:glossaryDocument>
XML;
        $parts['word/glossary/_rels/document.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rGlossarySource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/glossary-source?post=42" TargetMode="External"/>
  <Relationship Id="rGlossaryLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/glossary-logo.png"/>
</Relationships>
XML;
        $parts['word/glossary/media/glossary-logo.png'] = 'GLOSSARYPNG';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $selected = $package['selectedXmlParts'];
        $glossary = $selected['byKind']['glossaryDocument'];
        $relationship = $docx['glossaryDocumentRelationship'];
        $relationshipType = $package['relationshipTypes']['http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument'];
        $inventory = $package['parts']['word/glossary/document.xml'];
        $glossaryRelationshipsPart = $package['relationshipParts']['word/glossary/_rels/document.xml.rels'];
        $logoInventory = $package['parts']['word/glossary/media/glossary-logo.png'];

        $t->same('word/glossary/document.xml', $docx['glossaryDocumentPart']);
        $t->same('rGlossary', $relationship['id']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument', $relationship['type']);
        $t->same('word/document.xml', $relationship['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $relationship['relationshipsPart']);
        $t->same('glossary/document.xml?source=building-blocks#glossary', $relationship['target']);
        $t->same('word/glossary/document.xml?source=building-blocks#glossary', $relationship['resolvedTarget']);
        $t->same('word/glossary/document.xml', $relationship['targetPart']);
        $t->same('source=building-blocks', $relationship['targetQuery']);
        $t->same('glossary', $relationship['targetFragment']);
        $t->same('?source=building-blocks#glossary', $relationship['targetReferenceSuffix']);
        $t->same(true, $relationship['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml; profile=building-blocks', $relationship['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml', $relationship['contentTypeBase']);
        $t->same(1, $relationship['contentTypeParameterCount']);
        $t->same(['profile' => 'building-blocks'], $relationship['contentTypeParameterMap']);
        $t->same('override', $relationship['contentTypeSource']);
        $t->same('word/glossary/document.xml', $relationship['overridePartName']);

        $t->same(15, $selected['count']);
        $t->same(5, $selected['existingCount']);
        $t->same(3, $selected['relationshipSelectedCount']);
        $t->same(0, $selected['issueCount']);
        $t->same(15, $package['summary']['selectedXmlPartCount']);
        $t->same('word/glossary/document.xml', $package['summary']['glossaryDocumentPart']);
        $t->same(true, $package['summary']['glossaryDocumentExists']);
        $t->same('rGlossary', $package['summary']['glossaryDocumentRelationshipId']);

        $t->same('relationship', $glossary['selectionSource']);
        $t->same('word/glossary/document.xml', $glossary['partName']);
        $t->same('rGlossary', $glossary['relationshipId']);
        $t->same('glossary/document.xml?source=building-blocks#glossary', $glossary['relationshipTarget']);
        $t->same('word/glossary/document.xml?source=building-blocks#glossary', $glossary['relationshipResolvedTarget']);
        $t->same('?source=building-blocks#glossary', $glossary['targetReferenceSuffix']);
        $t->same('glossaryDocument', $glossary['rootLocalName']);
        $t->same('http://schemas.openxmlformats.org/wordprocessingml/2006/main', $glossary['rootNamespace']);
        $t->same(true, $glossary['validRoot']);
        $t->same(true, $glossary['contentTypeMatchesExpected']);
        $t->same(['profile' => 'building-blocks'], $glossary['contentTypeParameterMap']);
        $t->same([], $glossary['issues']);

        $t->same('glossaryDocument', $relationshipType['label']);
        $t->same(1, $relationshipType['count']);
        $t->same(['word/glossary/document.xml'], $relationshipType['existingTargetParts']);
        $t->same(['application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml; profile=building-blocks'], $relationshipType['contentTypes']);
        $t->same('?source=building-blocks#glossary', $relationshipType['relationships'][0]['targetReferenceSuffix']);
        $t->true(in_array('document-relationship-target', $inventory['roles'], true), 'glossary document inventory role missing');
        $t->true(in_array('glossary-document', $inventory['roles'], true), 'glossary document semantic inventory role missing');
        $t->same(1, $package['summary']['roleCounts']['glossary-document']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml', $inventory['contentTypeBase']);
        $t->same(['profile' => 'building-blocks'], $inventory['contentTypeParameterMap']);

        $t->same('word/glossary/document.xml', $glossaryRelationshipsPart['sourcePart']);
        $t->same(true, $glossaryRelationshipsPart['sourceExists']);
        $t->same(2, $glossaryRelationshipsPart['relationshipCount']);
        $t->same(true, $glossaryRelationshipsPart['relationships']['rGlossarySource']['external']);
        $t->same('word/glossary/media/glossary-logo.png', $glossaryRelationshipsPart['relationships']['rGlossaryLogo']['targetPart']);
        $t->same(true, $glossaryRelationshipsPart['relationships']['rGlossaryLogo']['exists']);
        $t->true(in_array('relationship-target', $logoInventory['roles'], true), 'glossary local image inventory role missing');
        $t->same(strlen('GLOSSARYPNG'), $logoInventory['bytes']);
    },
    'summarizes docx glossary document building block metadata' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/glossary/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml; profile=building-blocks"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rGlossary" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument" Target="glossary/document.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/glossary/document.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:glossaryDocument xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:docParts>
    <w:docPart>
      <w:docPartPr>
        <w:name w:val="Review Boilerplate"/>
        <w:style w:val="Heading1"/>
        <w:category><w:name w:val="WordPress"/><w:gallery w:val="quickParts"/></w:category>
        <w:description w:val="Reusable review intro"/>
        <w:guid w:val="{11111111-2222-3333-4444-555555555555}"/>
        <w:types><w:type w:val="bbPlcHdr"/></w:types>
        <w:behaviors><w:behavior w:val="content"/></w:behaviors>
      </w:docPartPr>
      <w:docPartBody>
        <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Reusable Review Heading</w:t></w:r></w:p>
        <w:p>
          <w:r><w:t xml:space="preserve">Use the </w:t></w:r>
          <w:hyperlink r:id="rGlossarySource"><w:r><w:t>source note</w:t></w:r></w:hyperlink>
          <w:r><w:t xml:space="preserve"> and logo </w:t></w:r>
          <w:r><w:drawing><wp:inline><wp:docPr id="9" name="Glossary logo" descr="Glossary logo"/><a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rGlossaryLogo"/></pic:blipFill></pic:pic></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>
        </w:p>
      </w:docPartBody>
    </w:docPart>
    <w:docPart>
      <w:docPartPr>
        <w:name w:val="Disclosure Snippet"/>
        <w:category><w:name w:val="Legal"/><w:gallery w:val="custom1"/></w:category>
        <w:types><w:type w:val="bbPlcHdr"/><w:type w:val="formField"/></w:types>
        <w:behaviors><w:behavior w:val="p"/></w:behaviors>
      </w:docPartPr>
      <w:docPartBody>
        <w:tbl>
          <w:tr><w:tc><w:p><w:r><w:t>Disclosure</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>Required</w:t></w:r></w:p></w:tc></w:tr>
        </w:tbl>
      </w:docPartBody>
    </w:docPart>
  </w:docParts>
</w:glossaryDocument>
XML;
        $parts['word/glossary/_rels/document.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rGlossarySource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/glossary-source" TargetMode="External"/>
  <Relationship Id="rGlossaryLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/glossary-logo.png"/>
</Relationships>
XML;
        $parts['word/glossary/media/glossary-logo.png'] = 'GLOSSARYPNG';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $glossary = $docx['glossaryDocument'];
        $packageGlossary = $docx['packageProvenance']['glossaryDocument'];
        $summary = $docx['packageProvenance']['summary'];
        $boilerplate = $glossary['byName']['Review Boilerplate'];
        $disclosure = $glossary['byName']['Disclosure Snippet'];

        $t->same($glossary, $packageGlossary);
        $t->same('word/glossary/document.xml', $glossary['partName']);
        $t->same(true, $glossary['exists']);
        $t->same(true, $glossary['validXml']);
        $t->same(true, $glossary['validRoot']);
        $t->same('glossaryDocument', $glossary['rootLocalName']);
        $t->same(2, $glossary['relationshipCount']);
        $t->same(2, $glossary['count']);
        $t->same(3, $glossary['bodyBlockCount']);
        $t->same(['Review Boilerplate', 'Disclosure Snippet'], $glossary['names']);
        $t->same(['quickParts', 'custom1'], $glossary['galleries']);
        $t->same(['WordPress', 'Legal'], $glossary['categories']);
        $t->same(['bbPlcHdr', 'formField'], $glossary['types']);
        $t->same(['content', 'p'], $glossary['behaviors']);
        $t->same(['rGlossaryLogo', 'rGlossarySource'], $glossary['relationshipIds']);
        $t->same(0, $glossary['issueCount']);
        $t->same([], $glossary['issueCodes']);
        $t->same('glossary-document-metadata-only', $glossary['reviewPolicy']);

        $t->same(0, $boilerplate['index']);
        $t->same('Heading1', $boilerplate['styleId']);
        $t->same('WordPress', $boilerplate['category']);
        $t->same('quickParts', $boilerplate['gallery']);
        $t->same('Reusable review intro', $boilerplate['description']);
        $t->same('{11111111-2222-3333-4444-555555555555}', $boilerplate['guid']);
        $t->same(['bbPlcHdr'], $boilerplate['types']);
        $t->same(['content'], $boilerplate['behaviors']);
        $t->same(['rGlossaryLogo', 'rGlossarySource'], $boilerplate['relationshipIds']);
        $t->same(2, $boilerplate['bodyBlockCount']);
        $t->same('Reusable Review Heading Use the source note and logo Glossary logo', $boilerplate['text']);
        $t->same([], $boilerplate['issues']);

        $t->same(1, $disclosure['index']);
        $t->same(null, $disclosure['styleId']);
        $t->same('Legal', $disclosure['category']);
        $t->same('custom1', $disclosure['gallery']);
        $t->same(['bbPlcHdr', 'formField'], $disclosure['types']);
        $t->same(['p'], $disclosure['behaviors']);
        $t->same([], $disclosure['relationshipIds']);
        $t->same(1, $disclosure['bodyBlockCount']);
        $t->same('DisclosureRequired', $disclosure['text']);

        $t->same(2, $summary['glossaryDocumentRelationshipCount']);
        $t->same(2, $summary['glossaryDocumentBuildingBlockCount']);
        $t->same(3, $summary['glossaryDocumentBodyBlockCount']);
        $t->same(0, $summary['glossaryDocumentIssueCount']);
        $t->same([], $summary['glossaryDocumentIssueCodes']);
    },
    'reports malformed docx selected xml sidecars without aborting package ingestion' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>' . "\n" .
            '  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>' . "\n" .
            '  <Override PartName="/word/glossary/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rGlossaryBroken" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/glossaryDocument" Target="glossary/document.xml?review=1#broken"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/glossary/document.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:glossaryDocument xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:docParts>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $selected = $package['selectedXmlParts'];
        $summary = $package['summary'];
        $glossary = $selected['byKind']['glossaryDocument'];

        $t->same('document', $document->type);
        $t->same('Imported DOCX Heading', $document->children[0]->attr('text'));
        $t->same('word/glossary/document.xml', $docx['glossaryDocumentPart']);
        $t->same('rGlossaryBroken', $docx['glossaryDocumentRelationship']['id']);
        $t->same('glossary/document.xml?review=1#broken', $docx['glossaryDocumentRelationship']['target']);
        $t->same('word/glossary/document.xml', $docx['glossaryDocumentRelationship']['targetPart']);
        $t->same(true, $docx['glossaryDocumentRelationship']['exists']);

        $t->same(15, $selected['count']);
        $t->same(5, $selected['existingCount']);
        $t->same(3, $selected['relationshipSelectedCount']);
        $t->same(4, $selected['validRootCount']);
        $t->same(1, $selected['invalidRootCount']);
        $t->same(1, $selected['invalidXmlCount']);
        $t->same(1, $selected['issueCount']);
        $t->same(['glossaryDocument'], $selected['issueKinds']);
        $t->same(1, $summary['selectedXmlPartIssueCount']);
        $t->same(1, $summary['selectedXmlPartInvalidXmlCount']);
        $t->same(['glossaryDocument'], $summary['selectedXmlPartIssueKinds']);

        $t->same('relationship', $glossary['selectionSource']);
        $t->same('word/glossary/document.xml', $glossary['partName']);
        $t->same('rGlossaryBroken', $glossary['relationshipId']);
        $t->same('word/glossary/document.xml?review=1#broken', $glossary['relationshipResolvedTarget']);
        $t->same('review=1', $glossary['targetQuery']);
        $t->same('broken', $glossary['targetFragment']);
        $t->same(true, $glossary['exists']);
        $t->same(false, $glossary['validRoot']);
        $t->same(null, $glossary['rootNamespace']);
        $t->same(null, $glossary['rootLocalName']);
        $t->same(true, $glossary['contentTypeMatchesExpected']);
        $t->same(['invalid-xml'], $glossary['issues']);
        $t->true(is_string($glossary['xmlParseError']) && $glossary['xmlParseError'] !== '', 'malformed glossary XML should retain the parser diagnostic');
    },
    'resolves docx web settings from relationship target' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/web/review-web-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rWebSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings" Target="web/review-web-settings.xml?profile=browser#web"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/web/review-web-settings.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:webSettings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:optimizeForBrowser/>
  <w:allowPNG w:val="0"/>
  <w:relyOnVML w:val="off"/>
  <w:doNotRelyOnCSS w:val="1"/>
  <w:doNotSaveAsSingleFile/>
  <w:doNotOrganizeInFolder w:val="true"/>
  <w:doNotUseLongFileNames w:val="false"/>
  <w:encoding w:val="UTF-8"/>
  <w:targetScreenSz w:val="1024x768"/>
  <w:pixelsPerInch w:val="144"/>
</w:webSettings>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $webSettings = $docx['webSettings'];

        $t->same('word/web/review-web-settings.xml', $docx['webSettingsPart']);
        $t->same('rWebSettings', $docx['webSettingsRelationship']['id']);
        $t->same('word/document.xml', $docx['webSettingsRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['webSettingsRelationship']['relationshipsPart']);
        $t->same('web/review-web-settings.xml?profile=browser#web', $docx['webSettingsRelationship']['target']);
        $t->same('word/web/review-web-settings.xml?profile=browser#web', $docx['webSettingsRelationship']['resolvedTarget']);
        $t->same('word/web/review-web-settings.xml', $docx['webSettingsRelationship']['targetPart']);
        $t->same(true, $docx['webSettingsRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml', $docx['webSettingsRelationship']['contentType']);
        $t->same(true, $webSettings['optimizeForBrowser']);
        $t->same(false, $webSettings['allowPng']);
        $t->same(false, $webSettings['relyOnVml']);
        $t->same(true, $webSettings['doNotRelyOnCss']);
        $t->same(true, $webSettings['doNotSaveAsSingleFile']);
        $t->same(true, $webSettings['doNotOrganizeInFolder']);
        $t->same(false, $webSettings['doNotUseLongFileNames']);
        $t->same('UTF-8', $webSettings['encoding']);
        $t->same('1024x768', $webSettings['targetScreenSize']);
        $t->same(144, $webSettings['pixelsPerInch']);
    },
    'summarizes docx web settings output policy for package review' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/web/policy-web-settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rWebPolicy" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings" Target="web/policy-web-settings.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/web/policy-web-settings.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:webSettings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:optimizeForBrowser/>
  <w:allowPNG w:val="0"/>
  <w:relyOnVML w:val="on"/>
  <w:doNotRelyOnCSS w:val="1"/>
  <w:doNotSaveAsSingleFile w:val="true"/>
  <w:doNotOrganizeInFolder w:val="false"/>
  <w:doNotUseLongFileNames/>
  <w:encoding w:val="windows-1252"/>
  <w:targetScreenSz w:val="800x600"/>
  <w:pixelsPerInch w:val="96"/>
</w:webSettings>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $policy = $docx['webSettings']['outputPolicy'];
        $summary = $docx['packageProvenance']['summary'];
        $expectedFlags = [
            'browser-optimized',
            'png-disabled',
            'vml-required',
            'css-output-disabled',
            'single-file-output-disabled',
            'folder-organization-allowed',
            'long-file-names-disabled',
        ];

        $t->same(7, $policy['flagCount']);
        $t->same($expectedFlags, $policy['flags']);
        $t->same(true, $policy['browserOptimized']);
        $t->same(false, $policy['pngAllowed']);
        $t->same(true, $policy['vmlRequired']);
        $t->same(true, $policy['cssOutputDisabled']);
        $t->same(true, $policy['singleFileOutputDisabled']);
        $t->same(false, $policy['folderOrganizationDisabled']);
        $t->same(true, $policy['longFileNamesDisabled']);
        $t->same('windows-1252', $policy['encoding']);
        $t->same('800x600', $policy['targetScreenSize']);
        $t->same(96, $policy['pixelsPerInch']);
        $t->same(7, $summary['webSettingsOutputPolicyFlagCount']);
        $t->same($expectedFlags, $summary['webSettingsOutputPolicyFlags']);
    },
    'resolves docx theme font and color scheme from relationship target' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/theme/review-theme.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/review-theme.xml?variant=wp#theme"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/theme/review-theme.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<a:theme xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" name="WordPress Review Theme">
  <a:themeElements>
    <a:clrScheme name="Review Colors">
      <a:dk1><a:sysClr val="windowText" lastClr="111111"/></a:dk1>
      <a:lt1><a:srgbClr val="FFFFFF"/></a:lt1>
      <a:accent1><a:srgbClr val="4472C4"/></a:accent1>
      <a:accent2><a:prstClr val="orange"/></a:accent2>
      <a:hlink><a:srgbClr val="0563C1"/></a:hlink>
      <a:folHlink><a:schemeClr val="accent1"/></a:folHlink>
    </a:clrScheme>
    <a:fontScheme name="Review Fonts">
      <a:majorFont>
        <a:latin typeface="Aptos Display"/>
        <a:ea typeface="Yu Gothic"/>
        <a:cs typeface="Arial"/>
      </a:majorFont>
      <a:minorFont>
        <a:latin typeface="Aptos"/>
        <a:ea typeface="Meiryo"/>
        <a:cs typeface="Times New Roman"/>
      </a:minorFont>
    </a:fontScheme>
  </a:themeElements>
</a:theme>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $theme = $docx['theme'];

        $t->same('word/theme/review-theme.xml', $docx['themePart']);
        $t->same('rTheme', $docx['themeRelationship']['id']);
        $t->same('word/document.xml', $docx['themeRelationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $docx['themeRelationship']['relationshipsPart']);
        $t->same('theme/review-theme.xml?variant=wp#theme', $docx['themeRelationship']['target']);
        $t->same('word/theme/review-theme.xml?variant=wp#theme', $docx['themeRelationship']['resolvedTarget']);
        $t->same('word/theme/review-theme.xml', $docx['themeRelationship']['targetPart']);
        $t->same(true, $docx['themeRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.theme+xml', $docx['themeRelationship']['contentType']);
        $t->same('WordPress Review Theme', $theme['name']);
        $t->same('Review Fonts', $theme['fonts']['schemeName']);
        $t->same('Aptos Display', $theme['fonts']['majorLatin']);
        $t->same('Yu Gothic', $theme['fonts']['majorEastAsia']);
        $t->same('Arial', $theme['fonts']['majorComplexScript']);
        $t->same('Aptos', $theme['fonts']['minorLatin']);
        $t->same('Meiryo', $theme['fonts']['minorEastAsia']);
        $t->same('Times New Roman', $theme['fonts']['minorComplexScript']);
        $t->same('Review Colors', $theme['colors']['schemeName']);
        $t->same(6, $theme['colors']['count']);
        $t->same('system', $theme['colors']['items'][0]['kind']);
        $t->same('windowText', $theme['colors']['items'][0]['value']);
        $t->same('111111', $theme['colors']['byName']['text1']);
        $t->same('4472C4', $theme['colors']['byName']['accent1']);
        $t->same('0563C1', $theme['colors']['byName']['hyperlink']);
        $t->same('preset', $theme['colors']['items'][3]['kind']);
        $t->same('orange', $theme['colors']['items'][3]['value']);
        $t->same('scheme', $theme['colors']['items'][5]['kind']);
        $t->same('accent1', $theme['colors']['items'][5]['value']);
    },
    'resolves docx footnotes and endnotes from relationship targets' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/notes/review-footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>' . "\n" .
            '  <Override PartName="/word/annotations/review-endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="../notes/review-footnotes.xml?batch=review#fn"/>' . "\n" .
            '  <Relationship Id="rEndnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="annotations/review-endnotes.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            '<w:hyperlink r:id="rLink"><w:r><w:t>source link</w:t></w:r></w:hyperlink>',
            '<w:hyperlink r:id="rLink"><w:r><w:t>source link</w:t></w:r></w:hyperlink>' . "\n" .
            '      <w:r><w:t xml:space="preserve"> with note</w:t></w:r>' . "\n" .
            '      <w:r><w:footnoteReference w:id="42"/></w:r>' . "\n" .
            '      <w:r><w:t xml:space="preserve"> and endnote</w:t></w:r>' . "\n" .
            '      <w:r><w:endnoteReference w:id="7"/></w:r>' . "\n" .
            '      <w:r><w:t xml:space="preserve"> plus missing note</w:t></w:r>' . "\n" .
            '      <w:r><w:footnoteReference w:id="99" w:customMarkFollows="1"/></w:r>',
            $parts['word/document.xml']
        );
        $parts['notes/review-footnotes.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:footnote w:id="-1" w:type="separator"><w:p><w:r><w:t>separator</w:t></w:r></w:p></w:footnote>
  <w:footnote w:id="42">
    <w:p>
      <w:r><w:footnoteRef/></w:r>
      <w:r><w:t xml:space="preserve">Footnote </w:t></w:r>
      <w:hyperlink r:id="rFootLink"><w:r><w:t>relationship source</w:t></w:r></w:hyperlink>
      <w:r><w:t xml:space="preserve"> note.</w:t></w:r>
    </w:p>
  </w:footnote>
</w:footnotes>
XML;
        $parts['notes/_rels/review-footnotes.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rFootLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/footnote-source" TargetMode="External"/>
  <Relationship Id="rFootMissingMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-note.bin?review=1#media"/>
</Relationships>
XML;
        $parts['word/annotations/review-endnotes.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:endnote w:id="0" w:type="separator"><w:p><w:r><w:t>separator</w:t></w:r></w:p></w:endnote>
  <w:endnote w:id="7">
    <w:p><w:r><w:endnoteRef/></w:r><w:r><w:t>Endnote package audit.</w:t></w:r></w:p>
  </w:endnote>
</w:endnotes>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $paragraph = $document->children[1];
        $notes = array_values(array_filter($paragraph->children, static fn (AstNode $node): bool => $node->type === 'note'));
        $footnote = $notes[0];
        $endnote = $notes[1];
        $missing = $notes[2];

        $t->same('notes/review-footnotes.xml', $docx['footnotesPart']);
        $t->same('rFootnotes', $docx['footnotesRelationship']['id']);
        $t->same('../notes/review-footnotes.xml?batch=review#fn', $docx['footnotesRelationship']['target']);
        $t->same('notes/review-footnotes.xml?batch=review#fn', $docx['footnotesRelationship']['resolvedTarget']);
        $t->same('notes/review-footnotes.xml', $docx['footnotesRelationship']['targetPart']);
        $t->same(true, $docx['footnotesRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml', $docx['footnotesRelationship']['contentType']);
        $t->same(1, $docx['footnotes']['count']);
        $t->same(['42'], $docx['footnotes']['ids']);
        $t->same('Footnote relationship source note.', $docx['footnotes']['byId']['42']['text']);
        $t->same(1, $docx['footnotes']['byId']['42']['blockCount']);
        $t->same('notes/_rels/review-footnotes.xml.rels', $docx['footnotes']['relationshipsPart']);
        $t->same(2, $docx['footnotes']['relationshipCount']);
        $t->same(1, $docx['footnotes']['internalRelationshipCount']);
        $t->same(1, $docx['footnotes']['externalRelationshipCount']);
        $t->same(0, $docx['footnotes']['existingRelationshipTargetCount']);
        $t->same(1, $docx['footnotes']['missingRelationshipTargetCount']);
        $t->same(1, $docx['footnotes']['missingRelationshipContentTypeCount']);
        $t->same(1, $docx['footnotes']['relationshipTargetReferenceSuffixCount']);
        $t->same(['rFootLink', 'rFootMissingMedia'], $docx['footnotes']['relationshipIds']);
        $t->same(['notes/media/missing-note.bin'], $docx['footnotes']['relationshipTargetParts']);
        $t->same(['https://example.test/footnote-source'], $docx['footnotes']['relationshipExternalTargets']);
        $t->same(1, $docx['footnotes']['relationshipIssueCount']);
        $t->same(['missing-target-content-type', 'missing-target-part'], $docx['footnotes']['relationshipIssueCodes']);
        $t->same(['rFootLink'], $docx['footnotes']['byId']['42']['relationshipIds']);
        $t->same(1, $docx['footnotes']['byId']['42']['relationshipCount']);
        $t->same([], $docx['footnotes']['byId']['42']['missingRelationshipIds']);
        $t->same(true, $docx['footnotes']['relationships']['rFootLink']['external']);
        $t->same('notes/media/missing-note.bin', $docx['footnotes']['relationships']['rFootMissingMedia']['targetPart']);
        $t->same('review=1', $docx['footnotes']['relationships']['rFootMissingMedia']['targetQuery']);
        $t->same('media', $docx['footnotes']['relationships']['rFootMissingMedia']['targetFragment']);
        $t->same('?review=1#media', $docx['footnotes']['relationships']['rFootMissingMedia']['targetReferenceSuffix']);
        $t->same(['missing-target-part', 'missing-target-content-type'], $docx['footnotes']['relationships']['rFootMissingMedia']['issues']);

        $t->same('word/annotations/review-endnotes.xml', $docx['endnotesPart']);
        $t->same('rEndnotes', $docx['endnotesRelationship']['id']);
        $t->same('annotations/review-endnotes.xml', $docx['endnotesRelationship']['target']);
        $t->same('word/annotations/review-endnotes.xml', $docx['endnotesRelationship']['resolvedTarget']);
        $t->same('word/annotations/review-endnotes.xml', $docx['endnotesRelationship']['targetPart']);
        $t->same(true, $docx['endnotesRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml', $docx['endnotesRelationship']['contentType']);
        $t->same(1, $docx['endnotes']['count']);
        $t->same(['7'], $docx['endnotes']['ids']);
        $t->same('Endnote package audit.', $docx['endnotes']['byId']['7']['text']);
        $t->same('word/annotations/_rels/review-endnotes.xml.rels', $docx['endnotes']['relationshipsPart']);
        $t->same(0, $docx['endnotes']['relationshipCount']);

        $t->same(3, count($notes));
        $t->same('42', $footnote->attr('id'));
        $t->same('footnote', $footnote->attr('sourceType'));
        $t->same('Footnote relationship source note.', $footnote->children[0]->attr('text'));
        $t->same('link', $footnote->children[0]->children[1]->type);
        $t->same('https://example.test/footnote-source', $footnote->children[0]->children[1]->attr('url'));
        $t->same('7', $endnote->attr('id'));
        $t->same('endnote', $endnote->attr('sourceType'));
        $t->same('Endnote package audit.', $endnote->children[0]->attr('text'));
        $t->same('99', $missing->attr('id'));
        $t->same('footnote', $missing->attr('sourceType'));
        $t->same(true, $missing->attr('missing'));
        $t->same(true, $missing->attr('customMarkFollows'));

        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('[^1]: Footnote [relationship source](https://example.test/footnote-source) note.', $markdown);
        $t->contains('[^2]: Endnote package audit.', $markdown);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol><li id="fn-1"><p>Footnote <a href="https://example.test/footnote-source">relationship source</a> note.</p>', $blocks);
        $t->contains('<li id="fn-2"><p>Endnote package audit.</p>', $blocks);
    },
    'resolves docx comments from relationship target into review notes' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/comments/review-comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments/review-comments.xml?batch=review#comments"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            '<w:hyperlink r:id="rLink"><w:r><w:t>source link</w:t></w:r></w:hyperlink>',
            '<w:hyperlink r:id="rLink"><w:r><w:t>source link</w:t></w:r></w:hyperlink>' . "\n" .
            '      <w:r><w:t xml:space="preserve"> with reviewer comment</w:t></w:r>' . "\n" .
            '      <w:commentRangeStart w:id="12"/>' . "\n" .
            '      <w:r><w:t>commented text</w:t></w:r>' . "\n" .
            '      <w:commentRangeEnd w:id="12"/>' . "\n" .
            '      <w:r><w:commentReference w:id="12"/></w:r>',
            $parts['word/document.xml']
        );
        $parts['word/comments/review-comments.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:comment w:id="12" w:author="Review Lead" w:initials="RL" w:date="2026-06-11T11:36:24Z">
    <w:p>
      <w:r><w:annotationRef/></w:r>
      <w:r><w:t xml:space="preserve">Comment </w:t></w:r>
      <w:hyperlink r:id="rCommentSource"><w:r><w:t>source</w:t></w:r></w:hyperlink>
      <w:r><w:t xml:space="preserve"> keeps review context.</w:t></w:r>
    </w:p>
  </w:comment>
</w:comments>
XML;
        $parts['word/comments/_rels/review-comments.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCommentSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/comment-source" TargetMode="External"/>
  <Relationship Id="rCommentMissingMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-comment.bin#review"/>
</Relationships>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $paragraph = $document->children[1];
        $notes = array_values(array_filter($paragraph->children, static fn (AstNode $node): bool => $node->type === 'note'));
        $commentNote = $notes[0];
        $comments = $docx['comments'];
        $comment = $comments['byId']['12'];
        $commentRelationshipsPart = $docx['packageProvenance']['relationshipParts']['word/comments/_rels/review-comments.xml.rels'];

        $t->same('word/comments/review-comments.xml', $docx['commentsPart']);
        $t->same('rComments', $docx['commentsRelationship']['id']);
        $t->same('comments/review-comments.xml?batch=review#comments', $docx['commentsRelationship']['target']);
        $t->same('word/comments/review-comments.xml?batch=review#comments', $docx['commentsRelationship']['resolvedTarget']);
        $t->same('word/comments/review-comments.xml', $docx['commentsRelationship']['targetPart']);
        $t->same(true, $docx['commentsRelationship']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', $docx['commentsRelationship']['contentType']);
        $t->same(1, $comments['count']);
        $t->same(['12'], $comments['ids']);
        $t->same('comment', $comment['sourceType']);
        $t->same('Review Lead', $comment['author']);
        $t->same('RL', $comment['initials']);
        $t->same('2026-06-11T11:36:24Z', $comment['date']);
        $t->same('Comment source keeps review context.', $comment['text']);
        $t->same(1, $comment['blockCount']);
        $t->same('word/comments/_rels/review-comments.xml.rels', $comments['relationshipsPart']);
        $t->same(2, $comments['relationshipCount']);
        $t->same(1, $comments['internalRelationshipCount']);
        $t->same(1, $comments['externalRelationshipCount']);
        $t->same(1, $comments['missingRelationshipTargetCount']);
        $t->same(1, $comments['missingRelationshipContentTypeCount']);
        $t->same(1, $comments['relationshipTargetReferenceSuffixCount']);
        $t->same(['rCommentSource', 'rCommentMissingMedia'], $comments['relationshipIds']);
        $t->same(['word/comments/media/missing-comment.bin'], $comments['relationshipTargetParts']);
        $t->same(['https://example.test/comment-source'], $comments['relationshipExternalTargets']);
        $t->same(['missing-target-content-type', 'missing-target-part'], $comments['relationshipIssueCodes']);
        $t->same(['rCommentSource'], $comment['relationshipIds']);
        $t->same(1, $comment['relationshipCount']);
        $t->same([], $comment['missingRelationshipIds']);
        $t->same(true, $comments['relationships']['rCommentSource']['external']);
        $t->same('word/comments/media/missing-comment.bin', $comments['relationships']['rCommentMissingMedia']['targetPart']);
        $t->same('review', $comments['relationships']['rCommentMissingMedia']['targetFragment']);
        $t->same(['missing-target-part', 'missing-target-content-type'], $comments['relationships']['rCommentMissingMedia']['issues']);
        $t->same('word/comments/review-comments.xml', $commentRelationshipsPart['sourcePart']);
        $t->same(2, $commentRelationshipsPart['relationshipCount']);
        $t->same(true, $commentRelationshipsPart['relationships']['rCommentSource']['external']);
        $t->same('word/comments/media/missing-comment.bin', $commentRelationshipsPart['relationships']['rCommentMissingMedia']['targetPart']);
        $t->same('12', $commentNote->attr('id'));
        $t->same('comment', $commentNote->attr('sourceType'));
        $t->same('Review Lead', $commentNote->attr('author'));
        $t->same('RL', $commentNote->attr('initials'));
        $t->same('2026-06-11T11:36:24Z', $commentNote->attr('date'));
        $t->same('Comment source keeps review context.', $commentNote->children[0]->attr('text'));
        $t->same('link', $commentNote->children[0]->children[1]->type);
        $t->same('https://example.test/comment-source', $commentNote->children[0]->children[1]->attr('url'));

        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);
        $t->contains('with reviewer commentcommented text[^1]', $markdown);
        $t->contains('[^1]: Comment [source](https://example.test/comment-source) keeps review context.', $markdown);
        $t->contains('<section class="footnotes" role="doc-endnotes"><ol><li id="fn-1"><p>Comment <a href="https://example.test/comment-source">source</a> keeps review context.</p>', $blocks);
    },
    'summarizes docx note and comment relationship diagnostics' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/notes/review-footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>' . "\n" .
            '  <Override PartName="/notes/review-endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/>' . "\n" .
            '  <Override PartName="/word/comments/review-comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="../notes/review-footnotes.xml"/>' . "\n" .
            '  <Relationship Id="rEndnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="../notes/review-endnotes.xml"/>' . "\n" .
            '  <Relationship Id="rComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments/review-comments.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['notes/review-footnotes.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:footnote w:id="42"><w:p><w:hyperlink r:id="rFootExternal"><w:r><w:t>source</w:t></w:r></w:hyperlink></w:p></w:footnote>
</w:footnotes>
XML;
        $parts['notes/_rels/review-footnotes.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rFootExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://mirror.example.test/footnote" TargetMode="External"/>
  <Relationship Id="rFootExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/footnote" TargetMode="External"/>
  <Relationship Id="rFootMissingMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-footnote.bin?review=1#media"/>
  <Relationship Id="rFootOrphanInternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/shared-target.png?dup=1#same"/>
  <Relationship Id="rFootOrphanExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="media/shared-target.png?dup=1#same" TargetMode="External"/>
  <Relationship Id="rFootSuffixTwin" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/twin-footnote.png?dup=1#same"/>
</Relationships>
XML;
        $parts['notes/media/shared-target.png'] = 'footnote shared target bytes';
        $parts['notes/media/twin-footnote.png'] = 'footnote suffix twin bytes';
        $parts['notes/review-endnotes.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:endnote w:id="7"><w:p><w:r><w:t>Endnote audit.</w:t></w:r></w:p></w:endnote>
</w:endnotes>
XML;
        $parts['notes/_rels/review-endnotes.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rEndOrphan" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/end-orphan.png?trace=end#media"/>
</Relationships>
XML;
        $parts['notes/media/end-orphan.png'] = 'endnote orphan target bytes';
        $parts['word/comments/review-comments.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:comment w:id="12"><w:p><w:hyperlink r:id="rCommentExternal"><w:r><w:t>source</w:t></w:r></w:hyperlink></w:p></w:comment>
</w:comments>
XML;
        $parts['word/comments/_rels/review-comments.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCommentExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://mirror.example.test/comment" TargetMode="External"/>
  <Relationship Id="rCommentExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/comment" TargetMode="External"/>
  <Relationship Id="rCommentMissingMedia" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-comment.bin#review"/>
  <Relationship Id="rCommentOrphan" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/comment-orphan.png?audit=orphan#comment"/>
</Relationships>
XML;
        $parts['word/comments/media/comment-orphan.png'] = 'comment orphan target bytes';

        $docx = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx');
        $footnotes = $docx['footnotes'];
        $endnotes = $docx['endnotes'];
        $comments = $docx['comments'];

        $t->same('notes/_rels/review-footnotes.xml.rels', $footnotes['relationshipsPart']);
        $t->same(5, $footnotes['relationshipCount']);
        $t->same(6, $footnotes['relationshipRecordCount']);
        $t->same(1, $footnotes['duplicateRelationshipIdCount']);
        $t->same(2, $footnotes['duplicateRelationshipRecordCount']);
        $t->same(['rFootExternal'], $footnotes['duplicateRelationshipIds']);
        $t->same([0, 1], $footnotes['duplicateRelationshipIdItems'][0]['ordinals']);
        $t->same(3, $footnotes['internalRelationshipCount']);
        $t->same(2, $footnotes['externalRelationshipCount']);
        $t->same(1, $footnotes['missingRelationshipTargetCount']);
        $t->same(1, $footnotes['missingRelationshipContentTypeCount']);
        $t->same(['rFootExternal', 'rFootMissingMedia', 'rFootOrphanInternal', 'rFootOrphanExternal', 'rFootSuffixTwin'], $footnotes['relationshipIds']);
        $t->same(['rFootExternal'], $footnotes['referencedRelationshipIds']);
        $t->same(['rFootMissingMedia', 'rFootOrphanInternal', 'rFootOrphanExternal', 'rFootSuffixTwin'], $footnotes['unreferencedRelationshipIds']);
        $t->same(['rFootMissingMedia', 'rFootOrphanInternal', 'rFootOrphanExternal', 'rFootSuffixTwin'], $footnotes['orphanedRelationshipIds']);
        $t->same(4, $footnotes['orphanedRelationshipCount']);
        $t->same([], $footnotes['missingReferencedRelationshipIds']);
        $t->same(['notes/media/missing-footnote.bin', 'notes/media/shared-target.png', 'notes/media/twin-footnote.png'], $footnotes['relationshipTargetParts']);
        $t->same(['https://example.test/footnote', 'media/shared-target.png?dup=1#same'], $footnotes['relationshipExternalTargets']);
        $t->same(['?review=1#media', '?dup=1#same'], $footnotes['relationshipTargetReferenceSuffixes']);
        $t->same(['missing-target-content-type', 'missing-target-part'], $footnotes['relationshipIssueCodes']);
        $t->same(['rFootExternal'], $footnotes['byId']['42']['relationshipIds']);
        $t->same(['rFootExternal'], $footnotes['byId']['42']['knownRelationshipIds']);
        $t->same(1, $footnotes['byId']['42']['knownRelationshipCount']);
        $t->same(0, $footnotes['byId']['42']['missingRelationshipCount']);
        $t->same(['rFootExternal'], $footnotes['byId']['42']['duplicateRelationshipIds']);
        $t->same(1, $footnotes['byId']['42']['duplicateRelationshipCount']);
        $t->same(2, $footnotes['byId']['42']['referencedRelationshipRecordCount']);
        $t->same([0, 1], array_column($footnotes['byId']['42']['referencedRelationshipRecords'], 'ordinal'));
        $t->same(['https://mirror.example.test/footnote', 'https://example.test/footnote'], array_column($footnotes['byId']['42']['referencedRelationshipRecords'], 'target'));
        $t->same('42', $footnotes['relationships']['rFootExternal']['referencedItemIds'][0]);
        $t->same(false, $footnotes['relationships']['rFootExternal']['orphaned']);
        $t->same(true, $footnotes['relationships']['rFootMissingMedia']['orphaned']);
        $t->same('review=1', $footnotes['relationships']['rFootMissingMedia']['targetQuery']);
        $t->same('media', $footnotes['relationships']['rFootMissingMedia']['targetFragment']);
        $t->same(['missing-target-part', 'missing-target-content-type'], $footnotes['relationships']['rFootMissingMedia']['issues']);
        $t->same(1, $footnotes['internalExternalRelationshipTargetCollisionCount']);
        $t->same(2, $footnotes['internalExternalRelationshipTargetCollisionRelationshipCount']);
        $t->same(1, $footnotes['recordInternalExternalRelationshipTargetCollisionCount']);
        $t->same(2, $footnotes['recordInternalExternalRelationshipTargetCollisionRelationshipCount']);
        $t->same('media/shared-target.png', $footnotes['internalExternalRelationshipTargetCollisions'][0]['target']);
        $t->same(['rFootOrphanInternal'], $footnotes['internalExternalRelationshipTargetCollisions'][0]['internalRelationshipIds']);
        $t->same(['rFootOrphanExternal'], $footnotes['internalExternalRelationshipTargetCollisions'][0]['externalRelationshipIds']);
        $t->same(['notes/media/shared-target.png'], $footnotes['internalExternalRelationshipTargetCollisions'][0]['targetParts']);
        $t->same(['media/shared-target.png?dup=1#same'], $footnotes['internalExternalRelationshipTargetCollisions'][0]['externalTargets']);
        $t->same(1, $footnotes['repeatedRelationshipTargetReferenceSuffixCount']);
        $t->same(3, $footnotes['repeatedRelationshipTargetReferenceSuffixRelationshipCount']);
        $t->same(1, $footnotes['recordRepeatedRelationshipTargetReferenceSuffixCount']);
        $t->same(3, $footnotes['recordRepeatedRelationshipTargetReferenceSuffixRelationshipCount']);
        $t->same(['?dup=1#same'], $footnotes['repeatedRelationshipTargetReferenceSuffixes']);
        $t->same(['rFootOrphanInternal', 'rFootOrphanExternal', 'rFootSuffixTwin'], $footnotes['repeatedRelationshipTargetReferenceSuffixGroups'][0]['relationshipIds']);

        $t->same('notes/_rels/review-endnotes.xml.rels', $endnotes['relationshipsPart']);
        $t->same(1, $endnotes['relationshipCount']);
        $t->same(['rEndOrphan'], $endnotes['relationshipIds']);
        $t->same([], $endnotes['referencedRelationshipIds']);
        $t->same(['rEndOrphan'], $endnotes['unreferencedRelationshipIds']);
        $t->same(['rEndOrphan'], $endnotes['orphanedRelationshipIds']);
        $t->same(1, $endnotes['orphanedRelationshipCount']);
        $t->same([], $endnotes['byId']['7']['relationshipIds']);
        $t->same([], $endnotes['byId']['7']['knownRelationshipIds']);
        $t->same(true, $endnotes['relationships']['rEndOrphan']['orphaned']);
        $t->same(['notes/media/end-orphan.png'], $endnotes['relationshipTargetParts']);

        $t->same('word/comments/_rels/review-comments.xml.rels', $comments['relationshipsPart']);
        $t->same(3, $comments['relationshipCount']);
        $t->same(4, $comments['relationshipRecordCount']);
        $t->same(1, $comments['duplicateRelationshipIdCount']);
        $t->same(2, $comments['duplicateRelationshipRecordCount']);
        $t->same(['rCommentExternal'], $comments['duplicateRelationshipIds']);
        $t->same([0, 1], $comments['duplicateRelationshipIdItems'][0]['ordinals']);
        $t->same(2, $comments['internalRelationshipCount']);
        $t->same(1, $comments['externalRelationshipCount']);
        $t->same(['rCommentExternal', 'rCommentMissingMedia', 'rCommentOrphan'], $comments['relationshipIds']);
        $t->same(['rCommentExternal'], $comments['referencedRelationshipIds']);
        $t->same(['rCommentMissingMedia', 'rCommentOrphan'], $comments['unreferencedRelationshipIds']);
        $t->same(['rCommentMissingMedia', 'rCommentOrphan'], $comments['orphanedRelationshipIds']);
        $t->same(['word/comments/media/missing-comment.bin', 'word/comments/media/comment-orphan.png'], $comments['relationshipTargetParts']);
        $t->same(['https://example.test/comment'], $comments['relationshipExternalTargets']);
        $t->same(['missing-target-content-type', 'missing-target-part'], $comments['relationshipIssueCodes']);
        $t->same(['rCommentExternal'], $comments['byId']['12']['relationshipIds']);
        $t->same(['rCommentExternal'], $comments['byId']['12']['knownRelationshipIds']);
        $t->same(['rCommentExternal'], $comments['byId']['12']['duplicateRelationshipIds']);
        $t->same(1, $comments['byId']['12']['duplicateRelationshipCount']);
        $t->same(2, $comments['byId']['12']['referencedRelationshipRecordCount']);
        $t->same([0, 1], array_column($comments['byId']['12']['referencedRelationshipRecords'], 'ordinal'));
        $t->same(['https://mirror.example.test/comment', 'https://example.test/comment'], array_column($comments['byId']['12']['referencedRelationshipRecords'], 'target'));
        $t->same('12', $comments['relationships']['rCommentExternal']['referencedItemIds'][0]);
        $t->same(false, $comments['relationships']['rCommentExternal']['orphaned']);
        $t->same('review', $comments['relationships']['rCommentMissingMedia']['targetFragment']);
        $t->same(['missing-target-part', 'missing-target-content-type'], $comments['relationships']['rCommentMissingMedia']['issues']);
        $t->same(true, $comments['relationships']['rCommentOrphan']['orphaned']);
        $t->same('audit=orphan', $comments['relationships']['rCommentOrphan']['targetQuery']);
    },
    'summarizes docx note and comment same-mode relationship collisions' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/notes/collision-footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>' . "\n" .
            '  <Override PartName="/notes/collision-endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/>' . "\n" .
            '  <Override PartName="/word/comments/collision-comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="../notes/collision-footnotes.xml"/>' . "\n" .
            '  <Relationship Id="rEndnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="../notes/collision-endnotes.xml"/>' . "\n" .
            '  <Relationship Id="rComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments/collision-comments.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['notes/collision-footnotes.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:footnote w:id="42">
    <w:p>
      <w:drawing r:id="rFootInternalA"/>
      <w:hyperlink r:id="rFootExternalA"><w:r><w:t>external source</w:t></w:r></w:hyperlink>
    </w:p>
  </w:footnote>
</w:footnotes>
XML;
        $parts['notes/_rels/collision-footnotes.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rFootInternalA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/shared-foot.png?slot=a#img"/>
  <Relationship Id="rFootInternalB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/shared-foot.png?slot=b#img"/>
  <Relationship Id="rFootExternalA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/shared-footnote-source" TargetMode="External"/>
  <Relationship Id="rFootExternalB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/shared-footnote-source" TargetMode="External"/>
</Relationships>
XML;
        $parts['notes/media/shared-foot.png'] = 'shared footnote image bytes';
        $parts['notes/collision-endnotes.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:endnote w:id="7">
    <w:p>
      <w:drawing r:id="rEndInternalA"/>
      <w:drawing r:id="rEndDuplicate"/>
    </w:p>
  </w:endnote>
</w:endnotes>
XML;
        $parts['notes/_rels/collision-endnotes.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rEndInternalA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/shared-end.png"/>
  <Relationship Id="rEndInternalB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/shared-end.png?review=b#asset"/>
  <Relationship Id="rEndDuplicate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/end-duplicate-a.png"/>
  <Relationship Id="rEndDuplicate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/end-duplicate-b.png"/>
</Relationships>
XML;
        $parts['notes/media/shared-end.png'] = 'shared endnote image bytes';
        $parts['notes/media/end-duplicate-a.png'] = 'endnote duplicate a bytes';
        $parts['notes/media/end-duplicate-b.png'] = 'endnote duplicate b bytes';
        $parts['word/comments/collision-comments.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <w:comment w:id="12">
    <w:p>
      <w:hyperlink r:id="rCommentExternalA"><w:r><w:t>external source</w:t></w:r></w:hyperlink>
    </w:p>
  </w:comment>
</w:comments>
XML;
        $parts['word/comments/_rels/collision-comments.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rCommentExternalA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/comment-collision?review=1#src" TargetMode="External"/>
  <Relationship Id="rCommentExternalB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/comment-collision?review=1#src" TargetMode="External"/>
</Relationships>
XML;

        $docx = (new DocxOpenXmlReader())->readPackage($parts)->attr('docx');
        $footnotes = $docx['footnotes'];
        $endnotes = $docx['endnotes'];
        $comments = $docx['comments'];

        $t->same(1, $footnotes['internalRelationshipTargetCollisionCount']);
        $t->same(2, $footnotes['internalRelationshipTargetCollisionRelationshipCount']);
        $t->same(1, $footnotes['recordInternalRelationshipTargetCollisionCount']);
        $t->same(2, $footnotes['recordInternalRelationshipTargetCollisionRelationshipCount']);
        $t->same('notes/media/shared-foot.png', $footnotes['internalRelationshipTargetCollisions'][0]['targetPart']);
        $t->same(['rFootInternalA', 'rFootInternalB'], $footnotes['internalRelationshipTargetCollisions'][0]['relationshipIds']);
        $t->same(['?slot=a#img', '?slot=b#img'], $footnotes['internalRelationshipTargetCollisions'][0]['targetReferenceSuffixes']);
        $t->same(1, $footnotes['externalRelationshipTargetCollisionCount']);
        $t->same(2, $footnotes['externalRelationshipTargetCollisionRelationshipCount']);
        $t->same('https://example.test/shared-footnote-source', $footnotes['externalRelationshipTargetCollisions'][0]['target']);
        $t->same(['rFootExternalA', 'rFootExternalB'], $footnotes['externalRelationshipTargetCollisions'][0]['relationshipIds']);
        $t->same(['rFootExternalA', 'rFootInternalA'], $footnotes['byId']['42']['referencedRelationshipIds']);
        $t->same(2, $footnotes['byId']['42']['referencedRelationshipCount']);
        $t->same('notes/media/shared-foot.png', $footnotes['byId']['42']['referencedRelationships']['rFootInternalA']['targetPart']);
        $t->same('https://example.test/shared-footnote-source', $footnotes['byId']['42']['referencedRelationships']['rFootExternalA']['target']);
        $t->same(['https://example.test/shared-footnote-source', 'media/shared-foot.png?slot=a#img'], array_column($footnotes['byId']['42']['referencedRelationshipItems'], 'target'));

        $t->same(1, $endnotes['internalRelationshipTargetCollisionCount']);
        $t->same(2, $endnotes['internalRelationshipTargetCollisionRelationshipCount']);
        $t->same('notes/media/shared-end.png', $endnotes['internalRelationshipTargetCollisions'][0]['targetPart']);
        $t->same(['rEndInternalA', 'rEndInternalB'], $endnotes['internalRelationshipTargetCollisions'][0]['relationshipIds']);
        $t->same(1, $endnotes['duplicateRelationshipIdCount']);
        $t->same(['rEndDuplicate'], $endnotes['duplicateRelationshipIds']);
        $t->same(['rEndDuplicate', 'rEndInternalA'], $endnotes['byId']['7']['referencedRelationshipIds']);
        $t->same(['rEndDuplicate'], $endnotes['byId']['7']['referencedDuplicateRelationshipIds']);
        $t->same('notes/media/end-duplicate-b.png', $endnotes['byId']['7']['referencedRelationships']['rEndDuplicate']['targetPart']);
        $t->same([2, 3, 0], array_column($endnotes['byId']['7']['referencedRelationshipRecords'], 'ordinal'));

        $t->same(1, $comments['externalRelationshipTargetCollisionCount']);
        $t->same(2, $comments['externalRelationshipTargetCollisionRelationshipCount']);
        $t->same(1, $comments['recordExternalRelationshipTargetCollisionCount']);
        $t->same(2, $comments['recordExternalRelationshipTargetCollisionRelationshipCount']);
        $t->same('https://example.test/comment-collision?review=1#src', $comments['externalRelationshipTargetCollisions'][0]['target']);
        $t->same(['rCommentExternalA', 'rCommentExternalB'], $comments['externalRelationshipTargetCollisions'][0]['relationshipIds']);
        $t->same(['rCommentExternalA'], $comments['byId']['12']['referencedRelationshipIds']);
        $t->same('https://example.test/comment-collision?review=1#src', $comments['byId']['12']['referencedRelationships']['rCommentExternalA']['target']);
    },
    'preserves docx commentsExtended package metadata from relationship target' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/comments/review-comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>' . "\n" .
            '  <Override PartName="/word/comments/review-comments-extended.xml" ContentType="application/vnd.ms-word.commentsExt+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments/review-comments.xml"/>' . "\n" .
            '  <Relationship Id="rCommentsExtended" Type="http://schemas.microsoft.com/office/2011/relationships/commentsExtended" Target="comments/review-comments-extended.xml?thread=1#commentsEx"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            '<w:hyperlink r:id="rLink"><w:r><w:t>source link</w:t></w:r></w:hyperlink>',
            '<w:hyperlink r:id="rLink"><w:r><w:t>source link</w:t></w:r></w:hyperlink>' . "\n" .
            '      <w:r><w:t xml:space="preserve"> with resolved review </w:t></w:r>' . "\n" .
            '      <w:r><w:commentReference w:id="12"/></w:r>' . "\n" .
            '      <w:r><w:t xml:space="preserve"> and threaded reply </w:t></w:r>' . "\n" .
            '      <w:r><w:commentReference w:id="13"/></w:r>',
            $parts['word/document.xml']
        );
        $parts['word/comments/review-comments.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:w14="http://schemas.microsoft.com/office/word/2010/wordml">
  <w:comment w:id="12" w:author="Review Lead" w:initials="RL" w:date="2026-06-11T18:20:00Z">
    <w:p w14:paraId="00ABCDEF"><w:r><w:t>Resolved comment package context.</w:t></w:r></w:p>
  </w:comment>
  <w:comment w:id="13" w:author="Review Reply" w:initials="RR" w:date="2026-06-11T18:25:00Z">
    <w:p w14:paraId="00FEDCBA"><w:r><w:t>Threaded reply package context.</w:t></w:r></w:p>
  </w:comment>
</w:comments>
XML;
        $parts['word/comments/review-comments-extended.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w15:commentsEx xmlns:w15="http://schemas.microsoft.com/office/word/2012/wordml">
  <w15:commentEx w15:paraId="00ABCDEF" w15:done="1"/>
  <w15:commentEx w15:paraId="00FEDCBA" w15:paraIdParent="00ABCDEF" w15:done="0"/>
</w15:commentsEx>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $commentsExtended = $docx['commentsExtended'];
        $relationship = $docx['commentsExtendedRelationship'];
        $selected = $docx['packageProvenance']['selectedXmlParts']['byKind']['commentsExtended'];
        $relationshipTypes = $docx['packageProvenance']['relationshipTypes'];
        $relationshipType = 'http://schemas.microsoft.com/office/2011/relationships/commentsExtended';
        $paragraph = $document->children[1];
        $notes = array_values(array_filter($paragraph->children, static fn (AstNode $node): bool => $node->type === 'note'));
        $resolvedNote = $notes[0];
        $replyNote = $notes[1];

        $t->same('word/comments/review-comments-extended.xml', $docx['commentsExtendedPart']);
        $t->same('rCommentsExtended', $relationship['id']);
        $t->same('comments/review-comments-extended.xml?thread=1#commentsEx', $relationship['target']);
        $t->same('word/comments/review-comments-extended.xml?thread=1#commentsEx', $relationship['resolvedTarget']);
        $t->same('word/comments/review-comments-extended.xml', $relationship['targetPart']);
        $t->same('thread=1', $relationship['targetQuery']);
        $t->same('commentsEx', $relationship['targetFragment']);
        $t->same(true, $relationship['exists']);
        $t->same('application/vnd.ms-word.commentsExt+xml', $relationship['contentType']);
        $t->same(2, $commentsExtended['count']);
        $t->same(1, $commentsExtended['resolvedCount']);
        $t->same(1, $commentsExtended['threadedCount']);
        $t->same(['00ABCDEF', '00FEDCBA'], $commentsExtended['paraIds']);
        $t->same(true, $commentsExtended['byParaId']['00ABCDEF']['resolved']);
        $t->same('00ABCDEF', $commentsExtended['byParaId']['00FEDCBA']['parentParaId']);
        $t->same(false, $commentsExtended['byParaId']['00FEDCBA']['resolved']);

        $t->same('relationship', $selected['selectionSource']);
        $t->same('commentsEx', $selected['rootLocalName']);
        $t->same('http://schemas.microsoft.com/office/word/2012/wordml', $selected['rootNamespace']);
        $t->same(true, $selected['validRoot']);
        $t->same(true, $selected['contentTypeMatchesExpected']);
        $t->same('thread=1', $selected['targetQuery']);
        $t->same('commentsEx', $selected['targetFragment']);

        $t->same('commentsExtended', $relationshipTypes[$relationshipType]['label']);
        $t->same(1, $relationshipTypes[$relationshipType]['count']);
        $t->same(['word/comments/review-comments-extended.xml'], $relationshipTypes[$relationshipType]['existingTargetParts']);

        $t->same('00ABCDEF', $docx['comments']['byId']['12']['commentParaId']);
        $t->same(true, $docx['comments']['byId']['12']['commentResolved']);
        $t->same('word/comments/review-comments-extended.xml', $docx['comments']['byId']['12']['commentsExtendedPart']);
        $t->same('00FEDCBA', $docx['comments']['byId']['13']['commentParaId']);
        $t->same('00ABCDEF', $docx['comments']['byId']['13']['commentParentParaId']);
        $t->same(false, $docx['comments']['byId']['13']['commentResolved']);

        $t->same('12', $resolvedNote->attr('id'));
        $t->same('00ABCDEF', $resolvedNote->attr('commentParaId'));
        $t->same(true, $resolvedNote->attr('commentResolved'));
        $t->same('word/comments/review-comments-extended.xml', $resolvedNote->attr('commentsExtendedPart'));
        $t->same('13', $replyNote->attr('id'));
        $t->same('00FEDCBA', $replyNote->attr('commentParaId'));
        $t->same('00ABCDEF', $replyNote->attr('commentParentParaId'));
        $t->same(false, $replyNote->attr('commentResolved'));
    },
    'classifies docx note package inventory roles from document relationships' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/notes/review-footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>' . "\n" .
            '  <Override PartName="/word/notes/review-endnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.endnotes+xml"/>' . "\n" .
            '  <Override PartName="/word/comments/review-comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>' . "\n" .
            '  <Override PartName="/word/comments/review-comments-extended.xml" ContentType="application/vnd.ms-word.commentsExt+xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="notes/review-footnotes.xml"/>' . "\n" .
            '  <Relationship Id="rEndnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="notes/review-endnotes.xml"/>' . "\n" .
            '  <Relationship Id="rComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments/review-comments.xml"/>' . "\n" .
            '  <Relationship Id="rCommentsExtended" Type="http://schemas.microsoft.com/office/2011/relationships/commentsExtended" Target="comments/review-comments-extended.xml?thread=2#commentsEx"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/notes/review-footnotes.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:footnote w:id="3"><w:p><w:r><w:t>Footnote inventory packet.</w:t></w:r></w:p></w:footnote>
</w:footnotes>
XML;
        $parts['word/notes/review-endnotes.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:endnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:endnote w:id="4"><w:p><w:r><w:t>Endnote inventory packet.</w:t></w:r></w:p></w:endnote>
</w:endnotes>
XML;
        $parts['word/comments/review-comments.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:comment w:id="5"><w:p><w:r><w:t>Comment inventory packet.</w:t></w:r></w:p></w:comment>
</w:comments>
XML;
        $parts['word/comments/review-comments-extended.xml'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w15:commentsEx xmlns:w15="http://schemas.microsoft.com/office/word/2012/wordml">
  <w15:commentEx w15:paraId="00ABCDEF" w15:done="1"/>
</w15:commentsEx>
XML;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $package = $document->attr('docx')['packageProvenance'];
        $inventory = $package['parts'];
        $roleCounts = $package['summary']['roleCounts'];

        $t->true(in_array('footnotes', $inventory['word/notes/review-footnotes.xml']['roles'], true), 'footnotes inventory role missing');
        $t->true(in_array('endnotes', $inventory['word/notes/review-endnotes.xml']['roles'], true), 'endnotes inventory role missing');
        $t->true(in_array('comments', $inventory['word/comments/review-comments.xml']['roles'], true), 'comments inventory role missing');
        $t->true(in_array('comments-extended', $inventory['word/comments/review-comments-extended.xml']['roles'], true), 'commentsExtended inventory role missing');
        $t->same(1, $roleCounts['footnotes']);
        $t->same(1, $roleCounts['endnotes']);
        $t->same(1, $roleCounts['comments']);
        $t->same(1, $roleCounts['comments-extended']);
    },
    'summarizes docx alternative format import chunks from document relationships' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Default Extension="png" ContentType="image/png"/>',
            '  <Default Extension="png" ContentType="image/png"/>' . "\n" .
            '  <Default Extension="html" ContentType="text/html"/>' . "\n" .
            '  <Default Extension="txt" ContentType="text/plain"/>',
            $parts['[Content_Types].xml']
        );
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>',
            '  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>' . "\n" .
            '  <Override PartName="/word/chunks/review.html" ContentType="text/html; charset=utf-8"/>' . "\n" .
            '  <Override PartName="/word/chunks/unreferenced.html" ContentType="text/html"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rAltHtml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/review.html?slot=body#chunk"/>' . "\n" .
            '  <Relationship Id="rAltText" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/review.txt"/>' . "\n" .
            '  <Relationship Id="rAltMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/missing.html"/>' . "\n" .
            '  <Relationship Id="rAltRemote" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="https://example.test/review.html" TargetMode="External"/>' . "\n" .
            '  <Relationship Id="rAltUnreferenced" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/unreferenced.html"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            '  </w:body>',
            '    <w:altChunk r:id="rAltHtml"/>' . "\n" .
            '    <w:altChunk r:id="rAltText"/>' . "\n" .
            '    <w:altChunk r:id="rAltMissing"/>' . "\n" .
            '    <w:altChunk r:id="rAltRemote"/>' . "\n" .
            '    <w:altChunk r:id="rAltUnknown"/>' . "\n" .
            '  </w:body>',
            $parts['word/document.xml']
        );
        $parts['word/chunks/review.html'] = '<section><h2>Embedded review HTML</h2></section>';
        $parts['word/chunks/review.txt'] = 'Plain text chunk';
        $parts['word/chunks/unreferenced.html'] = '<p>Unreferenced chunk</p>';
        $parts['word/chunks/_rels/review.html.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rChunkImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/chunk.png"/>
</Relationships>
XML;
        $parts['word/media/chunk.png'] = 'chunk png bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $alternativeFormats = $docx['alternativeFormats'];
        $html = $alternativeFormats['byRelationshipId']['rAltHtml'];
        $text = $alternativeFormats['byRelationshipId']['rAltText'];
        $missing = $alternativeFormats['byRelationshipId']['rAltMissing'];
        $external = $alternativeFormats['byRelationshipId']['rAltRemote'];
        $unknown = $alternativeFormats['byRelationshipId']['rAltUnknown'];
        $unreferenced = $alternativeFormats['byRelationshipId']['rAltUnreferenced'];
        $relationshipTypes = $docx['packageProvenance']['relationshipTypes'];
        $altChunkType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk';

        $t->same(6, $alternativeFormats['count']);
        $t->same(5, $alternativeFormats['relationshipCount']);
        $t->same(5, $alternativeFormats['referencedCount']);
        $t->same(1, $alternativeFormats['unreferencedRelationshipCount']);
        $t->same(3, $alternativeFormats['existingCount']);
        $t->same(1, $alternativeFormats['missingCount']);
        $t->same(1, $alternativeFormats['externalCount']);
        $t->same(1, $alternativeFormats['unresolvedCount']);
        $t->same(0, $alternativeFormats['missingContentTypeCount']);
        $t->same(['rAltHtml', 'rAltText', 'rAltMissing', 'rAltRemote', 'rAltUnknown', 'rAltUnreferenced'], $alternativeFormats['relationshipIds']);
        $t->same(['rAltHtml', 'rAltText', 'rAltMissing', 'rAltRemote', 'rAltUnknown'], $alternativeFormats['referencedRelationshipIds']);
        $t->same(['rAltUnreferenced'], $alternativeFormats['unreferencedRelationshipIds']);
        $t->same(['word/chunks/review.html', 'word/chunks/review.txt', 'word/chunks/missing.html', 'word/chunks/unreferenced.html'], $alternativeFormats['partNames']);

        $t->same(true, $html['referenced']);
        $t->same('word/chunks/review.html', $html['partName']);
        $t->same('chunks/review.html?slot=body#chunk', $html['target']);
        $t->same('word/chunks/review.html?slot=body#chunk', $html['resolvedTarget']);
        $t->same('slot=body', $html['targetQuery']);
        $t->same('chunk', $html['targetFragment']);
        $t->same('?slot=body#chunk', $html['targetReferenceSuffix']);
        $t->same(true, $html['exists']);
        $t->same(strlen($parts['word/chunks/review.html']), $html['bytes']);
        $t->same('text/html; charset=utf-8', $html['contentType']);
        $t->same('override', $html['contentTypeSource']);
        $t->same('word/chunks/_rels/review.html.rels', $html['relationshipsPart']);
        $t->same(1, $html['relationshipCount']);
        $t->same('word/document.xml', $html['relationship']['sourcePart']);
        $t->same('word/_rels/document.xml.rels', $html['relationship']['relationshipsPart']);
        $t->same('word/chunks/review.html', $html['relationship']['targetPart']);
        $t->same([], $html['issues']);

        $t->same('text/plain', $text['contentType']);
        $t->same('default', $text['contentTypeSource']);
        $t->same(true, $text['exists']);
        $t->same([], $text['issues']);
        $t->same(false, $missing['exists']);
        $t->same('word/chunks/missing.html', $missing['partName']);
        $t->same('text/html', $missing['contentType']);
        $t->same(['missing-in-package'], $missing['issues']);
        $t->same(true, $external['external']);
        $t->same(null, $external['partName']);
        $t->same(['external-altchunk'], $external['issues']);
        $t->same(null, $unknown['relationship']);
        $t->same(['unknown-relationship'], $unknown['issues']);
        $t->same(false, $unreferenced['referenced']);
        $t->same(true, $unreferenced['exists']);
        $t->same('text/html', $unreferenced['contentType']);

        $t->same('aFChunk', $relationshipTypes[$altChunkType]['label']);
        $t->same(5, $relationshipTypes[$altChunkType]['count']);
        $t->same(4, $relationshipTypes[$altChunkType]['internalCount']);
        $t->same(1, $relationshipTypes[$altChunkType]['externalCount']);
        $t->true(in_array('word/chunks/review.html', $relationshipTypes[$altChunkType]['existingTargetParts'], true), 'altChunk existing target missing from relationship type provenance');
    },
    'summarizes docx subdocument relationships as unsupported package diagnostics' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Default Extension="png" ContentType="image/png"/>',
            '  <Default Extension="png" ContentType="image/png"/>' . "\n" .
            '  <Default Extension="docx" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document"/>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rSubExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/subDocument" Target="https://example.test/subdocuments/source-review.docx?revision=4#main" TargetMode="External"/>' . "\n" .
            '  <Relationship Id="rSubInternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/subDocument" Target="subdocuments/internal.docx"/>' . "\n" .
            '  <Relationship Id="rSubMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/subDocument" Target="subdocuments/missing.docx"/>' . "\n" .
            '  <Relationship Id="rSubUnreferenced" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/subDocument" Target="subdocuments/unreferenced.docx"/>' . "\n" .
            '  <Relationship Id="rSubWrongType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/not-subdocument" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            '  </w:body>',
            '    <w:subDoc r:id="rSubExternal"/>' . "\n" .
            '    <w:subDoc r:id="rSubInternal"/>' . "\n" .
            '    <w:subDoc r:id="rSubMissing"/>' . "\n" .
            '    <w:subDoc r:id="rSubWrongType"/>' . "\n" .
            '    <w:subDoc r:id="rSubUnknown"/>' . "\n" .
            '    <w:subDoc/>' . "\n" .
            '  </w:body>',
            $parts['word/document.xml']
        );
        $parts['word/subdocuments/internal.docx'] = 'internal subdocument bytes';
        $parts['word/subdocuments/unreferenced.docx'] = 'unreferenced subdocument bytes';

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $subdocuments = $docx['subdocuments'];
        $package = $docx['packageProvenance'];
        $summary = $package['summary'];
        $relationshipTypes = $package['relationshipTypes'];
        $subdocumentRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/subDocument';
        $external = $subdocuments['byRelationshipId']['rSubExternal'];
        $internal = $subdocuments['byRelationshipId']['rSubInternal'];
        $missing = $subdocuments['byRelationshipId']['rSubMissing'];
        $wrongType = $subdocuments['byRelationshipId']['rSubWrongType'];
        $unknown = $subdocuments['byRelationshipId']['rSubUnknown'];
        $unreferenced = $subdocuments['byRelationshipId']['rSubUnreferenced'];
        $missingId = $subdocuments['items'][5];

        $t->same($subdocuments, $package['subdocuments']);
        $t->same(7, $subdocuments['count']);
        $t->same(4, $subdocuments['relationshipCount']);
        $t->same(6, $subdocuments['referencedCount']);
        $t->same(1, $subdocuments['unreferencedRelationshipCount']);
        $t->same(2, $subdocuments['existingCount']);
        $t->same(1, $subdocuments['missingCount']);
        $t->same(2, $subdocuments['externalCount']);
        $t->same(3, $subdocuments['internalCount']);
        $t->same(7, $subdocuments['unsupportedCount']);
        $t->same(6, $subdocuments['issueCount']);
        $t->same([
            'internal-subdocument-target',
            'missing-in-package',
            'missing-relationship-id',
            'unexpected-relationship-type',
            'unknown-relationship',
        ], $subdocuments['issueCodes']);
        $t->same(false, $subdocuments['directReaderParity']);
        $t->same('subdocument-master-document-expansion-not-implemented', $subdocuments['unsupportedReason']);
        $t->same('subdocument-package-bytes-blocked', $subdocuments['byteExposurePolicy']);
        $t->same('subdocument-metadata-only', $subdocuments['reviewPolicy']);
        $t->same(['rSubExternal', 'rSubInternal', 'rSubMissing', 'rSubWrongType', 'rSubUnknown', 'rSubUnreferenced'], $subdocuments['relationshipIds']);
        $t->same(['rSubExternal', 'rSubInternal', 'rSubMissing', 'rSubWrongType', 'rSubUnknown'], $subdocuments['referencedRelationshipIds']);
        $t->same(['rSubUnreferenced'], $subdocuments['unreferencedRelationshipIds']);
        $t->same(['word/subdocuments/internal.docx', 'word/subdocuments/missing.docx', 'word/subdocuments/unreferenced.docx'], $subdocuments['partNames']);
        $t->same([
            'https://example.test/subdocuments/source-review.docx?revision=4#main',
            'https://example.test/not-subdocument',
        ], $subdocuments['externalTargets']);

        $t->same('rSubExternal', $external['relationshipId']);
        $t->same(true, $external['referenced']);
        $t->same($subdocumentRel, $external['relationshipType']);
        $t->same('https://example.test/subdocuments/source-review.docx?revision=4#main', $external['target']);
        $t->same('revision=4', $external['targetQuery']);
        $t->same('main', $external['targetFragment']);
        $t->same('?revision=4#main', $external['targetReferenceSuffix']);
        $t->same(true, $external['external']);
        $t->same(null, $external['targetPart']);
        $t->same([], $external['issues']);

        $t->same('word/subdocuments/internal.docx', $internal['targetPart']);
        $t->same(true, $internal['exists']);
        $t->same(strlen($parts['word/subdocuments/internal.docx']), $internal['bytes']);
        $t->same(sprintf('%08x', crc32($parts['word/subdocuments/internal.docx'])), $internal['crc32']);
        $t->same(hash('sha256', $parts['word/subdocuments/internal.docx']), $internal['sha256']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document', $internal['contentType']);
        $t->same('default', $internal['contentTypeSource']);
        $t->same(['internal-subdocument-target'], $internal['issues']);

        $t->same('word/subdocuments/missing.docx', $missing['targetPart']);
        $t->same(false, $missing['exists']);
        $t->same(['internal-subdocument-target', 'missing-in-package'], $missing['issues']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink', $wrongType['relationshipType']);
        $t->same(['unexpected-relationship-type'], $wrongType['issues']);
        $t->same(null, $unknown['relationship']);
        $t->same(['unknown-relationship'], $unknown['issues']);
        $t->same('', $missingId['relationshipId']);
        $t->same(['missing-relationship-id'], $missingId['issues']);
        $t->same(false, $unreferenced['referenced']);
        $t->same('word/subdocuments/unreferenced.docx', $unreferenced['targetPart']);
        $t->same(true, $unreferenced['exists']);
        $t->same(['internal-subdocument-target'], $unreferenced['issues']);

        $t->same(7, $summary['subdocumentCount']);
        $t->same(4, $summary['subdocumentRelationshipCount']);
        $t->same(6, $summary['subdocumentReferencedCount']);
        $t->same(2, $summary['subdocumentExistingCount']);
        $t->same(1, $summary['subdocumentMissingCount']);
        $t->same(2, $summary['subdocumentExternalCount']);
        $t->same(3, $summary['subdocumentInternalCount']);
        $t->same(7, $summary['subdocumentUnsupportedCount']);
        $t->same(6, $summary['subdocumentIssueCount']);
        $t->same($subdocuments['issueCodes'], $summary['subdocumentIssueCodes']);
        $t->same('subDocument', $relationshipTypes[$subdocumentRel]['label']);
        $t->same(4, $relationshipTypes[$subdocumentRel]['count']);
        $t->same(3, $relationshipTypes[$subdocumentRel]['internalCount']);
        $t->same(1, $relationshipTypes[$subdocumentRel]['externalCount']);
        $t->same(['word/subdocuments/internal.docx', 'word/subdocuments/unreferenced.docx'], $relationshipTypes[$subdocumentRel]['existingTargetParts']);
        $t->same(['word/subdocuments/missing.docx'], $relationshipTypes[$subdocumentRel]['missingTargetParts']);
        $t->true(in_array('subdocument', $package['parts']['word/subdocuments/internal.docx']['roles'], true), 'internal subdocument role missing');
        $t->true(in_array('subdocument', $package['parts']['word/subdocuments/unreferenced.docx']['roles'], true), 'unreferenced subdocument role missing');
    },
    'summarizes docx chart package parts from drawing relationships' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $chartRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart';
        $chartContentType = 'application/vnd.openxmlformats-officedocument.drawingml.chart+xml';
        $chartXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <c:chart><c:title/><c:plotArea/></c:chart>
</c:chartSpace>
XML;
        $badChartXml = '<review-chart/>';
        $unreferencedChartXml = '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"><c:chart/></c:chartSpace>';

        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/charts/chart1.xml" ContentType="' . $chartContentType . '; profile=review-chart"/>' . "\n" .
            '  <Override PartName="/word/charts/bad-chart.xml" ContentType="application/xml"/>' . "\n" .
            '  <Override PartName="/word/charts/missing-chart.xml" ContentType="' . $chartContentType . '"/>' . "\n" .
            '  <Override PartName="/word/charts/unreferenced.xml" ContentType="' . $chartContentType . '"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rChart" Type="' . $chartRel . '" Target="charts/chart1.xml?series=1#chart"/>' . "\n" .
            '  <Relationship Id="rBadChart" Type="' . $chartRel . '" Target="charts/bad-chart.xml"/>' . "\n" .
            '  <Relationship Id="rMissingChart" Type="' . $chartRel . '" Target="charts/missing-chart.xml"/>' . "\n" .
            '  <Relationship Id="rExternalChart" Type="' . $chartRel . '" Target="https://example.test/chart.xml?remote=1#chart" TargetMode="External"/>' . "\n" .
            '  <Relationship Id="rWrongType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>' . "\n" .
            '  <Relationship Id="rUnreferencedChart" Type="' . $chartRel . '" Target="charts/unreferenced.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"',
            'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"',
            $parts['word/document.xml']
        );
        $parts['word/document.xml'] = str_replace(
            "      </w:r>\n    </w:p>\n    <w:tbl>",
            "      </w:r>\n" .
            "      <w:r><w:drawing><wp:inline><a:graphic><a:graphicData uri=\"http://schemas.openxmlformats.org/drawingml/2006/chart\"><c:chart r:id=\"rChart\"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>\n" .
            "      <w:r><w:drawing><wp:inline><a:graphic><a:graphicData uri=\"http://schemas.openxmlformats.org/drawingml/2006/chart\"><c:chart r:id=\"rBadChart\"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>\n" .
            "      <w:r><w:drawing><wp:inline><a:graphic><a:graphicData uri=\"http://schemas.openxmlformats.org/drawingml/2006/chart\"><c:chart r:id=\"rMissingChart\"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>\n" .
            "      <w:r><w:drawing><wp:inline><a:graphic><a:graphicData uri=\"http://schemas.openxmlformats.org/drawingml/2006/chart\"><c:chart r:id=\"rExternalChart\"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>\n" .
            "      <w:r><w:drawing><wp:inline><a:graphic><a:graphicData uri=\"http://schemas.openxmlformats.org/drawingml/2006/chart\"><c:chart r:id=\"rWrongType\"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>\n" .
            "      <w:r><w:drawing><wp:inline><a:graphic><a:graphicData uri=\"http://schemas.openxmlformats.org/drawingml/2006/chart\"><c:chart/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>\n" .
            "    </w:p>\n    <w:tbl>",
            $parts['word/document.xml']
        );
        $parts['word/charts/chart1.xml'] = $chartXml;
        $parts['word/charts/bad-chart.xml'] = $badChartXml;
        $parts['word/charts/unreferenced.xml'] = $unreferencedChartXml;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $charts = $docx['chartParts'];
        $summary = $docx['packageProvenance']['summary'];
        $chart = $charts['byRelationshipId']['rChart'];
        $bad = $charts['byRelationshipId']['rBadChart'];
        $missing = $charts['byRelationshipId']['rMissingChart'];
        $external = $charts['byRelationshipId']['rExternalChart'];
        $wrongType = $charts['byRelationshipId']['rWrongType'];
        $unreferenced = $charts['byRelationshipId']['rUnreferencedChart'];
        $relationshipTypes = $docx['packageProvenance']['relationshipTypes'];
        $inventory = $docx['packageProvenance']['parts'];

        $t->same($charts, $docx['packageProvenance']['chartParts']);
        $t->same(7, $charts['count']);
        $t->same(5, $charts['relationshipCount']);
        $t->same(6, $charts['referencedCount']);
        $t->same(1, $charts['unreferencedRelationshipCount']);
        $t->same(3, $charts['existingCount']);
        $t->same(1, $charts['missingCount']);
        $t->same(1, $charts['externalCount']);
        $t->same(1, $charts['unresolvedCount']);
        $t->same(0, $charts['invalidXmlCount']);
        $t->same(1, $charts['unexpectedRootCount']);
        $t->same(1, $charts['unexpectedRelationshipTypeCount']);
        $t->same(0, $charts['missingContentTypeCount']);
        $t->same(1, $charts['unexpectedContentTypeCount']);
        $t->same(5, $charts['issueCount']);
        $t->same([
            'external-chart-part',
            'missing-chart-part',
            'missing-relationship-id',
            'unexpected-chart-content-type',
            'unexpected-chart-root',
            'unexpected-relationship-type',
        ], $charts['issueCodes']);
        $t->same(['rChart', 'rBadChart', 'rMissingChart', 'rExternalChart', 'rWrongType', 'rUnreferencedChart'], $charts['relationshipIds']);
        $t->same(['rChart', 'rBadChart', 'rMissingChart', 'rExternalChart', 'rWrongType'], $charts['referencedRelationshipIds']);
        $t->same(['rUnreferencedChart'], $charts['unreferencedRelationshipIds']);
        $t->same(['word/charts/chart1.xml', 'word/charts/bad-chart.xml', 'word/charts/missing-chart.xml', 'word/charts/unreferenced.xml'], $charts['partNames']);
        $t->same('chart-part-bytes-blocked', $charts['byteExposurePolicy']);
        $t->same('chart-part-metadata-only', $charts['reviewPolicy']);

        $t->same(true, $chart['referenced']);
        $t->same($chartRel, $chart['relationshipType']);
        $t->same('charts/chart1.xml?series=1#chart', $chart['target']);
        $t->same('word/charts/chart1.xml?series=1#chart', $chart['resolvedTarget']);
        $t->same('word/charts/chart1.xml', $chart['targetPart']);
        $t->same('series=1', $chart['targetQuery']);
        $t->same('chart', $chart['targetFragment']);
        $t->same('?series=1#chart', $chart['targetReferenceSuffix']);
        $t->same(strlen($chartXml), $chart['byteLength']);
        $t->same(sprintf('%08x', crc32($chartXml)), $chart['crc32']);
        $t->same(hash('sha256', $chartXml), $chart['sha256']);
        $t->same($chartContentType . '; profile=review-chart', $chart['contentType']);
        $t->same($chartContentType, $chart['contentTypeBase']);
        $t->same(['profile' => 'review-chart'], $chart['contentTypeParameterMap']);
        $t->same('override', $chart['contentTypeSource']);
        $t->same('word/charts/_rels/chart1.xml.rels', $chart['chartRelationshipsPart']);
        $t->same(0, $chart['chartRelationshipCount']);
        $t->same(true, $chart['validXml']);
        $t->same(true, $chart['validRoot']);
        $t->same('http://schemas.openxmlformats.org/drawingml/2006/chart', $chart['rootNamespace']);
        $t->same('chartSpace', $chart['rootLocalName']);
        $t->same([], $chart['issues']);
        $t->same(true, $chart['valid']);

        $t->same(['unexpected-chart-content-type', 'unexpected-chart-root'], $bad['issues']);
        $t->same('application/xml', $bad['contentType']);
        $t->same('review-chart', $bad['rootLocalName']);
        $t->same(['missing-chart-part'], $missing['issues']);
        $t->same(false, $missing['exists']);
        $t->same(['external-chart-part'], $external['issues']);
        $t->same(true, $external['external']);
        $t->same(null, $external['targetPart']);
        $t->same(['unexpected-relationship-type'], $wrongType['issues']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $wrongType['relationshipType']);
        $t->same(false, $unreferenced['referenced']);
        $t->same(true, $unreferenced['exists']);
        $t->same([], $unreferenced['issues']);

        $t->same(7, $summary['chartPartCount']);
        $t->same(5, $summary['chartPartRelationshipCount']);
        $t->same(6, $summary['chartPartReferencedCount']);
        $t->same(3, $summary['chartPartExistingCount']);
        $t->same(1, $summary['chartPartMissingCount']);
        $t->same(1, $summary['chartPartExternalCount']);
        $t->same(5, $summary['chartPartIssueCount']);
        $t->same($charts['issueCodes'], $summary['chartPartIssueCodes']);
        $t->same('chart', $relationshipTypes[$chartRel]['label']);
        $t->same(5, $relationshipTypes[$chartRel]['count']);
        $t->same(4, $relationshipTypes[$chartRel]['internalCount']);
        $t->same(1, $relationshipTypes[$chartRel]['externalCount']);
        $t->same(['word/charts/chart1.xml', 'word/charts/bad-chart.xml', 'word/charts/unreferenced.xml'], $relationshipTypes[$chartRel]['existingTargetParts']);
        $t->true(in_array('chart-part', $inventory['word/charts/chart1.xml']['roles'], true), 'chart inventory role missing');
        $t->true(in_array('chart-part', $inventory['word/charts/unreferenced.xml']['roles'], true), 'unreferenced chart inventory role missing');
        $t->true(!isset($docx['media']['word/charts/chart1.xml']), 'Chart XML should not be exposed as document media');
    },
    'summarizes docx chart embedded package relationships for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $chartRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart';
        $packageRel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/package';
        $chartContentType = 'application/vnd.openxmlformats-officedocument.drawingml.chart+xml';
        $workbookContentType = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
        $workbookBytes = 'embedded chart workbook bytes';
        $unreferencedWorkbookBytes = 'unreferenced chart workbook bytes';
        $chartXml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <c:chart>
    <c:externalData r:id="rWorkbook"/>
    <c:externalData r:id="rMissingWorkbook"/>
    <c:externalData r:id="rExternalWorkbook"/>
  </c:chart>
</c:chartSpace>
XML;

        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/charts/chart-embedded.xml" ContentType="' . $chartContentType . '"/>' . "\n" .
            '  <Override PartName="/word/embeddings/chart-workbook.xlsx" ContentType="' . $workbookContentType . '; profile=chart-data"/>' . "\n" .
            '  <Override PartName="/word/embeddings/missing-workbook.xlsx" ContentType="' . $workbookContentType . '"/>' . "\n" .
            '  <Override PartName="/word/embeddings/unreferenced-workbook.xlsx" ContentType="' . $workbookContentType . '"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rChartEmbedded" Type="' . $chartRel . '" Target="charts/chart-embedded.xml"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/document.xml'] = str_replace(
            'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture"',
            'xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture" xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"',
            $parts['word/document.xml']
        );
        $parts['word/document.xml'] = str_replace(
            "      </w:r>\n    </w:p>\n    <w:tbl>",
            "      </w:r>\n" .
            "      <w:r><w:drawing><wp:inline><a:graphic><a:graphicData uri=\"http://schemas.openxmlformats.org/drawingml/2006/chart\"><c:chart r:id=\"rChartEmbedded\"/></a:graphicData></a:graphic></wp:inline></w:drawing></w:r>\n" .
            "    </w:p>\n    <w:tbl>",
            $parts['word/document.xml']
        );
        $parts['word/charts/chart-embedded.xml'] = $chartXml;
        $parts['word/charts/_rels/chart-embedded.xml.rels'] = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="../embeddings/chart-workbook.xlsx?sheet=Sheet1#table"/>
  <Relationship Id="rMissingWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="../embeddings/missing-workbook.xlsx"/>
  <Relationship Id="rExternalWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="https://example.test/chart-workbook.xlsx?remote=1#sheet" TargetMode="External"/>
  <Relationship Id="rUnreferencedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="../embeddings/unreferenced-workbook.xlsx"/>
  <Relationship Id="rChartImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/review.png"/>
</Relationships>
XML;
        $parts['word/embeddings/chart-workbook.xlsx'] = $workbookBytes;
        $parts['word/embeddings/unreferenced-workbook.xlsx'] = $unreferencedWorkbookBytes;

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $package = $docx['packageProvenance'];
        $charts = $docx['chartParts'];
        $summary = $package['summary'];
        $chart = $charts['byRelationshipId']['rChartEmbedded'];
        $embeddedPackages = $chart['embeddedPackages'];
        $workbook = $embeddedPackages['byRelationshipId']['rWorkbook'];
        $missing = $embeddedPackages['byRelationshipId']['rMissingWorkbook'];
        $external = $embeddedPackages['byRelationshipId']['rExternalWorkbook'];
        $unreferenced = $embeddedPackages['byRelationshipId']['rUnreferencedWorkbook'];
        $chartRelationshipsPart = $package['relationshipParts']['word/charts/_rels/chart-embedded.xml.rels'];
        $inventory = $package['parts'];
        $relationshipTypes = $package['relationshipTypes'];

        $t->same(['rWorkbook', 'rMissingWorkbook', 'rExternalWorkbook'], $chart['externalDataRelationshipIds']);
        $t->same(4, $embeddedPackages['count']);
        $t->same(4, $embeddedPackages['relationshipCount']);
        $t->same(3, $embeddedPackages['referencedCount']);
        $t->same(1, $embeddedPackages['unreferencedRelationshipCount']);
        $t->same(2, $embeddedPackages['existingCount']);
        $t->same(1, $embeddedPackages['missingCount']);
        $t->same(1, $embeddedPackages['externalCount']);
        $t->same(2, $embeddedPackages['issueCount']);
        $t->same(['external-chart-embedded-package', 'missing-chart-embedded-package'], $embeddedPackages['issueCodes']);
        $t->same(['rWorkbook', 'rMissingWorkbook', 'rExternalWorkbook', 'rUnreferencedWorkbook'], $embeddedPackages['relationshipIds']);
        $t->same(['rWorkbook', 'rMissingWorkbook', 'rExternalWorkbook'], $embeddedPackages['referencedRelationshipIds']);
        $t->same(['rUnreferencedWorkbook'], $embeddedPackages['unreferencedRelationshipIds']);
        $t->same(['word/embeddings/chart-workbook.xlsx', 'word/embeddings/missing-workbook.xlsx', 'word/embeddings/unreferenced-workbook.xlsx'], $embeddedPackages['partNames']);
        $t->same(['https://example.test/chart-workbook.xlsx?remote=1#sheet'], $embeddedPackages['externalTargets']);
        $t->same('chart-embedded-package-bytes-blocked', $embeddedPackages['byteExposurePolicy']);
        $t->same('chart-embedded-package-metadata-only', $embeddedPackages['reviewPolicy']);

        $t->same(true, $workbook['referenced']);
        $t->same($packageRel, $workbook['type']);
        $t->same('../embeddings/chart-workbook.xlsx?sheet=Sheet1#table', $workbook['target']);
        $t->same('word/embeddings/chart-workbook.xlsx?sheet=Sheet1#table', $workbook['resolvedTarget']);
        $t->same('word/embeddings/chart-workbook.xlsx', $workbook['targetPart']);
        $t->same('sheet=Sheet1', $workbook['targetQuery']);
        $t->same('table', $workbook['targetFragment']);
        $t->same('?sheet=Sheet1#table', $workbook['targetReferenceSuffix']);
        $t->same($workbookContentType . '; profile=chart-data', $workbook['contentType']);
        $t->same($workbookContentType, $workbook['contentTypeBase']);
        $t->same(['profile' => 'chart-data'], $workbook['contentTypeParameterMap']);
        $t->same(strlen($workbookBytes), $workbook['byteLength']);
        $t->same(sprintf('%08x', crc32($workbookBytes)), $workbook['crc32']);
        $t->same(hash('sha256', $workbookBytes), $workbook['sha256']);
        $t->same([], $workbook['issues']);
        $t->same(true, $workbook['valid']);

        $t->same(['missing-chart-embedded-package'], $missing['issues']);
        $t->same(false, $missing['exists']);
        $t->same(['external-chart-embedded-package'], $external['issues']);
        $t->same(true, $external['external']);
        $t->same(null, $external['targetPart']);
        $t->same(false, $unreferenced['referenced']);
        $t->same(true, $unreferenced['exists']);
        $t->same(strlen($unreferencedWorkbookBytes), $unreferenced['byteLength']);
        $t->same(false, $chart['valid']);
        $t->same([], $chart['issues']);

        $t->same(4, $summary['chartEmbeddedPackageCount']);
        $t->same(2, $summary['chartEmbeddedPackageExistingCount']);
        $t->same(1, $summary['chartEmbeddedPackageMissingCount']);
        $t->same(1, $summary['chartEmbeddedPackageExternalCount']);
        $t->same(2, $summary['chartEmbeddedPackageIssueCount']);
        $t->same($embeddedPackages['issueCodes'], $summary['chartEmbeddedPackageIssueCodes']);
        $t->same(5, $chartRelationshipsPart['relationshipCount']);
        $t->same('word/charts/chart-embedded.xml', $chartRelationshipsPart['sourcePart']);
        $t->same($workbookContentType, $chartRelationshipsPart['relationships']['rWorkbook']['contentTypeBase']);
        $t->same(4, $relationshipTypes[$packageRel]['count']);
        $t->same(3, $relationshipTypes[$packageRel]['internalCount']);
        $t->same(1, $relationshipTypes[$packageRel]['externalCount']);
        $t->true(in_array('embedded-package', $inventory['word/embeddings/chart-workbook.xlsx']['roles'], true), 'chart workbook inventory role missing');
        $t->true(in_array('relationship-target', $inventory['word/embeddings/unreferenced-workbook.xlsx']['roles'], true), 'unreferenced chart workbook target role missing');
        $t->true(!isset($docx['media']['word/embeddings/chart-workbook.xlsx']), 'Chart workbook package should not be exposed as document media');
    },
    'reads a native zip docx package without shelling out' => static function (TestRunner $t): void {
        $path = docx_openxml_reader_temp_docx(docx_openxml_reader_fixture_parts());
        try {
            $document = (new DocxOpenXmlReader())->readFile($path);
        } finally {
            if (is_file($path)) {
                unlink($path);
            }
        }

        $t->same('document', $document->type);
        $t->same('Imported DOCX Heading', $document->children[0]->attr('text'));
        $t->same('ordered_list', $document->children[2]->type);
        $t->same('word/media/review.png', $document->children[4]->children[1]->attr('mediaPath'));
    },
    'reads a bounded ZipPackage docx package without shelling out' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readZipPackage(
            docx_openxml_reader_zip_package(docx_openxml_reader_fixture_parts())
        );
        $docx = $document->attr('docx');
        $image = $document->children[4]->children[1];

        $t->same('Imported DOCX Batch', $document->attr('meta')['title']);
        $t->same('word/document.xml', $docx['documentPart']);
        $t->same('word/media/review.png', $image->attr('mediaPath'));
        $t->same('image/png', $image->attr('contentType'));
        $t->same(strlen('fake png bytes'), $docx['media']['word/media/review.png']['size']);
    },
    'renders docx reader ast through markdown and wordpress writers' => static function (TestRunner $t): void {
        $document = (new DocxOpenXmlReader())->readPackage(docx_openxml_reader_fixture_parts());
        $markdown = (new MarkdownWriter())->write($document);
        $blocks = (new WordPressBlockWriter())->write($document);

        $t->contains('# Imported DOCX Heading', $markdown);
        $t->contains('[source link](https://example.test/source?post=42)', $markdown);
        $t->contains('III) First review step', $markdown);
        $t->contains('![Review screenshot](word/media/review.png "Review image")', $markdown);
        $t->contains('<h1 id="imported-docx-heading">Imported DOCX Heading</h1>', $blocks);
        $t->contains('<a href="https://example.test/source?post=42">source link</a>', $blocks);
        $t->contains('<ol start="3" type="I">', $blocks);
        $t->contains('<img src="word/media/review.png" alt="Review screenshot" title="Review image"/>', $blocks);
        $t->contains('<td colspan="2"><p>Approved</p></td>', $blocks);
    },
    'rejects malformed or incomplete docx packages with bounded diagnostics' => static function (TestRunner $t): void {
        $reader = new DocxOpenXmlReader();

        $t->throws(RuntimeException::class, static fn (): AstNode => $reader->readPackage([]));
        $t->throws(RuntimeException::class, static fn (): AstNode => $reader->readPackage([
            'word/document.xml' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p>',
        ]));
    },
];

/**
 * @return array<string, string>
 */
function docx_openxml_reader_fixture_parts(): array
{
    return [
        '[Content_Types].xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML,
        '_rels/.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML,
        'docProps/core.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/">
  <dc:title>Imported DOCX Batch</dc:title>
  <dc:creator>Migration Editor</dc:creator>
  <dc:description>WordPress DOCX import fixture</dc:description>
  <cp:keywords>docx,wordpress,review</cp:keywords>
  <dcterms:created>2026-06-09T12:00:00Z</dcterms:created>
  <dcterms:modified>2026-06-09T12:30:00Z</dcterms:modified>
</cp:coreProperties>
XML,
        'word/styles.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:style w:type="paragraph" w:styleId="Heading1">
    <w:name w:val="Heading 1"/>
    <w:pPr><w:outlineLvl w:val="0"/></w:pPr>
  </w:style>
</w:styles>
XML,
        'word/numbering.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:numbering xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:abstractNum w:abstractNumId="10">
    <w:lvl w:ilvl="0"><w:start w:val="1"/><w:numFmt w:val="upperRoman"/><w:lvlText w:val="%1)"/></w:lvl>
  </w:abstractNum>
  <w:abstractNum w:abstractNumId="20">
    <w:lvl w:ilvl="0"><w:numFmt w:val="bullet"/><w:lvlText w:val="•"/></w:lvl>
  </w:abstractNum>
  <w:num w:numId="7">
    <w:abstractNumId w:val="10"/>
    <w:lvlOverride w:ilvl="0"><w:startOverride w:val="3"/></w:lvlOverride>
  </w:num>
  <w:num w:numId="8"><w:abstractNumId w:val="20"/></w:num>
</w:numbering>
XML,
        'word/_rels/document.xml.rels' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source?post=42" TargetMode="External"/>
  <Relationship Id="rImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML,
        'word/document.xml' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships" xmlns:wp="http://schemas.openxmlformats.org/drawingml/2006/wordprocessingDrawing" xmlns:a="http://schemas.openxmlformats.org/drawingml/2006/main" xmlns:pic="http://schemas.openxmlformats.org/drawingml/2006/picture">
  <w:body>
    <w:p><w:pPr><w:pStyle w:val="Heading1"/></w:pPr><w:r><w:t>Imported DOCX Heading</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Plain </w:t></w:r>
      <w:r><w:rPr><w:b/></w:rPr><w:t>bold</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:rPr><w:i/></w:rPr><w:t>italic</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:rPr><w:u w:val="single"/></w:rPr><w:t>underlined</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:r><w:rPr><w:strike/></w:rPr><w:t>removed</w:t></w:r>
      <w:r><w:t xml:space="preserve"> draft</w:t></w:r>
      <w:r><w:rPr><w:vertAlign w:val="superscript"/></w:rPr><w:t>2</w:t></w:r>
      <w:r><w:t xml:space="preserve"> </w:t></w:r>
      <w:hyperlink r:id="rLink"><w:r><w:t>source link</w:t></w:r></w:hyperlink>
    </w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>First review step</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="7"/></w:numPr></w:pPr><w:r><w:t>Second review step</w:t></w:r></w:p>
    <w:p><w:pPr><w:numPr><w:ilvl w:val="0"/><w:numId w:val="8"/></w:numPr></w:pPr><w:r><w:t>Bullet media note</w:t></w:r></w:p>
    <w:p>
      <w:r><w:t xml:space="preserve">Inline media </w:t></w:r>
      <w:r>
        <w:drawing>
          <wp:inline>
            <wp:docPr id="1" name="Review image" title="Review image" descr="Review screenshot"/>
            <a:graphic><a:graphicData><pic:pic><pic:blipFill><a:blip r:embed="rImage"/></pic:blipFill></pic:pic></a:graphicData></a:graphic>
          </wp:inline>
        </w:drawing>
      </w:r>
    </w:p>
    <w:tbl>
      <w:tr><w:tc><w:p><w:r><w:t>Reviewer</w:t></w:r></w:p></w:tc><w:tc><w:tcPr><w:gridSpan w:val="2"/></w:tcPr><w:p><w:r><w:t>Approved</w:t></w:r></w:p></w:tc></w:tr>
    </w:tbl>
  </w:body>
</w:document>
XML,
        'word/media/review.png' => 'fake png bytes',
    ];
}

/**
 * @param array<string, string> $parts
 * @return list<array{name:string, data:string}>
 */
function docx_openxml_reader_zip_parts(array $parts): array
{
    $zipParts = [];
    foreach ($parts as $name => $contents) {
        $zipParts[] = [
            'name' => $name,
            'data' => $contents,
        ];
    }

    return $zipParts;
}

/**
 * @param array<string, string> $parts
 */
function docx_openxml_reader_zip_package(array $parts): ZipPackage
{
    return ZipPackage::fromParts(docx_openxml_reader_zip_parts($parts));
}

/**
 * @param array<string, string> $parts
 */
function docx_openxml_reader_temp_docx(array $parts): string
{
    $path = tempnam(sys_get_temp_dir(), 'pandoc-docx-');
    if ($path === false) {
        throw new RuntimeException('Unable to allocate temporary DOCX path');
    }

    $written = file_put_contents($path, ZipPackage::build(docx_openxml_reader_zip_parts($parts)));
    if ($written === false) {
        throw new RuntimeException('Unable to write temporary DOCX package');
    }

    return $path;
}
