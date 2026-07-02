<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Manifest package order provenance.</text:p>
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
    <dc:title>Manifest Order Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:media-type="application/vnd.oasis.opendocument.text" manifest:full-path="/" manifest:version="1.3"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="content.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="styles.xml"/>
  <manifest:file-entry manifest:media-type="text/xml" manifest:full-path="meta.xml"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/hero.png"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Pictures/missing.png"/>
  <manifest:file-entry manifest:media-type="image/png" manifest:full-path="Thumbnails/thumbnail.png"/>
</manifest:manifest>
XML;

$buildZipPackageWithCentralDirectoryOrder = static function (array $parts, array $centralOrder): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $centralRecords = [];

    foreach ($parts as $part) {
        $name = $part['name'];
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $flags = $part['generalPurposeFlags'] ?? 0x0800;
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
            0
        );
        $body .= $name . $compressed;

        $centralRecords[$name] = pack(
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
            0,
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        ) . $name;
    }

    $central = '';
    foreach ($centralOrder as $name) {
        if (!isset($centralRecords[$name])) {
            throw new RuntimeException("Missing central directory record for {$name}");
        }
        $central .= $centralRecords[$name];
    }

    $centralOffset = strlen($body);

    return ZipPackage::fromString(
        $body
        . $central
        . pack('VvvvvVVv', 0x06054b50, 0, 0, count($parts), count($parts), strlen($central), $centralOffset, 0)
    );
};

$parts = [
    ['name' => 'mimetype', 'data' => OpenDocumentPackage::TEXT_MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 8],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 8],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 8],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Thumbnails/thumbnail.png', 'data' => 'THUMBNAIL', 'compressionMethod' => 0],
    ['name' => 'Notes/private.txt', 'data' => 'PRIVATE-NOTE', 'compressionMethod' => 0],
];

$centralOrder = [
    'mimetype',
    'META-INF/manifest.xml',
    'Pictures/hero.png',
    'content.xml',
    'styles.xml',
    'meta.xml',
    'Notes/private.txt',
    'Thumbnails/thumbnail.png',
];

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

$assertOrderSummary = static function (TestRunner $t, array $summary): void {
    $itemsByPath = [];
    foreach ($summary['items'] as $item) {
        $itemsByPath[$item['packagePath']] = $item;
    }

    $t->same(false, $summary['matchesCentralDirectoryOrder']);
    $t->same(6, $summary['manifestDeclaredPartCount']);
    $t->same(5, $summary['existingManifestDeclaredPartCount']);
    $t->same(1, $summary['missingManifestDeclaredPartCount']);
    $t->same(8, $summary['packageEntryCount']);
    $t->same(1, $summary['undeclaredPackageEntryCount']);
    $t->same(4, $summary['orderMismatchCount']);
    $t->same(3, $summary['maxAbsoluteOrderDelta']);
    $t->same('odf-manifest-package-order-metadata-only', $summary['byteExposurePolicy']);
    $t->same(false, $summary['canExposeBytes']);
    $t->same([
        'content.xml',
        'styles.xml',
        'meta.xml',
        'Pictures/hero.png',
        'Pictures/missing.png',
        'Thumbnails/thumbnail.png',
    ], $summary['manifestPackagePaths']);
    $t->same([
        'Pictures/hero.png',
        'content.xml',
        'styles.xml',
        'meta.xml',
        'Thumbnails/thumbnail.png',
    ], $summary['centralDirectoryDeclaredPackagePaths']);
    $t->same(['Pictures/missing.png'], $summary['missingManifestDeclaredPackagePaths']);
    $t->same(['Notes/private.txt'], $summary['undeclaredPackagePaths']);
    $t->same([
        'content.xml',
        'styles.xml',
        'meta.xml',
        'Pictures/hero.png',
    ], array_column($summary['mismatchItems'], 'packagePath'));

    $t->same(1, $itemsByPath['content.xml']['orderDelta']);
    $t->same(['odf-manifest-package-order-mismatch'], $itemsByPath['content.xml']['issues']);
    $t->same(-3, $itemsByPath['Pictures/hero.png']['orderDelta']);
    $t->same(0, $itemsByPath['Thumbnails/thumbnail.png']['orderDelta']);
    $t->same([], $itemsByPath['Thumbnails/thumbnail.png']['issues']);
    $t->same(true, $itemsByPath['Pictures/missing.png']['missing']);
    $t->same(['odf-manifest-package-order-missing-part'], $itemsByPath['Pictures/missing.png']['issues']);
    $t->same(['odf-manifest-package-order-undeclared-entry'], $summary['undeclaredItems'][0]['issues']);
};

return [
    'summarizes ODT manifest package order against central directory order' => static function (TestRunner $t) use (
        $buildZipPackageWithCentralDirectoryOrder,
        $parts,
        $centralOrder,
        $indexBy,
        $assertOrderSummary
    ): void {
        $compactPackage = $buildZipPackageWithCentralDirectoryOrder($parts, $centralOrder);
        $compact = OpenDocumentPackage::fromPackage($compactPackage)->summarize();
        $compactOrder = $compact['packageInventory']['manifestPackageOrder'];
        $assertOrderSummary($t, $compactOrder);
        $t->same($compactOrder, $compact['packageIdentity']['manifestPackageOrder']);
        $t->same(false, $compact['packageIdentity']['manifestPackageOrderMatchesCentralDirectoryOrder']);
        $t->same(4, $compact['packageIdentity']['manifestPackageOrderMismatchCount']);

        $richPackage = $buildZipPackageWithCentralDirectoryOrder($parts, $centralOrder);
        $result = (new OdfReader())->readPackage($richPackage);
        $provenance = $result['importReport']['manifest']['packageProvenance'];
        $richOrder = $provenance['manifestPackageOrder'];
        $assertOrderSummary($t, $richOrder);
        $t->same($richOrder, $result['document']->attr('manifest')['packageProvenance']['manifestPackageOrder']);
        $t->same($richOrder, $provenance['packageIdentity']['manifestPackageOrder']);
        $t->same(false, $provenance['packageIdentity']['manifestPackageOrderMatchesCentralDirectoryOrder']);
        $t->same(4, $provenance['packageIdentity']['manifestPackageOrderMismatchCount']);

        $richItems = $indexBy($richOrder['items'], 'packagePath');
        $t->same(2, $richItems['Pictures/hero.png']['centralDirectoryIndex']);
        $t->same(6, $richOrder['undeclaredItems'][0]['centralDirectoryIndex']);
    },
];
