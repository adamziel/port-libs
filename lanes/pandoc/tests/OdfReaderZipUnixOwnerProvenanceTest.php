<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/owner.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/mismatched.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>ZIP Unix owner provenance packet.</text:p>
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
    <dc:title>ZIP Unix Owner Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$packZipVariableUnsignedInteger = static function (int $value): string {
    if ($value < 0) {
        throw new RuntimeException('ZIP variable unsigned integer value must be non-negative');
    }

    $bytes = '';
    do {
        $bytes .= chr($value & 0xff);
        $value = intdiv($value, 256);
    } while ($value > 0);

    return $bytes;
};

$buildUnixOwnerExtra = static function (int $uid, int $gid) use ($packZipVariableUnsignedInteger): string {
    $uidBytes = $packZipVariableUnsignedInteger($uid);
    $gidBytes = $packZipVariableUnsignedInteger($gid);
    $payload = chr(1)
        . chr(strlen($uidBytes))
        . $uidBytes
        . chr(strlen($gidBytes))
        . $gidBytes;

    return pack('vv', 0x7875, strlen($payload)) . $payload;
};

$buildZipPackage = static function (array $parts): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $central = '';

    foreach ($parts as $part) {
        $name = $part['name'];
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $flags = $part['generalPurposeFlags'] ?? 0x0800;
        $localExtra = $part['localExtra'] ?? ($part['extraFieldData'] ?? '');
        $centralExtra = $part['centralExtra'] ?? ($part['extraFieldData'] ?? $localExtra);
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if ($compressed === false) {
            throw new RuntimeException("Unable to deflate {$name}");
        }

        $offset = strlen($body);
        $crc = $crc32($data);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            strlen($localExtra)
        ) . $name . $localExtra . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            $flags,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($name),
            strlen($centralExtra),
            0,
            0,
            0,
            $part['externalAttributes'] ?? (str_ends_with($name, '/') ? 0x10 : 0),
            $offset
        ) . $name . $centralExtra;
    }

    $centralOffset = strlen($body);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($parts), count($parts), strlen($central), $centralOffset, 0)
    );
};

$ownerExtra = $buildUnixOwnerExtra(1001, 1002);
$mismatchedCentralExtra = $buildUnixOwnerExtra(500, 501);
$mismatchedLocalExtra = $buildUnixOwnerExtra(600, 601);
$localOnlyExtra = $buildUnixOwnerExtra(33, 44);

$buildPackage = static fn (): ZipPackage => $buildZipPackage([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 8],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/owner.png', 'data' => 'OWNERPNG', 'compressionMethod' => 0, 'extraFieldData' => $ownerExtra],
    [
        'name' => 'Pictures/mismatched.png',
        'data' => 'MISMATCHEDPNG',
        'compressionMethod' => 0,
        'centralExtra' => $mismatchedCentralExtra,
        'localExtra' => $mismatchedLocalExtra,
    ],
    ['name' => 'Notes/local-owner.txt', 'data' => 'LOCALOWNER-BYTES', 'compressionMethod' => 0, 'centralExtra' => '', 'localExtra' => $localOnlyExtra],
]);

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        if (is_array($item) && is_string($item[$key] ?? null)) {
            $indexed[$item[$key]] = $item;
        }
    }

    return $indexed;
};

