<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$contentModifiedAt = 1780479017;
$imageModifiedAt = 1780479078;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Timestamped package review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0"
  office:version="1.3">
  <office:styles>
    <style:style style:name="BodyText" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Timestamp Provenance Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0, 'modifiedAt' => $contentModifiedAt],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0, 'modifiedAt' => $imageModifiedAt],
], 'odt timestamp provenance package');

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $value = $item[$key] ?? null;
        if (is_string($value) && $value !== '') {
            $indexed[$value] = $item;
        }
    }

    return $indexed;
};

return [
    'carries ODT ZIP timestamp provenance through rich package review' => static function (TestRunner $t) use (
        $buildPackage,
        $contentModifiedAt,
        $imageModifiedAt,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $parts = $provenance['parts'];
        $timestampItems = $indexBy($provenance['zipTimestamps']['items'], 'part');

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same($package->modificationTimePreflight(), $provenance['modificationTimes']);
        $t->same(2, $provenance['zipTimestampEntryCount']);
        $t->same(2, $provenance['zipDosTimestampEntryCount']);
        $t->same(2, $provenance['zipExtendedTimestampEntryCount']);
        $t->same(0, $provenance['zipNtfsTimestampEntryCount']);
        $t->same(0, $provenance['zipInvalidDosTimestampEntryCount']);
        $t->same([], $provenance['zipInvalidDosTimestampEntries']);

        $t->same(6, $provenance['zipTimestamps']['entryCount']);
        $t->same(2, $provenance['zipTimestamps']['timestampEntryCount']);
        $t->same(['extended-timestamp' => 2], $provenance['zipTimestamps']['timestampSourceCounts']);
        $t->same([], $provenance['zipTimestamps']['issueCounts']);
        $t->same(false, $provenance['zipTimestamps']['canExposeBytes']);
        $t->same('zip-timestamp-provenance-metadata-only', $provenance['zipTimestamps']['byteExposurePolicy']);

        $contentPart = $parts['content.xml'];
        $t->same($contentModifiedAt, $contentPart['zipModifiedAt']);
        $t->same('extended-timestamp', $contentPart['zipTimestampSource']);
        $t->same(true, $contentPart['zipHasDosTimestamp']);
        $t->same(true, $contentPart['zipIsDosTimestampValid']);
        $t->same($contentModifiedAt, $contentPart['zipExtendedModifiedAt']);
        $t->same($contentModifiedAt, $contentPart['zipCentralModifiedAt']);
        $t->same('extended-timestamp', $contentPart['zipCentralTimestampSource']);
        $t->same($contentModifiedAt, $contentPart['zipLocalModifiedAt']);
        $t->same('extended-timestamp', $contentPart['zipLocalTimestampSource']);
        $t->same([], $contentPart['zipTimestampIssues']);

        $imagePart = $parts['Pictures/hero.png'];
        $t->same($imageModifiedAt, $imagePart['zipModifiedAt']);
        $t->same('extended-timestamp', $imagePart['zipTimestampSource']);
        $t->same('package-bytes-exposable', $imagePart['byteExposurePolicy']);
        $t->same(['manifest-declared', 'media-resource'], $imagePart['roles']);
        $t->same('Pictures/hero.png', $result['media'][0]['part']);

        $t->same($contentModifiedAt, $timestampItems['content.xml']['zipModifiedAt']);
        $t->same($parts['content.xml']['roles'], $timestampItems['content.xml']['roles']);
        $t->same($imageModifiedAt, $timestampItems['Pictures/hero.png']['zipModifiedAt']);
        $t->same('package-bytes-exposable', $timestampItems['Pictures/hero.png']['byteExposurePolicy']);
        $t->same(null, $timestampItems['meta.xml']['zipModifiedAt'] ?? null);
        $t->same(null, $parts['meta.xml']['zipTimestampSource']);
    },
];
