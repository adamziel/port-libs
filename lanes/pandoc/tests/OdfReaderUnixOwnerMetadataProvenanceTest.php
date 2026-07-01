<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text" manifest:version="1.3"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/matching-owner.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/local-owner.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/mismatched-owner.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Unix owner metadata provenance packet.</text:p>
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
    <style:style style:name="OwnerMetadataBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/"
  office:version="1.3">
  <office:meta>
    <dc:title>Unix Owner Metadata Provenance</dc:title>
  </office:meta>
</office:document-meta>
XML;

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
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

$owner = static function (int $uid, int $gid): array {
    $byteLength = static function (int $value): int {
        $length = 0;
        do {
            ++$length;
            $value = intdiv($value, 256);
        } while ($value > 0);

        return $length;
    };

    return [
        'version' => 1,
        'uid' => $uid,
        'gid' => $gid,
        'uidByteLength' => $byteLength($uid),
        'gidByteLength' => $byteLength($gid),
    ];
};

$buildZipPackage = static function (array $entries, string $comment = '') use ($crc32): string {
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'] ?? '';
        $method = $entry['method'] ?? 0;
        $flags = $entry['flags'] ?? 0x0800;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if (!is_string($compressed)) {
            throw new RuntimeException("Unable to deflate ZIP entry {$name}");
        }

        $localExtra = $entry['localExtra'] ?? '';
        $centralExtra = $entry['centralExtra'] ?? $localExtra;
        $offset = strlen($body);
        $crc = $crc32($data);
        $externalAttributes = $entry['externalAttributes'] ?? 0;

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
        );
        $body .= $name . $localExtra . $compressed;

        $central .= pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            $entry['versionMadeBy'] ?? 0x0314,
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
            $entry['internalAttributes'] ?? 0,
            $externalAttributes,
            $offset
        );
        $central .= $name . $centralExtra;
    }

    $centralOffset = strlen($body);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $centralOffset, strlen($comment))
        . $comment;
};

$buildPackage = static function () use (
    $buildZipPackage,
    $buildUnixOwnerExtra,
    $contentXml,
    $manifestXml,
    $metaXml,
    $stylesXml
): ZipPackage {
    return ZipPackage::fromString($buildZipPackage([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'method' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'method' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'method' => 8],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'method' => 8],
        ['name' => 'meta.xml', 'data' => $metaXml, 'method' => 0],
        [
            'name' => 'Pictures/matching-owner.png',
            'data' => 'MATCHING-OWNER-PNG',
            'method' => 0,
            'localExtra' => $buildUnixOwnerExtra(1001, 1002),
            'centralExtra' => $buildUnixOwnerExtra(1001, 1002),
            'externalAttributes' => 0x81a40000,
        ],
        [
            'name' => 'Pictures/local-owner.png',
            'data' => 'LOCAL-OWNER-PNG',
            'method' => 0,
            'localExtra' => $buildUnixOwnerExtra(33, 44),
            'centralExtra' => '',
            'externalAttributes' => 0x81a40000,
        ],
        [
            'name' => 'Pictures/mismatched-owner.png',
            'data' => 'MISMATCHED-OWNER-PNG',
            'method' => 0,
            'localExtra' => $buildUnixOwnerExtra(52, 53),
            'centralExtra' => $buildUnixOwnerExtra(50, 51),
            'externalAttributes' => 0x81a40000,
        ],
    ], 'odt unix owner metadata provenance'));
};

$indexByPart = static function (array $entries): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $part = $entry['part'] ?? ($entry['path'] ?? null);
        if (is_string($part) && $part !== '') {
            $indexed[$part] = $entry;
        }
    }

    return $indexed;
};

