<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcPackagePath;
use PortLibs\Pandoc\OpcRelationship;
use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="Jpeg" ContentType="image/jpeg"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

$packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdExternalAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source packet.html?post=42#review" TargetMode="External"/>
</Relationships>
XML;

$documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="./footnotes.xml#notes"/>
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-image.PNG"/>
  <Relationship Id="rIdCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml"/>
  <Relationship Id="rIdReviewerLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
</Relationships>
XML;

$footnotesRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdNoteImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/footnote-source.png"/>
  <Relationship Id="rIdNoteSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source-footnote" TargetMode="External"/>
</Relationships>
XML;

return [
    'parses OPC content types defaults overrides and fallback lookup' => static function (TestRunner $t) use ($contentTypesXml): void {
        $types = OpcContentTypes::fromXml($contentTypesXml);

        $t->same([
            'rels' => 'application/vnd.openxmlformats-package.relationships+xml',
            'xml' => 'application/xml',
            'png' => 'image/png',
            'Jpeg' => 'image/jpeg',
        ], $types->defaults());
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $types->contentTypeForPart('/word/document.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $types->contentTypeForPart('word/styles.xml'));
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $types->contentTypeForPart('/docProps/core.xml?ignored=yes#frag'));
        $t->same('image/png', $types->contentTypeForPart('/word/media/review-image.PNG'));
        $t->same('image/jpeg', $types->contentTypeForPart('/word/media/source.JPEG'));
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $types->contentTypeForPart('/word/_rels/document.xml.rels'));
        $t->same(null, $types->contentTypeForPart('/word/media/no-extension'));
    },
    'serializes OPC content types with namespace and round trip lookup' => static function (TestRunner $t): void {
        $types = new OpcContentTypes();
        $types->addDefault('.xml', 'application/xml');
        $types->addDefault('png', 'image/png');
        $types->addOverride('word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml');
        $types->addOverride('/docProps/core.xml', 'application/vnd.openxmlformats-package.core-properties+xml');

        $xml = $types->toXml();
        $t->contains('xmlns="' . OpcContentTypes::NAMESPACE_URI . '"', $xml);
        $t->contains('Extension="xml"', $xml);
        $t->contains('PartName="/word/document.xml"', $xml);

        $roundTrip = OpcContentTypes::fromXml($xml);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $roundTrip->contentTypeForPart('/word/document.xml'));
        $t->same('image/png', $roundTrip->contentTypeForPart('/word/media/review.png'));
        $t->same([
            '/word/document.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            '/docProps/core.xml' => 'application/vnd.openxmlformats-package.core-properties+xml',
        ], $roundTrip->overrides());
    },
    'rejects malformed OPC content types XML and unsafe part names' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="urn:bad"/>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml"/></Types>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml" ContentType="application/xml"/><Default Extension="XML" ContentType="text/xml"/></Types>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/document.xml" ContentType="application/xml"/><Override PartName="word/document.xml" ContentType="application/xml"/></Types>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="../evil.xml" ContentType="application/xml"/></Types>'));
    },
    'maps OPC source parts and relationship part names' => static function (TestRunner $t): void {
        $t->same('/_rels/.rels', OpcRelationships::relationshipPartNameForSource('/'));
        $t->same('/_rels/.rels', OpcRelationships::relationshipPartNameForSource('/.'));
        $t->same('/_rels/document.xml.rels', OpcRelationships::relationshipPartNameForSource('/document.xml'));
        $t->same('/word/_rels/document.xml.rels', OpcRelationships::relationshipPartNameForSource('/word/document.xml'));
        $t->same('/word/embeddings/_rels/oleObject1.bin.rels', OpcRelationships::relationshipPartNameForSource('word/embeddings/oleObject1.bin'));
        $t->same('/', OpcRelationships::sourcePartNameForRelationshipPart('/_rels/.rels'));
        $t->same('/document.xml', OpcRelationships::sourcePartNameForRelationshipPart('/_rels/document.xml.rels'));
        $t->same('/word/document.xml', OpcRelationships::sourcePartNameForRelationshipPart('/word/_rels/document.xml.rels'));
        $t->same('/word/embeddings/oleObject1.bin', OpcRelationships::sourcePartNameForRelationshipPart('/word/embeddings/_rels/oleObject1.bin.rels'));
        $t->true(OpcRelationships::isRelationshipPartName('/_rels/.rels'));
        $t->true(OpcRelationships::isRelationshipPartName('/word/_rels/document.xml.rels'));
        $t->same(false, OpcRelationships::isRelationshipPartName('/word/document.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcRelationships::sourcePartNameForRelationshipPart('/word/document.xml.rels'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcRelationships::relationshipPartNameForSource('/word/_rels/document.xml.rels'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => new OpcRelationships('/word/_rels/document.xml.rels'));
    },
    'parses package level OPC relationships and resolves package root targets' => static function (TestRunner $t) use ($packageRelationshipsXml): void {
        $relationships = OpcRelationships::fromXml($packageRelationshipsXml);

        $t->same('/_rels/.rels', $relationships->relationshipPartName());
        $t->same(3, count($relationships->all()));
        $t->same('rIdDocument', $relationships->all()[0]->id);
        $t->same('word/document.xml', $relationships->byId('rIdDocument')?->target);
        $t->same('/word/document.xml', $relationships->resolveTarget('rIdDocument'));
        $t->same('/docProps/core.xml', $relationships->resolveTarget('rIdCore'));
        $t->same('https://example.test/source packet.html?post=42#review', $relationships->resolveTarget('rIdExternalAudit'));
        $t->true($relationships->byId('rIdExternalAudit')?->isExternal() ?? false);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument', $relationships->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument')?->type);
        $t->same(1, count($relationships->ofType('http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties')));
    },
    'parses part level OPC relationships and normalizes relative targets' => static function (TestRunner $t) use ($documentRelationshipsXml): void {
        $relationships = OpcRelationships::fromXml($documentRelationshipsXml, '/word/document.xml');

        $t->same('/word/_rels/document.xml.rels', $relationships->relationshipPartName());
        $t->same(5, count($relationships->all()));
        $t->same('/word/styles.xml', $relationships->resolveTarget('rIdStyles'));
        $t->same('/word/footnotes.xml#notes', $relationships->resolveTarget('rIdFootnotes'));
        $t->same('/word/media/review-image.PNG', $relationships->resolveTarget('rIdImage'));
        $t->same('/customXml/item1.xml', $relationships->resolveTarget('rIdCustomXml'));
        $t->same('https://example.test/wp-admin/post.php?post=42&action=edit', $relationships->resolveTarget('rIdReviewerLink'));
        $t->same('External', $relationships->byId('rIdReviewerLink')?->targetMode);
        $t->same('Internal', $relationships->byId('rIdStyles')?->targetMode);
        $t->same(null, $relationships->byId('missing'));
    },
    'resolves percent encoded OPC relationship target paths to package parts' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review%20image.PNG"/>
  <Relationship Id="rIdUtf8Image" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/%C3%A9preuve.png#crop"/>
  <Relationship Id="rIdExternalAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%20packet.html?post=42#review" TargetMode="External"/>
