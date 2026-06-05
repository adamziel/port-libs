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
    'normalizes percent encoded OPC content type override part names' => static function (TestRunner $t): void {
        $encodedContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/media/source%20diagram.svg" ContentType="image/svg+xml; role=reviewer"/>
  <Override PartName="/customXml/%C3%A9preuve.xml" ContentType="application/xml; role=reviewer"/>
</Types>
XML;

        $types = OpcContentTypes::fromXml($encodedContentTypesXml);
        $utf8Name = "\u{00E9}" . 'preuve.xml';

        $t->same('image/svg+xml; role=reviewer', $types->contentTypeForPart('/word/media/source diagram.svg'));
        $t->same('image/svg+xml; role=reviewer', $types->contentTypeForPart('/word/media/source%20diagram.svg'));
        $t->same('application/xml; role=reviewer', $types->contentTypeForPart('/customXml/' . $utf8Name));
        $t->same([
            '/word/document.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            '/word/media/source diagram.svg' => 'image/svg+xml; role=reviewer',
            '/customXml/' . $utf8Name => 'application/xml; role=reviewer',
        ], $types->overrides());

        $xml = $types->toXml();
        $t->contains('PartName="/word/media/source%20diagram.svg"', $xml);
        $t->contains('PartName="/customXml/%C3%A9preuve.xml"', $xml);

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDiagram" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/source%20diagram.svg"/>
  <Relationship Id="rIdReviewXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/%C3%A9preuve.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $encodedContentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdDocument" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="word/document.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/source diagram.svg', 'data' => '<svg xmlns="http://www.w3.org/2000/svg"/>'],
            ['name' => 'customXml/' . $utf8Name, 'data' => '<audit/>'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->same('/word/media/source diagram.svg', $preflight['rIdDiagram']['target']);
        $t->same('image/svg+xml; role=reviewer', $preflight['rIdDiagram']['contentType']);
        $t->same(true, $preflight['rIdDiagram']['valid']);
        $t->same('/customXml/' . $utf8Name, $preflight['rIdReviewXml']['target']);
        $t->same('application/xml; role=reviewer', $preflight['rIdReviewXml']['contentType']);
        $t->same(true, $preflight['rIdReviewXml']['valid']);

        foreach ([
            '/word/media/source%2Fdiagram.svg',
            '/word/media/source%5Cdiagram.svg',
            '/word/media/source%00diagram.svg',
            '/word/media/source%ZZdiagram.svg',
            '/word/media/source%20diagram.svg?variant=review',
            '/word/media/source%20diagram.svg#review',
        ] as $partName) {
            $badXml = '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="' . $partName . '" ContentType="application/xml"/></Types>';
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($badXml));
        }
    },
    'validates OPC content type media type grammar including parameters' => static function (TestRunner $t): void {
        $types = new OpcContentTypes();
        $types->addDefault('xml', 'application/xml');
        $types->addDefault('svg', 'image/svg+xml; charset=UTF-8');
        $types->addOverride('/word/document.xml', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml');
        $types->addOverride('/word/media/review.html', 'text/html; charset="utf-8"; source=import-review');

        $t->same('application/xml', $types->contentTypeForPart('/word/styles.xml'));
        $t->same('image/svg+xml; charset=UTF-8', $types->contentTypeForPart('/word/media/diagram.svg'));
        $t->same('text/html; charset="utf-8"; source=import-review', $types->contentTypeForPart('/word/media/review.html'));
        $t->same([
            'xml' => 'application/xml',
            'svg' => 'image/svg+xml; charset=UTF-8',
        ], $types->defaults());

        foreach ([
            'application/',
            '/xml',
            'application//xml',
            'application xml/html',
            'application/xml bad',
            'application/xml;',
            'application/xml; charset',
            'application/xml; charset=',
            'application/xml; =utf-8',
            'application/xml; charset="unterminated',
        ] as $invalidContentType) {
            $t->throws(\InvalidArgumentException::class, static function () use ($invalidContentType): void {
                $types = new OpcContentTypes();
                $types->addDefault('bad', $invalidContentType);
            });
        }
    },
    'rejects malformed OPC content types XML and unsafe part names' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="urn:bad"/>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml"/></Types>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml" ContentType="application/xml"/><Default Extension="XML" ContentType="text/xml"/></Types>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/document.xml" ContentType="application/xml"/><Override PartName="word/document.xml" ContentType="application/xml"/></Types>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="../evil.xml" ContentType="application/xml"/></Types>'));
    },
    'rejects OPC content type records with unexpected attributes or child content' => static function (TestRunner $t): void {
        $validWithWhitespace = OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml" ContentType="application/xml">   </Default><Override PartName="/word/document.xml" ContentType="application/xml"/></Types>');
        $t->same('application/xml', $validWithWhitespace->contentTypeForPart('/word/document.xml'));

        foreach ([
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml" ContentType="application/xml" Extra="1"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml" ContentType="application/xml"><Child/></Default></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/document.xml" ContentType="application/xml" Extra="1"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/document.xml" ContentType="application/xml">text</Override></Types>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }
    },
    'rejects OPC XML package roots with unexpected attributes or text content' => static function (TestRunner $t): void {
        $validContentTypes = OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review">  <Default Extension="xml" ContentType="application/xml"/></Types>');
        $t->same('application/xml', $validContentTypes->contentTypeForPart('/word/document.xml'));

        $validRelationships = OpcRelationships::fromXml('<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review">  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>', '/word/document.xml');
        $t->same('/word/media/image.png', $validRelationships->resolveTarget('rId1'));

        foreach ([
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" Extra="1"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" review:Extra="1" xmlns:review="urn:wordpress-review"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '">text<Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><![CDATA[text]]><Default Extension="xml" ContentType="application/xml"/></Types>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }

        foreach ([
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" Extra="1"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" review:Extra="1" xmlns:review="urn:wordpress-review"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '">text<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><![CDATA[text]]><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($xml, '/word/document.xml'));
        }
    },
    'honors bounded OPC markup compatibility ignorable extensions' => static function (TestRunner $t): void {
        $markupCompatibilityNamespace = 'http://schemas.openxmlformats.org/markup-compatibility/2006';

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" review:source="import-preflight">
  <review:Audit packet="docx"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml" review:origin="fixture">
    <review:Note value="ignored"/>
  </Default>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml" review:purpose="main"/>
</Types>
XML;

        $types = OpcContentTypes::fromXml($contentTypesXml);
        $t->same('application/xml', $types->contentTypeForPart('/word/styles.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $types->contentTypeForPart('/word/document.xml'));

        $relationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" review:source="import-preflight">
  <review:Audit packet="docx"/>
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml" review:label="main">
    <review:Trace value="ignored"/>
  </Relationship>
</Relationships>
XML;

        $relationships = OpcRelationships::fromXml($relationshipsXml);
        $t->same('/word/document.xml', $relationships->resolveTarget('rIdDocument'));

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $relationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
        ]));
        $root = $graph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $t->same(true, $root['valid']);
        $t->same('/word/document.xml', $root['relationships'][0]['targetPart']);

        foreach ([
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" review:source="import-preflight"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . $markupCompatibilityNamespace . '" mc:Ignorable="missing"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . $markupCompatibilityNamespace . '" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:ProcessContent="review"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><review:Audit/><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><Default Extension="xml" ContentType="application/xml" review:origin="fixture"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><Default Extension="xml" ContentType="application/xml"><review:Note/></Default></Types>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }

        foreach ([
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" review:source="import-preflight"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:mc="' . $markupCompatibilityNamespace . '" mc:Ignorable="missing"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:mc="' . $markupCompatibilityNamespace . '" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveElements="review:*"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><review:Audit/><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png" review:origin="fixture"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"><review:Note/></Relationship></Relationships>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($xml, '/word/document.xml'));
        }
    },
    'maps OPC source parts and relationship part names' => static function (TestRunner $t): void {
        $t->same('/_rels/.rels', OpcRelationships::relationshipPartNameForSource('/'));
        $t->same('/_rels/.rels', OpcRelationships::relationshipPartNameForSource('/.'));
        $t->same('/_rels/document.xml.rels', OpcRelationships::relationshipPartNameForSource('/document.xml'));
        $t->same('/word/_rels/document.xml.rels', OpcRelationships::relationshipPartNameForSource('/word/document.xml'));
        $t->same('/word/embeddings/_rels/oleObject1.bin.rels', OpcRelationships::relationshipPartNameForSource('word/embeddings/oleObject1.bin'));
        $t->same('/word/media/_rels/source%20diagram.svg.rels', OpcRelationships::relationshipPartNameForSource('/word/media/source diagram.svg'));
        $t->same('/customXml/_rels/%C3%A9preuve.xml.rels', OpcRelationships::relationshipPartNameForSource("/customXml/\u{00E9}preuve.xml"));
        $t->same('/', OpcRelationships::sourcePartNameForRelationshipPart('/_rels/.rels'));
        $t->same('/document.xml', OpcRelationships::sourcePartNameForRelationshipPart('/_rels/document.xml.rels'));
        $t->same('/word/document.xml', OpcRelationships::sourcePartNameForRelationshipPart('/word/_rels/document.xml.rels'));
        $t->same('/word/embeddings/oleObject1.bin', OpcRelationships::sourcePartNameForRelationshipPart('/word/embeddings/_rels/oleObject1.bin.rels'));
        $t->same('/word/media/source diagram.svg', OpcRelationships::sourcePartNameForRelationshipPart('/word/media/_rels/source%20diagram.svg.rels'));
        $t->same("/customXml/\u{00E9}preuve.xml", OpcRelationships::sourcePartNameForRelationshipPart('/customXml/_rels/%C3%A9preuve.xml.rels'));
        $t->true(OpcRelationships::isRelationshipPartName('/_rels/.rels'));
        $t->true(OpcRelationships::isRelationshipPartName('/word/_rels/document.xml.rels'));
        $t->same(false, OpcRelationships::isRelationshipPartName('/word/document.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcRelationships::sourcePartNameForRelationshipPart('/word/document.xml.rels'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcRelationships::relationshipPartNameForSource('/word/_rels/document.xml.rels'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => new OpcRelationships('/word/_rels/document.xml.rels'));
    },
    'loads OPC relationship parts for percent encoded source part names' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/review%20source.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/review%20source.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/source%20diagram.png"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'word/review source.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/review%20source.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/source diagram.png', 'data' => 'PNG'],
        ]);

        $t->true(OpcRelationships::packageHasRelationshipsForSource($package, '/word/review source.xml'));
        $relationships = OpcRelationships::fromPackage($package, '/word/review source.xml');
        $t->same('/word/_rels/review%20source.xml.rels', $relationships->relationshipPartName());
        $t->same('/word/media/source diagram.png', $relationships->resolveTarget('rIdReviewImage'));

        $graph = OpcRelationshipGraph::fromPackage($package);
        $t->same(['/', '/word/review source.xml'], $graph->sourcePartNames());
        $t->true($graph->hasRelationshipsForSource('/word/review source.xml'));
        $t->same(null, $graph->relationshipsForSource('/word/review%20source.xml'));

        $parts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $parts[$part['partName']] = $part;
        }

        $t->same('/word/review source.xml', $parts['/word/_rels/review%20source.xml.rels']['relationshipSource']);
        $t->same(true, $parts['/word/_rels/review%20source.xml.rels']['sourceExists']);
        $t->same(true, $parts['/word/_rels/review%20source.xml.rels']['relationshipSourceLoaded']);
        $t->same(true, $parts['/word/_rels/review%20source.xml.rels']['valid']);
        $t->same([], $parts['/word/_rels/review%20source.xml.rels']['issues']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(['rIdDocument', 'rIdReviewImage'], array_keys($closureById));
        $t->same('/word/review source.xml', $closureById['rIdDocument']['targetPart']);
        $t->same('/word/review source.xml', $closureById['rIdReviewImage']['source']);
        $t->same('/word/media/source diagram.png', $closureById['rIdReviewImage']['targetPart']);
        $t->same('image/png', $closureById['rIdReviewImage']['contentType']);
        $t->same(true, $closureById['rIdReviewImage']['valid']);
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
    'resolves same source internal OPC relationship fragment and query targets' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdBookmark" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#review-bookmark"/>
  <Relationship Id="rIdReviewerState" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="?review=ready#packet"/>
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;

        $relationships = OpcRelationships::fromXml($documentRelationshipsXml, '/word/document.xml');

        $t->same('/word/document.xml#review-bookmark', $relationships->resolveTarget('rIdBookmark'));
        $t->same('/word/document.xml?review=ready#packet', $relationships->resolveTarget('rIdReviewerState'));
        $t->same('/word/styles.xml', $relationships->resolveTarget('rIdStyles'));

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->same('/word/document.xml#review-bookmark', $preflight['rIdBookmark']['target']);
        $t->same('/word/document.xml', OpcPackagePath::stripQueryAndFragment($preflight['rIdBookmark']['target']));
        $t->same(true, $preflight['rIdBookmark']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $preflight['rIdBookmark']['contentType']);
        $t->same(true, $preflight['rIdBookmark']['valid']);
        $t->same([], $preflight['rIdBookmark']['issues']);
        $t->same('/word/document.xml?review=ready#packet', $preflight['rIdReviewerState']['target']);
        $t->same('/word/document.xml', OpcPackagePath::stripQueryAndFragment($preflight['rIdReviewerState']['target']));
        $t->same(true, $preflight['rIdReviewerState']['exists']);
        $t->same(true, $preflight['rIdReviewerState']['valid']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(['rIdDocument', 'rIdBookmark', 'rIdReviewerState', 'rIdStyles'], array_keys($closureById));
        $t->same('/word/document.xml', $closureById['rIdBookmark']['targetPart']);
        $t->same(1, $closureById['rIdBookmark']['depth']);
        $t->same('/word/document.xml', $closureById['rIdReviewerState']['targetPart']);
        $t->same(array_fill(0, 4, true), array_column($closureById, 'valid'));

        $rootRelationships = new OpcRelationships('/');
        $rootRelationships->add(new OpcRelationship('rIdFragment', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml', '#root-fragment'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $rootRelationships->resolveTarget('rIdFragment'));
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
        $t->same('absolute-uri', $preflight['rIdExternal']['externalTargetKind']);
        $t->same('https', $preflight['rIdExternal']['externalTargetScheme']);
        $t->same(true, $preflight['rIdExternal']['externalTargetAllowed']);
        $t->same([], $preflight['rIdExternal']['issues']);

        $t->same('../../evil.xml', $preflight['rIdEscape']['target']);
        $t->same(false, $preflight['rIdEscape']['valid']);
        $t->same(['invalid-target'], $preflight['rIdEscape']['issues']);

        $imagePreflight = $graph->preflightTargetsForSource('/word/document.xml', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
        $t->same(['rIdMissingImage', 'rIdEscape'], array_column($imagePreflight, 'id'));
        $t->same([], $graph->preflightTargetsForSource('/word/missing.xml'));
    },
    'classifies and preflights external OPC relationship target policies' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHttp" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source packet.html?post=42#review" TargetMode="External"/>
  <Relationship Id="rIdMailto" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="mailto:editor@example.test" TargetMode="External"/>
  <Relationship Id="rIdNetwork" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="//cdn.example.test/review.png" TargetMode="External"/>
  <Relationship Id="rIdRelative" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="review/source.html#packet" TargetMode="External"/>
  <Relationship Id="rIdFragment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#local-bookmark" TargetMode="External"/>
  <Relationship Id="rIdFile" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="file:///tmp/source.html" TargetMode="External"/>
  <Relationship Id="rIdJavascript" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>
  <Relationship Id="rIdData" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="data:text/plain;base64,SGVsbG8=" TargetMode="External"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->same([
            'rIdHttp',
            'rIdMailto',
            'rIdNetwork',
            'rIdRelative',
            'rIdFragment',
            'rIdFile',
            'rIdJavascript',
            'rIdData',
        ], array_keys($preflight));
        $t->same('absolute-uri', $preflight['rIdHttp']['externalTargetKind']);
        $t->same('https', $preflight['rIdHttp']['externalTargetScheme']);
        $t->same(true, $preflight['rIdHttp']['externalTargetAllowed']);
        $t->same([], $preflight['rIdHttp']['issues']);
        $t->same('mailto', $preflight['rIdMailto']['externalTargetScheme']);
        $t->same(true, $preflight['rIdMailto']['valid']);
        $t->same('network-path-reference', $preflight['rIdNetwork']['externalTargetKind']);
        $t->same(null, $preflight['rIdNetwork']['externalTargetScheme']);
        $t->same(true, $preflight['rIdNetwork']['externalTargetAllowed']);
        $t->same('relative-reference', $preflight['rIdRelative']['externalTargetKind']);
        $t->same(null, $preflight['rIdRelative']['externalTargetScheme']);
        $t->same(true, $preflight['rIdRelative']['valid']);
        $t->same('fragment-reference', $preflight['rIdFragment']['externalTargetKind']);
        $t->same(true, $preflight['rIdFragment']['externalTargetAllowed']);
        $t->same('absolute-uri', $preflight['rIdFile']['externalTargetKind']);
        $t->same('file', $preflight['rIdFile']['externalTargetScheme']);
        $t->same(false, $preflight['rIdFile']['externalTargetAllowed']);
        $t->same(false, $preflight['rIdFile']['valid']);
        $t->same(['external-target-unsafe-scheme'], $preflight['rIdFile']['issues']);
        $t->same('javascript', $preflight['rIdJavascript']['externalTargetScheme']);
        $t->same(['external-target-unsafe-scheme'], $preflight['rIdJavascript']['issues']);
        $t->same('data', $preflight['rIdData']['externalTargetScheme']);
        $t->same(['external-target-unsafe-scheme'], $preflight['rIdData']['issues']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same('javascript', $closureById['rIdJavascript']['externalTargetScheme']);
        $t->same(false, $closureById['rIdJavascript']['valid']);
        $t->same(['external-target-unsafe-scheme'], $closureById['rIdJavascript']['issues']);
        $t->same(null, $closureById['rIdRelative']['targetPart']);
    },
    'surfaces OPC external relative target rewrite context' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdAbsolute" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source.html" TargetMode="External"/>
  <Relationship Id="rIdRelative" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="../review/source.html#packet" TargetMode="External"/>
  <Relationship Id="rIdFragment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#local-review" TargetMode="External"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('word/./document.xml') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->true(array_key_exists('externalTargetRequiresBaseUri', $preflight['rIdRelative']));
        $t->same(false, $preflight['rIdAbsolute']['externalTargetRequiresBaseUri']);
        $t->same(null, $preflight['rIdAbsolute']['externalTargetRewriteBasePart']);
        $t->same(null, $preflight['rIdAbsolute']['externalTargetRewriteReason']);
        $t->same(true, $preflight['rIdRelative']['externalTargetRequiresBaseUri']);
        $t->same('/word/document.xml', $preflight['rIdRelative']['externalTargetRewriteBasePart']);
        $t->same('external-target-relative-reference', $preflight['rIdRelative']['externalTargetRewriteReason']);
        $t->same(true, $preflight['rIdRelative']['valid']);
        $t->same([], $preflight['rIdRelative']['issues']);
        $t->same(true, $preflight['rIdFragment']['externalTargetRequiresBaseUri']);
        $t->same('/word/document.xml', $preflight['rIdFragment']['externalTargetRewriteBasePart']);
        $t->same('external-target-fragment-reference', $preflight['rIdFragment']['externalTargetRewriteReason']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(true, $closureById['rIdRelative']['externalTargetRequiresBaseUri']);
        $t->same('/word/document.xml', $closureById['rIdRelative']['externalTargetRewriteBasePart']);
        $t->same('external-target-fragment-reference', $closureById['rIdFragment']['externalTargetRewriteReason']);
    },
    'preflights OPC relationship Type URI policies' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $valid = new OpcRelationship(
            'rIdImage',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
            'media/review.png',
        );
        $urn = new OpcRelationship('rIdUrn', 'urn:example:wordpress-import-review', 'customXml/item1.xml');
        $relative = new OpcRelationship('rIdRelativeType', 'officeDocument/relationships/image', 'media/review.png');
        $network = new OpcRelationship('rIdNetworkType', '//schemas.openxmlformats.org/relationships/image', 'media/review.png');
        $fragment = new OpcRelationship('rIdFragmentType', '#relationship-type', 'media/review.png');
        $space = new OpcRelationship('rIdSpaceType', 'http://example.test/relationship type', 'media/review.png');

        $t->same([
            'kind' => 'absolute-uri',
            'scheme' => 'http',
            'valid' => true,
            'issues' => [],
        ], $valid->relationshipTypePreflight());
        $t->same('urn', $urn->relationshipTypePreflight()['scheme']);
        $t->same([
            'kind' => 'relative-reference',
            'scheme' => null,
            'valid' => false,
            'issues' => ['relationship-type-not-absolute-uri'],
        ], $relative->relationshipTypePreflight());
        $t->same('network-path-reference', $network->relationshipTypePreflight()['kind']);
        $t->same(['relationship-type-not-absolute-uri'], $network->relationshipTypePreflight()['issues']);
        $t->same('fragment-reference', $fragment->relationshipTypePreflight()['kind']);
        $t->same(['relationship-type-not-absolute-uri'], $fragment->relationshipTypePreflight()['issues']);
        $t->same(['relationship-type-invalid-uri-bytes'], $space->relationshipTypePreflight()['issues']);

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdBadType" Type="officeDocument/relationships/hyperlink" Target="https://example.test/source" TargetMode="External"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->same('absolute-uri', $preflight['rIdStyles']['relationshipTypeKind']);
        $t->same('http', $preflight['rIdStyles']['relationshipTypeScheme']);
        $t->same(true, $preflight['rIdStyles']['relationshipTypeValid']);
        $t->same([], $preflight['rIdStyles']['relationshipTypeIssues']);
        $t->same(true, $preflight['rIdStyles']['valid']);

        $t->same('relative-reference', $preflight['rIdBadType']['relationshipTypeKind']);
        $t->same(null, $preflight['rIdBadType']['relationshipTypeScheme']);
        $t->same(false, $preflight['rIdBadType']['relationshipTypeValid']);
        $t->same(['relationship-type-not-absolute-uri'], $preflight['rIdBadType']['relationshipTypeIssues']);
        $t->same(false, $preflight['rIdBadType']['valid']);
        $t->same(['relationship-type-not-absolute-uri'], $preflight['rIdBadType']['issues']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same('relative-reference', $closureById['rIdBadType']['relationshipTypeKind']);
        $t->same(['relationship-type-not-absolute-uri'], $closureById['rIdBadType']['issues']);
    },
    'preflights OPC digital signature origin and signature parts' => static function (TestRunner $t): void {
        $signedContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $signedPackageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignature1" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $signedContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $signedPackageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
            ['name' => '_xmlsignatures/sig1.xml', 'data' => '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/>'],
        ]));

        $signatures = $graph->preflightDigitalSignatures();

        $t->same(1, count($signatures));
        $t->same('rIdSignatureOrigin', $signatures[0]['id']);
        $t->same('/_xmlsignatures/origin.sigs', $signatures[0]['targetPart']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-origin', $signatures[0]['contentType']);
        $t->same(true, $signatures[0]['exists']);
        $t->same('/_xmlsignatures/_rels/origin.sigs.rels', $signatures[0]['relationshipPartName']);
        $t->same(true, $signatures[0]['valid']);
        $t->same([], $signatures[0]['issues']);
        $t->same(1, count($signatures[0]['signatures']));
        $t->same('rIdSignature1', $signatures[0]['signatures'][0]['id']);
        $t->same('/_xmlsignatures/sig1.xml', $signatures[0]['signatures'][0]['targetPart']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml', $signatures[0]['signatures'][0]['contentType']);
        $t->same(true, $signatures[0]['signatures'][0]['exists']);
        $t->same(true, $signatures[0]['signatures'][0]['valid']);
        $t->same([], $signatures[0]['signatures'][0]['issues']);

        $rootSignatureTargets = $graph->preflightTargetsForSource('/', 'http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin');
        $t->same(['rIdSignatureOrigin'], array_column($rootSignatureTargets, 'id'));
        $t->same('/_xmlsignatures/origin.sigs', OpcPackagePath::stripQueryAndFragment($rootSignatureTargets[0]['target']));
    },
    'flags invalid OPC digital signature relationship packages' => static function (TestRunner $t): void {
        $badSignedContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/missing.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $signedPackageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $badSignatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdWrongType" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml"/>
  <Relationship Id="rIdMissingSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="missing.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $badSignedContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $signedPackageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $badSignatureOriginRelationshipsXml],
            ['name' => '_xmlsignatures/sig1.xml', 'data' => '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/>'],
        ]));

        $signatures = $graph->preflightDigitalSignatures();

        $t->same(1, count($signatures));
        $t->same(false, $signatures[0]['valid']);
        $t->same(['invalid-digital-signature-origin-content-type'], $signatures[0]['issues']);
        $t->same(2, count($signatures[0]['signatures']));
        $t->same('rIdWrongType', $signatures[0]['signatures'][0]['id']);
        $t->same(false, $signatures[0]['signatures'][0]['valid']);
        $t->same(['invalid-digital-signature-content-type'], $signatures[0]['signatures'][0]['issues']);
        $t->same('rIdMissingSignature', $signatures[0]['signatures'][1]['id']);
        $t->same(false, $signatures[0]['signatures'][1]['exists']);
        $t->same(false, $signatures[0]['signatures'][1]['valid']);
        $t->same(['missing-in-package'], $signatures[0]['signatures'][1]['issues']);

        $unsignedGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $badSignedContentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdDocument" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="word/document.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
        ]));

        $t->same([], $unsignedGraph->preflightDigitalSignatures());
    },
    'preflights OPC embedded package and object relationships' => static function (TestRunner $t): void {
        $embeddedContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/Workbook1.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/embeddings/oleObject1.bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/>
  <Override PartName="/word/embeddings/wrong.bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/embeddings/missing.bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/Workbook1.xlsx"/>
  <Relationship Id="rIdEmbeddedOle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject" Target="embeddings/oleObject1.bin"/>
  <Relationship Id="rIdExternalPackage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="https://example.test/source-workbook.xlsx" TargetMode="External"/>
  <Relationship Id="rIdUnsafeExternalPackage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="file:///tmp/source-workbook.xlsx" TargetMode="External"/>
  <Relationship Id="rIdWrongPackageType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/wrong.bin"/>
  <Relationship Id="rIdMissingOle" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject" Target="embeddings/missing.bin"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $embeddedContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/embeddings/Workbook1.xlsx', 'data' => 'PK' . "\x03\x04"],
            ['name' => 'word/embeddings/oleObject1.bin', 'data' => 'OLE'],
            ['name' => 'word/embeddings/wrong.bin', 'data' => 'not an embedded package'],
        ]));

        $embedded = [];
        foreach ($graph->preflightEmbeddedPackages('/word/document.xml') as $target) {
            $embedded[$target['id']] = $target;
        }

        $t->same([
            'rIdEmbeddedWorkbook',
            'rIdEmbeddedOle',
            'rIdExternalPackage',
            'rIdUnsafeExternalPackage',
            'rIdWrongPackageType',
            'rIdMissingOle',
        ], array_keys($embedded));

        $t->same('embedded-package', $embedded['rIdEmbeddedWorkbook']['kind']);
        $t->same('/word/embeddings/Workbook1.xlsx', $embedded['rIdEmbeddedWorkbook']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.package', $embedded['rIdEmbeddedWorkbook']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.package', $embedded['rIdEmbeddedWorkbook']['expectedContentType']);
        $t->same(true, $embedded['rIdEmbeddedWorkbook']['exists']);
        $t->same(true, $embedded['rIdEmbeddedWorkbook']['valid']);
        $t->same([], $embedded['rIdEmbeddedWorkbook']['issues']);

        $t->same('embedded-object', $embedded['rIdEmbeddedOle']['kind']);
        $t->same('/word/embeddings/oleObject1.bin', $embedded['rIdEmbeddedOle']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.oleObject', $embedded['rIdEmbeddedOle']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.oleObject', $embedded['rIdEmbeddedOle']['expectedContentType']);
        $t->same(true, $embedded['rIdEmbeddedOle']['valid']);

        $t->same('embedded-package', $embedded['rIdExternalPackage']['kind']);
        $t->same(true, $embedded['rIdExternalPackage']['external']);
        $t->same(null, $embedded['rIdExternalPackage']['targetPart']);
        $t->same(null, $embedded['rIdExternalPackage']['contentType']);
        $t->same('https', $embedded['rIdExternalPackage']['externalTargetScheme']);
        $t->same(true, $embedded['rIdExternalPackage']['valid']);
        $t->same([], $embedded['rIdExternalPackage']['issues']);

        $t->same('file', $embedded['rIdUnsafeExternalPackage']['externalTargetScheme']);
        $t->same(false, $embedded['rIdUnsafeExternalPackage']['valid']);
        $t->same(['external-target-unsafe-scheme'], $embedded['rIdUnsafeExternalPackage']['issues']);

        $t->same('/word/embeddings/wrong.bin', $embedded['rIdWrongPackageType']['targetPart']);
        $t->same('application/octet-stream', $embedded['rIdWrongPackageType']['contentType']);
        $t->same(false, $embedded['rIdWrongPackageType']['valid']);
        $t->same(['invalid-embedded-package-content-type'], $embedded['rIdWrongPackageType']['issues']);

        $t->same('/word/embeddings/missing.bin', $embedded['rIdMissingOle']['targetPart']);
        $t->same(false, $embedded['rIdMissingOle']['exists']);
        $t->same(false, $embedded['rIdMissingOle']['valid']);
        $t->same(['missing-in-package'], $embedded['rIdMissingOle']['issues']);

        $t->same([], $graph->preflightEmbeddedPackages('/word/missing.xml'));
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
    'preflights package-wide OPC consistency across overrides and relationships' => static function (TestRunner $t) use ($packageRelationshipsXml): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
  <Override PartName="/word/media/stale-review.png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/comment.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $overrides = [];
        foreach ($graph->preflightContentTypeOverrides() as $override) {
            $overrides[$override['partName']] = $override;
        }

        $targets = [];
        foreach ($graph->preflightAllRelationshipTargets() as $target) {
            $targets[$target['source'] . ':' . $target['id']] = $target;
        }

        $consistency = $graph->preflightPackageConsistency();

        $t->same([
            '/word/document.xml',
            '/word/comments.xml',
            '/word/_rels/comments.xml.rels',
            '/word/media/stale-review.png',
            '/docProps/core.xml',
        ], array_keys($overrides));
        $t->same(true, $overrides['/word/document.xml']['valid']);
        $t->same(true, $overrides['/word/comments.xml']['exists']);
        $t->same(true, $overrides['/word/comments.xml']['valid']);
        $t->same(true, $overrides['/word/_rels/comments.xml.rels']['relationshipPart']);
        $t->same('/word/comments.xml', $overrides['/word/_rels/comments.xml.rels']['relationshipSource']);
        $t->same(true, $overrides['/word/_rels/comments.xml.rels']['sourceExists']);
        $t->same(false, $overrides['/word/_rels/comments.xml.rels']['relationshipSourceLoaded']);
        $t->same(false, $overrides['/word/_rels/comments.xml.rels']['valid']);
        $t->same(['invalid-relationship-content-type'], $overrides['/word/_rels/comments.xml.rels']['issues']);
        $t->same(false, $overrides['/word/media/stale-review.png']['exists']);
        $t->same(false, $overrides['/word/media/stale-review.png']['valid']);
        $t->same(['override-target-missing-part'], $overrides['/word/media/stale-review.png']['issues']);

        $t->same([
            '/:rIdDocument',
            '/:rIdCore',
            '/:rIdExternalAudit',
            '/word/document.xml:rIdComments',
            '/word/document.xml:rIdHero',
        ], array_keys($targets));
        $t->same('/docProps/core.xml', $targets['/:rIdCore']['targetPart']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $targets['/:rIdCore']['contentType']);
        $t->same(null, $targets['/:rIdExternalAudit']['targetPart']);
        $t->same(true, $targets['/:rIdExternalAudit']['external']);
        $t->same('/word/comments.xml', $targets['/word/document.xml:rIdComments']['targetPart']);
        $t->same(true, $targets['/word/document.xml:rIdComments']['valid']);
        $t->same('/word/media/hero.png', $targets['/word/document.xml:rIdHero']['targetPart']);
        $t->same('image/png', $targets['/word/document.xml:rIdHero']['contentType']);
        $t->same(false, isset($targets['/word/comments.xml:rIdCommentImage']));

        $t->same(false, $consistency['valid']);
        $t->same(false, $consistency['packagePartsValid']);
        $t->same(false, $consistency['contentTypeOverridesValid']);
        $t->same(true, $consistency['relationshipTargetsValid']);
        $t->same(8, count($consistency['packageParts']));
        $t->same(5, count($consistency['contentTypeOverrides']));
        $t->same(5, count($consistency['relationshipTargets']));
    },
    'preflights DOCX officeDocument relationship cardinality and content type' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $validGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $valid = $validGraph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $t->same(1, $valid['relationshipCount']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES, $valid['expectedContentTypes']);
        $t->same(true, $valid['valid']);
        $t->same([], $valid['issues']);
        $t->same(1, count($valid['relationships']));
        $t->same('rIdDocument', $valid['relationships'][0]['id']);
        $t->same('/word/document.xml', $valid['relationships'][0]['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $valid['relationships'][0]['contentType']);
        $t->same(false, $valid['relationships'][0]['external']);
        $t->same(true, $valid['relationships'][0]['exists']);
        $t->same(true, $valid['relationships'][0]['valid']);
        $t->same([], $valid['relationships'][0]['issues']);

        $missingRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;
        $missingGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $missingRootRelationshipsXml],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));
        $missing = $missingGraph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $t->same(0, $missing['relationshipCount']);
        $t->same(false, $missing['valid']);
        $t->same(['missing-office-document-relationship'], $missing['issues']);
        $t->same([], $missing['relationships']);

        $multiContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/alt.xml" ContentType="application/xml"/>
