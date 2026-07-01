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
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Configurations2/accelerator/current.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Extra field role review.</text:p>
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

$crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
$extraField = static fn (int $id, string $payload): string => pack('vva*', $id, strlen($payload), $payload);

/**
 * @param list<array{name:string, data:string, method?:int, localExtra?:string, centralExtra?:string}> $entries
 */
$buildZipPackage = static function (array $entries) use ($crc32): string {
    $body = '';
    $central = '';

    foreach ($entries as $entry) {
        $name = $entry['name'];
        $data = $entry['data'];
        $method = $entry['method'] ?? 0;
        $flags = 0x0800;
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        if ($compressed === false) {
            throw new RuntimeException('Unable to deflate ZIP fixture entry ' . $name);
        }

        $localExtra = $entry['localExtra'] ?? '';
        $centralExtra = $entry['centralExtra'] ?? $localExtra;
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
        );
        $body .= $name . $localExtra . $compressed;

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
            0,
            $offset
        );
        $central .= $name . $centralExtra;
    }

    $centralOffset = strlen($body);

    return $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($entries), count($entries), strlen($central), $centralOffset, 0);
};

$buildPackage = static function () use ($buildZipPackage, $extraField, $manifestXml, $contentXml, $stylesXml): ZipPackage {
    $contentExtra = $extraField(0xcafe, 'content-review');
    $imageExtra = $extraField(0xbeef, 'image-review');
    $scriptCentralExtra = $extraField(0x1234, 'script-central-review');
    $configurationLocalExtra = $extraField(0x4444, 'configuration-local-review');
    $stylesLocalExtra = $extraField(0xabcd, 'styles-local-review');
    $stylesCentralExtra = $extraField(0xabcd, 'styles-central-review');

    return ZipPackage::fromString($buildZipPackage([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'method' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'method' => 0],
        ['name' => 'content.xml', 'data' => $contentXml, 'method' => 8, 'localExtra' => $contentExtra, 'centralExtra' => $contentExtra],
        ['name' => 'styles.xml', 'data' => $stylesXml, 'method' => 8, 'localExtra' => $stylesLocalExtra, 'centralExtra' => $stylesCentralExtra],
        ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'method' => 0, 'localExtra' => $imageExtra, 'centralExtra' => $imageExtra],
        ['name' => 'Basic/Standard/Review.xml', 'data' => '<review>script</review>', 'method' => 0, 'localExtra' => '', 'centralExtra' => $scriptCentralExtra],
        ['name' => 'Configurations2/accelerator/current.xml', 'data' => '<config>shortcut</config>', 'method' => 0, 'localExtra' => $configurationLocalExtra, 'centralExtra' => ''],
    ]));
};

$indexBy = static function (array $items, string $key): array {
    $indexed = [];
    foreach ($items as $item) {
        $indexed[(string) $item[$key]] = $item;
    }

    return $indexed;
};

$extraFieldSubset = static function (array $packet): array {
    $keys = [
        'extraFieldIdRoleCount',
        'extraFieldIdRoleCounts',
        'entryNamesByExtraFieldIdRole',
        'extraFieldIdManifestMediaFamilyCount',
        'extraFieldIdManifestMediaFamilyCounts',
        'entryNamesByExtraFieldIdManifestMediaFamily',
        'centralOnlyExtraFieldIdRoleCount',
        'centralOnlyExtraFieldIdRoleCounts',
        'entryNamesByCentralOnlyExtraFieldIdRole',
        'centralOnlyExtraFieldIdManifestMediaFamilyCount',
        'centralOnlyExtraFieldIdManifestMediaFamilyCounts',
        'entryNamesByCentralOnlyExtraFieldIdManifestMediaFamily',
        'localOnlyExtraFieldIdRoleCount',
        'localOnlyExtraFieldIdRoleCounts',
        'entryNamesByLocalOnlyExtraFieldIdRole',
        'localOnlyExtraFieldIdManifestMediaFamilyCount',
        'localOnlyExtraFieldIdManifestMediaFamilyCounts',
        'entryNamesByLocalOnlyExtraFieldIdManifestMediaFamily',
        'mismatchedExtraFieldValueIdRoleCount',
        'mismatchedExtraFieldValueIdRoleCounts',
        'entryNamesByMismatchedExtraFieldValueIdRole',
        'mismatchedExtraFieldValueIdManifestMediaFamilyCount',
        'mismatchedExtraFieldValueIdManifestMediaFamilyCounts',
        'entryNamesByMismatchedExtraFieldValueIdManifestMediaFamily',
        'extraFieldIdRoleSummaryCount',
        'extraFieldIdRoleSummaries',
    ];

    $subset = [];
    foreach ($keys as $key) {
        $subset[$key] = $packet[$key];
    }

    return $subset;
};

