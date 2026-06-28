<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$eventDefinitionXml = '<event:events xmlns:event="urn:example:events"><event:event name="on-load"/></event:events>';
$launchScriptBytes = 'function launchReview() { return true; }';
$metadataBytes = '{"event":"on-load","target":"review"}';
$encryptedBytes = 'ENCRYPTED-EVENT-BYTES';
$orphanBytes = 'ORPHAN-EVENT-DATA';

$eventDefinitionSize = strlen($eventDefinitionXml);
$launchScriptSize = strlen($launchScriptBytes);
$metadataSize = strlen($metadataBytes);
$encryptedSize = strlen($encryptedBytes);

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Events/Launch/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Events/Launch/events.xml" manifest:media-type="text/xml" manifest:size="{$eventDefinitionSize}"/>
  <manifest:file-entry manifest:full-path="Events/Launch/launch.js" manifest:media-type="application/javascript" manifest:size="{$launchScriptSize}"/>
  <manifest:file-entry manifest:full-path="Events/Launch/metadata.json" manifest:media-type="application/json" manifest:size="{$metadataSize}"/>
  <manifest:file-entry manifest:full-path="Events/Launch/missing.dat" manifest:media-type="application/octet-stream" manifest:size="21"/>
  <manifest:file-entry manifest:full-path="Events/Launch/encrypted.dat" manifest:media-type="application/octet-stream" manifest:size="{$encryptedSize}">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="event-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Event package sidecars.</text:p>
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
    <dc:title>Event Sidecar Packet</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Events/Launch/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Events/Launch/events.xml', 'data' => $eventDefinitionXml, 'compressionMethod' => 0],
    ['name' => 'Events/Launch/launch.js', 'data' => $launchScriptBytes, 'compressionMethod' => 0],
    ['name' => 'Events/Launch/metadata.json', 'data' => $metadataBytes, 'compressionMethod' => 0],
    ['name' => 'Events/Launch/encrypted.dat', 'data' => $encryptedBytes, 'compressionMethod' => 0],
    ['name' => 'Events/Launch/orphan.dat', 'data' => $orphanBytes, 'compressionMethod' => 0],
], 'odt event package sidecars');

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
    'reports ODT event package sidecars as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $eventDefinitionXml,
        $launchScriptBytes,
        $metadataBytes,
        $orphanBytes,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $readerEvents = $result['packageEvents'];
        $readerItems = $indexBy($readerEvents['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $readerProvenance = $result['importReport']['manifest']['packageProvenance'];
        $readerMediaResources = $readerProvenance['mediaResources'];
        $readerMediaResourceByPart = $indexBy($readerMediaResources['items'], 'part');
        $readerMediaResourcePrecedenceByPart = $indexBy($readerMediaResources['packageRolePrecedenceItems'], 'part');

        $t->same($readerEvents, $result['document']->attr('packageEvents'));
        $t->same($readerEvents, $result['metadata']['odfPackageEvents']);
        $t->same($readerEvents, $result['importReport']['packageEvents']);
        $t->same(7, $readerEvents['count']);
        $t->same(4, $readerEvents['readableCount']);
        $t->same(6, $readerEvents['declaredCount']);
        $t->same(1, $readerEvents['undeclaredCount']);
        $t->same(1, $readerEvents['missingCount']);
        $t->same(1, $readerEvents['directoryCount']);
        $t->same(1, $readerEvents['encryptedCount']);
        $t->same(0, $readerEvents['missingMediaTypeCount']);
        $t->same(0, $readerEvents['invalidMediaTypeCount']);
        $t->same(3, $readerEvents['issueCount']);
        $t->same([
            'odf-event-package-encrypted-part',
            'odf-event-package-missing-part',
            'odf-event-package-undeclared-part',
        ], $readerEvents['issueCodes']);
        $t->same([
            'event-binary-resource' => 3,
            'event-definition' => 1,
            'event-directory' => 1,
            'event-metadata' => 1,
            'event-script' => 1,
        ], $readerEvents['kindCounts']);
        $t->same(['launch' => 7], $readerEvents['groupCounts']);
        $t->same('event-package-bytes-blocked', $readerEvents['byteExposurePolicy']);
        $t->same('event-package-metadata-only', $readerEvents['reviewPolicy']);

        $definition = $readerItems['Events/Launch/events.xml'];
        $t->same('event-definition', $definition['kind']);
        $t->same(strlen($eventDefinitionXml), $definition['byteLength']);
        $t->same(sprintf('%08x', crc32($eventDefinitionXml)), $definition['crc32']);
        $t->same(false, $definition['canExposeBytes']);
        $t->same(false, $definition['canExposeAsDocumentMedia']);
        $t->same('event-package-bytes-blocked', $definition['byteExposurePolicy']);
        $t->same([], $definition['issues']);

        $script = $readerItems['Events/Launch/launch.js'];
        $t->same('event-script', $script['kind']);
        $t->same('application/javascript', $script['mediaTypeBase']);
        $t->same(strlen($launchScriptBytes), $script['byteLength']);

        $metadata = $readerItems['Events/Launch/metadata.json'];
        $t->same('event-metadata', $metadata['kind']);
        $t->same('application/json', $metadata['mediaTypeBase']);
        $t->same(strlen($metadataBytes), $metadata['byteLength']);

        $missing = $readerItems['Events/Launch/missing.dat'];
        $t->same(false, $missing['exists']);
        $t->same(false, $missing['valid']);
        $t->same(['odf-event-package-missing-part'], $missing['issues']);
        $t->same('event-package-bytes-blocked', $missing['byteExposurePolicy']);

        $encrypted = $readerItems['Events/Launch/encrypted.dat'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(['odf-event-package-encrypted-part'], $encrypted['issues']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);

        $orphan = $readerItems['Events/Launch/orphan.dat'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('event-binary-resource', $orphan['kind']);
        $t->same(strlen($orphanBytes), $orphan['byteLength']);
        $t->same(['odf-event-package-undeclared-part'], $orphan['issues']);
        $t->same('event-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $manifestScript = $manifestByPart['Events/Launch/launch.js'];
        $t->same(true, $manifestScript['eventPackagePart']);
        $t->same(false, $manifestScript['canExposeBytes']);
        $t->same(null, $manifestScript['byteLength']);
        $t->same(strlen($launchScriptBytes), $manifestScript['storedByteLength']);
        $t->same(null, $manifestScript['byteSha256']);
        $t->same('event-package-bytes-blocked', $manifestScript['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(1, $readerMediaResources['mediaResourceCount']);
        $t->same(4, $readerMediaResources['packageRolePrecedenceCount']);
        $t->same([
            'Pictures/hero.png',
            'Events/Launch/launch.js',
            'Events/Launch/metadata.json',
            'Events/Launch/missing.dat',
            'Events/Launch/encrypted.dat',
        ], array_column($readerMediaResources['items'], 'part'));
        $t->same(['event-package'], $readerMediaResourceByPart['Events/Launch/launch.js']['packageRolePrecedence']);
        $t->same(false, $readerMediaResourceByPart['Events/Launch/launch.js']['mediaResource']);
        $t->same(['odf-media-resource-package-role-precedence'], $readerMediaResourceByPart['Events/Launch/launch.js']['issues']);
        $t->same('event-package-bytes-blocked', $readerMediaResourcePrecedenceByPart['Events/Launch/launch.js']['byteExposurePolicy']);
        $t->same(6, $readerProvenance['eventPackagePartCount']);
        $t->same(6, $readerProvenance['roleCounts']['event-package']);
        $t->same(1, $readerProvenance['undeclaredRoleCounts']['event-package']);
        $t->same(['event-package', 'manifest-declared'], $readerProvenance['parts']['Events/Launch/launch.js']['roles']);
        $t->same(['event-package', 'undeclared-package-entry'], $readerProvenance['parts']['Events/Launch/orphan.dat']['roles']);
        $t->same(true, $readerProvenance['parts']['Events/Launch/launch.js']['eventPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['manifestEntries'][7]['eventPackagePart']);
        $t->same(true, $readerProvenance['packageIdentity']['packageEntries'][8]['eventPackagePart']);
        $t->same(6, $readerProvenance['packageIdentity']['eventPackagePartCount']);

        $blocks = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Event package sidecars.', $blocks);
        $t->same(false, str_contains($blocks, $eventDefinitionXml));
        $t->same(false, str_contains($blocks, $launchScriptBytes));
        $t->same(false, str_contains($blocks, $metadataBytes));
        $t->same(false, str_contains($blocks, $orphanBytes));

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactEvents = $compactSummary['packageEvents'];
        $compactItems = $indexBy($compactEvents['items'], 'packagePath');
        $reviewByPath = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $compactMediaResources = $compactSummary['manifestReview']['mediaResources'];
        $compactMediaResourceByPart = $indexBy($compactMediaResources['items'], 'part');
        $compactMediaResourcePrecedenceByPart = $indexBy($compactMediaResources['packageRolePrecedenceItems'], 'part');
        $inventory = $compactSummary['packageInventory'];

        $t->same(7, $compactEvents['count']);
        $t->same(4, $compactEvents['readableCount']);
        $t->same(6, $compactEvents['declaredCount']);
        $t->same(1, $compactEvents['undeclaredCount']);
        $t->same(1, $compactEvents['missingCount']);
        $t->same(1, $compactEvents['directoryCount']);
        $t->same(1, $compactEvents['encryptedCount']);
        $t->same(3, $compactEvents['issueCount']);
        $t->same($readerEvents['issueCodes'], $compactEvents['issueCodes']);
        $t->same('event-package-bytes-blocked', $compactEvents['byteExposurePolicy']);
        $t->same('event-package-metadata-only', $compactEvents['reviewPolicy']);
        $t->same('event-definition', $compactItems['Events/Launch/events.xml']['kind']);
        $t->same(false, $compactItems['Events/Launch/events.xml']['canExposeBytes']);
        $t->same(false, $compactItems['Events/Launch/events.xml']['canExposeAsDocumentMedia']);
        $t->same(strlen($eventDefinitionXml), $compactItems['Events/Launch/events.xml']['byteLength']);
        $t->same(sprintf('%08x', crc32($eventDefinitionXml)), $compactItems['Events/Launch/events.xml']['crc32']);
        $t->same('event-script', $compactItems['Events/Launch/launch.js']['kind']);
        $t->same('event-metadata', $compactItems['Events/Launch/metadata.json']['kind']);
        $t->same(['odf-event-package-missing-part'], $compactItems['Events/Launch/missing.dat']['issues']);
        $t->same(['odf-event-package-encrypted-part'], $compactItems['Events/Launch/encrypted.dat']['issues']);
        $t->same(['odf-event-package-undeclared-part'], $compactItems['Events/Launch/orphan.dat']['issues']);

        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
        $t->same(6, $compactSummary['manifestReview']['eventPackagePartCount']);
        $t->same(true, $reviewByPath['Events/Launch/launch.js']['eventPackagePart']);
        $t->same(false, $reviewByPath['Events/Launch/launch.js']['canExposeBytes']);
        $t->same(null, $reviewByPath['Events/Launch/launch.js']['byteLength']);
        $t->same(strlen($launchScriptBytes), $reviewByPath['Events/Launch/launch.js']['storedByteLength']);
        $t->same('event-package-bytes-blocked', $reviewByPath['Events/Launch/launch.js']['byteExposurePolicy']);
        $t->same('event', $reviewByPath['Events/Launch/launch.js']['manifestMediaFamily']);
        $t->same(1, $compactMediaResources['mediaResourceCount']);
        $t->same(4, $compactMediaResources['packageRolePrecedenceCount']);
        $t->same([
            'Pictures/hero.png',
            'Events/Launch/launch.js',
            'Events/Launch/metadata.json',
            'Events/Launch/missing.dat',
            'Events/Launch/encrypted.dat',
        ], array_column($compactMediaResources['items'], 'part'));
        $t->same(['event-package'], $compactMediaResourceByPart['Events/Launch/launch.js']['packageRolePrecedence']);
        $t->same(false, $compactMediaResourceByPart['Events/Launch/launch.js']['mediaResource']);
        $t->same(['odf-media-resource-package-role-precedence'], $compactMediaResourceByPart['Events/Launch/launch.js']['issues']);
        $t->same('event-package-bytes-blocked', $compactMediaResourcePrecedenceByPart['Events/Launch/launch.js']['byteExposurePolicy']);
        $t->same(5, $compactSummary['manifestReview']['manifestMediaFamilyCounts']['event']);
        $t->same(6, $inventory['eventPackagePartCount']);
        $t->same(6, $inventory['roleCounts']['event-package']);
        $t->same(1, $inventory['undeclaredRoleCounts']['event-package']);
        $t->same(['event-package', 'manifest-declared'], $inventory['parts']['Events/Launch/launch.js']['roles']);
        $t->same(['event-package', 'undeclared-package-entry'], $inventory['parts']['Events/Launch/orphan.dat']['roles']);
        $t->same(true, $inventory['parts']['Events/Launch/launch.js']['eventPackagePart']);
        $t->same(false, $inventory['parts']['Events/Launch/launch.js']['canExposeBytes']);
        $t->same(true, $compactSummary['packageIdentity']['manifestEntries'][7]['eventPackagePart']);
        $t->same(true, $compactSummary['packageIdentity']['packageEntries'][8]['eventPackagePart']);
        $t->same(6, $compactSummary['packageIdentity']['eventPackagePartCount']);
    },
];
