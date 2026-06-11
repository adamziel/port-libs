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