return [
    'carries ODT Unix UID/GID owner extra-field metadata through package provenance' => static function (TestRunner $t) use ($buildPackage, $indexByPart, $owner): void {
        $package = $buildPackage();
        $ownerPreflight = $package->unixOwnerPreflight();
        $compact = OpenDocumentPackage::fromPackage($package)->summarize();
        $rich = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $rich['importReport']['manifest']['packageProvenance'];
        $documentProvenance = $rich['document']->attr('manifest')['packageProvenance'];
        $compactInventory = $compact['packageInventory'];
        $compactIdentity = $compact['packageIdentity'];
        $richIdentity = $richProvenance['packageIdentity'];
        $compactIdentityParts = $indexByPart($compactIdentity['packageEntries']);
        $richIdentityParts = $indexByPart($richIdentity['packageEntries']);

        $t->same($ownerPreflight, $compactInventory['unixOwners']);
        $t->same($ownerPreflight, $richProvenance['unixOwners']);
        $t->same($richProvenance, $documentProvenance);

        foreach ([$compactInventory, $compactIdentity, $richProvenance, $richIdentity] as $handoff) {
            $t->same(true, $handoff['hasUnixOwnerMetadata']);
            $t->same(true, $handoff['hasMismatchedUnixOwnerMetadata']);
            $t->same(3, $handoff['unixOwnerMetadataEntryCount']);
            $t->same(2, $handoff['centralUnixOwnerMetadataEntryCount']);
            $t->same(3, $handoff['localUnixOwnerMetadataEntryCount']);
            $t->same(1, $handoff['mismatchedUnixOwnerMetadataEntryCount']);
            $t->same(['unix-owner-extra-fields', 'unix-uid-gid-mismatch'], $handoff['unixOwnerMetadataIssueCodes']);
            $t->same('zip-unix-owner-metadata-only', $handoff['unixOwnerMetadataByteExposurePolicy']);
            $t->same(false, $handoff['unixOwnerMetadataCanExposeBytes']);
        }

        $compactParts = $compactInventory['parts'];
        $richParts = $richProvenance['parts'];
        foreach ([$compactParts, $richParts, $compactIdentityParts, $richIdentityParts] as $parts) {
            $matching = $parts['Pictures/matching-owner.png'];
            $localOnly = $parts['Pictures/local-owner.png'];
            $mismatch = $parts['Pictures/mismatched-owner.png'];

            $t->same($owner(1001, 1002), $matching['centralUnixOwner']);
            $t->same($owner(1001, 1002), $matching['localUnixOwner']);
            $t->same(true, $matching['hasCentralUnixOwnerMetadata']);
            $t->same(true, $matching['hasLocalUnixOwnerMetadata']);
            $t->same(true, $matching['hasUnixOwnerMetadata']);
            $t->same(true, $matching['unixOwnerMetadataMatches']);
            $t->same(['central-unix-uid-gid-extra-field', 'local-unix-uid-gid-extra-field'], $matching['unixOwnerMetadataIssues']);
            $t->same('zip-unix-owner-metadata-only', $matching['unixOwnerMetadataByteExposurePolicy']);
            $t->same(false, $matching['unixOwnerMetadataCanExposeBytes']);

            $t->same(null, $localOnly['centralUnixOwner'] ?? null);
            $t->same($owner(33, 44), $localOnly['localUnixOwner']);
            $t->same(false, $localOnly['hasCentralUnixOwnerMetadata']);
            $t->same(true, $localOnly['hasLocalUnixOwnerMetadata']);
            $t->same(true, $localOnly['hasUnixOwnerMetadata']);
            $t->same(true, $localOnly['unixOwnerMetadataMatches']);
            $t->same(['local-unix-uid-gid-extra-field'], $localOnly['unixOwnerMetadataIssues']);

            $t->same($owner(50, 51), $mismatch['centralUnixOwner']);
            $t->same($owner(52, 53), $mismatch['localUnixOwner']);
            $t->same(true, $mismatch['hasCentralUnixOwnerMetadata']);
            $t->same(true, $mismatch['hasLocalUnixOwnerMetadata']);
            $t->same(false, $mismatch['unixOwnerMetadataMatches']);
            $t->same([
                'central-unix-uid-gid-extra-field',
                'local-unix-uid-gid-extra-field',
                'unix-uid-gid-mismatch',
            ], $mismatch['unixOwnerMetadataIssues']);
            $t->same(false, array_key_exists('contents', $mismatch));
        }

        $t->same(
            ['Pictures/matching-owner.png', 'Pictures/local-owner.png', 'Pictures/mismatched-owner.png'],
            array_column($compactInventory['unixOwnerMetadataEntries'], 'name')
        );
        $t->same(
            ['Pictures/mismatched-owner.png'],
            array_column($richIdentity['mismatchedUnixOwnerMetadataEntries'], 'name')
        );
        $t->same(false, array_key_exists('signatureData', $compactIdentity));
        $t->same(false, $richIdentity['canExposeBytes']);
        $t->same('odf-package-identity-metadata-only', $richIdentity['byteExposurePolicy']);
    },
];