return [
    'carries ODT ZIP extra-field id roles and media-family buckets through package provenance' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $extraFieldSubset
    ): void {
        $package = $buildPackage();
        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactInventory = $compactSummary['packageInventory'];
        $compactIdentity = $compactSummary['packageIdentity'];
        $richResult = (new OdfReader())->readPackage($package);
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $compactIdentityParts = $indexBy($compactIdentity['packageEntries'], 'path');
        $richIdentityParts = $indexBy($richIdentity['packageEntries'], 'part');

        $expectedRoleCounts = [
            0x1234 => ['manifest-declared' => 1, 'script-package' => 1],
            0x4444 => ['configuration-package' => 1, 'manifest-declared' => 1],
            0xabcd => ['manifest-declared' => 1, 'odf-styles' => 1],
            0xbeef => ['manifest-declared' => 1, 'media-resource' => 1],
            0xcafe => ['manifest-declared' => 1, 'odf-content' => 1],
        ];
        $expectedRoleEntryNames = [
            0x1234 => ['manifest-declared' => ['Basic/Standard/Review.xml'], 'script-package' => ['Basic/Standard/Review.xml']],
            0x4444 => ['configuration-package' => ['Configurations2/accelerator/current.xml'], 'manifest-declared' => ['Configurations2/accelerator/current.xml']],
            0xabcd => ['manifest-declared' => ['styles.xml'], 'odf-styles' => ['styles.xml']],
            0xbeef => ['manifest-declared' => ['Pictures/hero.png'], 'media-resource' => ['Pictures/hero.png']],
            0xcafe => ['manifest-declared' => ['content.xml'], 'odf-content' => ['content.xml']],
        ];
        $expectedFamilyCounts = [
            0x1234 => ['script' => 1],
            0x4444 => ['configuration' => 1],
            0xabcd => ['xml' => 1],
            0xbeef => ['image' => 1],
            0xcafe => ['xml' => 1],
        ];
        $expectedFamilyEntryNames = [
            0x1234 => ['script' => ['Basic/Standard/Review.xml']],
            0x4444 => ['configuration' => ['Configurations2/accelerator/current.xml']],
            0xabcd => ['xml' => ['styles.xml']],
            0xbeef => ['image' => ['Pictures/hero.png']],
            0xcafe => ['xml' => ['content.xml']],
        ];

        $t->same($richProvenance, $richResult['document']->attr('manifest')['packageProvenance']);
        $t->same($expectedRoleCounts, $compactInventory['extraFieldIdRoleCounts']);
        $t->same($expectedRoleEntryNames, $compactInventory['entryNamesByExtraFieldIdRole']);
        $t->same($expectedFamilyCounts, $compactInventory['extraFieldIdManifestMediaFamilyCounts']);
        $t->same($expectedFamilyEntryNames, $compactInventory['entryNamesByExtraFieldIdManifestMediaFamily']);
        $t->same([0x1234 => $expectedRoleCounts[0x1234]], $compactInventory['centralOnlyExtraFieldIdRoleCounts']);
        $t->same([0x1234 => $expectedFamilyCounts[0x1234]], $compactInventory['centralOnlyExtraFieldIdManifestMediaFamilyCounts']);
        $t->same([0x4444 => $expectedRoleCounts[0x4444]], $compactInventory['localOnlyExtraFieldIdRoleCounts']);
        $t->same([0x4444 => $expectedFamilyCounts[0x4444]], $compactInventory['localOnlyExtraFieldIdManifestMediaFamilyCounts']);
        $t->same([0xabcd => $expectedRoleCounts[0xabcd]], $compactInventory['mismatchedExtraFieldValueIdRoleCounts']);
        $t->same([0xabcd => $expectedFamilyCounts[0xabcd]], $compactInventory['mismatchedExtraFieldValueIdManifestMediaFamilyCounts']);

        foreach ([$richProvenance, $compactIdentity, $richIdentity] as $packet) {
            $t->same($extraFieldSubset($compactInventory), $extraFieldSubset($packet));
        }

        $t->same([0xcafe], $compactInventory['parts']['content.xml']['zipExtraFieldIds']);
        $t->same([0xabcd], $compactInventory['parts']['styles.xml']['mismatchedExtraFieldValueIds']);
        $t->same([0x1234], $compactInventory['parts']['Basic/Standard/Review.xml']['centralOnlyExtraFieldIds']);
        $t->same([0x4444], $compactInventory['parts']['Configurations2/accelerator/current.xml']['localOnlyExtraFieldIds']);
        $t->same('script', $compactIdentityParts['Basic/Standard/Review.xml']['manifestMediaFamily']);
        $t->same('script', $richIdentityParts['Basic/Standard/Review.xml']['manifestMediaFamily']);
        $t->same('configuration', $compactIdentityParts['Configurations2/accelerator/current.xml']['manifestMediaFamily']);
        $t->same('configuration', $richIdentityParts['Configurations2/accelerator/current.xml']['manifestMediaFamily']);
        $t->same(5, $compactInventory['extraFieldIdRoleSummaryCount']);
        $t->same(5, $richProvenance['extraFieldIdRoleSummaryCount']);
    },
];
