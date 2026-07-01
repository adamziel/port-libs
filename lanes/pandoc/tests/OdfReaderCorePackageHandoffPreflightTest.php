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
      <text:p>Core package handoff.</text:p>
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

$settingsXml = <<<'XML'
<office:document-settings
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:config="urn:oasis:names:tc:opendocument:xmlns:config:1.0"
  office:version="1.3">
  <office:settings>
    <config:config-item-set config:name="writer-review"/>
  </office:settings>
</office:document-settings>
XML;

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml?handoff=core#body" manifest:media-type="text/xml;charset=UTF-8;profile=&quot;core review&quot;" manifest:size="__CONTENT_SIZE__"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml" manifest:size="__STYLES_SIZE__"/>
</manifest:manifest>
XML;
$manifestXml = strtr($manifestXml, [
    '__CONTENT_SIZE__' => (string) strlen($contentXml),
    '__STYLES_SIZE__' => (string) strlen($stylesXml),
]);

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'settings.xml', 'data' => $settingsXml, 'compressionMethod' => 0],
], 'odt core package handoff preflight');

$indexByName = static function (array $items): array {
    $indexed = [];
    foreach ($items as $item) {
        if (is_string($item['name'] ?? null)) {
            $indexed[$item['name']] = $item;
        }
    }

    return $indexed;
};