return [
    'surfaces ODT ZIP Unix owner extra-field provenance without exposing payload bytes' => static function (TestRunner $t) use ($buildPackage, $indexBy): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $identity = $provenance['packageIdentity'];
        $identityEntries = $indexBy($identity['packageEntries'], 'part');
        $compact = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compact['packageInventory'];
        $compactIdentity = $compact['packageIdentity'];
        $compactIdentityEntries = $indexBy($compactIdentity['packageEntries'], 'path');
        $preflight = $package->unixOwnerPreflight();

        $owner = ['version' => 1, 'uid' => 1001, 'gid' => 1002, 'uidByteLength' => 2, 'gidByteLength' => 2];
        $mismatchedCentralOwner = ['version' => 1, 'uid' => 500, 'gid' => 501, 'uidByteLength' => 2, 'gidByteLength' => 2];
        $mismatchedLocalOwner = ['version' => 1, 'uid' => 600, 'gid' => 601, 'uidByteLength' => 2, 'gidByteLength' => 2];
        $localOwner = ['version' => 1, 'uid' => 33, 'gid' => 44, 'uidByteLength' => 1, 'gidByteLength' => 1];

        $t->same($provenance, $result['document']->attr('manifest')['packageProvenance']);
        $t->same($preflight, $provenance['unixOwners']);
        $t->same($preflight, $compactInventory['unixOwners']);
        $t->same(3, $provenance['zipUnixOwnerMetadataEntryCount']);
        $t->same(2, $provenance['zipCentralUnixOwnerMetadataEntryCount']);
        $t->same(3, $provenance['zipLocalUnixOwnerMetadataEntryCount']);
        $t->same(1, $provenance['zipMismatchedUnixOwnerMetadataEntryCount']);
        $t->same(3, $identity['zipUnixOwnerMetadataEntryCount']);
        $t->same(1, $identity['zipMismatchedUnixOwnerMetadataEntryCount']);
        $t->same(3, $compactInventory['zipUnixOwnerMetadataEntryCount']);
        $t->same(2, $compactInventory['zipCentralUnixOwnerMetadataEntryCount']);
        $t->same(3, $compactIdentity['zipUnixOwnerMetadataEntryCount']);
        $t->same(1, $compactIdentity['zipMismatchedUnixOwnerMetadataEntryCount']);
        $t->same($preflight, $identity['unixOwners']);
        $t->same($preflight, $compactIdentity['unixOwners']);

        $richOwner = $provenance['parts']['Pictures/owner.png'];
        $t->same($owner, $richOwner['zipCentralUnixOwner']);
        $t->same($owner, $richOwner['zipLocalUnixOwner']);
        $t->same(true, $richOwner['zipHasCentralUnixOwner']);
        $t->same(true, $richOwner['zipHasLocalUnixOwner']);
        $t->same(true, $richOwner['zipUnixOwnerMetadataMatches']);
        $t->same([
            'central-unix-uid-gid-extra-field',
            'local-unix-uid-gid-extra-field',
        ], $richOwner['zipUnixOwnerIssues']);
        $t->same(true, $richOwner['hasZipUnixOwnerProvenance']);
        $t->same('package-bytes-exposable', $richOwner['byteExposurePolicy']);
        $t->same(true, $richOwner['canExposeBytes']);
        $t->same($owner, $identityEntries['Pictures/owner.png']['zipCentralUnixOwner']);
        $t->same(true, $identityEntries['Pictures/owner.png']['hasZipUnixOwnerProvenance']);
        $t->same($owner, $compactInventory['parts']['Pictures/owner.png']['zipLocalUnixOwner']);
        $t->same($owner, $compactIdentityEntries['Pictures/owner.png']['zipCentralUnixOwner']);

        $richMismatch = $provenance['parts']['Pictures/mismatched.png'];
        $t->same($mismatchedCentralOwner, $richMismatch['zipCentralUnixOwner']);
        $t->same($mismatchedLocalOwner, $richMismatch['zipLocalUnixOwner']);
        $t->same(false, $richMismatch['zipUnixOwnerMetadataMatches']);
        $t->same([
            'central-unix-uid-gid-extra-field',
            'local-unix-uid-gid-extra-field',
            'unix-uid-gid-mismatch',
        ], $richMismatch['zipUnixOwnerIssues']);
        $t->same(true, $richMismatch['hasZipUnixOwnerProvenance']);
        $t->same(false, $identityEntries['Pictures/mismatched.png']['zipUnixOwnerMetadataMatches']);
        $t->same($richMismatch['zipUnixOwnerIssues'], $identityEntries['Pictures/mismatched.png']['zipUnixOwnerIssues']);
        $t->same($mismatchedLocalOwner, $compactInventory['parts']['Pictures/mismatched.png']['zipLocalUnixOwner']);
        $t->same(false, $compactIdentityEntries['Pictures/mismatched.png']['zipUnixOwnerMetadataMatches']);

        $localOnly = $provenance['parts']['Notes/local-owner.txt'];
        $t->same(null, $localOnly['zipCentralUnixOwner']);
        $t->same($localOwner, $localOnly['zipLocalUnixOwner']);
        $t->same(false, $localOnly['zipHasCentralUnixOwner']);
        $t->same(true, $localOnly['zipHasLocalUnixOwner']);
        $t->same(true, $localOnly['zipUnixOwnerMetadataMatches']);
        $t->same(['local-unix-uid-gid-extra-field'], $localOnly['zipUnixOwnerIssues']);
        $t->same(true, $localOnly['hasZipUnixOwnerProvenance']);
        $t->same(true, $localOnly['undeclared']);
        $t->same(false, $localOnly['canExposeBytes']);
        $t->same('undeclared-package-entry-no-bytes', $localOnly['byteExposurePolicy']);
        $t->same($localOwner, $identityEntries['Notes/local-owner.txt']['zipLocalUnixOwner']);
        $t->same(true, $identityEntries['Notes/local-owner.txt']['undeclared']);
        $t->same(false, $identityEntries['Notes/local-owner.txt']['canExposeBytes']);
        $t->same($localOwner, $compactInventory['parts']['Notes/local-owner.txt']['zipLocalUnixOwner']);
        $t->same($localOwner, $compactIdentityEntries['Notes/local-owner.txt']['zipLocalUnixOwner']);

        $encodedLocalOnly = json_encode($localOnly, JSON_THROW_ON_ERROR);
        $encodedCompactLocalOnly = json_encode($compactInventory['parts']['Notes/local-owner.txt'], JSON_THROW_ON_ERROR);
        $t->same(false, str_contains($encodedLocalOnly, 'LOCALOWNER-BYTES'));
        $t->same(false, str_contains($encodedCompactLocalOnly, 'LOCALOWNER-BYTES'));
    },
];
