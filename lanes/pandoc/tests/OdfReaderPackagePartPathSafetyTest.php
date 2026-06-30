<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\ZipPackage;

$minimalContentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Package part path safety packet.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;

$manifestForPath = static function (string $fullPath): string {
    $fullPath = htmlspecialchars($fullPath, ENT_QUOTES | ENT_XML1);

    return <<<XML
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="{$fullPath}" manifest:media-type="image/png"/>
</manifest:manifest>
XML;
};

$packageWithManifestPath = static function (string $fullPath) use ($manifestForPath, $minimalContentXml): ZipPackage {
    return ZipPackage::fromParts([
        ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
        ['name' => 'META-INF/manifest.xml', 'data' => $manifestForPath($fullPath)],
        ['name' => 'content.xml', 'data' => $minimalContentXml],
    ]);
};

return [
    'rejects unsafe decoded ODT manifest package part paths' => static function (TestRunner $t) use ($packageWithManifestPath): void {
        $unsafePaths = [
            'malformed percent escape' => 'Pictures/%ZZhero.png',
            'decoded leading slash' => '%2FPictures/hero.png',
            'decoded empty segment' => 'Pictures/%2Fhero.png',
            'decoded control byte' => 'Pictures/%00hero.png',
            'empty segment' => 'Pictures//hero.png',
            'literal dot segment' => 'Pictures/./hero.png',
            'encoded dot segment' => 'Pictures/%2E/hero.png',
        ];

        $reader = new OdfReader();
        foreach ($unsafePaths as $path) {
            $t->throws(\InvalidArgumentException::class, static fn (): array => $reader->readPackage($packageWithManifestPath($path)));
        }
    },

    'rejects unsafe decoded ODT content package references' => static function (TestRunner $t): void {
        $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
</manifest:manifest>
XML;
        $contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Unsafe image <draw:frame draw:name="Unsafe"><draw:image xlink:href="./Pictures/%2Fsecret.png"><svg:desc>Unsafe</svg:desc></draw:image></draw:frame>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $package = ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
        ]);

        $t->throws(\InvalidArgumentException::class, static fn (): array => (new OdfReader())->readPackage($package));
    },

    'preserves safe leading dot ODT package references with encoded spaces' => static function (TestRunner $t): void {
        $manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/source%20hero.png" manifest:media-type="image/png" manifest:size="8"/>
</manifest:manifest>
XML;
        $contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  xmlns:draw="urn:oasis:names:tc:opendocument:xmlns:drawing:1.0"
  xmlns:xlink="http://www.w3.org/1999/xlink"
  xmlns:svg="urn:oasis:names:tc:opendocument:xmlns:svg-compatible:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Encoded image <draw:frame draw:name="Encoded"><draw:image xlink:href="./Pictures/source%20hero.png"><svg:desc>Decoded source hero</svg:desc></draw:image></draw:frame>.</text:p>
    </office:text>
  </office:body>
</office:document-content>
XML;
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts([
            ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
            ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml],
            ['name' => 'content.xml', 'data' => $contentXml],
            ['name' => 'Pictures/source hero.png', 'data' => 'PNGDATA!', 'compressionMethod' => 0],
        ]));

        $image = $result['document']->children[0]->children[1];

        $t->same(1, count($result['media']));
        $t->same('Pictures/source hero.png', $result['media'][0]['part']);
        $t->same('image', $image->type);
        $t->same('./Pictures/source%20hero.png', $image->attr('url'));
        $t->same('Pictures/source hero.png', $image->attr('sourcePart'));
        $t->same(8, $image->attr('bytes'));
    },
];
