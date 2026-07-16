<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" xmlns:review="urn:wordpress:review" manifest:version="1.4">
  <manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="hero-checksum">
      <manifest:algorithm manifest:algorithm-name="Blowfish CFB" manifest:initialisation-vector="hero-iv"/>
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="16" manifest:iteration-count="1024" manifest:salt="hero-salt"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA1" manifest:key-size="20"/>
    </manifest:encryption-data>
  </manifest:file-entry>
  <manifest:file-entry manifest:full-path="Pictures/secret.bin" manifest:media-type="application/octet-stream" manifest:size="4096">
    <manifest:encryption-data manifest:checksum-type="SHA256/1K" manifest:checksum="secret-checksum">
      <manifest:algorithm manifest:algorithm-name="AES-256-CBC" manifest:initialisation-vector="secret-iv"/>
      <manifest:algorithm manifest:algorithm-name="AES-128-CBC" manifest:initialisation-vector="legacy-iv"/>
      <review:hint>legacy encryption metadata</review:hint>
    </manifest:encryption-data>
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="legacy-checksum">
      <manifest:key-derivation manifest:key-derivation-name="PBKDF2" manifest:key-size="32" manifest:iteration-count="2048" manifest:salt="secret-salt"/>
      <manifest:start-key-generation manifest:start-key-generation-name="SHA256" manifest:key-size="32"/>
    </manifest:encryption-data>
  </manifest:file-entry>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
  <office:body>
    <office:text>
      <text:p>Manifest encryption identity parity.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$stylesXml = <<<'XML'
<office:document-styles
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0">
  <office:styles>
    <style:style style:name="EncryptionReviewBody" style:family="paragraph"/>
  </office:styles>
</office:document-styles>
XML;

$metaXml = <<<'XML'
<office:document-meta
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:dc="http://purl.org/dc/elements/1.1/">
  <office:meta>
    <dc:title>Manifest Encryption Identity Parity</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/secret.bin', 'data' => 'SECRETBYTES', 'compressionMethod' => 0],
], 'odt manifest encryption identity parity');

return [
    'carries ODT manifest encryption summaries through package identities' => static function (TestRunner $t) use ($buildPackage): void {
        $compactSummary = OpenDocumentPackage::fromPackage($buildPackage())->summarize();
        $compactIdentity = $compactSummary['packageIdentity'];

        $richResult = (new OdfReader())->readPackage($buildPackage());
        $richProvenance = $richResult['importReport']['manifest']['packageProvenance'];
        $richIdentity = $richProvenance['packageIdentity'];
        $documentIdentity = $richResult['document']->attr('manifest')['packageProvenance']['packageIdentity'];

        $expectedEncryption = $compactSummary['manifestEncryption'];
        $t->same($expectedEncryption, $compactIdentity['manifestEncryption']);
        $t->same($richProvenance['manifestEncryption'], $richIdentity['manifestEncryption']);
        $t->same($richIdentity['manifestEncryption'], $documentIdentity['manifestEncryption']);
        $t->same($expectedEncryption, $richIdentity['manifestEncryption']);

        foreach ([$compactIdentity, $richIdentity, $documentIdentity] as $identity) {
            $encryption = $identity['manifestEncryption'];
            $itemsByPath = [];
            foreach ($encryption['items'] as $item) {
                $itemsByPath[(string) $item['path']] = $item;
            }

            $t->same(2, $encryption['encryptedItemCount']);
            $t->same(3, $encryption['recordCount']);
            $t->same(['Pictures/hero.png', 'Pictures/secret.bin'], $encryption['encryptedParts']);
            $t->same(['SHA1/1K' => 2, 'SHA256/1K' => 1], $encryption['checksumTypeCounts']);
            $t->same([
                'AES-128-CBC' => 1,
                'AES-256-CBC' => 1,
                'Blowfish CFB' => 1,
            ], $encryption['algorithmNameCounts']);
            $t->same(['PBKDF2' => 2], $encryption['keyDerivationNameCounts']);
            $t->same(['SHA1' => 1, 'SHA256' => 1], $encryption['startKeyGenerationNameCounts']);
            $t->same(['review:hint' => 1], $encryption['unknownChildNameCounts']);
            $t->same([
                'odf-manifest-encryption-multiple-algorithms' => 1,
                'odf-manifest-encryption-multiple-encryption-data' => 1,
                'odf-manifest-encryption-unknown-child' => 1,
            ], $encryption['issueCodeCounts']);
            $t->same(['Blowfish CFB'], $itemsByPath['Pictures/hero.png']['algorithmNames']);
            $t->same(['AES-256-CBC', 'AES-128-CBC'], $itemsByPath['Pictures/secret.bin']['algorithmNames']);
            $t->same(['review:hint'], $itemsByPath['Pictures/secret.bin']['unknownChildNames']);
            $t->same('encrypted-resource-bytes-blocked', $itemsByPath['Pictures/secret.bin']['byteExposurePolicy']);
        }
    },
];