$buildZipPackageWithCentralDirectoryOrder = static function (array $parts, array $centralOrder): ZipPackage {
    $crc32 = static fn (string $bytes): int => (int) sprintf('%u', crc32($bytes));
    $body = '';
    $centralRecords = [];

    foreach ($parts as $part) {
        $name = $part['name'];
        $rawName = $part['rawName'] ?? $name;
        $data = $part['data'] ?? '';
        $method = $part['compressionMethod'] ?? ($data === '' || str_ends_with($name, '/') ? 0 : 8);
        $compressed = $method === 8 ? gzdeflate($data) : $data;
        $offset = strlen($body);
        $crc = $crc32($data);

        $body .= pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($rawName),
            0
        );
        $body .= $rawName . $compressed;

        $centralRecords[$name] = pack(
            'VvvvvvvVVVvvvvvVV',
            0x02014b50,
            0x0314,
            20,
            0x0800,
            $method,
            0,
            0,
            $crc,
            strlen($compressed),
            strlen($data),
            strlen($rawName),
            0,
            0,
            0,
            0,
            str_ends_with($name, '/') ? 0x10 : 0,
            $offset
        ) . $rawName;
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

return [
    'preflights selected ODT core package entries before reader handoff' => static function (TestRunner $t) use ($buildPackage, $indexByName, $contentXml, $stylesXml, $settingsXml): void {
        $package = $buildPackage();
        $readerResult = (new OdfReader())->readPackage($package);
        $readerHandoff = $readerResult['corePackageHandoff'];
        $compactHandoff = OpenDocumentPackage::fromPackage($package)->summarize()['corePackageHandoff'];
        $readerEntries = $indexByName($readerHandoff['entries']);
        $compactEntries = $indexByName($compactHandoff['entries']);
        $readerSourceSpans = $indexByName($readerHandoff['selectedSourceByteSpanEntries']);
        $compactSourceSpans = $indexByName($compactHandoff['selectedSourceByteSpanEntries']);
        $readerLocalFixedFields = $indexByName($readerHandoff['selectedLocalHeaderFixedFieldEntries']);
        $readerCentralFixedFields = $indexByName($readerHandoff['selectedCentralDirectoryFixedFieldEntries']);

        $t->same($readerHandoff, $readerResult['document']->attr('manifest')['corePackageHandoff']);
        $t->same($readerHandoff, $readerResult['importReport']['manifest']['corePackageHandoff']);
        $t->same('odf-core-package-handoff', $readerHandoff['scope']);
        $t->same('odf-core-package-handoff-metadata-only', $readerHandoff['byteExposurePolicy']);
        $t->same('core-package-selected-entry-preflight', $readerHandoff['reviewPolicy']);
        $t->same(6, $readerHandoff['requestedEntryCount']);
        $t->same(3, $readerHandoff['requiredEntryCount']);
        $t->same(3, $readerHandoff['optionalEntryCount']);
        $t->same(5, $readerHandoff['presentEntryCount']);
        $t->same(1, $readerHandoff['missingOptionalEntryCount']);
        $t->same(5, $readerHandoff['handoffEntryCount']);
        $t->same(0, $readerHandoff['failedEntryCount']);
        $t->same(true, $readerHandoff['isSupportedByBoundedReader']);
        $t->same([
            'declared' => 2,
            'not-declared' => 1,
            'package-manifest-entry' => 1,
            'package-mimetype-entry' => 1,
            'undeclared' => 1,
        ], $readerHandoff['manifestDeclarationStateCounts']);
        $t->same(2, $readerHandoff['manifestDeclaredSelectedEntryCount']);
        $t->same(1, $readerHandoff['undeclaredSelectedEntryCount']);
        $t->same(2, $readerHandoff['specialPackageSelectedEntryCount']);

        $t->same('package-mimetype-entry', $readerEntries['mimetype']['manifestDeclarationState']);
        $t->same('/', $readerEntries['mimetype']['manifestFullPath']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $readerEntries['mimetype']['manifestMediaType']);
        $t->same('odf-mimetype', $readerEntries['mimetype']['role']);
        $t->same(strlen(OdfReader::MIMETYPE), $readerEntries['mimetype']['bytesRead']);

        $t->same('package-manifest-entry', $readerEntries['META-INF/manifest.xml']['manifestDeclarationState']);
        $t->same(false, $readerEntries['META-INF/manifest.xml']['manifestDeclared']);
        $t->same('odf-manifest', $readerEntries['META-INF/manifest.xml']['role']);

        $t->same('declared', $readerEntries['content.xml']['manifestDeclarationState']);
        $t->same(true, $readerEntries['content.xml']['manifestDeclared']);
        $t->same('content.xml?handoff=core#body', $readerEntries['content.xml']['manifestFullPath']);
        $t->same('content.xml', $readerEntries['content.xml']['manifestPackagePath']);
        $t->same('content.xml', $readerEntries['content.xml']['manifestPathReference']);
        $t->same('?handoff=core#body', $readerEntries['content.xml']['manifestPathSuffix']);
        $t->same('handoff=core', $readerEntries['content.xml']['manifestPathQuery']);
        $t->same('body', $readerEntries['content.xml']['manifestPathFragment']);
        $t->same(false, $readerEntries['content.xml']['manifestUriEncodedPackageReference']);
        $t->same('text/xml;charset=UTF-8;profile="core review"', $readerEntries['content.xml']['manifestMediaType']);
        $t->same('text/xml', $readerEntries['content.xml']['manifestMediaTypeBase']);
        $t->same(true, $readerEntries['content.xml']['manifestMediaTypeHasParameters']);
        $t->same(2, $readerEntries['content.xml']['manifestMediaTypeParameterCount']);
        $t->same([
            ['name' => 'charset', 'value' => 'UTF-8', 'raw' => 'charset=UTF-8'],
            ['name' => 'profile', 'value' => 'core review', 'raw' => 'profile="core review"'],
        ], $readerEntries['content.xml']['manifestMediaTypeParameters']);
        $t->same([
            'charset' => 'UTF-8',
            'profile' => 'core review',
        ], $readerEntries['content.xml']['manifestMediaTypeParameterMap']);
        $t->same(strlen($contentXml), $readerEntries['content.xml']['manifestDeclaredSize']);
        $t->same((string) strlen($contentXml), $readerEntries['content.xml']['manifestDeclaredSizeRaw']);
        $t->same(true, $readerEntries['content.xml']['manifestDeclaredSizeValid']);
        $t->same(false, $readerEntries['content.xml']['manifestDeclaredSizeInvalid']);
        $t->same(false, $readerEntries['content.xml']['manifestDeclaredSizeMismatch']);
        $t->same(strlen($contentXml), $readerEntries['content.xml']['bytesRead']);
        $t->same(hash('sha256', $contentXml), $readerEntries['content.xml']['contentSha256']);

        $t->same('declared', $readerEntries['styles.xml']['manifestDeclarationState']);
        $t->same(strlen($stylesXml), $readerEntries['styles.xml']['manifestDeclaredSize']);
        $t->same((string) strlen($stylesXml), $readerEntries['styles.xml']['manifestDeclaredSizeRaw']);
        $t->same(true, $readerEntries['styles.xml']['manifestDeclaredSizeValid']);
        $t->same(false, $readerEntries['styles.xml']['manifestDeclaredSizeInvalid']);
        $t->same(strlen($stylesXml), $readerEntries['styles.xml']['bytesRead']);

        $t->same('not-declared', $readerEntries['meta.xml']['manifestDeclarationState']);
        $t->same(false, $readerEntries['meta.xml']['exists']);
        $t->same('missing-optional', $readerEntries['meta.xml']['status']);
        $t->same(false, $readerEntries['meta.xml']['manifestDeclared']);

        $t->same('undeclared', $readerEntries['settings.xml']['manifestDeclarationState']);
        $t->same(false, $readerEntries['settings.xml']['manifestDeclared']);
        $t->same(strlen($settingsXml), $readerEntries['settings.xml']['bytesRead']);
        $t->same(hash('sha256', $settingsXml), $readerEntries['settings.xml']['contentSha256']);

        $t->same('package-mimetype-entry', $readerSourceSpans['mimetype']['manifestDeclarationState']);
        $t->same('/', $readerSourceSpans['mimetype']['manifestFullPath']);
        $t->same(OpenDocumentPackage::TEXT_MIMETYPE, $readerSourceSpans['mimetype']['manifestMediaType']);
        $t->same('package-manifest-entry', $readerSourceSpans['META-INF/manifest.xml']['manifestDeclarationState']);
        $t->same(false, $readerSourceSpans['META-INF/manifest.xml']['manifestDeclared']);
        $t->same('declared', $readerSourceSpans['content.xml']['manifestDeclarationState']);
        $t->same('content.xml?handoff=core#body', $readerSourceSpans['content.xml']['manifestFullPath']);
        $t->same('?handoff=core#body', $readerSourceSpans['content.xml']['manifestPathSuffix']);
        $t->same('handoff=core', $readerSourceSpans['content.xml']['manifestPathQuery']);
        $t->same('body', $readerSourceSpans['content.xml']['manifestPathFragment']);
        $t->same(true, $readerSourceSpans['content.xml']['manifestMediaTypeHasParameters']);
        $t->same(2, $readerSourceSpans['content.xml']['manifestMediaTypeParameterCount']);
        $t->same(strlen($contentXml), $readerSourceSpans['content.xml']['manifestDeclaredSize']);
        $t->same((string) strlen($contentXml), $readerSourceSpans['content.xml']['manifestDeclaredSizeRaw']);
        $t->same(true, $readerSourceSpans['content.xml']['manifestDeclaredSizeValid']);
        $t->same('undeclared', $readerSourceSpans['settings.xml']['manifestDeclarationState']);
        $t->same(false, $readerSourceSpans['settings.xml']['manifestDeclared']);
        $t->same(true, $readerSourceSpans['settings.xml']['hasSourceByteSpanProvenance']);
        $t->same('declared', $readerLocalFixedFields['styles.xml']['manifestDeclarationState']);
        $t->same(strlen($stylesXml), $readerLocalFixedFields['styles.xml']['manifestDeclaredSize']);
        $t->same((string) strlen($stylesXml), $readerLocalFixedFields['styles.xml']['manifestDeclaredSizeRaw']);
        $t->same(true, $readerLocalFixedFields['styles.xml']['manifestDeclaredSizeValid']);
        $t->same('undeclared', $readerCentralFixedFields['settings.xml']['manifestDeclarationState']);
        $t->same(false, $readerCentralFixedFields['settings.xml']['manifestDeclared']);

        $t->same($readerHandoff['manifestDeclarationStateCounts'], $compactHandoff['manifestDeclarationStateCounts']);
        $t->same($readerEntries['settings.xml']['manifestDeclarationState'], $compactEntries['settings.xml']['manifestDeclarationState']);
        $t->same($readerEntries['content.xml']['manifestDeclaredSize'], $compactEntries['content.xml']['manifestDeclaredSize']);
        $t->same($readerEntries['content.xml']['manifestDeclaredSizeRaw'], $compactEntries['content.xml']['manifestDeclaredSizeRaw']);
        $t->same($readerEntries['content.xml']['manifestDeclaredSizeValid'], $compactEntries['content.xml']['manifestDeclaredSizeValid']);
        $t->same($readerEntries['content.xml']['manifestDeclaredSizeInvalid'], $compactEntries['content.xml']['manifestDeclaredSizeInvalid']);
        $t->same($readerEntries['content.xml']['manifestPathSuffix'], $compactEntries['content.xml']['manifestPathSuffix']);
        $t->same($readerEntries['content.xml']['manifestPathQuery'], $compactEntries['content.xml']['manifestPathQuery']);
        $t->same($readerEntries['content.xml']['manifestPathFragment'], $compactEntries['content.xml']['manifestPathFragment']);
        $t->same($readerEntries['content.xml']['manifestMediaTypeParameterMap'], $compactEntries['content.xml']['manifestMediaTypeParameterMap']);
        $t->same($readerSourceSpans['settings.xml']['manifestDeclarationState'], $compactSourceSpans['settings.xml']['manifestDeclarationState']);
        $t->same($readerSourceSpans['content.xml']['manifestDeclaredSize'], $compactSourceSpans['content.xml']['manifestDeclaredSize']);
        $t->same($readerSourceSpans['content.xml']['manifestDeclaredSizeRaw'], $compactSourceSpans['content.xml']['manifestDeclaredSizeRaw']);
        $t->same($readerSourceSpans['content.xml']['manifestDeclaredSizeValid'], $compactSourceSpans['content.xml']['manifestDeclaredSizeValid']);
        $t->same($readerSourceSpans['content.xml']['manifestPathSuffix'], $compactSourceSpans['content.xml']['manifestPathSuffix']);
        $t->same($readerSourceSpans['content.xml']['manifestMediaTypeParameters'], $compactSourceSpans['content.xml']['manifestMediaTypeParameters']);
    },
    'preflights missing ODT core entries while unsupported package bytes stay blocked' => static function (TestRunner $t) use ($buildZipPackageWithCentralDirectoryOrder, $indexByName, $contentXml): void {
        $scriptXml = '<script:module xmlns:script="urn:oasis:names:tc:opendocument:xmlns:script:1.0"/>';
        $unsupportedBytes = 'UNSUPPORTED-CORE-HANDOFF-BLOCK';
        $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/missing.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Pictures/unsupported.bin" manifest:media-type="application/octet-stream"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Module1.xml" manifest:media-type="text/xml"/>
</manifest:manifest>
XML;
        $parts = [
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
            ['name' => 'Pictures/unsupported.bin', 'data' => $unsupportedBytes, 'compressionMethod' => 12],
            ['name' => 'Basic/Standard/Module1.xml', 'data' => $scriptXml, 'compressionMethod' => 0],
        ];
        $package = $buildZipPackageWithCentralDirectoryOrder($parts, array_column($parts, 'name'));

        $readerResult = (new OdfReader())->readPackage($package);
        $readerHandoff = $readerResult['corePackageHandoff'];
        $compactHandoff = OpenDocumentPackage::fromPackage($package)->summarize()['corePackageHandoff'];
        $readerEntries = $indexByName($readerHandoff['entries']);
        $compactEntries = $indexByName($compactHandoff['entries']);
        $manifestByPart = [];
        foreach ($readerResult['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $mediaByPart = [];
        foreach ($readerResult['media'] as $item) {
            $mediaByPart[$item['part']] = $item;
        }
        $provenance = $readerResult['importReport']['manifest']['packageProvenance'];
        $packageParts = $provenance['parts'];
        $compression = $provenance['compressionMethods'];

        $t->same($readerHandoff, $readerResult['document']->attr('manifest')['corePackageHandoff']);
        $t->same($readerHandoff, $readerResult['importReport']['manifest']['corePackageHandoff']);
        $t->same([
            'mimetype',
            'META-INF/manifest.xml',
            'content.xml',
            'styles.xml',
            'meta.xml',
            'settings.xml',
        ], array_column($readerHandoff['entries'], 'name'));
        $t->same(6, $readerHandoff['requestedEntryCount']);
        $t->same(3, $readerHandoff['requiredEntryCount']);
        $t->same(3, $readerHandoff['optionalEntryCount']);
        $t->same(3, $readerHandoff['presentEntryCount']);
        $t->same(3, $readerHandoff['missingEntryCount']);
        $t->same(0, $readerHandoff['missingRequiredEntryCount']);
        $t->same(3, $readerHandoff['missingOptionalEntryCount']);
        $t->same(3, $readerHandoff['handoffEntryCount']);
        $t->same(0, $readerHandoff['failedEntryCount']);
        $t->same(0, $readerHandoff['selectedUnsupportedCompressionMethodCount']);
        $t->same(true, $readerHandoff['isSupportedByBoundedReader']);
        $t->same([], $readerHandoff['issues']);
        $t->same([
            'declared' => 1,
            'not-declared' => 3,
            'package-manifest-entry' => 1,
            'package-mimetype-entry' => 1,
        ], $readerHandoff['manifestDeclarationStateCounts']);
        $t->same(1, $readerHandoff['manifestDeclaredSelectedEntryCount']);
        $t->same(0, $readerHandoff['undeclaredSelectedEntryCount']);
        $t->same(2, $readerHandoff['specialPackageSelectedEntryCount']);
        $t->same(['styles.xml', 'meta.xml', 'settings.xml'], array_column($readerHandoff['missingEntries'], 'name'));
        foreach (['styles.xml', 'meta.xml', 'settings.xml'] as $name) {
            $t->same(false, $readerEntries[$name]['exists']);
            $t->same('missing-optional', $readerEntries[$name]['status']);
            $t->same('not-declared', $readerEntries[$name]['manifestDeclarationState']);
            $t->same(false, $readerEntries[$name]['manifestDeclared']);
            $t->same([], $readerEntries[$name]['issues']);
            $t->same($readerEntries[$name]['manifestDeclarationState'], $compactEntries[$name]['manifestDeclarationState']);
        }
        $t->same(['mimetype', 'META-INF/manifest.xml', 'content.xml'], array_column($readerHandoff['handoffEntries'], 'name'));
        $t->same($readerHandoff['manifestDeclarationStateCounts'], $compactHandoff['manifestDeclarationStateCounts']);
        $t->same($readerHandoff['missingOptionalEntryCount'], $compactHandoff['missingOptionalEntryCount']);

        $t->same(['Pictures/hero.png', 'Pictures/missing.png', 'Pictures/unsupported.bin'], array_column($readerResult['media'], 'part'));
        $t->same('missing-package-part', $mediaByPart['Pictures/missing.png']['byteExposurePolicy']);
        $t->same(null, $mediaByPart['Pictures/missing.png']['byteLength']);
        $t->same('unsupported-compression-bytes-blocked', $mediaByPart['Pictures/unsupported.bin']['byteExposurePolicy']);
        $t->same(false, $mediaByPart['Pictures/unsupported.bin']['canExposeBytes']);
        $t->same(strlen($unsupportedBytes), $mediaByPart['Pictures/unsupported.bin']['storedByteLength']);
        $t->same(12, $mediaByPart['Pictures/unsupported.bin']['compressionMethod']);
        $t->same('unsupported', $mediaByPart['Pictures/unsupported.bin']['compressionMethodName']);

        $t->same('missing-package-part', $manifestByPart['Pictures/missing.png']['byteExposurePolicy']);
        $t->same('unsupported-compression-bytes-blocked', $manifestByPart['Pictures/unsupported.bin']['byteExposurePolicy']);
        $t->same(true, $manifestByPart['Basic/Standard/Module1.xml']['scriptPackagePart']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Basic/Standard/Module1.xml']['byteExposurePolicy']);
        $t->same(['manifest-declared', 'media-resource'], $packageParts['Pictures/unsupported.bin']['roles']);
        $t->same('unsupported-compression-bytes-blocked', $packageParts['Pictures/unsupported.bin']['byteExposurePolicy']);
        $t->same(['manifest-declared', 'script-package'], $packageParts['Basic/Standard/Module1.xml']['roles']);
        $t->same('script-package-bytes-blocked', $packageParts['Basic/Standard/Module1.xml']['byteExposurePolicy']);
        $t->same(1, $compression['unsupportedCompressionMethodCount']);
        $t->same(['Pictures/unsupported.bin'], array_column($compression['unsupportedEntries'], 'name'));
        $t->same(['Pictures/missing.png'], array_column($readerResult['importReport']['manifest']['missingItems'], 'part'));
    },
];
