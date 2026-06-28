<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$basicLibraryXml = '<library:library xmlns:library="http://openoffice.org/2000/library" library:name="Standard"/>';
$basicModuleXml = '<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review">Sub Approve' . "\n" . 'End Sub</script:module>';
$javaScript = 'function approveReview() { return false; }';
$rubyScript = 'puts "package review"';
$encryptedScript = 'encrypted macro payload';
$orphanPython = 'print("orphan review")';

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Basic/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Basic/Standard/script.xlb" manifest:media-type="text/xml" manifest:size="__LIBRARY_SIZE__"/>
  <manifest:file-entry manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="__BASIC_SIZE__"/>
  <manifest:file-entry manifest:full-path="Scripts/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Scripts/review.js" manifest:media-type="application/javascript" manifest:size="__JS_SIZE__"/>
  <manifest:file-entry manifest:full-path="Scripts/ruby/review.rb" manifest:media-type="text/x-ruby" manifest:size="__RUBY_SIZE__"/>
  <manifest:file-entry manifest:full-path="Scripts/missing.js" manifest:media-type="application/javascript"/>
  <manifest:file-entry manifest:full-path="Scripts/encrypted.js" manifest:media-type="application/javascript" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="script-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$manifestXml = str_replace(
    ['__LIBRARY_SIZE__', '__BASIC_SIZE__', '__JS_SIZE__', '__RUBY_SIZE__'],
    [(string) strlen($basicLibraryXml), (string) strlen($basicModuleXml), (string) strlen($javaScript), (string) strlen($rubyScript)],
    $manifestXml
);

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Script package metadata review.</text:p>
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
    <dc:title>Script Package Metadata</dc:title>
  </office:meta>
</office:document-meta>
XML;