</Relationships>
XML;
        $utf8Name = "\u{00E9}" . 'preuve.png';
        $relationships = OpcRelationships::fromXml($documentRelationshipsXml, '/word/document.xml');

        $t->same('/word/media/review image.PNG', $relationships->resolveTarget('rIdReviewImage'));
        $t->same('/word/media/' . $utf8Name . '#crop', $relationships->resolveTarget('rIdUtf8Image'));
        $t->same('https://example.test/source%20packet.html?post=42#review', $relationships->resolveTarget('rIdExternalAudit'));

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/review image.PNG', 'data' => 'PNG'],
            ['name' => 'word/media/' . $utf8Name, 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->same('/word/media/review image.PNG', $preflight['rIdReviewImage']['target']);
        $t->same(true, $preflight['rIdReviewImage']['exists']);
        $t->same('image/png', $preflight['rIdReviewImage']['contentType']);
        $t->same('/word/media/' . $utf8Name . '#crop', $preflight['rIdUtf8Image']['target']);
        $t->same('/word/media/' . $utf8Name, OpcPackagePath::stripQueryAndFragment($preflight['rIdUtf8Image']['target']));
        $t->same(true, $preflight['rIdUtf8Image']['valid']);
        $t->same(true, $preflight['rIdExternalAudit']['external']);
        $t->same(null, $preflight['rIdExternalAudit']['exists']);
    },
    'loads package and part level OPC relationship parts from zip package entries' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml, $documentRelationshipsXml): void {
        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]);

        $t->true(OpcRelationships::packageHasRelationshipsForSource($package));
        $t->true(OpcRelationships::packageHasRelationshipsForSource($package, '/word/document.xml'));
        $t->same(false, OpcRelationships::packageHasRelationshipsForSource($package, '/word/missing.xml'));

        $packageRelationships = OpcRelationships::fromPackage($package);
        $documentPart = $packageRelationships->resolveTarget('rIdDocument');
        $documentRelationships = OpcRelationships::fromPackage($package, $documentPart);

        $t->same('/word/document.xml', $documentPart);
        $t->same('/word/_rels/document.xml.rels', $documentRelationships->relationshipPartName());
        $t->same('/word/styles.xml', $documentRelationships->resolveTarget('rIdStyles'));
        $t->same('/word/media/review-image.PNG', $documentRelationships->resolveTarget('rIdImage'));
        $t->same('https://example.test/wp-admin/post.php?post=42&action=edit', $documentRelationships->resolveTarget('rIdReviewerLink'));

        $types = OpcContentTypes::fromXml($package->read('[Content_Types].xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $types->contentTypeForPart($documentPart));
        $t->same('image/png', $types->contentTypeForPart($documentRelationships->resolveTarget('rIdImage')));
        $t->throws(\RuntimeException::class, static fn (): OpcRelationships => OpcRelationships::fromPackage($package, '/word/missing.xml'));
    },
    'loads a ZIP backed OPC relationship graph by source part' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml, $documentRelationshipsXml, $footnotesRelationshipsXml): void {
        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $footnotesRelationshipsXml],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'word/media/footnote-source.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]);

        $graph = OpcRelationshipGraph::fromPackage($package);

        $t->same(['/', '/word/document.xml', '/word/footnotes.xml'], $graph->sourcePartNames());
        $t->true($graph->hasRelationshipsForSource('/'));
        $t->true($graph->hasRelationshipsForSource('/word/document.xml'));
        $t->same(false, $graph->hasRelationshipsForSource('/word/styles.xml'));
        $t->same('/word/document.xml', $graph->firstTargetOfType(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE));
        $t->same('/word/footnotes.xml#notes', $graph->firstTargetOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes', '/word/document.xml'));
        $t->same(null, $graph->firstTargetOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/header'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $graph->contentTypes()->contentTypeForPart('/word/document.xml'));
        $t->same(5, count($graph->requireRelationshipsForSource('/word/document.xml')->all()));
        $t->same('/word/media/footnote-source.png', $graph->requireRelationshipsForSource('/word/footnotes.xml')->resolveTarget('rIdNoteImage'));
        $t->throws(\RuntimeException::class, static fn (): OpcRelationships => $graph->requireRelationshipsForSource('/word/missing.xml'));
    },
    'summarizes graph targets with content types for DOCX import preflight' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml, $documentRelationshipsXml, $footnotesRelationshipsXml): void {
        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $footnotesRelationshipsXml],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'word/media/footnote-source.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $documentSummary = [];
        foreach ($graph->summarizeTargetsForSource('/word/document.xml') as $target) {
            $documentSummary[$target['id']] = $target;
        }

        $imageSummary = $graph->summarizeTargetsForSource('/word/document.xml', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
        $footnoteSummary = [];
        foreach ($graph->summarizeTargetsForSource('/word/footnotes.xml') as $target) {
            $footnoteSummary[$target['id']] = $target;
        }

        $t->same('/word/styles.xml', $documentSummary['rIdStyles']['target']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $documentSummary['rIdStyles']['contentType']);
        $t->same('/word/footnotes.xml#notes', $documentSummary['rIdFootnotes']['target']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml', $documentSummary['rIdFootnotes']['contentType']);
        $t->same('/customXml/item1.xml', $documentSummary['rIdCustomXml']['target']);
        $t->same('application/xml', $documentSummary['rIdCustomXml']['contentType']);
        $t->same(true, $documentSummary['rIdReviewerLink']['external']);
        $t->same(null, $documentSummary['rIdReviewerLink']['contentType']);
        $t->same(1, count($imageSummary));
        $t->same('/word/media/review-image.PNG', $imageSummary[0]['target']);
        $t->same('image/png', $imageSummary[0]['contentType']);
        $t->same('/word/media/footnote-source.png', $footnoteSummary['rIdNoteImage']['target']);
        $t->same('image/png', $footnoteSummary['rIdNoteImage']['contentType']);
        $t->same('https://example.test/source-footnote', $footnoteSummary['rIdNoteSource']['target']);
        $t->same([], $graph->summarizeTargetsForSource('/word/missing.xml'));
    },
    'preflights OPC graph targets for package integrity issues' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdEmbeddedOle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject" Target="embeddings/oleObject1.bin"/>
  <Relationship Id="rIdRelsTarget" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="_rels/document.xml.rels"/>
  <Relationship Id="rIdExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
  <Relationship Id="rIdEscape" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../../evil.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/embeddings/oleObject1.bin', 'data' => 'OLE'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->same(6, count($preflight));
        $t->same('/word/styles.xml', $preflight['rIdStyles']['target']);
        $t->same(true, $preflight['rIdStyles']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $preflight['rIdStyles']['contentType']);
        $t->same(true, $preflight['rIdStyles']['valid']);
        $t->same([], $preflight['rIdStyles']['issues']);

        $t->same('/word/media/missing.png', $preflight['rIdMissingImage']['target']);
        $t->same(false, $preflight['rIdMissingImage']['exists']);
        $t->same('image/png', $preflight['rIdMissingImage']['contentType']);
        $t->same(false, $preflight['rIdMissingImage']['valid']);
        $t->same(['missing-in-package'], $preflight['rIdMissingImage']['issues']);

        $t->same('/word/embeddings/oleObject1.bin', $preflight['rIdEmbeddedOle']['target']);
        $t->same(true, $preflight['rIdEmbeddedOle']['exists']);
        $t->same(null, $preflight['rIdEmbeddedOle']['contentType']);
        $t->same(['missing-content-type'], $preflight['rIdEmbeddedOle']['issues']);

        $t->same('/word/_rels/document.xml.rels', $preflight['rIdRelsTarget']['target']);
        $t->same(true, $preflight['rIdRelsTarget']['exists']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $preflight['rIdRelsTarget']['contentType']);
        $t->same(true, $preflight['rIdRelsTarget']['relationshipPartTarget']);
        $t->same(['targets-relationship-part'], $preflight['rIdRelsTarget']['issues']);

        $t->same('https://example.test/review', $preflight['rIdExternal']['target']);
        $t->same(true, $preflight['rIdExternal']['external']);
        $t->same(null, $preflight['rIdExternal']['exists']);
        $t->same([], $preflight['rIdExternal']['issues']);

        $t->same('../../evil.xml', $preflight['rIdEscape']['target']);
        $t->same(false, $preflight['rIdEscape']['valid']);
        $t->same(['invalid-target'], $preflight['rIdEscape']['issues']);

        $imagePreflight = $graph->preflightTargetsForSource('/word/document.xml', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
        $t->same(['rIdMissingImage', 'rIdEscape'], array_column($imagePreflight, 'id'));
        $t->same([], $graph->preflightTargetsForSource('/word/missing.xml'));
    },
    'preflights OPC package parts for content type and orphan relationship issues' => static function (TestRunner $t) use ($packageRelationshipsXml, $documentRelationshipsXml): void {
        $badContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $missingPartRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdOrphanImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/orphan.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $badContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'word/embeddings/oleObject1.bin', 'data' => 'OLE'],
            ['name' => 'word/_rels/missing.xml.rels', 'data' => $missingPartRelationshipsXml],
        ]));

        $parts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $parts[$part['partName']] = $part;
        }

        $t->same([
            '/_rels/.rels',
            '/word/document.xml',
            '/word/_rels/document.xml.rels',
            '/word/media/review-image.PNG',
            '/word/embeddings/oleObject1.bin',
            '/word/_rels/missing.xml.rels',
        ], array_keys($parts));

        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $parts['/word/document.xml']['contentType']);
        $t->same(false, $parts['/word/document.xml']['relationshipPart']);
        $t->same(true, $parts['/word/document.xml']['valid']);
        $t->same('image/png', $parts['/word/media/review-image.PNG']['contentType']);
        $t->same([], $parts['/word/media/review-image.PNG']['issues']);

        $t->same(null, $parts['/word/embeddings/oleObject1.bin']['contentType']);
        $t->same(['missing-content-type'], $parts['/word/embeddings/oleObject1.bin']['issues']);
        $t->same(false, $parts['/word/embeddings/oleObject1.bin']['valid']);

        $t->same(true, $parts['/_rels/.rels']['relationshipPart']);
        $t->same('/', $parts['/_rels/.rels']['relationshipSource']);
        $t->same(true, $parts['/_rels/.rels']['sourceExists']);
        $t->same(['invalid-relationship-content-type'], $parts['/_rels/.rels']['issues']);

        $t->same('/word/document.xml', $parts['/word/_rels/document.xml.rels']['relationshipSource']);
        $t->same(true, $parts['/word/_rels/document.xml.rels']['sourceExists']);
        $t->same(['invalid-relationship-content-type'], $parts['/word/_rels/document.xml.rels']['issues']);

        $t->same('/word/missing.xml', $parts['/word/_rels/missing.xml.rels']['relationshipSource']);
        $t->same(false, $parts['/word/_rels/missing.xml.rels']['sourceExists']);
        $t->same(['invalid-relationship-content-type', 'orphan-relationship-part'], $parts['/word/_rels/missing.xml.rels']['issues']);
        $t->same(false, $parts['/word/_rels/missing.xml.rels']['valid']);
    },
    'flags nested OPC relationship parts without loading them as sources' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-image.PNG"/>