</Types>
XML;
        $multiRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdAlt" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/alt.xml"/>
  <Relationship Id="rIdExternalDoc" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="https://example.test/reviewer-source.docx" TargetMode="External"/>
</Relationships>
XML;
        $multiGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $multiContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $multiRootRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/alt.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
        ]));

        $multi = $multiGraph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $byId = [];
        foreach ($multi['relationships'] as $relationship) {
            $byId[$relationship['id']] = $relationship;
        }

        $t->same(3, $multi['relationshipCount']);
        $t->same(false, $multi['valid']);
        $t->same(['multiple-office-document-relationships'], $multi['issues']);
        $t->same(['rIdDocument', 'rIdAlt', 'rIdExternalDoc'], array_keys($byId));
        $t->same(true, $byId['rIdDocument']['valid']);
        $t->same([], $byId['rIdDocument']['issues']);
        $t->same('/word/alt.xml', $byId['rIdAlt']['targetPart']);
        $t->same('application/xml', $byId['rIdAlt']['contentType']);
        $t->same(false, $byId['rIdAlt']['valid']);
        $t->same(['invalid-office-document-content-type'], $byId['rIdAlt']['issues']);
        $t->same(true, $byId['rIdExternalDoc']['external']);
        $t->same(null, $byId['rIdExternalDoc']['targetPart']);
        $t->same('https', $byId['rIdExternalDoc']['externalTargetScheme']);
        $t->same(false, $byId['rIdExternalDoc']['valid']);
        $t->same(['external-office-document-target'], $byId['rIdExternalDoc']['issues']);
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
    'does not load OPC relationship parts with invalid content type as sources' => static function (TestRunner $t) use ($packageRelationshipsXml): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
