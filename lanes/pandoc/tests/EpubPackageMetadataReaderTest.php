<?php

declare(strict_types=1);

use PortLibs\Pandoc\EpubPackageMetadataReader;
use PortLibs\Pandoc\JsonWriter;
use PortLibs\Pandoc\WordPressBlockWriter;

return [
    'reads epub opf dublin core metadata into pandoc document metadata' => static function (TestRunner $t): void {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="pub-id">
  <metadata>
    <dc:identifier id="isbn">urn:isbn:9780000000000</dc:identifier>
    <dc:identifier id="pub-id">urn:uuid:12345678-1234-1234-1234-123456789abc</dc:identifier>
    <dc:title>EPUB Metadata Title</dc:title>
    <dc:creator>First Author</dc:creator>
    <dc:creator>Second Author</dc:creator>
    <dc:language>en-US</dc:language>
    <dc:language>fr-CA</dc:language>
    <dc:date>2026-04-05</dc:date>
    <dc:publisher>Example Press</dc:publisher>
    <dc:subject>migration</dc:subject>
    <dc:subject>package metadata</dc:subject>
    <meta property="dcterms:modified">2026-04-06T07:08:09Z</meta>
  </metadata>
</package>
XML;

        $document = (new EpubPackageMetadataReader())->readPackageXml($xml);
        $meta = $document->attr('meta');
        $json = json_decode((new JsonWriter())->write($document), true, 512, JSON_THROW_ON_ERROR);

        $t->same('document', $document->type);
        $t->same('EPUB Metadata Title', $meta['title']);
        $t->same(['First Author', 'Second Author'], $meta['author']);
        $t->same('en-US', $meta['lang']);
        $t->same('en-US', $meta['language']);
        $t->same(['en-US', 'fr-CA'], $meta['languages']);
        $t->same('2026-04-05', $meta['date']);
        $t->same('urn:uuid:12345678-1234-1234-1234-123456789abc', $meta['identifier']);
        $t->same('Example Press', $meta['publisher']);
        $t->same(['migration', 'package metadata'], $meta['subject']);
        $t->same('2026-04-06T07:08:09Z', $meta['modified']);
        $t->same(['2026-04-06T07:08:09Z'], $meta['epubProperties']['dcterms:modified']);
        $t->same('MetaInlines', $json['meta']['title']['t']);
        $t->same('MetaList', $json['meta']['author']['t']);
        $t->same('MetaList', $json['meta']['languages']['t']);
        $t->same('MetaList', $json['meta']['subject']['t']);
    },
    'discovers opf rootfile from an epub zip container' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }

        $zip->addFromString('META-INF/container.xml', <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml"/>
  </rootfiles>
</container>
XML);
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-001</dc:identifier>
    <dc:title>Packaged EPUB</dc:title>
    <dc:creator>Package Author</dc:creator>
    <dc:language>fr</dc:language>
  </metadata>
</package>
XML);
        $zip->close();

        try {
            $document = (new EpubPackageMetadataReader())->readEpubFile($path);
            $meta = $document->attr('meta');
            $blocks = (new WordPressBlockWriter(['includeMetadata' => true]))->write($document);
        } finally {
            @unlink($path);
        }

        $t->same('Packaged EPUB', $meta['title']);
        $t->same('Package Author', $meta['author']);
        $t->same('fr', $meta['lang']);
        $t->same('fr', $meta['language']);
        $t->same('book-001', $meta['identifier']);
        $t->contains('<dt data-pandoc-meta-key="identifier">identifier</dt><dd><span>book-001</span></dd>', $blocks);
        $t->contains('<dt data-pandoc-meta-key="lang">lang</dt><dd><span>fr</span></dd>', $blocks);
    },
    'discovers parameterized opf rootfile after non-opf rootfile' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-metadata-rootfile-media-type-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }

        $zip->addFromString('META-INF/container.xml', <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles>
    <rootfile full-path="OPS/preview.xhtml" media-type="application/xhtml+xml"/>
    <rootfile full-path="OPS/package.opf" media-type="application/oebps-package+xml; profile=&quot;primary&quot;"/>
  </rootfiles>
</container>
XML);
        $zip->addFromString('OPS/preview.xhtml', '<html xmlns="http://www.w3.org/1999/xhtml"><body><p>Preview only.</p></body></html>');
        $zip->addFromString('OPS/package.opf', <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<package xmlns="http://www.idpf.org/2007/opf"
         xmlns:dc="http://purl.org/dc/elements/1.1/"
         version="3.0"
         unique-identifier="book-id">
  <metadata>
    <dc:identifier id="book-id">book-parameterized-metadata-rootfile</dc:identifier>
    <dc:title>Parameterized Metadata Rootfile</dc:title>
    <dc:creator>Package Author</dc:creator>
    <dc:language>en</dc:language>
  </metadata>
</package>
XML);
        $zip->close();

        try {
            $meta = (new EpubPackageMetadataReader())->readEpubFile($path)->attr('meta');
        } finally {
            @unlink($path);
        }

        $t->same('Parameterized Metadata Rootfile', $meta['title']);
        $t->same('Package Author', $meta['author']);
        $t->same('book-parameterized-metadata-rootfile', $meta['identifier']);
    },
    'rejects epub containers without an opf rootfile' => static function (TestRunner $t): void {
        $path = tempnam(sys_get_temp_dir(), 'pandoc-epub-');
        if ($path === false) {
            throw new RuntimeException('Unable to create temporary EPUB path');
        }

        $zip = new ZipArchive();
        $opened = $zip->open($path, ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new RuntimeException('Unable to create temporary EPUB package');
        }
        $zip->addFromString('META-INF/container.xml', <<<'XML'
<?xml version="1.0"?>
<container xmlns="urn:oasis:names:tc:opendocument:xmlns:container" version="1.0">
  <rootfiles/>
</container>
XML);
        $zip->close();

        try {
            $t->throws(InvalidArgumentException::class, static function () use ($path): void {
                (new EpubPackageMetadataReader())->readEpubFile($path);
            });
        } finally {
            @unlink($path);
        }
    },
];