$buildPackage = static fn (?string $manifestOverride = null): ZipPackage => ZipPackage::fromParts([
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestOverride ?? $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Basic/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/script.xlb', 'data' => $basicLibraryXml, 'compressionMethod' => 0],
    ['name' => 'Basic/Standard/Review.xml', 'data' => $basicModuleXml, 'compressionMethod' => 0],
    ['name' => 'Scripts/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Scripts/review.js', 'data' => $javaScript, 'compressionMethod' => 0],
    ['name' => 'Scripts/ruby/review.rb', 'data' => $rubyScript, 'compressionMethod' => 0],
    ['name' => 'Scripts/encrypted.js', 'data' => $encryptedScript, 'compressionMethod' => 0],
    ['name' => 'Scripts/orphan.py', 'data' => $orphanPython, 'compressionMethod' => 0],
], 'odt reader script package metadata');

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
    'reports ODT script package sidecars as metadata-only package review data' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $basicLibraryXml,
        $basicModuleXml,
        $javaScript,
        $rubyScript,
        $encryptedScript,
        $orphanPython
    ): void {
        $package = $buildPackage();
        $result = (new OdfReader())->readPackage($package);
        $scripts = $result['packageScripts'];
        $items = $indexBy($scripts['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $provenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($scripts, $result['document']->attr('packageScripts'));
        $t->same($scripts, $result['metadata']['odfPackageScripts']);
        $t->same($scripts, $result['importReport']['packageScripts']);
        $t->same(9, $scripts['count']);
        $t->same(7, $scripts['fileCount']);
        $t->same(2, $scripts['directoryCount']);
        $t->same(8, $scripts['storedPartCount']);
        $t->same(5, $scripts['readableCount']);
        $t->same(8, $scripts['declaredCount']);
        $t->same(1, $scripts['undeclaredCount']);
        $t->same(1, $scripts['missingCount']);
        $t->same(1, $scripts['encryptedCount']);
        $t->same(0, $scripts['invalidMediaTypeCount']);
        $t->same(3, $scripts['issueCount']);
        $t->same([
            'odf-script-encrypted-package-part',
            'odf-script-missing-package-part',
            'odf-script-undeclared-package-part',
        ], $scripts['issueCodes']);
        $t->same(['basic', 'scripts'], $scripts['scriptContainers']);
        $t->same(['basic-library-index', 'basic-module', 'javascript', 'python', 'ruby', 'script-directory'], $scripts['scriptKinds']);
        $t->same('script-package-bytes-blocked', $scripts['byteExposurePolicy']);
        $t->same('package-script-metadata-only', $scripts['reviewPolicy']);

        $basicDirectory = $items['Basic/'];
        $t->same(true, $basicDirectory['isDirectory']);
        $t->same('basic', $basicDirectory['scriptContainer']);
        $t->same('script-directory', $basicDirectory['scriptKind']);
        $t->same(null, $basicDirectory['scriptPath']);
        $t->same(null, $basicDirectory['scriptModule']);
        $t->same(0, $basicDirectory['storedByteLength']);
        $t->same(null, $basicDirectory['byteLength']);
        $t->same(false, $basicDirectory['canExposeBytes']);
        $t->same('directory-entry-no-bytes', $basicDirectory['byteExposurePolicy']);

        $basicLibraryIndex = $items['Basic/Standard/script.xlb'];
        $t->same('basic-library-index', $basicLibraryIndex['scriptKind']);
        $t->same('Standard', $basicLibraryIndex['scriptLibrary']);
        $t->same('script', $basicLibraryIndex['scriptModule']);
        $t->same('xlb', $basicLibraryIndex['extension']);
        $t->same('text/xml', $basicLibraryIndex['mediaType']);
        $t->same(true, $basicLibraryIndex['valid']);
        $t->same(strlen($basicLibraryXml), $basicLibraryIndex['byteLength']);
        $t->same(sprintf('%08x', crc32($basicLibraryXml)), $basicLibraryIndex['crc32']);
        $t->same(false, $basicLibraryIndex['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $basicLibraryIndex['byteExposurePolicy']);
        $t->same([], $basicLibraryIndex['issues']);

        $basicScript = $items['Basic/Standard/Review.xml'];
        $t->same('basic-module', $basicScript['scriptKind']);
        $t->same('Standard', $basicScript['scriptLibrary']);
        $t->same('Review', $basicScript['scriptModule']);
        $t->same('text/xml', $basicScript['mediaType']);
        $t->same(true, $basicScript['valid']);
        $t->same(strlen($basicModuleXml), $basicScript['byteLength']);
        $t->same(sprintf('%08x', crc32($basicModuleXml)), $basicScript['crc32']);
        $t->same(false, $basicScript['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $basicScript['byteExposurePolicy']);
        $t->same([], $basicScript['issues']);

        $manifestBasic = $manifestByPart['Basic/Standard/Review.xml'];
        $t->same(true, $manifestBasic['scriptPackagePart']);
        $t->same(false, $manifestBasic['canExposeBytes']);
        $t->same(null, $manifestBasic['byteLength']);
        $t->same(strlen($basicModuleXml), $manifestBasic['storedByteLength']);
        $t->same(null, $manifestBasic['byteSha256']);
        $t->same('script-package-bytes-blocked', $manifestBasic['byteExposurePolicy']);

        $javascript = $items['Scripts/review.js'];
        $t->same('scripts', $javascript['scriptContainer']);
        $t->same('javascript', $javascript['scriptKind']);
        $t->same('review.js', $javascript['scriptPath']);
        $t->same('review', $javascript['scriptModule']);
        $t->same(strlen($javaScript), $javascript['byteLength']);
        $t->same(sprintf('%08x', crc32($javaScript)), $javascript['crc32']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/review.js']['byteExposurePolicy']);

        $ruby = $items['Scripts/ruby/review.rb'];
        $t->same('scripts', $ruby['scriptContainer']);
        $t->same('ruby', $ruby['scriptKind']);
        $t->same('ruby/review.rb', $ruby['scriptPath']);
        $t->same('ruby', $ruby['scriptLibrary']);
        $t->same('review', $ruby['scriptModule']);
        $t->same('rb', $ruby['extension']);
        $t->same('text/x-ruby', $ruby['mediaType']);
        $t->same(true, $ruby['valid']);
        $t->same(strlen($rubyScript), $ruby['byteLength']);
        $t->same(sprintf('%08x', crc32($rubyScript)), $ruby['crc32']);
        $t->same(false, $ruby['canExposeBytes']);
        $t->same(false, $ruby['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/ruby/review.rb']['byteExposurePolicy']);

        $missing = $items['Scripts/missing.js'];
        $t->same(false, $missing['exists']);
        $t->same(null, $missing['byteLength']);
        $t->same(['odf-script-missing-package-part'], $missing['issues']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/missing.js']['byteExposurePolicy']);

        $encrypted = $items['Scripts/encrypted.js'];
        $t->same(true, $encrypted['encrypted']);
        $t->same(false, $encrypted['valid']);
        $t->same(null, $encrypted['byteLength']);
        $t->same(strlen($encryptedScript), $encrypted['storedByteLength']);
        $t->same(sprintf('%08x', crc32($encryptedScript)), $encrypted['storedCrc32']);
        $t->same('encrypted-resource-bytes-blocked', $encrypted['byteExposurePolicy']);
        $t->same(['odf-script-encrypted-package-part'], $encrypted['issues']);

        $orphan = $items['Scripts/orphan.py'];
        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('text/x-python', $orphan['mediaType']);
        $t->same('python', $orphan['scriptKind']);
        $t->same(strlen($orphanPython), $orphan['byteLength']);
        $t->same(['odf-script-undeclared-package-part'], $orphan['issues']);
        $t->same('script-package-bytes-blocked', $orphan['byteExposurePolicy']);

        $t->same(['Pictures/hero.png'], array_column($result['media'], 'part'));
        $t->same(['manifest-declared', 'script-package'], $provenance['parts']['Scripts/review.js']['roles']);
        $t->same(['manifest-declared', 'script-package'], $provenance['parts']['Scripts/ruby/review.rb']['roles']);
        $t->same(['script-package', 'undeclared-package-entry'], $provenance['parts']['Scripts/orphan.py']['roles']);
        $t->same(1, $provenance['undeclaredRoleCounts']['script-package']);
        $t->same('Scripts/orphan.py', $result['importReport']['manifest']['undeclaredEntries'][0]['part']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactScripts = $compactSummary['packageScripts'];
        $compactItems = $indexBy($compactScripts['items'], 'part');
        $compactReview = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $t->same($scripts['scriptKinds'], $compactScripts['scriptKinds']);
        $t->same('ruby', $compactItems['Scripts/ruby/review.rb']['scriptKind']);
        $t->same('text/x-ruby', $compactItems['Scripts/ruby/review.rb']['mediaType']);
        $t->same('ruby/review.rb', $compactItems['Scripts/ruby/review.rb']['scriptPath']);
        $t->same('ruby', $compactItems['Scripts/ruby/review.rb']['scriptLibrary']);
        $t->same(false, $compactItems['Scripts/ruby/review.rb']['canExposeBytes']);
        $t->same(false, $compactItems['Scripts/ruby/review.rb']['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $compactReview['Scripts/ruby/review.rb']['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
    },
    'accepts ODT ruby package script media-type aliases as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $manifestXml
    ): void {
        $rubyAliasManifest = str_replace(
            'manifest:full-path="Scripts/ruby/review.rb" manifest:media-type="text/x-ruby"',
            'manifest:full-path="Scripts/ruby/review.rb" manifest:media-type="application/x-ruby"',
            $manifestXml
        );
        $package = $buildPackage($rubyAliasManifest);
        $result = (new OdfReader())->readPackage($package);
        $scripts = $result['packageScripts'];
        $items = $indexBy($scripts['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');

        $t->same(0, $scripts['invalidMediaTypeCount']);
        $t->same(['basic-library-index', 'basic-module', 'javascript', 'python', 'ruby', 'script-directory'], $scripts['scriptKinds']);
        $t->same('application/x-ruby', $items['Scripts/ruby/review.rb']['mediaType']);
        $t->same('application/x-ruby', $items['Scripts/ruby/review.rb']['mediaTypeBase']);
        $t->same('ruby', $items['Scripts/ruby/review.rb']['scriptKind']);
        $t->same(true, $items['Scripts/ruby/review.rb']['valid']);
        $t->same([], $items['Scripts/ruby/review.rb']['issues']);
        $t->same(false, $items['Scripts/ruby/review.rb']['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/ruby/review.rb']['byteExposurePolicy']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactItems = $indexBy($compactSummary['packageScripts']['items'], 'part');
        $t->same('application/x-ruby', $compactItems['Scripts/ruby/review.rb']['mediaType']);
        $t->same('ruby', $compactItems['Scripts/ruby/review.rb']['scriptKind']);
        $t->same(true, $compactItems['Scripts/ruby/review.rb']['valid']);
        $t->same(false, $compactItems['Scripts/ruby/review.rb']['canExposeBytes']);
    },
];
