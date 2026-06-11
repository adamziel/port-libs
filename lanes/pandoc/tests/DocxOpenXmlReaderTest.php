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
    'reports docx content type declaration collisions without aborting package ingestion' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Default Extension="xml" ContentType="application/xml"/>',
            '  <Default Extension="XML" ContentType="application/vnd.review+xml"/>' . "\n" .
            '  <Default Extension="xml" ContentType="application/xml"/>',
            $parts['[Content_Types].xml']
        );
        $parts['[Content_Types].xml'] = str_replace(
            '  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>',
            '  <Override PartName="/WORD/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>' . "\n" .
            '  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>',
            $parts['[Content_Types].xml']
        );

        $document = (new DocxOpenXmlReader())->readPackage($parts);
        $docx = $document->attr('docx');
        $contentTypesPart = $docx['packageProvenance']['contentTypesPart'];
        $preflight = $contentTypesPart['preflight'];

        $t->same('Imported DOCX Batch', $document->attr('meta')['title']);
        $t->same('word/document.xml', $docx['documentPart']);
        $t->same(false, $contentTypesPart['valid']);
        $t->same(false, $preflight['valid']);
        $t->same(7, $contentTypesPart['recordCount']);
        $t->same(4, $preflight['defaultCount']);
        $t->same(3, $preflight['overrideCount']);
        $t->same(4, $contentTypesPart['invalidRecordCount']);
        $t->same(1, $contentTypesPart['duplicateDefaultExtensionCount']);
        $t->same(1, $contentTypesPart['duplicateOverridePartNameCount']);
        $t->same(['xml'], $contentTypesPart['duplicateDefaultExtensions']);
        $t->same(['/word/document.xml'], $contentTypesPart['duplicateOverridePartNames']);
        $t->same(['XML', 'xml'], $preflight['duplicateDefaultExtensionGroups']['xml']);
        $t->same(['/WORD/document.xml', '/word/document.xml'], $preflight['duplicateOverridePartNameGroups']['/word/document.xml']);
        $t->same(2, $contentTypesPart['issueCounts']['duplicate-default-extension']);
        $t->same(2, $contentTypesPart['issueCounts']['duplicate-override-part-name']);
        $t->same('application/xml', $contentTypesPart['defaults']['xml']['contentType']);
        $t->same(true, $contentTypesPart['overrides']['word/document.xml']['exists']);
        $t->same('duplicate-default-extension', $preflight['records'][1]['issues'][0]);
        $t->same('duplicate-override-part-name', $preflight['records'][4]['issues'][0]);
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
    'summarizes docx relationships by type for package review' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '  <Default Extension="png" ContentType="image/png"/>',
            '  <Default Extension="png" ContentType="image/png"/>' . "\n" .
            '  <Default Extension="mp3" ContentType="audio/mpeg"/>',
            $parts['[Content_Types].xml']
        );
        $parts['_rels/.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rPreview" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="https://example.test/review.png" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['_rels/.rels']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>' . "\n" .
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
        $t->same('image/png', $image['relationships'][1]['contentType']);

        $t->same('audio', $audio['label']);
        $t->same(1, $audio['missingTargetCount']);
        $t->same(['word/media/narration.mp3'], $audio['missingTargetParts']);
        $t->same(['audio/mpeg'], $audio['contentTypes']);
        $t->same('rNarration', $audio['relationships'][0]['id']);

        $t->same(1, $hyperlink['externalCount']);
        $t->same(['https://example.test/source?post=42'], $hyperlink['externalTargets']);
        $t->same(null, $hyperlink['relationships'][0]['targetPart']);

        $t->same('thumbnail', $thumbnail['label']);
        $t->same(1, $thumbnail['externalCount']);
        $t->same(['https://example.test/review.png'], $thumbnail['externalTargets']);
        $t->same(['_rels/.rels'], $thumbnail['relationshipParts']);
    },
    'summarizes docx package relationship targets for review handoff' => static function (TestRunner $t): void {
        $parts = docx_openxml_reader_fixture_parts();
        $parts['[Content_Types].xml'] = str_replace(
            '</Types>',
            '  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>' . "\n" .
            '</Types>',
            $parts['[Content_Types].xml']
        );
        $parts['word/_rels/document.xml.rels'] = str_replace(
            '</Relationships>',
            '  <Relationship Id="rMissingComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml?thread=review#c1"/>' . "\n" .
            '  <Relationship Id="rRemoteTemplate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/attachedTemplate" Target="https://example.test/templates/review.dotx" TargetMode="External"/>' . "\n" .
            '</Relationships>',
            $parts['word/_rels/document.xml.rels']
        );
        $parts['word/header1.xml'] = '<w:hdr xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:p><w:r><w:t>Header</w:t></w:r></w:p></w:hdr>';
        $parts['word/_rels/header1.xml.rels'] = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMissingHeaderImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing-header.png"/>
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
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', $summary['missingRelationshipTargets'][0]['contentType']);
        $t->same('override', $summary['missingRelationshipTargets'][0]['contentTypeSource']);
        $t->same('rMissingHeaderImage', $summary['missingRelationshipTargets'][1]['id']);
        $t->same('word/media/missing-header.png', $summary['missingRelationshipTargets'][1]['targetPart']);
        $t->same('image/png', $summary['missingRelationshipTargets'][1]['contentType']);
        $t->same('default', $summary['missingRelationshipTargets'][1]['contentTypeSource']);
        $t->same('rLink', $summary['externalRelationshipTargets'][0]['id']);
        $t->same('rRemoteTemplate', $summary['externalRelationshipTargets'][1]['id']);
        $t->same(null, $summary['externalRelationshipTargets'][1]['targetPart']);
        $t->same('https://example.test/templates/review.dotx', $summary['externalRelationshipTargets'][1]['resolvedTarget']);
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
        $t->same('word/comments/review-comments.xml', $commentRelationshipsPart['sourcePart']);
        $t->same(1, $commentRelationshipsPart['relationshipCount']);
        $t->same(true, $commentRelationshipsPart['relationships']['rCommentSource']['external']);
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
