<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$checksum = base64_encode('checksum bytes');
$iv = base64_encode(str_repeat("\x01", 16));
$salt = base64_encode('salty-salt-123456');

$manifestXml = <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/locked.png" manifest:media-type="image/png" manifest:size="9">
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="{$checksum}">
      <manifest:algorithm manifest:algorithm-name="AES-256-CBC" manifest:initialisation-vector="{$iv}"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="32" manifest:iteration-count="600000" manifest:salt="{$salt}"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA256" manifest:key-size="32"/>
    </manifest:encryption-data>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="Basic/Standard/Module1.xml" manifest:media-type="text/xml" manifest:size="9">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="not base64!!">
      <manifest:algorithm manifest:algorithm-name="ChaCha20" manifest:initialisation-vector="bad iv!!"/>
      <manifest:key-derivation manifest:key-derivation-name="Argon2id" manifest:iteration-count="3" manifest:salt="bad salt!!"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
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
      <text:p>Encrypted package review.</text:p>
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
    <dc:title>Encryption Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/locked.png', 'data' => 'PNG-BYTES', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Module1.xml', 'data' => '<script/>', 'compressionMethod' => 0],
], 'odt encryption profile review');

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
    'classifies ODT manifest encryption profiles without exposing encrypted package bytes' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $summary = $result['importReport']['manifest']['encryption'];
        $itemsByPart = $indexBy($summary['items'], 'part');

        $locked = $manifestByPart['Pictures/locked.png'];
        $lockedEncryption = $locked['encryption'];
        $t->same(false, $locked['canExposeBytes']);
        $t->same(null, $locked['byteLength']);
        $t->same('encrypted-resource-bytes-blocked', $locked['byteExposurePolicy']);
        $t->same('sha256-1k', $lockedEncryption['checksumTypeProfile']);
        $t->same(true, $lockedEncryption['checksumBase64Valid']);
        $t->same(strlen('checksum bytes'), $lockedEncryption['checksumDecodedByteLength']);
        $t->same('aes-256', $lockedEncryption['algorithm']['profile']);
        $t->same(true, $lockedEncryption['algorithm']['initialisationVectorBase64Valid']);
        $t->same(16, $lockedEncryption['algorithm']['initialisationVectorDecodedByteLength']);
        $t->same('pbkdf2', $lockedEncryption['keyDerivation']['profile']);
        $t->same('100k-plus', $lockedEncryption['keyDerivation']['iterationCountBucket']);
        $t->same(true, $lockedEncryption['keyDerivation']['saltBase64Valid']);
        $t->same(strlen('salty-salt-123456'), $lockedEncryption['keyDerivation']['saltDecodedByteLength']);
        $t->same('sha256', $lockedEncryption['startKeyGeneration']['profile']);

        $script = $manifestByPart['Basic/Standard/Module1.xml'];
        $scriptEncryption = $script['encryption'];
        $t->same(false, $script['canExposeBytes']);
        $t->same('encrypted-resource-bytes-blocked', $script['byteExposurePolicy']);
        $t->same('sha1-1k', $scriptEncryption['checksumTypeProfile']);
        $t->same(false, $scriptEncryption['checksumBase64Valid']);
        $t->same(false, isset($scriptEncryption['checksumDecodedByteLength']));
        $t->same('chacha20', $scriptEncryption['algorithm']['profile']);
        $t->same(false, $scriptEncryption['algorithm']['initialisationVectorBase64Valid']);
        $t->same('argon2id', $scriptEncryption['keyDerivation']['profile']);
        $t->same('under-1k', $scriptEncryption['keyDerivation']['iterationCountBucket']);
        $t->same(false, $scriptEncryption['keyDerivation']['saltBase64Valid']);
        $t->same('sha1', $scriptEncryption['startKeyGeneration']['profile']);

        $t->same(2, $summary['encryptedItemCount']);
        $t->same(2, $summary['recordCount']);
        $t->same(['Pictures/locked.png', 'Basic/Standard/Module1.xml'], $summary['encryptedParts']);
        $t->same(['sha1-1k' => 1, 'sha256-1k' => 1], $summary['checksumTypeProfileCounts']);
        $t->same(['aes-256' => 1, 'chacha20' => 1], $summary['algorithmProfileCounts']);
        $t->same(['argon2id' => 1, 'pbkdf2' => 1], $summary['keyDerivationProfileCounts']);
        $t->same(['100k-plus' => 1, 'under-1k' => 1], $summary['keyDerivationIterationBucketCounts']);
        $t->same(['sha1' => 1, 'sha256' => 1], $summary['startKeyGenerationProfileCounts']);
        $t->same(6, $summary['base64ValueCount']);
        $t->same(3, $summary['validBase64ValueCount']);
        $t->same(3, $summary['invalidBase64ValueCount']);
        $t->same(['sha256-1k'], $itemsByPart['Pictures/locked.png']['checksumTypeProfiles']);
        $t->same(['aes-256'], $itemsByPart['Pictures/locked.png']['algorithmProfiles']);
        $t->same(3, $itemsByPart['Pictures/locked.png']['validBase64ValueCount']);
        $t->same(['sha1-1k'], $itemsByPart['Basic/Standard/Module1.xml']['checksumTypeProfiles']);
        $t->same(['chacha20'], $itemsByPart['Basic/Standard/Module1.xml']['algorithmProfiles']);
        $t->same(3, $itemsByPart['Basic/Standard/Module1.xml']['invalidBase64ValueCount']);

        $compact = OpenDocumentPackage::fromPackage($package);
        $compactSummary = $compact->summarize();
        $t->same($lockedEncryption, $compact->manifestEntry('Pictures/locked.png')['encryption']);
        $t->same($scriptEncryption, $compact->manifestEntry('Basic/Standard/Module1.xml')['encryption']);
        $t->same($summary, $compactSummary['manifestEncryption']);
        $t->same($summary, $compactSummary['manifestReview']['manifestEncryption']);
    },
];