</Types>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/comment.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $t->same(['/', '/word/document.xml'], $graph->sourcePartNames());
        $t->same(false, $graph->hasRelationshipsForSource('/word/comments.xml'));
        $t->same(null, $graph->relationshipsForSource('/word/comments.xml'));
        $t->same([], $graph->preflightTargetsForSource('/word/comments.xml'));

        $parts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $parts[$part['partName']] = $part;
        }

        $commentsRels = $parts['/word/_rels/comments.xml.rels'];
        $t->same(true, $commentsRels['relationshipPart']);
        $t->same('/word/comments.xml', $commentsRels['relationshipSource']);
        $t->same(false, $commentsRels['relationshipSourceIsRelationshipPart']);
        $t->same(true, $commentsRels['sourceExists']);
        $t->same(false, $commentsRels['relationshipSourceLoaded']);
        $t->same('application/xml', $commentsRels['contentType']);
        $t->same(['invalid-relationship-content-type'], $commentsRels['issues']);
        $t->same(false, $commentsRels['valid']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(['rIdDocument', 'rIdComments'], array_keys($closureById));
        $t->same('/word/comments.xml', $closureById['rIdComments']['targetPart']);
        $t->same(true, $closureById['rIdComments']['valid']);
        $t->same(false, isset($closureById['rIdCommentImage']));
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
    'rejects OPC relationship records with unexpected attributes or child content' => static function (TestRunner $t): void {
        $validWithWhitespace = OpcRelationships::fromXml('<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png">   </Relationship></Relationships>', '/word/document.xml');
        $t->same('/word/media/image.png', $validWithWhitespace->resolveTarget('rId1'));

        foreach ([
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png" Extra="1"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"><Child/></Relationship></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png">text</Relationship></Relationships>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($xml, '/word/document.xml'));
        }
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
