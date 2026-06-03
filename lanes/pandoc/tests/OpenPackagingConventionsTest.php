<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcPackagePath;
use PortLibs\Pandoc\OpcRelationship;
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
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcRelationships::sourcePartNameForRelationshipPart('/word/document.xml.rels'));
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
