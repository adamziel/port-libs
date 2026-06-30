<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

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
      <text:p>ZIP extra field provenance packet.</text:p>
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
    <dc:title>ZIP Extra Field Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$extraField = static fn (int $id, string $data): string => pack('vva*', $id, strlen($data), $data);
$contentExtraFields = $extraField(0xcafe, 'odf-review') . $extraField(0xda7a, 'struct');
$heroExtraFields = $extraField(0xbeef, 'hero-meta');
$privateExtraFields = $extraField(0xcafe, 'private-note');

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 8],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8, 'extraFieldData' => $contentExtraFields],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0, 'extraFieldData' => $heroExtraFields],
    ['name' => 'Notes/private.bin', 'data' => 'PRIVATE-BYTES', 'compressionMethod' => 0, 'extraFieldData' => $privateExtraFields],
];

return [
    'surfaces ODT ZIP extra field provenance without exposing payload bytes' => static function (TestRunner $t) use ($parts, $contentExtraFields, $heroExtraFields, $privateExtraFields): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts($parts, 'odt extra field review'));
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $identity = $provenance['packageIdentity'];
        $inventory = $provenance['parts'];

        $extraFieldEntries = [];
        foreach ($provenance['extraFields']['entries'] as $entry) {
            $extraFieldEntries[$entry['name']] = $entry;
        }

        $identityEntries = [];
        foreach ($identity['packageEntries'] as $entry) {
            $identityEntries[$entry['part']] = $entry;
        }

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same(7, $provenance['entryCount']);
        $t->same(3, $provenance['extraFieldEntryCount']);
        $t->same(0, $provenance['duplicateExtraFieldEntryCount']);
        $t->same(0, $provenance['mismatchedExtraFieldEntryCount']);
        $t->same(0, $provenance['mismatchedExtraFieldValueEntryCount']);
        $t->same(3, $provenance['extraFieldIdCount']);
        $t->same(3, $provenance['centralExtraFieldIdCount']);
        $t->same(3, $provenance['localExtraFieldIdCount']);
        $t->same(3, $provenance['sharedExtraFieldIdCount']);
        $t->same(0, $provenance['centralOnlyExtraFieldIdCount']);
        $t->same(0, $provenance['localOnlyExtraFieldIdCount']);
        $t->same($provenance['extraFieldEntryCount'], $identity['extraFieldEntryCount']);
        $t->same($provenance['extraFieldIdUsage'], $identity['extraFieldIdUsage']);

        $t->same([0xcafe, 0xda7a], $inventory['content.xml']['centralExtraFieldIds']);
        $t->same([0xcafe, 0xda7a], $inventory['content.xml']['localExtraFieldIds']);
        $t->same(strlen($contentExtraFields), $inventory['content.xml']['centralExtraFieldLength']);
        $t->same(strlen($contentExtraFields), $inventory['content.xml']['localExtraFieldLength']);
        $t->same(2, $inventory['content.xml']['centralExtraFieldRecordCount']);
        $t->same([
            ['id' => 0xcafe, 'idHex' => 'cafe', 'dataLength' => strlen('odf-review')],
            ['id' => 0xda7a, 'idHex' => 'da7a', 'dataLength' => strlen('struct')],
        ], $inventory['content.xml']['centralExtraFieldRecords']);
        $t->same($inventory['content.xml']['centralExtraFieldRecords'], $inventory['content.xml']['localExtraFieldRecords']);
        $t->same(true, $inventory['content.xml']['hasZipExtraFieldProvenance']);
        $t->same(false, $inventory['content.xml']['hasDuplicateExtraFieldIds']);
        $t->same(false, $inventory['content.xml']['hasMismatchedExtraFieldIds']);
        $t->same(false, $inventory['content.xml']['hasMismatchedExtraFieldValues']);

        $t->same([0xbeef], $inventory['Pictures/hero.png']['centralExtraFieldIds']);
        $t->same(strlen($heroExtraFields), $inventory['Pictures/hero.png']['centralExtraFieldLength']);
        $t->same([0xcafe], $inventory['Notes/private.bin']['centralExtraFieldIds']);
        $t->same(strlen($privateExtraFields), $inventory['Notes/private.bin']['localExtraFieldLength']);
        $t->same(true, $inventory['Notes/private.bin']['undeclared']);
        $t->same(false, $inventory['Notes/private.bin']['canExposeBytes']);
        $t->same('undeclared-package-entry-no-bytes', $inventory['Notes/private.bin']['byteExposurePolicy']);

        $t->same([0xcafe, 0xda7a], $extraFieldEntries['content.xml']['centralExtraFieldIds']);
        $t->same([0xcafe, 0xda7a], $extraFieldEntries['content.xml']['localExtraFieldIds']);
        $t->same([0xbeef], $extraFieldEntries['Pictures/hero.png']['centralExtraFieldIds']);
        $t->same([0xcafe], $extraFieldEntries['Notes/private.bin']['localExtraFieldIds']);
        $t->same('0xbeef', $provenance['extraFieldIdUsage'][0]['idHex']);
        $t->same(['Pictures/hero.png'], $provenance['extraFieldIdUsage'][0]['centralEntryNames']);
        $t->same('0xcafe', $provenance['extraFieldIdUsage'][1]['idHex']);
        $t->same(['content.xml', 'Notes/private.bin'], $provenance['extraFieldIdUsage'][1]['centralEntryNames']);
        $t->same('0xda7a', $provenance['extraFieldIdUsage'][2]['idHex']);
        $t->same(['content.xml'], $provenance['extraFieldIdUsage'][2]['localEntryNames']);

        $t->same($inventory['content.xml']['centralExtraFieldRecords'], $identityEntries['content.xml']['centralExtraFieldRecords']);
        $t->same(true, $identityEntries['content.xml']['hasZipExtraFieldProvenance']);
        $t->same([0xcafe], $identityEntries['Notes/private.bin']['centralExtraFieldIds']);
        $t->same(true, $identityEntries['Notes/private.bin']['undeclared']);
        $t->same(false, $identityEntries['Notes/private.bin']['canExposeBytes']);
        $t->same('undeclared-package-entry-no-bytes', $identityEntries['Notes/private.bin']['byteExposurePolicy']);

        $encodedContentPart = json_encode($inventory['content.xml'], JSON_THROW_ON_ERROR);
        $encodedPrivatePart = json_encode($inventory['Notes/private.bin'], JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($encodedContentPart, 'odf-review'));
        $t->same(false, str_contains($encodedContentPart, 'struct'));
        $t->same(false, str_contains($encodedPrivatePart, 'private-note'));
    },
];