</Relationships>
XML;

        $nestedRelationshipXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdNeverLoaded" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/hidden.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/_rels/_rels/document.xml.rels.rels', 'data' => $nestedRelationshipXml],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'word/media/hidden.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $t->same(['/', '/word/document.xml'], $graph->sourcePartNames());
        $t->same(false, $graph->hasRelationshipsForSource('/word/_rels/document.xml.rels'));
        $t->same(null, $graph->relationshipsForSource('/word/_rels/document.xml.rels'));
        $t->same([], $graph->preflightTargetsForSource('/word/_rels/document.xml.rels'));

        $parts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $parts[$part['partName']] = $part;
        }

        $nested = $parts['/word/_rels/_rels/document.xml.rels.rels'];
        $t->same(true, $nested['relationshipPart']);
        $t->same('/word/_rels/document.xml.rels', $nested['relationshipSource']);
        $t->same(true, $nested['relationshipSourceIsRelationshipPart']);
        $t->same(true, $nested['sourceExists']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $nested['contentType']);
        $t->same(['relationship-part-source'], $nested['issues']);
        $t->same(false, $nested['valid']);

        $documentTargets = $graph->preflightTargetsForSource('/word/document.xml');
        $t->same(['rIdImage'], array_column($documentTargets, 'id'));
        $t->same('/word/media/review-image.PNG', $documentTargets[0]['target']);
    },
    'walks reachable OPC relationship closure from office document root' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml, $documentRelationshipsXml, $footnotesRelationshipsXml): void {
        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $footnotesRelationshipsXml],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'word/media/footnote-source.png', 'data' => 'PNG'],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same([
            'rIdDocument',
            'rIdStyles',
            'rIdFootnotes',
            'rIdImage',
            'rIdCustomXml',
            'rIdReviewerLink',
            'rIdNoteImage',
            'rIdNoteSource',
        ], array_keys($closureById));
        $t->same('/', $closureById['rIdDocument']['source']);
        $t->same(0, $closureById['rIdDocument']['depth']);
        $t->same('/word/document.xml', $closureById['rIdDocument']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $closureById['rIdDocument']['contentType']);
        $t->same('/word/document.xml', $closureById['rIdStyles']['source']);
        $t->same(1, $closureById['rIdStyles']['depth']);
        $t->same('/word/footnotes.xml#notes', $closureById['rIdFootnotes']['target']);
        $t->same('/word/footnotes.xml', $closureById['rIdFootnotes']['targetPart']);
        $t->same('/word/footnotes.xml', $closureById['rIdNoteImage']['source']);
        $t->same(2, $closureById['rIdNoteImage']['depth']);
        $t->same('/word/media/footnote-source.png', $closureById['rIdNoteImage']['targetPart']);
        $t->same(null, $closureById['rIdReviewerLink']['targetPart']);
        $t->same(true, $closureById['rIdReviewerLink']['external']);
        $t->same(array_fill(0, 8, true), array_column($closureById, 'valid'));
    },
    'does not traverse missing invalid or relationship part closure targets' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMissingFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="missing-footnotes.xml"/>
  <Relationship Id="rIdRelsTarget" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="_rels/document.xml.rels"/>
  <Relationship Id="rIdEscape" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../../evil.xml"/>
