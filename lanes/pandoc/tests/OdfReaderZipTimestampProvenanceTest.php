<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/timestamped.png" manifest:media-type="image/png" manifest:size="14"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Timestamp provenance packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Timestamp Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static function (int $modifiedAt) use ($manifestXml, $contentXml, $stylesXml, $metaXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
        ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
        [
            'name' => 'Pictures/timestamped.png',
            'data' => 'TIMESTAMPEDPNG',
            'compressionMethod' => 0,
            'modifiedAt' => $modifiedAt,
        ],
    ], 'odt timestamp provenance');
};

return [
    'preserves rich ODT ZIP timestamp provenance in package review handoff' => static function (TestRunner $t) use ($buildPackage): void {
        $modifiedAt = 1780479017;
        $result = (new OdfReader())->readPackage($buildPackage($modifiedAt));
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $documentProvenance = $result['document']->attr('manifest')['packageProvenance'];
        $part = $provenance['parts']['Pictures/timestamped.png'];
        $timestampEntry = null;
        foreach ($provenance['modificationTimes']['entries'] as $entry) {
            if (($entry['name'] ?? null) === 'Pictures/timestamped.png') {
                $timestampEntry = $entry;
                break;
            }
        }
        $identityPart = null;
        foreach ($provenance['packageIdentity']['packageEntries'] as $entry) {
            if (($entry['part'] ?? null) === 'Pictures/timestamped.png') {
                $identityPart = $entry;
                break;
            }
        }
        $changedIdentity = (new OdfReader())
            ->readPackage($buildPackage($modifiedAt + 2))['importReport']['manifest']['packageProvenance']['packageIdentity'];

        $t->same($provenance['modificationTimes'], $documentProvenance['modificationTimes']);
        $t->same(1, $provenance['zipTimestampEntryCount']);
        $t->same(1, $provenance['zipDosTimestampEntryCount']);
        $t->same(1, $provenance['zipExtendedTimestampEntryCount']);
        $t->same(0, $provenance['zipNtfsTimestampEntryCount']);
        $t->same(0, $provenance['zipInvalidDosTimestampEntryCount']);
        $t->same([], $provenance['zipInvalidDosTimestampEntries']);
        $t->true(is_array($timestampEntry), 'modification-time preflight must carry timestamped media row');
        $t->same($modifiedAt, $timestampEntry['modifiedAt']);
        $t->same('extended-timestamp', $timestampEntry['timestampSource']);

        $t->same($modifiedAt, $part['zipModifiedAt']);
        $t->same('extended-timestamp', $part['zipTimestampSource']);
        $t->same(true, $part['zipHasDosTimestamp']);
        $t->same(true, $part['zipIsDosTimestampValid']);
        $t->same($modifiedAt, $part['zipExtendedModifiedAt']);
        $t->same($modifiedAt, $part['zipCentralModifiedAt']);
        $t->same('extended-timestamp', $part['zipCentralTimestampSource']);
        $t->same($modifiedAt, $part['zipLocalExtendedModifiedAt']);
        $t->same($modifiedAt, $part['zipLocalModifiedAt']);
        $t->same('extended-timestamp', $part['zipLocalTimestampSource']);
        $t->same([], $part['zipTimestampIssues']);
        $t->same('package-bytes-exposable', $part['byteExposurePolicy']);
        $t->same(['manifest-declared', 'media-resource'], $part['roles']);

        $t->true(is_array($identityPart), 'package identity must carry timestamped package entry');
        $t->same($modifiedAt, $identityPart['zipModifiedAt']);
        $t->same('extended-timestamp', $identityPart['zipTimestampSource']);
        $t->same(1, $provenance['packageIdentity']['zipTimestampEntryCount']);
        $t->same(1, $provenance['packageIdentity']['zipExtendedTimestampEntryCount']);
        $t->true($provenance['packageIdentity']['identitySha256'] !== $changedIdentity['identitySha256']);
        $t->same(false, $provenance['packageIdentity']['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $provenance['packageIdentity']['byteExposurePolicy']);
    },
];
