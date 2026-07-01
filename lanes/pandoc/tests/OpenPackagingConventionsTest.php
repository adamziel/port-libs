<?php

declare(strict_types=1);

use PortLibs\Pandoc\OpcContentTypes;
use PortLibs\Pandoc\OpcMarkupCompatibility;
use PortLibs\Pandoc\OpcPackagePath;
use PortLibs\Pandoc\OpcRelationship;
use PortLibs\Pandoc\OpcRelationshipGraph;
use PortLibs\Pandoc\OpcRelationships;
use PortLibs\Pandoc\ZipPackage;

$pathSegmentPositionReviews = static function (array $segments): array {
    $reviews = [];
    $segmentCount = count($segments);
    foreach ($segments as $segmentIndex => $segment) {
        $isFirst = $segmentIndex === 0;
        $isLast = $segmentIndex === $segmentCount - 1;
        $isOnly = $segmentCount === 1;
        $position = match (true) {
            $isOnly => 'only',
            $isFirst => 'first',
            $isLast => 'last',
            default => 'middle',
        };

        $reviews[] = [
            'pathSegmentIndex' => $segmentIndex,
            'segment' => $segment,
            'position' => $position,
            'isFirst' => $isFirst,
            'isLast' => $isLast,
            'isOnly' => $isOnly,
        ];
    }

    return $reviews;
};

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
  <Relationship Id="rIdExternalAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%20packet.html?post=42#review" TargetMode="External"/>
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

/**
 * @param list<array{name:string, data:string, centralIndex?:int, method?:int, flags?:int, descriptor?:bool, descriptorSignature?:bool}> $entries
 */
$buildOpcZipPackage = static function (array $entries): string {
    $body = '';
    $centralRecords = [];

    foreach ($entries as $entryIndex => $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $method = $entry['method'] ?? 0;
        $compressed = match ($method) {
            0 => $data,
            8 => gzdeflate($data),
            default => throw new RuntimeException("Unsupported OPC fixture compression method {$method}"),
        };
        if (!is_string($compressed)) {
            throw new RuntimeException("Unable to deflate OPC fixture entry {$name}");
        }
        $usesDescriptor = ($entry['descriptor'] ?? false) === true;
        $flags = $entry['flags'] ?? 0x0800;
        if ($usesDescriptor) {
            $flags |= 0x0008;
        }
        $crc32 = (int) sprintf('%u', crc32($data));
        $compressedSize = strlen($compressed);
        $uncompressedSize = strlen($data);
        $offset = strlen($body);
        $localCrc32 = $usesDescriptor ? 0 : $crc32;
        $localCompressedSize = $usesDescriptor ? 0 : $compressedSize;
        $localUncompressedSize = $usesDescriptor ? 0 : $uncompressedSize;

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $localCrc32,
            $localCompressedSize,
            $localUncompressedSize,
            strlen($name),
            0
        );
        $body .= $name . $compressed;
        if ($usesDescriptor) {
            if (($entry['descriptorSignature'] ?? true) === true) {
                $body .= "PK\x07\x08";
            }
            $body .= pack('VVV', $crc32, $compressedSize, $uncompressedSize);
        }

        $centralRecord = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc32,
            $compressedSize,
            $uncompressedSize,
            strlen($name),
            0,
            0,
            0,
            0,
            0,
            $offset
        );
        $centralRecord .= $name;
        $centralRecords[] = [
            'order' => $entry['centralIndex'] ?? $entryIndex,
            'index' => $entryIndex,
            'record' => $centralRecord,
        ];
    }

    usort(
        $centralRecords,
        static fn (array $left, array $right): int => [$left['order'], $left['index']] <=> [$right['order'], $right['index']]
    );

    $central = implode('', array_map(static fn (array $record): string => $record['record'], $centralRecords));
    $centralOffset = strlen($body);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $centralOffset, 0);
};

$buildSignedOpcZipPackage = static function () use ($buildOpcZipPackage): string {
    $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
</Types>
XML;
    $zip = $buildOpcZipPackage([
        ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'method' => 0],
        ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'method' => 0],
        ['name' => 'word/document.xml', 'data' => '<w:document/>', 'method' => 0],
    ]);
    $eocdOffset = strrpos($zip, "PK\x05\x06");
    if ($eocdOffset === false) {
        throw new RuntimeException('Unable to locate EOCD in signed OPC fixture');
    }

    $signatureData = 'opc-central-signature';
    $signature = pack('Vv', 0x05054b50, strlen($signatureData)) . $signatureData;

    return substr($zip, 0, $eocdOffset) . $signature . substr($zip, $eocdOffset);
};

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
    'preflights OPC content type parameter provenance before package graph handoff' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="html" ContentType="text/html; charset=&quot;utf-8; wp&quot;; boundary=review"/>
  <Override PartName="/word/chunks/review.xhtml" ContentType="application/xhtml+xml; profile=&quot;urn:wp\&quot;review&quot;; charset=utf-8"/>
</Types>
XML;

        $summary = OpcContentTypes::preflightXml($contentTypesXml);
        $types = OpcContentTypes::fromXml($contentTypesXml);
        $manifest = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => 'word/chunks/source.html', 'data' => '<p>source</p>'],
            ['name' => 'word/chunks/review.xhtml', 'data' => '<html/>'],
        ]));
        $entries = [];
        foreach ($manifest['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }

        $html = $summary['records'][0];
        $xhtml = $summary['records'][1];
        $t->same(true, $summary['valid']);
        $t->same(2, $summary['parameterizedContentTypeRecordCount']);
        $t->same(4, $summary['contentTypeParameterCount']);
        $t->same(2, $summary['contentTypeQuotedParameterCount']);
        $t->same([
            'boundary' => 1,
            'charset' => 2,
            'profile' => 1,
        ], $summary['contentTypeParameterNameCounts']);

        $t->same('text/html; charset="utf-8; wp"; boundary=review', $types->contentTypeForPart('/word/chunks/source.html'));
        $t->same('text/html', $html['contentTypeMediaType']);
        $t->same(true, $html['contentTypeHasParameters']);
        $t->same(2, $html['contentTypeParameterCount']);
        $t->same(['charset', 'boundary'], $html['contentTypeParameterNames']);
        $t->same([
            'charset' => 'utf-8; wp',
            'boundary' => 'review',
        ], $html['contentTypeParameterMap']);
        $t->same(true, $html['contentTypeParameters'][0]['quoted']);
        $t->same(true, $html['contentTypeParameters'][0]['valueContainsSemicolon']);
        $t->same(false, $html['contentTypeParameters'][1]['quoted']);

        $t->same('application/xhtml+xml; profile="urn:wp\"review"; charset=utf-8', $types->contentTypeForPart('/word/chunks/review.xhtml'));
        $t->same('application/xhtml+xml', $xhtml['contentTypeMediaType']);
        $t->same(['profile', 'charset'], $xhtml['contentTypeParameterNames']);
        $t->same([
            'profile' => 'urn:wp"review',
            'charset' => 'utf-8',
        ], $xhtml['contentTypeParameterMap']);
        $t->same(true, $xhtml['contentTypeParameters'][0]['quoted']);
        $t->same(true, $xhtml['contentTypeParameters'][0]['containsQuotedPair']);
        $t->same(false, $xhtml['contentTypeParameters'][0]['valueContainsSemicolon']);

        $t->same('text/html', $entries['word/chunks/source.html']['contentTypeMediaType']);
        $t->same(['charset', 'boundary'], $entries['word/chunks/source.html']['contentTypeParameterNames']);
        $t->same('utf-8; wp', $entries['word/chunks/source.html']['contentTypeParameterMap']['charset']);
        $t->same('application/xhtml+xml', $entries['word/chunks/review.xhtml']['contentTypeMediaType']);
        $t->same('urn:wp"review', $entries['word/chunks/review.xhtml']['contentTypeParameterMap']['profile']);
    },
    'reports OPC content type resolution provenance for default and override matches' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml, $documentRelationshipsXml): void {
        $types = OpcContentTypes::fromXml($contentTypesXml);

        $imageResolution = $types->contentTypeResolutionForPart('/word/media/review-image.PNG?crop=hero#source');
        $t->same('/word/media/review-image.PNG', $imageResolution['partName']);
        $t->same('image/png', $imageResolution['contentType']);
        $t->same('default', $imageResolution['contentTypeSource']);
        $t->same('png', $imageResolution['defaultExtension']);
        $t->same(null, $imageResolution['overridePartName']);

        $documentResolution = $types->contentTypeResolutionForPart('/word/document.xml');
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $documentResolution['contentType']);
        $t->same('override', $documentResolution['contentTypeSource']);
        $t->same('/word/document.xml', $documentResolution['overridePartName']);
        $t->same(true, $documentResolution['overridePartNameExactMatch']);
        $t->same(false, $documentResolution['overridePartNameEquivalentMatch']);
        $t->same(null, $documentResolution['defaultExtension']);

        $missingResolution = $types->contentTypeResolutionForPart('/word/media/source');
        $t->same(null, $missingResolution['contentType']);
        $t->same('missing', $missingResolution['contentTypeSource']);

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $targets = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $targets[$target['id']] = $target;
        }

        $t->same('default', $targets['rIdImage']['contentTypeSource']);
        $t->same('png', $targets['rIdImage']['contentTypeDefaultExtension']);
        $t->same(null, $targets['rIdImage']['contentTypeOverridePartName']);
        $t->same('override', $targets['rIdStyles']['contentTypeSource']);
        $t->same('/word/styles.xml', $targets['rIdStyles']['contentTypeOverridePartName']);
        $t->same('default', $targets['rIdCustomXml']['contentTypeSource']);
        $t->same('xml', $targets['rIdCustomXml']['contentTypeDefaultExtension']);

        $packageParts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $packageParts[$part['partName']] = $part;
        }

        $t->same('default', $packageParts['/word/media/review-image.PNG']['contentTypeSource']);
        $t->same('png', $packageParts['/word/media/review-image.PNG']['contentTypeDefaultExtension']);
        $t->same('default', $packageParts['/word/_rels/document.xml.rels']['contentTypeSource']);
        $t->same('rels', $packageParts['/word/_rels/document.xml.rels']['contentTypeDefaultExtension']);

        $consistencyTargets = [];
        foreach ($graph->preflightPackageConsistency()['relationshipTargets'] as $target) {
            $consistencyTargets[$target['source'] . ':' . $target['id']] = $target;
        }

        $t->same('default', $consistencyTargets['/word/document.xml:rIdImage']['contentTypeSource']);
        $t->same('png', $consistencyTargets['/word/document.xml:rIdImage']['contentTypeDefaultExtension']);
    },
    'reports OPC content type URI reference suffix provenance before package graph construction' => static function (TestRunner $t) use ($contentTypesXml): void {
        $types = OpcContentTypes::fromXml($contentTypesXml);

        $override = $types->contentTypeResolutionForPart('/word/document.xml?review=ready#source');
        $t->same('/word/document.xml?review=ready#source', $override['uriReference']);
        $t->same('/word/document.xml', $override['partName']);
        $t->same('?review=ready#source', $override['uriReferenceSuffix']);
        $t->same('review=ready', $override['uriReferenceQuery']);
        $t->same('source', $override['uriReferenceFragment']);
        $t->same(true, $override['hasUriReferenceSuffix']);
        $t->same('override', $override['contentTypeSource']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $override['contentType']);
        $t->same('/word/document.xml', $override['overridePartName']);

        $default = $types->contentTypeResolutionForPart('word/media/review-image.PNG#crop');
        $t->same('/word/media/review-image.PNG', $default['partName']);
        $t->same('#crop', $default['uriReferenceSuffix']);
        $t->same(null, $default['uriReferenceQuery']);
        $t->same('crop', $default['uriReferenceFragment']);
        $t->same(true, $default['hasUriReferenceSuffix']);
        $t->same('default', $default['contentTypeSource']);
        $t->same('png', $default['defaultExtension']);
        $t->same('image/png', $default['contentType']);

        $missing = $types->contentTypeResolutionForPart('/word/media/source?download=1');
        $t->same('/word/media/source', $missing['partName']);
        $t->same('?download=1', $missing['uriReferenceSuffix']);
        $t->same('download=1', $missing['uriReferenceQuery']);
        $t->same(null, $missing['uriReferenceFragment']);
        $t->same(true, $missing['hasUriReferenceSuffix']);
        $t->same('missing', $missing['contentTypeSource']);
        $t->same(null, $missing['contentType']);
        $t->same(null, $missing['defaultExtension']);

        $plain = $types->contentTypeResolutionForPart('/docProps/core.xml');
        $t->same('', $plain['uriReferenceSuffix']);
        $t->same(null, $plain['uriReferenceQuery']);
        $t->same(null, $plain['uriReferenceFragment']);
        $t->same(false, $plain['hasUriReferenceSuffix']);
    },
    'preflights OPC ZIP entry manifest before XML package handoff' => static function (TestRunner $t): void {
        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => '<Types/>'],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'docProps/custom.xml', 'data' => '<Properties/>'],
            ['name' => 'word/document.xml', 'data' => '<w:document/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<Relationships/>'],
            ['name' => 'word/media/', 'data' => ''],
            ['name' => 'word/media/image.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/package1.docx', 'data' => 'DOCX'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/sig1.xml', 'data' => '<Signature/>'],
            ['name' => 'word/_rels/orphan.xml.rels', 'data' => '<Relationships/>'],
            ['name' => 'word/_rels/media/document.xml.rels', 'data' => '<Relationships/>'],
            ['name' => 'word/_rels/_rels/document.xml.rels.rels', 'data' => '<Relationships/>'],
            ['name' => '_rels/[Content_Types].xml.rels', 'data' => '<Relationships/>'],
        ]);

        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }
        $relationshipSources = [];
        foreach ($summary['relationshipParts'] as $relationshipPart) {
            $relationshipSources[$relationshipPart['partName']] = $relationshipPart;
        }

        $t->same(false, $summary['valid']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(15, $summary['entryCount']);
        $t->same(14, $summary['fileEntryCount']);
        $t->same(1, $summary['directoryEntryCount']);
        $t->same(14, $summary['packagePartCount']);
        $t->same(1, $summary['contentTypesItemCount']);
        $t->same(5, $summary['relationshipPartCount']);
        $t->same(1, $summary['rootRelationshipPartCount']);
        $t->same(4, $summary['partRelationshipPartCount']);
        $t->same(1, $summary['invalidRelationshipPartCount']);
        $t->same(1, $summary['orphanRelationshipPartCount']);
        $t->same(1, $summary['relationshipPartSourceCount']);
        $t->same(1, $summary['contentTypesItemRelationshipSourceCount']);
        $t->same(2, $summary['documentPropertyPartCount']);
        $t->same(2, $summary['digitalSignaturePartCount']);
        $t->same(1, $summary['embeddedPackageCandidateCount']);
        $t->same(1, $summary['mediaPartCandidateCount']);
        $t->same(10, $summary['xmlPayloadPartCount']);
        $t->same(3, $summary['binaryPayloadPartCount']);
        $t->same([
            'content-types-item-source' => 1,
            'invalid-relationship-part-name' => 1,
            'orphan-relationship-part' => 1,
            'relationship-part-source' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'orphan-relationship-part',
            'invalid-relationship-part-name',
            'relationship-part-source',
            'content-types-item-source',
        ], $summary['issues']);
        $t->same([
            'content-types-item-source' => ['_rels/[Content_Types].xml.rels'],
            'invalid-relationship-part-name' => ['word/_rels/media/document.xml.rels'],
            'orphan-relationship-part' => ['word/_rels/orphan.xml.rels'],
            'relationship-part-source' => ['word/_rels/_rels/document.xml.rels.rels'],
        ], $summary['entryNamesByIssue']);
        $t->same([
            'content-types-item-source' => ['/_rels/[Content_Types].xml.rels'],
            'invalid-relationship-part-name' => ['/word/_rels/media/document.xml.rels'],
            'orphan-relationship-part' => ['/word/_rels/orphan.xml.rels'],
            'relationship-part-source' => ['/word/_rels/_rels/document.xml.rels.rels'],
        ], $summary['partNamesByIssue']);
        $t->same([
            'content-types' => 1,
            'digital-signature' => 2,
            'directory' => 1,
            'document-properties' => 2,
            'embedded-package-candidate' => 1,
            'invalid-relationship-part' => 1,
            'media' => 1,
            'package-relationships' => 1,
            'part-relationships' => 4,
            'xml-part' => 1,
        ], $summary['roleCounts']);
        $t->same(['/[Content_Types].xml'], $summary['contentTypesItems']);

        $t->same('content-types', $entries['[Content_Types].xml']['role']);
        $t->same('content-types+xml', $entries['[Content_Types].xml']['handoffKind']);
        $t->same('package-relationships', $entries['_rels/.rels']['role']);
        $t->same('/', $entries['_rels/.rels']['relationshipSource']);
        $t->same(true, $entries['_rels/.rels']['relationshipSourceExists']);
        $t->same('part-relationships', $entries['word/_rels/document.xml.rels']['role']);
        $t->same('/word/document.xml', $entries['word/_rels/document.xml.rels']['relationshipSource']);
        $t->same(true, $entries['word/_rels/document.xml.rels']['valid']);
        $t->same('directory', $entries['word/media/']['role']);
        $t->same('media', $entries['word/media/image.png']['role']);
        $t->same('embedded-package-candidate', $entries['word/embeddings/package1.docx']['role']);
        $t->same('digital-signature', $entries['_xmlsignatures/origin.sigs']['role']);

        $t->same(false, $entries['word/_rels/orphan.xml.rels']['relationshipSourceExists']);
        $t->same(['orphan-relationship-part'], $entries['word/_rels/orphan.xml.rels']['issues']);
        $t->same('invalid-relationship-part', $entries['word/_rels/media/document.xml.rels']['role']);
        $t->same('blocked', $entries['word/_rels/media/document.xml.rels']['handoffKind']);
        $t->same(['invalid-relationship-part-name'], $entries['word/_rels/media/document.xml.rels']['issues']);
        $t->contains('single .rels file', (string) $entries['word/_rels/media/document.xml.rels']['parseError']);
        $t->same('/word/_rels/document.xml.rels', $entries['word/_rels/_rels/document.xml.rels.rels']['relationshipSource']);
        $t->same(['relationship-part-source'], $entries['word/_rels/_rels/document.xml.rels.rels']['issues']);
        $t->same('/[Content_Types].xml', $entries['_rels/[Content_Types].xml.rels']['relationshipSource']);
        $t->same(['content-types-item-source'], $entries['_rels/[Content_Types].xml.rels']['issues']);

        $t->same('/word/orphan.xml', $relationshipSources['/word/_rels/orphan.xml.rels']['relationshipSource']);
        $t->same(false, $relationshipSources['/word/_rels/orphan.xml.rels']['relationshipSourceExists']);
        $t->same('/[Content_Types].xml', $relationshipSources['/_rels/[Content_Types].xml.rels']['relationshipSource']);

        $missingContentTypes = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromParts([
            ['name' => '_rels/.rels', 'data' => '<Relationships/>'],
            ['name' => 'word/document.xml', 'data' => '<w:document/>'],
        ]));
        $t->same(false, $missingContentTypes['valid']);
        $t->same(0, $missingContentTypes['contentTypesItemCount']);
        $t->same(['missing-content-types-item'], $missingContentTypes['issues']);
        $t->same(['missing-content-types-item' => 1], $missingContentTypes['issueCounts']);
        $t->same([], $missingContentTypes['entryNamesByIssue']);
        $t->same(['missing-content-types-item' => ['/[Content_Types].xml']], $missingContentTypes['partNamesByIssue']);
    },
    'carries OPC ZIP central directory source record provenance through manifest preflights' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
</Types>
XML;
        $documentExtra = pack('vv', 0x5455, 0);
        $documentComment = 'central record review';
        $parts = [
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'compressionMethod' => 0],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document><w:body><w:p>source record</w:p></w:body></w:document>',
                'compressionMethod' => 0,
                'creatorHostSystem' => 10,
                'extraFieldData' => $documentExtra,
                'comment' => $documentComment,
            ],
            ['name' => 'word/media/review.png', 'data' => 'PNG', 'compressionMethod' => 0],
        ];
        $zip = ZipPackage::build($parts);
        $package = ZipPackage::fromString($zip);
        $packageManifest = $package->packageManifestPreflight();

        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $rawSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);
        $manifestEntries = [];
        foreach ($packageManifest['entries'] as $entry) {
            $manifestEntries[$entry['name']] = $entry;
        }
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }
        $rawEntries = [];
        foreach ($rawSummary['entries'] as $entry) {
            $rawEntries[$entry['entryName']] = $entry;
        }
        $sourceRecordEntries = [];
        foreach ($summary['zipSourceRecordManifest']['entries'] as $entry) {
            $sourceRecordEntries[$entry['entryName']] = $entry;
        }

        $documentEntry = $entries['word/document.xml'];
        $rawDocumentEntry = $rawEntries['word/document.xml'];
        $manifestDocumentEntry = $manifestEntries['word/document.xml'];
        $sourceRecordDocumentEntry = $sourceRecordEntries['word/document.xml'];
        $expectedCentralRecordBytes = 46
            + strlen('word/document.xml')
            + strlen($documentExtra)
            + strlen($documentComment);
        $expectedLocalReviewFieldBytes = strlen($documentExtra);
        $expectedCentralReviewFieldBytes = strlen($documentExtra) + strlen($documentComment);
        $expectedReviewFieldBytes = $expectedLocalReviewFieldBytes + $expectedCentralReviewFieldBytes;
        $expectedReviewFieldBucket = [
            'reviewFieldEntryCount' => 1,
            'reviewFieldByteCountsAreExact' => true,
            'localHeaderReviewFieldBytes' => $expectedLocalReviewFieldBytes,
            'centralDirectoryReviewFieldBytes' => $expectedCentralReviewFieldBytes,
            'reviewFieldBytes' => $expectedReviewFieldBytes,
        ];
        $creatorFields = [
            'versionMadeBy',
            'madeByHostSystem',
            'madeByHostSystemName',
            'madeByVersion',
            'versionNeededToExtract',
            'creatorVersionMeetsNeeded',
            'creatorVersionComparison',
            'creatorVersionDelta',
            'creatorHostSystemIsKnown',
            'creatorHostSystemIssues',
        ];
        $centralFieldBytes = [
            'centralDirectoryFixedHeaderBytes',
            'centralDirectoryVariableFieldOffset',
            'centralDirectoryVariableFieldBytes',
            'centralDirectoryVariableFieldSha256',
            'centralDirectoryRawNameOffset',
            'centralDirectoryRawNameBytes',
            'centralDirectoryRawNameSha256',
            'centralDirectoryExtraFieldOffset',
            'centralDirectoryExtraFieldBytes',
            'centralDirectoryExtraFieldSha256',
            'centralDirectoryRawCommentOffset',
            'centralDirectoryRawCommentBytes',
            'centralDirectoryRawCommentSha256',
            'centralDirectoryReviewFieldBytes',
        ];

        $t->same(true, $summary['valid']);
        $t->same(true, $rawSummary['valid']);
        $t->same($documentEntry['entryIndex'], $documentEntry['centralDirectoryIndex']);
        $t->same(2, $documentEntry['centralDirectoryIndex']);
        $t->same($rawDocumentEntry['centralDirectoryOffset'], $rawDocumentEntry['centralDirectoryRecordOffset']);
        $t->same($rawDocumentEntry['centralDirectoryRecordOffset'], $documentEntry['centralDirectoryRecordOffset']);
        $t->same($rawDocumentEntry['centralDirectoryRecordEnd'], $documentEntry['centralDirectoryRecordEnd']);
        $t->same($rawDocumentEntry['centralDirectoryRecordBytes'], $documentEntry['centralDirectoryRecordBytes']);
        $t->same($expectedCentralRecordBytes, $documentEntry['centralDirectoryRecordBytes']);
        $t->same(
            $documentEntry['centralDirectoryRecordOffset'] + $documentEntry['centralDirectoryRecordBytes'],
            $documentEntry['centralDirectoryRecordEnd']
        );
        $t->same(
            hash(
                'sha256',
                substr(
                    $zip,
                    $documentEntry['centralDirectoryRecordOffset'],
                    $documentEntry['centralDirectoryRecordBytes']
                )
            ),
            $documentEntry['centralDirectoryRecordSha256']
        );
        $t->same($documentEntry['centralDirectoryRecordSha256'], $rawDocumentEntry['centralDirectoryRecordSha256']);
        foreach (array_merge($creatorFields, $centralFieldBytes) as $field) {
            $t->same($manifestDocumentEntry[$field], $documentEntry[$field], "{$field} package manifest handoff");
            $t->same($documentEntry[$field], $rawDocumentEntry[$field], "{$field} raw manifest handoff");
        }
        $t->same(0x0a14, $documentEntry['versionMadeBy']);
        $t->same(10, $documentEntry['madeByHostSystem']);
        $t->same(20, $documentEntry['madeByVersion']);
        $t->same(20, $documentEntry['versionNeededToExtract']);
        $t->same(46, $documentEntry['centralDirectoryFixedHeaderBytes']);
        $t->same(
            $documentEntry['centralDirectoryRecordOffset'] + 46,
            $documentEntry['centralDirectoryVariableFieldOffset']
        );
        $t->same(
            strlen('word/document.xml') + strlen($documentExtra) + strlen($documentComment),
            $documentEntry['centralDirectoryVariableFieldBytes']
        );
        $t->same(
            hash('sha256', substr(
                $zip,
                $documentEntry['centralDirectoryVariableFieldOffset'],
                $documentEntry['centralDirectoryVariableFieldBytes']
            )),
            $documentEntry['centralDirectoryVariableFieldSha256']
        );
        $t->same(strlen('word/document.xml'), $documentEntry['centralDirectoryRawNameBytes']);
        $t->same(hash('sha256', 'word/document.xml'), $documentEntry['centralDirectoryRawNameSha256']);
        $t->same(strlen($documentExtra), $documentEntry['centralDirectoryExtraFieldBytes']);
        $t->same(hash('sha256', $documentExtra), $documentEntry['centralDirectoryExtraFieldSha256']);
        $t->same(strlen($documentComment), $documentEntry['centralDirectoryRawCommentBytes']);
        $t->same(hash('sha256', $documentComment), $documentEntry['centralDirectoryRawCommentSha256']);
        $t->same(
            strlen($documentExtra) + strlen($documentComment),
            $documentEntry['centralDirectoryReviewFieldBytes']
        );
        $t->same($summary['zipSourceRecordManifest'], $rawSummary['zipSourceRecordManifest']);
        $t->same(true, $summary['zipSourceRecordReviewFieldByteCountsAreExact']);
        $t->same(true, $rawSummary['zipSourceRecordReviewFieldByteCountsAreExact']);
        $t->same($expectedLocalReviewFieldBytes, $summary['zipSourceRecordLocalHeaderReviewFieldBytes']);
        $t->same($expectedLocalReviewFieldBytes, $rawSummary['zipSourceRecordLocalHeaderReviewFieldBytes']);
        $t->same($expectedLocalReviewFieldBytes, $summary['zipSourceRecordKnownLocalHeaderReviewFieldBytes']);
        $t->same($expectedCentralReviewFieldBytes, $summary['zipSourceRecordCentralDirectoryReviewFieldBytes']);
        $t->same($expectedCentralReviewFieldBytes, $rawSummary['zipSourceRecordCentralDirectoryReviewFieldBytes']);
        $t->same($expectedCentralReviewFieldBytes, $summary['zipSourceRecordKnownCentralDirectoryReviewFieldBytes']);
        $t->same($expectedReviewFieldBytes, $summary['zipSourceRecordReviewFieldBytes']);
        $t->same($expectedReviewFieldBytes, $rawSummary['zipSourceRecordReviewFieldBytes']);
        $t->same($expectedReviewFieldBytes, $summary['zipSourceRecordKnownReviewFieldBytes']);
        $t->same(
            ['xml-part' => $expectedReviewFieldBucket],
            $summary['zipSourceRecordReviewFieldBytesByRole']
        );
        $t->same(
            ['xml-part' => $expectedReviewFieldBucket],
            $rawSummary['zipSourceRecordReviewFieldBytesByRole']
        );
        $t->same(
            ['xml' => $expectedReviewFieldBucket],
            $summary['zipSourceRecordReviewFieldBytesByHandoffKind']
        );
        $t->same(
            ['xml' => $expectedReviewFieldBucket],
            $rawSummary['zipSourceRecordReviewFieldBytesByHandoffKind']
        );
        $t->same(true, $sourceRecordDocumentEntry['reviewFieldByteCountsAreExact']);
        $t->same($expectedLocalReviewFieldBytes, $sourceRecordDocumentEntry['localHeaderReviewFieldBytes']);
        $t->same($expectedCentralReviewFieldBytes, $sourceRecordDocumentEntry['centralDirectoryReviewFieldBytes']);
        $t->same($expectedReviewFieldBytes, $sourceRecordDocumentEntry['reviewFieldBytes']);
        $t->same($expectedReviewFieldBytes, $sourceRecordDocumentEntry['knownReviewFieldBytes']);
    },
    'carries OPC ZIP local header and compressed payload source hashes through manifest preflights' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
</Types>
XML;
        $documentXml = '<w:document><w:body><w:p>payload provenance</w:p></w:body></w:document>';
        $documentCompressed = gzdeflate($documentXml);
        $documentExtra = pack('vv', 0x5455, 0);
        $imageBytes = "PNG review bytes\n";
        $zip = ZipPackage::build([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'compressionMethod' => 0],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 8,
                'extraFieldData' => $documentExtra,
            ],
            ['name' => 'word/media/review.png', 'data' => $imageBytes, 'compressionMethod' => 0],
        ]);

        $package = ZipPackage::fromString($zip);
        $packageManifest = $package->packageManifestPreflight();
        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $rawSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);
        $manifestEntries = [];
        foreach ($packageManifest['entries'] as $entry) {
            $manifestEntries[$entry['name']] = $entry;
        }
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }
        $rawEntries = [];
        foreach ($rawSummary['entries'] as $entry) {
            $rawEntries[$entry['entryName']] = $entry;
        }

        $documentEntry = $entries['word/document.xml'];
        $rawDocumentEntry = $rawEntries['word/document.xml'];
        $manifestDocumentEntry = $manifestEntries['word/document.xml'];
        $contentTypesEntry = $entries['[Content_Types].xml'];
        $rawContentTypesEntry = $rawEntries['[Content_Types].xml'];
        $manifestContentTypesEntry = $manifestEntries['[Content_Types].xml'];
        $localFieldBytes = [
            'localHeaderFixedHeaderBytes',
            'localHeaderVariableFieldOffset',
            'localHeaderVariableFieldBytes',
            'localHeaderVariableFieldSha256',
            'localHeaderRawNameOffset',
            'localHeaderRawNameBytes',
            'localHeaderRawNameSha256',
            'localHeaderExtraFieldOffset',
            'localHeaderExtraFieldBytes',
            'localHeaderExtraFieldSha256',
            'localHeaderReviewFieldBytes',
        ];

        $t->same(true, $summary['valid']);
        $t->same(true, $rawSummary['valid']);
        $t->same(true, $rawSummary['localHeaderSpansValid']);
        $t->same([], $rawSummary['localHeaderSpanIssues']);
        $t->same(null, $rawSummary['localHeaderSpanPreflightError']);
        $t->same(4, $rawSummary['localHeaderSpans']['entryCount']);

        $t->same(30 + strlen('word/document.xml') + strlen($documentExtra), $documentEntry['localHeaderLength']);
        $t->same($documentEntry['localHeaderOffset'] + $documentEntry['localHeaderLength'], $documentEntry['compressedDataOffset']);
        $t->same($documentEntry['compressedDataOffset'] + strlen($documentCompressed), $documentEntry['compressedDataEnd']);
        $t->same(hash('sha256', substr($zip, $documentEntry['localHeaderOffset'], $documentEntry['localHeaderLength'])), $documentEntry['localHeaderSha256']);
        $t->same(hash('sha256', $documentCompressed), $documentEntry['compressedDataSha256']);
        $t->same(hash('sha256', substr($zip, $documentEntry['compressedDataOffset'], $documentEntry['compressedSize'])), $documentEntry['compressedDataSha256']);

        foreach ($localFieldBytes as $field) {
            $t->same($manifestDocumentEntry[$field], $documentEntry[$field], "{$field} document package manifest handoff");
            $t->same($documentEntry[$field], $rawDocumentEntry[$field], "{$field} document raw manifest handoff");
        }
        $t->same(30, $documentEntry['localHeaderFixedHeaderBytes']);
        $t->same($documentEntry['localHeaderOffset'] + 30, $documentEntry['localHeaderVariableFieldOffset']);
        $t->same(strlen('word/document.xml') + strlen($documentExtra), $documentEntry['localHeaderVariableFieldBytes']);
        $t->same(
            hash('sha256', substr(
                $zip,
                $documentEntry['localHeaderVariableFieldOffset'],
                $documentEntry['localHeaderVariableFieldBytes']
            )),
            $documentEntry['localHeaderVariableFieldSha256']
        );
        $t->same(strlen('word/document.xml'), $documentEntry['localHeaderRawNameBytes']);
        $t->same(hash('sha256', 'word/document.xml'), $documentEntry['localHeaderRawNameSha256']);
        $t->same(strlen($documentExtra), $documentEntry['localHeaderExtraFieldBytes']);
        $t->same(hash('sha256', $documentExtra), $documentEntry['localHeaderExtraFieldSha256']);
        $t->same(strlen($documentExtra), $documentEntry['localHeaderReviewFieldBytes']);
        $t->same($documentEntry['localHeaderLength'], $rawDocumentEntry['localHeaderLength']);
        $t->same($documentEntry['localHeaderSha256'], $rawDocumentEntry['localHeaderSha256']);
        $t->same($documentEntry['compressedDataOffset'], $rawDocumentEntry['compressedDataOffset']);
        $t->same($documentEntry['compressedDataEnd'], $rawDocumentEntry['compressedDataEnd']);
        $t->same($documentEntry['compressedDataSha256'], $rawDocumentEntry['compressedDataSha256']);

        $t->same(30 + strlen('[Content_Types].xml'), $contentTypesEntry['localHeaderLength']);
        $t->same(hash('sha256', $contentTypesXml), $contentTypesEntry['compressedDataSha256']);
        foreach ($localFieldBytes as $field) {
            $t->same($manifestContentTypesEntry[$field], $contentTypesEntry[$field], "{$field} content types package manifest handoff");
            $t->same($contentTypesEntry[$field], $rawContentTypesEntry[$field], "{$field} content types raw manifest handoff");
        }
        $t->same(strlen('[Content_Types].xml'), $contentTypesEntry['localHeaderRawNameBytes']);
        $t->same(0, $contentTypesEntry['localHeaderExtraFieldBytes']);
        $t->same(hash('sha256', ''), $contentTypesEntry['localHeaderExtraFieldSha256']);
        $t->same(0, $contentTypesEntry['localHeaderReviewFieldBytes']);
        $t->same($contentTypesEntry['localHeaderLength'], $rawContentTypesEntry['localHeaderLength']);
        $t->same($contentTypesEntry['localHeaderSha256'], $rawContentTypesEntry['localHeaderSha256']);
        $t->same($contentTypesEntry['compressedDataSha256'], $rawContentTypesEntry['compressedDataSha256']);
    },
    'carries OPC ZIP local record and data descriptor source spans through manifest preflights' => static function (TestRunner $t) use ($buildOpcZipPackage): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
</Types>
XML;
        $documentXml = '<w:document><w:body><w:p>descriptor source spans</w:p></w:body></w:document>';
        $documentCompressed = gzdeflate($documentXml);
        $descriptorBytes = "PK\x07\x08" . pack(
            'VVV',
            (int) sprintf('%u', crc32($documentXml)),
            strlen($documentCompressed),
            strlen($documentXml)
        );
        $zip = $buildOpcZipPackage([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'method' => 0],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'method' => 0],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'method' => 8,
                'descriptor' => true,
            ],
        ]);

        $summary = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromString($zip));
        $rawSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }
        $rawEntries = [];
        foreach ($rawSummary['entries'] as $entry) {
            $rawEntries[$entry['entryName']] = $entry;
        }

        $documentEntry = $entries['word/document.xml'];
        $rawDocumentEntry = $rawEntries['word/document.xml'];
        foreach ([$documentEntry, $rawDocumentEntry] as $entry) {
            $t->same(30 + strlen('word/document.xml'), $entry['localHeaderLength']);
            $t->same($entry['localHeaderOffset'], $entry['localRecordOffset']);
            $t->same($entry['compressedDataOffset'], $entry['localHeaderOffset'] + $entry['localHeaderLength']);
            $t->same($entry['compressedDataEnd'], $entry['compressedDataOffset'] + strlen($documentCompressed));
            $t->same($entry['compressedDataEnd'], $entry['dataDescriptorOffset']);
            $t->same(strlen($descriptorBytes), $entry['dataDescriptorBytes']);
            $t->same($entry['dataDescriptorOffset'] + strlen($descriptorBytes), $entry['dataDescriptorEnd']);
            $t->same($entry['dataDescriptorEnd'], $entry['localRecordEnd']);
            $t->same(
                $entry['localHeaderLength'] + strlen($documentCompressed) + strlen($descriptorBytes),
                $entry['localRecordBytes']
            );
            $t->same(
                hash('sha256', substr($zip, $entry['localRecordOffset'], $entry['localRecordBytes'])),
                $entry['localRecordSha256']
            );
            $t->same(hash('sha256', $descriptorBytes), $entry['dataDescriptorSha256']);
            $t->same($entry['localRecordBytes'] + $entry['centralDirectoryRecordBytes'], $entry['sourceRecordBytes']);
        }

        $t->same($documentEntry['localRecordBytes'], $rawDocumentEntry['localRecordBytes']);
        $t->same($documentEntry['localRecordSha256'], $rawDocumentEntry['localRecordSha256']);
        $t->same($documentEntry['dataDescriptorBytes'], $rawDocumentEntry['dataDescriptorBytes']);
        $t->same($documentEntry['dataDescriptorSha256'], $rawDocumentEntry['dataDescriptorSha256']);
        $t->same($documentEntry['sourceRecordBytes'], $rawDocumentEntry['sourceRecordBytes']);
    },
    'carries OPC ZIP package source records through manifest preflights' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
</Types>
XML;
        $packageComment = 'opc package source review';
        $zip = ZipPackage::build([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => '<w:document/>', 'compressionMethod' => 0],
        ], $packageComment);
        $package = ZipPackage::fromString($zip);
        $zipManifest = $package->packageManifestPreflight();
        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $rawSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);

        foreach ([$summary, $rawSummary] as $manifest) {
            $t->same(true, $manifest['valid']);
            $t->same($zipManifest['packageSource'], $manifest['packageSource']);
            $t->same(strlen($zip), $manifest['archiveLength']);
            $t->same(hash('sha256', $zip), $manifest['archiveSha256']);
            $t->same($zipManifest['centralDirectoryOffset'], $manifest['centralDirectoryOffset']);
            $t->same($zipManifest['centralDirectoryBytes'], $manifest['centralDirectoryBytes']);
            $t->same($zipManifest['centralDirectoryEnd'], $manifest['centralDirectoryEnd']);
            $t->same(
                hash('sha256', substr($zip, $manifest['centralDirectoryOffset'], $manifest['centralDirectoryBytes'])),
                $manifest['centralDirectorySha256']
            );
            $t->same(null, $manifest['centralDirectoryToEocdGapOffset']);
            $t->same(0, $manifest['centralDirectoryToEocdGapBytes']);
            $t->same(null, $manifest['centralDirectoryToEocdGapSha256']);
            $t->same($zipManifest['endOfCentralDirectoryOffset'], $manifest['endOfCentralDirectoryOffset']);
            $t->same(22 + strlen($packageComment), $manifest['endOfCentralDirectoryBytes']);
            $t->same(strlen($zip), $manifest['endOfCentralDirectoryEnd']);
            $t->same(
                hash(
                    'sha256',
                    substr($zip, $manifest['endOfCentralDirectoryOffset'], $manifest['endOfCentralDirectoryBytes'])
                ),
                $manifest['endOfCentralDirectorySha256']
            );
            $t->same($zipManifest['packageCommentOffset'], $manifest['packageCommentOffset']);
            $t->same(strlen($packageComment), $manifest['packageCommentBytes']);
            $t->same(hash('sha256', $packageComment), $manifest['packageCommentSha256']);
            $t->same(true, $manifest['hasPackageComment']);
            $t->same(false, $manifest['hasCentralDirectorySignature']);
            $t->same(null, $manifest['centralDirectorySignatureOffset']);
            $t->same(null, $manifest['centralDirectorySignatureDataOffset']);
            $t->same(null, $manifest['centralDirectorySignatureEnd']);
            $t->same(0, $manifest['centralDirectorySignatureBytes']);
            $t->same(0, $manifest['centralDirectorySignatureRecordBytes']);
            $t->same('', $manifest['centralDirectorySignaturePreviewHex']);
            $t->same(0, $manifest['centralDirectorySignaturePreviewByteCount']);
            $t->same(null, $manifest['centralDirectorySignatureSha256']);
            $t->same(null, $manifest['centralDirectorySignatureLocation']);
            $t->same('not-present', $manifest['centralDirectorySignatureVerification']);
            $t->same('not-present', $manifest['centralDirectorySignatureByteExposurePolicy']);
            $t->same(false, $manifest['centralDirectorySignatureCanExposeBytes']);
        }

        $t->same($summary['packageSource'], $rawSummary['packageSource']);
        $t->same($summary['endOfCentralDirectorySha256'], $rawSummary['endOfCentralDirectorySha256']);
        $t->same($summary['packageCommentSha256'], $rawSummary['packageCommentSha256']);
    },
    'carries OPC ZIP central directory signature source policy through manifest preflights' => static function (TestRunner $t) use ($buildSignedOpcZipPackage): void {
        $zip = $buildSignedOpcZipPackage();
        $package = ZipPackage::fromString($zip);
        $zipManifest = $package->packageManifestPreflight();
        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $rawSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);
        $signatureData = 'opc-central-signature';
        $signatureFields = [
            'hasCentralDirectorySignature',
            'centralDirectorySignatureOffset',
            'centralDirectorySignatureDataOffset',
            'centralDirectorySignatureEnd',
            'centralDirectorySignatureBytes',
            'centralDirectorySignatureRecordBytes',
            'centralDirectorySignaturePreviewHex',
            'centralDirectorySignaturePreviewByteCount',
            'centralDirectorySignatureSha256',
            'centralDirectorySignatureLocation',
            'centralDirectorySignatureVerification',
            'centralDirectorySignatureByteExposurePolicy',
            'centralDirectorySignatureCanExposeBytes',
        ];

        foreach ([$summary, $rawSummary] as $manifest) {
            $t->same(true, $manifest['valid']);
            $t->same($zipManifest['packageSource'], $manifest['packageSource']);
            foreach ($signatureFields as $field) {
                $t->same($zipManifest[$field], $manifest[$field], "{$field} top-level manifest");
                $t->same($manifest[$field], $manifest['packageSource'][$field], "{$field} package source");
            }
        }

        $t->same(true, $summary['hasCentralDirectorySignature']);
        $t->same($summary['centralDirectoryEnd'], $summary['centralDirectorySignatureOffset']);
        $t->same($summary['centralDirectorySignatureOffset'] + 6, $summary['centralDirectorySignatureDataOffset']);
        $t->same($summary['endOfCentralDirectoryOffset'], $summary['centralDirectorySignatureEnd']);
        $t->same(strlen($signatureData), $summary['centralDirectorySignatureBytes']);
        $t->same(strlen($signatureData) + 6, $summary['centralDirectorySignatureRecordBytes']);
        $t->same(bin2hex(substr($signatureData, 0, 16)), $summary['centralDirectorySignaturePreviewHex']);
        $t->same(16, $summary['centralDirectorySignaturePreviewByteCount']);
        $t->same(hash('sha256', $signatureData), $summary['centralDirectorySignatureSha256']);
        $t->same('between-central-directory-and-eocd', $summary['centralDirectorySignatureLocation']);
        $t->same('not-performed-native-bounded-reader', $summary['centralDirectorySignatureVerification']);
        $t->same('central-directory-signature-metadata-only', $summary['centralDirectorySignatureByteExposurePolicy']);
        $t->same(false, $summary['centralDirectorySignatureCanExposeBytes']);
        $t->same($summary['packageSource'], $rawSummary['packageSource']);
    },
    'preflights raw ZIP central directory OPC manifest before package construction' => static function (TestRunner $t): void {
        $contentTypesXml = '<Types/>';
        $rootRelationshipsXml = '<Relationships/>';
        $documentXml = '<w:document/>';
        $documentRelationshipsXml = '<Relationships/>';
        $imageBytes = 'PNG';
        $embeddedPackageBytes = 'DOCX';
        $orphanRelationshipsXml = '<Relationships/>';
        $zip = ZipPackage::build([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => $documentXml, 'compressionMethod' => 0],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/media/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'word/media/image.png', 'data' => $imageBytes, 'compressionMethod' => 0],
            ['name' => 'word/embeddings/package1.docx', 'data' => $embeddedPackageBytes, 'compressionMethod' => 0],
            ['name' => 'word/_rels/orphan.xml.rels', 'data' => $orphanRelationshipsXml, 'compressionMethod' => 0],
        ]);

        $centralDirectory = ZipPackage::centralDirectorySizePreflight($zip);
        $documentEntry = null;
        foreach ($centralDirectory['entries'] as $entry) {
            if ($entry['name'] === 'word/document.xml') {
                $documentEntry = $entry;
                break;
            }
        }
        $t->true(is_array($documentEntry));
        $zip = substr_replace(
            $zip,
            'word/otherdoc.xml',
            $documentEntry['localHeaderOffset'] + 30,
            strlen('word/document.xml')
        );
        $t->throws(RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));

        $summary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }
        $relationshipParts = [];
        foreach ($summary['relationshipParts'] as $relationshipPart) {
            $relationshipParts[$relationshipPart['partName']] = $relationshipPart;
        }

        $totalBytes = strlen($contentTypesXml)
            + strlen($rootRelationshipsXml)
            + strlen($documentXml)
            + strlen($documentRelationshipsXml)
            + strlen($imageBytes)
            + strlen($embeddedPackageBytes)
            + strlen($orphanRelationshipsXml);

        $t->same(false, $summary['valid']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(true, $summary['zipCentralDirectoryValid']);
        $t->same([], $summary['centralDirectoryIssues']);
        $t->same(false, $summary['localHeaderNamesValid']);
        $t->same(['local-header-name-mismatch', 'local-header-decoded-name-mismatch'], $summary['localHeaderNameIssues']);
        $t->same(null, $summary['localHeaderNamePreflightError']);
        $t->same(1, $summary['localHeaderNameMismatchEntryCount']);
        $t->same('word/document.xml', $summary['localHeaderNameMismatchedEntries'][0]['centralName']);
        $t->same('word/otherdoc.xml', $summary['localHeaderNameMismatchedEntries'][0]['localName']);
        $t->same(8, $summary['declaredEntryCount']);
        $t->same(8, $summary['entryCount']);
        $t->same(7, $summary['fileEntryCount']);
        $t->same(1, $summary['directoryEntryCount']);
        $t->same(7, $summary['packagePartCount']);
        $t->same($totalBytes, $summary['compressedPayloadBytes']);
        $t->same($totalBytes, $summary['uncompressedPayloadBytes']);
        $t->same(1, $summary['contentTypesItemCount']);
        $t->same(3, $summary['relationshipPartCount']);
        $t->same(1, $summary['rootRelationshipPartCount']);
        $t->same(2, $summary['partRelationshipPartCount']);
        $t->same(1, $summary['orphanRelationshipPartCount']);
        $t->same(1, $summary['embeddedPackageCandidateCount']);
        $t->same(1, $summary['mediaPartCandidateCount']);
        $t->same(5, $summary['xmlPayloadPartCount']);
        $t->same(2, $summary['binaryPayloadPartCount']);
        $t->same([
            'local-header-decoded-name-mismatch' => 1,
            'local-header-name-mismatch' => 1,
            'orphan-relationship-part' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'local-header-name-mismatch',
            'local-header-decoded-name-mismatch',
            'orphan-relationship-part',
        ], $summary['issues']);
        $t->same([
            'local-header-decoded-name-mismatch' => ['word/document.xml'],
            'local-header-name-mismatch' => ['word/document.xml'],
            'orphan-relationship-part' => ['word/_rels/orphan.xml.rels'],
        ], $summary['entryNamesByIssue']);
        $t->same([
            'local-header-decoded-name-mismatch' => ['/word/document.xml'],
            'local-header-name-mismatch' => ['/word/document.xml'],
            'orphan-relationship-part' => ['/word/_rels/orphan.xml.rels'],
        ], $summary['partNamesByIssue']);
        $t->same([
            'content-types' => 1,
            'directory' => 1,
            'embedded-package-candidate' => 1,
            'media' => 1,
            'package-relationships' => 1,
            'part-relationships' => 2,
            'xml-part' => 1,
        ], $summary['roleCounts']);
        $t->same([
            'content-types+xml' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($contentTypesXml),
                'uncompressedBytes' => strlen($contentTypesXml),
            ],
            'directory' => [
                'entryCount' => 1,
                'compressedBytes' => 0,
                'uncompressedBytes' => 0,
            ],
            'embedded-package' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($embeddedPackageBytes),
                'uncompressedBytes' => strlen($embeddedPackageBytes),
            ],
            'media' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($imageBytes),
                'uncompressedBytes' => strlen($imageBytes),
            ],
            'relationships+xml' => [
                'entryCount' => 3,
                'compressedBytes' => strlen($rootRelationshipsXml) + strlen($documentRelationshipsXml) + strlen($orphanRelationshipsXml),
                'uncompressedBytes' => strlen($rootRelationshipsXml) + strlen($documentRelationshipsXml) + strlen($orphanRelationshipsXml),
            ],
            'xml' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($documentXml),
                'uncompressedBytes' => strlen($documentXml),
            ],
        ], $summary['byteCountsByHandoffKind']);

        $t->same('content-types', $entries['[Content_Types].xml']['role']);
        $t->same('content-types+xml', $entries['[Content_Types].xml']['handoffKind']);
        $t->same('package-relationships', $entries['_rels/.rels']['role']);
        $t->same('/', $entries['_rels/.rels']['relationshipSource']);
        $t->same('word/document.xml', $entries['word/document.xml']['centralName']);
        $t->same('word/otherdoc.xml', $entries['word/document.xml']['localHeaderName']);
        $t->same(false, $entries['word/document.xml']['localHeaderNameMatchesCentral']);
        $t->same(false, $entries['word/document.xml']['localHeaderDecodedNameMatchesCentral']);
        $t->same(true, $entries['word/document.xml']['localHeaderGeneralPurposeFlagsMatchCentral']);
        $t->same(['local-header-name-mismatch', 'local-header-decoded-name-mismatch'], $entries['word/document.xml']['localHeaderNameIssues']);
        $t->same(['local-header-name-mismatch', 'local-header-decoded-name-mismatch'], $entries['word/document.xml']['issues']);
        $t->same('part-relationships', $entries['word/_rels/document.xml.rels']['role']);
        $t->same('/word/document.xml', $entries['word/_rels/document.xml.rels']['relationshipSource']);
        $t->same(true, $entries['word/_rels/document.xml.rels']['relationshipSourceExists']);
        $t->same(false, $entries['word/_rels/orphan.xml.rels']['relationshipSourceExists']);
        $t->same(['orphan-relationship-part'], $entries['word/_rels/orphan.xml.rels']['issues']);
        $t->same('/word/orphan.xml', $relationshipParts['/word/_rels/orphan.xml.rels']['relationshipSource']);
        $t->same(false, $relationshipParts['/word/_rels/orphan.xml.rels']['relationshipSourceExists']);
    },
    'preflights raw OPC central directory local header name mismatches before package construction' => static function (TestRunner $t): void {
        $zip = ZipPackage::build([
            ['name' => '[Content_Types].xml', 'data' => '<Types/>', 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => '<w:document/>', 'compressionMethod' => 0],
        ]);

        $documentEntry = null;
        foreach (ZipPackage::centralDirectorySizePreflight($zip)['entries'] as $entry) {
            if ($entry['name'] === 'word/document.xml') {
                $documentEntry = $entry;
                break;
            }
        }
        $t->true(is_array($documentEntry));

        $zip = substr_replace(
            $zip,
            'word/otherdoc.xml',
            $documentEntry['localHeaderOffset'] + 30,
            strlen('word/document.xml')
        );
        $t->throws(RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));

        $summary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }

        $t->same(false, $summary['valid']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(true, $summary['zipCentralDirectoryValid']);
        $t->same([], $summary['centralDirectoryIssues']);
        $t->same(false, $summary['localHeaderNamesValid']);
        $t->same(['local-header-name-mismatch', 'local-header-decoded-name-mismatch'], $summary['localHeaderNameIssues']);
        $t->same(null, $summary['localHeaderNamePreflightError']);
        $t->same(1, $summary['localHeaderNameMismatchEntryCount']);
        $t->same([
            'local-header-decoded-name-mismatch' => 1,
            'local-header-name-mismatch' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'local-header-name-mismatch',
            'local-header-decoded-name-mismatch',
        ], $summary['issues']);
        $t->same([
            'local-header-decoded-name-mismatch' => ['word/document.xml'],
            'local-header-name-mismatch' => ['word/document.xml'],
        ], $summary['entryNamesByIssue']);
        $t->same([
            'local-header-decoded-name-mismatch' => ['/word/document.xml'],
            'local-header-name-mismatch' => ['/word/document.xml'],
        ], $summary['partNamesByIssue']);
        $t->same('word/document.xml', $summary['localHeaderNameMismatchedEntries'][0]['centralName']);
        $t->same('word/otherdoc.xml', $summary['localHeaderNameMismatchedEntries'][0]['localName']);

        $document = $entries['word/document.xml'];
        $t->same('word/document.xml', $document['centralName']);
        $t->same('word/otherdoc.xml', $document['localHeaderName']);
        $t->same(false, $document['localHeaderNameMatchesCentral']);
        $t->same(false, $document['localHeaderDecodedNameMatchesCentral']);
        $t->same(true, $document['localHeaderGeneralPurposeFlagsMatchCentral']);
        $t->same(['local-header-name-mismatch', 'local-header-decoded-name-mismatch'], $document['localHeaderNameIssues']);
        $t->same(['local-header-name-mismatch', 'local-header-decoded-name-mismatch'], $document['issues']);
        $t->same(false, $document['valid']);
        $t->same('xml-part', $document['role']);
        $t->same('xml', $document['handoffKind']);
    },
    'preflights raw OPC central directory local header metadata mismatches before package construction' => static function (TestRunner $t): void {
        $documentXml = '<w:document><w:p>local metadata mismatch</w:p></w:document>';
        $zip = ZipPackage::build([
            ['name' => '[Content_Types].xml', 'data' => '<Types/>', 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'compressionMethod' => 0],
            [
                'name' => 'word/document.xml',
                'data' => $documentXml,
                'compressionMethod' => 0,
                'modifiedDosTime' => 0x4a21,
                'modifiedDosDate' => 0x5b63,
            ],
        ]);

        $documentEntry = null;
        foreach (ZipPackage::centralDirectorySizePreflight($zip)['entries'] as $entry) {
            if ($entry['name'] === 'word/document.xml') {
                $documentEntry = $entry;
                break;
            }
        }
        $t->true(is_array($documentEntry));
        $localHeaderOffset = $documentEntry['localHeaderOffset'];
        $zip = substr_replace($zip, pack('v', 10), $localHeaderOffset + 4, 2);
        $zip = substr_replace($zip, pack('v', 8), $localHeaderOffset + 8, 2);
        $zip = substr_replace($zip, pack('v', 0x4a22), $localHeaderOffset + 10, 2);
        $zip = substr_replace($zip, pack('v', 0x5b64), $localHeaderOffset + 12, 2);
        $zip = substr_replace($zip, pack('V', 0x12345678), $localHeaderOffset + 14, 4);
        $zip = substr_replace($zip, pack('V', strlen($documentXml) + 2), $localHeaderOffset + 18, 4);
        $zip = substr_replace($zip, pack('V', strlen($documentXml) + 3), $localHeaderOffset + 22, 4);
        $t->throws(RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));

        $summary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }
        $document = $entries['word/document.xml'];
        $expectedIssues = [
            'local-header-version-needed-mismatch',
            'local-header-compression-method-mismatch',
            'local-header-modification-time-mismatch',
            'local-header-crc32-mismatch',
            'local-header-compressed-size-mismatch',
            'local-header-uncompressed-size-mismatch',
        ];

        $t->same(false, $summary['valid']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(true, $summary['zipCentralDirectoryValid']);
        $t->same([], $summary['centralDirectoryIssues']);
        $t->same(true, $summary['localHeaderNamesValid']);
        $t->same([], $summary['localHeaderNameIssues']);
        $t->same(null, $summary['localHeaderNamePreflightError']);
        $t->same(0, $summary['localHeaderNameMismatchEntryCount']);
        $t->same(false, $summary['localHeaderMetadataValid']);
        $t->same($expectedIssues, $summary['localHeaderMetadataIssues']);
        $t->same(null, $summary['localHeaderMetadataPreflightError']);
        $t->same(1, $summary['localHeaderMetadataMismatchEntryCount']);
        $t->same('word/document.xml', $summary['localHeaderMetadataMismatchedEntries'][0]['centralName']);
        $t->same($expectedIssues, $summary['localHeaderMetadataMismatchedEntries'][0]['issues']);
        $t->same([
            'local-header-compressed-size-mismatch' => 1,
            'local-header-compression-method-mismatch' => 1,
            'local-header-crc32-mismatch' => 1,
            'local-header-modification-time-mismatch' => 1,
            'local-header-uncompressed-size-mismatch' => 1,
            'local-header-version-needed-mismatch' => 1,
        ], $summary['issueCounts']);
        $t->same($expectedIssues, $summary['issues']);
        $t->same([
            'local-header-compressed-size-mismatch' => ['word/document.xml'],
            'local-header-compression-method-mismatch' => ['word/document.xml'],
            'local-header-crc32-mismatch' => ['word/document.xml'],
            'local-header-modification-time-mismatch' => ['word/document.xml'],
            'local-header-uncompressed-size-mismatch' => ['word/document.xml'],
            'local-header-version-needed-mismatch' => ['word/document.xml'],
        ], $summary['entryNamesByIssue']);
        $t->same([
            'local-header-compressed-size-mismatch' => ['/word/document.xml'],
            'local-header-compression-method-mismatch' => ['/word/document.xml'],
            'local-header-crc32-mismatch' => ['/word/document.xml'],
            'local-header-modification-time-mismatch' => ['/word/document.xml'],
            'local-header-uncompressed-size-mismatch' => ['/word/document.xml'],
            'local-header-version-needed-mismatch' => ['/word/document.xml'],
        ], $summary['partNamesByIssue']);

        $t->same('word/document.xml', $document['centralName']);
        $t->same('word/document.xml', $document['localHeaderName']);
        $t->same(true, $document['localHeaderNameMatchesCentral']);
        $t->same(true, $document['localHeaderDecodedNameMatchesCentral']);
        $t->same(true, $document['localHeaderGeneralPurposeFlagsMatchCentral']);
        $t->same([], $document['localHeaderNameIssues']);
        $t->same(false, $document['localHeaderMetadataMatchesCentral']);
        $t->same(true, $document['localHeaderHasMetadataMismatch']);
        $t->same($expectedIssues, $document['localHeaderMetadataIssues']);
        $t->same(20, $document['centralVersionNeededToExtract']);
        $t->same(10, $document['localVersionNeededToExtract']);
        $t->same(0, $document['centralCompressionMethod']);
        $t->same(8, $document['localCompressionMethod']);
        $t->same(0x4a21, $document['centralModifiedDosTime']);
        $t->same(0x4a22, $document['localModifiedDosTime']);
        $t->same(0x5b63, $document['centralModifiedDosDate']);
        $t->same(0x5b64, $document['localModifiedDosDate']);
        $t->same((int) sprintf('%u', crc32($documentXml)), $document['centralCrc32']);
        $t->same(0x12345678, $document['localCrc32']);
        $t->same(sprintf('%08x', crc32($documentXml)), $document['centralCrc32Hex']);
        $t->same('12345678', $document['localCrc32Hex']);
        $t->same(strlen($documentXml), $document['centralCompressedSize']);
        $t->same(strlen($documentXml) + 2, $document['localCompressedSize']);
        $t->same(strlen($documentXml), $document['centralUncompressedSize']);
        $t->same(strlen($documentXml) + 3, $document['localUncompressedSize']);
        $t->same(false, $document['usesDataDescriptor']);
        $t->same(null, $document['hasZeroLocalHeaderPlaceholders']);
        $t->same($expectedIssues, $document['issues']);
        $t->same(false, $document['valid']);
        $t->same($summary['localHeaderMetadata'], ZipPackage::localHeaderMetadataPreflight($zip));
    },
    'preflights raw OPC central directory manifest ZIP64 byte sentinels before package construction' => static function (TestRunner $t): void {
        $contentTypesXml = '<Types/>';
        $rootRelationshipsXml = '<Relationships/>';
        $documentXml = '<w:document/>';
        $imageBytes = 'PNGDATA';
        $zip = ZipPackage::build([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => $documentXml, 'compressionMethod' => 0],
            ['name' => 'word/media/image.png', 'data' => $imageBytes, 'compressionMethod' => 0],
        ]);

        $mediaCentralDirectoryOffset = null;
        foreach (ZipPackage::centralDirectorySizePreflight($zip)['entries'] as $entry) {
            if ($entry['name'] === 'word/media/image.png') {
                $mediaCentralDirectoryOffset = $entry['centralDirectoryOffset'];
                break;
            }
        }
        $t->true(is_int($mediaCentralDirectoryOffset));

        $zip = substr_replace($zip, pack('V', 0xffffffff), $mediaCentralDirectoryOffset + 20, 4);
        $zip = substr_replace($zip, pack('V', 0xffffffff), $mediaCentralDirectoryOffset + 24, 4);
        $t->throws(RuntimeException::class, static fn (): ZipPackage => ZipPackage::fromString($zip));

        $summary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }

        $knownBytes = strlen($contentTypesXml) + strlen($rootRelationshipsXml) + strlen($documentXml);

        $t->same(false, $summary['valid']);
        $t->same(false, $summary['zipCentralDirectoryValid']);
        $t->same(false, $summary['byteCountsAreExact']);
        $t->same(['central-directory-size-unknown'], $summary['centralDirectoryIssues']);
        $t->same([
            'central-directory-size-unknown',
            'zip64-size-or-offset-sentinel',
        ], $summary['issues']);
        $t->same([
            'central-directory-size-unknown' => 1,
            'zip64-size-or-offset-sentinel' => 1,
        ], $summary['issueCounts']);
        $t->same(['zip64-size-or-offset-sentinel' => ['word/media/image.png']], $summary['entryNamesByIssue']);
        $t->same(['zip64-size-or-offset-sentinel' => ['/word/media/image.png']], $summary['partNamesByIssue']);
        $t->same(1, $summary['unknownByteCountEntryCount']);
        $t->same($knownBytes, $summary['compressedPayloadBytes']);
        $t->same($knownBytes, $summary['uncompressedPayloadBytes']);
        $t->same([
            'entryCount' => 1,
            'compressedBytes' => 0,
            'uncompressedBytes' => 0,
        ], $summary['byteCountsByHandoffKind']['media']);
        $t->same('_rels/.rels', $summary['largestPayloadEntry']['entryName']);

        $mediaEntry = $entries['word/media/image.png'];
        $t->same(false, $mediaEntry['byteCountsAreExact']);
        $t->same(null, $mediaEntry['exactCompressedSize']);
        $t->same(null, $mediaEntry['exactUncompressedSize']);
        $t->same(true, $mediaEntry['hasZip64SizeSentinel']);
        $t->same(['zip64-size-or-offset-sentinel'], $mediaEntry['issues']);
        $t->same(false, $mediaEntry['valid']);
        $t->same(0xffffffff, $mediaEntry['compressedSize']);
        $t->same(0xffffffff, $mediaEntry['uncompressedSize']);
        $t->same($summary['unknownByteCountEntries'][0]['entryName'], $mediaEntry['entryName']);
        $t->same(['zip64-size-or-offset-sentinel'], $summary['unknownByteCountEntries'][0]['issues']);
    },
    'carries OPC ZIP local header order provenance through manifest preflights' => static function (TestRunner $t) use ($buildOpcZipPackage): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
</Types>
XML;
        $zip = $buildOpcZipPackage([
            [
                'name' => '[Content_Types].xml',
                'data' => $contentTypesXml,
                'centralIndex' => 2,
            ],
            [
                'name' => '_rels/.rels',
                'data' => '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"/>',
                'centralIndex' => 0,
            ],
            [
                'name' => 'word/document.xml',
                'data' => '<w:document/>',
                'centralIndex' => 1,
            ],
        ]);

        $packageSummary = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromString($zip));
        $rawSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zip);
        foreach ([$packageSummary, $rawSummary] as $summary) {
            $entries = [];
            foreach ($summary['entries'] as $entry) {
                $entries[$entry['entryName']] = $entry;
            }

            $t->same(true, $summary['valid']);
            $t->same(['_rels/.rels', 'word/document.xml', '[Content_Types].xml'], $summary['localHeaderOrder']['centralDirectoryOrderNames']);
            $t->same(['[Content_Types].xml', '_rels/.rels', 'word/document.xml'], $summary['localHeaderOrder']['localHeaderOrderNames']);
            $t->same(true, $summary['localHeaderOrder']['hasCentralDirectoryOrderMismatch']);
            $t->same(3, $summary['localHeaderOrder']['mismatchedEntryCount']);
            $t->same(['_rels/.rels', 'word/document.xml', '[Content_Types].xml'], array_column($summary['localHeaderOrder']['mismatchedEntries'], 'name'));
            $t->same([1, 2, 0], array_column($summary['localHeaderOrder']['mismatchedEntries'], 'localHeaderOrder'));

            $t->same(1, $entries['_rels/.rels']['localHeaderOrder']);
            $t->same('[Content_Types].xml', $entries['_rels/.rels']['localHeaderNameAtCentralDirectoryIndex']);
            $t->same('word/document.xml', $entries['_rels/.rels']['centralDirectoryNameAtLocalHeaderOrder']);
            $t->same(false, $entries['_rels/.rels']['matchesCentralDirectoryOrder']);

            $t->same(2, $entries['word/document.xml']['localHeaderOrder']);
            $t->same('_rels/.rels', $entries['word/document.xml']['localHeaderNameAtCentralDirectoryIndex']);
            $t->same('[Content_Types].xml', $entries['word/document.xml']['centralDirectoryNameAtLocalHeaderOrder']);
            $t->same(false, $entries['word/document.xml']['matchesCentralDirectoryOrder']);

            $t->same(0, $entries['[Content_Types].xml']['localHeaderOrder']);
            $t->same('word/document.xml', $entries['[Content_Types].xml']['localHeaderNameAtCentralDirectoryIndex']);
            $t->same('_rels/.rels', $entries['[Content_Types].xml']['centralDirectoryNameAtLocalHeaderOrder']);
            $t->same(false, $entries['[Content_Types].xml']['matchesCentralDirectoryOrder']);
        }

        $t->same($packageSummary['localHeaderOrder'], $rawSummary['localHeaderOrder']);
    },
    'preflights OPC ZIP entry manifest equivalent package part name collisions before XML handoff' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
</Types>
XML;
        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>'],
            ['name' => 'word/document.xml', 'data' => '<w:document/>'],
            ['name' => 'word/Document.xml', 'data' => '<w:document/>'],
            ['name' => 'word/media/Hero.PNG', 'data' => 'PNG'],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
        ]);

        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }

        $t->same(false, $summary['valid']);
        $t->same(false, $summary['isSupportedByBoundedReader']);
        $t->same(2, $summary['equivalentPackagePartNameGroupCount']);
        $t->same(4, $summary['equivalentPackagePartNameEntryCount']);
        $t->same(['equivalent-part-name-case-collision' => 4], $summary['issueCounts']);
        $t->same(['equivalent-part-name-case-collision'], $summary['issues']);
        $t->same([
            [
                'equivalenceKey' => '/word/document.xml',
                'partNames' => ['/word/Document.xml', '/word/document.xml'],
                'entryNames' => ['word/Document.xml', 'word/document.xml'],
            ],
            [
                'equivalenceKey' => '/word/media/hero.png',
                'partNames' => ['/word/media/Hero.PNG', '/word/media/hero.png'],
                'entryNames' => ['word/media/Hero.PNG', 'word/media/hero.png'],
            ],
        ], $summary['equivalentPackagePartNameGroups']);

        $t->same('/word/document.xml', $entries['word/document.xml']['equivalenceKey']);
        $t->same(['/word/Document.xml', '/word/document.xml'], $entries['word/document.xml']['equivalentPartNames']);
        $t->same(['equivalent-part-name-case-collision'], $entries['word/document.xml']['issues']);
        $t->same(false, $entries['word/document.xml']['valid']);
        $t->same('/word/media/hero.png', $entries['word/media/Hero.PNG']['equivalenceKey']);
        $t->same(['/word/media/Hero.PNG', '/word/media/hero.png'], $entries['word/media/Hero.PNG']['equivalentPartNames']);
        $t->same(['equivalent-part-name-case-collision'], $entries['word/media/Hero.PNG']['issues']);
        $t->same([], $entries['_rels/.rels']['equivalentPartNames']);
        $t->same(true, $entries['_rels/.rels']['valid']);
    },
    'summarizes OPC ZIP entry manifest handoff byte buckets before XML package handoff' => static function (TestRunner $t): void {
        $contentTypesXml = '<Types/>';
        $rootRelationshipsXml = '<Relationships/>';
        $documentXml = '<w:document/>';
        $documentRelationshipsXml = '<Relationships/>';
        $imageBytes = 'PNGDATA';
        $embeddedPackageBytes = 'DOCXDATA';
        $blockedRelationshipsXml = '<rels-block/>';
        $customXml = '<audit/>';
        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => $documentXml, 'compressionMethod' => 0],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/media/', 'data' => ''],
            ['name' => 'word/media/image.png', 'data' => $imageBytes, 'compressionMethod' => 0],
            ['name' => 'word/embeddings/package1.docx', 'data' => $embeddedPackageBytes, 'compressionMethod' => 0],
            ['name' => 'word/_rels/media/document.xml.rels', 'data' => $blockedRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'customXml/item1.xml', 'data' => $customXml, 'compressionMethod' => 0],
        ]);

        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }

        $relationshipsBytes = strlen($rootRelationshipsXml) + strlen($documentRelationshipsXml);
        $xmlBytes = strlen($documentXml) + strlen($customXml);
        $totalFileBytes = strlen($contentTypesXml)
            + $relationshipsBytes
            + $xmlBytes
            + strlen($imageBytes)
            + strlen($embeddedPackageBytes)
            + strlen($blockedRelationshipsXml);

        $t->same(false, $summary['valid']);
        $t->same(9, $summary['entryCount']);
        $t->same(8, $summary['fileEntryCount']);
        $t->same(1, $summary['directoryEntryCount']);
        $t->same($totalFileBytes, $summary['compressedPayloadBytes']);
        $t->same($totalFileBytes, $summary['uncompressedPayloadBytes']);
        $t->same($totalFileBytes, $summary['fileCompressedBytes']);
        $t->same($totalFileBytes, $summary['fileUncompressedBytes']);
        $t->same(0, $summary['directoryCompressedBytes']);
        $t->same(0, $summary['directoryUncompressedBytes']);
        $t->same([
            'blocked' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($blockedRelationshipsXml),
                'uncompressedBytes' => strlen($blockedRelationshipsXml),
            ],
            'content-types+xml' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($contentTypesXml),
                'uncompressedBytes' => strlen($contentTypesXml),
            ],
            'directory' => [
                'entryCount' => 1,
                'compressedBytes' => 0,
                'uncompressedBytes' => 0,
            ],
            'embedded-package' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($embeddedPackageBytes),
                'uncompressedBytes' => strlen($embeddedPackageBytes),
            ],
            'media' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($imageBytes),
                'uncompressedBytes' => strlen($imageBytes),
            ],
            'relationships+xml' => [
                'entryCount' => 2,
                'compressedBytes' => $relationshipsBytes,
                'uncompressedBytes' => $relationshipsBytes,
            ],
            'xml' => [
                'entryCount' => 2,
                'compressedBytes' => $xmlBytes,
                'uncompressedBytes' => $xmlBytes,
            ],
        ], $summary['byteCountsByHandoffKind']);
        $t->same([
            'content-types' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($contentTypesXml),
                'uncompressedBytes' => strlen($contentTypesXml),
            ],
            'directory' => [
                'entryCount' => 1,
                'compressedBytes' => 0,
                'uncompressedBytes' => 0,
            ],
            'embedded-package-candidate' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($embeddedPackageBytes),
                'uncompressedBytes' => strlen($embeddedPackageBytes),
            ],
            'invalid-relationship-part' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($blockedRelationshipsXml),
                'uncompressedBytes' => strlen($blockedRelationshipsXml),
            ],
            'media' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($imageBytes),
                'uncompressedBytes' => strlen($imageBytes),
            ],
            'package-relationships' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($rootRelationshipsXml),
                'uncompressedBytes' => strlen($rootRelationshipsXml),
            ],
            'part-relationships' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($documentRelationshipsXml),
                'uncompressedBytes' => strlen($documentRelationshipsXml),
            ],
            'xml-part' => [
                'entryCount' => 2,
                'compressedBytes' => $xmlBytes,
                'uncompressedBytes' => $xmlBytes,
            ],
        ], $summary['byteCountsByRole']);
        $t->same([
            'entryName' => '_rels/.rels',
            'partName' => '/_rels/.rels',
            'role' => 'package-relationships',
            'handoffKind' => 'relationships+xml',
            'compressionMethod' => 0,
            'compressionMethodName' => 'stored',
            'compressedSize' => strlen($rootRelationshipsXml),
            'uncompressedSize' => strlen($rootRelationshipsXml),
        ], $summary['largestPayloadEntry']);
        $t->same('blocked', $entries['word/_rels/media/document.xml.rels']['handoffKind']);
        $t->same(['invalid-relationship-part-name'], $entries['word/_rels/media/document.xml.rels']['issues']);
        $t->same('directory', $entries['word/media/']['handoffKind']);
    },
    'summarizes OPC ZIP manifest package part extensions before XML package handoff' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="svg" ContentType="image/svg+xml"/>
  <Override PartName="/word/embeddings/source.DOCX" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/customXml/item1" ContentType="application/xml"/>
</Types>
XML;
        $parts = [
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"/>', 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => '<w:document/>', 'compressionMethod' => 0],
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"/>', 'compressionMethod' => 0],
            ['name' => 'word/media/image.PNG', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'word/media/vector.svg', 'data' => '<svg/>', 'compressionMethod' => 0],
            ['name' => 'word/embeddings/source.DOCX', 'data' => 'DOCXDATA', 'compressionMethod' => 0],
            ['name' => 'customXml/item1', 'data' => '<audit/>', 'compressionMethod' => 0],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>', 'compressionMethod' => 0],
        ];

        $package = ZipPackage::fromParts($parts);
        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $rawSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest(ZipPackage::build($parts));
        $extensionSummaries = [];
        foreach ($summary['packagePartExtensionSummaries'] as $extensionSummary) {
            $extensionSummaries[$extensionSummary['extensionKey']] = $extensionSummary;
        }
        $rawExtensionSummaries = [];
        foreach ($rawSummary['packagePartExtensionSummaries'] as $extensionSummary) {
            $rawExtensionSummaries[$extensionSummary['extensionKey']] = $extensionSummary;
        }

        $t->same(true, $summary['valid']);
        $t->same(true, $rawSummary['valid']);
        $t->same(1, $summary['extensionlessPackagePartCount']);
        $t->same(1, $rawSummary['extensionlessPackagePartCount']);
        $t->same([
            '(none)' => 1,
            'docx' => 1,
            'png' => 1,
            'rels' => 2,
            'svg' => 1,
            'xml' => 3,
        ], $summary['packagePartExtensionCounts']);
        $t->same($summary['packagePartExtensionCounts'], $rawSummary['packagePartExtensionCounts']);
        $t->same([
            'customXml/item1',
        ], $summary['entryNamesByPackagePartExtension']['(none)']);
        $t->same($summary['entryNamesByPackagePartExtension'], $rawSummary['entryNamesByPackagePartExtension']);
        $t->same([
            '[Content_Types].xml',
            'docProps/core.xml',
            'word/document.xml',
        ], $summary['entryNamesByPackagePartExtension']['xml']);
        $t->same(null, $extensionSummaries['(none)']['extension']);
        $t->same([
            'xml-part' => 1,
        ], $extensionSummaries['(none)']['roleCounts']);
        $t->same([
            'xml' => 1,
        ], $extensionSummaries['(none)']['handoffKindCounts']);
        $t->same(null, $rawExtensionSummaries['(none)']['extension']);
        $t->same([
            'binary-part' => 1,
        ], $rawExtensionSummaries['(none)']['roleCounts']);
        $t->same([
            'binary' => 1,
        ], $rawExtensionSummaries['(none)']['handoffKindCounts']);
        $t->same([
            'content-types' => 1,
            'document-properties' => 1,
            'xml-part' => 1,
        ], $extensionSummaries['xml']['roleCounts']);
        $t->same([
            'content-types+xml' => 1,
            'xml' => 2,
        ], $extensionSummaries['xml']['handoffKindCounts']);
        $t->same($extensionSummaries['xml'], $rawExtensionSummaries['xml']);
        $t->same([
            'embedded-package-candidate' => 1,
        ], $extensionSummaries['docx']['roleCounts']);
        $t->same($extensionSummaries['docx'], $rawExtensionSummaries['docx']);
        $t->same(2, $summary['roleCounts']['media']);
        $t->same(2, $rawSummary['roleCounts']['media']);
    },
    'summarizes OPC ZIP manifest compression provenance before XML package handoff' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
</Types>
XML;
        $rootRelationshipsXml = '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"/>';
        $documentXml = str_repeat('<w:p/>', 12);
        $documentRelationshipsXml = '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"/>';
        $imageBytes = str_repeat('PNG', 200);
        $rawBytes = 'RAW';
        $parts = [
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => $documentXml],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/media/image.png', 'data' => $imageBytes],
            ['name' => 'word/media/raw.bin', 'data' => $rawBytes, 'compressionMethod' => 0],
        ];

        $summary = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromParts($parts));
        $centralSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest(ZipPackage::build($parts));
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }

        $t->same(true, $summary['valid']);
        $t->same(true, $centralSummary['valid']);
        $t->same([
            'deflated' => 2,
            'stored' => 4,
        ], $summary['compressionMethodCounts']);
        $t->same($summary['compressionMethodCounts'], $centralSummary['compressionMethodCounts']);
        $t->same([
            'deflated' => ['word/document.xml', 'word/media/image.png'],
            'stored' => [
                '[Content_Types].xml',
                '_rels/.rels',
                'word/_rels/document.xml.rels',
                'word/media/raw.bin',
            ],
        ], $summary['entryNamesByCompressionMethod']);
        $t->same($summary['entryNamesByCompressionMethod'], $centralSummary['entryNamesByCompressionMethod']);
        $t->same([
            'binary-part' => ['stored'],
            'content-types' => ['stored'],
            'media' => ['deflated'],
            'package-relationships' => ['stored'],
            'part-relationships' => ['stored'],
            'xml-part' => ['deflated'],
        ], $summary['compressionMethodNamesByRole']);
        $t->same($summary['compressionMethodNamesByRole'], $centralSummary['compressionMethodNamesByRole']);
        $t->same([
            'binary' => ['stored'],
            'content-types+xml' => ['stored'],
            'media' => ['deflated'],
            'relationships+xml' => ['stored'],
            'xml' => ['deflated'],
        ], $summary['compressionMethodNamesByHandoffKind']);
        $t->same($summary['compressionMethodNamesByHandoffKind'], $centralSummary['compressionMethodNamesByHandoffKind']);
        $t->same('stored', $entries['_rels/.rels']['compressionMethodName']);
        $t->same('deflated', $entries['word/document.xml']['compressionMethodName']);
        $t->same('word/media/image.png', $summary['largestPayloadEntry']['entryName']);
        $t->same(8, $summary['largestPayloadEntry']['compressionMethod']);
        $t->same('deflated', $summary['largestPayloadEntry']['compressionMethodName']);
        $t->same($summary['largestPayloadEntry'], $centralSummary['largestPayloadEntry']);
    },
    'summarizes OPC ZIP source record manifest before XML package handoff' => static function (TestRunner $t) use ($buildOpcZipPackage): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
</Types>
XML;
        $documentXml = str_repeat('<w:p/>', 9);
        $imageBytes = str_repeat('PNG', 32);
        $zipBytes = $buildOpcZipPackage([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"/>'],
            ['name' => 'word/document.xml', 'data' => $documentXml, 'method' => 8],
            [
                'name' => 'word/media/review.png',
                'data' => $imageBytes,
                'descriptor' => true,
                'descriptorSignature' => true,
            ],
        ]);

        $package = ZipPackage::fromString($zipBytes);
        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $centralSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zipBytes);
        $packageManifest = $package->packageManifestPreflight();
        $manifest = $summary['zipSourceRecordManifest'];
        $centralManifest = $centralSummary['zipSourceRecordManifest'];
        $manifestEntries = [];
        foreach ($manifest['entries'] as $entry) {
            $manifestEntries[$entry['entryName']] = $entry;
        }

        $payload = $manifest;
        unset($payload['manifestSha256']);
        $expectedHash = hash('sha256', json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
        $expectedLocalRecordBytes = array_sum(array_column($manifest['entries'], 'localRecordBytes'));
        $expectedCentralDirectoryRecordBytes = array_sum(array_column($manifest['entries'], 'centralDirectoryRecordBytes'));
        $expectedSourceRecordBytes = array_sum(array_column($manifest['entries'], 'sourceRecordBytes'));

        $t->same(true, $summary['valid']);
        $t->same(true, $centralSummary['valid']);
        $t->same($packageManifest['manifestSha256'], $summary['zipPackageManifestSha256']);
        $t->same('zip-opc-source-record-manifest-v1', $summary['zipSourceRecordManifestVersion']);
        $t->same('zip-opc-source-record-manifest-v1', $manifest['manifestVersion']);
        $t->same($expectedHash, $summary['zipSourceRecordManifestSha256']);
        $t->same($expectedHash, $manifest['manifestSha256']);
        $t->same($manifest, $centralManifest);
        $t->same($summary['zipSourceRecordManifestSha256'], $centralSummary['zipSourceRecordManifestSha256']);
        $t->same(true, $summary['zipSourceRecordByteCountsAreExact']);
        $t->same(true, $centralSummary['zipSourceRecordByteCountsAreExact']);
        $t->same(4, $summary['zipSourceRecordEntryCount']);
        $t->same(0, $summary['zipSourceRecordUnknownEntryCount']);
        $t->same($expectedLocalRecordBytes, $summary['zipSourceRecordLocalRecordBytes']);
        $t->same($expectedLocalRecordBytes, $summary['zipSourceRecordKnownLocalRecordBytes']);
        $t->same($expectedCentralDirectoryRecordBytes, $summary['zipSourceRecordCentralDirectoryRecordBytes']);
        $t->same($expectedCentralDirectoryRecordBytes, $summary['zipSourceRecordKnownCentralDirectoryRecordBytes']);
        $t->same($expectedSourceRecordBytes, $summary['zipSourceRecordBytes']);
        $t->same($expectedSourceRecordBytes, $summary['zipSourceRecordKnownBytes']);
        $t->same($summary['zipSourceRecordBytes'], $summary['zipSourceRecordLocalRecordBytes'] + $summary['zipSourceRecordCentralDirectoryRecordBytes']);
        $t->same(16, $summary['zipSourceRecordDataDescriptorBytes']);
        $t->same(1, $summary['zipSourceRecordDataDescriptorEntryCount']);
        $t->same($summary['zipSourceRecordManifest'], $centralSummary['zipSourceRecordManifest']);

        $documentEntry = $manifestEntries['word/document.xml'];
        $imageEntry = $manifestEntries['word/media/review.png'];
        $t->same('xml-part', $documentEntry['role']);
        $t->same('media', $imageEntry['role']);
        $t->same(false, $documentEntry['usesDataDescriptor']);
        $t->same(true, $imageEntry['usesDataDescriptor']);
        $t->same(16, $imageEntry['dataDescriptorBytes']);
        $t->same($imageEntry['localRecordBytes'] + $imageEntry['centralDirectoryRecordBytes'], $imageEntry['sourceRecordBytes']);
        $t->same(hash('sha256', substr($zipBytes, $documentEntry['localRecordOffset'], $documentEntry['localRecordBytes'])), $documentEntry['localRecordSha256']);
        $t->same(hash('sha256', substr($zipBytes, $imageEntry['dataDescriptorOffset'], $imageEntry['dataDescriptorBytes'])), $imageEntry['dataDescriptorSha256']);
        $t->same(hash('sha256', substr($zipBytes, $imageEntry['centralDirectoryRecordOffset'], $imageEntry['centralDirectoryRecordBytes'])), $imageEntry['centralDirectoryRecordSha256']);
        $t->same([], $documentEntry['sourceRecordIssues']);
        $t->same([], $imageEntry['sourceRecordIssues']);
    },
    'summarizes OPC ZIP manifest general purpose flag provenance before XML package handoff' => static function (TestRunner $t) use ($buildOpcZipPackage): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;
        $zipBytes = $buildOpcZipPackage([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"/>'],
            [
                'name' => 'word/document.xml',
                'data' => str_repeat('<w:p/>', 8),
                'method' => 8,
                'flags' => 0x0802,
            ],
            [
                'name' => 'word/media/review.png',
                'data' => str_repeat('PNG', 12),
                'flags' => 0x0800,
                'descriptor' => true,
                'descriptorSignature' => false,
            ],
        ]);

        $summary = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromString($zipBytes));
        $centralSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest($zipBytes);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }
        $centralEntries = [];
        foreach ($centralSummary['entries'] as $entry) {
            $centralEntries[$entry['entryName']] = $entry;
        }

        $expectedFlagCounts = [
            '0x0800' => 2,
            '0x0802' => 1,
            '0x0808' => 1,
        ];
        $expectedEntryNamesByFlag = [
            '0x0800' => ['[Content_Types].xml', '_rels/.rels'],
            '0x0802' => ['word/document.xml'],
            '0x0808' => ['word/media/review.png'],
        ];
        $expectedIssueCounts = [
            'data-descriptor-entry' => 1,
            'deflate-option-flags' => 1,
        ];

        $t->same(true, $summary['valid']);
        $t->same(true, $centralSummary['valid']);
        $t->same($expectedFlagCounts, $summary['generalPurposeFlagCounts']);
        $t->same($expectedFlagCounts, $centralSummary['generalPurposeFlagCounts']);
        $t->same($expectedEntryNamesByFlag, $summary['entryNamesByGeneralPurposeFlag']);
        $t->same($expectedEntryNamesByFlag, $centralSummary['entryNamesByGeneralPurposeFlag']);
        $t->same($expectedIssueCounts, $summary['generalPurposeFlagIssueCounts']);
        $t->same($expectedIssueCounts, $centralSummary['generalPurposeFlagIssueCounts']);
        $t->same([
            'data-descriptor-entry' => ['word/media/review.png'],
            'deflate-option-flags' => ['word/document.xml'],
        ], $summary['entryNamesByGeneralPurposeFlagIssue']);
        $t->same($summary['entryNamesByGeneralPurposeFlagIssue'], $centralSummary['entryNamesByGeneralPurposeFlagIssue']);
        $t->same([
            'content-types' => ['utf-8-names'],
            'media' => ['data-descriptor', 'utf-8-names'],
            'package-relationships' => ['utf-8-names'],
            'xml-part' => ['deflate-maximum-compression', 'utf-8-names'],
        ], $summary['generalPurposeFlagNamesByRole']);
        $t->same($summary['generalPurposeFlagNamesByRole'], $centralSummary['generalPurposeFlagNamesByRole']);
        $t->same([
            'content-types+xml' => ['utf-8-names'],
            'media' => ['data-descriptor', 'utf-8-names'],
            'relationships+xml' => ['utf-8-names'],
            'xml' => ['deflate-maximum-compression', 'utf-8-names'],
        ], $summary['generalPurposeFlagNamesByHandoffKind']);
        $t->same($summary['generalPurposeFlagNamesByHandoffKind'], $centralSummary['generalPurposeFlagNamesByHandoffKind']);

        $t->same(0x0802, $entries['word/document.xml']['generalPurposeFlags']);
        $t->same('0x0802', $entries['word/document.xml']['generalPurposeFlagHex']);
        $t->same(['deflate-maximum-compression', 'utf-8-names'], $entries['word/document.xml']['generalPurposeFlagNames']);
        $t->same(0, $entries['word/document.xml']['generalPurposeUnsupportedFlagBits']);
        $t->same(true, $entries['word/document.xml']['generalPurposeFlagsSupportedByReader']);
        $t->same(true, $entries['word/document.xml']['zipUsesUtf8Names']);
        $t->same(false, $entries['word/document.xml']['zipUsesDataDescriptorFlag']);
        $t->same(0x0002, $entries['word/document.xml']['zipDeflateOptionFlags']);
        $t->same('deflate-maximum-compression', $entries['word/document.xml']['zipDeflateOptionName']);
        $t->same(true, $entries['word/document.xml']['zipRequiresGeneralPurposeFlagReview']);
        $t->same(['deflate-option-flags'], $entries['word/document.xml']['generalPurposeFlagIssues']);

        $t->same(0x0808, $entries['word/media/review.png']['generalPurposeFlags']);
        $t->same(['data-descriptor', 'utf-8-names'], $entries['word/media/review.png']['generalPurposeFlagNames']);
        $t->same(true, $entries['word/media/review.png']['zipUsesDataDescriptorFlag']);
        $t->same(0, $entries['word/media/review.png']['zipDeflateOptionFlags']);
        $t->same(null, $entries['word/media/review.png']['zipDeflateOptionName']);
        $t->same(['data-descriptor-entry'], $entries['word/media/review.png']['generalPurposeFlagIssues']);

        $t->same(0x0800, $entries['[Content_Types].xml']['generalPurposeFlags']);
        $t->same(['utf-8-names'], $entries['[Content_Types].xml']['generalPurposeFlagNames']);
        $t->same(false, $entries['[Content_Types].xml']['zipRequiresGeneralPurposeFlagReview']);
        $t->same([], $entries['[Content_Types].xml']['generalPurposeFlagIssues']);

        $t->same($entries['word/document.xml']['generalPurposeFlagNames'], $centralEntries['word/document.xml']['generalPurposeFlagNames']);
        $t->same($entries['word/document.xml']['generalPurposeFlagIssues'], $centralEntries['word/document.xml']['generalPurposeFlagIssues']);
        $t->same($entries['word/media/review.png']['generalPurposeFlagNames'], $centralEntries['word/media/review.png']['generalPurposeFlagNames']);
        $t->same($entries['word/media/review.png']['generalPurposeFlagIssues'], $centralEntries['word/media/review.png']['generalPurposeFlagIssues']);
    },
    'summarizes OPC ZIP manifest largest payload entries before XML package handoff' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Default Extension="png" ContentType="image/png"/><Default Extension="bin" ContentType="application/octet-stream"/></Types>
XML;
        $largeImage = str_repeat('l', 900);
        $tieAlpha = str_repeat('a', 800);
        $tieBeta = str_repeat('b', 800);
        $binaryPayload = str_repeat('p', 700);
        $documentXml = str_repeat('<w:p/>', 100);
        $rootRelationshipsXml = str_repeat('r', 500);
        $parts = [
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => $documentXml, 'compressionMethod' => 0],
            ['name' => 'word/media/large.png', 'data' => $largeImage, 'compressionMethod' => 0],
            ['name' => 'word/media/tie-alpha.png', 'data' => $tieAlpha, 'compressionMethod' => 0],
            ['name' => 'word/media/tie-beta.png', 'data' => $tieBeta, 'compressionMethod' => 0],
            ['name' => 'word/payload/package.bin', 'data' => $binaryPayload, 'compressionMethod' => 0],
            ['name' => 'word/payload/small.bin', 'data' => 'small', 'compressionMethod' => 0],
        ];

        $summary = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromParts($parts));
        $centralSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest(ZipPackage::build($parts));
        $expectedLargestNames = [
            'word/media/large.png',
            'word/media/tie-alpha.png',
            'word/media/tie-beta.png',
            'word/payload/package.bin',
            'word/document.xml',
        ];

        $t->same(true, $summary['valid']);
        $t->same(true, $centralSummary['valid']);
        $t->same(5, $summary['largestPayloadEntryLimit']);
        $t->same(5, $summary['largestPayloadEntryCount']);
        $t->same(5, $centralSummary['largestPayloadEntryLimit']);
        $t->same(5, $centralSummary['largestPayloadEntryCount']);
        $t->same($expectedLargestNames, array_column($summary['largestPayloadEntries'], 'entryName'));
        $t->same($expectedLargestNames, array_column($centralSummary['largestPayloadEntries'], 'entryName'));
        $t->same($summary['largestPayloadEntries'], $centralSummary['largestPayloadEntries']);
        $t->same($summary['largestPayloadEntries'][0], $summary['largestPayloadEntry']);
        $t->same($centralSummary['largestPayloadEntries'][0], $centralSummary['largestPayloadEntry']);

        $largest = $summary['largestPayloadEntries'][0];
        $t->same('word/media/large.png', $largest['entryName']);
        $t->same('/word/media/large.png', $largest['partName']);
        $t->same('media', $largest['role']);
        $t->same('media', $largest['handoffKind']);
        $t->same(0, $largest['compressionMethod']);
        $t->same('stored', $largest['compressionMethodName']);
        $t->same(strlen($largeImage), $largest['compressedSize']);
        $t->same(strlen($largeImage), $largest['uncompressedSize']);

        $t->same('/word/media/tie-alpha.png', $summary['largestPayloadEntries'][1]['partName']);
        $t->same('/word/media/tie-beta.png', $summary['largestPayloadEntries'][2]['partName']);
        $t->same('binary-part', $summary['largestPayloadEntries'][3]['role']);
        $t->same('binary', $summary['largestPayloadEntries'][3]['handoffKind']);
        $t->same('xml-part', $summary['largestPayloadEntries'][4]['role']);
        $t->same('xml', $summary['largestPayloadEntries'][4]['handoffKind']);
    },
    'summarizes OPC ZIP manifest directory roots before XML package handoff' => static function (TestRunner $t) use ($pathSegmentPositionReviews): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
</Types>
XML;
        $rootRelationshipsXml = '<Relationships/>';
        $coreXml = '<cp:coreProperties/>';
        $documentXml = '<w:document/>';
        $documentRelationshipsXml = '<Relationships><Relationship Id="rIdImage" Target="media/image.png"/></Relationships>';
        $imageBytes = 'PNGDATA';
        $binaryBytes = 'BINARYDATA';
        $customXml = '<audit/>';

        $parts = [
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'docProps/core.xml', 'data' => $coreXml, 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => $documentXml, 'compressionMethod' => 0],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/media/', 'data' => '', 'compressionMethod' => 0],
            ['name' => 'word/media/image.png', 'data' => $imageBytes, 'compressionMethod' => 0],
            ['name' => 'word/payload.bin', 'data' => $binaryBytes, 'compressionMethod' => 0],
            ['name' => 'customXml/item1.xml', 'data' => $customXml, 'compressionMethod' => 0],
        ];

        $summary = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromParts($parts));
        $centralSummary = OpcRelationshipGraph::preflightZipCentralDirectoryManifest(ZipPackage::build($parts));
        $roots = [];
        foreach ($summary['directoryRootSummaries'] as $rootSummary) {
            $roots[$rootSummary['directoryRoot']] = $rootSummary;
        }
        $centralRoots = [];
        foreach ($centralSummary['directoryRootSummaries'] as $rootSummary) {
            $centralRoots[$rootSummary['directoryRoot']] = $rootSummary;
        }
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }

        $expectedRootCounts = [
            '/' => 1,
            '_rels/' => 1,
            'customXml/' => 1,
            'docProps/' => 1,
            'word/' => 5,
        ];
        $wordRootBytes = strlen($documentXml)
            + strlen($documentRelationshipsXml)
            + strlen($imageBytes)
            + strlen($binaryBytes);
        $expectedWordRoot = [
            'directoryRoot' => 'word/',
            'entryCount' => 5,
            'fileEntryCount' => 4,
            'directoryEntryCount' => 1,
            'packagePartCount' => 4,
            'validEntryCount' => 5,
            'invalidEntryCount' => 0,
            'unknownByteCountEntryCount' => 0,
            'compressedBytes' => $wordRootBytes,
            'uncompressedBytes' => $wordRootBytes,
            'roleCounts' => [
                'binary-part' => 1,
                'directory' => 1,
                'media' => 1,
                'part-relationships' => 1,
                'xml-part' => 1,
            ],
            'handoffKindCounts' => [
                'binary' => 1,
                'directory' => 1,
                'media' => 1,
                'relationships+xml' => 1,
                'xml' => 1,
            ],
            'issueCounts' => [],
            'issues' => [],
            'entryNames' => [
                'word/_rels/document.xml.rels',
                'word/document.xml',
                'word/media/',
                'word/media/image.png',
                'word/payload.bin',
            ],
            'partNames' => [
                '/word/_rels/document.xml.rels',
                '/word/document.xml',
                '/word/media/image.png',
                '/word/payload.bin',
            ],
        ];

        $t->same(true, $summary['valid']);
        $t->same(true, $centralSummary['valid']);
        $t->same(5, $summary['directoryRootCount']);
        $t->same(5, $centralSummary['directoryRootCount']);
        $t->same($expectedRootCounts, $summary['directoryRootCounts']);
        $t->same($expectedRootCounts, $centralSummary['directoryRootCounts']);
        $t->same(4, $summary['pathSegmentPositionSummaryCount']);
        $t->same(19, $summary['pathSegmentPositionOccurrenceCount']);
        $t->same(['first' => 8, 'last' => 8, 'middle' => 2, 'only' => 1], $summary['pathSegmentPositionCounts']);
        $t->same(['first' => 8, 'last' => 8, 'middle' => 2, 'only' => 1], $summary['pathSegmentPositionEntryCounts']);
        $t->same([
            'first' => [
                'binary-part' => 1,
                'directory' => 1,
                'document-properties' => 1,
                'media' => 1,
                'package-relationships' => 1,
                'part-relationships' => 1,
                'xml-part' => 2,
            ],
            'last' => [
                'binary-part' => 1,
                'directory' => 1,
                'document-properties' => 1,
                'media' => 1,
                'package-relationships' => 1,
                'part-relationships' => 1,
                'xml-part' => 2,
            ],
            'middle' => [
                'media' => 1,
                'part-relationships' => 1,
            ],
            'only' => [
                'content-types' => 1,
            ],
        ], $summary['pathSegmentPositionRoleEntryCounts']);
        $t->same([
            'first' => [
                'binary' => 1,
                'directory' => 1,
                'media' => 1,
                'relationships+xml' => 2,
                'xml' => 3,
            ],
            'last' => [
                'binary' => 1,
                'directory' => 1,
                'media' => 1,
                'relationships+xml' => 2,
                'xml' => 3,
            ],
            'middle' => [
                'media' => 1,
                'relationships+xml' => 1,
            ],
            'only' => [
                'content-types+xml' => 1,
            ],
        ], $summary['pathSegmentPositionHandoffKindEntryCounts']);
        $t->same(
            ['word/_rels/document.xml.rels'],
            $summary['entryNamesByPathSegmentPositionRole']['middle']['part-relationships']
        );
        $t->same(
            ['word/media/image.png'],
            $summary['entryNamesByPathSegmentPositionHandoffKind']['middle']['media']
        );
        $t->same([
            'word/_rels/document.xml.rels',
            'word/document.xml',
            'word/media/',
            'word/media/image.png',
            'word/payload.bin',
        ], $summary['entryNamesByDirectoryRoot']['word/']);
        $t->same($summary['entryNamesByDirectoryRoot'], $centralSummary['entryNamesByDirectoryRoot']);
        $t->same($expectedWordRoot, $roots['word/']);
        $t->same($expectedWordRoot, $centralRoots['word/']);
        $t->same(['document-properties' => 1], $roots['docProps/']['roleCounts']);
        $t->same(['xml' => 1], $roots['docProps/']['handoffKindCounts']);
        $t->same('/', $entries['[Content_Types].xml']['directoryRoot']);
        $t->same(['[Content_Types].xml'], $entries['[Content_Types].xml']['pathSegments']);
        $t->same(
            $pathSegmentPositionReviews(['[Content_Types].xml']),
            $entries['[Content_Types].xml']['pathSegmentPositionReviews']
        );
        $t->same(1, $entries['[Content_Types].xml']['pathSegmentCount']);
        $t->same(0, $entries['[Content_Types].xml']['directoryDepth']);
        $t->same('word/', $entries['word/media/image.png']['directoryRoot']);
        $t->same(['word', 'media', 'image.png'], $entries['word/media/image.png']['pathSegments']);
        $t->same(
            $pathSegmentPositionReviews(['word', 'media', 'image.png']),
            $entries['word/media/image.png']['pathSegmentPositionReviews']
        );
        $t->same(3, $entries['word/media/image.png']['pathSegmentCount']);
        $t->same(2, $entries['word/media/image.png']['directoryDepth']);
        $t->same('customXml/', $entries['customXml/item1.xml']['directoryRoot']);
        $t->same(['customXml', 'item1.xml'], $entries['customXml/item1.xml']['pathSegments']);
        $t->same(
            $pathSegmentPositionReviews(['customXml', 'item1.xml']),
            $entries['customXml/item1.xml']['pathSegmentPositionReviews']
        );
        $t->same(2, $entries['customXml/item1.xml']['pathSegmentCount']);
        $t->same(1, $entries['customXml/item1.xml']['directoryDepth']);
    },
    'preflights OPC ZIP entry manifest content type declarations before graph construction' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>'],
            ['name' => 'word/document.xml', 'data' => '<w:document/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<Relationships/>'],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'word/theme/theme1.thmx', 'data' => '<a:theme/>'],
            ['name' => 'word/media/source', 'data' => 'raw'],
        ]);

        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }

        $t->same(false, $summary['valid']);
        $t->same(true, $summary['contentTypeDeclarationAvailable']);
        $t->same(null, $summary['contentTypesParseError']);
        $t->same(5, $summary['contentTypeResolvedPartCount']);
        $t->same(4, $summary['contentTypeDefaultResolvedPartCount']);
        $t->same(1, $summary['contentTypeOverrideResolvedPartCount']);
        $t->same(2, $summary['missingContentTypePartCount']);
        $t->same(1, $summary['missingContentTypeDefaultCount']);
        $t->same(1, $summary['missingContentTypeExtensionlessCount']);
        $t->same(['/word/media/source', '/word/theme/theme1.thmx'], $summary['missingContentTypeParts']);
        $t->same(['thmx'], $summary['missingContentTypeExtensions']);
        $t->same([
            'missing-content-type' => 2,
            'missing-content-type-default' => 1,
            'missing-content-type-extension' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'missing-content-type',
            'missing-content-type-default',
            'missing-content-type-extension',
        ], $summary['issues']);

        $t->same('override', $entries['word/document.xml']['contentTypeSource']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $entries['word/document.xml']['contentType']);
        $t->same('/word/document.xml', $entries['word/document.xml']['contentTypeOverridePartName']);
        $t->same('default', $entries['word/media/review-image.PNG']['contentTypeSource']);
        $t->same('png', $entries['word/media/review-image.PNG']['contentTypeDefaultExtension']);
        $t->same('image/png', $entries['word/media/review-image.PNG']['contentType']);
        $t->same('missing', $entries['word/theme/theme1.thmx']['contentTypeSource']);
        $t->same(['missing-content-type', 'missing-content-type-default'], $entries['word/theme/theme1.thmx']['issues']);
        $t->same('missing', $entries['word/media/source']['contentTypeSource']);
        $t->same(['missing-content-type', 'missing-content-type-extension'], $entries['word/media/source']['issues']);

        $rawManifest = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => '<Types/>'],
            ['name' => 'word/document.xml', 'data' => '<w:document/>'],
        ]));
        $t->same(false, $rawManifest['contentTypeDeclarationAvailable']);
        $t->contains('namespace', (string) $rawManifest['contentTypesParseError']);
        $t->same(0, $rawManifest['missingContentTypePartCount']);
        $t->same(true, $rawManifest['valid']);
    },
    'preflights selected OPC package part content type diagnostics before graph construction' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/_rels/case.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
</Types>
XML;

        $summary = OpcRelationshipGraph::preflightSelectedContentTypes(
            ZipPackage::fromParts([
                ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
                ['name' => '_rels/.rels', 'data' => '<Relationships/>'],
                ['name' => 'word/document.xml', 'data' => '<w:document/>'],
                ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
                ['name' => 'Word/_rels/CasE.xml.rels', 'data' => '<Relationships/>'],
                ['name' => 'word/theme/theme1.thmx', 'data' => '<a:theme/>'],
                ['name' => 'word/media/source', 'data' => 'raw'],
            ]),
            [
                'word/document.xml?review=ready#source',
                '/word/media/review-image.PNG',
                '/WORD/_rels/CASE.xml.rels',
                '/word/theme/theme1.thmx',
                'word/media/source',
                '/word/missing.xml',
                '/WORD/DOCUMENT.XML',
            ]
        );

        $records = [];
        foreach ($summary['records'] as $record) {
            $records[$record['selectedPartName']] = $record;
        }

        $t->same(false, $summary['valid']);
        $t->same('caller-provided-part-list', $summary['selectionPolicy']);
        $t->same(true, $summary['normalizesQueryAndFragment']);
        $t->same(true, $summary['matchesEquivalentPartNames']);
        $t->same(false, $summary['readsSelectedPartPayloadBytes']);
        $t->same(true, $summary['contentTypeDeclarationAvailable']);
        $t->same(null, $summary['contentTypesParseError']);
        $t->same(7, $summary['selectedPartCount']);
        $t->same(6, $summary['uniqueSelectedPartCount']);
        $t->same(1, $summary['duplicateSelectedPartCount']);
        $t->same(0, $summary['invalidSelectedPartCount']);
        $t->same(6, $summary['existingSelectedPartCount']);
        $t->same(1, $summary['missingSelectedPartCount']);
        $t->same(4, $summary['exactSelectedPartCount']);
        $t->same(2, $summary['equivalentSelectedPartCount']);
        $t->same(5, $summary['contentTypeResolvedPartCount']);
        $t->same(2, $summary['contentTypeDefaultResolvedPartCount']);
        $t->same(3, $summary['contentTypeOverrideResolvedPartCount']);
        $t->same(2, $summary['missingContentTypePartCount']);
        $t->same(1, $summary['missingContentTypeDefaultCount']);
        $t->same(1, $summary['missingContentTypeExtensionlessCount']);
        $t->same(['/word/media/source', '/word/theme/theme1.thmx'], $summary['missingContentTypeParts']);
        $t->same(['/word/missing.xml'], $summary['missingSelectedPartNames']);
        $t->same(['/WORD/DOCUMENT.XML'], $summary['duplicateSelectedPartNames']);
        $t->same([
            'duplicate-selected-part' => 1,
            'missing-content-type' => 2,
            'missing-content-type-default' => 1,
            'missing-content-type-extension' => 1,
            'selected-part-missing' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'duplicate-selected-part',
            'missing-content-type',
            'missing-content-type-default',
            'missing-content-type-extension',
            'selected-part-missing',
        ], $summary['issues']);
        $t->same([
            'default' => 2,
            'missing' => 2,
            'override' => 3,
        ], $summary['contentTypeSourceCounts']);
        $t->same([
            '/word/media/review-image.PNG',
            '/word/missing.xml',
        ], $summary['partNamesByContentTypeSource']['default']);
        $t->same([
            '/WORD/DOCUMENT.XML',
            '/WORD/_rels/CASE.xml.rels',
            '/word/document.xml',
        ], $summary['partNamesByContentTypeSource']['override']);
        $t->same([
            '/WORD/DOCUMENT.XML',
            '/WORD/_rels/CASE.xml.rels',
        ], $summary['selectedPartNamesByMatchKind']['equivalent']);
        $t->same(['/word/missing.xml'], $summary['partNamesByIssue']['selected-part-missing']);
        $t->same(['word/media/source'], $summary['selectedPartNamesByIssue']['missing-content-type-extension']);

        $document = $records['word/document.xml?review=ready#source'];
        $t->same('/word/document.xml', $document['partName']);
        $t->same('/word/document.xml', $document['packagePartName']);
        $t->same('exact', $document['matchKind']);
        $t->same('override', $document['contentTypeSource']);
        $t->same('/word/document.xml', $document['contentTypeOverridePartName']);
        $t->same(true, $document['contentTypeOverridePartNameExactMatch']);

        $image = $records['/word/media/review-image.PNG'];
        $t->same('default', $image['contentTypeSource']);
        $t->same('png', $image['contentTypeDefaultExtension']);
        $t->same('image/png', $image['contentType']);

        $caseRelationship = $records['/WORD/_rels/CASE.xml.rels'];
        $t->same('/Word/_rels/CasE.xml.rels', $caseRelationship['packagePartName']);
        $t->same('equivalent', $caseRelationship['matchKind']);
        $t->same('override', $caseRelationship['contentTypeSource']);
        $t->same('/word/_rels/case.xml.rels', $caseRelationship['contentTypeOverridePartName']);
        $t->same(true, $caseRelationship['contentTypeOverridePartNameEquivalentMatch']);

        $missingDefault = $records['/word/theme/theme1.thmx'];
        $t->same(true, $missingDefault['exists']);
        $t->same('missing', $missingDefault['contentTypeSource']);
        $t->same(['missing-content-type', 'missing-content-type-default'], $missingDefault['issues']);

        $missingExtension = $records['word/media/source'];
        $t->same(true, $missingExtension['exists']);
        $t->same(['missing-content-type', 'missing-content-type-extension'], $missingExtension['issues']);

        $missingSelected = $records['/word/missing.xml'];
        $t->same(false, $missingSelected['exists']);
        $t->same('default', $missingSelected['contentTypeSource']);
        $t->same(['selected-part-missing'], $missingSelected['issues']);

        $duplicate = $records['/WORD/DOCUMENT.XML'];
        $t->same(0, $duplicate['duplicateOfIndex']);
        $t->same('/word/document.xml', $duplicate['packagePartName']);
        $t->same('equivalent', $duplicate['matchKind']);
        $t->same('override', $duplicate['contentTypeSource']);
        $t->same(['duplicate-selected-part'], $duplicate['issues']);
    },
    'summarizes OPC ZIP entry manifest content type byte buckets before graph construction' => static function (TestRunner $t): void {
        $documentContentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';
        $embeddedPackageContentType = 'application/vnd.openxmlformats-officedocument.package';
        $relationshipsContentType = 'application/vnd.openxmlformats-package.relationships+xml';
        $contentTypesXml = <<<XML
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="{$relationshipsContentType}"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="{$documentContentType}"/>
  <Override PartName="/word/embeddings/package1.docx" ContentType="{$embeddedPackageContentType}"/>
</Types>
XML;
        $rootRelationshipsXml = '<Relationships/>';
        $documentXml = '<w:document/>';
        $stylesXml = '<w:styles/>';
        $imageBytes = 'PNGDATA';
        $embeddedPackageBytes = 'DOCXDATA';
        $customXml = '<audit/>';
        $binaryBytes = 'PAYLOAD';
        $missingThemeBytes = 'THEME';
        $missingBytes = 'RAW';

        $summary = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => $documentXml, 'compressionMethod' => 0],
            ['name' => 'word/styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
            ['name' => 'word/media/image.PNG', 'data' => $imageBytes, 'compressionMethod' => 0],
            ['name' => 'word/embeddings/package1.docx', 'data' => $embeddedPackageBytes, 'compressionMethod' => 0],
            ['name' => 'customXml/item1.xml', 'data' => $customXml, 'compressionMethod' => 0],
            ['name' => 'word/payload.bin', 'data' => $binaryBytes, 'compressionMethod' => 0],
            ['name' => 'word/theme/theme1.thmx', 'data' => $missingThemeBytes, 'compressionMethod' => 0],
            ['name' => 'word/media/source', 'data' => $missingBytes, 'compressionMethod' => 0],
        ]));

        $t->same(false, $summary['valid']);
        $t->same(7, $summary['contentTypeResolvedPartCount']);
        $t->same(5, $summary['contentTypeDefaultResolvedPartCount']);
        $t->same(2, $summary['contentTypeOverrideResolvedPartCount']);
        $t->same(2, $summary['missingContentTypePartCount']);
        $t->same(1, $summary['missingContentTypeDefaultCount']);
        $t->same(1, $summary['missingContentTypeExtensionlessCount']);
        $t->same(['/word/media/source', '/word/theme/theme1.thmx'], $summary['missingContentTypeParts']);
        $t->same(['thmx'], $summary['missingContentTypeExtensions']);
        $t->same([
            'default' => [
                'entryCount' => 5,
                'compressedBytes' => strlen($rootRelationshipsXml) + strlen($stylesXml) + strlen($imageBytes) + strlen($customXml) + strlen($binaryBytes),
                'uncompressedBytes' => strlen($rootRelationshipsXml) + strlen($stylesXml) + strlen($imageBytes) + strlen($customXml) + strlen($binaryBytes),
            ],
            'missing' => [
                'entryCount' => 2,
                'compressedBytes' => strlen($missingThemeBytes) + strlen($missingBytes),
                'uncompressedBytes' => strlen($missingThemeBytes) + strlen($missingBytes),
            ],
            'override' => [
                'entryCount' => 2,
                'compressedBytes' => strlen($documentXml) + strlen($embeddedPackageBytes),
                'uncompressedBytes' => strlen($documentXml) + strlen($embeddedPackageBytes),
            ],
        ], $summary['byteCountsByContentTypeSource']);
        $t->same([
            'application/octet-stream' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($binaryBytes),
                'uncompressedBytes' => strlen($binaryBytes),
            ],
            $embeddedPackageContentType => [
                'entryCount' => 1,
                'compressedBytes' => strlen($embeddedPackageBytes),
                'uncompressedBytes' => strlen($embeddedPackageBytes),
            ],
            $documentContentType => [
                'entryCount' => 1,
                'compressedBytes' => strlen($documentXml),
                'uncompressedBytes' => strlen($documentXml),
            ],
            $relationshipsContentType => [
                'entryCount' => 1,
                'compressedBytes' => strlen($rootRelationshipsXml),
                'uncompressedBytes' => strlen($rootRelationshipsXml),
            ],
            'application/xml' => [
                'entryCount' => 2,
                'compressedBytes' => strlen($stylesXml) + strlen($customXml),
                'uncompressedBytes' => strlen($stylesXml) + strlen($customXml),
            ],
            'image/png' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($imageBytes),
                'uncompressedBytes' => strlen($imageBytes),
            ],
        ], $summary['byteCountsByContentType']);
        $t->same([
            [
                'contentTypeSource' => 'default',
                'entryCount' => 5,
                'fileEntryCount' => 5,
                'directoryEntryCount' => 0,
                'packagePartCount' => 5,
                'compressedBytes' => strlen($rootRelationshipsXml) + strlen($stylesXml) + strlen($imageBytes) + strlen($customXml) + strlen($binaryBytes),
                'uncompressedBytes' => strlen($rootRelationshipsXml) + strlen($stylesXml) + strlen($imageBytes) + strlen($customXml) + strlen($binaryBytes),
                'roleCounts' => [
                    'binary-part' => 1,
                    'media' => 1,
                    'package-relationships' => 1,
                    'xml-part' => 2,
                ],
                'handoffKindCounts' => [
                    'binary' => 1,
                    'media' => 1,
                    'relationships+xml' => 1,
                    'xml' => 2,
                ],
                'entryNames' => [
                    '_rels/.rels',
                    'customXml/item1.xml',
                    'word/media/image.PNG',
                    'word/payload.bin',
                    'word/styles.xml',
                ],
                'partNames' => [
                    '/_rels/.rels',
                    '/customXml/item1.xml',
                    '/word/media/image.PNG',
                    '/word/payload.bin',
                    '/word/styles.xml',
                ],
            ],
            [
                'contentTypeSource' => 'missing',
                'entryCount' => 2,
                'fileEntryCount' => 2,
                'directoryEntryCount' => 0,
                'packagePartCount' => 2,
                'compressedBytes' => strlen($missingThemeBytes) + strlen($missingBytes),
                'uncompressedBytes' => strlen($missingThemeBytes) + strlen($missingBytes),
                'roleCounts' => [
                    'binary-part' => 2,
                ],
                'handoffKindCounts' => [
                    'binary' => 2,
                ],
                'entryNames' => [
                    'word/media/source',
                    'word/theme/theme1.thmx',
                ],
                'partNames' => [
                    '/word/media/source',
                    '/word/theme/theme1.thmx',
                ],
            ],
            [
                'contentTypeSource' => 'override',
                'entryCount' => 2,
                'fileEntryCount' => 2,
                'directoryEntryCount' => 0,
                'packagePartCount' => 2,
                'compressedBytes' => strlen($documentXml) + strlen($embeddedPackageBytes),
                'uncompressedBytes' => strlen($documentXml) + strlen($embeddedPackageBytes),
                'roleCounts' => [
                    'embedded-package-candidate' => 1,
                    'xml-part' => 1,
                ],
                'handoffKindCounts' => [
                    'embedded-package' => 1,
                    'xml' => 1,
                ],
                'entryNames' => [
                    'word/document.xml',
                    'word/embeddings/package1.docx',
                ],
                'partNames' => [
                    '/word/document.xml',
                    '/word/embeddings/package1.docx',
                ],
            ],
        ], $summary['contentTypeSourceSummaries']);
        $contentTypeSummariesByType = [];
        foreach ($summary['contentTypeSummaries'] as $contentTypeSummary) {
            $contentTypeSummariesByType[$contentTypeSummary['contentType']] = $contentTypeSummary;
        }
        $t->same([
            'contentType' => 'application/xml',
            'entryCount' => 2,
            'fileEntryCount' => 2,
            'directoryEntryCount' => 0,
            'packagePartCount' => 2,
            'compressedBytes' => strlen($stylesXml) + strlen($customXml),
            'uncompressedBytes' => strlen($stylesXml) + strlen($customXml),
            'contentTypeSourceCounts' => ['default' => 2],
            'roleCounts' => ['xml-part' => 2],
            'handoffKindCounts' => ['xml' => 2],
            'entryNames' => ['customXml/item1.xml', 'word/styles.xml'],
            'partNames' => ['/customXml/item1.xml', '/word/styles.xml'],
        ], $contentTypeSummariesByType['application/xml']);
        $t->same([
            'application/octet-stream',
            'application/vnd.openxmlformats-officedocument.package',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/vnd.openxmlformats-package.relationships+xml',
            'application/xml',
            'image/png',
        ], array_column($summary['contentTypeSummaries'], 'contentType'));
    },
    'summarizes OPC ZIP entry manifest content type entry name provenance before graph construction' => static function (TestRunner $t): void {
        $documentContentType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml';
        $relationshipsContentType = 'application/vnd.openxmlformats-package.relationships+xml';
        $contentTypesXml = <<<XML
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="{$relationshipsContentType}"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="{$documentContentType}"/>
</Types>
XML;

        $summary = OpcRelationshipGraph::preflightZipEntryManifest(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml, 'compressionMethod' => 0],
            ['name' => '_rels/.rels', 'data' => '<Relationships/>', 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => '<w:document/>', 'compressionMethod' => 0],
            ['name' => 'word/styles.xml', 'data' => '<w:styles/>', 'compressionMethod' => 0],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>', 'compressionMethod' => 0],
            ['name' => 'word/media/source', 'data' => 'RAW', 'compressionMethod' => 0],
        ]));

        $t->same([
            $documentContentType => [
                'word/document.xml',
            ],
            $relationshipsContentType => [
                '_rels/.rels',
            ],
            'application/xml' => [
                'customXml/item1.xml',
                'word/styles.xml',
            ],
        ], $summary['entryNamesByContentType']);
        $t->same([
            'default' => [
                '_rels/.rels',
                'customXml/item1.xml',
                'word/styles.xml',
            ],
            'missing' => [
                'word/media/source',
            ],
            'override' => [
                'word/document.xml',
            ],
        ], $summary['entryNamesByContentTypeSource']);
        $t->same(['/word/media/source'], $summary['missingContentTypeParts']);
    },
    'preflights OPC ZIP manifest content type override declarations before graph construction' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/WORD/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/_rels/document.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/[Content_Types].xml" ContentType="application/xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
  <Override PartName="/word/media/review.bin" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/missing.xml" ContentType="application/xml"/>
  <Override PartName="/word/_rels/missing.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/_rels/_rels/document.xml.rels.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/_rels/missing-review.xml" ContentType="application/xml"/>
</Types>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => 'word/document.xml', 'data' => '<w:document/>'],
            ['name' => 'word/styles.xml', 'data' => '<w:styles/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<Relationships/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => '<Relationships/>'],
            ['name' => 'word/media/review.bin', 'data' => 'RELATIONSHIP-CONTENT-TYPE'],
        ]);

        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }
        $overrides = [];
        foreach ($summary['contentTypeOverrideDeclarations'] as $override) {
            $overrides[$override['partName']] = $override;
        }

        $t->same(false, $summary['valid']);
        $t->same(10, $summary['contentTypeOverrideDeclarationCount']);
        $t->same(6, $summary['contentTypeUsedOverrideDeclarationCount']);
        $t->same(4, $summary['contentTypeUnusedOverrideDeclarationCount']);
        $t->same(5, $summary['contentTypeExactOverrideDeclarationCount']);
        $t->same(1, $summary['contentTypeEquivalentOverrideDeclarationCount']);
        $t->same(7, $summary['contentTypeInvalidOverrideDeclarationCount']);
        $t->same(4, $summary['contentTypeRelationshipOverrideDeclarationCount']);
        $t->same(4, $summary['contentTypeRelationshipContentTypeDeclarationCount']);
        $t->same(1, $summary['contentTypeNonRelationshipRelationshipContentTypeDeclarationCount']);
        $t->same(1, $summary['contentTypeContentTypesItemOverrideDeclarationCount']);
        $t->same(1, $summary['contentTypeReservedRelationshipDirectoryOverrideDeclarationCount']);
        $t->same([
            '/word/_rels/_rels/document.xml.rels.rels',
            '/word/_rels/missing-review.xml',
            '/word/_rels/missing.xml.rels',
            '/word/missing.xml',
        ], $summary['contentTypeUnusedOverridePartNames']);
        $t->same([
            '/[Content_Types].xml',
            '/word/_rels/comments.xml.rels',
            '/word/_rels/document.xml.rels',
            '/word/document.xml',
            '/word/media/review.bin',
        ], $summary['contentTypeExactOverridePartNames']);
        $t->same(['/WORD/styles.xml'], $summary['contentTypeEquivalentOverridePartNames']);
        $t->same([
            '/[Content_Types].xml',
            '/word/_rels/_rels/document.xml.rels.rels',
            '/word/_rels/comments.xml.rels',
            '/word/_rels/missing-review.xml',
            '/word/_rels/missing.xml.rels',
            '/word/media/review.bin',
            '/word/missing.xml',
        ], $summary['contentTypeInvalidOverridePartNames']);
        $t->same([
            '/word/_rels/_rels/document.xml.rels.rels',
            '/word/_rels/comments.xml.rels',
            '/word/_rels/document.xml.rels',
            '/word/_rels/missing.xml.rels',
        ], $summary['contentTypeRelationshipOverridePartNames']);
        $t->same([
            '/word/_rels/_rels/document.xml.rels.rels',
            '/word/_rels/document.xml.rels',
            '/word/_rels/missing.xml.rels',
            '/word/media/review.bin',
        ], $summary['contentTypeRelationshipContentTypePartNames']);
        $t->same(['/word/media/review.bin'], $summary['contentTypeNonRelationshipRelationshipContentTypePartNames']);
        $t->same(['/[Content_Types].xml'], $summary['contentTypeContentTypesItemOverridePartNames']);
        $t->same(['/word/_rels/missing-review.xml'], $summary['contentTypeReservedRelationshipDirectoryOverridePartNames']);
        $t->same([
            'content-types-override-target' => 1,
            'invalid-relationship-content-type' => 1,
            'override-target-missing-part' => 4,
            'relationship-content-type-on-non-relationship-part' => 1,
            'relationship-override-source-missing' => 1,
            'relationship-part-source' => 1,
            'reserved-relationship-directory-override' => 1,
        ], $summary['contentTypeOverrideDeclarationIssueCounts']);
        $t->same([
            'content-types-override-target' => 1,
            'invalid-relationship-content-type' => 1,
            'override-target-missing-part' => 4,
            'relationship-content-type-on-non-relationship-part' => 1,
            'relationship-override-source-missing' => 1,
            'relationship-part-source' => 1,
            'reserved-relationship-directory-override' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'content-types-override-target',
            'invalid-relationship-content-type',
            'relationship-content-type-on-non-relationship-part',
            'override-target-missing-part',
            'relationship-override-source-missing',
            'relationship-part-source',
            'reserved-relationship-directory-override',
        ], $summary['issues']);
        $t->same([], $summary['entryNamesByIssue']);
        $t->same([
            'content-types-override-target' => ['/[Content_Types].xml'],
            'invalid-relationship-content-type' => ['/word/_rels/comments.xml.rels'],
            'override-target-missing-part' => [
                '/word/_rels/_rels/document.xml.rels.rels',
                '/word/_rels/missing-review.xml',
                '/word/_rels/missing.xml.rels',
                '/word/missing.xml',
            ],
            'relationship-content-type-on-non-relationship-part' => ['/word/media/review.bin'],
            'relationship-override-source-missing' => ['/word/_rels/missing.xml.rels'],
            'relationship-part-source' => ['/word/_rels/_rels/document.xml.rels.rels'],
            'reserved-relationship-directory-override' => ['/word/_rels/missing-review.xml'],
        ], $summary['partNamesByIssue']);

        $t->same('exact', $overrides['/word/document.xml']['matchKind']);
        $t->same(true, $overrides['/word/document.xml']['exists']);
        $t->same('/word/document.xml', $overrides['/word/document.xml']['packagePartName']);
        $t->same(true, $overrides['/word/document.xml']['partNameExactMatch']);
        $t->same(false, $overrides['/word/document.xml']['partNameEquivalentMatch']);
        $t->same([], $overrides['/word/document.xml']['issues']);

        $t->same('equivalent', $overrides['/WORD/styles.xml']['matchKind']);
        $t->same(true, $overrides['/WORD/styles.xml']['exists']);
        $t->same('/word/styles.xml', $overrides['/WORD/styles.xml']['packagePartName']);
        $t->same(false, $overrides['/WORD/styles.xml']['partNameExactMatch']);
        $t->same(true, $overrides['/WORD/styles.xml']['partNameEquivalentMatch']);
        $t->same([], $overrides['/WORD/styles.xml']['issues']);
        $t->same('override', $entries['word/styles.xml']['contentTypeSource']);
        $t->same('/WORD/styles.xml', $entries['word/styles.xml']['contentTypeOverridePartName']);
        $t->same(true, $entries['word/styles.xml']['contentTypeOverridePartNameEquivalentMatch']);

        $t->same('missing', $overrides['/word/missing.xml']['matchKind']);
        $t->same(false, $overrides['/word/missing.xml']['exists']);
        $t->same(null, $overrides['/word/missing.xml']['packagePartName']);
        $t->same(['override-target-missing-part'], $overrides['/word/missing.xml']['issues']);

        $t->same('exact', $overrides['/word/_rels/document.xml.rels']['matchKind']);
        $t->same(true, $overrides['/word/_rels/document.xml.rels']['relationshipPart']);
        $t->same(true, $overrides['/word/_rels/document.xml.rels']['relationshipContentType']);
        $t->same('/word/document.xml', $overrides['/word/_rels/document.xml.rels']['relationshipSource']);
        $t->same(true, $overrides['/word/_rels/document.xml.rels']['relationshipSourceExists']);
        $t->same(false, $overrides['/word/_rels/document.xml.rels']['relationshipSourceIsRelationshipPart']);
        $t->same([], $overrides['/word/_rels/document.xml.rels']['issues']);

        $t->same('exact', $overrides['/word/_rels/comments.xml.rels']['matchKind']);
        $t->same(true, $overrides['/word/_rels/comments.xml.rels']['relationshipPart']);
        $t->same(false, $overrides['/word/_rels/comments.xml.rels']['relationshipContentType']);
        $t->same('/word/comments.xml', $overrides['/word/_rels/comments.xml.rels']['relationshipSource']);
        $t->same(true, $overrides['/word/_rels/comments.xml.rels']['relationshipSourceExists']);
        $t->same(['invalid-relationship-content-type'], $overrides['/word/_rels/comments.xml.rels']['issues']);

        $t->same('exact', $overrides['/word/media/review.bin']['matchKind']);
        $t->same(false, $overrides['/word/media/review.bin']['relationshipPart']);
        $t->same(true, $overrides['/word/media/review.bin']['relationshipContentType']);
        $t->same(['relationship-content-type-on-non-relationship-part'], $overrides['/word/media/review.bin']['issues']);

        $t->same('exact', $overrides['/[Content_Types].xml']['matchKind']);
        $t->same(true, $overrides['/[Content_Types].xml']['contentTypesItem']);
        $t->same(['content-types-override-target'], $overrides['/[Content_Types].xml']['issues']);

        $t->same('missing', $overrides['/word/_rels/missing.xml.rels']['matchKind']);
        $t->same(true, $overrides['/word/_rels/missing.xml.rels']['relationshipPart']);
        $t->same('/word/missing.xml', $overrides['/word/_rels/missing.xml.rels']['relationshipSource']);
        $t->same(false, $overrides['/word/_rels/missing.xml.rels']['relationshipSourceExists']);
        $t->same([
            'override-target-missing-part',
            'relationship-override-source-missing',
        ], $overrides['/word/_rels/missing.xml.rels']['issues']);

        $t->same('missing', $overrides['/word/_rels/_rels/document.xml.rels.rels']['matchKind']);
        $t->same(true, $overrides['/word/_rels/_rels/document.xml.rels.rels']['relationshipPart']);
        $t->same('/word/_rels/document.xml.rels', $overrides['/word/_rels/_rels/document.xml.rels.rels']['relationshipSource']);
        $t->same(true, $overrides['/word/_rels/_rels/document.xml.rels.rels']['relationshipSourceExists']);
        $t->same(true, $overrides['/word/_rels/_rels/document.xml.rels.rels']['relationshipSourceIsRelationshipPart']);
        $t->same([
            'override-target-missing-part',
            'relationship-part-source',
        ], $overrides['/word/_rels/_rels/document.xml.rels.rels']['issues']);

        $t->same('missing', $overrides['/word/_rels/missing-review.xml']['matchKind']);
        $t->same(false, $overrides['/word/_rels/missing-review.xml']['relationshipPart']);
        $t->same(false, $overrides['/word/_rels/missing-review.xml']['relationshipContentType']);
        $t->same(true, $overrides['/word/_rels/missing-review.xml']['reservedRelationshipDirectoryPart']);
        $t->same([
            'override-target-missing-part',
            'reserved-relationship-directory-override',
        ], $overrides['/word/_rels/missing-review.xml']['issues']);
    },
    'classifies generic OPC ZIP payload handoff roles from content types' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="image/png; role=thumbnail"/>
  <Default Extension="pkg" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/payload/extensionless-image" ContentType="image/svg+xml"/>
  <Override PartName="/word/payload/extensionless-package" ContentType="application/vnd.openxmlformats-package.encrypted-package"/>
  <Override PartName="/word/payload/extensionless-html" ContentType="text/html; charset=utf-8"/>
</Types>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => 'word/media/thumbnail.bin', 'data' => 'PNG'],
            ['name' => 'word/embeddings/nested.pkg', 'data' => 'ZIP'],
            ['name' => 'word/payload/extensionless-image', 'data' => '<svg xmlns="http://www.w3.org/2000/svg"/>'],
            ['name' => 'word/payload/extensionless-package', 'data' => 'encrypted package bytes'],
            ['name' => 'word/payload/extensionless-html', 'data' => '<html><body>review</body></html>'],
        ]);

        $summary = OpcRelationshipGraph::preflightZipEntryManifest($package);
        $entries = [];
        foreach ($summary['entries'] as $entry) {
            $entries[$entry['entryName']] = $entry;
        }

        $t->same(true, $summary['valid']);
        $t->same(2, $summary['mediaPartCandidateCount']);
        $t->same(2, $summary['embeddedPackageCandidateCount']);
        $t->same(2, $summary['xmlPayloadPartCount']);
        $t->same(4, $summary['binaryPayloadPartCount']);
        $t->same([
            'content-types' => 1,
            'embedded-package-candidate' => 2,
            'media' => 2,
            'xml-part' => 1,
        ], $summary['roleCounts']);

        $t->same('media', $entries['word/media/thumbnail.bin']['role']);
        $t->same('media', $entries['word/media/thumbnail.bin']['handoffKind']);
        $t->same('default', $entries['word/media/thumbnail.bin']['contentTypeSource']);
        $t->same('bin', $entries['word/media/thumbnail.bin']['contentTypeDefaultExtension']);
        $t->same('embedded-package-candidate', $entries['word/embeddings/nested.pkg']['role']);
        $t->same('embedded-package', $entries['word/embeddings/nested.pkg']['handoffKind']);
        $t->same('media', $entries['word/payload/extensionless-image']['role']);
        $t->same('override', $entries['word/payload/extensionless-image']['contentTypeSource']);
        $t->same('embedded-package-candidate', $entries['word/payload/extensionless-package']['role']);
        $t->same('embedded-package', $entries['word/payload/extensionless-package']['handoffKind']);
        $t->same('xml-part', $entries['word/payload/extensionless-html']['role']);
        $t->same('xml', $entries['word/payload/extensionless-html']['handoffKind']);

        $graph = OpcRelationshipGraph::fromPackage($package);
        $defaultUsage = $graph->contentTypeDefaultUsageSummary();
        $defaults = [];
        foreach ($defaultUsage['defaults'] as $default) {
            $defaults[$default['normalizedExtension']] = $default;
        }

        $t->same(1, $defaultUsage['mediaDefaultResolvedCount']);
        $t->same(1, $defaultUsage['embeddedPackageDefaultResolvedCount']);
        $t->same(1, $defaults['bin']['mediaPartCount']);
        $t->same(1, $defaults['pkg']['embeddedPackageCandidateCount']);
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
    'preflights OPC content type declaration collisions before graph construction' => static function (TestRunner $t): void {
        $collisionContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="XML" ContentType="text/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/WORD/document.xml" ContentType="application/xml"/>
  <Override PartName="/word/media/hero.png" ContentType="image/png"/>
</Types>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $collisionContentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdDocument" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="word/document.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
        ]);

        $preflight = OpcRelationshipGraph::preflightContentTypesInPackage($package);
        $records = [];
        foreach ($preflight['records'] as $record) {
            $records[$record['kind'] . ':' . ($record['normalizedExtension'] ?? $record['normalizedPartName'])] = $record;
        }

        $t->same('/[Content_Types].xml', $preflight['partName']);
        $t->same(true, $preflight['present']);
        $t->same(false, $preflight['valid']);
        $t->same(null, $preflight['parseError']);
        $t->same(6, $preflight['recordCount']);
        $t->same(3, $preflight['defaultCount']);
        $t->same(3, $preflight['overrideCount']);
        $t->same(4, $preflight['invalidCount']);
        $t->same(1, $preflight['duplicateDefaultExtensionCount']);
        $t->same(1, $preflight['duplicateOverridePartNameCount']);
        $t->same(['xml'], $preflight['duplicateDefaultExtensions']);
        $t->same(['/word/document.xml'], $preflight['duplicateOverridePartNames']);
        $t->same(['xml' => ['XML', 'xml']], $preflight['duplicateDefaultExtensionGroups']);
        $t->same(['/word/document.xml' => ['/WORD/document.xml', '/word/document.xml']], $preflight['duplicateOverridePartNameGroups']);
        $t->same([
            'duplicate-default-extension' => 2,
            'duplicate-override-part-name' => 2,
        ], $preflight['issueCounts']);
        $t->same(['duplicate-default-extension', 'duplicate-override-part-name'], $preflight['issues']);

        $t->same(['duplicate-default-extension'], $records['Default:xml']['issues']);
        $t->same(['duplicate-default-extension'], $records['Default:XML']['issues']);
        $t->same([], $records['Default:png']['issues']);
        $t->same(['duplicate-override-part-name'], $records['Override:/word/document.xml']['issues']);
        $t->same(['duplicate-override-part-name'], $records['Override:/WORD/document.xml']['issues']);
        $t->same([], $records['Override:/word/media/hero.png']['issues']);

        $missing = OpcRelationshipGraph::preflightContentTypesInPackage(ZipPackage::fromParts([
            ['name' => 'word/document.xml', 'data' => '<w:document/>'],
        ]));
        $t->same(false, $missing['present']);
        $t->same(['missing-content-types-item'], $missing['issues']);

        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($collisionContentTypesXml));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationshipGraph => OpcRelationshipGraph::fromPackage($package));
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
    'matches OPC package role content types case insensitively' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="Application/Vnd.Openxmlformats-Package.Relationships+Xml"/>
  <Default Extension="xml" ContentType="Application/Xml"/>
  <Default Extension="png" ContentType="Image/Png"/>
  <Override PartName="/word/document.xml" ContentType="Application/Vnd.Openxmlformats-Officedocument.Wordprocessingml.Document.Main+Xml"/>
  <Override PartName="/docProps/core.xml" ContentType="Application/Vnd.Openxmlformats-Package.Core-Properties+Xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="Application/Vnd.Openxmlformats-Package.Digital-Signature-Origin"/>
  <Override PartName="/_xmlsignatures/sig-case.xml" ContentType="Application/Vnd.Openxmlformats-Package.Digital-Signature-XmlSignature+Xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Hero.PNG"/>
</Relationships>
XML;

        $originRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignatureCase" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig-case.xml"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature Id="idPackageSignature" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=Application/Vnd.OpenXMLFormats-Package.Relationships+XML">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/Hero.PNG', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $originRelationshipsXml],
            ['name' => '_xmlsignatures/sig-case.xml', 'data' => $signatureXml],
        ]);

        $types = OpcContentTypes::fromXml($contentTypesXml);
        $t->same('Application/Vnd.Openxmlformats-Package.Relationships+Xml', $types->contentTypeForPart('/word/_rels/document.xml.rels'));
        $t->same('Application/Vnd.Openxmlformats-Officedocument.Wordprocessingml.Document.Main+Xml', $types->contentTypeForPart('/word/document.xml'));

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same(true, $loads['/_rels/.rels']['loaded']);
        $t->same(true, $loads['/word/_rels/document.xml.rels']['loaded']);
        $t->same(true, $loads['/_xmlsignatures/_rels/origin.sigs.rels']['loaded']);
        $t->same([], $loads['/word/_rels/document.xml.rels']['issues']);

        $graph = OpcRelationshipGraph::fromPackage($package);
        $t->same(['/', '/_xmlsignatures/origin.sigs', '/word/document.xml'], $graph->sourcePartNames());

        $officeDocument = $graph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $t->same(true, $officeDocument['valid']);
        $t->same('Application/Vnd.Openxmlformats-Officedocument.Wordprocessingml.Document.Main+Xml', $officeDocument['relationships'][0]['contentType']);
        $t->same([], $officeDocument['relationships'][0]['issues']);

        $coreProperties = $graph->preflightCoreProperties();
        $t->same(true, $coreProperties['valid']);
        $t->same('Application/Vnd.Openxmlformats-Package.Core-Properties+Xml', $coreProperties['relationships'][0]['contentType']);

        $documentTargets = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $documentTargets[$target['id']] = $target;
        }
        $t->same('Image/Png', $documentTargets['rIdHero']['contentType']);
        $t->same(true, $documentTargets['rIdHero']['valid']);

        $digitalSignatures = $graph->preflightDigitalSignatures();
        $t->same(true, $digitalSignatures[0]['valid']);
        $t->same('Application/Vnd.Openxmlformats-Package.Digital-Signature-Origin', $digitalSignatures[0]['contentType']);
        $t->same('Application/Vnd.Openxmlformats-Package.Digital-Signature-XmlSignature+Xml', $digitalSignatures[0]['signatures'][0]['contentType']);

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-case.xml');
        $t->same('Application/Vnd.Openxmlformats-Package.Relationships+Xml', $transforms[0]['referenceTargetContentType']);
        $t->same('Application/Vnd.OpenXMLFormats-Package.Relationships+XML', $transforms[0]['referenceContentType']);
        $t->same(true, $transforms[0]['referenceContentTypeMatches']);
        $t->same(true, $transforms[0]['valid']);
        $t->same(['rIdHero'], $transforms[0]['relationshipIds']);
        $t->same([], $transforms[0]['issues']);
    },
    'matches OPC package role content type media types with parameters' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml; charset=UTF-8"/>
  <Default Extension="xml" ContentType="application/xml; charset=UTF-8"/>
  <Default Extension="png" ContentType="image/png; review=thumbnail"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml; profile=docx"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml; audit=core"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin; profile=opc"/>
  <Override PartName="/_xmlsignatures/sig-params.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml; profile=opc"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $originRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignatureParams" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig-params.xml"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature Id="idPackageSignature" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $originRelationshipsXml],
            ['name' => '_xmlsignatures/sig-params.xml', 'data' => $signatureXml],
        ]);

        $types = OpcContentTypes::fromXml($contentTypesXml);
        $t->same('application/vnd.openxmlformats-package.relationships+xml; charset=UTF-8', $types->contentTypeForPart('/word/_rels/document.xml.rels'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml; profile=docx', $types->contentTypeForPart('/word/document.xml'));

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same(true, $loads['/_rels/.rels']['loaded']);
        $t->same(true, $loads['/word/_rels/document.xml.rels']['loaded']);
        $t->same(true, $loads['/_xmlsignatures/_rels/origin.sigs.rels']['loaded']);
        $t->same([], $loads['/word/_rels/document.xml.rels']['issues']);

        $t->same(true, OpcRelationships::packageHasRelationshipsForSource($package, '/word/document.xml'));
        $directRelationships = OpcRelationships::fromPackage($package, '/word/document.xml');
        $t->same(['rIdHero'], array_map(static fn (OpcRelationship $relationship): string => $relationship->id, $directRelationships->all()));

        $graph = OpcRelationshipGraph::fromPackage($package);
        $t->same(['/', '/_xmlsignatures/origin.sigs', '/word/document.xml'], $graph->sourcePartNames());

        $officeDocument = $graph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $t->same(true, $officeDocument['valid']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml; profile=docx', $officeDocument['relationships'][0]['contentType']);
        $t->same([], $officeDocument['relationships'][0]['issues']);

        $coreProperties = $graph->preflightCoreProperties();
        $t->same(true, $coreProperties['valid']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml; audit=core', $coreProperties['relationships'][0]['contentType']);

        $documentTargets = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $documentTargets[$target['id']] = $target;
        }
        $t->same('image/png; review=thumbnail', $documentTargets['rIdHero']['contentType']);
        $t->same(true, $documentTargets['rIdHero']['valid']);

        $digitalSignatures = $graph->preflightDigitalSignatures();
        $t->same(true, $digitalSignatures[0]['valid']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-origin; profile=opc', $digitalSignatures[0]['contentType']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml; profile=opc', $digitalSignatures[0]['signatures'][0]['contentType']);

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-params.xml');
        $t->same('application/vnd.openxmlformats-package.relationships+xml; charset=UTF-8', $transforms[0]['referenceTargetContentType']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[0]['referenceContentType']);
        $t->same(true, $transforms[0]['referenceContentTypeMatches']);
        $t->same(true, $transforms[0]['valid']);
        $t->same(['rIdHero'], $transforms[0]['relationshipIds']);
        $t->same([], $transforms[0]['issues']);
    },
    'rejects malformed OPC content types XML and unsafe part names' => static function (TestRunner $t): void {
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="urn:bad"/>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml"/></Types>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml" ContentType="application/xml"/><Default Extension="XML" ContentType="text/xml"/></Types>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/document.xml" ContentType="application/xml"/><Override PartName="word/document.xml" ContentType="application/xml"/></Types>'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml('<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="../evil.xml" ContentType="application/xml"/></Types>'));
    },
    'enforces OPC content type XML part-name and extension record grammar' => static function (TestRunner $t): void {
        $builder = new OpcContentTypes();
        $builder->addDefault('.xml', 'application/xml');
        $builder->addOverride('word/document.xml', 'application/xml');

        $t->same('application/xml', $builder->contentTypeForPart('/word/document.xml'));
        $t->contains('Extension="xml"', $builder->toXml());
        $t->contains('PartName="/word/document.xml"', $builder->toXml());

        foreach ([
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension=".xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="word/document.xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/./document.xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word//document.xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/%2E/document.xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/media/raw source.png" ContentType="image/png"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/media/trailing./source.png" ContentType="image/png"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/media/source%2E" ContentType="image/png"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="/word/document.xml/" ContentType="application/xml"/></Types>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }
    },
    'rejects OPC Default extension whitespace and control bytes' => static function (TestRunner $t): void {
        $types = new OpcContentTypes();
        $types->addDefault('Jpeg', 'image/jpeg');

        $t->same(['Jpeg' => 'image/jpeg'], $types->defaults());
        $t->same('image/jpeg', $types->contentTypeForPart('/word/media/cover.JPEG'));

        foreach (['', ' ', 'x y', "xml\n", "xml\t"] as $extension) {
            $t->throws(\InvalidArgumentException::class, static function () use ($extension): void {
                $types = new OpcContentTypes();
                $types->addDefault($extension, 'application/xml');
            });
        }

        foreach ([
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="x y" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml&#x0A;" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Default Extension="xml&#x09;" ContentType="application/xml"/></Types>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }
    },
    'rejects OPC package URI part names with raw whitespace or trailing dot segments' => static function (TestRunner $t): void {
        $validXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/media/source%20diagram.png" ContentType="image/png"/>
</Types>
XML;

        $types = OpcContentTypes::fromXml($validXml);
        $t->same('image/png', $types->contentTypeForPart('/word/media/source diagram.png'));
        $t->same('image/png', $types->contentTypeForPart('/word/media/source%20diagram.png'));
        $t->contains('PartName="/word/media/source%20diagram.png"', $types->toXml());

        foreach ([
            '/word/media/raw source.png',
            '/word/media/trailing./source.png',
            '/word/media/source%2E',
        ] as $partName) {
            $xml = '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="' . $partName . '" ContentType="image/png"/></Types>';
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }

        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship('rIdTrailingDotSegment', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', 'media/trailing./image.png'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdTrailingDotSegment'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/word/trailing./document.xml'));

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $validXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdDocument" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="word/document.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdTrailingDotSegment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/trailing./image.png"/></Relationships>'],
            ['name' => 'word/media/source diagram.png', 'data' => 'PNG'],
        ]));

        $targets = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $targets[$target['id']] = $target;
        }

        $t->same(false, $targets['rIdTrailingDotSegment']['valid']);
        $t->same(['invalid-target', 'internal-target-trailing-dot-segment'], $targets['rIdTrailingDotSegment']['issues']);
        $t->same(null, $targets['rIdTrailingDotSegment']['exists']);
        $t->same(null, $targets['rIdTrailingDotSegment']['contentType']);
    },
    'rejects OPC package path control bytes after URI decoding' => static function (TestRunner $t): void {
        $validXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/media/source%20diagram.png" ContentType="image/png"/>
  <Override PartName="/customXml/%C3%A9preuve.xml" ContentType="application/xml"/>
</Types>
XML;

        $types = OpcContentTypes::fromXml($validXml);
        $utf8Name = "\u{00E9}" . 'preuve.xml';

        $t->same('/word/media/source diagram.png', OpcPackagePath::canonicalPartNameFromUri('/word/media/source%20diagram.png'));
        $t->same('/word/media/source%20diagram.png', OpcPackagePath::partNameToUri('/word/media/source diagram.png'));
        $t->same('image/png', $types->contentTypeForPart('/word/media/source%20diagram.png'));
        $t->same('application/xml', $types->contentTypeForPart('/customXml/' . $utf8Name));

        foreach ([
            '/word/media/source%01diagram.png',
            '/word/media/source%1Fdiagram.png',
            '/word/media/source%7Fdiagram.png',
        ] as $partName) {
            $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartNameFromUri($partName));

            $xml = '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '"><Override PartName="' . $partName . '" ContentType="image/png"/></Types>';
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }

        foreach ([
            "/word/media/source\ndiagram.png",
            "/word/media/source\tdiagram.png",
            "/word/media/source\x7Fdiagram.png",
        ] as $partName) {
            $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName($partName));
        }
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
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><review:Audit/><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png" review:origin="fixture"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"><review:Note/></Relationship></Relationships>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($xml, '/word/document.xml'));
        }
    },
    'honors bounded OPC markup compatibility preserve declarations' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveElements="review:Audit review:Note" mc:PreserveAttributes="review:source review:origin" review:source="import-preflight">
  <review:Audit packet="docx"/>
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml" review:origin="fixture">
    <review:Note value="ignored"/>
  </Default>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $relationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveElements="review:*" mc:PreserveAttributes="review:*" review:source="import-preflight">
  <review:Audit packet="docx"/>
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml" review:label="main">
    <review:Trace value="ignored"/>
  </Relationship>
</Relationships>
XML;

        $types = OpcContentTypes::fromXml($contentTypesXml);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $types->contentTypeForPart('/_rels/.rels'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $types->contentTypeForPart('/word/document.xml'));

        $relationships = OpcRelationships::fromXml($relationshipsXml);
        $t->same('/word/document.xml', $relationships->resolveTarget('rIdDocument'));
        $t->same(1, count($relationships->all()));

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $relationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
        ]));

        $root = $graph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $t->same(true, $root['valid']);
        $t->same('/word/document.xml', $root['relationships'][0]['targetPart']);
        $t->same(['rIdDocument'], array_map(
            static fn (OpcRelationship $relationship): string => $relationship->id,
            $graph->requireRelationshipsForSource('/')->all()
        ));

        foreach ([
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveElements="review"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveElements="missing:Audit"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" mc:PreserveAttributes="review:origin"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" xmlns:p="' . OpcContentTypes::NAMESPACE_URI . '" mc:Ignorable="review" mc:PreserveElements="p:Default"><Default Extension="xml" ContentType="application/xml"/></Types>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }

        foreach ([
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveElements="review"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" mc:Ignorable="review" mc:PreserveAttributes="missing:origin"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" mc:PreserveElements="review:*"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
            '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:review="urn:wordpress-review" xmlns:r="' . OpcRelationships::NAMESPACE_URI . '" mc:Ignorable="review" mc:PreserveAttributes="r:Id"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></Relationships>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($xml, '/word/document.xml'));
        }
    },
    'processes bounded OPC markup compatibility ProcessContent wrappers' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:pc="urn:wordpress-opc-process-content" mc:Ignorable="pc" mc:ProcessContent="pc:Records">
  <pc:Records>
    <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
    <Default Extension="xml" ContentType="application/xml"/>
    <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  </pc:Records>
  <pc:Ignored>
    <Override PartName="/word/hidden.xml" ContentType="application/xml"/>
  </pc:Ignored>
</Types>
XML;

        $relationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:pc="urn:wordpress-opc-process-content" mc:Ignorable="pc" mc:ProcessContent="pc:Records">
  <pc:Records>
    <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
    <Relationship Id="rIdAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/review.xml"/>
  </pc:Records>
  <pc:Ignored>
    <Relationship Id="rIdHidden" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/hidden.xml"/>
  </pc:Ignored>
</Relationships>
XML;

        $types = OpcContentTypes::fromXml($contentTypesXml);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $types->contentTypeForPart('/word/document.xml'));
        $t->same('application/xml', $types->contentTypeForPart('/word/review.xml'));
        $t->same(null, $types->contentTypeForPart('/word/hidden.bin'));
        $t->same([
            'rels' => 'application/vnd.openxmlformats-package.relationships+xml',
            'xml' => 'application/xml',
        ], $types->defaults());
        $t->same([
            '/word/document.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
        ], $types->overrides());

        $relationships = OpcRelationships::fromXml($relationshipsXml);
        $t->same(2, count($relationships->all()));
        $t->same('/word/document.xml', $relationships->resolveTarget('rIdDocument'));
        $t->same('/word/review.xml', $relationships->resolveTarget('rIdAudit'));
        $t->same(null, $relationships->byId('rIdHidden'));

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $relationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/review.xml', 'data' => '<review/>'],
            ['name' => 'word/hidden.xml', 'data' => '<hidden/>'],
        ]));

        $t->same(['rIdDocument', 'rIdAudit'], array_map(
            static fn (OpcRelationship $relationship): string => $relationship->id,
            $graph->requireRelationshipsForSource('/')->all()
        ));
        $root = $graph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $t->same(1, $root['relationshipCount']);
        $t->same(true, $root['valid']);
        $t->same('/word/document.xml', $root['relationships'][0]['targetPart']);

        $targets = [];
        foreach ($graph->preflightTargetsForSource('/') as $target) {
            $targets[$target['id']] = $target;
        }
        $t->same(['rIdDocument', 'rIdAudit'], array_keys($targets));
        $t->same('/word/review.xml', $targets['rIdAudit']['target']);
        $t->same('application/xml', $targets['rIdAudit']['contentType']);
        $t->same(true, $targets['rIdAudit']['valid']);

        foreach ([
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:pc="urn:wordpress-opc-process-content" mc:Ignorable="pc" mc:ProcessContent="pc"><pc:Records><Default Extension="xml" ContentType="application/xml"/></pc:Records></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" mc:ProcessContent="missing:Records"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:pc="urn:wordpress-opc-process-content" mc:ProcessContent="pc:Records"><Default Extension="xml" ContentType="application/xml"/></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:pc="urn:wordpress-opc-process-content" mc:Ignorable="pc" mc:ProcessContent="pc:Records"><pc:Records>text<Default Extension="xml" ContentType="application/xml"/></pc:Records></Types>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }

        $badRelationshipsXml = '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:pc="urn:wordpress-opc-process-content" mc:Ignorable="pc" mc:ProcessContent="pc:Records"><pc:Records>text<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></pc:Records></Relationships>';
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($badRelationshipsXml, '/word/document.xml'));
    },
    'processes bounded OPC markup compatibility AlternateContent records' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:p="http://schemas.openxmlformats.org/package/2006/content-types" xmlns:future="urn:future-opc-records">
  <mc:AlternateContent>
    <mc:Choice Requires="future">
      <Default Extension="hidden" ContentType="application/hidden"/>
    </mc:Choice>
    <mc:Fallback>
      <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
      <Default Extension="xml" ContentType="application/xml"/>
    </mc:Fallback>
  </mc:AlternateContent>
  <mc:AlternateContent>
    <mc:Choice Requires="p">
      <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
      <Override PartName="/word/review.xml" ContentType="application/xml"/>
    </mc:Choice>
    <mc:Fallback>
      <Override PartName="/word/fallback.xml" ContentType="application/xml"/>
    </mc:Fallback>
  </mc:AlternateContent>
</Types>
XML;

        $relationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:mc="http://schemas.openxmlformats.org/markup-compatibility/2006" xmlns:r="http://schemas.openxmlformats.org/package/2006/relationships" xmlns:future="urn:future-opc-relationships">
  <mc:AlternateContent>
    <mc:Choice Requires="future">
      <Relationship Id="rIdHiddenDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/hidden.xml"/>
    </mc:Choice>
    <mc:Fallback>
      <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
    </mc:Fallback>
  </mc:AlternateContent>
  <mc:AlternateContent>
    <mc:Choice Requires="r">
      <Relationship Id="rIdAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/review.xml"/>
    </mc:Choice>
    <mc:Fallback>
      <Relationship Id="rIdFallbackAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/fallback.xml"/>
    </mc:Fallback>
  </mc:AlternateContent>
</Relationships>
XML;

        $types = OpcContentTypes::fromXml($contentTypesXml);
        $t->same([
            'rels' => 'application/vnd.openxmlformats-package.relationships+xml',
            'xml' => 'application/xml',
        ], $types->defaults());
        $t->same([
            '/word/document.xml' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            '/word/review.xml' => 'application/xml',
        ], $types->overrides());
        $t->same(null, $types->contentTypeForPart('/word/hidden.hidden'));
        $t->same('application/xml', $types->contentTypeForPart('/word/fallback.xml'));

        $relationships = OpcRelationships::fromXml($relationshipsXml);
        $t->same(['rIdDocument', 'rIdAudit'], array_map(
            static fn (OpcRelationship $relationship): string => $relationship->id,
            $relationships->all()
        ));
        $t->same('/word/document.xml', $relationships->resolveTarget('rIdDocument'));
        $t->same('/word/review.xml', $relationships->resolveTarget('rIdAudit'));

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $relationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/review.xml', 'data' => '<review/>'],
            ['name' => 'word/fallback.xml', 'data' => '<fallback/>'],
        ]));

        $root = $graph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $t->same(true, $root['valid']);
        $t->same('/word/document.xml', $root['relationships'][0]['targetPart']);

        $targets = [];
        foreach ($graph->preflightTargetsForSource('/') as $target) {
            $targets[$target['id']] = $target;
        }
        $t->same(['rIdDocument', 'rIdAudit'], array_keys($targets));
        $t->same('/word/review.xml', $targets['rIdAudit']['target']);
        $t->same(true, $targets['rIdAudit']['valid']);

        foreach ([
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:p="' . OpcContentTypes::NAMESPACE_URI . '"><mc:AlternateContent><mc:Choice><Default Extension="xml" ContentType="application/xml"/></mc:Choice></mc:AlternateContent></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:future="urn:future-opc-records"><mc:AlternateContent><mc:Choice Requires="future"><Default Extension="xml" ContentType="application/xml"/></mc:Choice></mc:AlternateContent></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:p="' . OpcContentTypes::NAMESPACE_URI . '"><mc:AlternateContent><mc:Fallback/><mc:Choice Requires="p"><Default Extension="xml" ContentType="application/xml"/></mc:Choice></mc:AlternateContent></Types>',
            '<Types xmlns="' . OpcContentTypes::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:p="' . OpcContentTypes::NAMESPACE_URI . '"><mc:AlternateContent><mc:Choice Requires="p">text<Default Extension="xml" ContentType="application/xml"/></mc:Choice></mc:AlternateContent></Types>',
        ] as $xml) {
            $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($xml));
        }

        $badRelationshipsXml = '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '" xmlns:mc="' . OpcMarkupCompatibility::NAMESPACE_URI . '" xmlns:r="' . OpcRelationships::NAMESPACE_URI . '"><mc:AlternateContent><mc:Choice Requires="missing"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/image.png"/></mc:Choice><mc:Fallback/></mc:AlternateContent></Relationships>';
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($badRelationshipsXml, '/word/document.xml'));
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
    'preflights duplicate OPC relationship parts resolving to the same source' => static function (TestRunner $t): void {
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

        $encodedRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdEncodedImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/encoded.png"/>
</Relationships>
XML;

        $rawRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdRawImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/raw.png"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'word/review source.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/review%20source.xml.rels', 'data' => $encodedRelationshipsXml],
            ['name' => 'word/_rels/review source.xml.rels', 'data' => $rawRelationshipsXml],
            ['name' => 'word/media/encoded.png', 'data' => 'PNG'],
            ['name' => 'word/media/raw.png', 'data' => 'PNG'],
        ]);

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same([
            '/_rels/.rels',
            '/word/_rels/review%20source.xml.rels',
            '/word/_rels/review source.xml.rels',
        ], array_keys($loads));

        $t->same(true, $loads['/_rels/.rels']['loaded']);
        $t->same(1, $loads['/_rels/.rels']['relationshipCount']);
        $t->same([], $loads['/_rels/.rels']['issues']);

        foreach ([
            '/word/_rels/review%20source.xml.rels',
            '/word/_rels/review source.xml.rels',
        ] as $relationshipPartName) {
            $t->same('/word/review source.xml', $loads[$relationshipPartName]['relationshipSource']);
            $t->same(true, $loads[$relationshipPartName]['sourceExists']);
            $t->same(false, $loads[$relationshipPartName]['loaded']);
            $t->same(null, $loads[$relationshipPartName]['relationshipCount']);
            $t->same([
                '/word/_rels/review source.xml.rels',
                '/word/_rels/review%20source.xml.rels',
            ], $loads[$relationshipPartName]['duplicateRelationshipPartNames']);
            $t->same(['duplicate-relationship-source'], $loads[$relationshipPartName]['issues']);
            $t->same(false, $loads[$relationshipPartName]['valid']);
        }

        $t->throws(\RuntimeException::class, static fn (): OpcRelationshipGraph => OpcRelationshipGraph::fromPackage($package));
    },
    'preflights case-insensitive OPC part-name equivalence collisions' => static function (TestRunner $t): void {
        $types = new OpcContentTypes();
        $types->addDefault('xml', 'application/xml');
        $types->addOverride('/Word/Document.XML', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml');

        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $types->contentTypeForPart('/word/document.xml'));
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $types->contentTypeForPart('/WORD/DOCUMENT.XML'));
        $t->same(['/Word/Document.XML' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml'], $types->overrides());
        $t->throws(\InvalidArgumentException::class, static fn (): null => $types->addOverride('/word/document.xml', 'application/xml'));

        $duplicateOverridesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/WORD/DOCUMENT.XML" ContentType="application/xml"/>
</Types>
XML;
        $t->throws(\InvalidArgumentException::class, static fn (): OpcContentTypes => OpcContentTypes::fromXml($duplicateOverridesXml));

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/Document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/Hero.PNG', 'data' => 'PNG'],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
        ]);

        $equivalence = [];
        foreach (OpcRelationshipGraph::preflightPackagePartNameEquivalence($package) as $part) {
            if ($part['valid']) {
                continue;
            }

            $equivalence[$part['partName']] = $part;
        }

        $t->same([
            '/word/document.xml',
            '/word/Document.xml',
            '/word/media/Hero.PNG',
            '/word/media/hero.png',
        ], array_keys($equivalence));

        foreach (['/word/document.xml', '/word/Document.xml'] as $partName) {
            $t->same('/word/document.xml', $equivalence[$partName]['equivalenceKey']);
            $t->same(['/word/Document.xml', '/word/document.xml'], $equivalence[$partName]['equivalentPartNames']);
            $t->same(['equivalent-part-name-case-collision'], $equivalence[$partName]['issues']);
            $t->same(false, $equivalence[$partName]['valid']);
        }

        foreach (['/word/media/Hero.PNG', '/word/media/hero.png'] as $partName) {
            $t->same('/word/media/hero.png', $equivalence[$partName]['equivalenceKey']);
            $t->same(['/word/media/Hero.PNG', '/word/media/hero.png'], $equivalence[$partName]['equivalentPartNames']);
            $t->same(['equivalent-part-name-case-collision'], $equivalence[$partName]['issues']);
        }

        $t->throws(\RuntimeException::class, static fn (): OpcRelationshipGraph => OpcRelationshipGraph::fromPackage($package));
    },
    'preflights invalid OPC package part names before graph construction' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/', 'data' => ''],
            ['name' => 'word/media/review.png?variant=source', 'data' => 'PNG'],
            ['name' => 'word/media/trailing./image.png', 'data' => 'PNG'],
            ['name' => 'word/custom#source.xml', 'data' => '<review/>'],
        ]);

        $summary = OpcRelationshipGraph::preflightPackagePartNames($package);
        $parts = [];
        foreach ($summary['parts'] as $part) {
            $parts[$part['entryName']] = $part;
        }

        $t->same(false, $summary['valid']);
        $t->same(7, $summary['entryCount']);
        $t->same(6, $summary['packagePartCount']);
        $t->same(1, $summary['directoryEntryCount']);
        $t->same(3, $summary['invalidPartCount']);
        $t->same([
            'word/media/review.png?variant=source',
            'word/media/trailing./image.png',
            'word/custom#source.xml',
        ], $summary['invalidPartNames']);
        $t->same([
            'invalid-opc-part-name',
            'part-name-query-or-fragment',
            'part-name-trailing-dot-segment',
        ], $summary['issues']);

        $t->same('/word/document.xml', $parts['word/document.xml']['partName']);
        $t->same(true, $parts['word/document.xml']['valid']);
        $t->same(false, $parts['word/media/']['isPackagePart']);
        $t->same(true, $parts['word/media/']['valid']);
        $t->same(null, $parts['word/media/review.png?variant=source']['partName']);
        $t->same(['invalid-opc-part-name', 'part-name-query-or-fragment'], $parts['word/media/review.png?variant=source']['issues']);
        $t->contains('query or fragment', $parts['word/media/review.png?variant=source']['parseError']);
        $t->same(['invalid-opc-part-name', 'part-name-trailing-dot-segment'], $parts['word/media/trailing./image.png']['issues']);

        try {
            OpcRelationshipGraph::fromPackage($package);
            $t->true(false, 'Invalid OPC package part names should block graph construction');
        } catch (\RuntimeException $exception) {
            $t->contains('OPC package contains invalid part names', $exception->getMessage());
            $t->contains('word/media/review.png?variant=source', $exception->getMessage());
            $t->contains('word/media/trailing./image.png', $exception->getMessage());
            $t->contains('word/custom#source.xml', $exception->getMessage());
        }
    },
    'loads case-equivalent OPC content types item for relationship filtering' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
</Types>
XML;

        $rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
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

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].XML', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
        ]);

        $contentTypes = OpcRelationshipGraph::preflightContentTypesInPackage($package);
        $t->same('/[Content_Types].XML', $contentTypes['partName']);
        $t->same(true, $contentTypes['present']);
        $t->same(true, $contentTypes['valid']);

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same(true, $loads['/_rels/.rels']['loaded']);
        $t->same(true, $loads['/word/_rels/document.xml.rels']['loaded']);
        $t->same(false, $loads['/word/_rels/comments.xml.rels']['loaded']);
        $t->same('application/xml', $loads['/word/_rels/comments.xml.rels']['contentType']);
        $t->same(['invalid-relationship-content-type'], $loads['/word/_rels/comments.xml.rels']['issues']);

        $graph = OpcRelationshipGraph::fromPackage($package);
        $t->same(['/', '/word/document.xml'], $graph->sourcePartNames());

        $packageParts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $packageParts[$part['partName']] = $part;
        }

        $t->true(!isset($packageParts['/[Content_Types].XML']), 'Case-equivalent content types item should not be treated as a package part');
        $t->same(false, $packageParts['/word/_rels/comments.xml.rels']['relationshipSourceLoaded']);
        $t->same('skipped', $packageParts['/word/_rels/comments.xml.rels']['relationshipPartLoadAction']);
        $t->same('invalid-relationship-content-type', $packageParts['/word/_rels/comments.xml.rels']['relationshipPartLoadReason']);

        $t->true(OpcRelationships::packageHasRelationshipsForSource($package, '/word/document.xml'));
        $t->same(false, OpcRelationships::packageHasRelationshipsForSource($package, '/word/comments.xml'));
        $t->throws(\RuntimeException::class, static fn (): OpcRelationships => OpcRelationships::fromPackage($package, '/word/comments.xml'));
    },
    'resolves case-equivalent OPC relationship targets to stored package parts' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/Word/Document.XML" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/Word/Styles.XML" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML;

        $rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'Word/Document.XML', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'Word/_rels/Document.XML.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'Word/Styles.XML', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
        ]));

        $t->same('/Word/Document.XML', $graph->firstTargetOfType(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE));
        $t->true($graph->hasRelationshipsForSource('/word/document.xml'));
        $t->true($graph->relationshipsForSource('/word/document.xml') instanceof OpcRelationships);
        $t->same('/Word/_rels/Document.XML.rels', $graph->requireRelationshipsForSource('/word/document.xml')->relationshipPartName());

        $root = $graph->preflightOfficeDocumentRoot(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES);
        $t->same(true, $root['valid']);
        $t->same('/Word/Document.XML', $root['relationships'][0]['targetPart']);
        $t->same(true, $root['relationships'][0]['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $root['relationships'][0]['contentType']);
        $t->same([], $root['relationships'][0]['issues']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(['rIdDocument', 'rIdStyles'], array_keys($closureById));
        $t->same('/Word/Document.XML', $closureById['rIdDocument']['targetPart']);
        $t->same('/Word/Document.XML', $closureById['rIdStyles']['source']);
        $t->same('/Word/Styles.XML', $closureById['rIdStyles']['targetPart']);
        $t->same(true, $closureById['rIdStyles']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $closureById['rIdStyles']['contentType']);
        $t->same(true, $closureById['rIdStyles']['valid']);
    },
    'preflights OPC content type override part-name provenance' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/missing.xml" ContentType="application/xml"/>
</Types>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => 'Word/Document.XML', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'Word/Styles.XML', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties"/>'],
        ]));

        $overrides = [];
        foreach ($graph->preflightContentTypeOverrides() as $override) {
            $overrides[$override['partName']] = $override;
        }

        $t->same('/Word/Document.XML', $overrides['/word/document.xml']['packagePartName']);
        $t->same(true, $overrides['/word/document.xml']['exists']);
        $t->same(false, $overrides['/word/document.xml']['partNameExactMatch']);
        $t->same(true, $overrides['/word/document.xml']['partNameEquivalentMatch']);
        $t->same(true, $overrides['/word/document.xml']['valid']);
        $t->same([], $overrides['/word/document.xml']['issues']);

        $t->same('/Word/Styles.XML', $overrides['/word/styles.xml']['packagePartName']);
        $t->same(false, $overrides['/word/styles.xml']['partNameExactMatch']);
        $t->same(true, $overrides['/word/styles.xml']['partNameEquivalentMatch']);
        $t->same(true, $overrides['/word/styles.xml']['valid']);

        $t->same('/docProps/core.xml', $overrides['/docProps/core.xml']['packagePartName']);
        $t->same(true, $overrides['/docProps/core.xml']['partNameExactMatch']);
        $t->same(false, $overrides['/docProps/core.xml']['partNameEquivalentMatch']);
        $t->same(true, $overrides['/docProps/core.xml']['valid']);

        $t->same(null, $overrides['/word/missing.xml']['packagePartName']);
        $t->same(false, $overrides['/word/missing.xml']['exists']);
        $t->same(false, $overrides['/word/missing.xml']['partNameExactMatch']);
        $t->same(false, $overrides['/word/missing.xml']['partNameEquivalentMatch']);
        $t->same(false, $overrides['/word/missing.xml']['valid']);
        $t->same(['override-target-missing-part'], $overrides['/word/missing.xml']['issues']);

        $consistencyOverrides = [];
        foreach ($graph->preflightPackageConsistency()['contentTypeOverrides'] as $override) {
            $consistencyOverrides[$override['partName']] = $override;
        }

        $t->same('/Word/Document.XML', $consistencyOverrides['/word/document.xml']['packagePartName']);
        $t->same(true, $consistencyOverrides['/word/document.xml']['partNameEquivalentMatch']);
        $t->same(null, $consistencyOverrides['/word/missing.xml']['packagePartName']);
        $t->same(false, $consistencyOverrides['/word/missing.xml']['exists']);
    },
    'loads OPC relationships from source-equivalent package entries' => static function (TestRunner $t): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.XML"/>
</Relationships>
XML;

        $reviewRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => 'Word/Document.XML', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'Word/_rels/Document.XML.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'Word/styles.XML', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/review source.xml', 'data' => '<review/>'],
            ['name' => 'word/_rels/review source.xml.rels', 'data' => $reviewRelationshipsXml],
            ['name' => 'word/media/review.png', 'data' => 'PNG'],
        ]);

        $t->true(OpcRelationships::packageHasRelationshipsForSource($package, '/word/document.xml'));
        $caseEquivalent = OpcRelationships::fromPackage($package, '/word/document.xml');
        $t->same('/Word/_rels/Document.XML.rels', $caseEquivalent->relationshipPartName());
        $t->same('/Word/styles.XML', $caseEquivalent->resolveTarget('rIdStyles'));

        $t->true(OpcRelationships::packageHasRelationshipsForSource($package, '/word/review source.xml'));
        $spaceEquivalent = OpcRelationships::fromPackage($package, '/word/review source.xml');
        $t->same('/word/_rels/review%20source.xml.rels', $spaceEquivalent->relationshipPartName());
        $t->same('/word/media/review.png', $spaceEquivalent->resolveTarget('rIdReviewImage'));

        $duplicatePackage = ZipPackage::fromParts([
            ['name' => 'word/review source.xml', 'data' => '<review/>'],
            ['name' => 'word/_rels/review%20source.xml.rels', 'data' => $reviewRelationshipsXml],
            ['name' => 'word/_rels/review source.xml.rels', 'data' => $reviewRelationshipsXml],
        ]);

        $t->true(OpcRelationships::packageHasRelationshipsForSource($duplicatePackage, '/word/review source.xml'));
        $t->throws(\RuntimeException::class, static fn (): OpcRelationships => OpcRelationships::fromPackage($duplicatePackage, '/word/review source.xml'));
    },
    'parses package level OPC relationships and resolves package root targets' => static function (TestRunner $t) use ($packageRelationshipsXml): void {
        $relationships = OpcRelationships::fromXml($packageRelationshipsXml);

        $t->same('/_rels/.rels', $relationships->relationshipPartName());
        $t->same(3, count($relationships->all()));
        $t->same('rIdDocument', $relationships->all()[0]->id);
        $t->same('word/document.xml', $relationships->byId('rIdDocument')?->target);
        $t->same('/word/document.xml', $relationships->resolveTarget('rIdDocument'));
        $t->same('/docProps/core.xml', $relationships->resolveTarget('rIdCore'));
        $t->same('https://example.test/source%20packet.html?post=42#review', $relationships->resolveTarget('rIdExternalAudit'));
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

        $internalReferences = [];
        foreach ($graph->preflightInternalTargetReferences('/word/document.xml') as $reference) {
            $internalReferences[$reference['id']] = $reference;
        }

        $t->same(['rIdBookmark', 'rIdReviewerState', 'rIdStyles'], array_keys($internalReferences));
        $t->same('/word/document.xml', $internalReferences['rIdBookmark']['source']);
        $t->same('/word/document.xml#review-bookmark', $internalReferences['rIdBookmark']['target']);
        $t->same('/word/document.xml', $internalReferences['rIdBookmark']['targetPart']);
        $t->same(null, $internalReferences['rIdBookmark']['targetQuery']);
        $t->same('review-bookmark', $internalReferences['rIdBookmark']['targetFragment']);
        $t->same(true, $internalReferences['rIdBookmark']['sameSourceReference']);
        $t->same(true, $internalReferences['rIdBookmark']['valid']);
        $t->same([], $internalReferences['rIdBookmark']['issues']);
        $t->same('/word/document.xml?review=ready#packet', $internalReferences['rIdReviewerState']['target']);
        $t->same('/word/document.xml', $internalReferences['rIdReviewerState']['targetPart']);
        $t->same('review=ready', $internalReferences['rIdReviewerState']['targetQuery']);
        $t->same('packet', $internalReferences['rIdReviewerState']['targetFragment']);
        $t->same(true, $internalReferences['rIdReviewerState']['sameSourceReference']);
        $t->same('/word/styles.xml', $internalReferences['rIdStyles']['targetPart']);
        $t->same(null, $internalReferences['rIdStyles']['targetQuery']);
        $t->same(null, $internalReferences['rIdStyles']['targetFragment']);
        $t->same(false, $internalReferences['rIdStyles']['sameSourceReference']);

        $customXmlReferences = $graph->preflightInternalTargetReferences(
            '/word/document.xml',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml'
        );
        $t->same(['rIdReviewerState'], array_column($customXmlReferences, 'id'));
        $t->same('review=ready', $customXmlReferences[0]['targetQuery']);
        $t->same('packet', $customXmlReferences[0]['targetFragment']);

        $partReferences = [];
        foreach ($graph->packagePartReferenceInventory('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $reference) {
            $partReferences[$reference['partName']] = $reference;
        }
        $documentReferencesById = [];
        foreach ($partReferences['/word/document.xml']['directReferences'] as $reference) {
            $documentReferencesById[$reference['id']] = $reference;
        }
        $styleReferencesById = [];
        foreach ($partReferences['/word/styles.xml']['directReferences'] as $reference) {
            $styleReferencesById[$reference['id']] = $reference;
        }

        $t->same(3, $partReferences['/word/document.xml']['directReferenceCount']);
        $t->same(null, $documentReferencesById['rIdBookmark']['targetQuery']);
        $t->same('review-bookmark', $documentReferencesById['rIdBookmark']['targetFragment']);
        $t->same(true, $documentReferencesById['rIdBookmark']['sameSourceReference']);
        $t->same('review=ready', $documentReferencesById['rIdReviewerState']['targetQuery']);
        $t->same('packet', $documentReferencesById['rIdReviewerState']['targetFragment']);
        $t->same(true, $documentReferencesById['rIdReviewerState']['sameSourceReference']);
        $t->same(null, $styleReferencesById['rIdStyles']['targetQuery']);
        $t->same(null, $styleReferencesById['rIdStyles']['targetFragment']);
        $t->same(false, $styleReferencesById['rIdStyles']['sameSourceReference']);

        $coverage = $graph->packagePartRelationshipCoverageSummary(
            '/',
            OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE
        );
        $t->same(1, $coverage['directQueryReferenceCount']);
        $t->same(2, $coverage['directFragmentReferenceCount']);
        $t->same(2, $coverage['directSameSourceReferenceCount']);
        $t->same(1, $coverage['reachableQueryReferenceCount']);
        $t->same(2, $coverage['reachableFragmentReferenceCount']);
        $t->same(2, $coverage['reachableSameSourceReferenceCount']);

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
    'preflights OPC internal relationship target query and fragment metadata for importer review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdBookmark" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#review-bookmark"/>
  <Relationship Id="rIdReviewState" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="?review=ready#packet"/>
  <Relationship Id="rIdStylesQuery" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml?theme=light"/>
  <Relationship Id="rIdExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
  <Relationship Id="rIdEscape" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../../evil.xml?x=1#bad"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
        ]));

        $references = [];
        foreach ($graph->preflightInternalTargetReferences('/word/document.xml') as $reference) {
            $references[$reference['id']] = $reference;
        }

        $t->same(['rIdBookmark', 'rIdReviewState', 'rIdStylesQuery', 'rIdEscape'], array_keys($references));
        $t->same('/word/document.xml#review-bookmark', $references['rIdBookmark']['target']);
        $t->same('/word/document.xml', $references['rIdBookmark']['targetPart']);
        $t->same(null, $references['rIdBookmark']['targetQuery']);
        $t->same('review-bookmark', $references['rIdBookmark']['targetFragment']);
        $t->same(true, $references['rIdBookmark']['sameSourceReference']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $references['rIdBookmark']['contentType']);
        $t->same('/word/document.xml?review=ready#packet', $references['rIdReviewState']['target']);
        $t->same('/word/document.xml', $references['rIdReviewState']['targetPart']);
        $t->same('review=ready', $references['rIdReviewState']['targetQuery']);
        $t->same('packet', $references['rIdReviewState']['targetFragment']);
        $t->same(true, $references['rIdReviewState']['sameSourceReference']);
        $t->same('/word/styles.xml?theme=light', $references['rIdStylesQuery']['target']);
        $t->same('/word/styles.xml', $references['rIdStylesQuery']['targetPart']);
        $t->same('theme=light', $references['rIdStylesQuery']['targetQuery']);
        $t->same(null, $references['rIdStylesQuery']['targetFragment']);
        $t->same(false, $references['rIdStylesQuery']['sameSourceReference']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $references['rIdStylesQuery']['contentType']);
        $t->same('../../evil.xml?x=1#bad', $references['rIdEscape']['target']);
        $t->same(null, $references['rIdEscape']['targetPart']);
        $t->same(null, $references['rIdEscape']['targetQuery']);
        $t->same(null, $references['rIdEscape']['targetFragment']);
        $t->same(false, $references['rIdEscape']['sameSourceReference']);
        $t->same(null, $references['rIdEscape']['exists']);
        $t->same(false, $references['rIdEscape']['valid']);
        $t->same(['invalid-target', 'internal-target-package-root-traversal'], $references['rIdEscape']['issues']);

        $customXmlReferences = $graph->preflightInternalTargetReferences(
            '/word/document.xml',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml'
        );
        $t->same(['rIdReviewState'], array_column($customXmlReferences, 'id'));
        $t->same([], $graph->preflightInternalTargetReferences('/word/missing.xml'));
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
        $t->same(['invalid-target', 'internal-target-package-root-traversal'], $preflight['rIdEscape']['issues']);

        $imagePreflight = $graph->preflightTargetsForSource('/word/document.xml', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image');
        $t->same(['rIdMissingImage', 'rIdEscape'], array_column($imagePreflight, 'id'));
        $t->same([], $graph->preflightTargetsForSource('/word/missing.xml'));
    },
    'classifies invalid internal OPC relationship target URI references' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdAbsoluteUri" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://example.test/review.png"/>
  <Relationship Id="rIdAuthority" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="//cdn.example.test/review.png"/>
  <Relationship Id="rIdTraversal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../../evil.xml"/>
  <Relationship Id="rIdBadEscape" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/bad%ZZ.png"/>
  <Relationship Id="rIdRawSpace" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/raw space.png"/>
  <Relationship Id="rIdEncodedSlash" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media%2Fhidden.png"/>
  <Relationship Id="rIdEncodedDotSegment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="media/%2E%2E/styles.xml"/>
  <Relationship Id="rIdEncodedBackslash" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media%5Chidden.png"/>
  <Relationship Id="rIdEncodedNul" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media%00hidden.png"/>
  <Relationship Id="rIdTrailingDotSegment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/trailing./image.png"/>
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

        $t->same([
            'rIdAbsoluteUri',
            'rIdAuthority',
            'rIdTraversal',
            'rIdBadEscape',
            'rIdRawSpace',
            'rIdEncodedSlash',
            'rIdEncodedDotSegment',
            'rIdEncodedBackslash',
            'rIdEncodedNul',
            'rIdTrailingDotSegment',
        ], array_keys($preflight));
        $t->same(['invalid-target', 'internal-target-absolute-uri'], $preflight['rIdAbsoluteUri']['issues']);
        $t->same(['invalid-target', 'internal-target-network-path-reference'], $preflight['rIdAuthority']['issues']);
        $t->same(['invalid-target', 'internal-target-package-root-traversal'], $preflight['rIdTraversal']['issues']);
        $t->same(['invalid-target', 'internal-target-malformed-percent-escape'], $preflight['rIdBadEscape']['issues']);
        $t->same(['invalid-target', 'internal-target-invalid-uri-byte'], $preflight['rIdRawSpace']['issues']);
        $t->same(['invalid-target', 'internal-target-unsafe-percent-encoded-path-byte'], $preflight['rIdEncodedSlash']['issues']);
        $t->same(['invalid-target', 'internal-target-unsafe-percent-encoded-dot-segment'], $preflight['rIdEncodedDotSegment']['issues']);
        $t->same(['invalid-target', 'internal-target-unsafe-percent-encoded-path-byte'], $preflight['rIdEncodedBackslash']['issues']);
        $t->same(['invalid-target', 'internal-target-unsafe-percent-encoded-path-byte'], $preflight['rIdEncodedNul']['issues']);
        $t->same(['invalid-target', 'internal-target-trailing-dot-segment'], $preflight['rIdTrailingDotSegment']['issues']);
        $t->same(array_fill(0, 10, null), array_column(array_filter(
            $graph->preflightAllRelationshipTargets(),
            static fn (array $target): bool => $target['source'] === '/word/document.xml',
        ), 'targetPart'));
        $t->same(array_fill(0, 10, false), array_column($preflight, 'valid'));
    },
    'classifies invalid internal OPC relationship target query and fragment escapes' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdGoodQuery" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml?review=%20ready#note%20one"/>
  <Relationship Id="rIdBadQueryEscape" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml?review=%ZZ"/>
  <Relationship Id="rIdBadFragmentEscape" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml#note%ZZ"/>
  <Relationship Id="rIdEncodedQueryNul" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml?review=%00"/>
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

        $t->same('/word/styles.xml?review=%20ready#note%20one', $preflight['rIdGoodQuery']['target']);
        $t->same(true, $preflight['rIdGoodQuery']['valid']);
        $t->same([], $preflight['rIdGoodQuery']['issues']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $preflight['rIdGoodQuery']['contentType']);

        $t->same('styles.xml?review=%ZZ', $preflight['rIdBadQueryEscape']['target']);
        $t->same(false, $preflight['rIdBadQueryEscape']['valid']);
        $t->same(['invalid-target', 'internal-target-malformed-percent-escape'], $preflight['rIdBadQueryEscape']['issues']);

        $t->same('styles.xml#note%ZZ', $preflight['rIdBadFragmentEscape']['target']);
        $t->same(false, $preflight['rIdBadFragmentEscape']['valid']);
        $t->same(['invalid-target', 'internal-target-malformed-percent-escape'], $preflight['rIdBadFragmentEscape']['issues']);

        $t->same('styles.xml?review=%00', $preflight['rIdEncodedQueryNul']['target']);
        $t->same(false, $preflight['rIdEncodedQueryNul']['valid']);
        $t->same(['invalid-target', 'internal-target-unsafe-percent-encoded-byte'], $preflight['rIdEncodedQueryNul']['issues']);

        $references = [];
        foreach ($graph->preflightInternalTargetReferences('/word/document.xml') as $reference) {
            $references[$reference['id']] = $reference;
        }

        $t->same('/word/styles.xml', $references['rIdGoodQuery']['targetPart']);
        $t->same('review=%20ready', $references['rIdGoodQuery']['targetQuery']);
        $t->same('note%20one', $references['rIdGoodQuery']['targetFragment']);
        $t->same(null, $references['rIdBadQueryEscape']['targetPart']);
        $t->same(null, $references['rIdBadFragmentEscape']['targetPart']);
        $t->same(null, $references['rIdEncodedQueryNul']['targetPart']);

        $allTargets = [];
        foreach ($graph->preflightAllRelationshipTargets() as $target) {
            if ($target['source'] === '/word/document.xml') {
                $allTargets[$target['id']] = $target;
            }
        }

        $t->same('/word/styles.xml', $allTargets['rIdGoodQuery']['targetPart']);
        $t->same(null, $allTargets['rIdBadQueryEscape']['targetPart']);
        $t->same(null, $allTargets['rIdBadFragmentEscape']['targetPart']);
        $t->same(null, $allTargets['rIdEncodedQueryNul']['targetPart']);
    },
    'rejects percent encoded OPC relationship target dot segments' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCurrentDirectory" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="./styles.xml"/>
  <Relationship Id="rIdParentDirectory" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml"/>
  <Relationship Id="rIdEncodedCurrentDirectory" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="%2E/styles.xml"/>
  <Relationship Id="rIdEncodedParentDirectory" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="media/%2E%2E/styles.xml"/>
  <Relationship Id="rIdMixedEncodedParentDirectory" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="media/.%2e/styles.xml"/>
</Relationships>
XML;

        $relationships = OpcRelationships::fromXml($documentRelationshipsXml, '/word/document.xml');
        $t->same('/word/styles.xml', $relationships->resolveTarget('rIdCurrentDirectory'));
        $t->same('/customXml/item1.xml', $relationships->resolveTarget('rIdParentDirectory'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedCurrentDirectory'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedParentDirectory'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdMixedEncodedParentDirectory'));

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->same('/word/styles.xml', $preflight['rIdCurrentDirectory']['target']);
        $t->same(true, $preflight['rIdCurrentDirectory']['valid']);
        $t->same('/customXml/item1.xml', $preflight['rIdParentDirectory']['target']);
        $t->same(true, $preflight['rIdParentDirectory']['valid']);

        foreach ([
            'rIdEncodedCurrentDirectory',
            'rIdEncodedParentDirectory',
            'rIdMixedEncodedParentDirectory',
        ] as $id) {
            $t->same($id, $preflight[$id]['id']);
            $t->same(false, $preflight[$id]['valid']);
            $t->same(['invalid-target', 'internal-target-unsafe-percent-encoded-dot-segment'], $preflight[$id]['issues']);
        }

        $targetParts = array_column($graph->preflightAllRelationshipTargets(), 'targetPart', 'id');
        $t->same('/word/styles.xml', $targetParts['rIdCurrentDirectory']);
        $t->same('/customXml/item1.xml', $targetParts['rIdParentDirectory']);
        $t->same(null, $targetParts['rIdEncodedCurrentDirectory']);
        $t->same(null, $targetParts['rIdEncodedParentDirectory']);
        $t->same(null, $targetParts['rIdMixedEncodedParentDirectory']);
    },
    'classifies and preflights external OPC relationship target policies' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHttp" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%20packet.html?post=42#review" TargetMode="External"/>
  <Relationship Id="rIdRawSpace" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source packet.html?post=42#review" TargetMode="External"/>
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
            'rIdRawSpace',
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
        $t->same('absolute-uri', $preflight['rIdRawSpace']['externalTargetKind']);
        $t->same('https', $preflight['rIdRawSpace']['externalTargetScheme']);
        $t->same(false, $preflight['rIdRawSpace']['externalTargetAllowed']);
        $t->same(false, $preflight['rIdRawSpace']['valid']);
        $t->same(['external-target-invalid-uri-byte'], $preflight['rIdRawSpace']['issues']);
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
    'preflights external OPC network-path targets as requiring a base scheme policy' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSchemeRelativeImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="//cdn.example.test/review.png" TargetMode="External"/>
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

        $t->same('network-path-reference', $preflight['rIdSchemeRelativeImage']['externalTargetKind']);
        $t->same(null, $preflight['rIdSchemeRelativeImage']['externalTargetScheme']);
        $t->same(true, $preflight['rIdSchemeRelativeImage']['externalTargetAllowed']);
        $t->same(true, $preflight['rIdSchemeRelativeImage']['externalTargetRequiresBaseUri']);
        $t->same(null, $preflight['rIdSchemeRelativeImage']['externalTargetRewriteBasePart']);
        $t->same('external-target-network-path-reference', $preflight['rIdSchemeRelativeImage']['externalTargetRewriteReason']);
        $t->same(false, $preflight['rIdSchemeRelativeImage']['valid']);
        $t->same(['external-target-network-path-base-uri'], $preflight['rIdSchemeRelativeImage']['issues']);

        $allTargets = [];
        foreach ($graph->preflightAllRelationshipTargets() as $target) {
            $allTargets[$target['source'] . ':' . $target['id']] = $target;
        }
        $t->same(false, $allTargets['/word/document.xml:rIdSchemeRelativeImage']['valid']);
        $t->same(['external-target-network-path-base-uri'], $allTargets['/word/document.xml:rIdSchemeRelativeImage']['issues']);
        $t->same(false, $graph->preflightPackageConsistency()['relationshipTargetsValid']);
    },
    'preflights raw whitespace in external OPC relationship target URI references' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $rawSpace = new OpcRelationship(
            'rIdRawSpaceExternal',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
            'https://example.test/source packet.html',
            OpcRelationship::TARGET_MODE_EXTERNAL,
        );
        $encodedSpace = new OpcRelationship(
            'rIdEncodedSpaceExternal',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
            'https://example.test/source%20packet.html',
            OpcRelationship::TARGET_MODE_EXTERNAL,
        );

        $t->same([
            'kind' => 'absolute-uri',
            'scheme' => 'https',
            'allowed' => false,
            'issues' => ['external-target-invalid-uri-byte'],
        ], $rawSpace->externalTargetPreflight());
        $t->same([
            'kind' => 'absolute-uri',
            'scheme' => 'https',
            'allowed' => true,
            'issues' => [],
        ], $encodedSpace->externalTargetPreflight());

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdRawSpaceExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source packet.html" TargetMode="External"/>
  <Relationship Id="rIdEncodedSpaceExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%20packet.html" TargetMode="External"/>
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

        $t->same(false, $preflight['rIdRawSpaceExternal']['externalTargetAllowed']);
        $t->same(false, $preflight['rIdRawSpaceExternal']['valid']);
        $t->same(['external-target-invalid-uri-byte'], $preflight['rIdRawSpaceExternal']['issues']);
        $t->same(true, $preflight['rIdEncodedSpaceExternal']['externalTargetAllowed']);
        $t->same(true, $preflight['rIdEncodedSpaceExternal']['valid']);
        $t->same([], $preflight['rIdEncodedSpaceExternal']['issues']);
    },
    'preflights malformed percent escapes in external OPC relationship target URI references' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $badEscape = new OpcRelationship(
            'rIdBadExternalEscape',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
            'https://example.test/source%ZZpacket.html',
            OpcRelationship::TARGET_MODE_EXTERNAL,
        );
        $encodedNul = new OpcRelationship(
            'rIdEncodedNulExternal',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
            'https://example.test/source%00packet.html',
            OpcRelationship::TARGET_MODE_EXTERNAL,
        );

        $t->same([
            'kind' => 'absolute-uri',
            'scheme' => 'https',
            'allowed' => false,
            'issues' => ['external-target-malformed-percent-escape'],
        ], $badEscape->externalTargetPreflight());
        $t->same([
            'kind' => 'absolute-uri',
            'scheme' => 'https',
            'allowed' => false,
            'issues' => ['external-target-unsafe-percent-encoded-byte'],
        ], $encodedNul->externalTargetPreflight());

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdGoodEncodedSpaceExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%20packet.html" TargetMode="External"/>
  <Relationship Id="rIdBadExternalEscape" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%ZZpacket.html" TargetMode="External"/>
  <Relationship Id="rIdEncodedNulExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source%00packet.html" TargetMode="External"/>
  <Relationship Id="rIdUnsafeEncodedExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert%00(1)" TargetMode="External"/>
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

        $t->same(true, $preflight['rIdGoodEncodedSpaceExternal']['externalTargetAllowed']);
        $t->same(true, $preflight['rIdGoodEncodedSpaceExternal']['valid']);
        $t->same([], $preflight['rIdGoodEncodedSpaceExternal']['issues']);
        $t->same(false, $preflight['rIdBadExternalEscape']['externalTargetAllowed']);
        $t->same(false, $preflight['rIdBadExternalEscape']['valid']);
        $t->same(['external-target-malformed-percent-escape'], $preflight['rIdBadExternalEscape']['issues']);
        $t->same(false, $preflight['rIdEncodedNulExternal']['externalTargetAllowed']);
        $t->same(false, $preflight['rIdEncodedNulExternal']['valid']);
        $t->same(['external-target-unsafe-percent-encoded-byte'], $preflight['rIdEncodedNulExternal']['issues']);
        $t->same(false, $preflight['rIdUnsafeEncodedExternal']['externalTargetAllowed']);
        $t->same(false, $preflight['rIdUnsafeEncodedExternal']['valid']);
        $t->same(['external-target-unsafe-percent-encoded-byte', 'external-target-unsafe-scheme'], $preflight['rIdUnsafeEncodedExternal']['issues']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(['external-target-malformed-percent-escape'], $closureById['rIdBadExternalEscape']['issues']);
        $t->same(['external-target-unsafe-percent-encoded-byte'], $closureById['rIdEncodedNulExternal']['issues']);
        $t->same(['external-target-unsafe-percent-encoded-byte', 'external-target-unsafe-scheme'], $closureById['rIdUnsafeEncodedExternal']['issues']);
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
    'flags external OPC relationship targets that shadow package parts' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdExternalLocalImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-image.PNG" TargetMode="External"/>
  <Relationship Id="rIdExternalLocalCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml#packet" TargetMode="External"/>
  <Relationship Id="rIdExternalAbsolutePackagePath" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="/word/styles.xml" TargetMode="External"/>
  <Relationship Id="rIdExternalRemoteRelative" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="../review/source.html#packet" TargetMode="External"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->same(true, $preflight['rIdExternalLocalImage']['external']);
        $t->same('relative-reference', $preflight['rIdExternalLocalImage']['externalTargetKind']);
        $t->same(true, $preflight['rIdExternalLocalImage']['externalTargetAllowed']);
        $t->same(true, $preflight['rIdExternalLocalImage']['externalTargetRequiresBaseUri']);
        $t->same('/word/document.xml', $preflight['rIdExternalLocalImage']['externalTargetRewriteBasePart']);
        $t->same(false, $preflight['rIdExternalLocalImage']['valid']);
        $t->same(['external-target-matches-package-part'], $preflight['rIdExternalLocalImage']['issues']);

        $t->same(false, $preflight['rIdExternalLocalCustomXml']['valid']);
        $t->same(['external-target-matches-package-part'], $preflight['rIdExternalLocalCustomXml']['issues']);
        $t->same(false, $preflight['rIdExternalAbsolutePackagePath']['valid']);
        $t->same(['external-target-matches-package-part'], $preflight['rIdExternalAbsolutePackagePath']['issues']);

        $t->same(true, $preflight['rIdExternalRemoteRelative']['valid']);
        $t->same([], $preflight['rIdExternalRemoteRelative']['issues']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(['external-target-matches-package-part'], $closureById['rIdExternalLocalImage']['issues']);
        $t->same(['external-target-matches-package-part'], $closureById['rIdExternalLocalCustomXml']['issues']);
        $t->same([], $closureById['rIdExternalRemoteRelative']['issues']);
        $t->same(false, $graph->preflightPackageConsistency()['relationshipTargetsValid']);
    },
    'summarizes OPC relationship TargetMode declarations for importer gates' => static function (TestRunner $t) use ($contentTypesXml): void {
        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImplicitImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rIdExplicitInternalStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml" TargetMode="Internal"/>
  <Relationship Id="rIdExternalReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/review.png', 'data' => 'PNG'],
        ]));

        $documentRelationships = $graph->requireRelationshipsForSource('/word/document.xml');
        $t->same(false, $documentRelationships->byId('rIdImplicitImage')?->targetModeExplicit);
        $t->same(true, $documentRelationships->byId('rIdExplicitInternalStyles')?->targetModeExplicit);
        $t->same(true, $documentRelationships->byId('rIdExternalReview')?->targetModeExplicit);

        $summary = $graph->relationshipTargetModeSummary();
        $t->same(null, $summary['source']);
        $t->same(null, $summary['relationshipType']);
        $t->same(true, $summary['valid']);
        $t->same(4, $summary['relationshipCount']);
        $t->same(2, $summary['sourceCount']);
        $t->same(3, $summary['internalCount']);
        $t->same(1, $summary['externalCount']);
        $t->same(2, $summary['implicitTargetModeCount']);
        $t->same(2, $summary['explicitTargetModeCount']);
        $t->same([
            '(implicit-internal)' => 2,
            'External' => 1,
            'Internal' => 1,
        ], $summary['relationshipRecordTargetModeCounts']);
        $t->same(2, $summary['relationshipRecordImplicitInternalTargetModeCount']);
        $t->same(1, $summary['relationshipRecordExplicitInternalTargetModeCount']);
        $t->same(1, $summary['relationshipRecordExplicitExternalTargetModeCount']);
        $t->same(0, $summary['relationshipRecordUnexpectedTargetModeCount']);
        $t->same(['External' => 1, 'Internal' => 3], $summary['targetModeCounts']);
        $t->same(['explicit' => 2, 'implicit' => 2], $summary['targetModeDeclarationCounts']);
        $t->same(['/', '/word/document.xml'], $summary['sources']);
        $t->same(['/word/document.xml'], $summary['sourcesWithExplicitInternalTargetMode']);
        $t->same(['/word/_rels/document.xml.rels'], $summary['relationshipPartsWithExplicitInternalTargetMode']);
        $t->same([
            [
                'source' => '/word/document.xml',
                'relationshipPartName' => '/word/_rels/document.xml.rels',
                'id' => 'rIdExplicitInternalStyles',
                'type' => 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles',
                'target' => 'styles.xml',
                'targetMode' => 'Internal',
                'targetModeExplicit' => true,
                'targetModeDeclaration' => 'explicit',
                'external' => false,
            ],
        ], $summary['relationshipsWithExplicitInternalTargetMode']);

        $relationshipsByKey = [];
        foreach ($summary['relationships'] as $relationship) {
            $relationshipsByKey[$relationship['source'] . ':' . $relationship['id']] = $relationship;
        }
        $t->same(false, $relationshipsByKey['/:rIdDocument']['targetModeExplicit']);
        $t->same('implicit', $relationshipsByKey['/word/document.xml:rIdImplicitImage']['targetModeDeclaration']);
        $t->same('explicit', $relationshipsByKey['/word/document.xml:rIdExplicitInternalStyles']['targetModeDeclaration']);
        $t->same('External', $relationshipsByKey['/word/document.xml:rIdExternalReview']['targetMode']);

        $documentSummary = $graph->relationshipTargetModeSummary('word/./document.xml');
        $t->same('/word/document.xml', $documentSummary['source']);
        $t->same(3, $documentSummary['relationshipCount']);
        $t->same(1, $documentSummary['sourceCount']);
        $t->same(2, $documentSummary['internalCount']);
        $t->same(1, $documentSummary['externalCount']);
        $t->same(1, $documentSummary['relationshipRecordImplicitInternalTargetModeCount']);
        $t->same(1, $documentSummary['relationshipRecordExplicitInternalTargetModeCount']);
        $t->same(1, $documentSummary['relationshipRecordExplicitExternalTargetModeCount']);

        $hyperlinkSummary = $graph->relationshipTargetModeSummary(null, OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE, $hyperlinkSummary['relationshipType']);
        $t->same(1, $hyperlinkSummary['relationshipCount']);
        $t->same(0, $hyperlinkSummary['internalCount']);
        $t->same(1, $hyperlinkSummary['externalCount']);
        $t->same(['External' => 1], $hyperlinkSummary['targetModeCounts']);
        $t->same(['/word/document.xml'], $hyperlinkSummary['sources']);
    },
    'preflights package root external relative targets without implicit source part' => static function (TestRunner $t) use ($contentTypesXml): void {
        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdPackageRelative" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="review/source.html#packet" TargetMode="External"/>
  <Relationship Id="rIdPackageFragment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#package-review" TargetMode="External"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $preflight = [];
        foreach ($graph->preflightTargetsForSource('/') as $target) {
            $preflight[$target['id']] = $target;
        }

        $t->same(true, $preflight['rIdDocument']['valid']);
        $t->same('relative-reference', $preflight['rIdPackageRelative']['externalTargetKind']);
        $t->same(true, $preflight['rIdPackageRelative']['externalTargetAllowed']);
        $t->same(true, $preflight['rIdPackageRelative']['externalTargetRequiresBaseUri']);
        $t->same(null, $preflight['rIdPackageRelative']['externalTargetRewriteBasePart']);
        $t->same('external-target-relative-reference', $preflight['rIdPackageRelative']['externalTargetRewriteReason']);
        $t->same(false, $preflight['rIdPackageRelative']['valid']);
        $t->same(['external-target-package-root-base-uri'], $preflight['rIdPackageRelative']['issues']);

        $t->same('fragment-reference', $preflight['rIdPackageFragment']['externalTargetKind']);
        $t->same(true, $preflight['rIdPackageFragment']['externalTargetAllowed']);
        $t->same(true, $preflight['rIdPackageFragment']['externalTargetRequiresBaseUri']);
        $t->same(null, $preflight['rIdPackageFragment']['externalTargetRewriteBasePart']);
        $t->same('external-target-fragment-reference', $preflight['rIdPackageFragment']['externalTargetRewriteReason']);
        $t->same(false, $preflight['rIdPackageFragment']['valid']);
        $t->same(['external-target-package-root-base-uri'], $preflight['rIdPackageFragment']['issues']);

        $allTargets = [];
        foreach ($graph->preflightAllRelationshipTargets() as $target) {
            $allTargets[$target['source'] . ':' . $target['id']] = $target;
        }
        $t->same(null, $allTargets['/:rIdPackageRelative']['targetPart']);
        $t->same(null, $allTargets['/:rIdPackageFragment']['targetPart']);
        $t->same(false, $graph->preflightPackageConsistency()['relationshipTargetsValid']);
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
        $badPercent = new OpcRelationship('rIdBadPercentType', 'http://example.test/relationships/%ZZ', 'media/review.png');
        $encodedControl = new OpcRelationship('rIdEncodedControlType', 'http://example.test/relationships/%00image', 'media/review.png');
        $encodedSpace = new OpcRelationship('rIdEncodedSpaceType', 'http://example.test/relationships/source%20image', 'media/review.png');

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
        $t->same(['relationship-type-malformed-percent-escape'], $badPercent->relationshipTypePreflight()['issues']);
        $t->same(['relationship-type-unsafe-percent-encoded-byte'], $encodedControl->relationshipTypePreflight()['issues']);
        $t->same([], $encodedSpace->relationshipTypePreflight()['issues']);

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdBadType" Type="officeDocument/relationships/hyperlink" Target="https://example.test/source" TargetMode="External"/>
  <Relationship Id="rIdBadPercentType" Type="http://example.test/relationships/%ZZ" Target="media/review-image.PNG"/>
  <Relationship Id="rIdEncodedControlType" Type="http://example.test/relationships/%00image" Target="media/review-image.PNG"/>
  <Relationship Id="rIdEncodedSpaceType" Type="http://example.test/relationships/source%20image" Target="media/review-image.PNG"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
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
        $t->same('absolute-uri', $preflight['rIdBadPercentType']['relationshipTypeKind']);
        $t->same('http', $preflight['rIdBadPercentType']['relationshipTypeScheme']);
        $t->same(false, $preflight['rIdBadPercentType']['relationshipTypeValid']);
        $t->same(['relationship-type-malformed-percent-escape'], $preflight['rIdBadPercentType']['relationshipTypeIssues']);
        $t->same(false, $preflight['rIdBadPercentType']['valid']);
        $t->same(['relationship-type-malformed-percent-escape'], $preflight['rIdBadPercentType']['issues']);
        $t->same(true, $preflight['rIdBadPercentType']['exists']);
        $t->same('image/png', $preflight['rIdBadPercentType']['contentType']);
        $t->same(false, $preflight['rIdEncodedControlType']['relationshipTypeValid']);
        $t->same(['relationship-type-unsafe-percent-encoded-byte'], $preflight['rIdEncodedControlType']['relationshipTypeIssues']);
        $t->same(false, $preflight['rIdEncodedControlType']['valid']);
        $t->same(['relationship-type-unsafe-percent-encoded-byte'], $preflight['rIdEncodedControlType']['issues']);
        $t->same(true, $preflight['rIdEncodedSpaceType']['relationshipTypeValid']);
        $t->same([], $preflight['rIdEncodedSpaceType']['relationshipTypeIssues']);
        $t->same(true, $preflight['rIdEncodedSpaceType']['valid']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same('relative-reference', $closureById['rIdBadType']['relationshipTypeKind']);
        $t->same(['relationship-type-not-absolute-uri'], $closureById['rIdBadType']['issues']);
        $t->same(['relationship-type-malformed-percent-escape'], $closureById['rIdBadPercentType']['issues']);
        $t->same(['relationship-type-unsafe-percent-encoded-byte'], $closureById['rIdEncodedControlType']['issues']);
        $t->same([], $closureById['rIdEncodedSpaceType']['relationshipTypeIssues']);
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
    'preflights OPC package signature object and certificate metadata' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig-metadata.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignatureMetadata" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig-metadata.xml"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature Id="idPackageSignature" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/document.xml"/>
  </ds:SignedInfo>
  <ds:KeyInfo>
    <ds:X509Data>
      <ds:X509Certificate>SGVsbG8gc2lnbmVyIGNlcnQ=</ds:X509Certificate>
      <ds:X509Certificate>not base64!</ds:X509Certificate>
    </ds:X509Data>
  </ds:KeyInfo>
  <ds:Object Id="idPackageSignatureObject" MimeType="text/xml">
    <ds:SignatureProperties>
      <ds:SignatureProperty Target="#idPackageSignature">
        <mdssi:SignatureTime>
          <mdssi:Format>YYYY-MM-DDThh:mm:ssTZD</mdssi:Format>
          <mdssi:Value>2026-06-06T22:33:48Z</mdssi:Value>
        </mdssi:SignatureTime>
      </ds:SignatureProperty>
    </ds:SignatureProperties>
  </ds:Object>
  <ds:Object MimeType="text/xml">
    <mdssi:SignatureTime>
      <mdssi:Value>2026-02-30T22:33:48Z</mdssi:Value>
    </mdssi:SignatureTime>
  </ds:Object>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
            ['name' => '_xmlsignatures/sig-metadata.xml', 'data' => $signatureXml],
        ]));

        $metadata = $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/sig-metadata.xml');

        $t->same('/_xmlsignatures/sig-metadata.xml', $metadata['signaturePart']);
        $t->same(2, $metadata['objectCount']);
        $t->same(['idPackageSignatureObject'], $metadata['objectIds']);
        $t->same([], $metadata['duplicateObjectIds']);
        $t->same(2, $metadata['certificateCount']);
        $t->same(false, $metadata['valid']);
        $t->same([
            'missing-signature-object-id',
            'invalid-signature-time-value',
            'invalid-x509-certificate-base64',
        ], $metadata['issues']);

        $t->same('idPackageSignatureObject', $metadata['objects'][0]['id']);
        $t->same(false, $metadata['objects'][0]['idDuplicate']);
        $t->same(1, $metadata['objects'][0]['idOccurrenceCount']);
        $t->same('text/xml', $metadata['objects'][0]['mimeType']);
        $t->same(null, $metadata['objects'][0]['encoding']);
        $t->same('YYYY-MM-DDThh:mm:ssTZD', $metadata['objects'][0]['signatureTimeFormat']);
        $t->same('2026-06-06T22:33:48Z', $metadata['objects'][0]['signatureTimeValue']);
        $t->same(true, $metadata['objects'][0]['signatureTimeValid']);
        $t->same(1, $metadata['objects'][0]['signaturePropertyCount']);
        $t->same(1, $metadata['objects'][0]['signaturePropertyTargetCount']);
        $t->same('#idPackageSignature', $metadata['objects'][0]['signaturePropertyTargets'][0]['target']);
        $t->same('same-document-fragment', $metadata['objects'][0]['signaturePropertyTargets'][0]['targetKind']);
        $t->same('idPackageSignature', $metadata['objects'][0]['signaturePropertyTargets'][0]['targetFragment']);
        $t->same(true, $metadata['objects'][0]['signaturePropertyTargets'][0]['targetMatched']);
        $t->same(['Signature'], $metadata['objects'][0]['signaturePropertyTargets'][0]['targetMatchedElementNames']);
        $t->same(true, $metadata['objects'][0]['signaturePropertyTargets'][0]['valid']);
        $t->same([], $metadata['objects'][0]['signaturePropertyTargets'][0]['issues']);
        $t->same(['SignatureTime', 'Format', 'Value'], $metadata['objects'][0]['packageSignatureElements']);
        $t->same([], $metadata['objects'][0]['manifestIds']);
        $t->same([], $metadata['objects'][0]['duplicateManifestIds']);
        $t->same(0, $metadata['objects'][0]['missingManifestIdCount']);
        $t->same(true, $metadata['objects'][0]['valid']);
        $t->same([], $metadata['objects'][0]['issues']);

        $t->same(null, $metadata['objects'][1]['id']);
        $t->same(false, $metadata['objects'][1]['idDuplicate']);
        $t->same(0, $metadata['objects'][1]['idOccurrenceCount']);
        $t->same('text/xml', $metadata['objects'][1]['mimeType']);
        $t->same(null, $metadata['objects'][1]['signatureTimeFormat']);
        $t->same('2026-02-30T22:33:48Z', $metadata['objects'][1]['signatureTimeValue']);
        $t->same(false, $metadata['objects'][1]['signatureTimeValid']);
        $t->same(0, $metadata['objects'][1]['signaturePropertyCount']);
        $t->same(0, $metadata['objects'][1]['signaturePropertyTargetCount']);
        $t->same(false, $metadata['objects'][1]['valid']);
        $t->same(['missing-signature-object-id', 'invalid-signature-time-value'], $metadata['objects'][1]['issues']);

        $t->same(0, $metadata['certificates'][0]['index']);
        $t->same(24, $metadata['certificates'][0]['base64Length']);
        $t->same(17, $metadata['certificates'][0]['decodedBytes']);
        $t->same('339af39211d5f1a9de3c16e229830accd22d7063980248a5ea57edf61cac6c6d', $metadata['certificates'][0]['sha256']);
        $t->same(true, $metadata['certificates'][0]['valid']);
        $t->same([], $metadata['certificates'][0]['issues']);

        $t->same(1, $metadata['certificates'][1]['index']);
        $t->same(10, $metadata['certificates'][1]['base64Length']);
        $t->same(null, $metadata['certificates'][1]['decodedBytes']);
        $t->same(null, $metadata['certificates'][1]['sha256']);
        $t->same(false, $metadata['certificates'][1]['valid']);
        $t->same(['invalid-x509-certificate-base64'], $metadata['certificates'][1]['issues']);

        $t->throws(\RuntimeException::class, static fn (): array => $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/missing.xml'));
    },
    'preflights OPC package signature object id and signature property policy metadata' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig-object-policy.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignaturePolicy" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig-object-policy.xml"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature Id="idPackageSignature" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo/>
  <ds:Object Id="duplicateObject" MimeType="text/xml">
    <ds:Manifest Id="manifestPrimary"/>
    <ds:SignatureProperties>
      <ds:SignatureProperty Target="#idPackageSignature">
        <mdssi:SignatureTime>
          <mdssi:Value>2026-06-08T23:46:43Z</mdssi:Value>
        </mdssi:SignatureTime>
      </ds:SignatureProperty>
    </ds:SignatureProperties>
  </ds:Object>
  <ds:Object Id="duplicateObject" MimeType="text/xml">
    <ds:Manifest Id="manifestDuplicate"/>
    <ds:Manifest Id="manifestDuplicate"/>
    <ds:SignatureProperties>
      <ds:SignatureProperty Target="#missingSignatureTarget"/>
      <ds:SignatureProperty/>
      <ds:SignatureProperty Target="https://example.test/signature-property"/>
    </ds:SignatureProperties>
  </ds:Object>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
            ['name' => '_xmlsignatures/sig-object-policy.xml', 'data' => $signatureXml],
        ]));

        $metadata = $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/sig-object-policy.xml');
        $validObject = $metadata['objects'][0];
        $invalidObject = $metadata['objects'][1];

        $t->same('/_xmlsignatures/sig-object-policy.xml', $metadata['signaturePart']);
        $t->same(2, $metadata['objectCount']);
        $t->same(['duplicateObject'], $metadata['objectIds']);
        $t->same(['duplicateObject'], $metadata['duplicateObjectIds']);
        $t->same(false, $metadata['valid']);
        $t->same([
            'duplicate-signature-object-id',
            'unmatched-signature-property-target',
            'missing-signature-property-target',
            'signature-property-target-not-same-document',
            'duplicate-manifest-id',
        ], $metadata['issues']);

        $t->same('duplicateObject', $validObject['id']);
        $t->same(true, $validObject['idDuplicate']);
        $t->same(2, $validObject['idOccurrenceCount']);
        $t->same(1, $validObject['signaturePropertyCount']);
        $t->same(1, $validObject['signaturePropertyTargetCount']);
        $t->same('#idPackageSignature', $validObject['signaturePropertyTargets'][0]['target']);
        $t->same('same-document-fragment', $validObject['signaturePropertyTargets'][0]['targetKind']);
        $t->same('idPackageSignature', $validObject['signaturePropertyTargets'][0]['targetFragment']);
        $t->same(true, $validObject['signaturePropertyTargets'][0]['targetMatched']);
        $t->same(['Signature'], $validObject['signaturePropertyTargets'][0]['targetMatchedElementNames']);
        $t->same(true, $validObject['signaturePropertyTargets'][0]['valid']);
        $t->same([], $validObject['signaturePropertyTargets'][0]['issues']);
        $t->same(['manifestPrimary'], $validObject['manifestIds']);
        $t->same([], $validObject['duplicateManifestIds']);
        $t->same(0, $validObject['missingManifestIdCount']);
        $t->same(false, $validObject['valid']);
        $t->same(['duplicate-signature-object-id'], $validObject['issues']);

        $t->same('duplicateObject', $invalidObject['id']);
        $t->same(true, $invalidObject['idDuplicate']);
        $t->same(2, $invalidObject['idOccurrenceCount']);
        $t->same(3, $invalidObject['signaturePropertyCount']);
        $t->same(2, $invalidObject['signaturePropertyTargetCount']);
        $t->same('#missingSignatureTarget', $invalidObject['signaturePropertyTargets'][0]['target']);
        $t->same('same-document-fragment', $invalidObject['signaturePropertyTargets'][0]['targetKind']);
        $t->same('missingSignatureTarget', $invalidObject['signaturePropertyTargets'][0]['targetFragment']);
        $t->same(false, $invalidObject['signaturePropertyTargets'][0]['targetMatched']);
        $t->same(false, $invalidObject['signaturePropertyTargets'][0]['valid']);
        $t->same(['unmatched-signature-property-target'], $invalidObject['signaturePropertyTargets'][0]['issues']);
        $t->same(null, $invalidObject['signaturePropertyTargets'][1]['target']);
        $t->same(false, $invalidObject['signaturePropertyTargets'][1]['valid']);
        $t->same(['missing-signature-property-target'], $invalidObject['signaturePropertyTargets'][1]['issues']);
        $t->same('https://example.test/signature-property', $invalidObject['signaturePropertyTargets'][2]['target']);
        $t->same('external-uri', $invalidObject['signaturePropertyTargets'][2]['targetKind']);
        $t->same(false, $invalidObject['signaturePropertyTargets'][2]['targetMatched']);
        $t->same(['signature-property-target-not-same-document'], $invalidObject['signaturePropertyTargets'][2]['issues']);
        $t->same(['manifestDuplicate', 'manifestDuplicate'], $invalidObject['manifestIds']);
        $t->same(['manifestDuplicate'], $invalidObject['duplicateManifestIds']);
        $t->same(0, $invalidObject['missingManifestIdCount']);
        $t->same(false, $invalidObject['valid']);
        $t->same([
            'duplicate-signature-object-id',
            'unmatched-signature-property-target',
            'missing-signature-property-target',
            'signature-property-target-not-same-document',
            'duplicate-manifest-id',
        ], $invalidObject['issues']);
    },
    'preflights OPC package signature object manifest references' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig-manifest.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignatureManifest" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig-manifest.xml"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature Id="idPackageSignature" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/document.xml"/>
  </ds:SignedInfo>
  <ds:Object Id="idPackageSignatureObject" MimeType="text/xml">
    <ds:Manifest Id="manifestPackageParts">
      <ds:Reference URI="/word/document.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>SGVsbG8=</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="../docProps/core.xml?ContentType=application/vnd.openxmlformats-package.core-properties+xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
        <ds:DigestValue>U291cmNl</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="/word/media/missing.png">
        <ds:DigestValue>bad base64!</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="https://example.test/document.xml">
        <ds:DigestMethod Algorithm="urn:example:digest"/>
        <ds:DigestValue></ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="#local-object">
        <ds:DigestMethod Algorithm="urn:example:digest"/>
        <ds:DigestValue>AA==</ds:DigestValue>
      </ds:Reference>
    </ds:Manifest>
    <ds:SignatureProperties>
      <ds:SignatureProperty Target="#idPackageSignature">
        <mdssi:SignatureTime>
          <mdssi:Value>2026-06-08T13:51:28Z</mdssi:Value>
        </mdssi:SignatureTime>
      </ds:SignatureProperty>
    </ds:SignatureProperties>
  </ds:Object>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
            ['name' => '_xmlsignatures/sig-manifest.xml', 'data' => $signatureXml],
        ]));

        $metadata = $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/sig-manifest.xml');
        $object = $metadata['objects'][0];
        $references = [];
        foreach ($object['manifestReferences'] as $reference) {
            $references[$reference['uri']] = $reference;
        }

        $t->same('/_xmlsignatures/sig-manifest.xml', $metadata['signaturePart']);
        $t->same(false, $metadata['valid']);
        $t->same([
            'manifest-reference-target-missing',
            'missing-manifest-reference-digest-method',
            'invalid-manifest-reference-digest-value-base64',
            'manifest-reference-external-uri',
            'missing-manifest-reference-digest-value',
            'manifest-reference-fragment-uri',
        ], $metadata['issues']);
        $t->same(1, $object['manifestCount']);
        $t->same(['manifestPackageParts'], $object['manifestIds']);
        $t->same([], $object['duplicateManifestIds']);
        $t->same(0, $object['missingManifestIdCount']);
        $t->same(5, $object['manifestReferenceCount']);
        $t->same(1, $object['signaturePropertyCount']);
        $t->same(1, $object['signaturePropertyTargetCount']);
        $t->same(['SignatureTime', 'Value'], $object['packageSignatureElements']);
        $t->same(false, $object['valid']);

        $t->same('manifestPackageParts', $references['/word/document.xml']['manifestId']);
        $t->same(0, $references['/word/document.xml']['referenceIndex']);
        $t->same('/word/document.xml', $references['/word/document.xml']['targetPart']);
        $t->same(true, $references['/word/document.xml']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $references['/word/document.xml']['contentType']);
        $t->same('http://www.w3.org/2001/04/xmlenc#sha256', $references['/word/document.xml']['digestAlgorithm']);
        $t->same(8, $references['/word/document.xml']['digestValueBase64Length']);
        $t->same(5, $references['/word/document.xml']['digestValueDecodedBytes']);
        $t->same(true, $references['/word/document.xml']['valid']);
        $t->same([], $references['/word/document.xml']['issues']);

        $coreReference = $references['../docProps/core.xml?ContentType=application/vnd.openxmlformats-package.core-properties+xml'];
        $t->same('/docProps/core.xml', $coreReference['targetPart']);
        $t->same(true, $coreReference['exists']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $coreReference['contentType']);
        $t->same(6, $coreReference['digestValueDecodedBytes']);
        $t->same(true, $coreReference['valid']);

        $missingReference = $references['/word/media/missing.png'];
        $t->same('/word/media/missing.png', $missingReference['targetPart']);
        $t->same(false, $missingReference['exists']);
        $t->same('image/png', $missingReference['contentType']);
        $t->same(null, $missingReference['digestAlgorithm']);
        $t->same(null, $missingReference['digestValueDecodedBytes']);
        $t->same(false, $missingReference['valid']);
        $t->same([
            'manifest-reference-target-missing',
            'missing-manifest-reference-digest-method',
            'invalid-manifest-reference-digest-value-base64',
        ], $missingReference['issues']);

        $externalReference = $references['https://example.test/document.xml'];
        $t->same(null, $externalReference['targetPart']);
        $t->same(null, $externalReference['exists']);
        $t->same(null, $externalReference['contentType']);
        $t->same(false, $externalReference['valid']);
        $t->same([
            'manifest-reference-external-uri',
            'missing-manifest-reference-digest-value',
        ], $externalReference['issues']);

        $fragmentReference = $references['#local-object'];
        $t->same(null, $fragmentReference['targetPart']);
        $t->same(null, $fragmentReference['exists']);
        $t->same(1, $fragmentReference['digestValueDecodedBytes']);
        $t->same(false, $fragmentReference['valid']);
        $t->same(['manifest-reference-fragment-uri'], $fragmentReference['issues']);
    },
    'cross-checks OPC package signature manifest references against relationship transform targets' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig-manifest-cross-check.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignatureCrossCheck" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig-manifest-cross-check.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source%20workbook.xlsx"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>SGVsbG8=</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
  <ds:Object Id="idPackageSignatureObject">
    <ds:Manifest Id="manifestPackageParts">
      <ds:Reference URI="/word/media/hero.png">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>SGVsbG8=</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="/word/embeddings/source%20workbook.xlsx">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>U291cmNl</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="/word/document.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
        <ds:DigestValue>RG9jdW1lbnQ=</ds:DigestValue>
      </ds:Reference>
    </ds:Manifest>
  </ds:Object>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/source workbook.xlsx', 'data' => 'PK'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
            ['name' => '_xmlsignatures/sig-manifest-cross-check.xml', 'data' => $signatureXml],
        ]));

        $relationshipTransforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-manifest-cross-check.xml');
        $expectedPayloadBytes = $relationshipTransforms[0]['relationshipXmlBytes'];
        $expectedPayloadHash = $relationshipTransforms[0]['relationshipXmlSha256'];

        $metadata = $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/sig-manifest-cross-check.xml');
        $object = $metadata['objects'][0];
        $references = [];
        foreach ($object['manifestReferences'] as $reference) {
            $references[$reference['targetPart'] ?? (string) $reference['uri']] = $reference;
        }

        $t->same(true, $metadata['valid']);
        $t->same([], $metadata['issues']);
        $t->same(1, $object['manifestCount']);
        $t->same(3, $object['manifestReferenceCount']);
        $t->same(true, $object['valid']);

        $heroReference = $references['/word/media/hero.png'];
        $t->same(true, $heroReference['relationshipTransformTargetMatched']);
        $t->same(1, $heroReference['relationshipTransformTargetMatchCount']);
        $t->same([$expectedPayloadBytes], $heroReference['relationshipTransformPayloadByteCounts']);
        $t->same([$expectedPayloadHash], $heroReference['relationshipTransformPayloadSha256s']);
        $t->same('/word/_rels/document.xml.rels', $heroReference['relationshipTransformTargetMatches'][0]['relationshipPartName']);
        $t->same('/word/document.xml', $heroReference['relationshipTransformTargetMatches'][0]['source']);
        $t->same(0, $heroReference['relationshipTransformTargetMatches'][0]['referenceIndex']);
        $t->same('rIdHero', $heroReference['relationshipTransformTargetMatches'][0]['id']);
        $t->same('/word/media/hero.png', $heroReference['relationshipTransformTargetMatches'][0]['targetPart']);
        $t->same('image/png', $heroReference['relationshipTransformTargetMatches'][0]['contentType']);
        $t->same(true, $heroReference['relationshipTransformTargetMatches'][0]['selectedBySourceId']);
        $t->same(false, $heroReference['relationshipTransformTargetMatches'][0]['selectedBySourceType']);
        $t->same(true, $heroReference['relationshipTransformTargetMatches'][0]['relationshipValid']);
        $t->same([], $heroReference['relationshipTransformTargetMatches'][0]['relationshipIssues']);
        $t->same(true, $heroReference['relationshipTransformTargetMatches'][0]['transformValid']);
        $t->same([], $heroReference['relationshipTransformTargetMatches'][0]['transformIssues']);
        $t->same($expectedPayloadBytes, $heroReference['relationshipTransformTargetMatches'][0]['relationshipXmlBytes']);
        $t->same($expectedPayloadHash, $heroReference['relationshipTransformTargetMatches'][0]['relationshipXmlSha256']);
        $t->same(true, $heroReference['valid']);
        $t->same([], $heroReference['issues']);

        $workbookReference = $references['/word/embeddings/source workbook.xlsx'];
        $t->same(true, $workbookReference['relationshipTransformTargetMatched']);
        $t->same(1, $workbookReference['relationshipTransformTargetMatchCount']);
        $t->same([$expectedPayloadBytes], $workbookReference['relationshipTransformPayloadByteCounts']);
        $t->same([$expectedPayloadHash], $workbookReference['relationshipTransformPayloadSha256s']);
        $t->same('rIdEmbeddedWorkbook', $workbookReference['relationshipTransformTargetMatches'][0]['id']);
        $t->same('/word/embeddings/source workbook.xlsx', $workbookReference['relationshipTransformTargetMatches'][0]['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', $workbookReference['relationshipTransformTargetMatches'][0]['contentType']);
        $t->same(false, $workbookReference['relationshipTransformTargetMatches'][0]['selectedBySourceId']);
        $t->same(true, $workbookReference['relationshipTransformTargetMatches'][0]['selectedBySourceType']);
        $t->same(true, $workbookReference['relationshipTransformTargetMatches'][0]['relationshipValid']);
        $t->same(true, $workbookReference['relationshipTransformTargetMatches'][0]['transformValid']);
        $t->same($expectedPayloadBytes, $workbookReference['relationshipTransformTargetMatches'][0]['relationshipXmlBytes']);
        $t->same($expectedPayloadHash, $workbookReference['relationshipTransformTargetMatches'][0]['relationshipXmlSha256']);
        $t->same(true, $workbookReference['valid']);
        $t->same([], $workbookReference['issues']);

        $documentReference = $references['/word/document.xml'];
        $t->same(false, $documentReference['relationshipTransformTargetMatched']);
        $t->same(0, $documentReference['relationshipTransformTargetMatchCount']);
        $t->same([], $documentReference['relationshipTransformPayloadByteCounts']);
        $t->same([], $documentReference['relationshipTransformPayloadSha256s']);
        $t->same([], $documentReference['relationshipTransformTargetMatches']);
        $t->same(true, $documentReference['valid']);
        $t->same([], $documentReference['issues']);
    },
    'preflights OPC digital signature SignedInfo digest references' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig-signed-info.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig-signed-info.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/document.xml">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>SGVsbG8=</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
      <ds:DigestValue>U291cmNl</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="urn:example:digest"/>
      <ds:DigestValue>AA==</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:DigestMethod Algorithm="urn:example:digest"/>
      <ds:DigestValue>AA==</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/word/document.xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="urn:example:digest"/>
      <ds:DigestValue>AA==</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="https://example.test/document.xml">
      <ds:DigestMethod Algorithm="urn:example:digest"/>
      <ds:DigestValue></ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/word/media/missing.png">
      <ds:DigestValue>bad base64!</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="urn:example:digest"/>
      <ds:DigestValue>AA==</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="urn:example:digest"/>
      <ds:DigestValue>AA==</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
            ['name' => '_xmlsignatures/sig-signed-info.xml', 'data' => $signatureXml],
        ]));

        $references = $graph->preflightDigitalSignatureSignedInfoReferences('/_xmlsignatures/sig-signed-info.xml');

        $t->same(9, count($references));

        $documentReference = $references[0];
        $t->same('/_xmlsignatures/sig-signed-info.xml', $documentReference['signaturePart']);
        $t->same(0, $documentReference['referenceIndex']);
        $t->same('/word/document.xml', $documentReference['targetPart']);
        $t->same(true, $documentReference['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $documentReference['contentType']);
        $t->same(false, $documentReference['relationshipPart']);
        $t->same([], $documentReference['transformAlgorithms']);
        $t->same('http://www.w3.org/2001/04/xmlenc#sha256', $documentReference['digestAlgorithm']);
        $t->same(8, $documentReference['digestValueBase64Length']);
        $t->same(5, $documentReference['digestValueDecodedBytes']);
        $t->same(true, $documentReference['valid']);
        $t->same([], $documentReference['issues']);

        $relationshipReference = $references[1];
        $t->same('/word/_rels/document.xml.rels', $relationshipReference['targetPart']);
        $t->same(true, $relationshipReference['relationshipPart']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $relationshipReference['contentType']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $relationshipReference['referenceContentType']);
        $t->same(true, $relationshipReference['referenceContentTypeMatches']);
        $t->same([
            'http://schemas.openxmlformats.org/package/2006/RelationshipTransform',
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
        ], $relationshipReference['transformAlgorithms']);
        $t->same(1, $relationshipReference['relationshipTransformCount']);
        $t->same(1, $relationshipReference['canonicalizationTransformCount']);
        $t->same(['http://www.w3.org/TR/2001/REC-xml-c14n-20010315'], $relationshipReference['canonicalizationTransformAlgorithms']);
        $t->same([[
            'algorithm' => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'profile' => 'inclusive-c14n-1.0',
            'version' => '1.0',
            'exclusive' => false,
            'withComments' => false,
        ]], $relationshipReference['canonicalizationTransforms']);
        $t->same([
            'algorithm' => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'profile' => 'inclusive-c14n-1.0',
            'version' => '1.0',
            'exclusive' => false,
            'withComments' => false,
        ], $relationshipReference['relationshipTransformFollowingCanonicalization']);
        $t->same(true, $relationshipReference['relationshipTransformFollowedByCanonicalization']);
        $t->same(6, $relationshipReference['digestValueDecodedBytes']);
        $t->same(true, $relationshipReference['valid']);
        $t->same([], $relationshipReference['issues']);

        $relationshipWithoutCanonicalization = $references[2];
        $t->same('/word/_rels/document.xml.rels', $relationshipWithoutCanonicalization['targetPart']);
        $t->same(true, $relationshipWithoutCanonicalization['relationshipPart']);
        $t->same(1, $relationshipWithoutCanonicalization['relationshipTransformCount']);
        $t->same(0, $relationshipWithoutCanonicalization['canonicalizationTransformCount']);
        $t->same([], $relationshipWithoutCanonicalization['canonicalizationTransformAlgorithms']);
        $t->same([], $relationshipWithoutCanonicalization['canonicalizationTransforms']);
        $t->same(null, $relationshipWithoutCanonicalization['relationshipTransformFollowingCanonicalization']);
        $t->same(false, $relationshipWithoutCanonicalization['relationshipTransformFollowedByCanonicalization']);
        $t->same(false, $relationshipWithoutCanonicalization['valid']);
        $t->same(['signed-info-relationship-transform-not-followed-by-canonicalization'], $relationshipWithoutCanonicalization['issues']);

        $relationshipWithoutTransform = $references[3];
        $t->same('/word/_rels/document.xml.rels', $relationshipWithoutTransform['targetPart']);
        $t->same(true, $relationshipWithoutTransform['relationshipPart']);
        $t->same(0, $relationshipWithoutTransform['relationshipTransformCount']);
        $t->same(null, $relationshipWithoutTransform['relationshipTransformFollowedByCanonicalization']);
        $t->same(false, $relationshipWithoutTransform['valid']);
        $t->same(['relationship-part-reference-missing-relationship-transform'], $relationshipWithoutTransform['issues']);

        $ordinaryPartWithRelationshipTransform = $references[4];
        $t->same('/word/document.xml', $ordinaryPartWithRelationshipTransform['targetPart']);
        $t->same(false, $ordinaryPartWithRelationshipTransform['relationshipPart']);
        $t->same(1, $ordinaryPartWithRelationshipTransform['relationshipTransformCount']);
        $t->same(false, $ordinaryPartWithRelationshipTransform['relationshipTransformFollowedByCanonicalization']);
        $t->same(false, $ordinaryPartWithRelationshipTransform['valid']);
        $t->same(['relationship-transform-reference-not-relationship-part'], $ordinaryPartWithRelationshipTransform['issues']);

        $externalReference = $references[5];
        $t->same(null, $externalReference['targetPart']);
        $t->same(null, $externalReference['exists']);
        $t->same(null, $externalReference['contentType']);
        $t->same(false, $externalReference['valid']);
        $t->same([
            'signed-info-reference-external-uri',
            'missing-signed-info-reference-digest-value',
        ], $externalReference['issues']);

        $missingReference = $references[6];
        $t->same('/word/media/missing.png', $missingReference['targetPart']);
        $t->same(false, $missingReference['exists']);
        $t->same('image/png', $missingReference['contentType']);
        $t->same(null, $missingReference['digestAlgorithm']);
        $t->same(null, $missingReference['digestValueDecodedBytes']);
        $t->same(false, $missingReference['valid']);
        $t->same([
            'signed-info-reference-target-missing',
            'missing-signed-info-reference-digest-method',
            'invalid-signed-info-reference-digest-value-base64',
        ], $missingReference['issues']);

        $relationshipCanonicalizedBeforeTransform = $references[7];
        $t->same('/word/_rels/document.xml.rels', $relationshipCanonicalizedBeforeTransform['targetPart']);
        $t->same(true, $relationshipCanonicalizedBeforeTransform['relationshipPart']);
        $t->same([
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'http://schemas.openxmlformats.org/package/2006/RelationshipTransform',
            'http://www.w3.org/2006/12/xml-c14n11',
        ], $relationshipCanonicalizedBeforeTransform['transformAlgorithms']);
        $t->same([1], $relationshipCanonicalizedBeforeTransform['relationshipTransformIndexes'] ?? null);
        $t->same([0, 2], $relationshipCanonicalizedBeforeTransform['canonicalizationTransformIndexes'] ?? null);
        $t->same(1, $relationshipCanonicalizedBeforeTransform['relationshipTransformCount']);
        $t->same(2, $relationshipCanonicalizedBeforeTransform['canonicalizationTransformCount']);
        $t->same(true, $relationshipCanonicalizedBeforeTransform['relationshipTransformFollowedByCanonicalization']);
        $t->same(false, $relationshipCanonicalizedBeforeTransform['valid']);
        $t->same(['signed-info-relationship-transform-after-canonicalization'], $relationshipCanonicalizedBeforeTransform['issues']);

        $multipleRelationshipTransforms = $references[8];
        $t->same('/word/_rels/document.xml.rels', $multipleRelationshipTransforms['targetPart']);
        $t->same(true, $multipleRelationshipTransforms['relationshipPart']);
        $t->same([
            'http://schemas.openxmlformats.org/package/2006/RelationshipTransform',
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'http://schemas.openxmlformats.org/package/2006/RelationshipTransform',
            'http://www.w3.org/2001/10/xml-exc-c14n#',
        ], $multipleRelationshipTransforms['transformAlgorithms']);
        $t->same([0, 2], $multipleRelationshipTransforms['relationshipTransformIndexes'] ?? null);
        $t->same([1, 3], $multipleRelationshipTransforms['canonicalizationTransformIndexes'] ?? null);
        $t->same(2, $multipleRelationshipTransforms['relationshipTransformCount']);
        $t->same(2, $multipleRelationshipTransforms['canonicalizationTransformCount']);
        $t->same(true, $multipleRelationshipTransforms['relationshipTransformFollowedByCanonicalization']);
        $t->same(false, $multipleRelationshipTransforms['valid']);
        $t->same(['signed-info-multiple-relationship-transforms'], $multipleRelationshipTransforms['issues']);

        $t->throws(\RuntimeException::class, static fn (): array => $graph->preflightDigitalSignatureSignedInfoReferences('/_xmlsignatures/missing.xml'));
    },
    'preflights OPC digital signature SignedInfo same-document references' => static function (TestRunner $t): void {
        $sha256Digest = base64_encode(str_repeat('d', 32));
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-same-document.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $signatureXml = <<<XML
<ds:Signature Id="signatureRoot" xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:SignedInfo>
    <ds:Reference URI="#manifestPackageParts">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>{$sha256Digest}</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="#signatureObject">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>{$sha256Digest}</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="#missingManifest">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>{$sha256Digest}</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="#duplicateManifest">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>{$sha256Digest}</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="#">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>{$sha256Digest}</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
  <ds:Object Id="signatureObject" MimeType="text/xml">
    <ds:Manifest Id="manifestPackageParts">
      <ds:Reference URI="/word/document.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>{$sha256Digest}</ds:DigestValue>
      </ds:Reference>
    </ds:Manifest>
    <ds:Manifest Id="duplicateManifest"/>
    <ds:Manifest Id="duplicateManifest"/>
  </ds:Object>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => '_xmlsignatures/sig-same-document.xml', 'data' => $signatureXml],
        ]));

        $references = $graph->preflightDigitalSignatureSignedInfoReferences('/_xmlsignatures/sig-same-document.xml');
        $byUri = array_column($references, null, 'uri');

        $t->same(5, count($references));
        $t->same(true, $byUri['#manifestPackageParts']['sameDocumentReference']);
        $t->same('manifestPackageParts', $byUri['#manifestPackageParts']['sameDocumentFragment']);
        $t->same(true, $byUri['#manifestPackageParts']['sameDocumentTargetMatched']);
        $t->same(1, $byUri['#manifestPackageParts']['sameDocumentTargetMatchCount']);
        $t->same(['Manifest'], $byUri['#manifestPackageParts']['sameDocumentTargetMatchedElementNames']);
        $t->same(null, $byUri['#manifestPackageParts']['targetPart']);
        $t->same(null, $byUri['#manifestPackageParts']['exists']);
        $t->same(null, $byUri['#manifestPackageParts']['contentType']);
        $t->same(false, $byUri['#manifestPackageParts']['relationshipPart']);
        $t->same(true, $byUri['#manifestPackageParts']['valid']);
        $t->same([], $byUri['#manifestPackageParts']['issues']);

        $t->same('signatureObject', $byUri['#signatureObject']['sameDocumentFragment']);
        $t->same(true, $byUri['#signatureObject']['sameDocumentTargetMatched']);
        $t->same(1, $byUri['#signatureObject']['sameDocumentTargetMatchCount']);
        $t->same(['Object'], $byUri['#signatureObject']['sameDocumentTargetMatchedElementNames']);
        $t->same(true, $byUri['#signatureObject']['valid']);
        $t->same([], $byUri['#signatureObject']['issues']);

        $t->same('missingManifest', $byUri['#missingManifest']['sameDocumentFragment']);
        $t->same(false, $byUri['#missingManifest']['sameDocumentTargetMatched']);
        $t->same(0, $byUri['#missingManifest']['sameDocumentTargetMatchCount']);
        $t->same([], $byUri['#missingManifest']['sameDocumentTargetMatchedElementNames']);
        $t->same(false, $byUri['#missingManifest']['valid']);
        $t->same(['unmatched-signed-info-same-document-reference'], $byUri['#missingManifest']['issues']);

        $t->same('duplicateManifest', $byUri['#duplicateManifest']['sameDocumentFragment']);
        $t->same(true, $byUri['#duplicateManifest']['sameDocumentTargetMatched']);
        $t->same(2, $byUri['#duplicateManifest']['sameDocumentTargetMatchCount']);
        $t->same(['Manifest', 'Manifest'], $byUri['#duplicateManifest']['sameDocumentTargetMatchedElementNames']);
        $t->same(false, $byUri['#duplicateManifest']['valid']);
        $t->same(['ambiguous-signed-info-same-document-reference'], $byUri['#duplicateManifest']['issues']);

        $t->same('', $byUri['#']['sameDocumentFragment']);
        $t->same(false, $byUri['#']['sameDocumentTargetMatched']);
        $t->same(false, $byUri['#']['valid']);
        $t->same(['invalid-signed-info-same-document-reference'], $byUri['#']['issues']);

        $metadata = $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/sig-same-document.xml');
        $t->same(false, $metadata['valid']);
        $t->same(['duplicate-manifest-id'], $metadata['issues']);
        $t->same(['duplicateManifest'], $metadata['objects'][0]['duplicateManifestIds']);
        $t->same(3, $metadata['objects'][0]['manifestCount']);
        $t->same(1, $metadata['objects'][0]['manifestReferenceCount']);
    },
    'classifies OPC digital signature digest algorithms and decoded lengths' => static function (TestRunner $t): void {
        $sha1Digest = base64_encode(str_repeat('s', 20));
        $sha256Digest = base64_encode(str_repeat('d', 32));
        $sha256ShortDigest = base64_encode(str_repeat('x', 20));
        $sha384Digest = base64_encode(str_repeat('m', 48));
        $sha512ShortDigest = base64_encode(str_repeat('z', 32));
        $unknownDigest = base64_encode('opaque-digest');

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/item1.xml" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/sig-digests.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $signatureXml = <<<XML
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:SignedInfo>
    <ds:Reference URI="/word/document.xml">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>{$sha256Digest}</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/docProps/core.xml">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha512"/>
      <ds:DigestValue>{$sha512ShortDigest}</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/customXml/item1.xml">
      <ds:DigestMethod Algorithm="urn:example:digest"/>
      <ds:DigestValue>{$unknownDigest}</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
  <ds:Object Id="idPackageSignatureObject" MimeType="text/xml">
    <ds:Manifest Id="manifestPackageParts">
      <ds:Reference URI="/word/document.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#sha384"/>
        <ds:DigestValue>{$sha384Digest}</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="/docProps/core.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
        <ds:DigestValue>{$sha1Digest}</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="/customXml/item1.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>{$sha256ShortDigest}</ds:DigestValue>
      </ds:Reference>
    </ds:Manifest>
  </ds:Object>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdDocument" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="word/document.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'customXml/item1.xml', 'data' => '<item/>'],
            ['name' => '_xmlsignatures/sig-digests.xml', 'data' => $signatureXml],
        ]));

        $signedInfoReferences = $graph->preflightDigitalSignatureSignedInfoReferences('/_xmlsignatures/sig-digests.xml');
        $t->same(3, count($signedInfoReferences));
        $t->same([true, true, false], array_map(
            static fn (array $reference): ?bool => $reference['digestAlgorithmKnown'],
            $signedInfoReferences,
        ));
        $t->same(['sha256', 'sha512', null], array_map(
            static fn (array $reference): ?string => $reference['digestAlgorithmProfile'],
            $signedInfoReferences,
        ));
        $t->same([32, 64, null], array_map(
            static fn (array $reference): ?int => $reference['digestExpectedDecodedBytes'],
            $signedInfoReferences,
        ));
        $t->same([true, false, null], array_map(
            static fn (array $reference): ?bool => $reference['digestValueLengthValid'],
            $signedInfoReferences,
        ));
        $t->same([true, true, true], array_map(
            static fn (array $reference): bool => $reference['valid'],
            $signedInfoReferences,
        ));

        $metadata = $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/sig-digests.xml');
        $manifestReferences = $metadata['objects'][0]['manifestReferences'];
        $t->same(3, count($manifestReferences));
        $t->same([true, true, true], array_map(
            static fn (array $reference): ?bool => $reference['digestAlgorithmKnown'],
            $manifestReferences,
        ));
        $t->same(['sha384', 'sha1', 'sha256'], array_map(
            static fn (array $reference): ?string => $reference['digestAlgorithmProfile'],
            $manifestReferences,
        ));
        $t->same([48, 20, 32], array_map(
            static fn (array $reference): ?int => $reference['digestExpectedDecodedBytes'],
            $manifestReferences,
        ));
        $t->same([true, true, false], array_map(
            static fn (array $reference): ?bool => $reference['digestValueLengthValid'],
            $manifestReferences,
        ));
        $t->same([true, true, true], array_map(
            static fn (array $reference): bool => $reference['valid'],
            $manifestReferences,
        ));
        $t->same(true, $metadata['valid']);
    },
    'summarizes OPC digital signature digest policy issues for import review' => static function (TestRunner $t): void {
        $sha1Digest = base64_encode(str_repeat('s', 20));
        $sha256Digest = base64_encode(str_repeat('d', 32));
        $sha256ShortDigest = base64_encode(str_repeat('x', 20));
        $unknownDigest = base64_encode('opaque-digest');

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/customXml/item1.xml" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/sig-digest-policy.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $signatureXml = <<<XML
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:SignedInfo>
    <ds:Reference URI="/word/document.xml">
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>{$sha256Digest}</ds:DigestValue>
    </ds:Reference>
    <ds:Reference URI="/customXml/item1.xml">
      <ds:DigestMethod Algorithm="urn:example:digest"/>
      <ds:DigestValue>{$unknownDigest}</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
  <ds:Object Id="idPackageSignatureObject" MimeType="text/xml">
    <ds:Manifest Id="manifestPackageParts">
      <ds:Reference URI="/docProps/core.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2000/09/xmldsig#sha1"/>
        <ds:DigestValue>{$sha1Digest}</ds:DigestValue>
      </ds:Reference>
      <ds:Reference URI="/customXml/item1.xml">
        <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
        <ds:DigestValue>{$sha256ShortDigest}</ds:DigestValue>
      </ds:Reference>
    </ds:Manifest>
  </ds:Object>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdDocument" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="word/document.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'customXml/item1.xml', 'data' => '<item/>'],
            ['name' => '_xmlsignatures/sig-digest-policy.xml', 'data' => $signatureXml],
        ]));

        $signedInfoReferences = $graph->preflightDigitalSignatureSignedInfoReferences('/_xmlsignatures/sig-digest-policy.xml');
        $metadata = $graph->preflightDigitalSignatureMetadata('/_xmlsignatures/sig-digest-policy.xml');
        $summary = $graph->digitalSignatureDigestPolicySummary('/_xmlsignatures/sig-digest-policy.xml');

        $t->same([true, true], array_map(
            static fn (array $reference): bool => $reference['valid'],
            $signedInfoReferences,
        ));
        $t->same(true, $metadata['valid']);
        $t->same('/_xmlsignatures/sig-digest-policy.xml', $summary['signaturePart']);
        $t->same(false, $summary['valid']);
        $t->same(4, $summary['referenceCount']);
        $t->same(2, $summary['signedInfoReferenceCount']);
        $t->same(2, $summary['manifestReferenceCount']);
        $t->same(2, $summary['validDigestPolicyCount']);
        $t->same(2, $summary['invalidDigestPolicyCount']);
        $t->same(3, $summary['knownDigestAlgorithmCount']);
        $t->same(1, $summary['unknownDigestAlgorithmCount']);
        $t->same(0, $summary['missingDigestMethodCount']);
        $t->same(0, $summary['missingDigestValueCount']);
        $t->same(0, $summary['invalidDigestValueBase64Count']);
        $t->same(1, $summary['digestValueLengthMismatchCount']);
        $t->same([
            'http://www.w3.org/2000/09/xmldsig#sha1' => 1,
            'http://www.w3.org/2001/04/xmlenc#sha256' => 2,
            'urn:example:digest' => 1,
        ], $summary['algorithmCounts']);
        $t->same([
            'sha1' => 1,
            'sha256' => 2,
        ], $summary['profileCounts']);
        $t->same([
            'invalid-manifest-reference-digest-value-length' => 1,
            'unknown-signed-info-reference-digest-algorithm' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'invalid-manifest-reference-digest-value-length',
            'unknown-signed-info-reference-digest-algorithm',
        ], $summary['issues']);
        $t->same(4, count($summary['references']));
        $t->same(2, count($summary['invalidReferences']));
        $t->same(true, $summary['references'][0]['valid']);
        $t->same([], $summary['references'][0]['issues']);
        $t->same('signed-info', $summary['invalidReferences'][0]['section']);
        $t->same(1, $summary['invalidReferences'][0]['referenceIndex']);
        $t->same(null, $summary['invalidReferences'][0]['manifestId']);
        $t->same('/customXml/item1.xml', $summary['invalidReferences'][0]['uri']);
        $t->same('/customXml/item1.xml', $summary['invalidReferences'][0]['targetPart']);
        $t->same('urn:example:digest', $summary['invalidReferences'][0]['digestAlgorithm']);
        $t->same(false, $summary['invalidReferences'][0]['digestAlgorithmKnown']);
        $t->same(null, $summary['invalidReferences'][0]['digestAlgorithmProfile']);
        $t->same(null, $summary['invalidReferences'][0]['digestExpectedDecodedBytes']);
        $t->same(strlen('opaque-digest'), $summary['invalidReferences'][0]['digestValueDecodedBytes']);
        $t->same(null, $summary['invalidReferences'][0]['digestValueLengthValid']);
        $t->same(['unknown-signed-info-reference-digest-algorithm'], $summary['invalidReferences'][0]['issues']);
        $t->same('manifest', $summary['invalidReferences'][1]['section']);
        $t->same(1, $summary['invalidReferences'][1]['referenceIndex']);
        $t->same('manifestPackageParts', $summary['invalidReferences'][1]['manifestId']);
        $t->same('/customXml/item1.xml', $summary['invalidReferences'][1]['uri']);
        $t->same('/customXml/item1.xml', $summary['invalidReferences'][1]['targetPart']);
        $t->same('http://www.w3.org/2001/04/xmlenc#sha256', $summary['invalidReferences'][1]['digestAlgorithm']);
        $t->same(true, $summary['invalidReferences'][1]['digestAlgorithmKnown']);
        $t->same('sha256', $summary['invalidReferences'][1]['digestAlgorithmProfile']);
        $t->same(32, $summary['invalidReferences'][1]['digestExpectedDecodedBytes']);
        $t->same(20, $summary['invalidReferences'][1]['digestValueDecodedBytes']);
        $t->same(false, $summary['invalidReferences'][1]['digestValueLengthValid']);
        $t->same(['invalid-manifest-reference-digest-value-length'], $summary['invalidReferences'][1]['issues']);
    },
    'maps OPC signature canonicalization transform algorithms to reviewer profiles' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-profiles.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $canonicalizationAlgorithms = [
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315#WithComments',
            'http://www.w3.org/2001/10/xml-exc-c14n#',
            'http://www.w3.org/2001/10/xml-exc-c14n#WithComments',
            'http://www.w3.org/2006/12/xml-c14n11',
            'http://www.w3.org/2006/12/xml-c14n11#WithComments',
        ];
        $referencesXml = '';
        foreach ($canonicalizationAlgorithms as $algorithm) {
            $referencesXml .= <<<XML
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="{$algorithm}"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>SGVsbG8=</ds:DigestValue>
    </ds:Reference>

XML;
        }

        $signatureXml = <<<XML
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
{$referencesXml}  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-profiles.xml', 'data' => $signatureXml],
        ]));

        $references = $graph->preflightDigitalSignatureSignedInfoReferences('/_xmlsignatures/sig-profiles.xml');

        $t->same(6, count($references));
        $t->same([
            'inclusive-c14n-1.0',
            'inclusive-c14n-1.0-with-comments',
            'exclusive-c14n-1.0',
            'exclusive-c14n-1.0-with-comments',
            'c14n-1.1',
            'c14n-1.1-with-comments',
        ], array_map(
            static fn (array $reference): string => $reference['canonicalizationTransforms'][0]['profile'],
            $references,
        ));
        $t->same([false, false, true, true, false, false], array_map(
            static fn (array $reference): bool => $reference['relationshipTransformFollowingCanonicalization']['exclusive'],
            $references,
        ));
        $t->same([false, true, false, true, false, true], array_map(
            static fn (array $reference): bool => $reference['relationshipTransformFollowingCanonicalization']['withComments'],
            $references,
        ));
        $t->same(['1.0', '1.0', '1.0', '1.0', '1.1', '1.1'], array_map(
            static fn (array $reference): string => $reference['canonicalizationTransforms'][0]['version'],
            $references,
        ));
        foreach ($references as $index => $reference) {
            $t->same($canonicalizationAlgorithms[$index], $reference['canonicalizationTransformAlgorithms'][0]);
            $t->same($reference['canonicalizationTransforms'][0], $reference['relationshipTransformFollowingCanonicalization']);
            $t->same(true, $reference['relationshipTransformFollowedByCanonicalization']);
            $t->same(true, $reference['valid']);
        }
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
    'flags OPC digital signature origins without signature relationships' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $emptySignatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdOriginAudit" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="../docProps/thumbnail.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $emptySignatureOriginRelationshipsXml],
        ]));

        $signatures = $graph->preflightDigitalSignatures();

        $t->same(1, count($signatures));
        $t->same('rIdSignatureOrigin', $signatures[0]['id']);
        $t->same('/_xmlsignatures/origin.sigs', $signatures[0]['targetPart']);
        $t->same('/_xmlsignatures/_rels/origin.sigs.rels', $signatures[0]['relationshipPartName']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-origin', $signatures[0]['contentType']);
        $t->same([], $signatures[0]['signatures']);
        $t->same(false, $signatures[0]['valid']);
        $t->same(['missing-digital-signature-signature-relationships'], $signatures[0]['issues']);
    },
    'preflights OPC digital signature relationship package roles' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/root-sig.xml" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/bad-origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
  <Override PartName="/_xmlsignatures/bad-sig.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
  <Relationship Id="rIdRootSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="_xmlsignatures/root-sig.xml"/>
</Relationships>
XML;

        $signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignature1" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMisplacedOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="../_xmlsignatures/bad-origin.sigs"/>
  <Relationship Id="rIdMisplacedSignature" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="../_xmlsignatures/bad-sig.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
            ['name' => '_xmlsignatures/sig1.xml', 'data' => '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/>'],
            ['name' => '_xmlsignatures/root-sig.xml', 'data' => '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/>'],
            ['name' => '_xmlsignatures/bad-origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/bad-sig.xml', 'data' => '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/>'],
        ]));

        $roles = $graph->preflightDigitalSignatureRelationshipRoles();
        $rolesById = [];
        foreach ($roles['roles'] as $role) {
            $rolesById[$role['id']] = $role;
        }

        $t->same(false, $roles['valid']);
        $t->same(2, $roles['originCount']);
        $t->same(3, $roles['signatureCount']);
        $t->same(['/_xmlsignatures/origin.sigs'], $roles['allowedSignatureSources']);
        $t->same([
            'rIdSignatureOrigin',
            'rIdRootSignature',
            'rIdSignature1',
            'rIdMisplacedOrigin',
            'rIdMisplacedSignature',
        ], array_keys($rolesById));

        $t->same('digital-signature-origin', $rolesById['rIdSignatureOrigin']['role']);
        $t->same('/', $rolesById['rIdSignatureOrigin']['source']);
        $t->same('/_xmlsignatures/origin.sigs', $rolesById['rIdSignatureOrigin']['targetPart']);
        $t->same('/', $rolesById['rIdSignatureOrigin']['expectedSource']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-origin', $rolesById['rIdSignatureOrigin']['expectedContentType']);
        $t->same(true, $rolesById['rIdSignatureOrigin']['sourceAllowed']);
        $t->same(true, $rolesById['rIdSignatureOrigin']['valid']);
        $t->same([], $rolesById['rIdSignatureOrigin']['issues']);

        $t->same('digital-signature-signature', $rolesById['rIdSignature1']['role']);
        $t->same('/_xmlsignatures/origin.sigs', $rolesById['rIdSignature1']['source']);
        $t->same('/_xmlsignatures/sig1.xml', $rolesById['rIdSignature1']['targetPart']);
        $t->same(null, $rolesById['rIdSignature1']['expectedSource']);
        $t->same(['/_xmlsignatures/origin.sigs'], $rolesById['rIdSignature1']['allowedSignatureSources']);
        $t->same(true, $rolesById['rIdSignature1']['sourceAllowed']);
        $t->same('application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml', $rolesById['rIdSignature1']['expectedContentType']);
        $t->same(true, $rolesById['rIdSignature1']['valid']);
        $t->same([], $rolesById['rIdSignature1']['issues']);

        $t->same('/', $rolesById['rIdRootSignature']['source']);
        $t->same('/_xmlsignatures/root-sig.xml', $rolesById['rIdRootSignature']['targetPart']);
        $t->same(false, $rolesById['rIdRootSignature']['sourceAllowed']);
        $t->same('application/xml', $rolesById['rIdRootSignature']['contentType']);
        $t->same(false, $rolesById['rIdRootSignature']['valid']);
        $t->same([
            'digital-signature-signature-source-not-origin',
            'invalid-digital-signature-content-type',
        ], $rolesById['rIdRootSignature']['issues']);

        $t->same('/word/document.xml', $rolesById['rIdMisplacedOrigin']['source']);
        $t->same('/_xmlsignatures/bad-origin.sigs', $rolesById['rIdMisplacedOrigin']['targetPart']);
        $t->same(false, $rolesById['rIdMisplacedOrigin']['sourceAllowed']);
        $t->same(false, $rolesById['rIdMisplacedOrigin']['valid']);
        $t->same(['digital-signature-origin-source-not-package-root'], $rolesById['rIdMisplacedOrigin']['issues']);

        $t->same('/word/document.xml', $rolesById['rIdMisplacedSignature']['source']);
        $t->same('/_xmlsignatures/bad-sig.xml', $rolesById['rIdMisplacedSignature']['targetPart']);
        $t->same(false, $rolesById['rIdMisplacedSignature']['sourceAllowed']);
        $t->same(false, $rolesById['rIdMisplacedSignature']['valid']);
        $t->same(['digital-signature-signature-source-not-origin'], $rolesById['rIdMisplacedSignature']['issues']);
    },
    'does not authorize OPC digital signature sources from invalid origin targets' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/bad-origin.sigs" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdBadSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/bad-origin.sigs"/>
</Relationships>
XML;

        $signatureOriginRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSignature1" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/signature" Target="sig1.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => '_xmlsignatures/bad-origin.sigs', 'data' => ''],
            ['name' => '_xmlsignatures/_rels/bad-origin.sigs.rels', 'data' => $signatureOriginRelationshipsXml],
            ['name' => '_xmlsignatures/sig1.xml', 'data' => '<Signature xmlns="http://www.w3.org/2000/09/xmldsig#"/>'],
        ]));

        $roles = $graph->preflightDigitalSignatureRelationshipRoles();
        $rolesById = [];
        foreach ($roles['roles'] as $role) {
            $rolesById[$role['id']] = $role;
        }

        $t->same(false, $roles['valid']);
        $t->same(1, $roles['originCount']);
        $t->same(1, $roles['signatureCount']);
        $t->same([], $roles['allowedSignatureSources']);
        $t->same(['rIdBadSignatureOrigin', 'rIdSignature1'], array_keys($rolesById));

        $t->same('digital-signature-origin', $rolesById['rIdBadSignatureOrigin']['role']);
        $t->same('/', $rolesById['rIdBadSignatureOrigin']['source']);
        $t->same('/_xmlsignatures/bad-origin.sigs', $rolesById['rIdBadSignatureOrigin']['targetPart']);
        $t->same('application/xml', $rolesById['rIdBadSignatureOrigin']['contentType']);
        $t->same(true, $rolesById['rIdBadSignatureOrigin']['sourceAllowed']);
        $t->same(false, $rolesById['rIdBadSignatureOrigin']['valid']);
        $t->same(['invalid-digital-signature-origin-content-type'], $rolesById['rIdBadSignatureOrigin']['issues']);

        $t->same('digital-signature-signature', $rolesById['rIdSignature1']['role']);
        $t->same('/_xmlsignatures/bad-origin.sigs', $rolesById['rIdSignature1']['source']);
        $t->same('/_xmlsignatures/sig1.xml', $rolesById['rIdSignature1']['targetPart']);
        $t->same([], $rolesById['rIdSignature1']['allowedSignatureSources']);
        $t->same(false, $rolesById['rIdSignature1']['sourceAllowed']);
        $t->same(false, $rolesById['rIdSignature1']['valid']);
        $t->same(['digital-signature-signature-source-not-origin'], $rolesById['rIdSignature1']['issues']);
    },
    'preflights OPC encrypted package relationship policy' => static function (TestRunner $t): void {
        $encryptedRelationshipType = OpcRelationshipGraph::ENCRYPTED_PACKAGE_RELATIONSHIP_TYPE;
        $encryptedContentType = 'application/vnd.openxmlformats-package.encrypted-package';
        $contentTypesXml = <<<XML
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="bin" ContentType="application/octet-stream"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/EncryptedPackage" ContentType="{$encryptedContentType}"/>
</Types>
XML;

        $packageRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdEncryptedPackage" Type="{$encryptedRelationshipType}" Target="EncryptedPackage"/>
  <Relationship Id="rIdExternalEncryptedPackage" Type="{$encryptedRelationshipType}" Target="https://example.test/encrypted.docx" TargetMode="External"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdNestedEncryptedPackage" Type="{$encryptedRelationshipType}" Target="encrypted-review.bin"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'EncryptedPackage', 'data' => 'encrypted package bytes'],
            ['name' => 'word/encrypted-review.bin', 'data' => 'not an encrypted package'],
        ]));

        $encryptedPackages = [];
        foreach ($graph->preflightEncryptedPackages() as $row) {
            $encryptedPackages[$row['id']] = $row;
        }

        $t->same([
            'rIdEncryptedPackage',
            'rIdExternalEncryptedPackage',
            'rIdNestedEncryptedPackage',
        ], array_keys($encryptedPackages));

        $t->same('encrypted-package', $encryptedPackages['rIdEncryptedPackage']['role']);
        $t->same('/', $encryptedPackages['rIdEncryptedPackage']['source']);
        $t->same('/EncryptedPackage', $encryptedPackages['rIdEncryptedPackage']['targetPart']);
        $t->same($encryptedContentType, $encryptedPackages['rIdEncryptedPackage']['contentType']);
        $t->same($encryptedContentType, $encryptedPackages['rIdEncryptedPackage']['expectedContentType']);
        $t->same('/', $encryptedPackages['rIdEncryptedPackage']['expectedSource']);
        $t->same(false, $encryptedPackages['rIdEncryptedPackage']['expectedExternal']);
        $t->same(true, $encryptedPackages['rIdEncryptedPackage']['sourceAllowed']);
        $t->same(true, $encryptedPackages['rIdEncryptedPackage']['exists']);
        $t->same(true, $encryptedPackages['rIdEncryptedPackage']['valid']);
        $t->same([], $encryptedPackages['rIdEncryptedPackage']['issues']);

        $t->same(true, $encryptedPackages['rIdExternalEncryptedPackage']['external']);
        $t->same(null, $encryptedPackages['rIdExternalEncryptedPackage']['targetPart']);
        $t->same(false, $encryptedPackages['rIdExternalEncryptedPackage']['valid']);
        $t->same(['external-encrypted-package-target'], $encryptedPackages['rIdExternalEncryptedPackage']['issues']);

        $t->same('/word/document.xml', $encryptedPackages['rIdNestedEncryptedPackage']['source']);
        $t->same('/word/encrypted-review.bin', $encryptedPackages['rIdNestedEncryptedPackage']['targetPart']);
        $t->same(false, $encryptedPackages['rIdNestedEncryptedPackage']['sourceAllowed']);
        $t->same('application/octet-stream', $encryptedPackages['rIdNestedEncryptedPackage']['contentType']);
        $t->same(false, $encryptedPackages['rIdNestedEncryptedPackage']['valid']);
        $t->same([
            'encrypted-package-source-not-package-root',
            'invalid-encrypted-package-content-type',
        ], $encryptedPackages['rIdNestedEncryptedPackage']['issues']);

        $documentOnly = $graph->preflightEncryptedPackages('/word/document.xml');
        $t->same(1, count($documentOnly));
        $t->same('rIdNestedEncryptedPackage', $documentOnly[0]['id']);

        $typeInventory = [];
        foreach ($graph->relationshipTypeInventory() as $relationshipType) {
            $typeInventory[$relationshipType['type']] = $relationshipType;
        }
        $encryptedInventory = $typeInventory[$encryptedRelationshipType];
        $t->same('encrypted-package', $encryptedInventory['knownRole']);
        $t->same('package-root', $encryptedInventory['sourceScope']);
        $t->same('package', $encryptedInventory['singletonScope']);
        $t->same(false, $encryptedInventory['policyValid']);
        $t->same([
            'encrypted-package-source-not-package-root',
            'multiple-encrypted-package-relationships',
        ], $encryptedInventory['policyIssues']);

        $consistency = $graph->preflightPackageConsistency();
        $t->same(false, $consistency['relationshipTypePoliciesValid']);
        $policyByType = [];
        foreach ($consistency['relationshipTypePolicies'] as $policy) {
            $policyByType[$policy['type']] = $policy;
        }
        $t->same([
            'encrypted-package-source-not-package-root',
            'multiple-encrypted-package-relationships',
        ], $policyByType[$encryptedRelationshipType]['policyIssues']);
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
    'preflights nested OPC graphs for embedded package relationships' => static function (TestRunner $t): void {
        $nestedWorkbookBytes = ZipPackage::build([
            ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
</Types>
XML],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdWorkbook" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="xl/workbook.xml"/></Relationships>'],
            ['name' => 'xl/workbook.xml', 'data' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'],
            ['name' => 'xl/_rels/workbook.xml.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdSheet1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>'],
            ['name' => 'xl/worksheets/sheet1.xml', 'data' => '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'],
        ]);

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/source-workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/embeddings/malformed-workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/embeddings/missing-workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-workbook.xlsx"/>
  <Relationship Id="rIdMalformedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/malformed-workbook.xlsx"/>
  <Relationship Id="rIdExternalWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="https://example.test/source-workbook.xlsx" TargetMode="External"/>
  <Relationship Id="rIdMissingWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/missing-workbook.xlsx"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/embeddings/source-workbook.xlsx', 'data' => $nestedWorkbookBytes],
            ['name' => 'word/embeddings/malformed-workbook.xlsx', 'data' => 'not a zip package'],
        ]));

        $embeddedGraphs = [];
        foreach ($graph->preflightEmbeddedPackageGraphs('/word/document.xml') as $embeddedGraph) {
            $embeddedGraphs[$embeddedGraph['id']] = $embeddedGraph;
        }

        $t->same([
            'rIdEmbeddedWorkbook',
            'rIdMalformedWorkbook',
            'rIdExternalWorkbook',
            'rIdMissingWorkbook',
        ], array_keys($embeddedGraphs));

        $t->same('/word/embeddings/source-workbook.xlsx', $embeddedGraphs['rIdEmbeddedWorkbook']['targetPart']);
        $t->same(true, $embeddedGraphs['rIdEmbeddedWorkbook']['expanded']);
        $t->same(5, $embeddedGraphs['rIdEmbeddedWorkbook']['nestedPackagePartCount']);
        $t->same(2, $embeddedGraphs['rIdEmbeddedWorkbook']['nestedRelationshipSourceCount']);
        $t->same(['/', '/xl/workbook.xml'], $embeddedGraphs['rIdEmbeddedWorkbook']['nestedSourcePartNames']);
        $t->same(1, $embeddedGraphs['rIdEmbeddedWorkbook']['nestedOfficeDocument']['relationshipCount'] ?? null);
        $t->same(true, $embeddedGraphs['rIdEmbeddedWorkbook']['nestedOfficeDocument']['valid'] ?? null);
        $t->same('/xl/workbook.xml', $embeddedGraphs['rIdEmbeddedWorkbook']['nestedOfficeDocument']['relationships'][0]['targetPart'] ?? null);
        $t->same('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml', $embeddedGraphs['rIdEmbeddedWorkbook']['nestedOfficeDocument']['relationships'][0]['contentType'] ?? null);
        $t->same('/', $embeddedGraphs['rIdEmbeddedWorkbook']['nestedRelationshipClosure']['source'] ?? null);
        $t->same(2, $embeddedGraphs['rIdEmbeddedWorkbook']['nestedRelationshipClosure']['expandedSourceCount'] ?? null);
        $t->same(1, $embeddedGraphs['rIdEmbeddedWorkbook']['nestedRelationshipClosure']['stopCount'] ?? null);
        $t->same([], $embeddedGraphs['rIdEmbeddedWorkbook']['nestedRelationshipClosure']['issues'] ?? null);
        $t->same(true, $embeddedGraphs['rIdEmbeddedWorkbook']['valid']);
        $t->same([], $embeddedGraphs['rIdEmbeddedWorkbook']['issues']);

        $t->same('/word/embeddings/malformed-workbook.xlsx', $embeddedGraphs['rIdMalformedWorkbook']['targetPart']);
        $t->same(false, $embeddedGraphs['rIdMalformedWorkbook']['expanded']);
        $t->same(null, $embeddedGraphs['rIdMalformedWorkbook']['nestedOfficeDocument']);
        $t->same(false, $embeddedGraphs['rIdMalformedWorkbook']['valid']);
        $t->same(['embedded-package-parse-error'], $embeddedGraphs['rIdMalformedWorkbook']['issues']);
        $t->contains('ZIP', $embeddedGraphs['rIdMalformedWorkbook']['parseError'] ?? '');

        $t->same(true, $embeddedGraphs['rIdExternalWorkbook']['external']);
        $t->same(false, $embeddedGraphs['rIdExternalWorkbook']['expanded']);
        $t->same(null, $embeddedGraphs['rIdExternalWorkbook']['targetPart']);
        $t->same(false, $embeddedGraphs['rIdExternalWorkbook']['valid']);
        $t->same(['external-embedded-package-not-expanded'], $embeddedGraphs['rIdExternalWorkbook']['issues']);

        $t->same('/word/embeddings/missing-workbook.xlsx', $embeddedGraphs['rIdMissingWorkbook']['targetPart']);
        $t->same(false, $embeddedGraphs['rIdMissingWorkbook']['expanded']);
        $t->same(false, $embeddedGraphs['rIdMissingWorkbook']['valid']);
        $t->same(['missing-in-package'], $embeddedGraphs['rIdMissingWorkbook']['issues']);

        $summary = $graph->embeddedPackageGraphSummary('/word/document.xml');
        $t->same('/word/document.xml', $summary['source']);
        $t->same(false, $summary['valid']);
        $t->same(4, $summary['embeddedPackageCount']);
        $t->same(1, $summary['validPackageCount']);
        $t->same(3, $summary['invalidPackageCount']);
        $t->same(1, $summary['expandedCount']);
        $t->same(3, $summary['blockedCount']);
        $t->same(1, $summary['externalCount']);
        $t->same(1, $summary['missingTargetCount']);
        $t->same(1, $summary['parseErrorCount']);
        $t->same(5, $summary['nestedPackagePartCount']);
        $t->same(2, $summary['nestedRelationshipSourceCount']);
        $t->same(1, $summary['nestedRelationshipStopCount']);
        $t->same(0, $summary['nestedMissingStopCount']);
        $t->same(0, $summary['nestedExternalStopCount']);
        $t->same(1, $summary['nestedUnloadedStopCount']);
        $t->same(['rIdEmbeddedWorkbook'], $summary['expandedIds']);
        $t->same(['rIdExternalWorkbook', 'rIdMalformedWorkbook', 'rIdMissingWorkbook'], $summary['blockedIds']);
        $t->same(['rIdExternalWorkbook'], $summary['externalIds']);
        $t->same(['/word/embeddings/missing-workbook.xlsx'], $summary['missingTargetParts']);
        $t->same(['rIdMalformedWorkbook'], $summary['parseErrorIds']);
        $t->same([
            'embedded-package-parse-error' => 1,
            'external-embedded-package-not-expanded' => 1,
            'missing-in-package' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'embedded-package-parse-error',
            'external-embedded-package-not-expanded',
            'missing-in-package',
        ], $summary['issues']);

        $packagesById = [];
        foreach ($summary['packages'] as $package) {
            $packagesById[$package['id']] = $package;
        }
        $t->same(1, $packagesById['rIdEmbeddedWorkbook']['nestedRelationshipStopCount']);
        $t->same([], $packagesById['rIdEmbeddedWorkbook']['nestedRelationshipIssues']);
        $t->same(false, $packagesById['rIdExternalWorkbook']['expanded']);
        $t->same(false, $packagesById['rIdMalformedWorkbook']['valid']);
        $t->same(false, $packagesById['rIdMissingWorkbook']['exists']);
    },
    'preflights embedded OPC package relationship closure for importer review' => static function (TestRunner $t): void {
        $nestedWorkbookBytes = ZipPackage::build([
            ['name' => '[Content_Types].xml', 'data' => <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/media/logo.png" ContentType="image/png"/>
  <Override PartName="/xl/drawings/missing.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>
</Types>
XML],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdWorkbook" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="xl/workbook.xml"/></Relationships>'],
            ['name' => 'xl/workbook.xml', 'data' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'],
            ['name' => 'xl/_rels/workbook.xml.rels', 'data' => <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSheet1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rIdLogo" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/logo.png"/>
  <Relationship Id="rIdMissingDrawing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/diagramData" Target="drawings/missing.xml"/>
  <Relationship Id="rIdExternalTemplate" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/template" TargetMode="External"/>
</Relationships>
XML],
            ['name' => 'xl/worksheets/sheet1.xml', 'data' => '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'],
            ['name' => 'xl/media/logo.png', 'data' => 'PNG'],
        ]);

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/source-workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
</Types>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-workbook.xlsx"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdDocument" Type="' . OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE . '" Target="word/document.xml"/></Relationships>'],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/embeddings/source-workbook.xlsx', 'data' => $nestedWorkbookBytes],
        ]));

        $embedded = $graph->preflightEmbeddedPackageGraphs('/word/document.xml')[0] ?? null;
        $t->true(is_array($embedded));
        $t->same('rIdEmbeddedWorkbook', $embedded['id'] ?? null);
        $t->same(true, $embedded['expanded'] ?? null);
        $t->same(false, $embedded['valid'] ?? null);
        $t->same(['embedded-missing-in-package'], $embedded['issues'] ?? null);

        $closure = $embedded['nestedRelationshipClosure'] ?? null;
        $t->true(is_array($closure));
        $t->same('/', $closure['source'] ?? null);
        $t->same(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE, $closure['relationshipType'] ?? null);
        $t->same(false, $closure['valid'] ?? null);
        $t->same(['missing-in-package'], $closure['issues'] ?? null);
        $t->same(2, $closure['expandedSourceCount'] ?? null);
        $t->same(4, $closure['stopCount'] ?? null);
        $t->same(1, $closure['externalStopCount'] ?? null);
        $t->same(1, $closure['missingStopCount'] ?? null);
        $t->same(2, $closure['unloadedStopCount'] ?? null);

        $sources = [];
        foreach ($closure['sources'] as $source) {
            $sources[$source['source']] = $source;
        }
        $t->same(['/', '/xl/workbook.xml'], array_keys($sources));
        $t->same(true, $sources['/xl/workbook.xml']['reachable']);
        $t->same(1, $sources['/xl/workbook.xml']['depth']);
        $t->same(4, $sources['/xl/workbook.xml']['relationshipCount']);
        $t->same(['/xl/drawings/missing.xml'], $sources['/xl/workbook.xml']['missingTargetParts']);
        $t->same(['https://example.test/template'], $sources['/xl/workbook.xml']['externalTargets']);

        $stops = [];
        foreach ($closure['stops'] as $stop) {
            $stops[$stop['id']] = $stop;
        }
        $t->same('target-source-not-loaded', $stops['rIdSheet1']['stopReason']);
        $t->same('/xl/worksheets/sheet1.xml', $stops['rIdSheet1']['targetPart']);
        $t->same('target-source-not-loaded', $stops['rIdLogo']['stopReason']);
        $t->same('/xl/media/logo.png', $stops['rIdLogo']['targetPart']);
        $t->same('missing-target', $stops['rIdMissingDrawing']['stopReason']);
        $t->same('/xl/drawings/missing.xml', $stops['rIdMissingDrawing']['targetPart']);
        $t->same(['missing-in-package'], $stops['rIdMissingDrawing']['issues']);
        $t->same('external-target', $stops['rIdExternalTemplate']['stopReason']);
        $t->same(null, $stops['rIdExternalTemplate']['targetPart']);
    },
    'preflights OPC relationship selectors by SourceId and SourceType' => static function (TestRunner $t): void {
        $selectorContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/source-workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
</Types>
XML;

        $selectorPackageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $selectorDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-workbook.xlsx"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $selectorContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $selectorPackageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $selectorDocumentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/source-workbook.xlsx', 'data' => 'PK' . "\x03\x04"],
        ]));

        $selector = $graph->preflightRelationshipSelector(
            '/word/document.xml',
            ['rIdHero', 'rIdHero', 'rIdReviewer', 'rIdMissing'],
            [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE, 'http://example.test/missing-relationship-type'],
        );

        $selected = [];
        foreach ($selector['relationships'] as $relationship) {
            $selected[$relationship['id']] = $relationship;
        }

        $t->same('/word/document.xml', $selector['source']);
        $t->same(['rIdHero', 'rIdReviewer', 'rIdMissing'], $selector['sourceIds']);
        $t->same([
            OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE,
            'http://example.test/missing-relationship-type',
        ], $selector['sourceTypes']);
        $t->same(['rIdMissing'], $selector['unmatchedSourceIds']);
        $t->same(['http://example.test/missing-relationship-type'], $selector['unmatchedSourceTypes']);
        $t->same(false, $selector['valid']);
        $t->same(['unmatched-source-id', 'unmatched-source-type'], $selector['issues']);
        $t->same(['rIdHero', 'rIdEmbeddedWorkbook', 'rIdReviewer'], array_keys($selected));

        $t->same(true, $selected['rIdHero']['selectedBySourceId']);
        $t->same(false, $selected['rIdHero']['selectedBySourceType']);
        $t->same('/word/media/hero.png', $selected['rIdHero']['targetPart']);
        $t->same('image/png', $selected['rIdHero']['contentType']);
        $t->same(true, $selected['rIdHero']['valid']);

        $t->same(false, $selected['rIdEmbeddedWorkbook']['selectedBySourceId']);
        $t->same(true, $selected['rIdEmbeddedWorkbook']['selectedBySourceType']);
        $t->same('/word/embeddings/source-workbook.xlsx', $selected['rIdEmbeddedWorkbook']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.package', $selected['rIdEmbeddedWorkbook']['contentType']);
        $t->same(true, $selected['rIdEmbeddedWorkbook']['valid']);

        $t->same(true, $selected['rIdReviewer']['selectedBySourceId']);
        $t->same(false, $selected['rIdReviewer']['selectedBySourceType']);
        $t->same(true, $selected['rIdReviewer']['external']);
        $t->same('https', $selected['rIdReviewer']['externalTargetScheme']);
        $t->same(true, $selected['rIdReviewer']['externalTargetAllowed']);
        $t->same(null, $selected['rIdReviewer']['targetPart']);

        $overlap = $graph->preflightRelationshipSelector(
            '/word/document.xml',
            ['rIdHero'],
            ['http://schemas.openxmlformats.org/officeDocument/2006/relationships/image'],
        );
        $t->same(true, $overlap['valid']);
        $t->same([], $overlap['issues']);
        $t->same(1, count($overlap['relationships']));
        $t->same(true, $overlap['relationships'][0]['selectedBySourceId']);
        $t->same(true, $overlap['relationships'][0]['selectedBySourceType']);

        $missingSource = $graph->preflightRelationshipSelector('/word/missing.xml', ['rIdHero'], []);
        $t->same(false, $missingSource['valid']);
        $t->same(['relationship-source-not-loaded', 'unmatched-source-id'], $missingSource['issues']);
        $t->same([], $missingSource['relationships']);

        $empty = $graph->preflightRelationshipSelector('/word/document.xml', [], []);
        $t->same(false, $empty['valid']);
        $t->same(['empty-relationship-selector'], $empty['issues']);

        $t->throws(\InvalidArgumentException::class, static fn (): array => $graph->preflightRelationshipSelector('/word/document.xml', ['1bad'], []));
        $t->throws(\InvalidArgumentException::class, static fn (): array => $graph->preflightRelationshipSelector('/word/document.xml', ['rIdHero'], ['']));
    },
    'rejects OPC relationship selector SourceType values that are not absolute URI references' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-invalid-source-type.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipsGroupReference SourceType="officeDocument/relationships/image"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-invalid-source-type.xml', 'data' => $signatureXml],
        ]));

        $selector = $graph->preflightRelationshipSelector(
            '/word/document.xml',
            [],
            [
                'officeDocument/relationships/image',
                'http:',
                OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE,
            ],
        );

        $t->same([
            'officeDocument/relationships/image',
            'http:',
            OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE,
        ], $selector['sourceTypes']);
        $t->same([
            'officeDocument/relationships/image',
            'http:',
        ], $selector['invalidSourceTypes']);
        $t->same([
            'officeDocument/relationships/image' => ['source-type-not-absolute-uri'],
            'http:' => ['source-type-empty-uri-body'],
        ], $selector['sourceTypeIssues']);
        $t->same([], $selector['unmatchedSourceTypes']);
        $t->same(false, $selector['valid']);
        $t->same(['invalid-source-type'], $selector['issues']);
        $t->same(1, count($selector['relationships']));
        $t->same('rIdHero', $selector['relationships'][0]['id']);
        $t->same(true, $selector['relationships'][0]['selectedBySourceType']);
        $t->same('/word/media/hero.png', $selector['relationships'][0]['targetPart']);
        $t->same('image/png', $selector['relationships'][0]['contentType']);

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-invalid-source-type.xml');

        $t->same(1, count($transforms));
        $t->same('/word/document.xml', $transforms[0]['source']);
        $t->same([], $transforms[0]['sourceIds']);
        $t->same(['officeDocument/relationships/image'], $transforms[0]['sourceTypes']);
        $t->same(['officeDocument/relationships/image'], $transforms[0]['invalidSourceTypes']);
        $t->same([
            'officeDocument/relationships/image' => ['source-type-not-absolute-uri'],
        ], $transforms[0]['sourceTypeIssues']);
        $t->same([], $transforms[0]['relationshipIds']);
        $t->same(0, $transforms[0]['relationshipCount']);
        $t->same(false, $transforms[0]['selectorValid']);
        $t->same(true, $transforms[0]['relationshipTargetsValid']);
        $t->same(false, $transforms[0]['valid']);
        $t->same(['invalid-source-type'], $transforms[0]['issues']);
        $t->same('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>', $transforms[0]['relationshipXml']);
    },
    'materializes OPC relationship transform payloads for selected relationships' => static function (TestRunner $t): void {
        $selectorContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/source-workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
</Types>
XML;

        $selectorPackageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $selectorDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-workbook.xlsx"/>
  <Relationship Id="rIdDraft" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="draft.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $selectorContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $selectorPackageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $selectorDocumentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/source-workbook.xlsx', 'data' => 'PK' . "\x03\x04"],
        ]));

        $transform = $graph->materializeRelationshipTransform(
            '/word/document.xml',
            ['rIdReviewer', 'rIdHero', 'rIdHero'],
            [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE],
        );

        $t->same('/word/document.xml', $transform['source']);
        $t->same('/word/_rels/document.xml.rels', $transform['relationshipPartName']);
        $t->same('http://schemas.openxmlformats.org/package/2006/RelationshipTransform', $transform['transformAlgorithm']);
        $t->same(['rIdReviewer', 'rIdHero'], $transform['sourceIds']);
        $t->same([OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE], $transform['sourceTypes']);
        $t->same(['rIdEmbeddedWorkbook', 'rIdHero', 'rIdReviewer'], $transform['relationshipIds']);
        $t->same(3, $transform['relationshipCount']);
        $t->same(true, $transform['selectorValid']);
        $t->same(true, $transform['relationshipTargetsValid']);
        $t->same(true, $transform['valid']);
        $t->same([], $transform['issues']);
        $t->same([
            'rIdReviewer',
            'rIdHero',
            'rIdEmbeddedWorkbook',
        ], array_column($transform['relationships'], 'id'));
        $t->same('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdEmbeddedWorkbook" Target="embeddings/source-workbook.xlsx" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"></Relationship><Relationship Id="rIdHero" Target="media/hero.png" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"></Relationship><Relationship Id="rIdReviewer" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"></Relationship></Relationships>', $transform['relationshipXml']);
        $t->same(false, str_contains($transform['relationshipXml'], 'rIdDraft'));
        $t->same(false, str_contains($transform['relationshipXml'], 'TargetMode="Internal"'));
        $t->same(false, str_contains($transform['relationshipXml'], '/word/media/hero.png'));

        $invalid = $graph->materializeRelationshipTransform('/word/document.xml', ['rIdMissing'], []);
        $t->same(false, $invalid['valid']);
        $t->same(false, $invalid['selectorValid']);
        $t->same(['unmatched-source-id'], $invalid['issues']);
        $t->same([], $invalid['relationshipIds']);
        $t->same('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"></Relationships>', $invalid['relationshipXml']);

        $missingSource = $graph->materializeRelationshipTransform('/word/missing.xml', ['rIdHero'], []);
        $t->same('/word/_rels/missing.xml.rels', $missingSource['relationshipPartName']);
        $t->same(false, $missingSource['valid']);
        $t->same(['relationship-source-not-loaded', 'unmatched-source-id'], $missingSource['issues']);
        $t->same(null, $missingSource['relationshipXml']);
    },
    'summarizes OPC relationship transform payload fingerprints' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-fingerprint.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review?post=42&amp;stage=import" TargetMode="External"/>
  <Relationship Id="rIdDraft" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="draft.xml"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:opc="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <opc:RelationshipReference SourceId="rIdImage"/>
          <opc:RelationshipReference SourceId="rIdExternal"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-fingerprint.xml', 'data' => $signatureXml],
        ]));

        $expectedXml = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdExternal" Target="https://example.test/review?post=42&amp;stage=import" TargetMode="External" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"></Relationship><Relationship Id="rIdImage" Target="media/hero.png" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"></Relationship></Relationships>';
        $expectedHash = hash('sha256', $expectedXml);
        $materialized = $graph->materializeRelationshipTransform(
            '/word/document.xml',
            ['rIdImage', 'rIdExternal'],
        );

        $t->same(['rIdExternal', 'rIdImage'], $materialized['relationshipIds']);
        $t->same($expectedXml, $materialized['relationshipXml']);
        $t->same(strlen($expectedXml), $materialized['relationshipXmlBytes']);
        $t->same($expectedHash, $materialized['relationshipXmlSha256']);
        $t->same(64, strlen((string) $materialized['relationshipXmlSha256']));
        $t->same(true, ctype_xdigit((string) $materialized['relationshipXmlSha256']));
        $t->same(false, str_contains((string) $materialized['relationshipXml'], 'rIdDraft'));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-fingerprint.xml');
        $t->same(1, count($transforms));
        $t->same(true, $transforms[0]['valid']);
        $t->same($expectedXml, $transforms[0]['relationshipXml']);
        $t->same(strlen($expectedXml), $transforms[0]['relationshipXmlBytes']);
        $t->same($expectedHash, $transforms[0]['relationshipXmlSha256']);
        $t->same($materialized['relationshipXmlBytes'], $transforms[0]['relationshipXmlBytes']);
        $t->same($materialized['relationshipXmlSha256'], $transforms[0]['relationshipXmlSha256']);

        $missingSource = $graph->materializeRelationshipTransform('/word/missing.xml', ['rIdImage'], []);
        $t->same(null, $missingSource['relationshipXml']);
        $t->same(null, $missingSource['relationshipXmlBytes']);
        $t->same(null, $missingSource['relationshipXmlSha256']);
    },
    'summarizes OPC signature relationship transform provenance for importer review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-transform-summary.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-workbook.xlsx"/>
  <Relationship Id="rIdDraft" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="draft.xml"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:opc="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <opc:RelationshipReference SourceId="rIdHero"/>
          <opc:RelationshipReference SourceId="rIdReviewer"/>
          <opc:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/document.xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <opc:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/source-workbook.xlsx', 'data' => 'PK'],
            ['name' => '_xmlsignatures/sig-transform-summary.xml', 'data' => $signatureXml],
        ]));

        $expectedXml = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdEmbeddedWorkbook" Target="embeddings/source-workbook.xlsx" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"></Relationship><Relationship Id="rIdHero" Target="media/hero.png" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"></Relationship><Relationship Id="rIdReviewer" Target="https://example.test/review" TargetMode="External" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"></Relationship></Relationships>';
        $expectedHash = hash('sha256', $expectedXml);
        $summary = $graph->signatureRelationshipTransformSummary('/_xmlsignatures/sig-transform-summary.xml');

        $t->same('/_xmlsignatures/sig-transform-summary.xml', $summary['signaturePart']);
        $t->same(false, $summary['valid']);
        $t->same(2, $summary['transformCount']);
        $t->same(1, $summary['validTransformCount']);
        $t->same(1, $summary['invalidTransformCount']);
        $t->same(1, $summary['relationshipPartCount']);
        $t->same(1, $summary['sourceCount']);
        $t->same(3, $summary['selectedRelationshipCount']);
        $t->same(2, $summary['selectedInternalTargetPartCount']);
        $t->same(1, $summary['selectedExternalTargetCount']);
        $t->same(1, $summary['relationshipXmlPayloadCount']);
        $t->same(['/word/_rels/document.xml.rels'], $summary['relationshipPartNames']);
        $t->same(['/word/document.xml'], $summary['sources']);
        $t->same(['rIdEmbeddedWorkbook', 'rIdHero', 'rIdReviewer'], $summary['selectedRelationshipIds']);
        $t->same(['/word/embeddings/source-workbook.xlsx', '/word/media/hero.png'], $summary['selectedInternalTargetParts']);
        $t->same(['https://example.test/review'], $summary['selectedExternalTargets']);
        $t->same(['/word/document.xml'], $summary['invalidReferenceUris']);
        $t->same([], $summary['invalidRelationshipPartNames']);
        $t->same([$expectedHash], $summary['relationshipXmlSha256s']);
        $t->same(['reference-not-relationship-part' => 1], $summary['issueCounts']);
        $t->same(['reference-not-relationship-part'], $summary['issues']);
        $t->same([
            [
                'referenceIndex' => 0,
                'referenceUri' => '/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml',
                'relationshipPartName' => '/word/_rels/document.xml.rels',
                'source' => '/word/document.xml',
                'relationshipCount' => 3,
                'relationshipXmlBytes' => strlen($expectedXml),
                'relationshipXmlSha256' => $expectedHash,
                'valid' => true,
                'issues' => [],
            ],
            [
                'referenceIndex' => 1,
                'referenceUri' => '/word/document.xml',
                'relationshipPartName' => '/word/document.xml',
                'source' => null,
                'relationshipCount' => 0,
                'relationshipXmlBytes' => null,
                'relationshipXmlSha256' => null,
                'valid' => false,
                'issues' => ['reference-not-relationship-part'],
            ],
        ], $summary['transforms']);
    },
    'summarizes OPC signed relationship type policy for importer review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Default Extension="xlsx" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-signed-relationships.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
  <Relationship Id="rIdUnsafeReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-workbook.xlsx"/>
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdDraft" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="draft.xml"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:opc="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <opc:RelationshipReference SourceId="rIdHero"/>
          <opc:RelationshipReference SourceId="rIdReviewer"/>
          <opc:RelationshipReference SourceId="rIdUnsafeReviewer"/>
          <opc:RelationshipReference SourceId="rIdMissingImage"/>
          <opc:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/source-workbook.xlsx', 'data' => 'PK'],
            ['name' => '_xmlsignatures/sig-signed-relationships.xml', 'data' => $signatureXml],
        ]));

        $summary = $graph->signedRelationshipPolicySummary('/_xmlsignatures/sig-signed-relationships.xml', [
            OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE,
            OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE,
        ]);

        $t->same('/_xmlsignatures/sig-signed-relationships.xml', $summary['signaturePart']);
        $t->same(false, $summary['valid']);
        $t->same([
            OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE,
            OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE,
        ], $summary['allowedRelationshipTypes']);
        $t->same(1, $summary['transformCount']);
        $t->same(5, $summary['selectedRelationshipCount']);
        $t->same(3, $summary['allowedRelationshipCount']);
        $t->same(2, $summary['disallowedRelationshipCount']);
        $t->same(2, $summary['externalRelationshipCount']);
        $t->same(3, $summary['internalRelationshipCount']);
        $t->same(2, $summary['invalidRelationshipCount']);
        $t->same(1, $summary['unsafeExternalRelationshipCount']);
        $t->same(1, $summary['missingTargetRelationshipCount']);
        $t->same([
            'rIdEmbeddedWorkbook',
            'rIdHero',
            'rIdMissingImage',
            'rIdReviewer',
            'rIdUnsafeReviewer',
        ], $summary['selectedRelationshipIds']);
        $t->same([
            OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE,
            OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE,
            OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE,
        ], $summary['selectedRelationshipTypes']);
        $t->same([OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE], $summary['disallowedRelationshipTypes']);
        $t->same(['/word/embeddings/source-workbook.xlsx', '/word/media/hero.png', '/word/media/missing.png'], $summary['selectedInternalTargetParts']);
        $t->same(['https://example.test/review', 'javascript:alert(1)'], $summary['selectedExternalTargets']);
        $t->same([
            'external-signed-relationship' => 2,
            'missing-in-package' => 1,
            'selected-relationship-target-issues' => 1,
            'signed-relationship-type-not-allowed' => 2,
            'unsafe-external-signed-relationship' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'external-signed-relationship',
            'missing-in-package',
            'selected-relationship-target-issues',
            'signed-relationship-type-not-allowed',
            'unsafe-external-signed-relationship',
        ], $summary['issues']);
        $t->same([
            [
                'source' => '/word/document.xml',
                'id' => 'rIdReviewer',
                'type' => OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE,
                'target' => 'https://example.test/review',
                'targetPart' => null,
                'contentType' => null,
                'external' => true,
                'selectedBySourceId' => true,
                'selectedBySourceType' => false,
                'allowedType' => false,
                'externalTargetAllowed' => true,
                'valid' => true,
                'issues' => [],
                'policyIssues' => [
                    'external-signed-relationship',
                    'signed-relationship-type-not-allowed',
                ],
            ],
            [
                'source' => '/word/document.xml',
                'id' => 'rIdUnsafeReviewer',
                'type' => OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE,
                'target' => 'javascript:alert(1)',
                'targetPart' => null,
                'contentType' => null,
                'external' => true,
                'selectedBySourceId' => true,
                'selectedBySourceType' => false,
                'allowedType' => false,
                'externalTargetAllowed' => false,
                'valid' => false,
                'issues' => ['external-target-unsafe-scheme'],
                'policyIssues' => [
                    'external-signed-relationship',
                    'signed-relationship-type-not-allowed',
                    'unsafe-external-signed-relationship',
                ],
            ],
        ], $summary['disallowedRelationships']);
        $t->same('rIdMissingImage', $summary['invalidRelationships'][0]['id']);
        $t->same(['missing-in-package'], $summary['invalidRelationships'][0]['issues']);
        $t->same('rIdUnsafeReviewer', $summary['invalidRelationships'][1]['id']);
        $t->same(['external-target-unsafe-scheme'], $summary['invalidRelationships'][1]['issues']);
        $t->same([
            [
                'referenceIndex' => 0,
                'referenceUri' => '/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml',
                'relationshipPartName' => '/word/_rels/document.xml.rels',
                'source' => '/word/document.xml',
                'selectedRelationshipCount' => 5,
                'disallowedRelationshipCount' => 2,
                'invalidRelationshipCount' => 2,
                'valid' => false,
                'issues' => [
                    'external-signed-relationship',
                    'missing-in-package',
                    'selected-relationship-target-issues',
                    'signed-relationship-type-not-allowed',
                    'unsafe-external-signed-relationship',
                ],
            ],
        ], $summary['transforms']);
    },
    'omits internal TargetMode attributes from OPC relationship transform XML' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdInternalImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
  <Relationship Id="rIdExternalSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source" TargetMode="External"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/review.png', 'data' => 'PNG'],
        ]));

        $transform = $graph->materializeRelationshipTransform(
            '/word/document.xml',
            ['rIdInternalImage', 'rIdExternalSource'],
        );

        $expectedXml = '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rIdExternalSource" Target="https://example.test/source" TargetMode="External" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"></Relationship><Relationship Id="rIdInternalImage" Target="media/review.png" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"></Relationship></Relationships>';
        $t->same(['rIdExternalSource', 'rIdInternalImage'], $transform['relationshipIds']);
        $t->same($expectedXml, $transform['relationshipXml']);
        $t->same(false, str_contains((string) $transform['relationshipXml'], 'TargetMode="Internal"'));
        $t->contains('TargetMode="External"', (string) $transform['relationshipXml']);

        $roundTrip = OpcRelationships::fromXml((string) $transform['relationshipXml'], '/word/document.xml');
        $t->same('/word/media/review.png', $roundTrip->resolveTarget('rIdInternalImage'));
        $t->same(OpcRelationship::TARGET_MODE_INTERNAL, $roundTrip->byId('rIdInternalImage')?->targetMode);
        $t->same('https://example.test/source', $roundTrip->resolveTarget('rIdExternalSource'));
        $t->true($roundTrip->byId('rIdExternalSource')?->isExternal() ?? false);
    },
    'accepts OPC relationships group reference selectors in signature transforms' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-alias.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/2006/12/xml-c14n11"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-alias.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-alias.xml');

        $t->same(1, count($transforms));
        $t->same('/word/_rels/document.xml.rels', $transforms[0]['relationshipPartName']);
        $t->same('/word/document.xml', $transforms[0]['source']);
        $t->same([], $transforms[0]['sourceIds']);
        $t->same(['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink'], $transforms[0]['sourceTypes']);
        $t->same(1, $transforms[0]['selectorChildCount']);
        $t->same(0, $transforms[0]['selectorRelationshipReferenceCount']);
        $t->same(1, $transforms[0]['selectorRelationshipGroupReferenceCount']);
        $t->same(0, $transforms[0]['selectorUnsupportedChildCount']);
        $t->same(0, $transforms[0]['selectorUnsupportedContentCount']);
        $t->same('http://www.w3.org/2006/12/xml-c14n11', $transforms[0]['followingCanonicalizationAlgorithm']);
        $t->same([
            'algorithm' => 'http://www.w3.org/2006/12/xml-c14n11',
            'profile' => 'c14n-1.1',
            'version' => '1.1',
            'exclusive' => false,
            'withComments' => false,
        ], $transforms[0]['followingCanonicalization']);
        $t->same(true, $transforms[0]['followedByCanonicalization']);
        $t->same(['rIdReviewer'], $transforms[0]['relationshipIds']);
        $t->same(1, $transforms[0]['relationshipCount']);
        $t->same(true, $transforms[0]['selectorValid']);
        $t->same(true, $transforms[0]['relationshipTargetsValid']);
        $t->same(true, $transforms[0]['valid']);
        $t->same([], $transforms[0]['issues']);
        $t->contains('TargetMode="External"', $transforms[0]['relationshipXml']);
        $t->contains('Id="rIdReviewer"', $transforms[0]['relationshipXml']);
        $t->same(false, str_contains((string) $transforms[0]['relationshipXml'], 'rIdHero'));
    },
    'normalizes case-equivalent OPC signature relationship transform references' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/Word/Document.XML" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-case-equivalent.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/Hero.PNG"/>
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.XML"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/Word/_rels/Document.XML.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdStyles"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'Word/Document.XML', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'Word/_rels/Document.XML.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'Word/media/Hero.PNG', 'data' => 'PNG'],
            ['name' => 'Word/styles.XML', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => '_xmlsignatures/sig-case-equivalent.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-case-equivalent.xml');

        $t->same(2, count($transforms));
        $t->same('/word/_rels/document.xml.rels', $transforms[0]['referenceUri']);
        $t->same('/Word/_rels/Document.XML.rels', $transforms[0]['relationshipPartName']);
        $t->same('/Word/Document.XML', $transforms[0]['source']);
        $t->same(true, $transforms[0]['referenceRelationshipPartExists']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[0]['referenceTargetContentType']);
        $t->same(['rIdHero'], $transforms[0]['relationshipIds']);
        $t->same(false, $transforms[0]['valid']);
        $t->same(['multiple-relationship-transforms-for-part'], $transforms[0]['issues']);
        $t->contains('Target="media/Hero.PNG"', $transforms[0]['relationshipXml']);

        $t->same('/Word/_rels/Document.XML.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml', $transforms[1]['referenceUri']);
        $t->same('/Word/_rels/Document.XML.rels', $transforms[1]['relationshipPartName']);
        $t->same('/Word/Document.XML', $transforms[1]['source']);
        $t->same(true, $transforms[1]['referenceContentTypeMatches']);
        $t->same(['rIdStyles'], $transforms[1]['relationshipIds']);
        $t->same(false, $transforms[1]['valid']);
        $t->same(['multiple-relationship-transforms-for-part'], $transforms[1]['issues']);
        $t->contains('Target="styles.XML"', $transforms[1]['relationshipXml']);
    },
    'preflights OPC relationship transform selector element shape issues' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-selector-shape.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero" mdssi:review="bad"><mdssi:Trace/></mdssi:RelationshipReference>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Extra="bad">text</mdssi:RelationshipsGroupReference>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-selector-shape.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-selector-shape.xml');

        $t->same(1, count($transforms));
        $t->same('/word/_rels/document.xml.rels', $transforms[0]['relationshipPartName']);
        $t->same('/word/document.xml', $transforms[0]['source']);
        $t->same(['rIdHero'], $transforms[0]['sourceIds']);
        $t->same(['http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink'], $transforms[0]['sourceTypes']);
        $t->same(2, $transforms[0]['selectorChildCount']);
        $t->same(1, $transforms[0]['selectorRelationshipReferenceCount']);
        $t->same(1, $transforms[0]['selectorRelationshipGroupReferenceCount']);
        $t->same(0, $transforms[0]['selectorUnsupportedChildCount']);
        $t->same(0, $transforms[0]['selectorUnsupportedContentCount']);
        $t->same(['rIdHero', 'rIdReviewer'], $transforms[0]['relationshipIds']);
        $t->same(2, $transforms[0]['relationshipCount']);
        $t->same(false, $transforms[0]['valid']);
        $t->same([
            'unsupported-relationship-transform-selector-attribute',
            'unsupported-relationship-transform-selector-child',
            'unsupported-relationship-transform-selector-content',
        ], $transforms[0]['issues']);
        $t->contains('Id="rIdHero"', $transforms[0]['relationshipXml']);
        $t->contains('Id="rIdReviewer"', $transforms[0]['relationshipXml']);
    },
    'preflights duplicate OPC relationship transform selectors for review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/source-workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/_xmlsignatures/sig-duplicate-selector.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-workbook.xlsx"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
          <mdssi:RelationshipReference SourceId="rIdHero"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/source-workbook.xlsx', 'data' => 'PK' . "\x03\x04"],
            ['name' => '_xmlsignatures/sig-duplicate-selector.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-duplicate-selector.xml');

        $t->same(1, count($transforms));
        $t->same('/word/document.xml', $transforms[0]['source']);
        $t->same(['rIdHero'], $transforms[0]['sourceIds']);
        $t->same([OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE], $transforms[0]['sourceTypes']);
        $t->same(['rIdHero'], $transforms[0]['duplicateSourceIds']);
        $t->same([OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE], $transforms[0]['duplicateSourceTypes']);
        $t->same(1, $transforms[0]['selectorDuplicateSourceIdCount']);
        $t->same(1, $transforms[0]['selectorDuplicateSourceTypeCount']);
        $t->same(4, $transforms[0]['selectorChildCount']);
        $t->same(2, $transforms[0]['selectorRelationshipReferenceCount']);
        $t->same(2, $transforms[0]['selectorRelationshipGroupReferenceCount']);
        $t->same(['rIdEmbeddedWorkbook', 'rIdHero'], $transforms[0]['relationshipIds']);
        $t->same(2, $transforms[0]['relationshipCount']);
        $t->same(true, $transforms[0]['selectorValid']);
        $t->same(true, $transforms[0]['relationshipTargetsValid']);
        $t->same(false, $transforms[0]['valid']);
        $t->same(['duplicate-source-id', 'duplicate-source-type'], $transforms[0]['issues']);
        $t->contains('Id="rIdEmbeddedWorkbook"', $transforms[0]['relationshipXml']);
        $t->contains('Id="rIdHero"', $transforms[0]['relationshipXml']);
        $t->same(false, str_contains((string) $transforms[0]['relationshipXml'], 'rIdReviewer'));
    },
    'reports OPC relationship transform selector overlap without duplicating materialized relationships' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/source-a.docx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/embeddings/source-b.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/_xmlsignatures/sig-selector-overlap.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdPackageA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-a.docx"/>
  <Relationship Id="rIdPackageB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-b.xlsx"/>
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdPackageA"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/embeddings/source-a.docx', 'data' => 'PK' . "\x03\x04"],
            ['name' => 'word/embeddings/source-b.xlsx', 'data' => 'PK' . "\x03\x04"],
            ['name' => '_xmlsignatures/sig-selector-overlap.xml', 'data' => $signatureXml],
        ]));

        $selector = $graph->preflightRelationshipSelector(
            '/word/document.xml',
            ['rIdPackageA'],
            [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE],
        );

        $t->same(['rIdPackageA'], $selector['selectorOverlappingRelationshipIds']);
        $t->same(1, $selector['selectorOverlapCount']);
        $t->same(true, $selector['valid']);
        $t->same([], $selector['issues']);

        $selectedById = [];
        foreach ($selector['relationships'] as $relationship) {
            $selectedById[$relationship['id']] = [
                'selectedBySourceId' => $relationship['selectedBySourceId'],
                'selectedBySourceType' => $relationship['selectedBySourceType'],
            ];
        }

        $t->same([
            'rIdPackageA' => ['selectedBySourceId' => true, 'selectedBySourceType' => true],
            'rIdPackageB' => ['selectedBySourceId' => false, 'selectedBySourceType' => true],
        ], $selectedById);

        $materialized = $graph->materializeRelationshipTransform(
            '/word/document.xml',
            ['rIdPackageA'],
            [OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE],
        );

        $t->same(['rIdPackageA', 'rIdPackageB'], $materialized['relationshipIds']);
        $t->same(2, $materialized['relationshipCount']);
        $t->same(['rIdPackageA'], $materialized['selectorOverlappingRelationshipIds']);
        $t->same(1, $materialized['selectorOverlapCount']);
        $t->same(true, $materialized['valid']);
        $t->same([], $materialized['issues']);
        $t->same(1, substr_count((string) $materialized['relationshipXml'], 'Id="rIdPackageA"'));
        $t->same(1, substr_count((string) $materialized['relationshipXml'], 'Id="rIdPackageB"'));
        $t->same(false, str_contains((string) $materialized['relationshipXml'], 'rIdReviewer'));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-selector-overlap.xml');
        $t->same(1, count($transforms));
        $t->same(['rIdPackageA'], $transforms[0]['sourceIds']);
        $t->same([OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE], $transforms[0]['sourceTypes']);
        $t->same([], $transforms[0]['duplicateSourceIds']);
        $t->same([], $transforms[0]['duplicateSourceTypes']);
        $t->same(['rIdPackageA'], $transforms[0]['selectorOverlappingRelationshipIds']);
        $t->same(1, $transforms[0]['selectorOverlapCount']);
        $t->same(['rIdPackageA', 'rIdPackageB'], $transforms[0]['relationshipIds']);
        $t->same(2, $transforms[0]['relationshipCount']);
        $t->same(true, $transforms[0]['selectorValid']);
        $t->same(true, $transforms[0]['relationshipTargetsValid']);
        $t->same(true, $transforms[0]['valid']);
        $t->same([], $transforms[0]['issues']);
        $t->same(1, substr_count((string) $transforms[0]['relationshipXml'], 'Id="rIdPackageA"'));
        $t->same(1, substr_count((string) $transforms[0]['relationshipXml'], 'Id="rIdPackageB"'));
    },
    'rejects singular OPC relationship group reference aliases in signature transforms' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/source-workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/_xmlsignatures/sig-singular-group-reference.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-workbook.xlsx"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
          <mdssi:RelationshipGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink"/>
          selector text
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/source-workbook.xlsx', 'data' => 'PK' . "\x03\x04"],
            ['name' => '_xmlsignatures/sig-singular-group-reference.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-singular-group-reference.xml');

        $t->same(1, count($transforms));
        $t->same('/word/document.xml', $transforms[0]['source']);
        $t->same(['rIdHero'], $transforms[0]['sourceIds']);
        $t->same([OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE], $transforms[0]['sourceTypes']);
        $t->same(4, $transforms[0]['selectorChildCount']);
        $t->same(1, $transforms[0]['selectorRelationshipReferenceCount']);
        $t->same(1, $transforms[0]['selectorRelationshipGroupReferenceCount']);
        $t->same(1, $transforms[0]['selectorUnsupportedChildCount']);
        $t->same(1, $transforms[0]['selectorUnsupportedContentCount']);
        $t->same(['rIdEmbeddedWorkbook', 'rIdHero'], $transforms[0]['relationshipIds']);
        $t->same(2, $transforms[0]['relationshipCount']);
        $t->same(true, $transforms[0]['selectorValid']);
        $t->same(false, $transforms[0]['valid']);
        $t->same([
            'unsupported-relationship-transform-child',
            'unsupported-relationship-transform-content',
        ], $transforms[0]['issues']);
        $t->contains('Id="rIdEmbeddedWorkbook"', $transforms[0]['relationshipXml']);
        $t->contains('Id="rIdHero"', $transforms[0]['relationshipXml']);
        $t->same(false, str_contains((string) $transforms[0]['relationshipXml'], 'rIdReviewer'));
    },
    'preflights XML signature relationship transform declarations from signature parts' => static function (TestRunner $t): void {
        $signatureContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/embeddings/source-workbook.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/_xmlsignatures/sig1.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
  <Override PartName="/_xmlsignatures/sig-invalid.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $signaturePackageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $signatureDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewer" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/wp-admin/post.php?post=42&amp;action=edit" TargetMode="External"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdEmbeddedWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/source-workbook.xlsx"/>
  <Relationship Id="rIdDraft" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="draft.xml"/>
</Relationships>
XML;

        $validSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
          <mdssi:RelationshipsGroupReference SourceType="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/_rels/.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdDocument"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $invalidSignatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature" xmlns:bad="urn:bad">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdMissing"/>
          <mdssi:RelationshipsGroupReference/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/document.xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <bad:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $signatureContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $signaturePackageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $signatureDocumentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/source-workbook.xlsx', 'data' => 'PK' . "\x03\x04"],
            ['name' => '_xmlsignatures/sig1.xml', 'data' => $validSignatureXml],
            ['name' => '_xmlsignatures/sig-invalid.xml', 'data' => $invalidSignatureXml],
        ]));

        $validTransforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig1.xml');
        $t->same(2, count($validTransforms));
        $t->same('/_xmlsignatures/sig1.xml', $validTransforms[0]['signaturePart']);
        $t->same(0, $validTransforms[0]['referenceIndex']);
        $t->same('/word/_rels/document.xml.rels', $validTransforms[0]['relationshipPartName']);
        $t->same('/word/document.xml', $validTransforms[0]['source']);
        $t->same(['rIdHero'], $validTransforms[0]['sourceIds']);
        $t->same([OpcRelationshipGraph::EMBEDDED_PACKAGE_RELATIONSHIP_TYPE], $validTransforms[0]['sourceTypes']);
        $t->same(2, $validTransforms[0]['selectorChildCount']);
        $t->same(1, $validTransforms[0]['selectorRelationshipReferenceCount']);
        $t->same(1, $validTransforms[0]['selectorRelationshipGroupReferenceCount']);
        $t->same(0, $validTransforms[0]['selectorUnsupportedChildCount']);
        $t->same(0, $validTransforms[0]['selectorUnsupportedContentCount']);
        $t->same('http://www.w3.org/TR/2001/REC-xml-c14n-20010315', $validTransforms[0]['followingCanonicalizationAlgorithm']);
        $t->same([
            'algorithm' => 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'profile' => 'inclusive-c14n-1.0',
            'version' => '1.0',
            'exclusive' => false,
            'withComments' => false,
        ], $validTransforms[0]['followingCanonicalization']);
        $t->same(true, $validTransforms[0]['followedByCanonicalization']);
        $t->same(['rIdEmbeddedWorkbook', 'rIdHero'], $validTransforms[0]['relationshipIds']);
        $t->same(2, $validTransforms[0]['relationshipCount']);
        $t->same(true, $validTransforms[0]['selectorValid']);
        $t->same(true, $validTransforms[0]['relationshipTargetsValid']);
        $t->same(true, $validTransforms[0]['valid']);
        $t->same([], $validTransforms[0]['issues']);
        $t->contains('Id="rIdEmbeddedWorkbook"', $validTransforms[0]['relationshipXml']);
        $t->same(false, str_contains((string) $validTransforms[0]['relationshipXml'], 'rIdDraft'));

        $t->same('/_rels/.rels', $validTransforms[1]['relationshipPartName']);
        $t->same('/', $validTransforms[1]['source']);
        $t->same(['rIdDocument'], $validTransforms[1]['sourceIds']);
        $t->same([], $validTransforms[1]['sourceTypes']);
        $t->same(1, $validTransforms[1]['selectorChildCount']);
        $t->same(1, $validTransforms[1]['selectorRelationshipReferenceCount']);
        $t->same(0, $validTransforms[1]['selectorRelationshipGroupReferenceCount']);
        $t->same(0, $validTransforms[1]['selectorUnsupportedChildCount']);
        $t->same(0, $validTransforms[1]['selectorUnsupportedContentCount']);
        $t->same('http://www.w3.org/2001/10/xml-exc-c14n#', $validTransforms[1]['followingCanonicalizationAlgorithm']);
        $t->same([
            'algorithm' => 'http://www.w3.org/2001/10/xml-exc-c14n#',
            'profile' => 'exclusive-c14n-1.0',
            'version' => '1.0',
            'exclusive' => true,
            'withComments' => false,
        ], $validTransforms[1]['followingCanonicalization']);
        $t->same(true, $validTransforms[1]['valid']);
        $t->same(['rIdDocument'], $validTransforms[1]['relationshipIds']);
        $t->contains('Target="word/document.xml"', $validTransforms[1]['relationshipXml']);

        $invalidTransforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-invalid.xml');
        $t->same(3, count($invalidTransforms));
        $t->same(false, $invalidTransforms[0]['followedByCanonicalization']);
        $t->same('http://www.w3.org/2000/09/xmldsig#enveloped-signature', $invalidTransforms[0]['followingCanonicalizationAlgorithm']);
        $t->same(null, $invalidTransforms[0]['followingCanonicalization']);
        $t->same(false, $invalidTransforms[0]['valid']);
        $t->same([
            'relationship-transform-not-followed-by-canonicalization',
            'relationship-transform-with-enveloped-signature-transform',
            'multiple-relationship-transforms-for-part',
        ], $invalidTransforms[0]['issues']);
        $t->same(false, $invalidTransforms[1]['valid']);
        $t->same(['rIdMissing'], $invalidTransforms[1]['sourceIds']);
        $t->same(2, $invalidTransforms[1]['selectorChildCount']);
        $t->same(1, $invalidTransforms[1]['selectorRelationshipReferenceCount']);
        $t->same(1, $invalidTransforms[1]['selectorRelationshipGroupReferenceCount']);
        $t->same(0, $invalidTransforms[1]['selectorUnsupportedChildCount']);
        $t->same(0, $invalidTransforms[1]['selectorUnsupportedContentCount']);
        $t->same([
            'missing-source-type',
            'unmatched-source-id',
            'multiple-relationship-transforms-for-part',
        ], $invalidTransforms[1]['issues']);
        $t->same('/word/document.xml', $invalidTransforms[2]['relationshipPartName']);
        $t->same(null, $invalidTransforms[2]['source']);
        $t->same(false, $invalidTransforms[2]['valid']);
        $t->same([
            'reference-not-relationship-part',
            'unsupported-relationship-transform-child',
            'empty-relationship-selector',
        ], $invalidTransforms[2]['issues']);

        $t->throws(\RuntimeException::class, static fn (): array => $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/missing.xml'));
    },
    'flags enveloped signature transforms on OPC relationship part references' => static function (TestRunner $t): void {
        $digest = base64_encode(str_repeat('d', 32));
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-enveloped-transform.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $signatureXml = <<<XML
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
        <ds:Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
      </ds:Transforms>
      <ds:DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
      <ds:DigestValue>{$digest}</ds:DigestValue>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-enveloped-transform.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-enveloped-transform.xml');
        $t->same(1, count($transforms));
        $t->same('/word/_rels/document.xml.rels', $transforms[0]['relationshipPartName']);
        $t->same('/word/document.xml', $transforms[0]['source']);
        $t->same(['rIdHero'], $transforms[0]['relationshipIds']);
        $t->same(true, $transforms[0]['followedByCanonicalization']);
        $t->same(false, $transforms[0]['valid']);
        $t->same(['relationship-transform-with-enveloped-signature-transform'], $transforms[0]['issues']);
        $t->contains('Id="rIdHero"', $transforms[0]['relationshipXml']);

        $references = $graph->preflightDigitalSignatureSignedInfoReferences('/_xmlsignatures/sig-enveloped-transform.xml');
        $t->same(1, count($references));
        $t->same([
            'http://schemas.openxmlformats.org/package/2006/RelationshipTransform',
            'http://www.w3.org/TR/2001/REC-xml-c14n-20010315',
            'http://www.w3.org/2000/09/xmldsig#enveloped-signature',
        ], $references[0]['transformAlgorithms']);
        $t->same([0], $references[0]['relationshipTransformIndexes']);
        $t->same([1], $references[0]['canonicalizationTransformIndexes']);
        $t->same(1, $references[0]['relationshipTransformCount']);
        $t->same(1, $references[0]['canonicalizationTransformCount']);
        $t->same(true, $references[0]['relationshipTransformFollowedByCanonicalization']);
        $t->same(32, $references[0]['digestValueDecodedBytes']);
        $t->same(false, $references[0]['valid']);
        $t->same(['signed-info-relationship-transform-with-enveloped-signature-transform'], $references[0]['issues']);
    },
    'preflights OPC signature relationship transform reference content type queries' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/customXml/item1.xml" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/sig-content-type.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $footnotesRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdFootnoteImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/footnote.png"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/comment.png"/>
</Relationships>
XML;

        $customXmlRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCustomImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../word/media/custom.png"/>
</Relationships>
XML;

        $settingsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSettingsImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/settings.png"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/_rels/.rels?ContentType=application%2Fvnd.openxmlformats-package.relationships%2Bxml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdDocument"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/footnotes.xml.rels?ContentType=application/xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdFootnoteImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/comments.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml&amp;ContentType=application/xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdCommentImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/customXml/_rels/item1.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships%ZZxml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdCustomImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/settings.xml.rels?ContentType=application/xml%20bad">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdSettingsImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $footnotesRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/settings.xml', 'data' => '<w:settings xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/settings.xml.rels', 'data' => $settingsRelationshipsXml],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'customXml/_rels/item1.xml.rels', 'data' => $customXmlRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/media/footnote.png', 'data' => 'PNG'],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
            ['name' => 'word/media/custom.png', 'data' => 'PNG'],
            ['name' => 'word/media/settings.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-content-type.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-content-type.xml');

        $t->same(6, count($transforms));
        $t->same('/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml', $transforms[0]['referenceUri']);
        $t->same('/word/_rels/document.xml.rels', $transforms[0]['relationshipPartName']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[0]['referenceTargetContentType']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[0]['referenceContentType']);
        $t->same(true, $transforms[0]['referenceContentTypeMatches']);
        $t->same(true, $transforms[0]['valid']);
        $t->same([], $transforms[0]['issues']);
        $t->same(['rIdHero'], $transforms[0]['relationshipIds']);

        $t->same('/_rels/.rels', $transforms[1]['relationshipPartName']);
        $t->same('/', $transforms[1]['source']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[1]['referenceContentType']);
        $t->same(true, $transforms[1]['referenceContentTypeMatches']);
        $t->same(true, $transforms[1]['valid']);
        $t->same(['rIdDocument'], $transforms[1]['relationshipIds']);

        $t->same('application/xml', $transforms[2]['referenceContentType']);
        $t->same(false, $transforms[2]['referenceContentTypeMatches']);
        $t->same(false, $transforms[2]['valid']);
        $t->same(['reference-content-type-mismatch'], $transforms[2]['issues']);
        $t->same('/word/_rels/footnotes.xml.rels', $transforms[2]['relationshipPartName']);
        $t->same(['rIdFootnoteImage'], $transforms[2]['relationshipIds']);

        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[3]['referenceContentType']);
        $t->same(true, $transforms[3]['referenceContentTypeMatches']);
        $t->same(false, $transforms[3]['valid']);
        $t->same(['duplicate-reference-content-type-query'], $transforms[3]['issues']);
        $t->same('/word/_rels/comments.xml.rels', $transforms[3]['relationshipPartName']);
        $t->same(['rIdCommentImage'], $transforms[3]['relationshipIds']);

        $t->same(null, $transforms[4]['referenceContentType']);
        $t->same(null, $transforms[4]['referenceContentTypeMatches']);
        $t->same(false, $transforms[4]['valid']);
        $t->same([
            'invalid-reference-uri',
            'relationship-transform-reference-malformed-percent-escape',
            'invalid-reference-content-type-query',
        ], $transforms[4]['issues']);
        $t->same(null, $transforms[4]['relationshipPartName']);
        $t->same(null, $transforms[4]['source']);
        $t->same([], $transforms[4]['relationshipIds']);
        $t->contains('malformed percent escape', $transforms[4]['parseError'] ?? '');

        $t->same('/word/_rels/settings.xml.rels?ContentType=application/xml%20bad', $transforms[5]['referenceUri']);
        $t->same('/word/_rels/settings.xml.rels', $transforms[5]['relationshipPartName']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[5]['referenceTargetContentType']);
        $t->same('application/xml bad', $transforms[5]['referenceContentType']);
        $t->same(false, $transforms[5]['referenceContentTypeMatches']);
        $t->same(false, $transforms[5]['valid']);
        $t->same([
            'invalid-reference-content-type-query',
            'reference-content-type-mismatch',
        ], $transforms[5]['issues']);
        $t->same(['rIdSettingsImage'], $transforms[5]['relationshipIds']);
    },
    'preflights OPC signature relationship transform referenced relationship part content types' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/_rels/.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/_rels/document.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
  <Override PartName="/_xmlsignatures/sig-reference-content-type-guard.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/comment.png"/>
</Relationships>
XML;

        $footnotesRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdFootnoteImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/footnote.png"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/comments.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdCommentImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/footnotes.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdFootnoteImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $footnotesRelationshipsXml],
            ['name' => '_xmlsignatures/sig-reference-content-type-guard.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-reference-content-type-guard.xml');

        $t->same(2, count($transforms));
        $t->same('/word/_rels/comments.xml.rels', $transforms[0]['relationshipPartName']);
        $t->same(true, $transforms[0]['referenceRelationshipPartExists']);
        $t->same('application/xml', $transforms[0]['referenceTargetContentType']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[0]['referenceContentType']);
        $t->same(false, $transforms[0]['referenceContentTypeMatches']);
        $t->same('/word/comments.xml', $transforms[0]['source']);
        $t->same(['rIdCommentImage'], $transforms[0]['sourceIds']);
        $t->same([], $transforms[0]['relationshipIds']);
        $t->same(0, $transforms[0]['relationshipCount']);
        $t->same(false, $transforms[0]['selectorValid']);
        $t->same(true, $transforms[0]['relationshipTargetsValid']);
        $t->same(false, $transforms[0]['valid']);
        $t->same([
            'reference-relationship-content-type-invalid',
            'reference-content-type-mismatch',
            'relationship-source-not-loaded',
            'unmatched-source-id',
        ], $transforms[0]['issues']);
        $t->same(null, $transforms[0]['relationshipXml']);

        $t->same('/word/_rels/footnotes.xml.rels', $transforms[1]['relationshipPartName']);
        $t->same(true, $transforms[1]['referenceRelationshipPartExists']);
        $t->same(null, $transforms[1]['referenceTargetContentType']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[1]['referenceContentType']);
        $t->same(false, $transforms[1]['referenceContentTypeMatches']);
        $t->same('/word/footnotes.xml', $transforms[1]['source']);
        $t->same(['rIdFootnoteImage'], $transforms[1]['sourceIds']);
        $t->same([], $transforms[1]['relationshipIds']);
        $t->same(0, $transforms[1]['relationshipCount']);
        $t->same(false, $transforms[1]['selectorValid']);
        $t->same(true, $transforms[1]['relationshipTargetsValid']);
        $t->same(false, $transforms[1]['valid']);
        $t->same([
            'reference-relationship-content-type-missing',
            'reference-content-type-mismatch',
            'relationship-source-not-loaded',
            'unmatched-source-id',
        ], $transforms[1]['issues']);
        $t->same(null, $transforms[1]['relationshipXml']);
    },
    'rejects dot-segment OPC relationship part name references' => static function (TestRunner $t): void {
        $t->same('/word/document.xml', OpcRelationships::sourcePartNameForRelationshipPart('/word/_rels/document.xml.rels'));
        $t->true(OpcRelationships::isRelationshipPartName('/word/_rels/document.xml.rels'));

        foreach ([
            '/word/./_rels/document.xml.rels',
            '/word/_rels/./document.xml.rels',
            '/word/_rels/sub/../document.xml.rels',
            '/word//_rels/document.xml.rels',
            '/word/_rels/document.xml.rels/',
        ] as $relationshipPartName) {
            $t->throws(\InvalidArgumentException::class, static fn (): string => OpcRelationships::sourcePartNameForRelationshipPart($relationshipPartName));
            $t->same(false, OpcRelationships::isRelationshipPartName($relationshipPartName));
        }

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-dot-segments.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/./_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word//_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document.xml.rels/">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-dot-segments.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-dot-segments.xml');

        $t->same(4, count($transforms));
        $t->same('/word/_rels/document.xml.rels', $transforms[0]['relationshipPartName']);
        $t->same('/word/document.xml', $transforms[0]['source']);
        $t->same(['rIdHero'], $transforms[0]['relationshipIds']);
        $t->same(1, $transforms[0]['relationshipCount']);
        $t->same(true, $transforms[0]['valid']);
        $t->same([], $transforms[0]['issues']);

        foreach ([1, 2, 3] as $index) {
            $t->same(null, $transforms[$index]['relationshipPartName']);
            $t->same(null, $transforms[$index]['referenceRelationshipPartExists']);
            $t->same(null, $transforms[$index]['source']);
            $t->same(['rIdHero'], $transforms[$index]['sourceIds']);
            $t->same([], $transforms[$index]['relationshipIds']);
            $t->same(0, $transforms[$index]['relationshipCount']);
            $t->same(null, $transforms[$index]['selectorValid']);
            $t->same(null, $transforms[$index]['relationshipTargetsValid']);
            $t->same(false, $transforms[$index]['valid']);
            $t->same(['relationship-transform-reference-invalid-part-name'], $transforms[$index]['issues']);
            $t->same(null, $transforms[$index]['relationshipXml']);
            $t->contains('must not contain empty or dot path segments', $transforms[$index]['parseError'] ?? '');
        }
    },
    'classifies unsafe OPC signature relationship transform reference URI paths' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-unsafe-reference.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $referenceUris = [
            '/word/_rels/document%ZZ.xml.rels' => [
                'relationship-transform-reference-malformed-percent-escape',
                'malformed percent escape',
            ],
            '/word/_rels/document%2Fhidden.xml.rels' => [
                'relationship-transform-reference-unsafe-percent-encoded-path-byte',
                'unsafe percent-encoded path bytes',
            ],
            '/word/_rels/document%5Chidden.xml.rels' => [
                'relationship-transform-reference-unsafe-percent-encoded-path-byte',
                'unsafe percent-encoded path bytes',
            ],
            '/word/_rels/document%00hidden.xml.rels' => [
                'relationship-transform-reference-unsafe-percent-encoded-path-byte',
                'unsafe percent-encoded path bytes',
            ],
            '/word/_rels/%2E%2E/document.xml.rels' => [
                'relationship-transform-reference-unsafe-percent-encoded-dot-segment',
                'unsafe percent-encoded dot segment',
            ],
            '/word/_rels/raw space.xml.rels' => [
                'relationship-transform-reference-invalid-uri-byte',
                'invalid URI bytes',
            ],
            '../word/_rels/trailing./document.xml.rels' => [
                'relationship-transform-reference-trailing-dot-segment',
                'segments must not end with a dot',
            ],
            '../../evil/_rels/document.xml.rels' => [
                'relationship-transform-reference-package-root-traversal',
                'traverse above the package root',
            ],
        ];

        $referencesXml = '';
        foreach (array_keys($referenceUris) as $referenceUri) {
            $referencesXml .= <<<XML
    <ds:Reference URI="$referenceUri">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
XML;
        }

        $signatureXml = <<<XML
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
$referencesXml
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-unsafe-reference.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-unsafe-reference.xml');

        $t->same(count($referenceUris), count($transforms));
        foreach (array_values($referenceUris) as $index => [$specificIssue, $parseErrorNeedle]) {
            $t->same(null, $transforms[$index]['relationshipPartName']);
            $t->same(null, $transforms[$index]['referenceRelationshipPartExists']);
            $t->same(null, $transforms[$index]['source']);
            $t->same(['rIdHero'], $transforms[$index]['sourceIds']);
            $t->same([], $transforms[$index]['relationshipIds']);
            $t->same(0, $transforms[$index]['relationshipCount']);
            $t->same(null, $transforms[$index]['selectorValid']);
            $t->same(null, $transforms[$index]['relationshipTargetsValid']);
            $t->same(false, $transforms[$index]['valid']);
            $t->same(['invalid-reference-uri', $specificIssue], $transforms[$index]['issues']);
            $t->same(null, $transforms[$index]['relationshipXml']);
            $t->contains($parseErrorNeedle, $transforms[$index]['parseError'] ?? '');
        }
    },
    'preflights missing OPC signature relationship transform reference parts' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/_xmlsignatures/sig-missing-rels.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/comments.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdCommentImage"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => '_xmlsignatures/sig-missing-rels.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-missing-rels.xml');

        $t->same(1, count($transforms));
        $t->same('/word/_rels/comments.xml.rels', $transforms[0]['relationshipPartName']);
        $t->same(false, $transforms[0]['referenceRelationshipPartExists']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[0]['referenceTargetContentType']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[0]['referenceContentType']);
        $t->same(true, $transforms[0]['referenceContentTypeMatches']);
        $t->same('/word/comments.xml', $transforms[0]['source']);
        $t->same(['rIdCommentImage'], $transforms[0]['sourceIds']);
        $t->same([], $transforms[0]['relationshipIds']);
        $t->same(0, $transforms[0]['relationshipCount']);
        $t->same(false, $transforms[0]['selectorValid']);
        $t->same(true, $transforms[0]['relationshipTargetsValid']);
        $t->same(false, $transforms[0]['valid']);
        $t->same([
            'reference-relationship-part-missing-in-package',
            'relationship-source-not-loaded',
            'unmatched-source-id',
        ], $transforms[0]['issues']);
        $t->same(null, $transforms[0]['relationshipXml']);
    },
    'flags OPC signature relationship transform references with fragments' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-fragment.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="/word/_rels/document.xml.rels?ContentType=application/vnd.openxmlformats-package.relationships+xml#fragment">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-fragment.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-fragment.xml');

        $t->same(1, count($transforms));
        $t->same('/word/_rels/document.xml.rels', $transforms[0]['relationshipPartName']);
        $t->same('/word/document.xml', $transforms[0]['source']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $transforms[0]['referenceContentType']);
        $t->same(['rIdHero'], $transforms[0]['relationshipIds']);
        $t->same(1, $transforms[0]['relationshipCount']);
        $t->same(true, $transforms[0]['selectorValid']);
        $t->same(true, $transforms[0]['relationshipTargetsValid']);
        $t->same(false, $transforms[0]['valid']);
        $t->same(['relationship-transform-reference-has-fragment'], $transforms[0]['issues']);
        $t->contains('Id="rIdHero"', $transforms[0]['relationshipXml']);
    },
    'preflights unsupported OPC signature relationship transform reference URI kinds' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-reference-uri-kinds.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:mdssi="http://schemas.openxmlformats.org/package/2006/digital-signature">
  <ds:SignedInfo>
    <ds:Reference URI="#local-relationship-transform">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="https://example.test/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="//example.test/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform">
          <mdssi:RelationshipReference SourceId="rIdHero"/>
        </ds:Transform>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-reference-uri-kinds.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-reference-uri-kinds.xml');

        $t->same(3, count($transforms));
        $t->same('#local-relationship-transform', $transforms[0]['referenceUri']);
        $t->same(null, $transforms[0]['relationshipPartName']);
        $t->same(null, $transforms[0]['referenceRelationshipPartExists']);
        $t->same(null, $transforms[0]['source']);
        $t->same(['rIdHero'], $transforms[0]['sourceIds']);
        $t->same([], $transforms[0]['sourceTypes']);
        $t->same('http://www.w3.org/TR/2001/REC-xml-c14n-20010315', $transforms[0]['followingCanonicalizationAlgorithm']);
        $t->same(true, $transforms[0]['followedByCanonicalization']);
        $t->same([], $transforms[0]['relationshipIds']);
        $t->same(0, $transforms[0]['relationshipCount']);
        $t->same(null, $transforms[0]['selectorValid']);
        $t->same(null, $transforms[0]['relationshipTargetsValid']);
        $t->same(false, $transforms[0]['valid']);
        $t->same([
            'relationship-transform-reference-same-document',
            'relationship-transform-reference-has-fragment',
        ], $transforms[0]['issues']);
        $t->same(null, $transforms[0]['parseError']);
        $t->same(null, $transforms[0]['relationshipXml']);

        $t->same('https://example.test/word/_rels/document.xml.rels', $transforms[1]['referenceUri']);
        $t->same(null, $transforms[1]['relationshipPartName']);
        $t->same(null, $transforms[1]['referenceRelationshipPartExists']);
        $t->same(null, $transforms[1]['referenceTargetContentType']);
        $t->same(null, $transforms[1]['source']);
        $t->same(['rIdHero'], $transforms[1]['sourceIds']);
        $t->same(true, $transforms[1]['followedByCanonicalization']);
        $t->same([], $transforms[1]['relationshipIds']);
        $t->same(0, $transforms[1]['relationshipCount']);
        $t->same(null, $transforms[1]['selectorValid']);
        $t->same(false, $transforms[1]['valid']);
        $t->same(['relationship-transform-reference-external-uri'], $transforms[1]['issues']);
        $t->same(null, $transforms[1]['parseError']);
        $t->same(null, $transforms[1]['relationshipXml']);

        $t->same('//example.test/word/_rels/document.xml.rels', $transforms[2]['referenceUri']);
        $t->same(null, $transforms[2]['relationshipPartName']);
        $t->same(null, $transforms[2]['referenceRelationshipPartExists']);
        $t->same(null, $transforms[2]['source']);
        $t->same(['rIdHero'], $transforms[2]['sourceIds']);
        $t->same(true, $transforms[2]['followedByCanonicalization']);
        $t->same([], $transforms[2]['relationshipIds']);
        $t->same(0, $transforms[2]['relationshipCount']);
        $t->same(null, $transforms[2]['selectorValid']);
        $t->same(false, $transforms[2]['valid']);
        $t->same(['relationship-transform-reference-external-uri'], $transforms[2]['issues']);
        $t->same(null, $transforms[2]['parseError']);
        $t->same(null, $transforms[2]['relationshipXml']);
    },
    'flags empty OPC signature relationship transform selectors before reference resolution' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/_xmlsignatures/sig-empty-selector.xml" ContentType="application/vnd.openxmlformats-package.digital-signature-xmlsignature+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $signatureXml = <<<'XML'
<ds:Signature xmlns:ds="http://www.w3.org/2000/09/xmldsig#">
  <ds:SignedInfo>
    <ds:Reference URI="#local-empty-selector">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform"/>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="https://example.test/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform"/>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
    <ds:Reference URI="/word/_rels/document.xml.rels">
      <ds:Transforms>
        <ds:Transform Algorithm="http://schemas.openxmlformats.org/package/2006/RelationshipTransform"/>
        <ds:Transform Algorithm="http://www.w3.org/TR/2001/REC-xml-c14n-20010315"/>
      </ds:Transforms>
    </ds:Reference>
  </ds:SignedInfo>
</ds:Signature>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => '_xmlsignatures/sig-empty-selector.xml', 'data' => $signatureXml],
        ]));

        $transforms = $graph->preflightSignatureRelationshipTransforms('/_xmlsignatures/sig-empty-selector.xml');

        $t->same(3, count($transforms));

        $t->same('#local-empty-selector', $transforms[0]['referenceUri']);
        $t->same(null, $transforms[0]['relationshipPartName']);
        $t->same(null, $transforms[0]['source']);
        $t->same([], $transforms[0]['sourceIds']);
        $t->same([], $transforms[0]['sourceTypes']);
        $t->same(null, $transforms[0]['selectorValid']);
        $t->same(false, $transforms[0]['valid']);
        $t->same([
            'relationship-transform-reference-same-document',
            'relationship-transform-reference-has-fragment',
            'empty-relationship-selector',
        ], $transforms[0]['issues']);

        $t->same('https://example.test/word/_rels/document.xml.rels', $transforms[1]['referenceUri']);
        $t->same(null, $transforms[1]['relationshipPartName']);
        $t->same(null, $transforms[1]['source']);
        $t->same([], $transforms[1]['sourceIds']);
        $t->same([], $transforms[1]['sourceTypes']);
        $t->same(null, $transforms[1]['selectorValid']);
        $t->same(false, $transforms[1]['valid']);
        $t->same([
            'relationship-transform-reference-external-uri',
            'empty-relationship-selector',
        ], $transforms[1]['issues']);

        $t->same('/word/_rels/document.xml.rels', $transforms[2]['relationshipPartName']);
        $t->same('/word/document.xml', $transforms[2]['source']);
        $t->same([], $transforms[2]['sourceIds']);
        $t->same([], $transforms[2]['sourceTypes']);
        $t->same(false, $transforms[2]['selectorValid']);
        $t->same(0, $transforms[2]['relationshipCount']);
        $t->same(false, $transforms[2]['valid']);
        $t->same(['empty-relationship-selector'], $transforms[2]['issues']);
        $t->contains('<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">', $transforms[2]['relationshipXml']);
        $t->same(false, str_contains((string) $transforms[2]['relationshipXml'], 'Relationship Id='));
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
    'rejects reserved OPC relationship content type on non relationship parts' => static function (TestRunner $t): void {
        $relationshipContentType = 'application/vnd.openxmlformats-package.relationships+xml';
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/media/override-source.bin" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/media/missing.bin" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDefaultRels" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/default-source.rels"/>
  <Relationship Id="rIdOverrideRels" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/override-source.bin"/>
  <Relationship Id="rIdMissingOverride" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.bin"/>
  <Relationship Id="rIdGoodImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/good.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/default-source.rels', 'data' => 'not relationship xml'],
            ['name' => 'word/media/override-source.bin', 'data' => 'not relationship xml'],
            ['name' => 'word/media/good.png', 'data' => 'PNG'],
        ]));

        $parts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $parts[$part['partName']] = $part;
        }

        $overrides = [];
        foreach ($graph->preflightContentTypeOverrides() as $override) {
            $overrides[$override['partName']] = $override;
        }

        $targets = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $targets[$target['id']] = $target;
        }

        $inventory = [];
        foreach ($graph->contentTypeInventory() as $entry) {
            $inventory[$entry['contentType']] = $entry;
        }

        $references = [];
        foreach ($graph->packagePartReferenceInventory('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $reference) {
            $references[$reference['partName']] = $reference;
        }

        $t->same(false, $parts['/word/media/default-source.rels']['relationshipPart']);
        $t->same($relationshipContentType, $parts['/word/media/default-source.rels']['contentType']);
        $t->same(['relationship-content-type-on-non-relationship-part'], $parts['/word/media/default-source.rels']['issues']);
        $t->same(false, $parts['/word/media/default-source.rels']['valid']);
        $t->same(false, $parts['/word/media/override-source.bin']['relationshipPart']);
        $t->same($relationshipContentType, $parts['/word/media/override-source.bin']['contentType']);
        $t->same(['relationship-content-type-on-non-relationship-part'], $parts['/word/media/override-source.bin']['issues']);
        $t->same(false, $parts['/word/media/override-source.bin']['valid']);
        $t->same(true, $parts['/word/media/good.png']['valid']);
        $t->same([], $parts['/word/media/good.png']['issues']);

        $t->same(true, $overrides['/word/media/override-source.bin']['exists']);
        $t->same(false, $overrides['/word/media/override-source.bin']['relationshipPart']);
        $t->same(false, $overrides['/word/media/override-source.bin']['valid']);
        $t->same(['relationship-content-type-on-non-relationship-part'], $overrides['/word/media/override-source.bin']['issues']);
        $t->same(false, $overrides['/word/media/missing.bin']['exists']);
        $t->same(false, $overrides['/word/media/missing.bin']['valid']);
        $t->same(['override-target-missing-part', 'relationship-content-type-on-non-relationship-part'], $overrides['/word/media/missing.bin']['issues']);

        $t->same('/word/media/default-source.rels', $targets['rIdDefaultRels']['target']);
        $t->same($relationshipContentType, $targets['rIdDefaultRels']['contentType']);
        $t->same(true, $targets['rIdDefaultRels']['exists']);
        $t->same(false, $targets['rIdDefaultRels']['relationshipPartTarget']);
        $t->same(false, $targets['rIdDefaultRels']['valid']);
        $t->same(['relationship-content-type-on-non-relationship-part'], $targets['rIdDefaultRels']['issues']);
        $t->same('/word/media/override-source.bin', $targets['rIdOverrideRels']['target']);
        $t->same($relationshipContentType, $targets['rIdOverrideRels']['contentType']);
        $t->same(['relationship-content-type-on-non-relationship-part'], $targets['rIdOverrideRels']['issues']);
        $t->same(false, $targets['rIdOverrideRels']['valid']);
        $t->same('/word/media/missing.bin', $targets['rIdMissingOverride']['target']);
        $t->same(false, $targets['rIdMissingOverride']['exists']);
        $t->same(['missing-in-package', 'relationship-content-type-on-non-relationship-part'], $targets['rIdMissingOverride']['issues']);
        $t->same(false, $targets['rIdMissingOverride']['valid']);
        $t->same('image/png', $targets['rIdGoodImage']['contentType']);
        $t->same([], $targets['rIdGoodImage']['issues']);
        $t->same(true, $targets['rIdGoodImage']['valid']);

        $consistency = $graph->preflightPackageConsistency();
        $t->same(false, $consistency['valid']);
        $t->same(false, $consistency['packagePartsValid']);
        $t->same(false, $consistency['contentTypeOverridesValid']);
        $t->same(false, $consistency['relationshipTargetsValid']);

        $t->same(4, $inventory[$relationshipContentType]['packagePartCount']);
        $t->same(2, $inventory[$relationshipContentType]['relationshipPartCount']);
        $t->same(2, $inventory[$relationshipContentType]['invalidPackagePartCount']);
        $t->same(2, $inventory[$relationshipContentType]['overrideCount']);
        $t->same(1, $inventory[$relationshipContentType]['missingOverrideCount']);
        $t->same(3, $inventory[$relationshipContentType]['relationshipTargetReferenceCount']);
        $t->same(['/word/media/missing.bin'], $inventory[$relationshipContentType]['missingOverrideParts']);
        $t->same(['missing-in-package', 'override-target-missing-part', 'relationship-content-type-on-non-relationship-part'], $inventory[$relationshipContentType]['issues']);

        $t->same(false, $references['/word/media/default-source.rels']['valid']);
        $t->same(1, $references['/word/media/default-source.rels']['directReferenceCount']);
        $t->same(1, $references['/word/media/default-source.rels']['reachableReferenceCount']);
        $t->same(['relationship-content-type-on-non-relationship-part'], $references['/word/media/default-source.rels']['issues']);
        $t->same(['relationship-content-type-on-non-relationship-part'], $references['/word/media/default-source.rels']['directReferences'][0]['issues']);
        $t->same(false, $references['/word/media/missing.bin']['exists']);
        $t->same(false, $references['/word/media/missing.bin']['valid']);
        $t->same(['missing-in-package', 'relationship-content-type-on-non-relationship-part'], $references['/word/media/missing.bin']['issues']);
        $t->same(['missing-in-package', 'relationship-content-type-on-non-relationship-part'], $references['/word/media/missing.bin']['directReferences'][0]['issues']);
    },
    'rejects non relationship package parts under reserved relationship directories' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/_rels/review-metadata.xml" ContentType="application/xml"/>
  <Override PartName="/word/_rels/missing-review.xml" ContentType="application/xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReserved" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="_rels/review-metadata.xml"/>
  <Relationship Id="rIdMissingReserved" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="_rels/missing-review.xml"/>
  <Relationship Id="rIdNormal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="review/metadata.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/_rels/review-metadata.xml', 'data' => '<metadata/>'],
            ['name' => 'word/review/metadata.xml', 'data' => '<metadata/>'],
        ]));

        $parts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $parts[$part['partName']] = $part;
        }

        $overrides = [];
        foreach ($graph->preflightContentTypeOverrides() as $override) {
            $overrides[$override['partName']] = $override;
        }

        $targets = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $targets[$target['id']] = $target;
        }

        $references = [];
        foreach ($graph->packagePartReferenceInventory('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $reference) {
            $references[$reference['partName']] = $reference;
        }

        $t->same(false, $parts['/word/_rels/review-metadata.xml']['relationshipPart']);
        $t->same('application/xml', $parts['/word/_rels/review-metadata.xml']['contentType']);
        $t->same(['reserved-relationship-directory-part'], $parts['/word/_rels/review-metadata.xml']['issues']);
        $t->same(false, $parts['/word/_rels/review-metadata.xml']['valid']);
        $t->same(true, $parts['/word/_rels/document.xml.rels']['relationshipPart']);
        $t->same([], $parts['/word/_rels/document.xml.rels']['issues']);
        $t->same(true, $parts['/word/_rels/document.xml.rels']['valid']);
        $t->same(true, $parts['/word/review/metadata.xml']['valid']);
        $t->same([], $parts['/word/review/metadata.xml']['issues']);

        $t->same(true, $overrides['/word/_rels/review-metadata.xml']['exists']);
        $t->same(false, $overrides['/word/_rels/review-metadata.xml']['relationshipPart']);
        $t->same(false, $overrides['/word/_rels/review-metadata.xml']['valid']);
        $t->same(['reserved-relationship-directory-override'], $overrides['/word/_rels/review-metadata.xml']['issues']);
        $t->same(false, $overrides['/word/_rels/missing-review.xml']['exists']);
        $t->same(false, $overrides['/word/_rels/missing-review.xml']['valid']);
        $t->same(['override-target-missing-part', 'reserved-relationship-directory-override'], $overrides['/word/_rels/missing-review.xml']['issues']);

        $t->same('/word/_rels/review-metadata.xml', $targets['rIdReserved']['target']);
        $t->same(true, $targets['rIdReserved']['exists']);
        $t->same('application/xml', $targets['rIdReserved']['contentType']);
        $t->same(false, $targets['rIdReserved']['relationshipPartTarget']);
        $t->same(false, $targets['rIdReserved']['valid']);
        $t->same(['targets-reserved-relationship-directory-part'], $targets['rIdReserved']['issues']);
        $t->same('/word/_rels/missing-review.xml', $targets['rIdMissingReserved']['target']);
        $t->same(false, $targets['rIdMissingReserved']['exists']);
        $t->same(false, $targets['rIdMissingReserved']['valid']);
        $t->same(['missing-in-package', 'targets-reserved-relationship-directory-part'], $targets['rIdMissingReserved']['issues']);
        $t->same('/word/review/metadata.xml', $targets['rIdNormal']['target']);
        $t->same(true, $targets['rIdNormal']['valid']);
        $t->same([], $targets['rIdNormal']['issues']);

        $consistency = $graph->preflightPackageConsistency();
        $t->same(false, $consistency['valid']);
        $t->same(false, $consistency['packagePartsValid']);
        $t->same(false, $consistency['contentTypeOverridesValid']);
        $t->same(false, $consistency['relationshipTargetsValid']);

        $t->same(false, $references['/word/_rels/review-metadata.xml']['valid']);
        $t->same(1, $references['/word/_rels/review-metadata.xml']['directReferenceCount']);
        $t->same(1, $references['/word/_rels/review-metadata.xml']['reachableReferenceCount']);
        $t->same(['reserved-relationship-directory-part', 'targets-reserved-relationship-directory-part'], $references['/word/_rels/review-metadata.xml']['issues']);
        $t->same(['reserved-relationship-directory-part'], $references['/word/_rels/review-metadata.xml']['packagePartIssues']);
        $t->same(['targets-reserved-relationship-directory-part'], $references['/word/_rels/review-metadata.xml']['directReferences'][0]['issues']);
        $t->same(false, $references['/word/_rels/missing-review.xml']['exists']);
        $t->same(false, $references['/word/_rels/missing-review.xml']['valid']);
        $t->same(['missing-in-package', 'targets-reserved-relationship-directory-part'], $references['/word/_rels/missing-review.xml']['issues']);
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
    'summarizes package-wide OPC consistency issue buckets for importer gates' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/core-copy.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/word/_rels/draft.xml.rels" ContentType="application/xml"/>
  <Override PartName="/word/media/stale.png" ContentType="image/png"/>
  <Override PartName="/word/media/not-image.xml" ContentType="application/xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdCoreCopy" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core-copy.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdNotImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/not-image.xml"/>
</Relationships>
XML;

        $draftRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDraftImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/draft.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/_rels/draft.xml.rels', 'data' => $draftRelationshipsXml],
            ['name' => 'word/media/not-image.xml', 'data' => '<not-image/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'docProps/core-copy.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $summary = $graph->packageConsistencySummary();

        $t->same(false, $summary['valid']);
        $t->same(false, $summary['packagePartsValid']);
        $t->same(false, $summary['contentTypeOverridesValid']);
        $t->same(false, $summary['relationshipTargetsValid']);
        $t->same(false, $summary['relationshipTypePoliciesValid']);
        $t->same(7, $summary['packagePartCount']);
        $t->same(1, $summary['invalidPackagePartCount']);
        $t->same(6, $summary['contentTypeOverrideCount']);
        $t->same(2, $summary['invalidContentTypeOverrideCount']);
        $t->same(5, $summary['relationshipTargetCount']);
        $t->same(1, $summary['invalidRelationshipTargetCount']);
        $t->same(2, $summary['relationshipTypePolicyCount']);
        $t->same(1, $summary['invalidRelationshipTypePolicyCount']);
        $t->same(['/word/_rels/draft.xml.rels'], $summary['invalidPackagePartNames']);
        $t->same(['/word/_rels/draft.xml.rels', '/word/media/stale.png'], $summary['invalidContentTypeOverrideParts']);
        $t->same(['/word/document.xml:rIdMissingImage'], $summary['invalidRelationshipTargetKeys']);
        $t->same([OpcRelationshipGraph::CORE_PROPERTIES_RELATIONSHIP_TYPE], $summary['invalidRelationshipTypePolicyTypes']);
        $t->same([
            'invalid-relationship-content-type' => 2,
            'missing-in-package' => 1,
            'multiple-core-properties-relationships' => 1,
            'orphan-relationship-part' => 1,
            'override-target-missing-part' => 1,
            'relationship-override-source-missing' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'invalid-relationship-content-type',
            'missing-in-package',
            'multiple-core-properties-relationships',
            'orphan-relationship-part',
            'override-target-missing-part',
            'relationship-override-source-missing',
        ], $summary['issues']);
        $t->same([
            'invalid-relationship-content-type' => 1,
            'orphan-relationship-part' => 1,
        ], $summary['sectionIssueCounts']['packageParts']);
        $t->same([
            'invalid-relationship-content-type' => 1,
            'override-target-missing-part' => 1,
            'relationship-override-source-missing' => 1,
        ], $summary['sectionIssueCounts']['contentTypeOverrides']);
        $t->same(['missing-in-package' => 1], $summary['sectionIssueCounts']['relationshipTargets']);
        $t->same(['multiple-core-properties-relationships' => 1], $summary['sectionIssueCounts']['relationshipTypePolicies']);
    },
    'summarizes package-wide OPC relationship type inventory for import review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdUnsafeLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>
  <Relationship Id="rIdMalformedType" Type="officeDocument/relationships/hyperlink" Target="https://example.test/source" TargetMode="External"/>
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
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $inventory = [];
        foreach ($graph->relationshipTypeInventory() as $type) {
            $inventory[$type['type']] = $type;
        }

        $imageType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image';
        $hyperlinkType = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink';

        $t->same([
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments',
            $hyperlinkType,
            $imageType,
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles',
            'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties',
            'officeDocument/relationships/hyperlink',
        ], array_keys($inventory));

        $t->same(3, $inventory[$imageType]['relationshipCount']);
        $t->same(2, $inventory[$imageType]['sourceCount']);
        $t->same(['/word/comments.xml', '/word/document.xml'], $inventory[$imageType]['sources']);
        $t->same(['rIdCommentImage'], $inventory[$imageType]['idsBySource']['/word/comments.xml']);
        $t->same(['rIdHero', 'rIdMissingImage'], $inventory[$imageType]['idsBySource']['/word/document.xml']);
        $t->same(3, $inventory[$imageType]['internalCount']);
        $t->same(0, $inventory[$imageType]['externalCount']);
        $t->same(2, $inventory[$imageType]['validCount']);
        $t->same(1, $inventory[$imageType]['invalidCount']);
        $t->same(['/word/media/comment.png', '/word/media/hero.png', '/word/media/missing.png'], $inventory[$imageType]['targetParts']);
        $t->same(['image/png'], $inventory[$imageType]['contentTypes']);
        $t->same(['missing-in-package'], $inventory[$imageType]['issues']);

        $t->same(1, $inventory[$hyperlinkType]['relationshipCount']);
        $t->same(0, $inventory[$hyperlinkType]['internalCount']);
        $t->same(1, $inventory[$hyperlinkType]['externalCount']);
        $t->same(0, $inventory[$hyperlinkType]['validCount']);
        $t->same(1, $inventory[$hyperlinkType]['invalidCount']);
        $t->same(['external-target-unsafe-scheme'], $inventory[$hyperlinkType]['issues']);

        $t->same(1, $inventory['officeDocument/relationships/hyperlink']['relationshipCount']);
        $t->same(false, $inventory['officeDocument/relationships/hyperlink']['relationshipTypeValid']);
        $t->same(['relationship-type-not-absolute-uri'], $inventory['officeDocument/relationships/hyperlink']['relationshipTypeIssues']);
        $t->same(['relationship-type-not-absolute-uri'], $inventory['officeDocument/relationships/hyperlink']['issues']);

        $documentInventory = [];
        foreach ($graph->relationshipTypeInventory('/word/document.xml') as $type) {
            $documentInventory[$type['type']] = $type;
        }

        $t->same([
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments',
            $hyperlinkType,
            $imageType,
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles',
            'officeDocument/relationships/hyperlink',
        ], array_keys($documentInventory));
        $t->same(2, $documentInventory[$imageType]['relationshipCount']);

        $caseEquivalentDocumentInventory = [];
        foreach ($graph->relationshipTypeInventory('/WORD/DOCUMENT.XML') as $type) {
            $caseEquivalentDocumentInventory[$type['type']] = $type;
        }

        $t->same(array_keys($documentInventory), array_keys($caseEquivalentDocumentInventory));
        $t->same(['/word/document.xml'], $caseEquivalentDocumentInventory[$imageType]['sources']);
        $t->same(['rIdHero', 'rIdMissingImage'], $caseEquivalentDocumentInventory[$imageType]['idsBySource']['/word/document.xml']);
        $t->same(false, isset($caseEquivalentDocumentInventory[$imageType]['idsBySource']['/WORD/DOCUMENT.XML']));
        $t->same([], $graph->relationshipTypeInventory('/word/missing.xml'));
    },
    'classifies OPC relationship type scope and singleton policy for import review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/second.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/_xmlsignatures/origin.sigs" ContentType="application/vnd.openxmlformats-package.digital-signature-origin"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocumentA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdDocumentB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/second.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdPackageThumb" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/thumb.png"/>
  <Relationship Id="rIdSignatureOrigin" Type="http://schemas.openxmlformats.org/package/2006/relationships/digital-signature/origin" Target="_xmlsignatures/origin.sigs"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMisplacedDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="comments.xml"/>
  <Relationship Id="rIdMisplacedCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="../docProps/core.xml"/>
  <Relationship Id="rIdThumbA" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="media/thumb-a.png"/>
  <Relationship Id="rIdThumbB" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="media/thumb-b.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/second.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/thumb-a.png', 'data' => 'PNG'],
            ['name' => 'word/media/thumb-b.png', 'data' => 'PNG'],
            ['name' => 'docProps/thumb.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => '_xmlsignatures/origin.sigs', 'data' => ''],
        ]));

        $inventory = [];
        foreach ($graph->relationshipTypeInventory() as $type) {
            $inventory[$type['type']] = $type;
        }

        $officeDocument = $inventory[OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE];
        $coreProperties = $inventory[OpcRelationshipGraph::CORE_PROPERTIES_RELATIONSHIP_TYPE];
        $thumbnail = $inventory[OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE];
        $signatureOrigin = $inventory[OpcRelationshipGraph::DIGITAL_SIGNATURE_ORIGIN_RELATIONSHIP_TYPE];

        $t->same('office-document', $officeDocument['knownRole']);
        $t->same('package-root', $officeDocument['sourceScope']);
        $t->same('package', $officeDocument['singletonScope']);
        $t->same(false, $officeDocument['policyValid']);
        $t->same(['multiple-office-document-relationships', 'office-document-relationship-source-not-package-root'], $officeDocument['policyIssues']);
        $t->same(['/', '/word/document.xml'], $officeDocument['sources']);
        $t->same(['rIdDocumentA', 'rIdDocumentB'], $officeDocument['idsBySource']['/']);
        $t->same(['rIdMisplacedDocument'], $officeDocument['idsBySource']['/word/document.xml']);

        $t->same('core-properties', $coreProperties['knownRole']);
        $t->same('package-root', $coreProperties['sourceScope']);
        $t->same('package', $coreProperties['singletonScope']);
        $t->same(false, $coreProperties['policyValid']);
        $t->same(['core-properties-relationship-source-not-package-root', 'multiple-core-properties-relationships'], $coreProperties['policyIssues']);

        $t->same('thumbnail', $thumbnail['knownRole']);
        $t->same('any-source', $thumbnail['sourceScope']);
        $t->same('source', $thumbnail['singletonScope']);
        $t->same(false, $thumbnail['policyValid']);
        $t->same(['multiple-thumbnail-relationships-for-source'], $thumbnail['policyIssues']);
        $t->same(3, $thumbnail['relationshipCount']);
        $t->same(2, $thumbnail['sourceCount']);

        $t->same('digital-signature-origin', $signatureOrigin['knownRole']);
        $t->same('package-root', $signatureOrigin['sourceScope']);
        $t->same('package', $signatureOrigin['singletonScope']);
        $t->same(true, $signatureOrigin['policyValid']);
        $t->same([], $signatureOrigin['policyIssues']);

        $documentInventory = [];
        foreach ($graph->relationshipTypeInventory('/word/document.xml') as $type) {
            $documentInventory[$type['type']] = $type;
        }

        $t->same(false, $documentInventory[OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['policyValid']);
        $t->same(['office-document-relationship-source-not-package-root'], $documentInventory[OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE]['policyIssues']);
        $t->same(false, $documentInventory[OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['policyValid']);
        $t->same(['multiple-thumbnail-relationships-for-source'], $documentInventory[OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE]['policyIssues']);
    },
    'classifies fixed WordprocessingML support relationships as source scoped singletons' => static function (TestRunner $t): void {
        $stylesType = OpcRelationshipGraph::WORDPROCESSING_STYLES_RELATIONSHIP_TYPE;
        $numberingType = OpcRelationshipGraph::WORDPROCESSING_NUMBERING_RELATIONSHIP_TYPE;
        $settingsType = OpcRelationshipGraph::WORDPROCESSING_SETTINGS_RELATIONSHIP_TYPE;
        $hyperlinkType = OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE;

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/alternate-styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/settings-copy.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="{$stylesType}" Target="styles.xml"/>
  <Relationship Id="rIdStylesDuplicate" Type="{$stylesType}" Target="alternate-styles.xml"/>
  <Relationship Id="rIdNumbering" Type="{$numberingType}" Target="numbering.xml"/>
  <Relationship Id="rIdSettings" Type="{$settingsType}" Target="settings.xml"/>
  <Relationship Id="rIdSettingsDuplicate" Type="{$settingsType}" Target="settings-copy.xml"/>
  <Relationship Id="rIdSourceLinkA" Type="{$hyperlinkType}" Target="https://example.test/a" TargetMode="External"/>
  <Relationship Id="rIdSourceLinkB" Type="{$hyperlinkType}" Target="https://example.test/b" TargetMode="External"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentStyles" Type="{$stylesType}" Target="styles.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles/>'],
            ['name' => 'word/alternate-styles.xml', 'data' => '<w:styles/>'],
            ['name' => 'word/numbering.xml', 'data' => '<w:numbering/>'],
            ['name' => 'word/settings.xml', 'data' => '<w:settings/>'],
            ['name' => 'word/settings-copy.xml', 'data' => '<w:settings/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
        ]));

        $inventory = [];
        foreach ($graph->relationshipTypeInventory() as $type) {
            $inventory[$type['type']] = $type;
        }

        $styles = $inventory[$stylesType];
        $numbering = $inventory[$numberingType];
        $settings = $inventory[$settingsType];
        $hyperlinks = $inventory[$hyperlinkType];

        $t->same('styles', $styles['knownRole']);
        $t->same('any-source', $styles['sourceScope']);
        $t->same('source', $styles['singletonScope']);
        $t->same(false, $styles['policyValid']);
        $t->same(['multiple-styles-relationships-for-source'], $styles['policyIssues']);
        $t->same(3, $styles['relationshipCount']);
        $t->same(2, $styles['sourceCount']);
        $t->same(['/word/comments.xml', '/word/document.xml'], $styles['sources']);
        $t->same(['rIdCommentStyles'], $styles['idsBySource']['/word/comments.xml']);
        $t->same(['rIdStyles', 'rIdStylesDuplicate'], $styles['idsBySource']['/word/document.xml']);

        $t->same('numbering', $numbering['knownRole']);
        $t->same('source', $numbering['singletonScope']);
        $t->same(true, $numbering['policyValid']);
        $t->same([], $numbering['policyIssues']);

        $t->same('settings', $settings['knownRole']);
        $t->same('source', $settings['singletonScope']);
        $t->same(false, $settings['policyValid']);
        $t->same(['multiple-settings-relationships-for-source'], $settings['policyIssues']);
        $t->same(['rIdSettings', 'rIdSettingsDuplicate'], $settings['idsBySource']['/word/document.xml']);

        $t->same(null, $hyperlinks['knownRole']);
        $t->same(null, $hyperlinks['singletonScope']);
        $t->same(true, $hyperlinks['policyValid']);
        $t->same([], $hyperlinks['policyIssues']);

        $consistency = $graph->preflightPackageConsistency();
        $policies = [];
        foreach ($consistency['relationshipTypePolicies'] as $policy) {
            $policies[$policy['type']] = $policy;
        }

        $t->same(false, $consistency['relationshipTypePoliciesValid']);
        $t->same(false, $policies[$stylesType]['policyValid']);
        $t->same(true, $policies[$numberingType]['policyValid']);
        $t->same(false, $policies[$settingsType]['policyValid']);
        $t->same(false, isset($policies[$hyperlinkType]));
    },
    'classifies WordprocessingML alternative-format imports as unscoped relationship roles' => static function (TestRunner $t): void {
        $altChunkType = OpcRelationshipGraph::WORDPROCESSING_ALTERNATIVE_FORMAT_IMPORT_RELATIONSHIP_TYPE;

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/chunks/review.html" ContentType="text/html"/>
  <Override PartName="/word/chunks/plain-review.txt" ContentType="text/plain; charset=utf-8"/>
  <Override PartName="/word/chunks/comment-review.xhtml" ContentType="application/xhtml+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHtmlChunk" Type="{$altChunkType}" Target="chunks/review.html"/>
  <Relationship Id="rIdPlainTextChunk" Type="{$altChunkType}" Target="chunks/plain-review.txt"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentChunk" Type="{$altChunkType}" Target="chunks/comment-review.xhtml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/chunks/review.html', 'data' => '<p>Imported HTML review</p>'],
            ['name' => 'word/chunks/plain-review.txt', 'data' => 'Imported plain review'],
            ['name' => 'word/chunks/comment-review.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Comment review</p></body></html>'],
        ]));

        $inventory = [];
        foreach ($graph->relationshipTypeInventory() as $type) {
            $inventory[$type['type']] = $type;
        }

        $altChunk = $inventory[$altChunkType] ?? null;
        $t->same(true, is_array($altChunk));
        if (!is_array($altChunk)) {
            return;
        }

        $t->same('alternative-format-import', $altChunk['knownRole']);
        $t->same('any-source', $altChunk['sourceScope']);
        $t->same(null, $altChunk['singletonScope']);
        $t->same(true, $altChunk['policyValid']);
        $t->same([], $altChunk['policyIssues']);
        $t->same(3, $altChunk['relationshipCount']);
        $t->same(2, $altChunk['sourceCount']);
        $t->same(['/word/comments.xml', '/word/document.xml'], $altChunk['sources']);
        $t->same(['rIdCommentChunk'], $altChunk['idsBySource']['/word/comments.xml']);
        $t->same(['rIdHtmlChunk', 'rIdPlainTextChunk'], $altChunk['idsBySource']['/word/document.xml']);
        $t->same(3, $altChunk['internalCount']);
        $t->same(0, $altChunk['externalCount']);
        $t->same(3, $altChunk['validCount']);
        $t->same(0, $altChunk['invalidCount']);
        $t->same([
            '/word/chunks/comment-review.xhtml',
            '/word/chunks/plain-review.txt',
            '/word/chunks/review.html',
        ], $altChunk['targetParts']);
        $t->same([
            'application/xhtml+xml',
            'text/html',
            'text/plain; charset=utf-8',
        ], $altChunk['contentTypes']);

        $documentInventory = [];
        foreach ($graph->relationshipTypeInventory('/word/document.xml') as $type) {
            $documentInventory[$type['type']] = $type;
        }
        $t->same(2, $documentInventory[$altChunkType]['relationshipCount']);
        $t->same(null, $documentInventory[$altChunkType]['singletonScope']);
        $t->same(true, $documentInventory[$altChunkType]['policyValid']);

        $policies = [];
        foreach ($graph->preflightPackageConsistency()['relationshipTypePolicies'] as $policy) {
            $policies[$policy['type']] = $policy;
        }
        $t->same('alternative-format-import', $policies[$altChunkType]['knownRole'] ?? null);
        $t->same(true, array_key_exists('singletonScope', $policies[$altChunkType] ?? []));
        $t->same(null, $policies[$altChunkType]['singletonScope']);
        $t->same(true, $policies[$altChunkType]['policyValid'] ?? null);
        $t->same([], $policies[$altChunkType]['policyIssues'] ?? null);
    },
    'classifies DrawingML relationship roles without source singleton constraints' => static function (TestRunner $t): void {
        $chartType = OpcRelationshipGraph::DRAWINGML_CHART_RELATIONSHIP_TYPE;
        $diagramDataType = OpcRelationshipGraph::DRAWINGML_DIAGRAM_DATA_RELATIONSHIP_TYPE;
        $diagramLayoutType = OpcRelationshipGraph::DRAWINGML_DIAGRAM_LAYOUT_RELATIONSHIP_TYPE;
        $diagramQuickStyleType = OpcRelationshipGraph::DRAWINGML_DIAGRAM_QUICK_STYLE_RELATIONSHIP_TYPE;
        $diagramColorsType = OpcRelationshipGraph::DRAWINGML_DIAGRAM_COLORS_RELATIONSHIP_TYPE;

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/charts/chart2.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/diagrams/data1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramData+xml"/>
  <Override PartName="/word/diagrams/layout1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramLayout+xml"/>
  <Override PartName="/word/diagrams/quickStyle1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramStyle+xml"/>
  <Override PartName="/word/diagrams/colors1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramColors+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdChartOne" Type="{$chartType}" Target="charts/chart1.xml"/>
  <Relationship Id="rIdChartTwo" Type="{$chartType}" Target="charts/chart2.xml"/>
  <Relationship Id="rIdDiagramData" Type="{$diagramDataType}" Target="diagrams/data1.xml"/>
  <Relationship Id="rIdDiagramLayout" Type="{$diagramLayoutType}" Target="diagrams/layout1.xml"/>
  <Relationship Id="rIdDiagramQuickStyle" Type="{$diagramQuickStyleType}" Target="diagrams/quickStyle1.xml"/>
  <Relationship Id="rIdDiagramColors" Type="{$diagramColorsType}" Target="diagrams/colors1.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/charts/chart1.xml', 'data' => '<c:chartSpace/>'],
            ['name' => 'word/charts/chart2.xml', 'data' => '<c:chartSpace/>'],
            ['name' => 'word/diagrams/data1.xml', 'data' => '<dgm:dataModel/>'],
            ['name' => 'word/diagrams/layout1.xml', 'data' => '<dgm:layoutDef/>'],
            ['name' => 'word/diagrams/quickStyle1.xml', 'data' => '<dgm:styleDef/>'],
            ['name' => 'word/diagrams/colors1.xml', 'data' => '<dgm:colorsDef/>'],
        ]));

        $documentInventory = [];
        foreach ($graph->relationshipTypeInventory('/word/document.xml') as $type) {
            $documentInventory[$type['type']] = $type;
        }

        foreach ([
            $chartType => ['role' => 'chart', 'count' => 2, 'ids' => ['rIdChartOne', 'rIdChartTwo']],
            $diagramDataType => ['role' => 'diagram-data', 'count' => 1, 'ids' => ['rIdDiagramData']],
            $diagramLayoutType => ['role' => 'diagram-layout', 'count' => 1, 'ids' => ['rIdDiagramLayout']],
            $diagramQuickStyleType => ['role' => 'diagram-quick-style', 'count' => 1, 'ids' => ['rIdDiagramQuickStyle']],
            $diagramColorsType => ['role' => 'diagram-colors', 'count' => 1, 'ids' => ['rIdDiagramColors']],
        ] as $relationshipType => $expected) {
            $entry = $documentInventory[$relationshipType] ?? null;
            $t->same(true, is_array($entry));
            if (!is_array($entry)) {
                continue;
            }

            $t->same($expected['role'], $entry['knownRole']);
            $t->same('any-source', $entry['sourceScope']);
            $t->same(null, $entry['singletonScope']);
            $t->same(true, $entry['policyValid']);
            $t->same([], $entry['policyIssues']);
            $t->same($expected['count'], $entry['relationshipCount']);
            $t->same(['/word/document.xml'], $entry['sources']);
            $t->same($expected['ids'], $entry['idsBySource']['/word/document.xml']);
        }

        $consistency = $graph->preflightPackageConsistency();
        $policies = [];
        foreach ($consistency['relationshipTypePolicies'] as $policy) {
            $policies[$policy['type']] = $policy;
        }

        $t->same(true, $consistency['relationshipTypePoliciesValid']);
        $t->same('chart', $policies[$chartType]['knownRole'] ?? null);
        $t->same(true, array_key_exists('singletonScope', $policies[$chartType]));
        $t->same(null, $policies[$chartType]['singletonScope']);
        $t->same(true, $policies[$chartType]['policyValid'] ?? null);
        $t->same('diagram-data', $policies[$diagramDataType]['knownRole'] ?? null);
        $t->same('diagram-layout', $policies[$diagramLayoutType]['knownRole'] ?? null);
        $t->same('diagram-quick-style', $policies[$diagramQuickStyleType]['knownRole'] ?? null);
        $t->same('diagram-colors', $policies[$diagramColorsType]['knownRole'] ?? null);
    },
    'preflights OPC relationship type policies in package consistency' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/second.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocumentA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdDocumentB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/second.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdPackageThumb" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="docProps/thumb.png"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMisplacedDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="comments.xml"/>
  <Relationship Id="rIdMisplacedCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="../docProps/core.xml"/>
  <Relationship Id="rIdThumbA" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="media/thumb-a.png"/>
  <Relationship Id="rIdThumbB" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/thumbnail" Target="media/thumb-b.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/second.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/thumb-a.png', 'data' => 'PNG'],
            ['name' => 'word/media/thumb-b.png', 'data' => 'PNG'],
            ['name' => 'docProps/thumb.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $consistency = $graph->preflightPackageConsistency();
        $policies = [];
        foreach ($consistency['relationshipTypePolicies'] ?? [] as $policy) {
            $policies[$policy['type']] = $policy;
        }

        $t->same(true, array_key_exists('relationshipTypePoliciesValid', $consistency));
        $t->same(false, $consistency['valid']);
        $t->same(true, $consistency['packagePartsValid']);
        $t->same(true, $consistency['contentTypeOverridesValid']);
        $t->same(true, $consistency['relationshipTargetsValid']);
        $t->same(false, $consistency['relationshipTypePoliciesValid']);
        $t->same([
            OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE,
            OpcRelationshipGraph::CORE_PROPERTIES_RELATIONSHIP_TYPE,
            OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE,
        ], array_keys($policies));

        $officeDocument = $policies[OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE];
        $coreProperties = $policies[OpcRelationshipGraph::CORE_PROPERTIES_RELATIONSHIP_TYPE];
        $thumbnail = $policies[OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE];

        $t->same('office-document', $officeDocument['knownRole']);
        $t->same(false, $officeDocument['policyValid']);
        $t->same(['multiple-office-document-relationships', 'office-document-relationship-source-not-package-root'], $officeDocument['policyIssues']);
        $t->same(3, $officeDocument['relationshipCount']);
        $t->same(['/', '/word/document.xml'], $officeDocument['sources']);

        $t->same('core-properties', $coreProperties['knownRole']);
        $t->same(false, $coreProperties['policyValid']);
        $t->same(['core-properties-relationship-source-not-package-root', 'multiple-core-properties-relationships'], $coreProperties['policyIssues']);
        $t->same(2, $coreProperties['relationshipCount']);

        $t->same('thumbnail', $thumbnail['knownRole']);
        $t->same(false, $thumbnail['policyValid']);
        $t->same(['multiple-thumbnail-relationships-for-source'], $thumbnail['policyIssues']);
        $t->same(3, $thumbnail['relationshipCount']);
        $t->same(2, $thumbnail['sourceCount']);
    },
    'summarizes OPC known relationship role policy buckets for importer reports' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="html" ContentType="text/html"/>
  <Default Extension="txt" ContentType="text/plain"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/second.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/rogue.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/styles-copy.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/charts/chart2.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocumentA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdDocumentB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/second.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMisplacedDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="rogue.xml"/>
  <Relationship Id="rIdStylesA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdStylesB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles-copy.xml"/>
  <Relationship Id="rIdAltChunkHtml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/review.html"/>
  <Relationship Id="rIdAltChunkText" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/aFChunk" Target="chunks/plain.txt"/>
  <Relationship Id="rIdChartA" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/chart1.xml"/>
  <Relationship Id="rIdChartB" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/chart2.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/second.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/rogue.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/styles-copy.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/chunks/review.html', 'data' => '<html><body>Review</body></html>'],
            ['name' => 'word/chunks/plain.txt', 'data' => 'Review text'],
            ['name' => 'word/charts/chart1.xml', 'data' => '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"/>'],
            ['name' => 'word/charts/chart2.xml', 'data' => '<c:chartSpace xmlns:c="http://schemas.openxmlformats.org/drawingml/2006/chart"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $summary = $graph->relationshipRolePolicySummary();
        $roles = [];
        foreach ($summary['roles'] as $role) {
            $roles[$role['role']] = $role;
        }

        $t->same(null, $summary['source']);
        $t->same(false, $summary['valid']);
        $t->same(5, $summary['knownRoleCount']);
        $t->same(10, $summary['relationshipCount']);
        $t->same(3, $summary['validPolicyCount']);
        $t->same(2, $summary['invalidPolicyCount']);
        $t->same(2, $summary['packageScopedCount']);
        $t->same(1, $summary['sourceScopedCount']);
        $t->same(2, $summary['unscopedCount']);
        $t->same(2, $summary['packageSingletonCount']);
        $t->same(1, $summary['sourceSingletonCount']);
        $t->same([
            'multiple-office-document-relationships' => 1,
            'multiple-styles-relationships-for-source' => 1,
            'office-document-relationship-source-not-package-root' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'multiple-office-document-relationships',
            'multiple-styles-relationships-for-source',
            'office-document-relationship-source-not-package-root',
        ], $summary['issues']);
        $t->same([
            'alternative-format-import',
            'chart',
            'core-properties',
            'office-document',
            'styles',
        ], array_keys($roles));

        $t->same(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE, $roles['office-document']['type']);
        $t->same(3, $roles['office-document']['relationshipCount']);
        $t->same(2, $roles['office-document']['sourceCount']);
        $t->same(['/', '/word/document.xml'], $roles['office-document']['sources']);
        $t->same('package-root', $roles['office-document']['sourceScope']);
        $t->same('package', $roles['office-document']['singletonScope']);
        $t->same(false, $roles['office-document']['policyValid']);
        $t->same(['multiple-office-document-relationships', 'office-document-relationship-source-not-package-root'], $roles['office-document']['policyIssues']);
        $t->same(['/word/document.xml', '/word/rogue.xml', '/word/second.xml'], $roles['office-document']['targetParts']);

        $t->same(OpcRelationshipGraph::WORDPROCESSING_STYLES_RELATIONSHIP_TYPE, $roles['styles']['type']);
        $t->same(2, $roles['styles']['relationshipCount']);
        $t->same(1, $roles['styles']['sourceCount']);
        $t->same('any-source', $roles['styles']['sourceScope']);
        $t->same('source', $roles['styles']['singletonScope']);
        $t->same(false, $roles['styles']['policyValid']);
        $t->same(['multiple-styles-relationships-for-source'], $roles['styles']['policyIssues']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml',
        ], $roles['styles']['contentTypes']);

        $t->same(OpcRelationshipGraph::WORDPROCESSING_ALTERNATIVE_FORMAT_IMPORT_RELATIONSHIP_TYPE, $roles['alternative-format-import']['type']);
        $t->same(2, $roles['alternative-format-import']['relationshipCount']);
        $t->same(null, $roles['alternative-format-import']['singletonScope']);
        $t->same(true, $roles['alternative-format-import']['policyValid']);
        $t->same(['text/html', 'text/plain'], $roles['alternative-format-import']['contentTypes']);

        $t->same(OpcRelationshipGraph::DRAWINGML_CHART_RELATIONSHIP_TYPE, $roles['chart']['type']);
        $t->same(2, $roles['chart']['relationshipCount']);
        $t->same(null, $roles['chart']['singletonScope']);
        $t->same(true, $roles['chart']['policyValid']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.drawingml.chart+xml',
        ], $roles['chart']['contentTypes']);

        $documentSummary = $graph->relationshipRolePolicySummary('/word/document.xml');
        $documentRoles = [];
        foreach ($documentSummary['roles'] as $role) {
            $documentRoles[$role['role']] = $role;
        }

        $t->same('/word/document.xml', $documentSummary['source']);
        $t->same(false, $documentSummary['valid']);
        $t->same(4, $documentSummary['knownRoleCount']);
        $t->same(7, $documentSummary['relationshipCount']);
        $t->same(2, $documentSummary['validPolicyCount']);
        $t->same(2, $documentSummary['invalidPolicyCount']);
        $t->same(1, $documentSummary['packageScopedCount']);
        $t->same(1, $documentSummary['sourceScopedCount']);
        $t->same(2, $documentSummary['unscopedCount']);
        $t->same(1, $documentSummary['packageSingletonCount']);
        $t->same(1, $documentSummary['sourceSingletonCount']);
        $t->same([
            'multiple-styles-relationships-for-source' => 1,
            'office-document-relationship-source-not-package-root' => 1,
        ], $documentSummary['issueCounts']);
        $t->same([
            'alternative-format-import',
            'chart',
            'office-document',
            'styles',
        ], array_keys($documentRoles));
        $t->same(1, $documentRoles['office-document']['relationshipCount']);
        $t->same(['office-document-relationship-source-not-package-root'], $documentRoles['office-document']['policyIssues']);
        $t->same(2, $documentRoles['styles']['relationshipCount']);
    },
    'summarizes package-wide OPC relationship source inventory for import review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdUnsafeLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="file:///tmp/source.html" TargetMode="External"/>
  <Relationship Id="rIdMalformedType" Type="officeDocument/relationships/hyperlink" Target="../customXml/item1.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'customXml/item1.xml', 'data' => '<review/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $inventory = [];
        foreach ($graph->relationshipSourceInventory() as $source) {
            $inventory[$source['source']] = $source;
        }

        $rootSource = $inventory['/'];
        $documentSource = $inventory['/word/document.xml'];

        $t->same(['/', '/word/document.xml'], array_keys($inventory));
        $t->same(true, $rootSource['sourceExists']);
        $t->same(null, $rootSource['sourceContentType']);
        $t->same('/_rels/.rels', $rootSource['relationshipPartName']);
        $t->same(true, $rootSource['relationshipPartExists']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $rootSource['relationshipPartContentType']);
        $t->same(true, $rootSource['relationshipPartLoaded']);
        $t->same('loaded', $rootSource['relationshipPartLoadAction']);
        $t->same('loaded', $rootSource['relationshipPartLoadReason']);
        $t->same([], $rootSource['relationshipPartIssues']);
        $t->same(2, $rootSource['relationshipCount']);
        $t->same(2, $rootSource['internalCount']);
        $t->same(0, $rootSource['externalCount']);
        $t->same(2, $rootSource['validTargetCount']);
        $t->same(0, $rootSource['invalidTargetCount']);
        $t->same([
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument',
            'http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties',
        ], $rootSource['relationshipTypes']);
        $t->same(['/docProps/core.xml', '/word/document.xml'], $rootSource['targetParts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/vnd.openxmlformats-package.core-properties+xml',
        ], $rootSource['contentTypes']);
        $t->same([], $rootSource['externalTargets']);
        $t->same([], $rootSource['missingTargetParts']);
        $t->same([], $rootSource['issues']);
        $t->same(true, $rootSource['valid']);

        $t->same(true, $documentSource['sourceExists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $documentSource['sourceContentType']);
        $t->same('/word/_rels/document.xml.rels', $documentSource['relationshipPartName']);
        $t->same(true, $documentSource['relationshipPartExists']);
        $t->same('application/vnd.openxmlformats-package.relationships+xml', $documentSource['relationshipPartContentType']);
        $t->same(true, $documentSource['relationshipPartLoaded']);
        $t->same('loaded', $documentSource['relationshipPartLoadAction']);
        $t->same('loaded', $documentSource['relationshipPartLoadReason']);
        $t->same([], $documentSource['relationshipPartIssues']);
        $t->same(4, $documentSource['relationshipCount']);
        $t->same(3, $documentSource['internalCount']);
        $t->same(1, $documentSource['externalCount']);
        $t->same(1, $documentSource['validTargetCount']);
        $t->same(3, $documentSource['invalidTargetCount']);
        $t->same([
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles',
            'officeDocument/relationships/hyperlink',
        ], $documentSource['relationshipTypes']);
        $t->same(['/customXml/item1.xml', '/word/media/missing.png', '/word/styles.xml'], $documentSource['targetParts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml',
            'application/xml',
            'image/png',
        ], $documentSource['contentTypes']);
        $t->same(['file:///tmp/source.html'], $documentSource['externalTargets']);
        $t->same(['/word/media/missing.png'], $documentSource['missingTargetParts']);
        $t->same([
            'external-target-unsafe-scheme',
            'missing-in-package',
            'relationship-type-not-absolute-uri',
        ], $documentSource['issues']);
        $t->same(false, $documentSource['valid']);
    },
    'summarizes package-wide OPC relationship target provenance for import review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png?slot=cover#preview"/>
  <Relationship Id="rIdMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdRelPart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../_rels/.rels"/>
  <Relationship Id="rIdTypes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="/[Content_Types].xml"/>
  <Relationship Id="rIdReserved" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="_rels/orphan.xml"/>
  <Relationship Id="rIdFile" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="file:///tmp/source.docx" TargetMode="External"/>
  <Relationship Id="rIdExternalRelative" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="../review/source.html#packet" TargetMode="External"/>
  <Relationship Id="rIdExternalLocalImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png" TargetMode="External"/>
  <Relationship Id="rIdSelf" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="document.xml#self"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
        ]));

        $summary = $graph->relationshipTargetSummary();
        $targets = [];
        foreach ($summary['targets'] as $target) {
            $targets[$target['id']] = $target;
        }

        $t->same(null, $summary['relationshipType']);
        $t->same(false, $summary['valid']);
        $t->same(10, $summary['relationshipCount']);
        $t->same(4, $summary['validTargetCount']);
        $t->same(6, $summary['invalidTargetCount']);
        $t->same(7, $summary['internalTargetCount']);
        $t->same(3, $summary['externalTargetCount']);
        $t->same(5, $summary['existingInternalTargetCount']);
        $t->same(2, $summary['missingInternalTargetCount']);
        $t->same(1, $summary['queryTargetCount']);
        $t->same(2, $summary['fragmentTargetCount']);
        $t->same(1, $summary['sameSourceReferenceCount']);
        $t->same(1, $summary['relationshipPartTargetCount']);
        $t->same(1, $summary['contentTypesItemTargetCount']);
        $t->same(1, $summary['reservedRelationshipDirectoryTargetCount']);
        $t->same(1, $summary['unsafeExternalTargetCount']);
        $t->same(2, $summary['relativeExternalTargetCount']);
        $t->same(2, $summary['rewriteRequiredExternalTargetCount']);
        $t->same(6, $summary['targetPartCount']);
        $t->same([
            '/[Content_Types].xml',
            '/_rels/.rels',
            '/word/_rels/orphan.xml',
            '/word/comments.xml',
            '/word/document.xml',
            '/word/media/hero.png',
        ], $summary['targetParts']);
        $t->same(['/word/_rels/orphan.xml', '/word/comments.xml'], $summary['missingTargetParts']);
        $t->same([
            '../review/source.html#packet',
            'file:///tmp/source.docx',
            'media/hero.png',
        ], $summary['externalTargets']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/vnd.openxmlformats-package.relationships+xml',
            'application/xml',
            'image/png',
        ], $summary['contentTypes']);
        $t->same(['default' => 5, 'override' => 2], $summary['contentTypeSourceCounts']);
        $t->same([
            'external-blocked' => 1,
            'external-package-shadow' => 1,
            'external-relative-reference' => 1,
            'internal-content-types-item' => 1,
            'internal-existing-part' => 2,
            'internal-missing-part' => 1,
            'internal-relationship-part' => 1,
            'internal-reserved-relationship-directory' => 1,
            'internal-same-source' => 1,
        ], $summary['targetResolutionKindCounts']);
        $t->same([
            '/:rIdDocument',
            '/word/document.xml:rIdImage',
        ], $summary['targetKeysByResolutionKind']['internal-existing-part']);
        $t->same(['/word/document.xml:rIdExternalRelative'], $summary['targetKeysByResolutionKind']['external-relative-reference']);
        $t->same(['/word/document.xml:rIdExternalLocalImage'], $summary['targetKeysByResolutionKind']['external-package-shadow']);
        $t->same([
            '/word/document.xml',
            '/word/media/hero.png',
        ], $summary['targetNamesByResolutionKind']['internal-existing-part']);
        $t->same(['../review/source.html#packet'], $summary['targetNamesByResolutionKind']['external-relative-reference']);
        $t->same(['media/hero.png'], $summary['targetNamesByResolutionKind']['external-package-shadow']);
        $t->same([
            'external-target-matches-package-part' => 1,
            'external-target-unsafe-scheme' => 1,
            'missing-in-package' => 2,
            'targets-content-types-item' => 1,
            'targets-relationship-part' => 1,
            'targets-reserved-relationship-directory-part' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'external-target-matches-package-part',
            'external-target-unsafe-scheme',
            'missing-in-package',
            'targets-content-types-item',
            'targets-relationship-part',
            'targets-reserved-relationship-directory-part',
        ], $summary['issues']);

        $t->same('/word/media/hero.png', $targets['rIdImage']['targetPart']);
        $t->same('slot=cover', $targets['rIdImage']['targetQuery']);
        $t->same('preview', $targets['rIdImage']['targetFragment']);
        $t->same('default', $targets['rIdImage']['contentTypeSource']);
        $t->same(true, $targets['rIdImage']['valid']);
        $t->same('internal-existing-part', $targets['rIdImage']['targetResolutionKind']);

        $t->same('/word/document.xml', $targets['rIdSelf']['targetPart']);
        $t->same(true, $targets['rIdSelf']['sameSourceReference']);
        $t->same('self', $targets['rIdSelf']['targetFragment']);
        $t->same('internal-same-source', $targets['rIdSelf']['targetResolutionKind']);

        $t->same('/_rels/.rels', $targets['rIdRelPart']['targetPart']);
        $t->same(true, $targets['rIdRelPart']['relationshipPartTarget']);
        $t->same(['targets-relationship-part'], $targets['rIdRelPart']['issues']);
        $t->same('internal-relationship-part', $targets['rIdRelPart']['targetResolutionKind']);
        $t->same('/[Content_Types].xml', $targets['rIdTypes']['targetPart']);
        $t->same(['targets-content-types-item'], $targets['rIdTypes']['issues']);
        $t->same('internal-content-types-item', $targets['rIdTypes']['targetResolutionKind']);
        $t->same('/word/_rels/orphan.xml', $targets['rIdReserved']['targetPart']);
        $t->same(['missing-in-package', 'targets-reserved-relationship-directory-part'], $targets['rIdReserved']['issues']);
        $t->same('internal-reserved-relationship-directory', $targets['rIdReserved']['targetResolutionKind']);
        $t->same(false, $targets['rIdFile']['externalTargetAllowed']);
        $t->same('file', $targets['rIdFile']['externalTargetScheme']);
        $t->same('external-blocked', $targets['rIdFile']['targetResolutionKind']);
        $t->same(true, $targets['rIdExternalRelative']['externalTargetRequiresBaseUri']);
        $t->same('external-relative-reference', $targets['rIdExternalRelative']['targetResolutionKind']);
        $t->same(['external-target-matches-package-part'], $targets['rIdExternalLocalImage']['issues']);
        $t->same('external-package-shadow', $targets['rIdExternalLocalImage']['targetResolutionKind']);

        $imageSummary = $graph->relationshipTargetSummary(OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE, $imageSummary['relationshipType']);
        $t->same(false, $imageSummary['valid']);
        $t->same(2, $imageSummary['relationshipCount']);
        $t->same(['/word/media/hero.png'], $imageSummary['targetParts']);
        $t->same([
            'external-package-shadow' => 1,
            'internal-existing-part' => 1,
        ], $imageSummary['targetResolutionKindCounts']);
    },
    'summarizes OPC relationship target aggregate buckets for reviewer handoff' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/package-source" TargetMode="External"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdFootnotes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="footnotes.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png?asset=1#main"/>
  <Relationship Id="rIdMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdFile" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="file:///tmp/source.docx" TargetMode="External"/>
  <Relationship Id="rIdRelativeExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="../review.html" TargetMode="External"/>
</Relationships>
XML;

        $footnotesRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdNoteImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/note.png"/>
  <Relationship Id="rIdNoteLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/note-source" TargetMode="External"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $footnotesRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'HERO'],
            ['name' => 'word/media/note.png', 'data' => 'NOTE'],
        ]));

        $summary = $graph->relationshipTargetSummary();
        $imageSummary = $graph->relationshipTargetSummary(OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE);

        $t->same(3, $summary['sourceCount']);
        $t->same([
            '/' => 2,
            '/word/document.xml' => 5,
            '/word/footnotes.xml' => 2,
        ], $summary['sourcePartCounts']);
        $t->same([
            OpcRelationshipGraph::WORDPROCESSING_FOOTNOTES_RELATIONSHIP_TYPE => 1,
            OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE => 4,
            OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE => 3,
            OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE => 1,
        ], $summary['relationshipTypeCounts']);
        $t->same([
            'absolute-uri' => 3,
            'relative-reference' => 1,
        ], $summary['externalTargetKindCounts']);
        $t->same([
            'file' => 1,
            'https' => 2,
            'none' => 1,
        ], $summary['externalTargetSchemeCounts']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml' => 1,
            'image/png' => 3,
        ], $summary['contentTypeCounts']);
        $t->same(['default' => 3, 'override' => 2], $summary['contentTypeSourceCounts']);
        $t->same(9, $summary['relationshipCount']);
        $t->same(4, $summary['externalTargetCount']);
        $t->same(1, $summary['missingInternalTargetCount']);
        $t->same(['/word/media/missing.png'], $summary['missingTargetParts']);
        $t->same(1, $summary['queryTargetCount']);
        $t->same(1, $summary['fragmentTargetCount']);

        $t->same(2, $imageSummary['sourceCount']);
        $t->same([
            '/word/document.xml' => 2,
            '/word/footnotes.xml' => 1,
        ], $imageSummary['sourcePartCounts']);
        $t->same([OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE => 3], $imageSummary['relationshipTypeCounts']);
        $t->same([], $imageSummary['externalTargetSchemeCounts']);
        $t->same(['image/png' => 3], $imageSummary['contentTypeCounts']);
    },
    'summarizes package-wide OPC content type inventory for import review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
  <Override PartName="/word/media/stale-review.png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdUnsafeLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>
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
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $inventory = [];
        foreach ($graph->contentTypeInventory() as $contentType) {
            $inventory[$contentType['contentType']] = $contentType;
        }

        $imageType = 'image/png';
        $relationshipType = 'application/vnd.openxmlformats-package.relationships+xml';
        $commentsType = 'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml';

        $t->same([
            $commentsType,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml',
            'application/vnd.openxmlformats-package.core-properties+xml',
            $relationshipType,
            'application/xml',
            $imageType,
        ], array_keys($inventory));

        $t->same(0, $inventory[$relationshipType]['overrideCount']);
        $t->same(2, $inventory[$relationshipType]['packagePartCount']);
        $t->same(2, $inventory[$relationshipType]['defaultPartCount']);
        $t->same(2, $inventory[$relationshipType]['relationshipPartCount']);
        $t->same(['/_rels/.rels', '/word/_rels/document.xml.rels'], $inventory[$relationshipType]['parts']);
        $t->same(['/', '/word/document.xml'], $inventory[$relationshipType]['relationshipSources']);
        $t->same([], $inventory[$relationshipType]['issues']);

        $t->same(['/word/comments.xml'], $inventory[$commentsType]['parts']);
        $t->same(['/word/comments.xml'], $inventory[$commentsType]['overrideParts']);
        $t->same(1, $inventory[$commentsType]['relationshipTargetReferenceCount']);
        $t->same(['/word/comments.xml'], $inventory[$commentsType]['relationshipTargetParts']);
        $t->same(['/word/comments.xml'], $inventory[$commentsType]['reachableTargetParts']);
        $t->same([], $inventory[$commentsType]['issues']);

        $t->same(['/word/_rels/comments.xml.rels'], $inventory['application/xml']['parts']);
        $t->same(['/word/_rels/comments.xml.rels'], $inventory['application/xml']['overrideParts']);
        $t->same(1, $inventory['application/xml']['relationshipPartCount']);
        $t->same(['/word/comments.xml'], $inventory['application/xml']['relationshipSources']);
        $t->same(1, $inventory['application/xml']['invalidPackagePartCount']);
        $t->same(['invalid-relationship-content-type'], $inventory['application/xml']['issues']);

        $t->same(1, $inventory[$imageType]['packagePartCount']);
        $t->same(1, $inventory[$imageType]['defaultPartCount']);
        $t->same(1, $inventory[$imageType]['overrideCount']);
        $t->same(['/word/media/hero.png'], $inventory[$imageType]['parts']);
        $t->same(['/word/media/stale-review.png'], $inventory[$imageType]['overrideParts']);
        $t->same(['/word/media/stale-review.png'], $inventory[$imageType]['missingOverrideParts']);
        $t->same(2, $inventory[$imageType]['relationshipTargetReferenceCount']);
        $t->same(['/word/media/hero.png', '/word/media/missing.png'], $inventory[$imageType]['relationshipTargetParts']);
        $t->same(['/word/media/hero.png', '/word/media/missing.png'], $inventory[$imageType]['reachableTargetParts']);
        $t->same('rIdHero', $inventory[$imageType]['relationshipTargetReferences'][0]['id']);
        $t->same(true, $inventory[$imageType]['relationshipTargetReferences'][0]['valid']);
        $t->same('rIdMissingImage', $inventory[$imageType]['relationshipTargetReferences'][1]['id']);
        $t->same(false, $inventory[$imageType]['relationshipTargetReferences'][1]['valid']);
        $t->same(['missing-in-package'], $inventory[$imageType]['relationshipTargetReferences'][1]['issues']);
        $t->same(['missing-in-package', 'override-target-missing-part'], $inventory[$imageType]['issues']);
    },
    'summarizes OPC content type inventory counts for package review' => static function (TestRunner $t): void {
        $embeddedPackageType = 'application/vnd.openxmlformats-officedocument.package';
        $imageType = 'image/png';
        $relationshipType = 'application/vnd.openxmlformats-package.relationships+xml';
        $contentTypesXml = <<<XML
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="{$relationshipType}"/>
  <Default Extension="png" ContentType="{$imageType}"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
  <Override PartName="/word/media/stale-review.png" ContentType="{$imageType}"/>
  <Override PartName="/word/embeddings/nested.docx" ContentType="{$embeddedPackageType}"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdEmbeddedPackage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/nested.docx"/>
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
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/embeddings/nested.docx', 'data' => 'DOCX'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $summary = $graph->contentTypeInventorySummary();
        $contentTypes = [];
        foreach ($summary['contentTypes'] as $contentType) {
            $contentTypes[$contentType['contentType']] = $contentType;
        }

        $t->same(false, $summary['valid']);
        $t->same(8, $summary['contentTypeCount']);
        $t->same(9, $summary['packagePartCount']);
        $t->same(7, $summary['overridePartCount']);
        $t->same(3, $summary['defaultPartCount']);
        $t->same(3, $summary['relationshipPartCount']);
        $t->same(3, $summary['relationshipSourceCount']);
        $t->same(7, $summary['relationshipTargetReferenceCount']);
        $t->same(7, $summary['relationshipTargetPartCount']);
        $t->same(7, $summary['reachableTargetCount']);
        $t->same(1, $summary['missingOverrideCount']);
        $t->same(1, $summary['invalidPackagePartCount']);
        $t->same(2, $summary['invalidContentTypeCount']);
        $t->same(1, $summary['missingOverrideContentTypeCount']);
        $t->same(2, $summary['relationshipPartContentTypeCount']);
        $t->same(1, $summary['mediaContentTypeCount']);
        $t->same(1, $summary['embeddedPackageContentTypeCount']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.package',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml',
            'application/vnd.openxmlformats-package.core-properties+xml',
            $relationshipType,
            'application/xml',
            $imageType,
        ], $summary['contentTypeNames']);
        $t->same(['application/xml', $imageType], $summary['invalidContentTypes']);
        $t->same([$imageType], $summary['missingOverrideContentTypes']);
        $t->same([$relationshipType, 'application/xml'], $summary['relationshipPartContentTypes']);
        $t->same([$imageType], $summary['mediaContentTypes']);
        $t->same([$embeddedPackageType], $summary['embeddedPackageContentTypes']);
        $t->same([
            'invalid-relationship-content-type' => 1,
            'missing-in-package' => 1,
            'override-target-missing-part' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'invalid-relationship-content-type',
            'missing-in-package',
            'override-target-missing-part',
        ], $summary['issues']);
        $t->same([
            'invalid-relationship-content-type' => ['application/xml'],
            'missing-in-package' => [$imageType],
            'override-target-missing-part' => [$imageType],
        ], $summary['contentTypesByIssue']);

        $t->same(2, $contentTypes[$relationshipType]['packagePartCount']);
        $t->same(2, $contentTypes[$relationshipType]['relationshipPartCount']);
        $t->same(true, $contentTypes[$relationshipType]['valid']);
        $t->same(1, $contentTypes['application/xml']['packagePartCount']);
        $t->same(1, $contentTypes['application/xml']['relationshipPartCount']);
        $t->same(1, $contentTypes['application/xml']['invalidPackagePartCount']);
        $t->same(false, $contentTypes['application/xml']['valid']);
        $t->same(1, $contentTypes[$imageType]['packagePartCount']);
        $t->same(2, $contentTypes[$imageType]['relationshipTargetReferenceCount']);
        $t->same(1, $contentTypes[$imageType]['missingOverrideCount']);
        $t->same(false, $contentTypes[$imageType]['valid']);
        $t->same(1, $contentTypes[$embeddedPackageType]['packagePartCount']);
        $t->same(1, $contentTypes[$embeddedPackageType]['reachableTargetCount']);
    },
    'summarizes OPC default content type usage for package preflight handoff' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml, $documentRelationshipsXml): void {
        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'word/theme/theme1.thmx', 'data' => '<a:theme/>'],
            ['name' => 'word/media/source', 'data' => 'raw'],
        ]));

        $summary = $graph->contentTypeDefaultUsageSummary();
        $defaults = [];
        foreach ($summary['defaults'] as $default) {
            $defaults[$default['extension']] = $default;
        }
        $missingParts = [];
        foreach ($summary['missingParts'] as $part) {
            $missingParts[$part['partName']] = $part;
        }

        $t->same(false, $summary['valid']);
        $t->same(4, $summary['defaultCount']);
        $t->same(3, $summary['usedDefaultCount']);
        $t->same(1, $summary['unusedDefaultCount']);
        $t->same(10, $summary['packagePartCount']);
        $t->same(4, $summary['defaultResolvedPartCount']);
        $t->same(4, $summary['overrideResolvedPartCount']);
        $t->same(2, $summary['missingContentTypePartCount']);
        $t->same(2, $summary['relationshipPartDefaultResolvedCount']);
        $t->same(1, $summary['mediaDefaultResolvedCount']);
        $t->same(0, $summary['embeddedPackageDefaultResolvedCount']);
        $t->same(1, $summary['extensionlessMissingPartCount']);
        $t->same(['rels', 'xml', 'png', 'Jpeg'], $summary['defaultExtensions']);
        $t->same(['Jpeg'], $summary['unusedDefaultExtensions']);
        $t->same(['thmx'], $summary['missingExtensions']);
        $t->same([
            'missing-content-type' => 2,
            'missing-content-type-default' => 1,
            'missing-content-type-extension' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'missing-content-type',
            'missing-content-type-default',
            'missing-content-type-extension',
        ], $summary['issues']);

        $t->same([
            '/_rels/.rels',
            '/word/_rels/document.xml.rels',
        ], $defaults['rels']['packageParts']);
        $t->same(2, $defaults['rels']['relationshipPartCount']);
        $t->same(['/customXml/item1.xml'], $defaults['xml']['packageParts']);
        $t->same(['/word/media/review-image.PNG'], $defaults['png']['packageParts']);
        $t->same(1, $defaults['png']['mediaPartCount']);
        $t->same([], $defaults['Jpeg']['packageParts']);

        $t->same(null, $missingParts['/word/media/source']['extension']);
        $t->same(['missing-content-type', 'missing-content-type-extension'], $missingParts['/word/media/source']['issues']);
        $t->same('thmx', $missingParts['/word/theme/theme1.thmx']['extension']);
        $t->same(['missing-content-type', 'missing-content-type-default'], $missingParts['/word/theme/theme1.thmx']['issues']);
    },
    'summarizes OPC override content type usage for package preflight handoff' => static function (TestRunner $t): void {
        $relationshipContentType = 'application/vnd.openxmlformats-package.relationships+xml';
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/_rels/document.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/media/review.bin" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/media/missing.png" ContentType="image/png"/>
  <Override PartName="/word/_rels/missing-review.xml" ContentType="application/xml"/>
  <Override PartName="/[Content_Types].xml" ContentType="application/xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="../Word/Styles.XML"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'Word/Styles.XML', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/review.bin', 'data' => 'not relationship xml'],
        ]));

        $summary = $graph->contentTypeOverrideUsageSummary();
        $overrides = [];
        foreach ($summary['overrides'] as $override) {
            $overrides[$override['partName']] = $override;
        }

        $t->same(false, $summary['valid']);
        $t->same(7, $summary['overrideCount']);
        $t->same(5, $summary['usedOverrideCount']);
        $t->same(4, $summary['exactMatchCount']);
        $t->same(1, $summary['equivalentMatchCount']);
        $t->same(2, $summary['missingPartCount']);
        $t->same(4, $summary['invalidOverrideCount']);
        $t->same(1, $summary['relationshipPartOverrideCount']);
        $t->same(2, $summary['relationshipContentTypeOverrideCount']);
        $t->same(1, $summary['nonRelationshipPartRelationshipContentTypeCount']);
        $t->same(1, $summary['contentTypesItemOverrideCount']);
        $t->same(1, $summary['reservedRelationshipDirectoryOverrideCount']);
        $t->same([
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml' => 1,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml' => 1,
            $relationshipContentType => 2,
            'application/xml' => 2,
            'image/png' => 1,
        ], $summary['contentTypeCounts']);
        $t->same([
            'content-types-override-target' => 1,
            'override-target-missing-part' => 2,
            'relationship-content-type-on-non-relationship-part' => 1,
            'reserved-relationship-directory-override' => 1,
        ], $summary['issueCounts']);
        $t->same([
            'content-types-override-target',
            'override-target-missing-part',
            'relationship-content-type-on-non-relationship-part',
            'reserved-relationship-directory-override',
        ], $summary['issues']);
        $t->same([
            '/[Content_Types].xml',
            '/word/_rels/document.xml.rels',
            '/word/document.xml',
            '/word/media/review.bin',
        ], $summary['exactMatchParts']);
        $t->same(['/word/styles.xml'], $summary['equivalentMatchParts']);
        $t->same(['/word/_rels/missing-review.xml', '/word/media/missing.png'], $summary['missingParts']);
        $t->same([
            '/[Content_Types].xml',
            '/word/_rels/missing-review.xml',
            '/word/media/missing.png',
            '/word/media/review.bin',
        ], $summary['invalidParts']);
        $t->same(['/word/_rels/document.xml.rels'], $summary['relationshipPartOverrides']);
        $t->same(['/word/_rels/document.xml.rels', '/word/media/review.bin'], $summary['relationshipContentTypeOverrideParts']);
        $t->same(['/word/media/review.bin'], $summary['nonRelationshipPartRelationshipContentTypeParts']);
        $t->same(['/[Content_Types].xml'], $summary['contentTypesItemOverrides']);
        $t->same(['/word/_rels/missing-review.xml'], $summary['reservedRelationshipDirectoryOverrides']);

        $t->same('equivalent', $overrides['/word/styles.xml']['matchKind']);
        $t->same('/Word/Styles.XML', $overrides['/word/styles.xml']['packagePartName']);
        $t->same(true, $overrides['/word/styles.xml']['valid']);
        $t->same('exact', $overrides['/word/_rels/document.xml.rels']['matchKind']);
        $t->same(true, $overrides['/word/_rels/document.xml.rels']['relationshipPart']);
        $t->same('/word/document.xml', $overrides['/word/_rels/document.xml.rels']['relationshipSource']);
        $t->same(true, $overrides['/word/_rels/document.xml.rels']['relationshipSourceLoaded']);
        $t->same(true, $overrides['/word/_rels/document.xml.rels']['sourceExists']);
        $t->same('missing', $overrides['/word/_rels/missing-review.xml']['matchKind']);
        $t->same(['override-target-missing-part', 'reserved-relationship-directory-override'], $overrides['/word/_rels/missing-review.xml']['issues']);
        $t->same(['content-types-override-target'], $overrides['/[Content_Types].xml']['issues']);
    },
    'summarizes package-wide OPC package part relationship references for import review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/media/stale-review.png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdExternalReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
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
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
            ['name' => 'word/media/stale-review.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $inventory = [];
        foreach ($graph->packagePartReferenceInventory('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $part) {
            $inventory[$part['partName']] = $part;
        }

        $t->same([
            '/_rels/.rels',
            '/docProps/core.xml',
            '/word/_rels/comments.xml.rels',
            '/word/_rels/document.xml.rels',
            '/word/comments.xml',
            '/word/document.xml',
            '/word/media/comment.png',
            '/word/media/hero.png',
            '/word/media/missing.png',
            '/word/media/stale-review.png',
            '/word/styles.xml',
        ], array_keys($inventory));

        $t->same(true, $inventory['/word/document.xml']['exists']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $inventory['/word/document.xml']['contentType']);
        $t->same(1, $inventory['/word/document.xml']['directReferenceCount']);
        $t->same(1, $inventory['/word/document.xml']['reachableReferenceCount']);
        $t->same('/', $inventory['/word/document.xml']['directReferences'][0]['source']);
        $t->same('rIdDocument', $inventory['/word/document.xml']['directReferences'][0]['id']);
        $t->same(0, $inventory['/word/document.xml']['reachableReferences'][0]['depth']);
        $t->same(true, $inventory['/word/document.xml']['valid']);
        $t->same([], $inventory['/word/document.xml']['issues']);

        $t->same(1, $inventory['/docProps/core.xml']['directReferenceCount']);
        $t->same(0, $inventory['/docProps/core.xml']['reachableReferenceCount']);
        $t->same('rIdCore', $inventory['/docProps/core.xml']['directReferences'][0]['id']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $inventory['/docProps/core.xml']['contentType']);

        $t->same(1, $inventory['/word/comments.xml']['directReferenceCount']);
        $t->same(1, $inventory['/word/comments.xml']['reachableReferenceCount']);
        $t->same('/word/document.xml', $inventory['/word/comments.xml']['directReferences'][0]['source']);
        $t->same('rIdComments', $inventory['/word/comments.xml']['directReferences'][0]['id']);
        $t->same(1, $inventory['/word/comments.xml']['reachableReferences'][0]['depth']);

        $t->same(1, $inventory['/word/media/comment.png']['directReferenceCount']);
        $t->same(1, $inventory['/word/media/comment.png']['reachableReferenceCount']);
        $t->same('/word/comments.xml', $inventory['/word/media/comment.png']['directReferences'][0]['source']);
        $t->same('rIdCommentImage', $inventory['/word/media/comment.png']['directReferences'][0]['id']);
        $t->same(2, $inventory['/word/media/comment.png']['reachableReferences'][0]['depth']);

        $t->same(false, $inventory['/word/media/missing.png']['exists']);
        $t->same('image/png', $inventory['/word/media/missing.png']['contentType']);
        $t->same(1, $inventory['/word/media/missing.png']['directReferenceCount']);
        $t->same(1, $inventory['/word/media/missing.png']['reachableReferenceCount']);
        $t->same(false, $inventory['/word/media/missing.png']['directReferences'][0]['valid']);
        $t->same(['missing-in-package'], $inventory['/word/media/missing.png']['directReferences'][0]['issues']);
        $t->same(false, $inventory['/word/media/missing.png']['valid']);
        $t->same(['missing-in-package'], $inventory['/word/media/missing.png']['issues']);

        $t->same(true, $inventory['/word/media/stale-review.png']['exists']);
        $t->same(0, $inventory['/word/media/stale-review.png']['directReferenceCount']);
        $t->same(0, $inventory['/word/media/stale-review.png']['reachableReferenceCount']);
        $t->same([], $inventory['/word/media/stale-review.png']['directReferences']);
        $t->same([], $inventory['/word/media/stale-review.png']['reachableReferences']);
        $t->same(true, $inventory['/word/media/stale-review.png']['valid']);

        $t->same(true, $inventory['/_rels/.rels']['relationshipPart']);
        $t->same('/', $inventory['/_rels/.rels']['relationshipSource']);
        $t->same(true, $inventory['/_rels/.rels']['relationshipSourceLoaded']);
        $t->same(0, $inventory['/_rels/.rels']['directReferenceCount']);
        $t->same(true, $inventory['/word/_rels/document.xml.rels']['relationshipPart']);
        $t->same('/word/document.xml', $inventory['/word/_rels/document.xml.rels']['relationshipSource']);
        $t->same(true, $inventory['/word/_rels/document.xml.rels']['relationshipSourceLoaded']);
    },
    'summarizes OPC package part relationship coverage for importer review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/media/stale-review.png" ContentType="image/png"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdExternalReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="External"/>
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
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
            ['name' => 'word/media/stale-review.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $summary = $graph->packagePartRelationshipCoverageSummary(
            '/',
            OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE
        );
        $parts = [];
        foreach ($summary['parts'] as $part) {
            $parts[$part['partName']] = $part;
        }

        $t->same('/', $summary['source']);
        $t->same(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE, $summary['relationshipType']);
        $t->same(false, $summary['valid']);
        $t->same(11, $summary['inventoryPartCount']);
        $t->same(10, $summary['packagePartCount']);
        $t->same(3, $summary['relationshipPartCount']);
        $t->same(3, $summary['relationshipSourcePartCount']);
        $t->same(7, $summary['directReferencePartCount']);
        $t->same(6, $summary['reachableReferencePartCount']);
        $t->same(7, $summary['directReferenceCount']);
        $t->same(6, $summary['reachableReferenceCount']);
        $t->same(1, $summary['directOnlyPartCount']);
        $t->same(1, $summary['missingReferencedPartCount']);
        $t->same(1, $summary['unreferencedPackagePartCount']);
        $t->same(3, $summary['unreferencedRelationshipPartCount']);
        $t->same(1, $summary['invalidPartCount']);
        $t->same(1, $summary['externalDirectReferenceCount']);
        $t->same(1, $summary['externalReachableReferenceCount']);
        $t->same(0, $summary['invalidExternalReferenceCount']);
        $t->same(['missing-in-package' => 1], $summary['issueCounts']);
        $t->same(['missing-in-package'], $summary['issues']);
        $t->same([
            '/docProps/core.xml',
            '/word/comments.xml',
            '/word/document.xml',
            '/word/media/comment.png',
            '/word/media/hero.png',
            '/word/media/missing.png',
            '/word/styles.xml',
        ], $summary['referencedPartNames']);
        $t->same([
            '/word/comments.xml',
            '/word/document.xml',
            '/word/media/comment.png',
            '/word/media/hero.png',
            '/word/media/missing.png',
            '/word/styles.xml',
        ], $summary['reachablePartNames']);
        $t->same(['/docProps/core.xml'], $summary['directOnlyPartNames']);
        $t->same(['/word/media/missing.png'], $summary['missingReferencedPartNames']);
        $t->same(['/word/media/stale-review.png'], $summary['unreferencedPackagePartNames']);
        $t->same([
            '/_rels/.rels',
            '/word/_rels/comments.xml.rels',
            '/word/_rels/document.xml.rels',
        ], $summary['unreferencedRelationshipPartNames']);
        $t->same(['/word/media/missing.png'], $summary['invalidPartNames']);
        $t->same(['https://example.test/review'], $summary['externalTargets']);
        $t->same(['https://example.test/review'], $summary['reachableExternalTargets']);

        $t->same('direct-and-reachable', $parts['/word/document.xml']['coverage']);
        $t->same('direct-only', $parts['/docProps/core.xml']['coverage']);
        $t->same('missing-referenced-part', $parts['/word/media/missing.png']['coverage']);
        $t->same('unreferenced-package-part', $parts['/word/media/stale-review.png']['coverage']);
        $t->same('unreferenced-relationship-part', $parts['/_rels/.rels']['coverage']);
        $t->same(['missing-in-package'], $parts['/word/media/missing.png']['issues']);
    },
    'preflights fixed OPC content types item references' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/[Content_Types].xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdContentTypes" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="[Content_Types].xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
        ]));

        $overrides = [];
        foreach ($graph->preflightContentTypeOverrides() as $override) {
            $overrides[$override['partName']] = $override;
        }

        $targets = [];
        foreach ($graph->preflightTargetsForSource('/') as $target) {
            $targets[$target['id']] = $target;
        }

        $inventory = [];
        foreach ($graph->contentTypeInventory() as $contentType) {
            $inventory[$contentType['contentType']] = $contentType;
        }

        $consistency = $graph->preflightPackageConsistency();

        $t->same(true, $overrides['/[Content_Types].xml']['exists']);
        $t->same(false, $overrides['/[Content_Types].xml']['relationshipPart']);
        $t->same(false, $overrides['/[Content_Types].xml']['valid']);
        $t->same(['content-types-override-target'], $overrides['/[Content_Types].xml']['issues']);
        $t->same(true, $overrides['/word/document.xml']['valid']);

        $t->same('/[Content_Types].xml', $targets['rIdContentTypes']['target']);
        $t->same('/[Content_Types].xml', OpcPackagePath::stripQueryAndFragment($targets['rIdContentTypes']['target']));
        $t->same(true, $targets['rIdContentTypes']['exists']);
        $t->same('application/xml', $targets['rIdContentTypes']['contentType']);
        $t->same(false, $targets['rIdContentTypes']['relationshipPartTarget']);
        $t->same(false, $targets['rIdContentTypes']['valid']);
        $t->same(['targets-content-types-item'], $targets['rIdContentTypes']['issues']);
        $t->same(true, $targets['rIdDocument']['valid']);

        $t->same(false, $consistency['valid']);
        $t->same(false, $consistency['contentTypeOverridesValid']);
        $t->same(false, $consistency['relationshipTargetsValid']);
        $t->same(true, $consistency['packagePartsValid']);

        $t->same(['/word/document.xml'], $inventory['application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml']['parts']);
        $t->same(['/[Content_Types].xml'], $inventory['application/xml']['overrideParts']);
        $t->same(['/[Content_Types].xml'], $inventory['application/xml']['relationshipTargetParts']);
        $t->same(['content-types-override-target', 'targets-content-types-item'], $inventory['application/xml']['issues']);
    },
    'preflights fixed OPC content types item relationship sources' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $contentTypesRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdContentTypeAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/document.xml"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => '_rels/[Content_Types].xml.rels', 'data' => $contentTypesRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
        ]);

        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcRelationships::relationshipPartNameForSource('/[Content_Types].xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => new OpcRelationships('/[Content_Types].xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): bool => OpcRelationships::packageHasRelationshipsForSource($package, '/[Content_Types].xml'));

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same(['/_rels/.rels', '/_rels/[Content_Types].xml.rels'], array_keys($loads));
        $t->same(true, $loads['/_rels/.rels']['loaded']);
        $t->same(1, $loads['/_rels/.rels']['relationshipCount']);
        $t->same([], $loads['/_rels/.rels']['issues']);

        $contentTypeSourceLoad = $loads['/_rels/[Content_Types].xml.rels'];
        $t->same('/[Content_Types].xml', $contentTypeSourceLoad['relationshipSource']);
        $t->same(false, $contentTypeSourceLoad['relationshipSourceIsRelationshipPart']);
        $t->same(true, $contentTypeSourceLoad['sourceExists']);
        $t->same(false, $contentTypeSourceLoad['loaded']);
        $t->same('skipped', $contentTypeSourceLoad['loadAction']);
        $t->same('content-types-item-source', $contentTypeSourceLoad['loadReason']);
        $t->same(null, $contentTypeSourceLoad['relationshipCount']);
        $t->same(false, $contentTypeSourceLoad['valid']);
        $t->same(['content-types-item-source'], $contentTypeSourceLoad['issues']);

        $graph = OpcRelationshipGraph::fromPackage($package);
        $t->same(['/'], $graph->sourcePartNames());
        $t->same(false, $graph->hasRelationshipsForSource('/[Content_Types].xml'));

        $packageParts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $packageParts[$part['partName']] = $part;
        }

        $t->same(true, $packageParts['/_rels/[Content_Types].xml.rels']['relationshipPart']);
        $t->same('/[Content_Types].xml', $packageParts['/_rels/[Content_Types].xml.rels']['relationshipSource']);
        $t->same(false, $packageParts['/_rels/[Content_Types].xml.rels']['relationshipSourceIsRelationshipPart']);
        $t->same(false, $packageParts['/_rels/[Content_Types].xml.rels']['relationshipSourceLoaded']);
        $t->same(true, $packageParts['/_rels/[Content_Types].xml.rels']['sourceExists']);
        $t->same('skipped', $packageParts['/_rels/[Content_Types].xml.rels']['relationshipPartLoadAction']);
        $t->same('content-types-item-source', $packageParts['/_rels/[Content_Types].xml.rels']['relationshipPartLoadReason']);
        $t->same(false, $packageParts['/_rels/[Content_Types].xml.rels']['valid']);
        $t->same(['content-types-item-source'], $packageParts['/_rels/[Content_Types].xml.rels']['issues']);

        $consistency = $graph->preflightPackageConsistency();
        $t->same(false, $consistency['valid']);
        $t->same(false, $consistency['packagePartsValid']);
        $t->same(true, $consistency['contentTypeOverridesValid']);
        $t->same(true, $consistency['relationshipTargetsValid']);
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
    'summarizes DOCX officeDocument relationship readiness for importer handoff' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml, $documentRelationshipsXml, $footnotesRelationshipsXml): void {
        $validGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/footnotes.xml.rels', 'data' => $footnotesRelationshipsXml],
            ['name' => 'word/media/review-image.PNG', 'data' => 'PNG'],
            ['name' => 'word/media/footnote-source.png', 'data' => 'PNG'],
            ['name' => 'customXml/item1.xml', 'data' => '<review/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $valid = $validGraph->preflightOfficeDocumentRelationshipReadiness();
        $t->same(true, $valid['valid']);
        $t->same([], $valid['issues']);
        $t->same('/word/document.xml', $valid['documentPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $valid['documentContentType']);
        $t->same('/word/_rels/document.xml.rels', $valid['documentRelationshipPartName']);
        $t->same(true, $valid['documentRelationshipPartLoaded']);
        $t->same(1, $valid['officeDocument']['relationshipCount']);
        $t->same(true, $valid['officeDocument']['valid']);
        $t->same(true, $valid['relationshipClosure']['valid']);
        $t->same(3, $valid['relationshipClosure']['expandedSourceCount']);
        $t->same(6, $valid['relationshipClosure']['stopCount']);
        $t->same(2, $valid['relationshipClosure']['externalStopCount']);
        $t->same(4, $valid['relationshipClosure']['unloadedStopCount']);
        $t->same(5, $valid['relationshipRoleCount']);
        $t->same([
            'custom-xml' => 1,
            'footnotes' => 1,
            'hyperlink' => 1,
            'image' => 1,
            'styles' => 1,
        ], $valid['relationshipRoleCounts']);
        $t->same(0, $valid['invalidRelationshipRoleCount']);
        $t->same([], $valid['invalidRelationshipRoleIssues']);

        $roles = [];
        foreach ($valid['documentRelationshipRoles'] as $role) {
            $roles[$role['id']] = $role;
        }
        $t->same(['rIdStyles', 'rIdFootnotes', 'rIdImage', 'rIdCustomXml', 'rIdReviewerLink'], array_keys($roles));
        $t->same('/word/footnotes.xml', $roles['rIdFootnotes']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml', $roles['rIdFootnotes']['contentType']);
        $t->same(true, $roles['rIdReviewerLink']['external']);
        $t->same(true, $roles['rIdReviewerLink']['valid']);

        $invalidDocumentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMissingStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="missing-styles.xml"/>
  <Relationship Id="rIdUnsafeReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>
  <Relationship Id="rIdInternalBookmark" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#bookmark"/>
</Relationships>
XML;
        $invalidGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $invalidDocumentRelationshipsXml],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $invalid = $invalidGraph->preflightOfficeDocumentRelationshipReadiness();
        $t->same(false, $invalid['valid']);
        $t->same([
            'external-target-unsafe-scheme',
            'internal-hyperlink-target',
            'invalid-styles-content-type',
            'missing-in-package',
        ], $invalid['issues']);
        $t->same('/word/document.xml', $invalid['documentPart']);
        $t->same(true, $invalid['documentRelationshipPartLoaded']);
        $t->same(false, $invalid['relationshipClosure']['valid']);
        $t->same(['external-target-unsafe-scheme', 'missing-in-package'], $invalid['relationshipClosure']['issues']);
        $t->same(2, $invalid['relationshipClosure']['expandedSourceCount']);
        $t->same(3, $invalid['relationshipClosure']['stopCount']);
        $t->same(1, $invalid['relationshipClosure']['externalStopCount']);
        $t->same(1, $invalid['relationshipClosure']['missingStopCount']);
        $t->same(1, $invalid['relationshipClosure']['cycleStopCount']);
        $t->same(3, $invalid['relationshipRoleCount']);
        $t->same([
            'hyperlink' => 2,
            'styles' => 1,
        ], $invalid['relationshipRoleCounts']);
        $t->same(3, $invalid['invalidRelationshipRoleCount']);
        $t->same([
            'external-target-unsafe-scheme',
            'internal-hyperlink-target',
            'invalid-styles-content-type',
            'missing-in-package',
        ], $invalid['invalidRelationshipRoleIssues']);
        $t->same('rIdMissingStyles', $invalid['invalidRelationshipRoles'][0]['id']);
        $t->same(['missing-in-package', 'invalid-styles-content-type'], $invalid['invalidRelationshipRoles'][0]['issues']);
        $t->same('rIdUnsafeReview', $invalid['invalidRelationshipRoles'][1]['id']);
        $t->same(['external-target-unsafe-scheme'], $invalid['invalidRelationshipRoles'][1]['issues']);
        $t->same('rIdInternalBookmark', $invalid['invalidRelationshipRoles'][2]['id']);
        $t->same(['internal-hyperlink-target'], $invalid['invalidRelationshipRoles'][2]['issues']);
    },
    'preflights OPC core properties relationship cardinality and content type' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $validGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]));

        $valid = $validGraph->preflightCoreProperties();
        $t->same(1, $valid['relationshipCount']);
        $t->same(true, $valid['valid']);
        $t->same([], $valid['issues']);
        $t->same('rIdCore', $valid['relationships'][0]['id']);
        $t->same(OpcRelationshipGraph::CORE_PROPERTIES_RELATIONSHIP_TYPE, $valid['relationships'][0]['type']);
        $t->same('/docProps/core.xml', $valid['relationships'][0]['targetPart']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $valid['relationships'][0]['contentType']);
        $t->same(false, $valid['relationships'][0]['external']);
        $t->same(true, $valid['relationships'][0]['exists']);
        $t->same(true, $valid['relationships'][0]['valid']);
        $t->same([], $valid['relationships'][0]['issues']);

        $noCoreRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;
        $noCoreGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $noCoreRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
        ]));
        $noCore = $noCoreGraph->preflightCoreProperties();
        $t->same(0, $noCore['relationshipCount']);
        $t->same(true, $noCore['valid']);
        $t->same([], $noCore['issues']);
        $t->same([], $noCore['relationships']);

        $badContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/custom.xml" ContentType="application/xml"/>
</Types>
XML;
        $badRootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdCustomCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/custom.xml"/>
  <Relationship Id="rIdExternalCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="https://example.test/core.xml" TargetMode="External"/>
</Relationships>
XML;
        $badGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $badContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $badRootRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'docProps/custom.xml', 'data' => '<audit/>'],
        ]));

        $bad = $badGraph->preflightCoreProperties();
        $badById = [];
        foreach ($bad['relationships'] as $relationship) {
            $badById[$relationship['id']] = $relationship;
        }

        $t->same(3, $bad['relationshipCount']);
        $t->same(false, $bad['valid']);
        $t->same(['multiple-core-properties-relationships'], $bad['issues']);
        $t->same(['rIdCore', 'rIdCustomCore', 'rIdExternalCore'], array_keys($badById));
        $t->same(true, $badById['rIdCore']['valid']);
        $t->same([], $badById['rIdCore']['issues']);
        $t->same('/docProps/custom.xml', $badById['rIdCustomCore']['targetPart']);
        $t->same('application/xml', $badById['rIdCustomCore']['contentType']);
        $t->same(false, $badById['rIdCustomCore']['valid']);
        $t->same(['invalid-core-properties-content-type'], $badById['rIdCustomCore']['issues']);
        $t->same(true, $badById['rIdExternalCore']['external']);
        $t->same(null, $badById['rIdExternalCore']['targetPart']);
        $t->same('https', $badById['rIdExternalCore']['externalTargetScheme']);
        $t->same(false, $badById['rIdExternalCore']['valid']);
        $t->same(['external-core-properties-target'], $badById['rIdExternalCore']['issues']);
    },
    'preflights OPC document property relationship roles' => static function (TestRunner $t): void {
        $validContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/custom.xml" ContentType="application/vnd.openxmlformats-officedocument.custom-properties+xml"/>
</Types>
XML;
        $validPackageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdExtended" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
  <Relationship Id="rIdCustom" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="docProps/custom.xml"/>
</Relationships>
XML;
        $validGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $validContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $validPackageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'docProps/app.xml', 'data' => '<Properties/>'],
            ['name' => 'docProps/custom.xml', 'data' => '<Properties/>'],
        ]));

        $valid = $validGraph->preflightDocumentProperties();
        $t->same(true, $valid['valid']);
        $t->same(['core', 'extended', 'custom'], array_keys($valid['roles']));

        $t->same(1, $valid['roles']['core']['relationshipCount']);
        $t->same(OpcRelationshipGraph::CORE_PROPERTIES_RELATIONSHIP_TYPE, $valid['roles']['core']['relationshipType']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $valid['roles']['core']['expectedContentType']);
        $t->same(true, $valid['roles']['core']['valid']);
        $t->same([], $valid['roles']['core']['issues']);
        $t->same('rIdCore', $valid['roles']['core']['relationships'][0]['id']);
        $t->same('/docProps/core.xml', $valid['roles']['core']['relationships'][0]['targetPart']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $valid['roles']['core']['relationships'][0]['contentType']);
        $t->same(false, $valid['roles']['core']['relationships'][0]['external']);
        $t->same(true, $valid['roles']['core']['relationships'][0]['exists']);
        $t->same(true, $valid['roles']['core']['relationships'][0]['valid']);
        $t->same([], $valid['roles']['core']['relationships'][0]['issues']);

        $t->same(1, $valid['roles']['extended']['relationshipCount']);
        $t->same(OpcRelationshipGraph::EXTENDED_PROPERTIES_RELATIONSHIP_TYPE, $valid['roles']['extended']['relationshipType']);
        $t->same('application/vnd.openxmlformats-officedocument.extended-properties+xml', $valid['roles']['extended']['expectedContentType']);
        $t->same(true, $valid['roles']['extended']['valid']);
        $t->same([], $valid['roles']['extended']['issues']);
        $t->same('rIdExtended', $valid['roles']['extended']['relationships'][0]['id']);
        $t->same('/docProps/app.xml', $valid['roles']['extended']['relationships'][0]['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.extended-properties+xml', $valid['roles']['extended']['relationships'][0]['contentType']);
        $t->same(false, $valid['roles']['extended']['relationships'][0]['external']);
        $t->same(true, $valid['roles']['extended']['relationships'][0]['exists']);
        $t->same(true, $valid['roles']['extended']['relationships'][0]['valid']);
        $t->same([], $valid['roles']['extended']['relationships'][0]['issues']);

        $t->same(1, $valid['roles']['custom']['relationshipCount']);
        $t->same(OpcRelationshipGraph::CUSTOM_PROPERTIES_RELATIONSHIP_TYPE, $valid['roles']['custom']['relationshipType']);
        $t->same('application/vnd.openxmlformats-officedocument.custom-properties+xml', $valid['roles']['custom']['expectedContentType']);
        $t->same(true, $valid['roles']['custom']['valid']);
        $t->same([], $valid['roles']['custom']['issues']);
        $t->same('rIdCustom', $valid['roles']['custom']['relationships'][0]['id']);
        $t->same('/docProps/custom.xml', $valid['roles']['custom']['relationships'][0]['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.custom-properties+xml', $valid['roles']['custom']['relationships'][0]['contentType']);
        $t->same(false, $valid['roles']['custom']['relationships'][0]['external']);
        $t->same(true, $valid['roles']['custom']['relationships'][0]['exists']);
        $t->same(true, $valid['roles']['custom']['relationships'][0]['valid']);
        $t->same([], $valid['roles']['custom']['relationships'][0]['issues']);

        $badContentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/app-copy.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>
  <Override PartName="/docProps/custom.xml" ContentType="application/xml"/>
</Types>
XML;
        $badPackageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdExtended" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>
  <Relationship Id="rIdExtendedCopy" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app-copy.xml"/>
  <Relationship Id="rIdCustomWrong" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="docProps/custom.xml"/>
  <Relationship Id="rIdCustomExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/custom-properties" Target="https://example.test/wp-admin/custom-properties.xml" TargetMode="External"/>
</Relationships>
XML;
        $badGraph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $badContentTypesXml],
            ['name' => '_rels/.rels', 'data' => $badPackageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'docProps/app.xml', 'data' => '<Properties/>'],
            ['name' => 'docProps/app-copy.xml', 'data' => '<Properties/>'],
            ['name' => 'docProps/custom.xml', 'data' => '<Properties/>'],
        ]));

        $bad = $badGraph->preflightDocumentProperties();
        $badExtendedById = [];
        foreach ($bad['roles']['extended']['relationships'] as $relationship) {
            $badExtendedById[$relationship['id']] = $relationship;
        }

        $badCustomById = [];
        foreach ($bad['roles']['custom']['relationships'] as $relationship) {
            $badCustomById[$relationship['id']] = $relationship;
        }

        $t->same(false, $bad['valid']);
        $t->same(true, $bad['roles']['core']['valid']);
        $t->same(1, $bad['roles']['core']['relationshipCount']);
        $t->same([], $bad['roles']['core']['issues']);
        $t->same(2, $bad['roles']['extended']['relationshipCount']);
        $t->same(false, $bad['roles']['extended']['valid']);
        $t->same(['multiple-extended-properties-relationships'], $bad['roles']['extended']['issues']);
        $t->same(['rIdExtended', 'rIdExtendedCopy'], array_keys($badExtendedById));
        $t->same(true, $badExtendedById['rIdExtended']['valid']);
        $t->same([], $badExtendedById['rIdExtended']['issues']);
        $t->same('/docProps/app-copy.xml', $badExtendedById['rIdExtendedCopy']['targetPart']);
        $t->same(true, $badExtendedById['rIdExtendedCopy']['valid']);
        $t->same([], $badExtendedById['rIdExtendedCopy']['issues']);
        $t->same(2, $bad['roles']['custom']['relationshipCount']);
        $t->same(false, $bad['roles']['custom']['valid']);
        $t->same(['multiple-custom-properties-relationships'], $bad['roles']['custom']['issues']);
        $t->same(['rIdCustomWrong', 'rIdCustomExternal'], array_keys($badCustomById));
        $t->same('/docProps/custom.xml', $badCustomById['rIdCustomWrong']['targetPart']);
        $t->same('application/xml', $badCustomById['rIdCustomWrong']['contentType']);
        $t->same(false, $badCustomById['rIdCustomWrong']['valid']);
        $t->same(['invalid-custom-properties-content-type'], $badCustomById['rIdCustomWrong']['issues']);
        $t->same(true, $badCustomById['rIdCustomExternal']['external']);
        $t->same(null, $badCustomById['rIdCustomExternal']['targetPart']);
        $t->same('https', $badCustomById['rIdCustomExternal']['externalTargetScheme']);
        $t->same(false, $badCustomById['rIdCustomExternal']['valid']);
        $t->same(['external-custom-properties-target'], $badCustomById['rIdCustomExternal']['issues']);
    },
    'preflights WordprocessingML document relationship role content types' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/xml"/>
  <Override PartName="/word/footnotes.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.footnotes+xml"/>
  <Override PartName="/word/endnotes.xml" ContentType="application/xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/settings.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml"/>
  <Override PartName="/word/theme/theme1.xml" ContentType="application/vnd.openxmlformats-officedocument.theme+xml"/>
  <Override PartName="/word/fontTable.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml"/>
  <Override PartName="/word/webSettings.xml" ContentType="application/xml"/>
  <Override PartName="/word/header1.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml"/>
  <Override PartName="/word/footer1.xml" ContentType="application/xml"/>
  <Override PartName="/word/media/not-image.xml" ContentType="application/xml"/>
  <Override PartName="/customXml/wrong-type.xml" ContentType="text/plain"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdNumberingWrongType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
  <Relationship Id="rIdFootnotesExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footnotes" Target="https://example.test/footnotes.xml" TargetMode="External"/>
  <Relationship Id="rIdEndnotesWrongType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/endnotes" Target="endnotes.xml"/>
  <Relationship Id="rIdCommentsMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments-missing.xml"/>
  <Relationship Id="rIdSettings" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/settings" Target="settings.xml"/>
  <Relationship Id="rIdTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
  <Relationship Id="rIdFontTable" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/fontTable" Target="fontTable.xml"/>
  <Relationship Id="rIdWebSettingsWrongType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/webSettings" Target="webSettings.xml"/>
  <Relationship Id="rIdHeader" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="header1.xml"/>
  <Relationship Id="rIdFooterWrongType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/footer" Target="footer1.xml"/>
  <Relationship Id="rIdHeaderExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/header" Target="https://example.test/header.xml" TargetMode="External"/>
  <Relationship Id="rIdImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdImageWrongType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/not-image.xml"/>
  <Relationship Id="rIdLinkedImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="https://example.test/uploads/hero.png" TargetMode="External"/>
  <Relationship Id="rIdHyperlinkExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/source" TargetMode="External"/>
  <Relationship Id="rIdHyperlinkInternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#review-bookmark"/>
  <Relationship Id="rIdCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml"/>
  <Relationship Id="rIdCustomXmlWrongType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/wrong-type.xml"/>
  <Relationship Id="rIdCustomXmlExternal" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="https://example.test/customXml/item1.xml" TargetMode="External"/>
  <Relationship Id="rIdCustomXmlMissing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/missing.xml"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdCommentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles/>'],
            ['name' => 'word/numbering.xml', 'data' => '<w:numbering/>'],
            ['name' => 'word/footnotes.xml', 'data' => '<w:footnotes/>'],
            ['name' => 'word/endnotes.xml', 'data' => '<w:endnotes/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/settings.xml', 'data' => '<w:settings/>'],
            ['name' => 'word/theme/theme1.xml', 'data' => '<a:theme/>'],
            ['name' => 'word/fontTable.xml', 'data' => '<w:fonts/>'],
            ['name' => 'word/webSettings.xml', 'data' => '<w:webSettings/>'],
            ['name' => 'word/header1.xml', 'data' => '<w:hdr/>'],
            ['name' => 'word/footer1.xml', 'data' => '<w:ftr/>'],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/media/not-image.xml', 'data' => '<not-image/>'],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'customXml/wrong-type.xml', 'data' => '<audit/>'],
        ]));

        $roles = [];
        foreach ($graph->preflightWordprocessingDocumentRelationships('/word/document.xml') as $role) {
            $roles[$role['id']] = $role;
        }

        $t->same([
            'rIdStyles',
            'rIdNumberingWrongType',
            'rIdFootnotesExternal',
            'rIdEndnotesWrongType',
            'rIdCommentsMissing',
            'rIdSettings',
            'rIdTheme',
            'rIdFontTable',
            'rIdWebSettingsWrongType',
            'rIdHeader',
            'rIdFooterWrongType',
            'rIdHeaderExternal',
            'rIdImage',
            'rIdImageWrongType',
            'rIdLinkedImage',
            'rIdHyperlinkExternal',
            'rIdHyperlinkInternal',
            'rIdCustomXml',
            'rIdCustomXmlWrongType',
            'rIdCustomXmlExternal',
            'rIdCustomXmlMissing',
        ], array_keys($roles));

        $t->same('styles', $roles['rIdStyles']['role']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_STYLES_RELATIONSHIP_TYPE, $roles['rIdStyles']['type']);
        $t->same('/word/document.xml', $roles['rIdStyles']['source']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $roles['rIdStyles']['sourceContentType']);
        $t->same('/word/styles.xml', $roles['rIdStyles']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $roles['rIdStyles']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $roles['rIdStyles']['expectedContentType']);
        $t->same(null, $roles['rIdStyles']['expectedContentTypePrefix']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES, $roles['rIdStyles']['expectedSourceContentTypes']);
        $t->same(false, $roles['rIdStyles']['expectedExternal']);
        $t->same(false, $roles['rIdStyles']['external']);
        $t->same(true, $roles['rIdStyles']['exists']);
        $t->same(true, $roles['rIdStyles']['valid']);
        $t->same([], $roles['rIdStyles']['issues']);

        $t->same('numbering', $roles['rIdNumberingWrongType']['role']);
        $t->same('application/xml', $roles['rIdNumberingWrongType']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml', $roles['rIdNumberingWrongType']['expectedContentType']);
        $t->same(false, $roles['rIdNumberingWrongType']['valid']);
        $t->same(['invalid-numbering-content-type'], $roles['rIdNumberingWrongType']['issues']);

        $t->same('footnotes', $roles['rIdFootnotesExternal']['role']);
        $t->same(true, $roles['rIdFootnotesExternal']['external']);
        $t->same(null, $roles['rIdFootnotesExternal']['targetPart']);
        $t->same(null, $roles['rIdFootnotesExternal']['contentType']);
        $t->same(false, $roles['rIdFootnotesExternal']['expectedExternal']);
        $t->same('https', $roles['rIdFootnotesExternal']['externalTargetScheme']);
        $t->same(true, $roles['rIdFootnotesExternal']['externalTargetAllowed']);
        $t->same(false, $roles['rIdFootnotesExternal']['valid']);
        $t->same(['external-footnotes-target'], $roles['rIdFootnotesExternal']['issues']);

        $t->same('endnotes', $roles['rIdEndnotesWrongType']['role']);
        $t->same('/word/endnotes.xml', $roles['rIdEndnotesWrongType']['targetPart']);
        $t->same('application/xml', $roles['rIdEndnotesWrongType']['contentType']);
        $t->same(false, $roles['rIdEndnotesWrongType']['valid']);
        $t->same(['invalid-endnotes-content-type'], $roles['rIdEndnotesWrongType']['issues']);

        $t->same('comments', $roles['rIdCommentsMissing']['role']);
        $t->same('/word/comments-missing.xml', $roles['rIdCommentsMissing']['targetPart']);
        $t->same(false, $roles['rIdCommentsMissing']['exists']);
        $t->same('application/xml', $roles['rIdCommentsMissing']['contentType']);
        $t->same(false, $roles['rIdCommentsMissing']['valid']);
        $t->same(['missing-in-package', 'invalid-comments-content-type'], $roles['rIdCommentsMissing']['issues']);

        $t->same('settings', $roles['rIdSettings']['role']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml', $roles['rIdSettings']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.settings+xml', $roles['rIdSettings']['expectedContentType']);
        $t->same(true, $roles['rIdSettings']['valid']);
        $t->same([], $roles['rIdSettings']['issues']);

        $t->same('theme', $roles['rIdTheme']['role']);
        $t->same('/word/theme/theme1.xml', $roles['rIdTheme']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.theme+xml', $roles['rIdTheme']['expectedContentType']);
        $t->same(true, $roles['rIdTheme']['valid']);
        $t->same([], $roles['rIdTheme']['issues']);

        $t->same('font-table', $roles['rIdFontTable']['role']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_FONT_TABLE_RELATIONSHIP_TYPE, $roles['rIdFontTable']['type']);
        $t->same('/word/fontTable.xml', $roles['rIdFontTable']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml', $roles['rIdFontTable']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.fontTable+xml', $roles['rIdFontTable']['expectedContentType']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES, $roles['rIdFontTable']['expectedSourceContentTypes']);
        $t->same(false, $roles['rIdFontTable']['expectedExternal']);
        $t->same(true, $roles['rIdFontTable']['valid']);
        $t->same([], $roles['rIdFontTable']['issues']);

        $t->same('web-settings', $roles['rIdWebSettingsWrongType']['role']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_WEB_SETTINGS_RELATIONSHIP_TYPE, $roles['rIdWebSettingsWrongType']['type']);
        $t->same('/word/webSettings.xml', $roles['rIdWebSettingsWrongType']['targetPart']);
        $t->same('application/xml', $roles['rIdWebSettingsWrongType']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.webSettings+xml', $roles['rIdWebSettingsWrongType']['expectedContentType']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES, $roles['rIdWebSettingsWrongType']['expectedSourceContentTypes']);
        $t->same(false, $roles['rIdWebSettingsWrongType']['expectedExternal']);
        $t->same(false, $roles['rIdWebSettingsWrongType']['valid']);
        $t->same(['invalid-web-settings-content-type'], $roles['rIdWebSettingsWrongType']['issues']);

        $t->same('header', $roles['rIdHeader']['role']);
        $t->same('http://schemas.openxmlformats.org/officeDocument/2006/relationships/header', $roles['rIdHeader']['type']);
        $t->same('/word/header1.xml', $roles['rIdHeader']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml', $roles['rIdHeader']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.header+xml', $roles['rIdHeader']['expectedContentType']);
        $t->same(null, $roles['rIdHeader']['expectedContentTypePrefix']);
        $t->same(false, $roles['rIdHeader']['expectedExternal']);
        $t->same(false, $roles['rIdHeader']['external']);
        $t->same(true, $roles['rIdHeader']['exists']);
        $t->same(true, $roles['rIdHeader']['valid']);
        $t->same([], $roles['rIdHeader']['issues']);

        $t->same('footer', $roles['rIdFooterWrongType']['role']);
        $t->same('/word/footer1.xml', $roles['rIdFooterWrongType']['targetPart']);
        $t->same('application/xml', $roles['rIdFooterWrongType']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.footer+xml', $roles['rIdFooterWrongType']['expectedContentType']);
        $t->same(false, $roles['rIdFooterWrongType']['expectedExternal']);
        $t->same(false, $roles['rIdFooterWrongType']['valid']);
        $t->same(['invalid-footer-content-type'], $roles['rIdFooterWrongType']['issues']);

        $t->same('header', $roles['rIdHeaderExternal']['role']);
        $t->same(true, $roles['rIdHeaderExternal']['external']);
        $t->same(null, $roles['rIdHeaderExternal']['targetPart']);
        $t->same(null, $roles['rIdHeaderExternal']['contentType']);
        $t->same(false, $roles['rIdHeaderExternal']['expectedExternal']);
        $t->same('https', $roles['rIdHeaderExternal']['externalTargetScheme']);
        $t->same(true, $roles['rIdHeaderExternal']['externalTargetAllowed']);
        $t->same(false, $roles['rIdHeaderExternal']['valid']);
        $t->same(['external-header-target'], $roles['rIdHeaderExternal']['issues']);

        $t->same('image', $roles['rIdImage']['role']);
        $t->same('/word/media/hero.png', $roles['rIdImage']['targetPart']);
        $t->same('image/png', $roles['rIdImage']['contentType']);
        $t->same(null, $roles['rIdImage']['expectedContentType']);
        $t->same('image/', $roles['rIdImage']['expectedContentTypePrefix']);
        $t->same(null, $roles['rIdImage']['expectedSourceContentTypes']);
        $t->same(null, $roles['rIdImage']['expectedExternal']);
        $t->same(true, $roles['rIdImage']['valid']);
        $t->same([], $roles['rIdImage']['issues']);

        $t->same('image', $roles['rIdImageWrongType']['role']);
        $t->same('/word/media/not-image.xml', $roles['rIdImageWrongType']['targetPart']);
        $t->same('application/xml', $roles['rIdImageWrongType']['contentType']);
        $t->same(false, $roles['rIdImageWrongType']['valid']);
        $t->same(['invalid-image-content-type'], $roles['rIdImageWrongType']['issues']);

        $t->same('image', $roles['rIdLinkedImage']['role']);
        $t->same(true, $roles['rIdLinkedImage']['external']);
        $t->same(null, $roles['rIdLinkedImage']['targetPart']);
        $t->same(null, $roles['rIdLinkedImage']['contentType']);
        $t->same('image/', $roles['rIdLinkedImage']['expectedContentTypePrefix']);
        $t->same(true, $roles['rIdLinkedImage']['valid']);
        $t->same([], $roles['rIdLinkedImage']['issues']);

        $t->same('hyperlink', $roles['rIdHyperlinkExternal']['role']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_HYPERLINK_RELATIONSHIP_TYPE, $roles['rIdHyperlinkExternal']['type']);
        $t->same(true, $roles['rIdHyperlinkExternal']['external']);
        $t->same(true, $roles['rIdHyperlinkExternal']['expectedExternal']);
        $t->same('https', $roles['rIdHyperlinkExternal']['externalTargetScheme']);
        $t->same(true, $roles['rIdHyperlinkExternal']['valid']);
        $t->same([], $roles['rIdHyperlinkExternal']['issues']);

        $t->same('hyperlink', $roles['rIdHyperlinkInternal']['role']);
        $t->same(false, $roles['rIdHyperlinkInternal']['external']);
        $t->same('/word/document.xml', $roles['rIdHyperlinkInternal']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml', $roles['rIdHyperlinkInternal']['contentType']);
        $t->same(true, $roles['rIdHyperlinkInternal']['expectedExternal']);
        $t->same(false, $roles['rIdHyperlinkInternal']['valid']);
        $t->same(['internal-hyperlink-target'], $roles['rIdHyperlinkInternal']['issues']);

        $t->same('custom-xml', $roles['rIdCustomXml']['role']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_CUSTOM_XML_RELATIONSHIP_TYPE, $roles['rIdCustomXml']['type']);
        $t->same('/customXml/item1.xml', $roles['rIdCustomXml']['targetPart']);
        $t->same('application/xml', $roles['rIdCustomXml']['contentType']);
        $t->same('application/xml', $roles['rIdCustomXml']['expectedContentType']);
        $t->same(false, $roles['rIdCustomXml']['expectedExternal']);
        $t->same(false, $roles['rIdCustomXml']['external']);
        $t->same(true, $roles['rIdCustomXml']['exists']);
        $t->same(true, $roles['rIdCustomXml']['valid']);
        $t->same([], $roles['rIdCustomXml']['issues']);

        $t->same('custom-xml', $roles['rIdCustomXmlWrongType']['role']);
        $t->same('/customXml/wrong-type.xml', $roles['rIdCustomXmlWrongType']['targetPart']);
        $t->same('text/plain', $roles['rIdCustomXmlWrongType']['contentType']);
        $t->same(false, $roles['rIdCustomXmlWrongType']['valid']);
        $t->same(['invalid-custom-xml-content-type'], $roles['rIdCustomXmlWrongType']['issues']);

        $t->same('custom-xml', $roles['rIdCustomXmlExternal']['role']);
        $t->same(true, $roles['rIdCustomXmlExternal']['external']);
        $t->same(null, $roles['rIdCustomXmlExternal']['targetPart']);
        $t->same(null, $roles['rIdCustomXmlExternal']['contentType']);
        $t->same(false, $roles['rIdCustomXmlExternal']['expectedExternal']);
        $t->same('https', $roles['rIdCustomXmlExternal']['externalTargetScheme']);
        $t->same(false, $roles['rIdCustomXmlExternal']['valid']);
        $t->same(['external-custom-xml-target'], $roles['rIdCustomXmlExternal']['issues']);

        $t->same('custom-xml', $roles['rIdCustomXmlMissing']['role']);
        $t->same('/customXml/missing.xml', $roles['rIdCustomXmlMissing']['targetPart']);
        $t->same('application/xml', $roles['rIdCustomXmlMissing']['contentType']);
        $t->same(false, $roles['rIdCustomXmlMissing']['exists']);
        $t->same(false, $roles['rIdCustomXmlMissing']['valid']);
        $t->same(['missing-in-package'], $roles['rIdCustomXmlMissing']['issues']);

        $basePreflight = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $basePreflight[$target['id']] = $target;
        }
        $t->same(true, isset($basePreflight['rIdCustomXml']));
        $t->same(true, isset($roles['rIdCustomXml']));
        $t->same(true, $basePreflight['rIdHyperlinkInternal']['valid']);
        $t->same([], $basePreflight['rIdHyperlinkInternal']['issues']);

        $commentRoles = [];
        foreach ($graph->preflightWordprocessingDocumentRelationships('/word/comments.xml') as $role) {
            $commentRoles[$role['id']] = $role;
        }

        $t->same(['rIdCommentStyles', 'rIdCommentImage'], array_keys($commentRoles));
        $t->same('styles', $commentRoles['rIdCommentStyles']['role']);
        $t->same('/word/comments.xml', $commentRoles['rIdCommentStyles']['source']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', $commentRoles['rIdCommentStyles']['sourceContentType']);
        $t->same('/word/styles.xml', $commentRoles['rIdCommentStyles']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml', $commentRoles['rIdCommentStyles']['contentType']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES, $commentRoles['rIdCommentStyles']['expectedSourceContentTypes']);
        $t->same(false, $commentRoles['rIdCommentStyles']['valid']);
        $t->same(['invalid-styles-source-content-type'], $commentRoles['rIdCommentStyles']['issues']);

        $t->same('image', $commentRoles['rIdCommentImage']['role']);
        $t->same('/word/comments.xml', $commentRoles['rIdCommentImage']['source']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', $commentRoles['rIdCommentImage']['sourceContentType']);
        $t->same('/word/media/hero.png', $commentRoles['rIdCommentImage']['targetPart']);
        $t->same('image/png', $commentRoles['rIdCommentImage']['contentType']);
        $t->same(null, $commentRoles['rIdCommentImage']['expectedSourceContentTypes']);
        $t->same(true, $commentRoles['rIdCommentImage']['valid']);
        $t->same([], $commentRoles['rIdCommentImage']['issues']);
    },
    'summarizes package-wide OPC relationship role target policies for importer review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/word/numbering.xml" ContentType="application/xml"/>
  <Override PartName="/word/media/not-image.xml" ContentType="application/xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/embeddings/review.xlsx" ContentType="application/vnd.openxmlformats-officedocument.package"/>
  <Override PartName="/word/embeddings/oleObject1.bin" ContentType="application/vnd.openxmlformats-officedocument.oleObject"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
  <Override PartName="/EncryptedPackage" ContentType="application/octet-stream"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
  <Relationship Id="rIdEncrypted" Type="http://schemas.openxmlformats.org/package/2006/relationships/encrypted-package" Target="EncryptedPackage"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdNumbering" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/numbering" Target="numbering.xml"/>
  <Relationship Id="rIdHero" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/hero.png"/>
  <Relationship Id="rIdNotImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/not-image.xml"/>
  <Relationship Id="rIdBookmark" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="#bookmark"/>
  <Relationship Id="rIdChart" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/chart" Target="charts/chart1.xml"/>
  <Relationship Id="rIdEmbeddedPackage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/package" Target="embeddings/review.xlsx"/>
  <Relationship Id="rIdEmbeddedObject" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/oleObject" Target="embeddings/oleObject1.bin"/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles/>'],
            ['name' => 'word/numbering.xml', 'data' => '<w:numbering/>'],
            ['name' => 'word/media/hero.png', 'data' => 'PNG'],
            ['name' => 'word/media/not-image.xml', 'data' => '<not-image/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/charts/chart1.xml', 'data' => '<c:chart/>'],
            ['name' => 'word/embeddings/review.xlsx', 'data' => 'PK'],
            ['name' => 'word/embeddings/oleObject1.bin', 'data' => 'OLE'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'EncryptedPackage', 'data' => 'encrypted'],
        ]));

        $roleTargets = $graph->preflightRelationshipRoleTargets();
        $byKey = [];
        foreach ($roleTargets['relationships'] as $relationship) {
            $byKey[$relationship['source'] . ':' . $relationship['id']] = $relationship;
        }

        $t->same(false, $roleTargets['valid']);
        $t->same(null, $roleTargets['source']);
        $t->same(13, $roleTargets['roleTargetCount']);
        $t->same(8, $roleTargets['validRoleTargetCount']);
        $t->same(5, $roleTargets['invalidRoleTargetCount']);
        $t->same([
            'chart' => 1,
            'comments' => 1,
            'core-properties' => 1,
            'embedded-object' => 1,
            'embedded-package' => 1,
            'encrypted-package' => 1,
            'hyperlink' => 1,
            'image' => 2,
            'numbering' => 1,
            'office-document' => 1,
            'styles' => 2,
        ], $roleTargets['roleCounts']);
        $t->same([
            'internal-hyperlink-target' => 1,
            'invalid-encrypted-package-content-type' => 1,
            'invalid-image-content-type' => 1,
            'invalid-numbering-content-type' => 1,
            'invalid-styles-source-content-type' => 1,
        ], $roleTargets['issueCounts']);
        $t->same([
            'internal-hyperlink-target',
            'invalid-encrypted-package-content-type',
            'invalid-image-content-type',
            'invalid-numbering-content-type',
            'invalid-styles-source-content-type',
        ], $roleTargets['issues']);

        $t->same('office-document', $byKey['/:rIdDocument']['role']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES, $byKey['/:rIdDocument']['expectedContentTypes']);
        $t->same(false, $byKey['/:rIdDocument']['expectedExternal']);
        $t->same('/word/document.xml', $byKey['/:rIdDocument']['targetPart']);
        $t->same(true, $byKey['/:rIdDocument']['valid']);

        $t->same('core-properties', $byKey['/:rIdCore']['role']);
        $t->same('application/vnd.openxmlformats-package.core-properties+xml', $byKey['/:rIdCore']['expectedContentType']);
        $t->same('/', $byKey['/:rIdCore']['expectedSource']);
        $t->same(true, $byKey['/:rIdCore']['valid']);

        $t->same('encrypted-package', $byKey['/:rIdEncrypted']['role']);
        $t->same('application/vnd.openxmlformats-package.encrypted-package', $byKey['/:rIdEncrypted']['expectedContentType']);
        $t->same(true, $byKey['/:rIdEncrypted']['sourceAllowed']);
        $t->same('application/octet-stream', $byKey['/:rIdEncrypted']['contentType']);
        $t->same(false, $byKey['/:rIdEncrypted']['valid']);
        $t->same(['invalid-encrypted-package-content-type'], $byKey['/:rIdEncrypted']['issues']);

        $t->same('numbering', $byKey['/word/document.xml:rIdNumbering']['role']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.numbering+xml', $byKey['/word/document.xml:rIdNumbering']['expectedContentType']);
        $t->same('application/xml', $byKey['/word/document.xml:rIdNumbering']['contentType']);
        $t->same(false, $byKey['/word/document.xml:rIdNumbering']['valid']);
        $t->same(['invalid-numbering-content-type'], $byKey['/word/document.xml:rIdNumbering']['issues']);

        $t->same('image', $byKey['/word/document.xml:rIdNotImage']['role']);
        $t->same('image/', $byKey['/word/document.xml:rIdNotImage']['expectedContentTypePrefix']);
        $t->same('application/xml', $byKey['/word/document.xml:rIdNotImage']['contentType']);
        $t->same(false, $byKey['/word/document.xml:rIdNotImage']['valid']);
        $t->same(['invalid-image-content-type'], $byKey['/word/document.xml:rIdNotImage']['issues']);

        $t->same('hyperlink', $byKey['/word/document.xml:rIdBookmark']['role']);
        $t->same(true, $byKey['/word/document.xml:rIdBookmark']['expectedExternal']);
        $t->same(false, $byKey['/word/document.xml:rIdBookmark']['external']);
        $t->same(false, $byKey['/word/document.xml:rIdBookmark']['valid']);
        $t->same(['internal-hyperlink-target'], $byKey['/word/document.xml:rIdBookmark']['issues']);

        $t->same('embedded-package', $byKey['/word/document.xml:rIdEmbeddedPackage']['role']);
        $t->same('application/vnd.openxmlformats-officedocument.package', $byKey['/word/document.xml:rIdEmbeddedPackage']['expectedContentType']);
        $t->same('/word/embeddings/review.xlsx', $byKey['/word/document.xml:rIdEmbeddedPackage']['targetPart']);
        $t->same(true, $byKey['/word/document.xml:rIdEmbeddedPackage']['valid']);

        $t->same('embedded-object', $byKey['/word/document.xml:rIdEmbeddedObject']['role']);
        $t->same('application/vnd.openxmlformats-officedocument.oleObject', $byKey['/word/document.xml:rIdEmbeddedObject']['expectedContentType']);
        $t->same('/word/embeddings/oleObject1.bin', $byKey['/word/document.xml:rIdEmbeddedObject']['targetPart']);
        $t->same(true, $byKey['/word/document.xml:rIdEmbeddedObject']['valid']);

        $t->same('styles', $byKey['/word/comments.xml:rIdCommentStyles']['role']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', $byKey['/word/comments.xml:rIdCommentStyles']['sourceContentType']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES, $byKey['/word/comments.xml:rIdCommentStyles']['expectedSourceContentTypes']);
        $t->same(false, $byKey['/word/comments.xml:rIdCommentStyles']['valid']);
        $t->same(['invalid-styles-source-content-type'], $byKey['/word/comments.xml:rIdCommentStyles']['issues']);

        $documentOnly = $graph->preflightRelationshipRoleTargets('/word/document.xml');
        $t->same('/word/document.xml', $documentOnly['source']);
        $t->same(9, $documentOnly['roleTargetCount']);
        $t->same(3, $documentOnly['invalidRoleTargetCount']);
        $t->same([
            'rIdBookmark',
            'rIdChart',
            'rIdComments',
            'rIdEmbeddedObject',
            'rIdEmbeddedPackage',
            'rIdHero',
            'rIdNotImage',
            'rIdNumbering',
            'rIdStyles',
        ], array_column($documentOnly['relationships'], 'id'));
    },
    'preflights WordprocessingML custom XML properties relationship roles' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/customXml/itemProps1.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
  <Override PartName="/customXml/wrong-props.xml" ContentType="application/xml"/>
  <Override PartName="/customXml/source-wrong-type.bin" ContentType="application/octet-stream"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml"/>
  <Relationship Id="rIdCustomXmlWrongSource" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/source-wrong-type.bin"/>
</Relationships>
XML;

        $customXmlRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdItemProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps1.xml"/>
  <Relationship Id="rIdWrongItemProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="wrong-props.xml"/>
  <Relationship Id="rIdExternalItemProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="https://example.test/itemProps.xml" TargetMode="External"/>
  <Relationship Id="rIdMissingItemProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="missing-props.xml"/>
</Relationships>
XML;

        $wrongSourceRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdWrongSourceProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps1.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'customXml/_rels/item1.xml.rels', 'data' => $customXmlRelationshipsXml],
            ['name' => 'customXml/itemProps1.xml', 'data' => '<ds:datastoreItem xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml"/>'],
            ['name' => 'customXml/wrong-props.xml', 'data' => '<audit/>'],
            ['name' => 'customXml/source-wrong-type.bin', 'data' => 'binary-ish'],
            ['name' => 'customXml/_rels/source-wrong-type.bin.rels', 'data' => $wrongSourceRelationshipsXml],
        ]));

        $documentRoles = [];
        foreach ($graph->preflightWordprocessingDocumentRelationships('/word/document.xml') as $role) {
            $documentRoles[$role['id']] = $role;
        }

        $customXmlRoles = [];
        foreach ($graph->preflightWordprocessingDocumentRelationships('/customXml/item1.xml') as $role) {
            $customXmlRoles[$role['id']] = $role;
        }

        $wrongSourceRoles = [];
        foreach ($graph->preflightWordprocessingDocumentRelationships('/customXml/source-wrong-type.bin') as $role) {
            $wrongSourceRoles[$role['id']] = $role;
        }

        $t->same('custom-xml', $documentRoles['rIdCustomXml']['role']);
        $t->same('/customXml/item1.xml', $documentRoles['rIdCustomXml']['targetPart']);
        $t->same('application/xml', $documentRoles['rIdCustomXml']['contentType']);
        $t->same(true, $documentRoles['rIdCustomXml']['valid']);

        $t->same([
            'rIdItemProps',
            'rIdWrongItemProps',
            'rIdExternalItemProps',
            'rIdMissingItemProps',
        ], array_keys($customXmlRoles));

        $t->same('custom-xml-properties', $customXmlRoles['rIdItemProps']['role']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_CUSTOM_XML_PROPERTIES_RELATIONSHIP_TYPE, $customXmlRoles['rIdItemProps']['type']);
        $t->same('/customXml/item1.xml', $customXmlRoles['rIdItemProps']['source']);
        $t->same('application/xml', $customXmlRoles['rIdItemProps']['sourceContentType']);
        $t->same('/customXml/itemProps1.xml', $customXmlRoles['rIdItemProps']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.customXmlProperties+xml', $customXmlRoles['rIdItemProps']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.customXmlProperties+xml', $customXmlRoles['rIdItemProps']['expectedContentType']);
        $t->same(['application/xml'], $customXmlRoles['rIdItemProps']['expectedSourceContentTypes']);
        $t->same(false, $customXmlRoles['rIdItemProps']['expectedExternal']);
        $t->same(false, $customXmlRoles['rIdItemProps']['external']);
        $t->same(true, $customXmlRoles['rIdItemProps']['exists']);
        $t->same(true, $customXmlRoles['rIdItemProps']['valid']);
        $t->same([], $customXmlRoles['rIdItemProps']['issues']);

        $t->same('custom-xml-properties', $customXmlRoles['rIdWrongItemProps']['role']);
        $t->same('/customXml/wrong-props.xml', $customXmlRoles['rIdWrongItemProps']['targetPart']);
        $t->same('application/xml', $customXmlRoles['rIdWrongItemProps']['contentType']);
        $t->same(false, $customXmlRoles['rIdWrongItemProps']['valid']);
        $t->same(['invalid-custom-xml-properties-content-type'], $customXmlRoles['rIdWrongItemProps']['issues']);

        $t->same('custom-xml-properties', $customXmlRoles['rIdExternalItemProps']['role']);
        $t->same(true, $customXmlRoles['rIdExternalItemProps']['external']);
        $t->same(null, $customXmlRoles['rIdExternalItemProps']['targetPart']);
        $t->same(null, $customXmlRoles['rIdExternalItemProps']['contentType']);
        $t->same(false, $customXmlRoles['rIdExternalItemProps']['expectedExternal']);
        $t->same(false, $customXmlRoles['rIdExternalItemProps']['valid']);
        $t->same(['external-custom-xml-properties-target'], $customXmlRoles['rIdExternalItemProps']['issues']);

        $t->same('custom-xml-properties', $customXmlRoles['rIdMissingItemProps']['role']);
        $t->same('/customXml/missing-props.xml', $customXmlRoles['rIdMissingItemProps']['targetPart']);
        $t->same('application/xml', $customXmlRoles['rIdMissingItemProps']['contentType']);
        $t->same(false, $customXmlRoles['rIdMissingItemProps']['exists']);
        $t->same(false, $customXmlRoles['rIdMissingItemProps']['valid']);
        $t->same(['missing-in-package', 'invalid-custom-xml-properties-content-type'], $customXmlRoles['rIdMissingItemProps']['issues']);

        $t->same('custom-xml-properties', $wrongSourceRoles['rIdWrongSourceProps']['role']);
        $t->same('application/octet-stream', $wrongSourceRoles['rIdWrongSourceProps']['sourceContentType']);
        $t->same('/customXml/itemProps1.xml', $wrongSourceRoles['rIdWrongSourceProps']['targetPart']);
        $t->same(false, $wrongSourceRoles['rIdWrongSourceProps']['valid']);
        $t->same(['invalid-custom-xml-properties-source-content-type'], $wrongSourceRoles['rIdWrongSourceProps']['issues']);
    },
    'preflights WordprocessingML custom XML properties payload metadata' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/customXml/itemProps1.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
  <Override PartName="/customXml/itemProps-invalid.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
  <Override PartName="/customXml/itemProps-wrong-root.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
  <Override PartName="/customXml/itemProps-malformed.xml" ContentType="application/vnd.openxmlformats-officedocument.customXmlProperties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCustomXml" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="../customXml/item1.xml"/>
</Relationships>
XML;

        $customXmlRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdItemProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps1.xml"/>
  <Relationship Id="rIdInvalidItemProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps-invalid.xml"/>
  <Relationship Id="rIdWrongRootProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps-wrong-root.xml"/>
  <Relationship Id="rIdMalformedProps" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXmlProps" Target="itemProps-malformed.xml"/>
</Relationships>
XML;

        $validPropertiesXml = <<<'XML'
<ds:datastoreItem xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml" ds:itemID="{11111111-2222-3333-4444-555555555555}">
  <ds:schemaRefs>
    <ds:schemaRef ds:uri="urn:wordpress:review-packet"/>
    <ds:schemaRef ds:uri="https://example.test/schema/review.xsd"/>
  </ds:schemaRefs>
</ds:datastoreItem>
XML;

        $invalidPropertiesXml = <<<'XML'
<ds:datastoreItem xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml" ds:itemID="not-a-guid">
  <ds:schemaRefs>
    <ds:schemaRef/>
    <ds:schemaRef ds:uri="bad uri"/>
  </ds:schemaRefs>
</ds:datastoreItem>
XML;

        $wrongRootPropertiesXml = <<<'XML'
<ds:wrongRoot xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml" ds:itemID="{11111111-2222-3333-4444-555555555555}"/>
XML;

        $malformedPropertiesXml = '<ds:datastoreItem xmlns:ds="http://schemas.openxmlformats.org/officeDocument/2006/customXml" ds:itemID="{11111111-2222-3333-4444-555555555555}">';

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'customXml/item1.xml', 'data' => '<audit/>'],
            ['name' => 'customXml/_rels/item1.xml.rels', 'data' => $customXmlRelationshipsXml],
            ['name' => 'customXml/itemProps1.xml', 'data' => $validPropertiesXml],
            ['name' => 'customXml/itemProps-invalid.xml', 'data' => $invalidPropertiesXml],
            ['name' => 'customXml/itemProps-wrong-root.xml', 'data' => $wrongRootPropertiesXml],
            ['name' => 'customXml/itemProps-malformed.xml', 'data' => $malformedPropertiesXml],
        ]));

        $payloads = [];
        foreach ($graph->preflightCustomXmlProperties('/customXml/item1.xml') as $payload) {
            $payloads[$payload['id']] = $payload;
        }

        $t->same([
            'rIdItemProps',
            'rIdInvalidItemProps',
            'rIdWrongRootProps',
            'rIdMalformedProps',
        ], array_keys($payloads));

        $t->same('/customXml/item1.xml', $payloads['rIdItemProps']['source']);
        $t->same('application/xml', $payloads['rIdItemProps']['sourceContentType']);
        $t->same('custom-xml-properties', $payloads['rIdItemProps']['role']);
        $t->same('/customXml/itemProps1.xml', $payloads['rIdItemProps']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.customXmlProperties+xml', $payloads['rIdItemProps']['contentType']);
        $t->same('datastoreItem', $payloads['rIdItemProps']['rootName']);
        $t->same(OpcRelationshipGraph::CUSTOM_XML_DATA_STORE_NAMESPACE_URI, $payloads['rIdItemProps']['rootNamespace']);
        $t->same('{11111111-2222-3333-4444-555555555555}', $payloads['rIdItemProps']['itemId']);
        $t->same(true, $payloads['rIdItemProps']['itemIdValid']);
        $t->same(2, $payloads['rIdItemProps']['schemaRefCount']);
        $t->same(['urn:wordpress:review-packet', 'https://example.test/schema/review.xsd'], $payloads['rIdItemProps']['schemaRefUris']);
        $t->same(null, $payloads['rIdItemProps']['parseError']);
        $t->same(true, $payloads['rIdItemProps']['valid']);
        $t->same([], $payloads['rIdItemProps']['issues']);

        $t->same('/customXml/itemProps-invalid.xml', $payloads['rIdInvalidItemProps']['targetPart']);
        $t->same('not-a-guid', $payloads['rIdInvalidItemProps']['itemId']);
        $t->same(false, $payloads['rIdInvalidItemProps']['itemIdValid']);
        $t->same(2, $payloads['rIdInvalidItemProps']['schemaRefCount']);
        $t->same([], $payloads['rIdInvalidItemProps']['schemaRefUris']);
        $t->same(false, $payloads['rIdInvalidItemProps']['valid']);
        $t->same([
            'invalid-custom-xml-item-id',
            'missing-custom-xml-schema-ref-uri',
            'invalid-custom-xml-schema-ref-uri',
        ], $payloads['rIdInvalidItemProps']['issues']);

        $t->same('/customXml/itemProps-wrong-root.xml', $payloads['rIdWrongRootProps']['targetPart']);
        $t->same('wrongRoot', $payloads['rIdWrongRootProps']['rootName']);
        $t->same(OpcRelationshipGraph::CUSTOM_XML_DATA_STORE_NAMESPACE_URI, $payloads['rIdWrongRootProps']['rootNamespace']);
        $t->same(null, $payloads['rIdWrongRootProps']['itemId']);
        $t->same(null, $payloads['rIdWrongRootProps']['itemIdValid']);
        $t->same(false, $payloads['rIdWrongRootProps']['valid']);
        $t->same(['missing-custom-xml-datastore-item-root'], $payloads['rIdWrongRootProps']['issues']);

        $t->same('/customXml/itemProps-malformed.xml', $payloads['rIdMalformedProps']['targetPart']);
        $t->same(null, $payloads['rIdMalformedProps']['rootName']);
        $t->same(null, $payloads['rIdMalformedProps']['itemId']);
        $t->same(null, $payloads['rIdMalformedProps']['itemIdValid']);
        $t->same(true, str_contains((string) $payloads['rIdMalformedProps']['parseError'], 'OPC custom XML properties XML'));
        $t->same(false, $payloads['rIdMalformedProps']['valid']);
        $t->same(['custom-xml-properties-parse-error'], $payloads['rIdMalformedProps']['issues']);

        $allPayloads = [];
        foreach ($graph->preflightCustomXmlProperties() as $payload) {
            $allPayloads[$payload['source'] . ':' . $payload['id']] = $payload;
        }
        $t->same(true, isset($allPayloads['/customXml/item1.xml:rIdItemProps']));
    },
    'preflights DOCX reader supplemental relationship role content types' => static function (TestRunner $t): void {
        $commentsExtendedType = OpcRelationshipGraph::WORDPROCESSING_COMMENTS_EXTENDED_RELATIONSHIP_TYPE;
        $glossaryType = OpcRelationshipGraph::WORDPROCESSING_GLOSSARY_DOCUMENT_RELATIONSHIP_TYPE;
        $altChunkType = OpcRelationshipGraph::WORDPROCESSING_ALTERNATIVE_FORMAT_IMPORT_RELATIONSHIP_TYPE;
        $chartType = OpcRelationshipGraph::DRAWINGML_CHART_RELATIONSHIP_TYPE;
        $diagramDataType = OpcRelationshipGraph::DRAWINGML_DIAGRAM_DATA_RELATIONSHIP_TYPE;
        $diagramLayoutType = OpcRelationshipGraph::DRAWINGML_DIAGRAM_LAYOUT_RELATIONSHIP_TYPE;
        $diagramQuickStyleType = OpcRelationshipGraph::DRAWINGML_DIAGRAM_QUICK_STYLE_RELATIONSHIP_TYPE;
        $diagramColorsType = OpcRelationshipGraph::DRAWINGML_DIAGRAM_COLORS_RELATIONSHIP_TYPE;

        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/commentsExtended.xml" ContentType="application/vnd.ms-word.commentsExt+xml"/>
  <Override PartName="/word/glossary/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml"/>
  <Override PartName="/word/chunks/review.html" ContentType="text/html"/>
  <Override PartName="/word/chunks/plain-review.txt" ContentType="text/plain; charset=utf-8"/>
  <Override PartName="/word/chunks/review.xhtml" ContentType="application/xhtml+xml"/>
  <Override PartName="/word/chunks/source.rtf" ContentType="application/rtf"/>
  <Override PartName="/word/charts/chart1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.chart+xml"/>
  <Override PartName="/word/diagrams/data1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramData+xml"/>
  <Override PartName="/word/diagrams/layout1.xml" ContentType="application/xml"/>
  <Override PartName="/word/diagrams/quickStyle1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramStyle+xml"/>
  <Override PartName="/word/diagrams/colors1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawingml.diagramColors+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentsExtended" Type="{$commentsExtendedType}" Target="commentsExtended.xml"/>
  <Relationship Id="rIdGlossary" Type="{$glossaryType}" Target="glossary/document.xml"/>
  <Relationship Id="rIdHtmlChunk" Type="{$altChunkType}" Target="chunks/review.html"/>
  <Relationship Id="rIdPlainTextChunk" Type="{$altChunkType}" Target="chunks/plain-review.txt"/>
  <Relationship Id="rIdXhtmlChunk" Type="{$altChunkType}" Target="chunks/review.xhtml"/>
  <Relationship Id="rIdUnsupportedChunk" Type="{$altChunkType}" Target="chunks/source.rtf"/>
  <Relationship Id="rIdExternalChunk" Type="{$altChunkType}" Target="https://example.test/review.html" TargetMode="External"/>
  <Relationship Id="rIdChart" Type="{$chartType}" Target="charts/chart1.xml"/>
  <Relationship Id="rIdDiagramData" Type="{$diagramDataType}" Target="diagrams/data1.xml"/>
  <Relationship Id="rIdDiagramLayoutWrongType" Type="{$diagramLayoutType}" Target="diagrams/layout1.xml"/>
  <Relationship Id="rIdDiagramStyle" Type="{$diagramQuickStyleType}" Target="diagrams/quickStyle1.xml"/>
  <Relationship Id="rIdDiagramColorsExternal" Type="{$diagramColorsType}" Target="https://example.test/diagramColors.xml" TargetMode="External"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentThread" Type="{$commentsExtendedType}" Target="commentsExtended.xml"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/commentsExtended.xml', 'data' => '<w15:commentsEx/>'],
            ['name' => 'word/glossary/document.xml', 'data' => '<w:glossaryDocument/>'],
            ['name' => 'word/chunks/review.html', 'data' => '<p>Review</p>'],
            ['name' => 'word/chunks/plain-review.txt', 'data' => 'Review'],
            ['name' => 'word/chunks/review.xhtml', 'data' => '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Review</p></body></html>'],
            ['name' => 'word/chunks/source.rtf', 'data' => '{\\rtf1 Review}'],
            ['name' => 'word/charts/chart1.xml', 'data' => '<c:chartSpace/>'],
            ['name' => 'word/diagrams/data1.xml', 'data' => '<dgm:dataModel/>'],
            ['name' => 'word/diagrams/layout1.xml', 'data' => '<dgm:layoutDef/>'],
            ['name' => 'word/diagrams/quickStyle1.xml', 'data' => '<dgm:styleDef/>'],
        ]));

        $roles = [];
        foreach ($graph->preflightWordprocessingDocumentRelationships('/word/document.xml') as $role) {
            $roles[$role['id']] = $role;
        }

        $t->same([
            'rIdCommentsExtended',
            'rIdGlossary',
            'rIdHtmlChunk',
            'rIdPlainTextChunk',
            'rIdXhtmlChunk',
            'rIdUnsupportedChunk',
            'rIdExternalChunk',
            'rIdChart',
            'rIdDiagramData',
            'rIdDiagramLayoutWrongType',
            'rIdDiagramStyle',
            'rIdDiagramColorsExternal',
        ], array_keys($roles));

        $t->same('comments-extended', $roles['rIdCommentsExtended']['role']);
        $t->same($commentsExtendedType, $roles['rIdCommentsExtended']['type']);
        $t->same('/word/commentsExtended.xml', $roles['rIdCommentsExtended']['targetPart']);
        $t->same('application/vnd.ms-word.commentsExt+xml', $roles['rIdCommentsExtended']['contentType']);
        $t->same('application/vnd.ms-word.commentsExt+xml', $roles['rIdCommentsExtended']['expectedContentType']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES, $roles['rIdCommentsExtended']['expectedSourceContentTypes']);
        $t->same(false, $roles['rIdCommentsExtended']['expectedExternal']);
        $t->same(true, $roles['rIdCommentsExtended']['valid']);
        $t->same([], $roles['rIdCommentsExtended']['issues']);

        $t->same('glossary-document', $roles['rIdGlossary']['role']);
        $t->same('/word/glossary/document.xml', $roles['rIdGlossary']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml', $roles['rIdGlossary']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.document.glossary+xml', $roles['rIdGlossary']['expectedContentType']);
        $t->same(true, $roles['rIdGlossary']['valid']);
        $t->same([], $roles['rIdGlossary']['issues']);

        foreach ([
            'rIdHtmlChunk' => ['targetPart' => '/word/chunks/review.html', 'contentType' => 'text/html'],
            'rIdPlainTextChunk' => ['targetPart' => '/word/chunks/plain-review.txt', 'contentType' => 'text/plain; charset=utf-8'],
            'rIdXhtmlChunk' => ['targetPart' => '/word/chunks/review.xhtml', 'contentType' => 'application/xhtml+xml'],
        ] as $id => $expected) {
            $t->same('alternative-format-import', $roles[$id]['role']);
            $t->same($altChunkType, $roles[$id]['type']);
            $t->same($expected['targetPart'], $roles[$id]['targetPart']);
            $t->same($expected['contentType'], $roles[$id]['contentType']);
            $t->same(['text/html', 'application/xhtml+xml', 'text/plain'], $roles[$id]['expectedContentTypes']);
            $t->same(null, $roles[$id]['expectedContentType']);
            $t->same(false, $roles[$id]['expectedExternal']);
            $t->same(true, $roles[$id]['valid']);
            $t->same([], $roles[$id]['issues']);
        }

        $t->same('alternative-format-import', $roles['rIdUnsupportedChunk']['role']);
        $t->same('application/rtf', $roles['rIdUnsupportedChunk']['contentType']);
        $t->same(['text/html', 'application/xhtml+xml', 'text/plain'], $roles['rIdUnsupportedChunk']['expectedContentTypes']);
        $t->same(false, $roles['rIdUnsupportedChunk']['valid']);
        $t->same(['invalid-alternative-format-import-content-type'], $roles['rIdUnsupportedChunk']['issues']);

        $t->same('alternative-format-import', $roles['rIdExternalChunk']['role']);
        $t->same(true, $roles['rIdExternalChunk']['external']);
        $t->same(null, $roles['rIdExternalChunk']['targetPart']);
        $t->same(false, $roles['rIdExternalChunk']['expectedExternal']);
        $t->same(false, $roles['rIdExternalChunk']['valid']);
        $t->same(['external-alternative-format-import-target'], $roles['rIdExternalChunk']['issues']);

        $t->same('chart', $roles['rIdChart']['role']);
        $t->same('/word/charts/chart1.xml', $roles['rIdChart']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.chart+xml', $roles['rIdChart']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.chart+xml', $roles['rIdChart']['expectedContentType']);
        $t->same(true, $roles['rIdChart']['valid']);
        $t->same([], $roles['rIdChart']['issues']);

        $t->same('diagram-data', $roles['rIdDiagramData']['role']);
        $t->same('/word/diagrams/data1.xml', $roles['rIdDiagramData']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.diagramData+xml', $roles['rIdDiagramData']['contentType']);
        $t->same(true, $roles['rIdDiagramData']['valid']);
        $t->same([], $roles['rIdDiagramData']['issues']);

        $t->same('diagram-layout', $roles['rIdDiagramLayoutWrongType']['role']);
        $t->same('/word/diagrams/layout1.xml', $roles['rIdDiagramLayoutWrongType']['targetPart']);
        $t->same('application/xml', $roles['rIdDiagramLayoutWrongType']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.diagramLayout+xml', $roles['rIdDiagramLayoutWrongType']['expectedContentType']);
        $t->same(false, $roles['rIdDiagramLayoutWrongType']['valid']);
        $t->same(['invalid-diagram-layout-content-type'], $roles['rIdDiagramLayoutWrongType']['issues']);

        $t->same('diagram-quick-style', $roles['rIdDiagramStyle']['role']);
        $t->same('/word/diagrams/quickStyle1.xml', $roles['rIdDiagramStyle']['targetPart']);
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.diagramStyle+xml', $roles['rIdDiagramStyle']['contentType']);
        $t->same('application/vnd.openxmlformats-officedocument.drawingml.diagramStyle+xml', $roles['rIdDiagramStyle']['expectedContentType']);
        $t->same(true, $roles['rIdDiagramStyle']['valid']);
        $t->same([], $roles['rIdDiagramStyle']['issues']);

        $t->same('diagram-colors', $roles['rIdDiagramColorsExternal']['role']);
        $t->same(true, $roles['rIdDiagramColorsExternal']['external']);
        $t->same(false, $roles['rIdDiagramColorsExternal']['expectedExternal']);
        $t->same(false, $roles['rIdDiagramColorsExternal']['valid']);
        $t->same(['external-diagram-colors-target'], $roles['rIdDiagramColorsExternal']['issues']);

        $commentRoles = [];
        foreach ($graph->preflightWordprocessingDocumentRelationships('/word/comments.xml') as $role) {
            $commentRoles[$role['id']] = $role;
        }

        $t->same(['rIdCommentThread'], array_keys($commentRoles));
        $t->same('comments-extended', $commentRoles['rIdCommentThread']['role']);
        $t->same('/word/comments.xml', $commentRoles['rIdCommentThread']['source']);
        $t->same('application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml', $commentRoles['rIdCommentThread']['sourceContentType']);
        $t->same('/word/commentsExtended.xml', $commentRoles['rIdCommentThread']['targetPart']);
        $t->same('application/vnd.ms-word.commentsExt+xml', $commentRoles['rIdCommentThread']['contentType']);
        $t->same(OpcRelationshipGraph::WORDPROCESSING_OFFICE_DOCUMENT_CONTENT_TYPES, $commentRoles['rIdCommentThread']['expectedSourceContentTypes']);
        $t->same(false, $commentRoles['rIdCommentThread']['valid']);
        $t->same(['invalid-comments-extended-source-content-type'], $commentRoles['rIdCommentThread']['issues']);
    },
    'preflights OPC package and part thumbnail relationships' => static function (TestRunner $t): void {
        $thumbnailType = OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE;
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/docProps/thumbnail.png" ContentType="image/png"/>
  <Override PartName="/word/media/section-thumbnail.jpg" ContentType="image/jpeg; source=review"/>
  <Override PartName="/word/media/bad-thumbnail.xml" ContentType="application/xml"/>
</Types>
XML;
        $packageRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdPackageThumbnail" Type="{$thumbnailType}" Target="docProps/thumbnail.png"/>
  <Relationship Id="rIdMissingPackageThumbnail" Type="{$thumbnailType}" Target="word/media/missing-thumbnail.png"/>
</Relationships>
XML;
        $documentRelationshipsXml = <<<XML
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSectionThumbnail" Type="{$thumbnailType}" Target="media/section-thumbnail.jpg"/>
  <Relationship Id="rIdBadThumbnail" Type="{$thumbnailType}" Target="media/bad-thumbnail.xml"/>
  <Relationship Id="rIdRelatedThumbnail" Type="{$thumbnailType}" Target="media/related-thumbnail.png"/>
  <Relationship Id="rIdExternalThumbnail" Type="{$thumbnailType}" Target="https://example.test/wp-content/uploads/thumb.png" TargetMode="External"/>
</Relationships>
XML;
        $thumbnailRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdThumbnailAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="audit.png"/>
</Relationships>
XML;
        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'docProps/thumbnail.png', 'data' => 'PNG'],
            ['name' => 'word/media/section-thumbnail.jpg', 'data' => 'JPG'],
            ['name' => 'word/media/bad-thumbnail.xml', 'data' => '<not-image/>'],
            ['name' => 'word/media/related-thumbnail.png', 'data' => 'PNG'],
            ['name' => 'word/media/_rels/related-thumbnail.png.rels', 'data' => $thumbnailRelationshipsXml],
        ]));

        $thumbnailRows = [];
        foreach ($graph->preflightThumbnails() as $thumbnail) {
            $thumbnailRows[$thumbnail['source'] . ':' . $thumbnail['id']] = $thumbnail;
        }

        $t->same([
            '/:rIdPackageThumbnail',
            '/:rIdMissingPackageThumbnail',
            '/word/document.xml:rIdSectionThumbnail',
            '/word/document.xml:rIdBadThumbnail',
            '/word/document.xml:rIdRelatedThumbnail',
            '/word/document.xml:rIdExternalThumbnail',
        ], array_keys($thumbnailRows));

        $packageThumbnail = $thumbnailRows['/:rIdPackageThumbnail'];
        $t->same(OpcRelationshipGraph::THUMBNAIL_RELATIONSHIP_TYPE, $packageThumbnail['type']);
        $t->same('/docProps/thumbnail.png', $packageThumbnail['targetPart']);
        $t->same('image/png', $packageThumbnail['contentType']);
        $t->same('image/', $packageThumbnail['expectedContentTypePrefix']);
        $t->same(false, $packageThumbnail['external']);
        $t->same(true, $packageThumbnail['exists']);
        $t->same(false, $packageThumbnail['valid']);
        $t->same(['multiple-thumbnail-relationships-for-source'], $packageThumbnail['issues']);

        $missingPackageThumbnail = $thumbnailRows['/:rIdMissingPackageThumbnail'];
        $t->same('/word/media/missing-thumbnail.png', $missingPackageThumbnail['targetPart']);
        $t->same('image/png', $missingPackageThumbnail['contentType']);
        $t->same(false, $missingPackageThumbnail['external']);
        $t->same(false, $missingPackageThumbnail['exists']);
        $t->same(false, $missingPackageThumbnail['valid']);
        $t->same(['missing-in-package', 'multiple-thumbnail-relationships-for-source'], $missingPackageThumbnail['issues']);

        $sectionThumbnail = $thumbnailRows['/word/document.xml:rIdSectionThumbnail'];
        $t->same('/word/media/section-thumbnail.jpg', $sectionThumbnail['targetPart']);
        $t->same('image/jpeg; source=review', $sectionThumbnail['contentType']);
        $t->same(false, $sectionThumbnail['external']);
        $t->same(true, $sectionThumbnail['exists']);
        $t->same(false, $sectionThumbnail['valid']);
        $t->same(['multiple-thumbnail-relationships-for-source'], $sectionThumbnail['issues']);

        $badThumbnail = $thumbnailRows['/word/document.xml:rIdBadThumbnail'];
        $t->same('/word/media/bad-thumbnail.xml', $badThumbnail['targetPart']);
        $t->same('application/xml', $badThumbnail['contentType']);
        $t->same(false, $badThumbnail['valid']);
        $t->same(['multiple-thumbnail-relationships-for-source', 'invalid-thumbnail-content-type'], $badThumbnail['issues']);

        $relatedThumbnail = $thumbnailRows['/word/document.xml:rIdRelatedThumbnail'];
        $t->same('/word/media/related-thumbnail.png', $relatedThumbnail['targetPart']);
        $t->same('image/png', $relatedThumbnail['contentType']);
        $t->same(true, $relatedThumbnail['exists']);
        $t->same(false, $relatedThumbnail['valid']);
        $t->same(['multiple-thumbnail-relationships-for-source', 'thumbnail-target-has-relationships'], $relatedThumbnail['issues']);

        $externalThumbnail = $thumbnailRows['/word/document.xml:rIdExternalThumbnail'];
        $t->same('https://example.test/wp-content/uploads/thumb.png', $externalThumbnail['target']);
        $t->same(null, $externalThumbnail['targetPart']);
        $t->same(null, $externalThumbnail['contentType']);
        $t->same(true, $externalThumbnail['external']);
        $t->same('absolute-uri', $externalThumbnail['externalTargetKind']);
        $t->same('https', $externalThumbnail['externalTargetScheme']);
        $t->same(false, $externalThumbnail['valid']);
        $t->same(['multiple-thumbnail-relationships-for-source', 'external-thumbnail-target'], $externalThumbnail['issues']);

        $documentOnly = $graph->preflightThumbnails('/WORD/DOCUMENT.XML');
        $t->same([
            'rIdSectionThumbnail',
            'rIdBadThumbnail',
            'rIdRelatedThumbnail',
            'rIdExternalThumbnail',
        ], array_column($documentOnly, 'id'));
        $t->same([], $graph->preflightThumbnails('/word/media/related-thumbnail.png'));
        $t->same([], $graph->preflightThumbnails('/word/missing.xml'));
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

        $nestedPayloadSegmentRelationshipXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdHiddenPayload" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../hidden.png"/>
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
        $t->same('skipped', $commentsRels['relationshipPartLoadAction']);
        $t->same('invalid-relationship-content-type', $commentsRels['relationshipPartLoadReason']);
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
    'direct OPC relationship loaders require relationship part content type when content types are present' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
</Types>
XML;

        $rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCommentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/comment.png"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
        ]);

        $t->same(false, OpcRelationships::packageHasRelationshipsForSource($package, '/word/comments.xml'));
        $t->throws(\RuntimeException::class, static fn (): OpcRelationships => OpcRelationships::fromPackage($package, '/word/comments.xml'));

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same('/word/comments.xml', $loads['/word/_rels/comments.xml.rels']['relationshipSource']);
        $t->same('application/xml', $loads['/word/_rels/comments.xml.rels']['contentType']);
        $t->same(false, $loads['/word/_rels/comments.xml.rels']['loaded']);
        $t->same(['invalid-relationship-content-type'], $loads['/word/_rels/comments.xml.rels']['issues']);
    },
    'reports OPC relationship part content type provenance before graph construction' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/_rels/document.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/_rels/case.xml.rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
</Types>
XML;

        $rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'Word/CasE.xml', 'data' => '<review/>'],
            ['name' => 'Word/_rels/CasE.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $documentRelationshipsXml],
        ]);

        $parts = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $parts[$part['partName']] = $part;
        }

        $t->same('default', $parts['/_rels/.rels']['contentTypeSource']);
        $t->same('rels', $parts['/_rels/.rels']['contentTypeDefaultExtension']);
        $t->same(null, $parts['/_rels/.rels']['contentTypeOverridePartName']);
        $t->same(false, $parts['/_rels/.rels']['contentTypeOverridePartNameExactMatch']);
        $t->same(false, $parts['/_rels/.rels']['contentTypeOverridePartNameEquivalentMatch']);

        $t->same('override', $parts['/word/_rels/document.xml.rels']['contentTypeSource']);
        $t->same(null, $parts['/word/_rels/document.xml.rels']['contentTypeDefaultExtension']);
        $t->same('/word/_rels/document.xml.rels', $parts['/word/_rels/document.xml.rels']['contentTypeOverridePartName']);
        $t->same(true, $parts['/word/_rels/document.xml.rels']['contentTypeOverridePartNameExactMatch']);
        $t->same(false, $parts['/word/_rels/document.xml.rels']['contentTypeOverridePartNameEquivalentMatch']);
        $t->same(true, $parts['/word/_rels/document.xml.rels']['loaded']);

        $t->same('override', $parts['/Word/_rels/CasE.xml.rels']['contentTypeSource']);
        $t->same('/word/_rels/case.xml.rels', $parts['/Word/_rels/CasE.xml.rels']['contentTypeOverridePartName']);
        $t->same(false, $parts['/Word/_rels/CasE.xml.rels']['contentTypeOverridePartNameExactMatch']);
        $t->same(true, $parts['/Word/_rels/CasE.xml.rels']['contentTypeOverridePartNameEquivalentMatch']);
        $t->same('/Word/CasE.xml', $parts['/Word/_rels/CasE.xml.rels']['relationshipSource']);
        $t->same(true, $parts['/Word/_rels/CasE.xml.rels']['loaded']);

        $t->same('override', $parts['/word/_rels/comments.xml.rels']['contentTypeSource']);
        $t->same('application/xml', $parts['/word/_rels/comments.xml.rels']['contentType']);
        $t->same(false, $parts['/word/_rels/comments.xml.rels']['loaded']);
        $t->same(['invalid-relationship-content-type'], $parts['/word/_rels/comments.xml.rels']['issues']);

        $summary = OpcRelationshipGraph::relationshipPartLoadSummary($package);
        $t->same(['default' => 1, 'override' => 3], $summary['contentTypeSourceCounts']);
        $t->same(['/_rels/.rels'], $summary['partNamesByContentTypeSource']['default']);
        $t->same([
            '/Word/_rels/CasE.xml.rels',
            '/word/_rels/comments.xml.rels',
            '/word/_rels/document.xml.rels',
        ], $summary['partNamesByContentTypeSource']['override']);
    },
    'does not load orphan OPC relationship parts as graph sources' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML;

        $rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
</Relationships>
XML;

        $orphanRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdOrphanImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/orphan.png"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/orphan.xml.rels', 'data' => $orphanRelationshipsXml],
            ['name' => 'word/media/orphan.png', 'data' => 'PNG'],
        ]);

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same('/word/orphan.xml', $loads['/word/_rels/orphan.xml.rels']['relationshipSource']);
        $t->same(false, $loads['/word/_rels/orphan.xml.rels']['sourceExists']);
        $t->same(false, $loads['/word/_rels/orphan.xml.rels']['loaded']);
        $t->same('skipped', $loads['/word/_rels/orphan.xml.rels']['loadAction']);
        $t->same('orphan-relationship-part', $loads['/word/_rels/orphan.xml.rels']['loadReason']);
        $t->same(['orphan-relationship-part'], $loads['/word/_rels/orphan.xml.rels']['issues']);

        $graph = OpcRelationshipGraph::fromPackage($package);
        $t->same(['/', '/word/document.xml'], $graph->sourcePartNames());
        $t->same(false, $graph->hasRelationshipsForSource('/word/orphan.xml'));
        $t->same(null, $graph->relationshipsForSource('/word/orphan.xml'));
        $t->same([], $graph->preflightTargetsForSource('/word/orphan.xml'));

        $parts = [];
        foreach ($graph->preflightPackageParts() as $part) {
            $parts[$part['partName']] = $part;
        }

        $orphanPart = $parts['/word/_rels/orphan.xml.rels'];
        $t->same(true, $orphanPart['relationshipPart']);
        $t->same('/word/orphan.xml', $orphanPart['relationshipSource']);
        $t->same(false, $orphanPart['sourceExists']);
        $t->same(false, $orphanPart['relationshipSourceLoaded']);
        $t->same('skipped', $orphanPart['relationshipPartLoadAction']);
        $t->same('orphan-relationship-part', $orphanPart['relationshipPartLoadReason']);
        $t->same(['orphan-relationship-part'], $orphanPart['issues']);

        $closureById = [];
        foreach ($graph->reachableTargetsForSource('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE) as $target) {
            $closureById[$target['id']] = $target;
        }

        $t->same(['rIdDocument', 'rIdStyles'], array_keys($closureById));
        $t->same(false, isset($closureById['rIdOrphanImage']));
    },
    'preflights OPC relationship part load decisions before graph construction' => static function (TestRunner $t) use ($packageRelationshipsXml): void {
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

        $contentTypesRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdContentTypesAudit" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/document.xml"/>
</Relationships>
XML;

        $nestedRelationshipXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdNestedImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../media/nested.png"/>
</Relationships>
XML;

        $invalidTargetModeRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdTargetModeCase" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/review" TargetMode="external"/>
</Relationships>
XML;

        $malformedRelationshipXml = '<Relationships xmlns="' . OpcRelationships::NAMESPACE_URI . '"><Relationship Id="rIdBad" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/bad.png">';

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => '_rels/[Content_Types].xml.rels', 'data' => $contentTypesRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/document.xml', 'data' => '<review/>'],
            ['name' => 'word/_rels/media/document.xml.rels', 'data' => $nestedRelationshipXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/targetmode.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/targetmode.xml.rels', 'data' => $invalidTargetModeRelationshipsXml],
            ['name' => 'word/malformed.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/malformed.xml.rels', 'data' => $malformedRelationshipXml],
            ['name' => 'word/_rels/missing.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/_rels/_rels/document.xml.rels.rels', 'data' => $nestedRelationshipXml],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
            ['name' => 'word/media/nested.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
        ]);

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same([
            '/_rels/.rels',
            '/_rels/[Content_Types].xml.rels',
            '/word/_rels/document.xml.rels',
            '/word/_rels/media/document.xml.rels',
            '/word/_rels/comments.xml.rels',
            '/word/_rels/targetmode.xml.rels',
            '/word/_rels/malformed.xml.rels',
            '/word/_rels/missing.xml.rels',
            '/word/_rels/_rels/document.xml.rels.rels',
        ], array_keys($loads));

        $t->same('/', $loads['/_rels/.rels']['relationshipSource']);
        $t->same(true, $loads['/_rels/.rels']['loaded']);
        $t->same('loaded', $loads['/_rels/.rels']['loadAction']);
        $t->same('loaded', $loads['/_rels/.rels']['loadReason']);
        $t->same(3, $loads['/_rels/.rels']['relationshipCount']);
        $t->same('package-root', $loads['/_rels/.rels']['relationshipSourceKind']);
        $t->same(true, $loads['/_rels/.rels']['valid']);
        $t->same([], $loads['/_rels/.rels']['issues']);

        $t->same('/[Content_Types].xml', $loads['/_rels/[Content_Types].xml.rels']['relationshipSource']);
        $t->same(true, $loads['/_rels/[Content_Types].xml.rels']['sourceExists']);
        $t->same(false, $loads['/_rels/[Content_Types].xml.rels']['loaded']);
        $t->same('skipped', $loads['/_rels/[Content_Types].xml.rels']['loadAction']);
        $t->same('content-types-item-source', $loads['/_rels/[Content_Types].xml.rels']['loadReason']);
        $t->same('content-types-item', $loads['/_rels/[Content_Types].xml.rels']['relationshipSourceKind']);
        $t->same(['content-types-item-source'], $loads['/_rels/[Content_Types].xml.rels']['issues']);

        $t->same('/word/document.xml', $loads['/word/_rels/document.xml.rels']['relationshipSource']);
        $t->same(true, $loads['/word/_rels/document.xml.rels']['sourceExists']);
        $t->same(true, $loads['/word/_rels/document.xml.rels']['loaded']);
        $t->same('loaded', $loads['/word/_rels/document.xml.rels']['loadAction']);
        $t->same('loaded', $loads['/word/_rels/document.xml.rels']['loadReason']);
        $t->same('package-part', $loads['/word/_rels/document.xml.rels']['relationshipSourceKind']);
        $t->same(1, $loads['/word/_rels/document.xml.rels']['relationshipCount']);

        $t->same(null, $loads['/word/_rels/media/document.xml.rels']['relationshipSource']);
        $t->same(null, $loads['/word/_rels/media/document.xml.rels']['sourceExists']);
        $t->same(false, $loads['/word/_rels/media/document.xml.rels']['loaded']);
        $t->same('skipped', $loads['/word/_rels/media/document.xml.rels']['loadAction']);
        $t->same('invalid-relationship-part-name', $loads['/word/_rels/media/document.xml.rels']['loadReason']);
        $t->same('invalid-source', $loads['/word/_rels/media/document.xml.rels']['relationshipSourceKind']);
        $t->same(null, $loads['/word/_rels/media/document.xml.rels']['relationshipCount']);
        $t->same(false, $loads['/word/_rels/media/document.xml.rels']['valid']);
        $t->same(['invalid-relationship-part-name'], $loads['/word/_rels/media/document.xml.rels']['issues']);
        $t->contains('single .rels file inside a _rels directory', $loads['/word/_rels/media/document.xml.rels']['parseError'] ?? '');
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcRelationships::sourcePartNameForRelationshipPart('/word/_rels/media/document.xml.rels'));

        $t->same('/word/comments.xml', $loads['/word/_rels/comments.xml.rels']['relationshipSource']);
        $t->same('application/xml', $loads['/word/_rels/comments.xml.rels']['contentType']);
        $t->same(false, $loads['/word/_rels/comments.xml.rels']['loaded']);
        $t->same('skipped', $loads['/word/_rels/comments.xml.rels']['loadAction']);
        $t->same('invalid-relationship-content-type', $loads['/word/_rels/comments.xml.rels']['loadReason']);
        $t->same('package-part', $loads['/word/_rels/comments.xml.rels']['relationshipSourceKind']);
        $t->same(null, $loads['/word/_rels/comments.xml.rels']['relationshipCount']);
        $t->same(['invalid-relationship-content-type'], $loads['/word/_rels/comments.xml.rels']['issues']);

        $t->same('/word/targetmode.xml', $loads['/word/_rels/targetmode.xml.rels']['relationshipSource']);
        $t->same(true, $loads['/word/_rels/targetmode.xml.rels']['sourceExists']);
        $t->same(false, $loads['/word/_rels/targetmode.xml.rels']['loaded']);
        $t->same('skipped', $loads['/word/_rels/targetmode.xml.rels']['loadAction']);
        $t->same('malformed-relationship-xml', $loads['/word/_rels/targetmode.xml.rels']['loadReason']);
        $t->same('package-part', $loads['/word/_rels/targetmode.xml.rels']['relationshipSourceKind']);
        $t->same(null, $loads['/word/_rels/targetmode.xml.rels']['relationshipCount']);
        $t->same(['malformed-relationship-xml', 'invalid-relationship-target-mode'], $loads['/word/_rels/targetmode.xml.rels']['issues']);
        $t->contains('Unsupported OPC relationship TargetMode: external', $loads['/word/_rels/targetmode.xml.rels']['parseError'] ?? '');

        $t->same('/word/malformed.xml', $loads['/word/_rels/malformed.xml.rels']['relationshipSource']);
        $t->same(true, $loads['/word/_rels/malformed.xml.rels']['sourceExists']);
        $t->same(false, $loads['/word/_rels/malformed.xml.rels']['loaded']);
        $t->same('skipped', $loads['/word/_rels/malformed.xml.rels']['loadAction']);
        $t->same('malformed-relationship-xml', $loads['/word/_rels/malformed.xml.rels']['loadReason']);
        $t->same('package-part', $loads['/word/_rels/malformed.xml.rels']['relationshipSourceKind']);
        $t->same(['malformed-relationship-xml'], $loads['/word/_rels/malformed.xml.rels']['issues']);
        $t->contains('OPC relationships XML', $loads['/word/_rels/malformed.xml.rels']['parseError'] ?? '');

        $t->same('/word/missing.xml', $loads['/word/_rels/missing.xml.rels']['relationshipSource']);
        $t->same(false, $loads['/word/_rels/missing.xml.rels']['sourceExists']);
        $t->same(false, $loads['/word/_rels/missing.xml.rels']['loaded']);
        $t->same('skipped', $loads['/word/_rels/missing.xml.rels']['loadAction']);
        $t->same('orphan-relationship-part', $loads['/word/_rels/missing.xml.rels']['loadReason']);
        $t->same('missing-source', $loads['/word/_rels/missing.xml.rels']['relationshipSourceKind']);
        $t->same(['orphan-relationship-part'], $loads['/word/_rels/missing.xml.rels']['issues']);

        $t->same('/word/_rels/document.xml.rels', $loads['/word/_rels/_rels/document.xml.rels.rels']['relationshipSource']);
        $t->same(true, $loads['/word/_rels/_rels/document.xml.rels.rels']['relationshipSourceIsRelationshipPart']);
        $t->same(true, $loads['/word/_rels/_rels/document.xml.rels.rels']['sourceExists']);
        $t->same(false, $loads['/word/_rels/_rels/document.xml.rels.rels']['loaded']);
        $t->same('skipped', $loads['/word/_rels/_rels/document.xml.rels.rels']['loadAction']);
        $t->same('relationship-part-source', $loads['/word/_rels/_rels/document.xml.rels.rels']['loadReason']);
        $t->same('relationship-part', $loads['/word/_rels/_rels/document.xml.rels.rels']['relationshipSourceKind']);
        $t->same(['relationship-part-source'], $loads['/word/_rels/_rels/document.xml.rels.rels']['issues']);

        $summary = OpcRelationshipGraph::relationshipPartLoadSummary($package);
        $t->same(false, $summary['valid']);
        $t->same(9, $summary['relationshipPartCount']);
        $t->same(2, $summary['loadedCount']);
        $t->same(7, $summary['skippedCount']);
        $t->same(2, $summary['validCount']);
        $t->same(7, $summary['invalidCount']);
        $t->same(4, $summary['relationshipCount']);
        $t->same([
            '/_rels/.rels',
            '/word/_rels/document.xml.rels',
        ], $summary['loadedPartNames']);
        $t->same([
            '/_rels/[Content_Types].xml.rels',
            '/word/_rels/_rels/document.xml.rels.rels',
            '/word/_rels/comments.xml.rels',
            '/word/_rels/malformed.xml.rels',
            '/word/_rels/media/document.xml.rels',
            '/word/_rels/missing.xml.rels',
            '/word/_rels/targetmode.xml.rels',
        ], $summary['skippedPartNames']);
        $t->same(['/', '/word/document.xml'], $summary['loadedSources']);
        $t->same([
            '/[Content_Types].xml',
            '/word/_rels/document.xml.rels',
            '/word/comments.xml',
            '/word/malformed.xml',
            '/word/missing.xml',
            '/word/targetmode.xml',
        ], $summary['skippedSources']);
        $t->same(['loaded' => 2, 'skipped' => 7], $summary['loadActionCounts']);
        $t->same([
            'content-types-item-source' => 1,
            'invalid-relationship-content-type' => 1,
            'invalid-relationship-part-name' => 1,
            'loaded' => 2,
            'malformed-relationship-xml' => 2,
            'orphan-relationship-part' => 1,
            'relationship-part-source' => 1,
        ], $summary['loadReasonCounts']);
        $t->same([
            'content-types-item' => 1,
            'invalid-source' => 1,
            'missing-source' => 1,
            'package-part' => 4,
            'package-root' => 1,
            'relationship-part' => 1,
        ], $summary['sourceKindCounts']);
        $t->same(['/_rels/[Content_Types].xml.rels'], $summary['partNamesBySourceKind']['content-types-item']);
        $t->same([
            '/word/_rels/comments.xml.rels',
            '/word/_rels/document.xml.rels',
            '/word/_rels/malformed.xml.rels',
            '/word/_rels/targetmode.xml.rels',
        ], $summary['partNamesBySourceKind']['package-part']);
        $t->same([
            '/word/_rels/malformed.xml.rels',
            '/word/_rels/targetmode.xml.rels',
        ], $summary['partNamesByLoadReason']['malformed-relationship-xml']);
        $t->same([
            'content-types-item-source' => 1,
            'invalid-relationship-content-type' => 1,
            'invalid-relationship-part-name' => 1,
            'invalid-relationship-target-mode' => 1,
            'malformed-relationship-xml' => 2,
            'orphan-relationship-part' => 1,
            'relationship-part-source' => 1,
        ], $summary['issueCounts']);
        $t->same(['/_rels/[Content_Types].xml.rels'], $summary['partNamesByIssue']['content-types-item-source']);
        $t->same([
            '/word/_rels/targetmode.xml.rels',
        ], $summary['partNamesByIssue']['invalid-relationship-target-mode']);
        $t->same([
            'content-types-item-source',
            'invalid-relationship-content-type',
            'invalid-relationship-part-name',
            'invalid-relationship-target-mode',
            'malformed-relationship-xml',
            'orphan-relationship-part',
            'relationship-part-source',
        ], $summary['issues']);

        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationshipGraph => OpcRelationshipGraph::fromPackage($package));
    },
    'summarizes OPC relationship part load counts by action reason and issue' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/_rels/comments.xml.rels" ContentType="application/xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
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

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml, 'compressionMethod' => 0],
            ['name' => 'word/_rels/missing.xml.rels', 'data' => $commentsRelationshipsXml, 'compressionMethod' => 0],
        ]);

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same('_rels/.rels', $loads['/_rels/.rels']['entryName']);
        $t->same(0, $loads['/_rels/.rels']['compressionMethod']);
        $t->same('stored', $loads['/_rels/.rels']['compressionMethodName']);
        $t->same(strlen($packageRelationshipsXml), $loads['/_rels/.rels']['compressedSize']);
        $t->same(strlen($packageRelationshipsXml), $loads['/_rels/.rels']['uncompressedSize']);
        $t->same('word/_rels/comments.xml.rels', $loads['/word/_rels/comments.xml.rels']['entryName']);
        $t->same(0, $loads['/word/_rels/comments.xml.rels']['compressionMethod']);
        $t->same('stored', $loads['/word/_rels/comments.xml.rels']['compressionMethodName']);
        $t->same(strlen($commentsRelationshipsXml), $loads['/word/_rels/comments.xml.rels']['compressedSize']);
        $t->same(strlen($commentsRelationshipsXml), $loads['/word/_rels/comments.xml.rels']['uncompressedSize']);

        $loadedRelationshipPartBytes = strlen($packageRelationshipsXml) + strlen($documentRelationshipsXml);
        $skippedRelationshipPartBytes = strlen($commentsRelationshipsXml) * 2;
        $totalRelationshipPartBytes = $loadedRelationshipPartBytes + $skippedRelationshipPartBytes;

        $summary = OpcRelationshipGraph::relationshipPartLoadSummary($package);

        $t->same(false, $summary['valid']);
        $t->same(4, $summary['relationshipPartCount']);
        $t->same(2, $summary['loadedCount']);
        $t->same(2, $summary['skippedCount']);
        $t->same(2, $summary['validCount']);
        $t->same(2, $summary['invalidCount']);
        $t->same(2, $summary['relationshipCount']);
        $t->same($totalRelationshipPartBytes, $summary['compressedRelationshipPartBytes']);
        $t->same($totalRelationshipPartBytes, $summary['uncompressedRelationshipPartBytes']);
        $t->same($loadedRelationshipPartBytes, $summary['loadedCompressedRelationshipPartBytes']);
        $t->same($loadedRelationshipPartBytes, $summary['loadedUncompressedRelationshipPartBytes']);
        $t->same($skippedRelationshipPartBytes, $summary['skippedCompressedRelationshipPartBytes']);
        $t->same($skippedRelationshipPartBytes, $summary['skippedUncompressedRelationshipPartBytes']);
        $t->same(['loaded' => 2, 'skipped' => 2], $summary['loadActionCounts']);
        $t->same([
            'invalid-relationship-content-type' => 1,
            'loaded' => 2,
            'orphan-relationship-part' => 1,
        ], $summary['loadReasonCounts']);
        $t->same([
            'loaded' => [
                'entryCount' => 2,
                'compressedBytes' => $loadedRelationshipPartBytes,
                'uncompressedBytes' => $loadedRelationshipPartBytes,
            ],
            'skipped' => [
                'entryCount' => 2,
                'compressedBytes' => $skippedRelationshipPartBytes,
                'uncompressedBytes' => $skippedRelationshipPartBytes,
            ],
        ], $summary['byteCountsByLoadAction']);
        $t->same([
            'invalid-relationship-content-type' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($commentsRelationshipsXml),
                'uncompressedBytes' => strlen($commentsRelationshipsXml),
            ],
            'loaded' => [
                'entryCount' => 2,
                'compressedBytes' => $loadedRelationshipPartBytes,
                'uncompressedBytes' => $loadedRelationshipPartBytes,
            ],
            'orphan-relationship-part' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($commentsRelationshipsXml),
                'uncompressedBytes' => strlen($commentsRelationshipsXml),
            ],
        ], $summary['byteCountsByLoadReason']);
        $t->same([
            'missing-source' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($commentsRelationshipsXml),
                'uncompressedBytes' => strlen($commentsRelationshipsXml),
            ],
            'package-part' => [
                'entryCount' => 2,
                'compressedBytes' => strlen($documentRelationshipsXml) + strlen($commentsRelationshipsXml),
                'uncompressedBytes' => strlen($documentRelationshipsXml) + strlen($commentsRelationshipsXml),
            ],
            'package-root' => [
                'entryCount' => 1,
                'compressedBytes' => strlen($packageRelationshipsXml),
                'uncompressedBytes' => strlen($packageRelationshipsXml),
            ],
        ], $summary['byteCountsBySourceKind']);
        $t->same([
            'invalid-relationship-content-type' => 1,
            'orphan-relationship-part' => 1,
        ], $summary['issueCounts']);
        $t->same([
            '/_rels/.rels',
            '/word/_rels/document.xml.rels',
        ], $summary['partNamesByLoadReason']['loaded']);
        $t->same(['/word/_rels/comments.xml.rels'], $summary['partNamesByIssue']['invalid-relationship-content-type']);
        $t->same(['/word/_rels/missing.xml.rels'], $summary['partNamesByIssue']['orphan-relationship-part']);
        $t->same([
            'invalid-relationship-content-type',
            'orphan-relationship-part',
        ], $summary['issues']);
    },
    'classifies malformed OPC relationship records by required attribute and id issues' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
</Types>
XML;

        $rootRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdMissingId" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/missing-id.xml"/>
  <Relationship Id="rIdMissingType" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/missing-type.xml"/>
  <Relationship Id="rIdMissingTarget" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/missing-target.xml"/>
  <Relationship Id="rIdInvalidId" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/invalid-id.xml"/>
  <Relationship Id="rIdDuplicateId" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="word/duplicate-id.xml"/>
</Relationships>
XML;

        $missingIdRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML;

        $missingTypeRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Target="media/review.png"/>
</Relationships>
XML;

        $missingTargetRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"/>
</Relationships>
XML;

        $invalidIdRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="1bad" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review.png"/>
</Relationships>
XML;

        $duplicateIdRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-a.png"/>
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-b.png"/>
</Relationships>
XML;

        $package = ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $rootRelationshipsXml],
            ['name' => 'word/missing-id.xml', 'data' => '<review/>'],
            ['name' => 'word/_rels/missing-id.xml.rels', 'data' => $missingIdRelationshipsXml],
            ['name' => 'word/missing-type.xml', 'data' => '<review/>'],
            ['name' => 'word/_rels/missing-type.xml.rels', 'data' => $missingTypeRelationshipsXml],
            ['name' => 'word/missing-target.xml', 'data' => '<review/>'],
            ['name' => 'word/_rels/missing-target.xml.rels', 'data' => $missingTargetRelationshipsXml],
            ['name' => 'word/invalid-id.xml', 'data' => '<review/>'],
            ['name' => 'word/_rels/invalid-id.xml.rels', 'data' => $invalidIdRelationshipsXml],
            ['name' => 'word/duplicate-id.xml', 'data' => '<review/>'],
            ['name' => 'word/_rels/duplicate-id.xml.rels', 'data' => $duplicateIdRelationshipsXml],
        ]);

        $loads = [];
        foreach (OpcRelationshipGraph::preflightRelationshipPartsInPackage($package) as $part) {
            $loads[$part['partName']] = $part;
        }

        $t->same(true, $loads['/_rels/.rels']['loaded']);
        $t->same(5, $loads['/_rels/.rels']['relationshipCount']);

        $expected = [
            '/word/_rels/missing-id.xml.rels' => ['missing-relationship-id', 'missing required Id attribute'],
            '/word/_rels/missing-type.xml.rels' => ['missing-relationship-type', 'missing required Type attribute'],
            '/word/_rels/missing-target.xml.rels' => ['missing-relationship-target', 'missing required Target attribute'],
            '/word/_rels/invalid-id.xml.rels' => ['invalid-relationship-id', 'XML NCName-style identifier'],
            '/word/_rels/duplicate-id.xml.rels' => ['duplicate-relationship-id', 'Duplicate OPC relationship Id: rIdReviewImage'],
        ];
        $imageRelationshipType = OpcRelationshipGraph::WORDPROCESSING_IMAGE_RELATIONSHIP_TYPE;
        $expectedRecords = [
            '/word/_rels/missing-id.xml.rels' => [[
                'relationshipOrdinal' => 1,
                'id' => null,
                'type' => $imageRelationshipType,
                'target' => 'media/review.png',
                'targetMode' => null,
                'duplicateOfOrdinal' => null,
                'issues' => ['missing-relationship-id'],
            ]],
            '/word/_rels/missing-type.xml.rels' => [[
                'relationshipOrdinal' => 1,
                'id' => 'rIdReviewImage',
                'type' => null,
                'target' => 'media/review.png',
                'targetMode' => null,
                'duplicateOfOrdinal' => null,
                'issues' => ['missing-relationship-type'],
            ]],
            '/word/_rels/missing-target.xml.rels' => [[
                'relationshipOrdinal' => 1,
                'id' => 'rIdReviewImage',
                'type' => $imageRelationshipType,
                'target' => null,
                'targetMode' => null,
                'duplicateOfOrdinal' => null,
                'issues' => ['missing-relationship-target'],
            ]],
            '/word/_rels/invalid-id.xml.rels' => [[
                'relationshipOrdinal' => 1,
                'id' => '1bad',
                'type' => $imageRelationshipType,
                'target' => 'media/review.png',
                'targetMode' => null,
                'duplicateOfOrdinal' => null,
                'issues' => ['invalid-relationship-id'],
            ]],
            '/word/_rels/duplicate-id.xml.rels' => [[
                'relationshipOrdinal' => 2,
                'id' => 'rIdReviewImage',
                'type' => $imageRelationshipType,
                'target' => 'media/review-b.png',
                'targetMode' => null,
                'duplicateOfOrdinal' => 1,
                'issues' => ['duplicate-relationship-id'],
            ]],
        ];

        foreach ($expected as $partName => [$specificIssue, $parseErrorNeedle]) {
            $t->same(true, $loads[$partName]['sourceExists']);
            $t->same(false, $loads[$partName]['loaded']);
            $t->same('skipped', $loads[$partName]['loadAction']);
            $t->same('malformed-relationship-xml', $loads[$partName]['loadReason']);
            $t->same(null, $loads[$partName]['relationshipCount']);
            $t->same(false, $loads[$partName]['valid']);
            $t->same(['malformed-relationship-xml', $specificIssue], $loads[$partName]['issues']);
            $t->contains($parseErrorNeedle, $loads[$partName]['parseError'] ?? '');
            $t->same($expectedRecords[$partName], $loads[$partName]['relationshipXmlIssueRecords']);
        }

        $summary = OpcRelationshipGraph::relationshipPartLoadSummary($package);
        $t->same(5, $summary['relationshipXmlIssueRecordCount']);
        $summaryRecordsByPart = [];
        foreach ($summary['relationshipXmlIssueRecords'] as $record) {
            $summaryRecordsByPart[$record['partName']] = $record;
        }
        $t->same('/word/duplicate-id.xml', $summaryRecordsByPart['/word/_rels/duplicate-id.xml.rels']['relationshipSource']);
        $t->same(2, $summaryRecordsByPart['/word/_rels/duplicate-id.xml.rels']['relationshipOrdinal']);
        $t->same(1, $summaryRecordsByPart['/word/_rels/duplicate-id.xml.rels']['duplicateOfOrdinal']);
        $t->same(['duplicate-relationship-id'], $summaryRecordsByPart['/word/_rels/duplicate-id.xml.rels']['issues']);

        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($missingIdRelationshipsXml, '/word/missing-id.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($missingTypeRelationshipsXml, '/word/missing-type.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($missingTargetRelationshipsXml, '/word/missing-target.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($invalidIdRelationshipsXml, '/word/invalid-id.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): OpcRelationships => OpcRelationships::fromXml($duplicateIdRelationshipsXml, '/word/duplicate-id.xml'));
    },
    'summarizes malformed OPC relationship XML record provenance by package part' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
</Types>
XML;

        $relationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-a.png"/>
  <Relationship Id="rIdReviewImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/review-b.png"/>
  <Relationship Id="rIdMissingTarget" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image"/>
</Relationships>
XML;

        $summary = OpcRelationshipGraph::relationshipPartLoadSummary(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => 'word/review.xml', 'data' => '<review/>'],
            ['name' => 'word/_rels/review.xml.rels', 'data' => $relationshipsXml],
        ]));

        $t->same(false, $summary['valid']);
        $t->same(1, $summary['relationshipPartCount']);
        $t->same(2, $summary['relationshipXmlIssueRecordCount']);
        $t->same([
            'duplicate-relationship-id',
            'malformed-relationship-xml',
            'missing-relationship-target',
        ], $summary['issues']);
        $t->same('/word/_rels/review.xml.rels', $summary['relationshipXmlIssueRecords'][0]['partName']);
        $t->same('/word/review.xml', $summary['relationshipXmlIssueRecords'][0]['relationshipSource']);
        $t->same(2, $summary['relationshipXmlIssueRecords'][0]['relationshipOrdinal']);
        $t->same(1, $summary['relationshipXmlIssueRecords'][0]['duplicateOfOrdinal']);
        $t->same(['duplicate-relationship-id'], $summary['relationshipXmlIssueRecords'][0]['issues']);
        $t->same(3, $summary['relationshipXmlIssueRecords'][1]['relationshipOrdinal']);
        $t->same('rIdMissingTarget', $summary['relationshipXmlIssueRecords'][1]['id']);
        $t->same(['missing-relationship-target'], $summary['relationshipXmlIssueRecords'][1]['issues']);
    },
    'preserves OPC closure stop query fragment and same-source provenance for review' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSelfReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="?review=ready#bookmark"/>
  <Relationship Id="rIdStylesQuery" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml?theme=light"/>
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

        $closure = $graph->relationshipSourceClosureInventory('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE);
        $stops = [];
        foreach ($closure['stops'] as $stop) {
            $stops[$stop['id']] = $stop;
        }

        $t->same(2, $closure['stopCount']);
        $t->same('cycle-target', $stops['rIdSelfReview']['stopReason']);
        $t->same('/word/document.xml', $stops['rIdSelfReview']['targetPart']);
        $t->same('review=ready', $stops['rIdSelfReview']['targetQuery']);
        $t->same('bookmark', $stops['rIdSelfReview']['targetFragment']);
        $t->same(true, $stops['rIdSelfReview']['sameSourceReference']);
        $t->same('target-source-not-loaded', $stops['rIdStylesQuery']['stopReason']);
        $t->same('/word/styles.xml', $stops['rIdStylesQuery']['targetPart']);
        $t->same('theme=light', $stops['rIdStylesQuery']['targetQuery']);
        $t->same(null, $stops['rIdStylesQuery']['targetFragment']);
        $t->same(false, $stops['rIdStylesQuery']['sameSourceReference']);

        $summary = $graph->relationshipSourceClosureCoverageSummary(
            '/',
            OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE
        );
        $t->same(2, $summary['stopCount']);
        $t->same(2, $summary['stopQueryTargetCount']);
        $t->same(1, $summary['stopFragmentTargetCount']);
        $t->same(1, $summary['stopSameSourceReferenceCount']);
        $t->same(['rIdSelfReview'], $summary['stopIdsByReason']['cycle-target']);
        $t->same(['rIdStylesQuery'], $summary['stopIdsByReason']['target-source-not-loaded']);
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
        $t->same(['invalid-target', 'internal-target-package-root-traversal'], $closureById['rIdEscape']['issues']);
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
    'summarizes OPC relationship source closure expansion and stop policy' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
  <Override PartName="/word/comments.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.comments+xml"/>
  <Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdComments" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/comments" Target="comments.xml"/>
  <Relationship Id="rIdMissingImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/missing.png"/>
  <Relationship Id="rIdUnsafeLink" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="javascript:alert(1)" TargetMode="External"/>
  <Relationship Id="rIdRelsTarget" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="_rels/document.xml.rels"/>
  <Relationship Id="rIdEscape" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../../evil.xml"/>
</Relationships>
XML;

        $commentsRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdBackToDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="document.xml#cycle"/>
  <Relationship Id="rIdCommentImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/comment.png"/>
</Relationships>
XML;

        $coreRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCoreImage" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../word/media/core.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/comments.xml', 'data' => '<w:comments xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/comments.xml.rels', 'data' => $commentsRelationshipsXml],
            ['name' => 'word/styles.xml', 'data' => '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/media/comment.png', 'data' => 'PNG'],
            ['name' => 'word/media/core.png', 'data' => 'PNG'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'docProps/_rels/core.xml.rels', 'data' => $coreRelationshipsXml],
        ]));

        $closure = $graph->relationshipSourceClosureInventory('/', OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE);

        $t->same('/', $closure['source']);
        $t->same(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE, $closure['relationshipType']);
        $t->same(false, $closure['valid']);
        $t->same([
            'external-target-unsafe-scheme',
            'internal-target-package-root-traversal',
            'invalid-target',
            'missing-in-package',
            'targets-relationship-part',
        ], $closure['issues']);
        $t->same(3, $closure['expandedSourceCount']);
        $t->same(1, $closure['outsideSourceCount']);
        $t->same(7, $closure['stopCount']);
        $t->same(1, $closure['externalStopCount']);
        $t->same(1, $closure['invalidStopCount']);
        $t->same(1, $closure['missingStopCount']);
        $t->same(1, $closure['relationshipPartStopCount']);
        $t->same(1, $closure['cycleStopCount']);
        $t->same(2, $closure['unloadedStopCount']);

        $sources = [];
        foreach ($closure['sources'] as $source) {
            $sources[$source['source']] = $source;
        }

        $t->same(true, $sources['/']['reachable']);
        $t->same(0, $sources['/']['depth']);
        $t->same('expanded', $sources['/']['closureAction']);
        $t->same(true, $sources['/word/document.xml']['reachable']);
        $t->same(1, $sources['/word/document.xml']['depth']);
        $t->same(true, $sources['/word/comments.xml']['reachable']);
        $t->same(2, $sources['/word/comments.xml']['depth']);
        $t->same(false, $sources['/docProps/core.xml']['reachable']);
        $t->same(null, $sources['/docProps/core.xml']['depth']);
        $t->same('outside-selected-closure', $sources['/docProps/core.xml']['closureAction']);

        $stops = [];
        foreach ($closure['stops'] as $stop) {
            $stops[$stop['id']] = $stop;
        }

        $t->same('target-source-not-loaded', $stops['rIdStyles']['stopReason']);
        $t->same('/word/styles.xml', $stops['rIdStyles']['targetPart']);
        $t->same(true, $stops['rIdStyles']['valid']);
        $t->same('missing-target', $stops['rIdMissingImage']['stopReason']);
        $t->same(false, $stops['rIdMissingImage']['exists']);
        $t->same(['missing-in-package'], $stops['rIdMissingImage']['issues']);
        $t->same('external-target', $stops['rIdUnsafeLink']['stopReason']);
        $t->same(['external-target-unsafe-scheme'], $stops['rIdUnsafeLink']['issues']);
        $t->same('relationship-part-target', $stops['rIdRelsTarget']['stopReason']);
        $t->same('/word/_rels/document.xml.rels', $stops['rIdRelsTarget']['targetPart']);
        $t->same('invalid-target', $stops['rIdEscape']['stopReason']);
        $t->same(['invalid-target', 'internal-target-package-root-traversal'], $stops['rIdEscape']['issues']);
        $t->same('cycle-target', $stops['rIdBackToDocument']['stopReason']);
        $t->same('/word/document.xml', $stops['rIdBackToDocument']['targetPart']);
        $t->same('target-source-not-loaded', $stops['rIdCommentImage']['stopReason']);
        $t->same('/word/media/comment.png', $stops['rIdCommentImage']['targetPart']);
    },
    'summarizes OPC relationship source closure coverage for non DOCX package review' => static function (TestRunner $t): void {
        $contentTypesXml = <<<'XML'
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>
  <Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>
  <Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>
  <Override PartName="/xl/drawings/drawing1.xml" ContentType="application/vnd.openxmlformats-officedocument.drawing+xml"/>
  <Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>
</Types>
XML;

        $packageRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>
  <Relationship Id="rIdCore" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>
</Relationships>
XML;

        $workbookRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdSheet1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>
  <Relationship Id="rIdStyles" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>
  <Relationship Id="rIdMissingTheme" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/theme" Target="theme/theme1.xml"/>
  <Relationship Id="rIdExternalReview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink" Target="https://example.test/sheet-source" TargetMode="External"/>
  <Relationship Id="rIdRelsPayload" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml" Target="_rels/workbook.xml.rels"/>
</Relationships>
XML;

        $worksheetRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdDrawing" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/drawing" Target="../drawings/drawing1.xml"/>
  <Relationship Id="rIdBackToWorkbook" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="../workbook.xml#cycle"/>
</Relationships>
XML;

        $coreRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdCorePreview" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="../xl/media/core.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'xl/workbook.xml', 'data' => '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'],
            ['name' => 'xl/_rels/workbook.xml.rels', 'data' => $workbookRelationshipsXml],
            ['name' => 'xl/worksheets/sheet1.xml', 'data' => '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'],
            ['name' => 'xl/worksheets/_rels/sheet1.xml.rels', 'data' => $worksheetRelationshipsXml],
            ['name' => 'xl/styles.xml', 'data' => '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"/>'],
            ['name' => 'xl/drawings/drawing1.xml', 'data' => '<xdr:wsDr xmlns:xdr="http://schemas.openxmlformats.org/drawingml/2006/spreadsheetDrawing"/>'],
            ['name' => 'docProps/core.xml', 'data' => '<cp:coreProperties/>'],
            ['name' => 'docProps/_rels/core.xml.rels', 'data' => $coreRelationshipsXml],
        ]));

        $summary = $graph->relationshipSourceClosureCoverageSummary(
            '/',
            OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE
        );

        $t->same('/', $summary['source']);
        $t->same(OpcRelationshipGraph::OFFICE_DOCUMENT_RELATIONSHIP_TYPE, $summary['relationshipType']);
        $t->same(false, $summary['valid']);
        $t->same(['missing-in-package', 'targets-relationship-part'], $summary['issues']);
        $t->same(4, $summary['sourceCount']);
        $t->same(3, $summary['expandedSourceCount']);
        $t->same(1, $summary['outsideSourceCount']);
        $t->same(6, $summary['stopCount']);
        $t->same([
            '/',
            '/xl/workbook.xml',
            '/xl/worksheets/sheet1.xml',
        ], $summary['expandedSourceNames']);
        $t->same(['/docProps/core.xml'], $summary['outsideSourceNames']);
        $t->same([
            '/' => 0,
            '/xl/workbook.xml' => 1,
            '/xl/worksheets/sheet1.xml' => 2,
        ], $summary['sourceDepths']);
        $t->same([
            'cycle-target' => 1,
            'external-target' => 1,
            'missing-target' => 1,
            'relationship-part-target' => 1,
            'target-source-not-loaded' => 2,
        ], $summary['stopReasonCounts']);
        $t->same(['rIdBackToWorkbook'], $summary['stopIdsByReason']['cycle-target']);
        $t->same(['rIdDrawing', 'rIdStyles'], $summary['stopIdsByReason']['target-source-not-loaded']);
        $t->same(['/xl/theme/theme1.xml'], $summary['missingTargetParts']);
        $t->same(['/xl/_rels/workbook.xml.rels'], $summary['relationshipPartTargetParts']);
        $t->same(['/xl/drawings/drawing1.xml', '/xl/styles.xml'], $summary['unloadedTargetSources']);
        $t->same(['https://example.test/sheet-source'], $summary['externalTargets']);
        $t->same(['rIdMissingTheme', 'rIdRelsPayload'], $summary['invalidStopIds']);
        $t->same(2, $summary['invalidStopCount']);
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
        $utf8Name = "\u{00E9}" . 'preuve.png';
        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship('rIdStyles', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles', 'styles.xml'));
        $relationships->add(new OpcRelationship('rIdReviewImage', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', 'media/review source ' . $utf8Name . '#crop'));
        $relationships->add(new OpcRelationship('rIdSource', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink', 'https://example.test/source', OpcRelationship::TARGET_MODE_EXTERNAL));

        $xml = $relationships->toXml();
        $t->contains('xmlns="' . OpcRelationships::NAMESPACE_URI . '"', $xml);
        $t->contains('Id="rIdStyles"', $xml);
        $t->contains('Target="styles.xml"', $xml);
        $t->contains('Target="media/review%20source%20%C3%A9preuve.png#crop"', $xml);
        $t->same(false, str_contains($xml, 'Target="media/review source '));
        $t->contains('TargetMode="External"', $xml);
        $t->same(false, str_contains($xml, 'TargetMode="Internal"'));

        $roundTrip = OpcRelationships::fromXml($xml, '/word/document.xml');
        $t->same('/word/styles.xml', $roundTrip->resolveTarget('rIdStyles'));
        $t->same('/word/media/review source ' . $utf8Name . '#crop', $roundTrip->resolveTarget('rIdReviewImage'));
        $t->same('https://example.test/source', $roundTrip->resolveTarget('rIdSource'));
        $t->true($roundTrip->byId('rIdSource')?->isExternal() ?? false);
    },
    'rejects unsafe external OPC relationship targets during XML serialization' => static function (TestRunner $t): void {
        $safe = new OpcRelationships('/word/document.xml');
        $safe->add(new OpcRelationship(
            'rIdExternalEncodedSpace',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
            'https://example.test/source%20packet.html?post=42#review',
            OpcRelationship::TARGET_MODE_EXTERNAL
        ));
        $safe->add(new OpcRelationship(
            'rIdExternalRelative',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
            'review/source.html#packet',
            OpcRelationship::TARGET_MODE_EXTERNAL
        ));

        $xml = $safe->toXml();
        $t->contains('Target="https://example.test/source%20packet.html?post=42#review"', $xml);
        $t->contains('Target="review/source.html#packet"', $xml);

        foreach ([
            'rawSpace' => [
                'target' => 'https://example.test/source packet.html',
                'issue' => 'external-target-invalid-uri-byte',
            ],
            'badPercentEscape' => [
                'target' => 'https://example.test/source%ZZpacket.html',
                'issue' => 'external-target-malformed-percent-escape',
            ],
            'encodedControlByte' => [
                'target' => 'https://example.test/source%00packet.html',
                'issue' => 'external-target-unsafe-percent-encoded-byte',
            ],
            'unsafeScheme' => [
                'target' => 'javascript:alert(1)',
                'issue' => 'external-target-unsafe-scheme',
            ],
        ] as $label => $case) {
            $relationships = new OpcRelationships('/word/document.xml');
            $relationships->add(new OpcRelationship(
                'rId' . ucfirst($label),
                'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink',
                $case['target'],
                OpcRelationship::TARGET_MODE_EXTERNAL
            ));

            try {
                $relationships->toXml();
                $t->true(false, 'Unsafe external OPC target serialized: ' . $label);
            } catch (\InvalidArgumentException $exception) {
                $t->contains($case['issue'], $exception->getMessage());
            }
        }
    },
    'serializes OPC internal relationship target path bytes as URI escaped XML attributes' => static function (TestRunner $t): void {
        $utf8Name = "\u{00E9}" . 'preuve.png';
        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship(
            'rIdReviewImage',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image',
            'media/raw review ' . $utf8Name . '#crop'
        ));
        $relationships->add(new OpcRelationship(
            'rIdCustomXml',
            'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml',
            '../customXml/source%20data.xml?state=ready#packet'
        ));

        $xml = $relationships->toXml();
        $t->contains('Target="media/raw%20review%20%C3%A9preuve.png#crop"', $xml);
        $t->contains('Target="../customXml/source%20data.xml?state=ready#packet"', $xml);
        $t->same(false, str_contains($xml, 'Target="media/raw review '));
        $t->same(false, str_contains($xml, 'TargetMode="Internal"'));

        $roundTrip = OpcRelationships::fromXml($xml, '/word/document.xml');
        $t->same('/word/media/raw review ' . $utf8Name . '#crop', $roundTrip->resolveTarget('rIdReviewImage'));
        $t->same('/customXml/source data.xml?state=ready#packet', $roundTrip->resolveTarget('rIdCustomXml'));

        $bad = new OpcRelationships('/word/document.xml');
        $bad->add(new OpcRelationship('rIdBadEscape', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', 'media/source%ZZ.png'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $bad->toXml());
    },
    'rejects non round trippable OPC internal relationship targets during XML serialization' => static function (TestRunner $t): void {
        foreach ([
            'rIdEncodedSlash' => 'media%2Fhidden.png',
            'rIdEncodedDotSegment' => 'media/%2E%2E/styles.xml',
            'rIdEncodedBackslash' => 'media%5Chidden.png',
            'rIdEncodedNul' => 'media%00hidden.png',
            'rIdTrailingDotSegment' => 'media/trailing./image.png',
            'rIdTraversal' => '../../evil.xml',
            'rIdAuthority' => '//cdn.example.test/review.png',
        ] as $id => $target) {
            $unsafe = new OpcRelationships('/word/document.xml');
            $unsafe->add(new OpcRelationship($id, 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', $target));
            $t->throws(\InvalidArgumentException::class, static fn (): string => $unsafe->toXml());
        }

        $rootFragment = new OpcRelationships('/');
        $rootFragment->add(new OpcRelationship('rIdRootFragment', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/hyperlink', '#root-fragment'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $rootFragment->toXml());

        $rootQuery = new OpcRelationships('/');
        $rootQuery->add(new OpcRelationship('rIdRootQuery', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/customXml', '?review=packet'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $rootQuery->toXml());
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
        $relationships->add(new OpcRelationship('rIdBadQueryEscape', 't', 'media/image.png?review=%ZZ'));
        $relationships->add(new OpcRelationship('rIdBadFragmentEscape', 't', 'media/image.png#review%ZZ'));
        $relationships->add(new OpcRelationship('rIdAuthority', 't', '//example.test/media.png'));
        $relationships->add(new OpcRelationship('rIdEncodedSlash', 't', 'media%2Fhidden.png'));
        $relationships->add(new OpcRelationship('rIdEncodedBackslash', 't', 'media%5Chidden.png'));
        $relationships->add(new OpcRelationship('rIdEncodedNul', 't', 'media%00hidden.png'));
        $relationships->add(new OpcRelationship('rIdEncodedQueryNul', 't', 'media/image.png?review=%00'));
        $relationships->add(new OpcRelationship('rIdEncodedFragmentDelete', 't', 'media/image.png#review%7F'));
        $relationships->add(new OpcRelationship('rIdTrailingDotSegment', 't', 'media/trailing./image.png'));

        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdBadEscape'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdBadQueryEscape'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdBadFragmentEscape'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdAuthority'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedSlash'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedBackslash'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedNul'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedQueryNul'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEncodedFragmentDelete'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdTrailingDotSegment'));
    },
    'rejects raw whitespace in internal OPC relationship target URI references' => static function (TestRunner $t): void {
        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship('rIdRawSpace', 't', 'media/raw space.png'));
        $relationships->add(new OpcRelationship('rIdRawTab', 't', "media/raw\tname.png"));
        $relationships->add(new OpcRelationship('rIdEncodedSpace', 't', 'media/raw%20space.png'));

        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdRawSpace'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdRawTab'));
        $t->same('/word/media/raw space.png', $relationships->resolveTarget('rIdEncodedSpace'));
    },
    'rejects empty OPC package path segments before relationship preflight normalization' => static function (TestRunner $t) use ($contentTypesXml, $packageRelationshipsXml): void {
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/word//document.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/word/document.xml/'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartNameFromUri('/word/media//image.png'));

        $relationships = new OpcRelationships('/word/document.xml');
        $relationships->add(new OpcRelationship('rIdEmptySegment', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships/image', 'media//image.png'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => $relationships->resolveTarget('rIdEmptySegment'));

        $documentRelationshipsXml = <<<'XML'
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rIdEmptySegment" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media//image.png"/>
</Relationships>
XML;

        $graph = OpcRelationshipGraph::fromPackage(ZipPackage::fromParts([
            ['name' => '[Content_Types].xml', 'data' => $contentTypesXml],
            ['name' => '_rels/.rels', 'data' => $packageRelationshipsXml],
            ['name' => 'word/document.xml', 'data' => '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"/>'],
            ['name' => 'word/_rels/document.xml.rels', 'data' => $documentRelationshipsXml],
            ['name' => 'word/media/image.png', 'data' => 'PNG'],
        ]));

        $targets = [];
        foreach ($graph->preflightTargetsForSource('/word/document.xml') as $target) {
            $targets[$target['id']] = $target;
        }

        $t->same(false, $targets['rIdEmptySegment']['valid']);
        $t->same(['invalid-target', 'internal-target-empty-path-segment'], $targets['rIdEmptySegment']['issues']);
        $t->same(null, $targets['rIdEmptySegment']['exists']);
        $t->same(null, $targets['rIdEmptySegment']['contentType']);
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
        $t->same('/', OpcPackagePath::canonicalPartName('/.', true));
        $t->same('/', OpcPackagePath::canonicalPartName('/word/..', true));
        $t->same('/word/styles.xml#section', OpcPackagePath::resolveInternalTarget('/word/document.xml', './styles.xml#section'));
        $t->same('/media/image.png?variant=review', OpcPackagePath::resolveInternalTarget('/word/document.xml', '../media/image.png?variant=review'));
        $t->same('/word/styles.xml?review=%20ready#note%20one', OpcPackagePath::resolveInternalTarget('/word/document.xml', 'styles.xml?review=%20ready#note%20one'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/.'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/word/..'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('word/..'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartNameFromUri('/word/..'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/word/document.xml#frag'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::canonicalPartName('/word/trailing./document.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', ''));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', '..'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', '../../evil.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', 'file:///tmp/evil.xml'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', 'media/trailing./image.png'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', 'styles.xml?review=%ZZ'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', 'styles.xml#note%ZZ'));
        $t->throws(\InvalidArgumentException::class, static fn (): string => OpcPackagePath::resolveInternalTarget('/word/document.xml', 'styles.xml?review=%00'));
    },
];
