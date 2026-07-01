<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxOpenXmlReader;
use PortLibs\Pandoc\ZipPackage;

$docxUnixOwnerCrc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
$docxUnixOwnerPackVariableUnsignedInteger = static function (int $value): string {
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
$docxUnixOwnerExtra = static function (int $uid, int $gid) use ($docxUnixOwnerPackVariableUnsignedInteger): string {
    $uidBytes = $docxUnixOwnerPackVariableUnsignedInteger($uid);
    $gidBytes = $docxUnixOwnerPackVariableUnsignedInteger($gid);
    $payload = chr(1)
        . chr(strlen($uidBytes))
        . $uidBytes
        . chr(strlen($gidBytes))
        . $gidBytes;

    return pack('vv', 0x7875, strlen($payload)) . $payload;
};
$docxUnixOwner = static function (int $uid, int $gid): array {
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
$docxUnixOwnerBuildZipPackage = static function (array $entries, string $comment = '') use ($docxUnixOwnerCrc32): string {
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
        $crc = $docxUnixOwnerCrc32($data);
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
$docxUnixOwnerBuildPackage = static function () use ($docxUnixOwnerBuildZipPackage, $docxUnixOwnerExtra): ZipPackage {
    return ZipPackage::fromString($docxUnixOwnerBuildZipPackage([
        [
            'name' => '[Content_Types].xml',
            'method' => 0,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">
  <Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>
  <Default Extension="xml" ContentType="application/xml"/>
  <Default Extension="png" ContentType="image/png"/>
  <Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>
</Types>
XML,
        ],
        [
            'name' => '_rels/.rels',
            'method' => 8,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rDocument" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>
</Relationships>
XML,
        ],
        [
            'name' => 'word/_rels/document.xml.rels',
            'method' => 8,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rMatching" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/matching-owner.png"/>
  <Relationship Id="rLocalOnly" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/local-owner.png"/>
  <Relationship Id="rMismatch" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/image" Target="media/mismatched-owner.png"/>
</Relationships>
XML,
        ],
        [
            'name' => 'word/document.xml',
            'method' => 8,
            'data' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">
  <w:body>
    <w:p><w:r><w:t>DOCX Unix owner metadata provenance.</w:t></w:r></w:p>
  </w:body>
</w:document>
XML,
        ],
        [
            'name' => 'word/media/matching-owner.png',
            'data' => 'MATCHING-DOCX-OWNER-PNG',
            'method' => 0,
            'localExtra' => $docxUnixOwnerExtra(1001, 1002),
            'centralExtra' => $docxUnixOwnerExtra(1001, 1002),
            'externalAttributes' => 0x81a40000,
        ],
        [
            'name' => 'word/media/local-owner.png',
            'data' => 'LOCAL-DOCX-OWNER-PNG',
            'method' => 0,
            'localExtra' => $docxUnixOwnerExtra(33, 44),
            'centralExtra' => '',
            'externalAttributes' => 0x81a40000,
        ],
        [
            'name' => 'word/media/mismatched-owner.png',
            'data' => 'MISMATCHED-DOCX-OWNER-PNG',
            'method' => 0,
            'localExtra' => $docxUnixOwnerExtra(52, 53),
            'centralExtra' => $docxUnixOwnerExtra(50, 51),
            'externalAttributes' => 0x81a40000,
        ],
    ], 'docx unix owner metadata provenance'));
};
$docxUnixOwnerIndexByPart = static function (array $entries): array {
    $indexed = [];
    foreach ($entries as $entry) {
        $part = $entry['partName'] ?? ($entry['packagePath'] ?? null);
        if (is_string($part) && $part !== '') {
            $indexed[$part] = $entry;
        }
    }

    return $indexed;
};

return [
    'carries DOCX Unix UID/GID owner extra-field metadata through package provenance' => static function (TestRunner $t) use (
        $docxUnixOwnerBuildPackage,
        $docxUnixOwnerIndexByPart,
        $docxUnixOwner
    ): void {
        $package = $docxUnixOwnerBuildPackage();
        $ownerPreflight = $package->unixOwnerPreflight();
        $document = (new DocxOpenXmlReader())->readZipPackage($package);
        $packageProvenance = $document->attr('docx')['packageProvenance'];
        $summary = $packageProvenance['summary'];
        $zipPackage = $packageProvenance['zipPackage'];
        $identity = $packageProvenance['packageIdentity'];
        $parts = $packageProvenance['parts'];
        $identityParts = $docxUnixOwnerIndexByPart($identity['packageEntries']);
        $zipEntries = $docxUnixOwnerIndexByPart($zipPackage['entries']);

        $t->same('DOCX Unix owner metadata provenance.', $document->children[0]->attr('text'));
        $t->same($ownerPreflight, $zipPackage['unixOwners']);
        $t->same($ownerPreflight, $summary['zipUnixOwners']);
        $t->same($ownerPreflight, $identity['zipUnixOwners']);

        foreach ([$summary, $identity] as $handoff) {
            $t->same(true, $handoff['zipHasUnixOwnerMetadata']);
            $t->same(true, $handoff['zipHasMismatchedUnixOwnerMetadata']);
            $t->same(3, $handoff['zipUnixOwnerMetadataEntryCount']);
            $t->same(2, $handoff['zipCentralUnixOwnerMetadataEntryCount']);
            $t->same(3, $handoff['zipLocalUnixOwnerMetadataEntryCount']);
            $t->same(1, $handoff['zipMismatchedUnixOwnerMetadataEntryCount']);
            $t->same(['unix-owner-extra-fields', 'unix-uid-gid-mismatch'], $handoff['zipUnixOwnerMetadataIssueCodes']);
            $t->same('zip-unix-owner-metadata-only', $handoff['zipUnixOwnerMetadataByteExposurePolicy']);
            $t->same(false, $handoff['zipUnixOwnerMetadataCanExposeBytes']);
        }

        foreach ([$parts, $identityParts, $zipEntries] as $entries) {
            $matching = $entries['word/media/matching-owner.png'];
            $localOnly = $entries['word/media/local-owner.png'];
            $mismatch = $entries['word/media/mismatched-owner.png'];

            $t->same($docxUnixOwner(1001, 1002), $matching['centralUnixOwner']);
            $t->same($docxUnixOwner(1001, 1002), $matching['localUnixOwner']);
            $t->same(true, $matching['hasCentralUnixOwnerMetadata']);
            $t->same(true, $matching['hasLocalUnixOwnerMetadata']);
            $t->same(true, $matching['hasUnixOwnerMetadata']);
            $t->same(true, $matching['unixOwnerMetadataMatches']);
            $t->same(['central-unix-uid-gid-extra-field', 'local-unix-uid-gid-extra-field'], $matching['unixOwnerMetadataIssues']);
            $t->same('zip-unix-owner-metadata-only', $matching['unixOwnerMetadataByteExposurePolicy']);
            $t->same(false, $matching['unixOwnerMetadataCanExposeBytes']);

            $t->same(null, $localOnly['centralUnixOwner'] ?? null);
            $t->same($docxUnixOwner(33, 44), $localOnly['localUnixOwner']);
            $t->same(false, $localOnly['hasCentralUnixOwnerMetadata']);
            $t->same(true, $localOnly['hasLocalUnixOwnerMetadata']);
            $t->same(true, $localOnly['hasUnixOwnerMetadata']);
            $t->same(true, $localOnly['unixOwnerMetadataMatches']);
            $t->same(['local-unix-uid-gid-extra-field'], $localOnly['unixOwnerMetadataIssues']);

            $t->same($docxUnixOwner(50, 51), $mismatch['centralUnixOwner']);
            $t->same($docxUnixOwner(52, 53), $mismatch['localUnixOwner']);
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
            ['word/media/matching-owner.png', 'word/media/local-owner.png', 'word/media/mismatched-owner.png'],
            array_column($summary['zipUnixOwnerMetadataEntries'], 'name')
        );
        $t->same(
            ['word/media/mismatched-owner.png'],
            array_column($identity['zipMismatchedUnixOwnerMetadataEntries'], 'name')
        );
        $t->same(false, $identity['canExposeBytes']);
        $t->same('docx-package-identity-metadata-only', $identity['byteExposurePolicy']);
    },
];
