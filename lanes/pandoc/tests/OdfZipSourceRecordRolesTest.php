<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Thumbnails/review.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="META-INF/documentsignatures.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="META-INF/review-state.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Extras/report.bin" manifest:media-type="application/octet-stream"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>ZIP source record role review.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="RoleBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>ZIP Source Record Role Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$signatureXml = <<<'XML'
<dsig:document-signatures xmlns:dsig="http://www.w3.org/2000/09/xmldsig#"/>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/review.png', 'data' => str_repeat('R', 512), 'compressionMethod' => 0],
    ['name' => 'Thumbnails/review.png', 'data' => str_repeat('T', 64), 'compressionMethod' => 8],
    ['name' => 'META-INF/documentsignatures.xml', 'data' => $signatureXml, 'compressionMethod' => 0],
    ['name' => 'META-INF/review-state.xml', 'data' => '<review-state/>', 'compressionMethod' => 0],
    ['name' => 'Extras/report.bin', 'data' => str_repeat('B', 160), 'compressionMethod' => 0],
    ['name' => 'Notes/private.txt', 'data' => str_repeat('N', 32), 'compressionMethod' => 0],
], 'odt zip source record role provenance');

return [
    'summarizes ODT ZIP source records by package role' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentProvenance = $richResult['document']->attr('manifest')['packageProvenance'];

        $expectedRoleCounts = odf_zip_source_record_role_counts($compactInventory['parts']);
        $expectedRoleBytes = odf_zip_source_record_role_sums($compactInventory['parts'], 'zipSourceRecordBytes');
        $expectedRoles = [
            'manifest-declared',
            'media-resource',
            'meta-inf-sidecar',
            'odf-content',
            'odf-manifest',
            'odf-meta',
            'odf-mimetype',
            'odf-styles',
            'package-signature',
            'package-thumbnail',
            'undeclared-package-entry',
        ];

        $t->same($expectedRoles, array_keys($expectedRoleCounts));
        foreach ([
            'compact inventory' => $compactInventory,
            'compact identity' => $compactIdentity,
            'rich provenance' => $richProvenance,
            'rich identity' => $richIdentity,
            'document provenance' => $documentProvenance,
        ] as $label => $handoff) {
            $t->same(count($expectedRoles), $handoff['packageZipSourceRecordRoleCount'], "{$label} role count");
            $t->same($expectedRoles, array_column($handoff['packageZipSourceRecordRoles'], 'role'), "{$label} role order");
            $t->same($expectedRoleCounts, $handoff['packageZipSourceRecordRoleCounts'], "{$label} role counts");
            $t->same($expectedRoleBytes, $handoff['packageZipSourceRecordRoleBytes'], "{$label} role bytes");
            $t->same(array_sum($expectedRoleCounts), $handoff['packageZipSourceRecordRoleOccurrenceCount'], "{$label} occurrence count");
            $t->same(0, $handoff['packageZipSourceRecordRoleDataDescriptorOccurrenceCount'], "{$label} descriptor count");
            $t->same(0, $handoff['packageZipSourceRecordRoleIssueOccurrenceCount'], "{$label} issue count");
        }

        $t->same($compactInventory['packageZipSourceRecordRoleCounts'], $compactIdentity['packageZipSourceRecordRoleCounts']);
        $t->same($richProvenance['packageZipSourceRecordRoles'], $richIdentity['packageZipSourceRecordRoles']);
        $t->same($richProvenance['packageZipSourceRecordRoleBytes'], $documentProvenance['packageZipSourceRecordRoleBytes']);

        $compactRoles = odf_zip_source_record_role_index_by($compactInventory['packageZipSourceRecordRoles'], 'role');
        $richRoles = odf_zip_source_record_role_index_by($richProvenance['packageZipSourceRecordRoles'], 'role');

        foreach ([
            [$compactRoles['media-resource'], $compactInventory['parts']],
            [$richRoles['media-resource'], $richProvenance['parts']],
        ] as [$media, $parts]) {
            $t->same(1, $media['entryCount']);
            $t->same(['Pictures/review.png'], $media['entryNames']);
            $t->same(['Pictures/' => 1], $media['directoryRootCounts']);
            $t->same(['package-bytes-exposable' => 1], $media['byteExposurePolicyCounts']);
            $t->same(
                odf_zip_source_record_role_string_counts($parts, 'media-resource', 'manifestMediaFamily'),
                $media['manifestMediaFamilyCounts']
            );
            $t->same(
                odf_zip_source_record_role_string_counts($parts, 'media-resource', 'manifestMediaTypeBase'),
                $media['manifestMediaTypeBaseCounts']
            );
            $t->same([0 => 1], $media['compressionMethodCounts']);
            $t->same(
                odf_zip_source_record_role_sum_for_role($parts, 'media-resource', 'zipSourceRecordBytes'),
                $media['sourceRecordBytes']
            );
            $t->same('Pictures/review.png', $media['largestSourceRecordEntry']['entryName']);
            $t->same('media-resource', $media['largestSourceRecordEntry']['role']);
            $t->same(false, array_key_exists('contents', $media['largestSourceRecordEntry']));
        }

        foreach ([$compactRoles['package-thumbnail'], $richRoles['package-thumbnail']] as $thumbnail) {
            $t->same(1, $thumbnail['entryCount']);
            $t->same(['Thumbnails/review.png'], $thumbnail['entryNames']);
            $t->same(['Thumbnails/' => 1], $thumbnail['directoryRootCounts']);
            $t->same(['package-thumbnail-bytes-blocked' => 1], $thumbnail['byteExposurePolicyCounts']);
            $t->same(['image/png' => 1], $thumbnail['manifestMediaTypeBaseCounts']);
            $t->same([8 => 1], $thumbnail['compressionMethodCounts']);
            $t->same('Thumbnails/review.png', $thumbnail['largestSourceRecordEntry']['entryName']);
            $t->same('package-thumbnail', $thumbnail['largestSourceRecordEntry']['role']);
            $t->same(['package-thumbnail', 'manifest-declared'], $thumbnail['largestSourceRecordEntry']['roles']);
        }

        foreach ([$compactRoles['package-signature'], $richRoles['package-signature']] as $signature) {
            $t->same(1, $signature['entryCount']);
            $t->same(['META-INF/documentsignatures.xml'], $signature['entryNames']);
            $t->same(['META-INF/' => 1], $signature['directoryRootCounts']);
            $t->same(['signature-package-bytes-blocked' => 1], $signature['byteExposurePolicyCounts']);
            $t->same('package-signature', $signature['largestSourceRecordEntry']['role']);
        }

        foreach ([$compactRoles['meta-inf-sidecar'], $richRoles['meta-inf-sidecar']] as $sidecar) {
            $t->same(1, $sidecar['entryCount']);
            $t->same(['META-INF/review-state.xml'], $sidecar['entryNames']);
            $t->same(['META-INF/' => 1], $sidecar['directoryRootCounts']);
            $t->same(['meta-inf-sidecar-package-bytes-blocked' => 1], $sidecar['byteExposurePolicyCounts']);
            $t->same('meta-inf-sidecar', $sidecar['largestSourceRecordEntry']['role']);
        }

        $manifestDeclared = $compactRoles['manifest-declared'];
        $t->same($expectedRoleCounts['manifest-declared'], $manifestDeclared['entryCount']);
        $t->same(
            odf_zip_source_record_role_entry_names($compactInventory['parts'], 'manifest-declared'),
            $manifestDeclared['entryNames']
        );
        $t->same(
            odf_zip_source_record_role_sum_for_role($compactInventory['parts'], 'manifest-declared', 'zipCentralDirectoryRecordBytes'),
            $manifestDeclared['centralDirectoryRecordBytes']
        );
        $t->same(0, $manifestDeclared['sourceRecordIssueEntryCount']);
    },
];

