<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\ZipPackage;

$basicLibraryXml = '<library:library xmlns:library="http://openoffice.org/2000/library" library:name="Standard"/>';
$basicModuleXml = '<script:module xmlns:script="http://openoffice.org/2000/script" script:name="Review">Sub Approve' . "\n" . 'End Sub</script:module>';
$javaScript = 'function approveReview() { return false; }';
$rubyScript = 'puts "package review"';
$beanShellScript = 'print("package review");';
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
  <manifest:file-entry manifest:full-path="Scripts/beanshell/review.bsh" manifest:media-type="text/x-beanshell" manifest:size="__BEANSHELL_SIZE__"/>
  <manifest:file-entry manifest:full-path="Scripts/missing.js" manifest:media-type="application/javascript"/>
  <manifest:file-entry manifest:full-path="Scripts/encrypted.js" manifest:media-type="application/javascript" manifest:size="2048">
    <manifest:encryption-data manifest:checksum-type="SHA1/1K" manifest:checksum="script-checksum"/>
  </manifest:file-entry>
</manifest:manifest>
XML;

$manifestXml = str_replace(
    ['__LIBRARY_SIZE__', '__BASIC_SIZE__', '__JS_SIZE__', '__RUBY_SIZE__', '__BEANSHELL_SIZE__'],
    [
        (string) strlen($basicLibraryXml),
        (string) strlen($basicModuleXml),
        (string) strlen($javaScript),
        (string) strlen($rubyScript),
        (string) strlen($beanShellScript),
    ],
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

$buildPackage = static fn (?string $manifestOverride = null, array $extraParts = []): ZipPackage => ZipPackage::fromParts(array_merge([
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
    ['name' => 'Scripts/beanshell/review.bsh', 'data' => $beanShellScript, 'compressionMethod' => 0],
    ['name' => 'Scripts/encrypted.js', 'data' => $encryptedScript, 'compressionMethod' => 0],
    ['name' => 'Scripts/orphan.py', 'data' => $orphanPython, 'compressionMethod' => 0],
], $extraParts), 'odt reader script package metadata');

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
        $beanShellScript,
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
        $t->same(10, $scripts['count']);
        $t->same(8, $scripts['fileCount']);
        $t->same(2, $scripts['directoryCount']);
        $t->same(9, $scripts['storedPartCount']);
        $t->same(6, $scripts['readableCount']);
        $t->same(9, $scripts['declaredCount']);
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
        $t->same(['basic-library-index', 'basic-module', 'beanshell', 'javascript', 'python', 'ruby', 'script-directory'], $scripts['scriptKinds']);
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

        $beanShell = $items['Scripts/beanshell/review.bsh'];
        $t->same('scripts', $beanShell['scriptContainer']);
        $t->same('beanshell', $beanShell['scriptKind']);
        $t->same('beanshell/review.bsh', $beanShell['scriptPath']);
        $t->same('beanshell', $beanShell['scriptLibrary']);
        $t->same('review', $beanShell['scriptModule']);
        $t->same('bsh', $beanShell['extension']);
        $t->same('text/x-beanshell', $beanShell['mediaType']);
        $t->same(true, $beanShell['mediaTypeValid']);
        $t->same(true, $beanShell['valid']);
        $t->same(strlen($beanShellScript), $beanShell['byteLength']);
        $t->same(sprintf('%08x', crc32($beanShellScript)), $beanShell['crc32']);
        $t->same(false, $beanShell['canExposeBytes']);
        $t->same(false, $beanShell['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/beanshell/review.bsh']['byteExposurePolicy']);

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
        $t->same(['manifest-declared', 'script-package'], $provenance['parts']['Scripts/beanshell/review.bsh']['roles']);
        $t->same(['script-package', 'undeclared-package-entry'], $provenance['parts']['Scripts/orphan.py']['roles']);
        $t->same(1, $provenance['undeclaredRoleCounts']['script-package']);
        $t->same('Scripts/orphan.py', $result['importReport']['manifest']['undeclaredEntries'][0]['part']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactScripts = $compactSummary['packageScripts'];
        $compactItems = $indexBy($compactScripts['items'], 'part');
        $compactReview = $indexBy($compactSummary['manifestReview']['items'], 'path');
        $t->same($scripts['scriptKinds'], $compactScripts['scriptKinds']);
        $t->same('beanshell', $compactItems['Scripts/beanshell/review.bsh']['scriptKind']);
        $t->same('text/x-beanshell', $compactItems['Scripts/beanshell/review.bsh']['mediaType']);
        $t->same('beanshell/review.bsh', $compactItems['Scripts/beanshell/review.bsh']['scriptPath']);
        $t->same('beanshell', $compactItems['Scripts/beanshell/review.bsh']['scriptLibrary']);
        $t->same(false, $compactItems['Scripts/beanshell/review.bsh']['canExposeBytes']);
        $t->same(false, $compactItems['Scripts/beanshell/review.bsh']['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $compactReview['Scripts/beanshell/review.bsh']['byteExposurePolicy']);
        $t->same('ruby', $compactItems['Scripts/ruby/review.rb']['scriptKind']);
        $t->same('text/x-ruby', $compactItems['Scripts/ruby/review.rb']['mediaType']);
        $t->same('ruby/review.rb', $compactItems['Scripts/ruby/review.rb']['scriptPath']);
        $t->same('ruby', $compactItems['Scripts/ruby/review.rb']['scriptLibrary']);
        $t->same(false, $compactItems['Scripts/ruby/review.rb']['canExposeBytes']);
        $t->same(false, $compactItems['Scripts/ruby/review.rb']['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $compactReview['Scripts/ruby/review.rb']['byteExposurePolicy']);
        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
    },
    'reports ODT script package invalid declared sizes as metadata-only diagnostics' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $manifestXml,
        $basicModuleXml
    ): void {
        $invalidSizeManifest = str_replace(
            'manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="' . strlen($basicModuleXml) . '"',
            'manifest:full-path="Basic/Standard/Review.xml" manifest:media-type="text/xml" manifest:size="macro-bytes"',
            $manifestXml
        );
        $package = $buildPackage($invalidSizeManifest);
        $result = (new OdfReader())->readPackage($package);
        $scripts = $result['packageScripts'];
        $items = $indexBy($scripts['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $runtimeParts = $indexBy($result['scriptMetadata']['parts'], 'part');

        $t->same(1, $scripts['invalidDeclaredSizeCount']);
        $t->same([
            'odf-script-encrypted-package-part',
            'odf-script-invalid-declared-size',
            'odf-script-missing-package-part',
            'odf-script-undeclared-package-part',
        ], $scripts['issueCodes']);
        $t->same(4, $scripts['issueCount']);

        $script = $items['Basic/Standard/Review.xml'];
        $t->same(null, $script['declaredSize']);
        $t->same('macro-bytes', $script['declaredSizeRaw']);
        $t->same(false, $script['declaredSizeValid']);
        $t->same(true, $script['declaredSizeInvalid']);
        $t->same(false, $script['declaredSizeMismatch']);
        $t->same(true, $script['valid']);
        $t->same(strlen($basicModuleXml), $script['byteLength']);
        $t->same('script-package-bytes-blocked', $script['byteExposurePolicy']);
        $t->same(['odf-script-invalid-declared-size'], $script['issues']);

        $manifestScript = $manifestByPart['Basic/Standard/Review.xml'];
        $t->same(null, $manifestScript['declaredSize']);
        $t->same('macro-bytes', $manifestScript['declaredSizeRaw']);
        $t->same(false, $manifestScript['declaredSizeValid']);
        $t->same(true, $manifestScript['declaredSizeInvalid']);
        $t->same(false, $manifestScript['declaredSizeMismatch']);
        $t->same(['odf-manifest-invalid-declared-size'], $manifestScript['diagnostics']);
        $t->same(false, $manifestScript['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $manifestScript['byteExposurePolicy']);
        $t->same(null, $manifestScript['byteSha256']);

        $runtimeScript = $runtimeParts['Basic/Standard/Review.xml'];
        $t->same(null, $runtimeScript['declaredSize']);
        $t->same('macro-bytes', $runtimeScript['declaredSizeRaw']);
        $t->same(false, $runtimeScript['declaredSizeValid']);
        $t->same(true, $runtimeScript['declaredSizeInvalid']);
        $t->same(['odf-script-package-invalid-declared-size'], $runtimeScript['diagnostics']);
        $t->same(false, $runtimeScript['canExposeBytes']);
        $t->same(null, $runtimeScript['byteLength']);
        $t->same(strlen($basicModuleXml), $runtimeScript['storedByteLength']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactScripts = $compactSummary['packageScripts'];
        $compactItems = $indexBy($compactScripts['items'], 'part');
        $compactReview = $indexBy($compactSummary['manifestReview']['items'], 'path');

        $t->same(1, $compactScripts['invalidDeclaredSizeCount']);
        $t->same($scripts['issueCodes'], $compactScripts['issueCodes']);
        $t->same(4, $compactScripts['issueCount']);
        $t->same('macro-bytes', $compactItems['Basic/Standard/Review.xml']['declaredSizeRaw']);
        $t->same(false, $compactItems['Basic/Standard/Review.xml']['declaredSizeValid']);
        $t->same(true, $compactItems['Basic/Standard/Review.xml']['declaredSizeInvalid']);
        $t->same(['odf-script-invalid-declared-size'], $compactItems['Basic/Standard/Review.xml']['issues']);
        $t->same('macro-bytes', $compactReview['Basic/Standard/Review.xml']['declaredSizeRaw']);
        $t->same(true, $compactReview['Basic/Standard/Review.xml']['declaredSizeInvalid']);
        $t->same(['odf-manifest-invalid-declared-size'], $compactReview['Basic/Standard/Review.xml']['diagnostics']);
        $t->same(false, $compactReview['Basic/Standard/Review.xml']['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $compactReview['Basic/Standard/Review.xml']['byteExposurePolicy']);
    },
    'accepts ODT BeanShell package script media-type aliases as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $manifestXml
    ): void {
        $beanShellAliasManifest = str_replace(
            'manifest:full-path="Scripts/beanshell/review.bsh" manifest:media-type="text/x-beanshell"',
            'manifest:full-path="Scripts/beanshell/review.bsh" manifest:media-type="application/x-bsh"',
            $manifestXml
        );
        $package = $buildPackage($beanShellAliasManifest);
        $result = (new OdfReader())->readPackage($package);
        $scripts = $result['packageScripts'];
        $items = $indexBy($scripts['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');

        $t->same(0, $scripts['invalidMediaTypeCount']);
        $t->same(['basic-library-index', 'basic-module', 'beanshell', 'javascript', 'python', 'ruby', 'script-directory'], $scripts['scriptKinds']);
        $t->same('application/x-bsh', $items['Scripts/beanshell/review.bsh']['mediaType']);
        $t->same('application/x-bsh', $items['Scripts/beanshell/review.bsh']['mediaTypeBase']);
        $t->same('beanshell', $items['Scripts/beanshell/review.bsh']['scriptKind']);
        $t->same(true, $items['Scripts/beanshell/review.bsh']['valid']);
        $t->same([], $items['Scripts/beanshell/review.bsh']['issues']);
        $t->same(false, $items['Scripts/beanshell/review.bsh']['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/beanshell/review.bsh']['byteExposurePolicy']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactItems = $indexBy($compactSummary['packageScripts']['items'], 'part');
        $t->same('application/x-bsh', $compactItems['Scripts/beanshell/review.bsh']['mediaType']);
        $t->same('beanshell', $compactItems['Scripts/beanshell/review.bsh']['scriptKind']);
        $t->same(true, $compactItems['Scripts/beanshell/review.bsh']['valid']);
        $t->same(false, $compactItems['Scripts/beanshell/review.bsh']['canExposeBytes']);
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
        $t->same(['basic-library-index', 'basic-module', 'beanshell', 'javascript', 'python', 'ruby', 'script-directory'], $scripts['scriptKinds']);
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
    'accepts ODT JavaScript package script media-type aliases as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $manifestXml
    ): void {
        $extensionlessJavaScript = 'return "extensionless script review";';
        $javaScriptAliasManifest = str_replace(
            [
                'manifest:full-path="Scripts/review.js" manifest:media-type="application/javascript"',
                '  <manifest:file-entry manifest:full-path="Scripts/missing.js" manifest:media-type="application/javascript"/>',
            ],
            [
                'manifest:full-path="Scripts/review.js" manifest:media-type="application/x-javascript"',
                '  <manifest:file-entry manifest:full-path="Scripts/javascript/audit" manifest:media-type="application/ecmascript" manifest:size="' . strlen($extensionlessJavaScript) . '"/>' . "\n"
                    . '  <manifest:file-entry manifest:full-path="Scripts/missing.js" manifest:media-type="application/javascript"/>',
            ],
            $manifestXml
        );
        $package = $buildPackage($javaScriptAliasManifest, [
            ['name' => 'Scripts/javascript/audit', 'data' => $extensionlessJavaScript, 'compressionMethod' => 0],
        ]);
        $result = (new OdfReader())->readPackage($package);
        $scripts = $result['packageScripts'];
        $items = $indexBy($scripts['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $scriptMetadataParts = $indexBy($result['scriptMetadata']['parts'], 'part');

        $t->same(0, $scripts['invalidMediaTypeCount']);
        $javaScript = $items['Scripts/review.js'];
        $t->same('application/x-javascript', $javaScript['mediaType']);
        $t->same('application/x-javascript', $javaScript['mediaTypeBase']);
        $t->same(true, $javaScript['mediaTypeValid']);
        $t->same('javascript', $javaScript['scriptKind']);
        $t->same(true, $javaScript['valid']);
        $t->same([], $javaScript['issues']);
        $t->same(false, $javaScript['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/review.js']['byteExposurePolicy']);

        $extensionless = $items['Scripts/javascript/audit'];
        $t->same('application/ecmascript', $extensionless['mediaType']);
        $t->same('application/ecmascript', $extensionless['mediaTypeBase']);
        $t->same(true, $extensionless['mediaTypeValid']);
        $t->same('javascript', $extensionless['scriptKind']);
        $t->same('javascript/audit', $extensionless['scriptPath']);
        $t->same('javascript', $extensionless['scriptLibrary']);
        $t->same('audit', $extensionless['scriptModule']);
        $t->same(null, $extensionless['extension']);
        $t->same(true, $extensionless['valid']);
        $t->same([], $extensionless['issues']);
        $t->same(strlen($extensionlessJavaScript), $extensionless['byteLength']);
        $t->same(false, $extensionless['canExposeBytes']);
        $t->same(false, $extensionless['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/javascript/audit']['byteExposurePolicy']);

        $runtimeJavaScript = $scriptMetadataParts['Scripts/javascript/audit'];
        $t->same('javascript-script', $runtimeJavaScript['kind']);
        $t->same('JavaScript', $runtimeJavaScript['language']);
        $t->same('javascript', $runtimeJavaScript['libraryName']);
        $t->same('audit', $runtimeJavaScript['moduleName']);
        $t->same(false, $runtimeJavaScript['canExposeBytes']);
        $t->same(null, $runtimeJavaScript['byteLength']);
        $t->same(strlen($extensionlessJavaScript), $runtimeJavaScript['storedByteLength']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactItems = $indexBy($compactSummary['packageScripts']['items'], 'part');
        $compactJavaScript = $compactItems['Scripts/review.js'];
        $t->same('application/x-javascript', $compactJavaScript['mediaType']);
        $t->same('javascript', $compactJavaScript['scriptKind']);
        $t->same(true, $compactJavaScript['valid']);
        $t->same(false, $compactJavaScript['canExposeBytes']);

        $compactExtensionless = $compactItems['Scripts/javascript/audit'];
        $t->same('application/ecmascript', $compactExtensionless['mediaType']);
        $t->same('javascript', $compactExtensionless['scriptKind']);
        $t->same('javascript/audit', $compactExtensionless['scriptPath']);
        $t->same('javascript', $compactExtensionless['scriptLibrary']);
        $t->same('audit', $compactExtensionless['scriptModule']);
        $t->same(null, $compactExtensionless['extension']);
        $t->same(true, $compactExtensionless['valid']);
        $t->same(false, $compactExtensionless['canExposeBytes']);
        $t->same(false, $compactExtensionless['canExposeAsDocumentMedia']);
    },
    'classifies extensionless ODT package scripts from script media-type aliases' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $manifestXml
    ): void {
        $pythonScript = 'print("extensionless review")';
        $beanShellScript = 'print("extensionless beanshell");';
        $javaClass = 'CAFEBABE';
        $aliasManifest = str_replace(
            '</manifest:manifest>',
            '  <manifest:file-entry manifest:full-path="Scripts/python/audit" manifest:media-type="application/x-python" manifest:size="' . strlen($pythonScript) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Scripts/beanshell/review" manifest:media-type="application/x-bsh" manifest:size="' . strlen($beanShellScript) . '"/>' . "\n"
            . '  <manifest:file-entry manifest:full-path="Scripts/java/review" manifest:media-type="application/java-vm" manifest:size="' . strlen($javaClass) . '"/>' . "\n"
            . '</manifest:manifest>',
            $manifestXml
        );
        $package = $buildPackage($aliasManifest, [
            ['name' => 'Scripts/python/audit', 'data' => $pythonScript, 'compressionMethod' => 0],
            ['name' => 'Scripts/beanshell/review', 'data' => $beanShellScript, 'compressionMethod' => 0],
            ['name' => 'Scripts/java/review', 'data' => $javaClass, 'compressionMethod' => 0],
        ]);
        $result = (new OdfReader())->readPackage($package);
        $scripts = $result['packageScripts'];
        $items = $indexBy($scripts['items'], 'part');
        $runtimeParts = $indexBy($result['scriptMetadata']['parts'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');

        $t->same(0, $scripts['invalidMediaTypeCount']);
        $t->same(true, in_array('python', $scripts['scriptKinds'], true));
        $t->same(true, in_array('beanshell', $scripts['scriptKinds'], true));
        $t->same(true, in_array('java-class', $scripts['scriptKinds'], true));

        $python = $items['Scripts/python/audit'];
        $t->same('application/x-python', $python['mediaType']);
        $t->same('python', $python['scriptKind']);
        $t->same('python/audit', $python['scriptPath']);
        $t->same('python', $python['scriptLibrary']);
        $t->same('audit', $python['scriptModule']);
        $t->same(null, $python['extension']);
        $t->same(true, $python['mediaTypeValid']);
        $t->same(true, $python['valid']);
        $t->same([], $python['issues']);
        $t->same(false, $python['canExposeBytes']);
        $t->same(false, $python['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/python/audit']['byteExposurePolicy']);

        $pythonRuntime = $runtimeParts['Scripts/python/audit'];
        $t->same('python-script', $pythonRuntime['kind']);
        $t->same('Python', $pythonRuntime['language']);
        $t->same(false, $pythonRuntime['canExposeBytes']);
        $t->same(null, $pythonRuntime['byteLength']);
        $t->same(strlen($pythonScript), $pythonRuntime['storedByteLength']);

        $beanShell = $items['Scripts/beanshell/review'];
        $t->same('application/x-bsh', $beanShell['mediaType']);
        $t->same('beanshell', $beanShell['scriptKind']);
        $t->same('beanshell/review', $beanShell['scriptPath']);
        $t->same('beanshell', $beanShell['scriptLibrary']);
        $t->same('review', $beanShell['scriptModule']);
        $t->same(null, $beanShell['extension']);
        $t->same(true, $beanShell['valid']);
        $t->same([], $beanShell['issues']);

        $beanShellRuntime = $runtimeParts['Scripts/beanshell/review'];
        $t->same('beanshell-script', $beanShellRuntime['kind']);
        $t->same('BeanShell', $beanShellRuntime['language']);
        $t->same(false, $beanShellRuntime['canExposeBytes']);
        $t->same(null, $beanShellRuntime['byteLength']);

        $java = $items['Scripts/java/review'];
        $t->same('application/java-vm', $java['mediaType']);
        $t->same('java-class', $java['scriptKind']);
        $t->same('java/review', $java['scriptPath']);
        $t->same('java', $java['scriptLibrary']);
        $t->same('review', $java['scriptModule']);
        $t->same(null, $java['extension']);
        $t->same(true, $java['valid']);
        $t->same([], $java['issues']);

        $javaRuntime = $runtimeParts['Scripts/java/review'];
        $t->same('java-class', $javaRuntime['kind']);
        $t->same('Java', $javaRuntime['language']);
        $t->same(false, $javaRuntime['canExposeBytes']);
        $t->same(null, $javaRuntime['byteLength']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactItems = $indexBy($compactSummary['packageScripts']['items'], 'part');
        $t->same('python', $compactItems['Scripts/python/audit']['scriptKind']);
        $t->same('beanshell', $compactItems['Scripts/beanshell/review']['scriptKind']);
        $t->same('java-class', $compactItems['Scripts/java/review']['scriptKind']);
        $t->same(false, $compactItems['Scripts/python/audit']['canExposeBytes']);
        $t->same(false, $compactItems['Scripts/beanshell/review']['canExposeBytes']);
        $t->same(false, $compactItems['Scripts/java/review']['canExposeBytes']);
        $t->same(['Pictures/hero.png'], array_column($compactSummary['mediaParts'], 'path'));
    },
    'accepts ODT extensionless Python package script media-type aliases as metadata-only review data' => static function (TestRunner $t) use (
        $buildPackage,
        $indexBy,
        $manifestXml
    ): void {
        $extensionlessPythonScript = 'print("extensionless package review")';
        $pythonAliasManifest = str_replace(
            '  <manifest:file-entry manifest:full-path="Scripts/missing.js" manifest:media-type="application/javascript"/>',
            '  <manifest:file-entry manifest:full-path="Scripts/python/review" manifest:media-type="application/x-python" manifest:size="' . strlen($extensionlessPythonScript) . '"/>' . "\n"
                . '  <manifest:file-entry manifest:full-path="Scripts/missing.js" manifest:media-type="application/javascript"/>',
            $manifestXml
        );
        $package = $buildPackage($pythonAliasManifest, [
            ['name' => 'Scripts/python/review', 'data' => $extensionlessPythonScript, 'compressionMethod' => 0],
        ]);
        $result = (new OdfReader())->readPackage($package);
        $scripts = $result['packageScripts'];
        $items = $indexBy($scripts['items'], 'part');
        $manifestByPart = $indexBy($result['manifest'], 'part');
        $scriptMetadataParts = $indexBy($result['scriptMetadata']['parts'], 'part');

        $t->same(0, $scripts['invalidMediaTypeCount']);
        $python = $items['Scripts/python/review'];
        $t->same('application/x-python', $python['mediaType']);
        $t->same('application/x-python', $python['mediaTypeBase']);
        $t->same(true, $python['mediaTypeValid']);
        $t->same('python', $python['scriptKind']);
        $t->same('python/review', $python['scriptPath']);
        $t->same('python', $python['scriptLibrary']);
        $t->same('review', $python['scriptModule']);
        $t->same(null, $python['extension']);
        $t->same(true, $python['valid']);
        $t->same([], $python['issues']);
        $t->same(strlen($extensionlessPythonScript), $python['byteLength']);
        $t->same(sprintf('%08x', crc32($extensionlessPythonScript)), $python['crc32']);
        $t->same(false, $python['canExposeBytes']);
        $t->same(false, $python['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Scripts/python/review']['byteExposurePolicy']);

        $richScript = $scriptMetadataParts['Scripts/python/review'];
        $t->same('python-script', $richScript['kind']);
        $t->same('Python', $richScript['language']);
        $t->same('python', $richScript['libraryName']);
        $t->same('review', $richScript['moduleName']);
        $t->same(false, $richScript['canExposeBytes']);
        $t->same(null, $richScript['byteLength']);
        $t->same(strlen($extensionlessPythonScript), $richScript['storedByteLength']);
        $t->same(sprintf('%08x', crc32($extensionlessPythonScript)), $richScript['storedCrc32']);

        $compactSummary = OpenDocumentPackage::fromPackage($package)->summarize();
        $compactItems = $indexBy($compactSummary['packageScripts']['items'], 'part');
        $compactPython = $compactItems['Scripts/python/review'];
        $t->same('application/x-python', $compactPython['mediaType']);
        $t->same('application/x-python', $compactPython['mediaTypeBase']);
        $t->same('python', $compactPython['scriptKind']);
        $t->same('python/review', $compactPython['scriptPath']);
        $t->same('python', $compactPython['scriptLibrary']);
        $t->same('review', $compactPython['scriptModule']);
        $t->same(null, $compactPython['extension']);
        $t->same(true, $compactPython['valid']);
        $t->same(false, $compactPython['canExposeBytes']);
        $t->same(false, $compactPython['canExposeAsDocumentMedia']);
    },
];