</Relationships>
XML;

        $missingFootnotesRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdNeverTraversed" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/never.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/_rels/missing-footnotes.xml.rels', 'data' => $missingFootnotesRelationshipsXml],
            ['name' => 'word/media/never.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(['rIdDocument', 'rIdMissingFootnotes', 'rIdRelsTarget', 'rIdEscape'], array_keys($closureById));
        $t->same('/word/missing-footnotes.xml', $closureById['rIdMissingFootnotes']['targetPart']);
        $t->same(false, $closureById['rIdMissingFootnotes']['exists']);
        $t->same(['missing-in-package'], $closureById['rIdMissingFootnotes']['issues']);
        $t->same('/word/_rels/document.xml.rels', $closureById['rIdRelsTarget']['targetPart']);
        $t->same(true, $closureById['rIdRelsTarget']['relationshipPartTarget']);
        $t->same(['targets-relationship-part'], $closureById['rIdRelsTarget']['issues']);
        $t->same(null, $closureById['rIdEscape']['targetPart']);
        $t->same(['invalid-target'], $closureById['rIdEscape']['issues']);
        $t->same(false, isset($closureById['rIdNeverTraversed']));
    },
    'guards cyclic OPC relationship closure traversal' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdBackToDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="document.xml#cycle"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(['rIdDocument', 'rIdComments', 'rIdBackToDocument'], array_keys($closureById));
        $t->same('/word/comments.xml', $closureById['rIdComments']['targetPart']);
        $t->same('/word/comments.xml', $closureById['rIdBackToDocument']['source']);
        $t->same(2, $closureById['rIdBackToDocument']['depth']);
        $t->same('/word/document.xml#cycle', $closureById['rIdBackToDocument']['target']);
        $t->same('/word/document.xml', $closureById['rIdBackToDocument']['targetPart']);
    },
    'rejects malformed OPC relationship graph package inputs' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $t->throws(\RuntimeException::class, static fn (): OpcRelationshipGraph => OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
        ])));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationshipGraph => OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => 'word/_rels/.rels', 'data' => $packageRelationshipsXml],
        ])));
        $t->throws(\InvalidArgumentException::class, static function () use ($contentTypesXml): void {
            $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
                ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
                ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdBad" Type="t" Target="../evil.xml"/></Relationships>'],
            ]));
            $graph->summarizeTargetsForSource('/');
        });
    },
    'serializes OPC relationships with external target modes only when needed' => static function (TestRunner $t): void {
        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship('rIdStyles', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles', 'styles.xml'));
        $relationships->add(new OpcRelationship('rIdSource', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink', 'https://example.test/source', OpcRelationship::TARGET_MODE_EXTERNAL));

        $xml = $relationships->toXml();
        $t->contains('xmlns="' . OpcRelationships::NAMESPACE_URI . '"', $xml);
        $t->contains('Id="rIdStyles"', $xml);
        $t->contains('Target="styles.xml"', $xml);
        $t->contains('TargetMode="External"', $xml);
        $t->same(false, str_contains($xml, 'TargetMode="Internal"'));

        $roundTrip = OpcRelationships::fromXml($xml, '/word/document.xml');
        $t->same('/word/styles.xml', $roundTrip->resolveTarget('rIdStyles'));
        $t->same('https://example.test/source', $roundTrip->resolveTarget('rIdSource'));
        $t->true($roundTrip->byId('rIdSource')?->isExternal() ?? false);
    },
    'rejects malformed OPC relationships and unsafe internal targets' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml('<Relationships xmlns="urn:bad"/>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml('<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rId1" Type="t" Target="a.xml"/><Relationship Id="rId1" Type="t" Target="b.xml"/></Relationships>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml('<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rId1" Type="t"/></Relationships>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml('<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rId1" Type="t" Target="a.xml" TargetMode="external"/></Relationships>'));

        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship('rIdEscape', 't', '../../evil.xml'));
        $relationships->add(new OpcRelationship('rIdAbsoluteUri', 't', 'https://example.test/evil.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEscape'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdAbsoluteUri'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('missing'));
    },
    'rejects malformed percent escapes and URI authorities in internal OPC relationship targets' => static function (TestRunner $t): void {
        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship('rIdBadEscape', 't', 'media/bad%ZZ.png'));
        $relationships->add(new OpcRelationship('rIdAuthority', 't', '//example.test/media.png'));
        $relationships->add(new OpcRelationship('rIdEncodedSlash', 't', 'media%2Fhidden.png'));
        $relationships->add(new OpcRelationship('rIdEncodedBackslash', 't', 'media%5Chidden.png'));
        $relationships->add(new OpcRelationship('rIdEncodedNul', 't', 'media%00hidden.png'));

        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdBadEscape'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdAuthority'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedSlash'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedBackslash'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedNul'));
    },
    'rejects OPC relationship Id values outside XML NCName shape' => static function (TestRunner $t): void {
        $xml = static fn (string $id): string => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="' . $id . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>';

        $relationships = OpcRelationships::fromXml($xml('rId_image-1.2'), '/word/document.xml');
        $t->same('/word/media/image.png', $relationships->resolveTarget('rId_image-1.2'));

        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($xml('1rId')));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($xml('rId one')));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($xml('r:id')));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationship => new OpcRelationship('-rId', 't', 'media/image.png'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationship => new OpcRelationship('rId/one', 't', 'media/image.png'));
    },
    'preflights a DOCX package relationship graph for WordPress import' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml, $documentRelationshipsXml): void {
        $types = OpcContentTypes::fromXml($contentTypesXml);
        $packageRelationships = OpcRelationships::fromXml($packageRelationshipsXml);
        $officeDocument = $packageRelationships->firstOfType('http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument');

        $t->true($officeDocument instanceof OpcRelationship);
        $documentPart = $packageRelationships->resolveTarget($officeDocument);
        $documentRelationships = OpcRelationships::fromXml($documentRelationshipsXml, $documentPart);

        $resolved = [];
        foreach ($documentRelationships->all() as $relationship) {
            $target = $documentRelationships->resolveTarget($relationship);
            $resolved[$relationship->id] = [
                'target' => $target,
                'contentType' => $relationship->isExternal() ? null : $types->contentTypeForPart($target),
                'external' => $relationship->isExternal(),
            ];
        }

        $t->same('/word/document.xml', $documentPart);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $types->contentTypeForPart($documentPart));
        $t->same('/word/styles.xml', $resolved['rIdStyles']['target']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $resolved['rIdStyles']['contentType']);
        $t->same('/word/footnotes.xml#notes', $resolved['rIdFootnotes']['target']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml', $resolved['rIdFootnotes']['contentType']);
        $t->same('/word/media/review-image.PNG', $resolved['rIdImage']['target']);
        $t->same('image/png', $resolved['rIdImage']['contentType']);
        $t->same('/customXml/item1.xml', $resolved['rIdCustomXml']['target']);
        $t->same('application/xml', $resolved['rIdCustomXml']['contentType']);
        $t->same('https://example.test/wp-admin/post.php?post=42&action=edit', $resolved['rIdReviewerLink']['target']);
        $t->same(true, $resolved['rIdReviewerLink']['external']);
        $t->same(null, $resolved['rIdReviewerLink']['contentType']);
        $t->same('/docProps/core.xml', $packageRelationships->resolveTarget('rIdCore'));
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $types->contentTypeForPart($packageRelationships->resolveTarget('rIdCore')));
    },
    'normalizes OPC package paths without allowing traversal or URI components' => static function (TestRunner $t): void {
        $t->same('/word/document.xml', OpcPackagePath::canonicalPartName('word/./document.xml'));
        $t->same('/word/styles.xml#section', OpcPackagePath::resolveInternalTarget('/word/document.xml', './styles.xml#section'));
        $t->same('/media/image.png?variant=review', OpcPackagePath::resolveInternalTarget('/word/document.xml', '../media/image.png?variant=review'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/word/document.xml#frag'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', ''));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', '../../evil.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', 'file:///tmp/evil.xml'));
    },
];