/**
 * @param list<array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function odf_zip_source_record_role_index_by(array $items, string $key): array
{
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
}

/**
 * @param array<string, mixed> $part
 * @return list<string>
 */
function odf_zip_source_record_role_part_roles(array $part): array
{
    $roles = array_values(array_unique(array_filter(
        array_map('strval', is_array($part['roles'] ?? null) ? $part['roles'] : []),
        static fn (string $role): bool => $role !== ''
    )));

    return $roles === [] ? ['package-part'] : $roles;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function odf_zip_source_record_role_counts(array $inventory): array
{
    $counts = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }

        foreach (odf_zip_source_record_role_part_roles($part) as $role) {
            $counts[$role] = ($counts[$role] ?? 0) + 1;
        }
    }
    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function odf_zip_source_record_role_sums(array $inventory, string $field): array
{
    $sums = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }

        $value = is_int($part[$field] ?? null) ? $part[$field] : 0;
        foreach (odf_zip_source_record_role_part_roles($part) as $role) {
            $sums[$role] = ($sums[$role] ?? 0) + $value;
        }
    }
    ksort($sums, SORT_STRING);

    return $sums;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return array<string, int>
 */
function odf_zip_source_record_role_string_counts(array $inventory, string $role, string $field): array
{
    $counts = [];
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }
        if (!in_array($role, odf_zip_source_record_role_part_roles($part), true)) {
            continue;
        }

        $value = is_string($part[$field] ?? null) && $part[$field] !== '' ? $part[$field] : '(missing)';
        $counts[$value] = ($counts[$value] ?? 0) + 1;
    }
    ksort($counts, SORT_STRING);

    return $counts;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 */
function odf_zip_source_record_role_sum_for_role(array $inventory, string $role, string $field): int
{
    $sum = 0;
    foreach ($inventory as $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }
        if (!in_array($role, odf_zip_source_record_role_part_roles($part), true)) {
            continue;
        }

        $sum += is_int($part[$field] ?? null) ? $part[$field] : 0;
    }

    return $sum;
}

/**
 * @param array<string, array<string, mixed>> $inventory
 * @return list<string>
 */
function odf_zip_source_record_role_entry_names(array $inventory, string $role): array
{
    $names = [];
    foreach ($inventory as $name => $part) {
        if (($part['zipHasSourceRecordProvenance'] ?? false) !== true) {
            continue;
        }
        if (!in_array($role, odf_zip_source_record_role_part_roles($part), true)) {
            continue;
        }

        $names[] = is_string($part['path'] ?? null) ? $part['path'] : (string) $name;
    }
    sort($names, SORT_STRING);

    return $names;
}
