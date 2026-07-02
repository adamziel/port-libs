<?php

declare(strict_types=1);

use PortLibs\Pandoc\OdfReader;
use PortLibs\Pandoc\OpenDocumentPackage;
use PortLibs\Pandoc\WordPressBlockWriter;
use PortLibs\Pandoc\ZipPackage;

$manifestXml = <<<'XML'
<manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.3">
  <manifest:file-entry manifest:full-path="/" manifest:version="1.3" manifest:media-type="application/vnd.oasis.opendocument.text"/>
  <manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="styles.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="meta.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Pictures/hero.png" manifest:media-type="image/png"/>
  <manifest:file-entry manifest:full-path="Dialogs/" manifest:media-type=""/>
  <manifest:file-entry manifest:full-path="Dialogs/Standard/dialog-lb.xml" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Dialogs/Standard/dialog.xlb" manifest:media-type="text/xml"/>
  <manifest:file-entry manifest:full-path="Dialogs/Standard/ReviewDialog.xdl" manifest:media-type="text/xml" manifest:size="77"/>
</manifest:manifest>
XML;

$contentXml = <<<'XML'
<office:document-content
  xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0"
  xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0"
  office:version="1.3">
  <office:body>
    <office:text>
      <text:p>Dialog package sidecar review.</text:p>
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
    <dc:title>Dialog Package Review</dc:title>
  </office:meta>
</office:document-meta>
XML;

$dialogIndexXml = '<library:library xmlns:library="http://openoffice.org/2000/library" library:name="Standard"/>';
$dialogLibraryXml = '<library:libraries xmlns:library="http://openoffice.org/2000/library"><library:library library:name="Standard"/></library:libraries>';
$dialogXml = '<dlg:window xmlns:dlg="http://openoffice.org/2000/dialog" dlg:id="ReviewDialog"><dlg:button dlg:id="Approve"/></dlg:window>';
$orphanDialogXml = '<dlg:window xmlns:dlg="http://openoffice.org/2000/dialog" dlg:id="OrphanDialog"/>';

$parts = [
    ['name' => 'mimetype', 'data' => OdfReader::MIMETYPE, 'compressionMethod' => 0],
    ['name' => 'META-INF/manifest.xml', 'data' => $manifestXml, 'compressionMethod' => 0],
    ['name' => 'content.xml', 'data' => $contentXml, 'compressionMethod' => 0],
    ['name' => 'styles.xml', 'data' => $stylesXml, 'compressionMethod' => 0],
    ['name' => 'meta.xml', 'data' => $metaXml, 'compressionMethod' => 0],
    ['name' => 'Pictures/hero.png', 'data' => 'PNGDATA', 'compressionMethod' => 0],
    ['name' => 'Dialogs/', 'data' => '', 'compressionMethod' => 0],
    ['name' => 'Dialogs/Standard/dialog-lb.xml', 'data' => $dialogIndexXml, 'compressionMethod' => 0],
    ['name' => 'Dialogs/Standard/dialog.xlb', 'data' => $dialogLibraryXml, 'compressionMethod' => 0],
    ['name' => 'Dialogs/Standard/ReviewDialog.xdl', 'data' => $dialogXml, 'compressionMethod' => 0],
    ['name' => 'Dialogs/Standard/OrphanDialog.xdl', 'data' => $orphanDialogXml, 'compressionMethod' => 0],
];

return [
    'blocks compact ODT dialog package sidecars as script provenance' => static function (TestRunner $t) use ($parts, $dialogIndexXml, $dialogLibraryXml, $dialogXml, $orphanDialogXml): void {
        $summary = OpenDocumentPackage::fromPackage(ZipPackage::fromParts($parts, 'compact odt dialog sidecars'))->summarize();
        $scripts = $summary['packageScripts'];
        $scriptByPath = [];
        foreach ($scripts['items'] as $item) {
            $scriptByPath[$item['packagePath']] = $item;
        }
        $reviewByPath = [];
        foreach ($summary['manifestReview']['items'] as $item) {
            $reviewByPath[$item['path']] = $item;
        }
        $inventory = $summary['packageInventory'];

        $t->same(5, $scripts['count']);
        $t->same(4, $scripts['fileCount']);
        $t->same(1, $scripts['directoryCount']);
        $t->same(5, $scripts['storedPartCount']);
        $t->same(4, $scripts['readableCount']);
        $t->same(4, $scripts['declaredCount']);
        $t->same(1, $scripts['undeclaredCount']);
        $t->same(0, $scripts['missingCount']);
        $t->same(0, $scripts['encryptedCount']);
        $t->same(1, $scripts['issueCount']);
        $t->same(['odf-script-undeclared-package-part'], $scripts['issueCodes']);
        $t->same(['dialogs'], $scripts['scriptContainers']);
        $t->same(['basic-dialog', 'basic-library-index', 'script-directory'], $scripts['scriptKinds']);
        $t->same('script-package-bytes-blocked', $scripts['byteExposurePolicy']);
        $t->same('package-script-metadata-only', $scripts['reviewPolicy']);

        $directory = $scriptByPath['Dialogs/'];
        $index = $scriptByPath['Dialogs/Standard/dialog-lb.xml'];
        $library = $scriptByPath['Dialogs/Standard/dialog.xlb'];
        $dialog = $scriptByPath['Dialogs/Standard/ReviewDialog.xdl'];
        $orphan = $scriptByPath['Dialogs/Standard/OrphanDialog.xdl'];

        $t->same(true, $directory['isDirectory']);
        $t->same('script-directory', $directory['scriptKind']);
        $t->same(true, $directory['valid']);
        $t->same(null, $directory['byteLength']);
        $t->same(0, $directory['storedByteLength']);
        $t->same('directory-entry-no-bytes', $directory['byteExposurePolicy']);
        $t->same([], $directory['issues']);

        $t->same('dialogs', $index['scriptContainer']);
        $t->same('basic-library-index', $index['scriptKind']);
        $t->same('Standard', $index['scriptLibrary']);
        $t->same('dialog-lb', $index['scriptModule']);
        $t->same(strlen($dialogIndexXml), $index['byteLength']);
        $t->same(false, $index['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $index['byteExposurePolicy']);
        $t->same([], $index['issues']);

        $t->same('dialogs', $library['scriptContainer']);
        $t->same('basic-library-index', $library['scriptKind']);
        $t->same('Standard', $library['scriptLibrary']);
        $t->same('dialog', $library['scriptModule']);
        $t->same('xlb', $library['extension']);
        $t->same('text/xml', $library['mediaType']);
        $t->same(strlen($dialogLibraryXml), $library['byteLength']);
        $t->same(sprintf('%08x', crc32($dialogLibraryXml)), $library['crc32']);
        $t->same(false, $library['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $library['byteExposurePolicy']);
        $t->same([], $library['issues']);

        $t->same('dialogs', $dialog['scriptContainer']);
        $t->same('basic-dialog', $dialog['scriptKind']);
        $t->same('Standard', $dialog['scriptLibrary']);
        $t->same('ReviewDialog', $dialog['scriptModule']);
        $t->same('xdl', $dialog['extension']);
        $t->same('text/xml', $dialog['mediaType']);
        $t->same(strlen($dialogXml), $dialog['byteLength']);
        $t->same(sprintf('%08x', crc32($dialogXml)), $dialog['crc32']);
        $t->same(false, $dialog['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $dialog['byteExposurePolicy']);
        $t->same([], $dialog['issues']);

        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('basic-dialog', $orphan['scriptKind']);
        $t->same(strlen($orphanDialogXml), $orphan['byteLength']);
        $t->same(['odf-script-undeclared-package-part'], $orphan['issues']);

        $t->same(5, $inventory['scriptPackagePartCount']);
        $t->same(1, $inventory['undeclaredRoleCounts']['script-package']);
        $t->same(['script-package', 'zip-directory', 'manifest-declared'], $inventory['parts']['Dialogs/']['roles']);
        $t->same(['script-package', 'manifest-declared'], $inventory['parts']['Dialogs/Standard/dialog-lb.xml']['roles']);
        $t->same(['script-package', 'manifest-declared'], $inventory['parts']['Dialogs/Standard/dialog.xlb']['roles']);
        $t->same(['script-package', 'manifest-declared'], $inventory['parts']['Dialogs/Standard/ReviewDialog.xdl']['roles']);
        $t->same(['script-package', 'undeclared-package-entry'], $inventory['parts']['Dialogs/Standard/OrphanDialog.xdl']['roles']);
        $t->same(4, $summary['manifestReview']['scriptPackagePartCount']);
        $t->same('script-package-bytes-blocked', $reviewByPath['Dialogs/Standard/dialog.xlb']['byteExposurePolicy']);
        $t->same(false, $reviewByPath['Dialogs/Standard/dialog.xlb']['canExposeBytes']);
        $t->same(null, $reviewByPath['Dialogs/Standard/dialog.xlb']['byteSha256']);
        $t->same('script-package-bytes-blocked', $reviewByPath['Dialogs/Standard/ReviewDialog.xdl']['byteExposurePolicy']);
        $t->same(false, $reviewByPath['Dialogs/Standard/ReviewDialog.xdl']['canExposeBytes']);
        $t->same(null, $reviewByPath['Dialogs/Standard/ReviewDialog.xdl']['byteSha256']);
        $t->same(['Pictures/hero.png'], array_column($summary['mediaParts'], 'path'));
        $t->same('Dialogs/Standard/OrphanDialog.xdl', $summary['undeclaredPackageEntries'][0]['path']);
    },
    'preserves ODT dialog package invalid declared-size diagnostics as metadata only' => static function (TestRunner $t) use ($parts, $manifestXml, $dialogXml, $orphanDialogXml): void {
        $invalidSizeManifest = str_replace('manifest:size="77"', 'manifest:size="dialog-bytes"', $manifestXml);
        $invalidSizeParts = array_map(
            static function (array $part) use ($invalidSizeManifest): array {
                if (($part['name'] ?? null) === 'META-INF/manifest.xml') {
                    $part['data'] = $invalidSizeManifest;
                }

                return $part;
            },
            $parts
        );

        $compactSummary = OpenDocumentPackage::fromPackage(ZipPackage::fromParts($invalidSizeParts, 'compact odt dialog invalid size'))->summarize();
        $compactScripts = $compactSummary['packageScripts'];
        $compactScriptsByPath = [];
        foreach ($compactScripts['items'] as $item) {
            $compactScriptsByPath[$item['packagePath']] = $item;
        }
        $compactReviewByPath = [];
        foreach ($compactSummary['manifestReview']['items'] as $item) {
            $compactReviewByPath[$item['path']] = $item;
        }

        $t->same(1, $compactScripts['invalidDeclaredSizeCount']);
        $t->same(2, $compactScripts['issueCount']);
        $t->same([
            'odf-script-invalid-declared-size',
            'odf-script-undeclared-package-part',
        ], $compactScripts['issueCodes']);

        $compactDialog = $compactScriptsByPath['Dialogs/Standard/ReviewDialog.xdl'];
        $t->same(null, $compactDialog['declaredSize']);
        $t->same('dialog-bytes', $compactDialog['declaredSizeRaw']);
        $t->same(false, $compactDialog['declaredSizeValid']);
        $t->same(true, $compactDialog['declaredSizeInvalid']);
        $t->same(false, $compactDialog['declaredSizeMismatch']);
        $t->same(strlen($dialogXml), $compactDialog['byteLength']);
        $t->same(false, $compactDialog['canExposeBytes']);
        $t->same(false, $compactDialog['canExposeAsDocumentMedia']);
        $t->same('script-package-bytes-blocked', $compactDialog['byteExposurePolicy']);
        $t->same(['odf-script-invalid-declared-size'], $compactDialog['issues']);

        $compactReviewDialog = $compactReviewByPath['Dialogs/Standard/ReviewDialog.xdl'];
        $t->same(null, $compactReviewDialog['declaredSize']);
        $t->same('dialog-bytes', $compactReviewDialog['declaredSizeRaw']);
        $t->same(false, $compactReviewDialog['declaredSizeValid']);
        $t->same(true, $compactReviewDialog['declaredSizeInvalid']);
        $t->same(['odf-manifest-invalid-declared-size'], $compactReviewDialog['diagnostics']);
        $t->same(false, $compactReviewDialog['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $compactReviewDialog['byteExposurePolicy']);
        $t->same(null, $compactReviewDialog['byteSha256']);

        $result = (new OdfReader())->readPackage(ZipPackage::fromParts($invalidSizeParts, 'rich odt dialog invalid size'));
        $scripts = $result['scriptMetadata'];
        $runtimePartsByPart = [];
        foreach ($scripts['parts'] as $part) {
            $runtimePartsByPart[$part['part']] = $part;
        }
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }

        $t->same($scripts, $result['document']->attr('scriptMetadata'));
        $t->same($scripts, $result['importReport']['scriptMetadata']);
        $t->same(1, $scripts['invalidDeclaredSizeCount']);
        $t->same(0, $scripts['declaredSizeMismatchCount']);
        $t->same(2, $scripts['diagnosticCount']);
        $t->same([
            'odf-script-package-invalid-declared-size' => 1,
            'odf-script-package-undeclared-part' => 1,
        ], $scripts['diagnosticCodeCounts']);
        $t->same([
            'odf-script-package-invalid-declared-size',
            'odf-script-package-undeclared-part',
        ], $scripts['diagnosticCodes']);

        $runtimeDialog = $runtimePartsByPart['Dialogs/Standard/ReviewDialog.xdl'];
        $t->same(null, $runtimeDialog['declaredSize']);
        $t->same('dialog-bytes', $runtimeDialog['declaredSizeRaw']);
        $t->same(false, $runtimeDialog['declaredSizeValid']);
        $t->same(true, $runtimeDialog['declaredSizeInvalid']);
        $t->same(false, $runtimeDialog['declaredSizeMismatch']);
        $t->same(['odf-script-package-invalid-declared-size'], $runtimeDialog['diagnostics']);
        $t->same(false, $runtimeDialog['canExposeBytes']);
        $t->same(null, $runtimeDialog['byteLength']);
        $t->same(strlen($dialogXml), $runtimeDialog['storedByteLength']);

        $runtimeOrphan = $runtimePartsByPart['Dialogs/Standard/OrphanDialog.xdl'];
        $t->same(strlen($orphanDialogXml), $runtimeOrphan['storedByteLength']);
        $t->same(['odf-script-package-undeclared-part'], $runtimeOrphan['diagnostics']);

        $manifestDialog = $manifestByPart['Dialogs/Standard/ReviewDialog.xdl'];
        $t->same('dialog-bytes', $manifestDialog['declaredSizeRaw']);
        $t->same(true, $manifestDialog['declaredSizeInvalid']);
        $t->same(['odf-manifest-invalid-declared-size'], $manifestDialog['diagnostics']);
        $t->same(false, $manifestDialog['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $manifestDialog['byteExposurePolicy']);
        $t->same(null, $manifestDialog['byteSha256']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Dialog package sidecar review.', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'ReviewDialog'), 'Invalid-size dialog sidecar bytes must not be rendered into WordPress output');
        $t->true(!str_contains($blocksHtml, 'OrphanDialog'), 'Undeclared dialog sidecar bytes must not be rendered into WordPress output');
    },
    'blocks rich ODT dialog package sidecars before WordPress handoff' => static function (TestRunner $t) use ($parts, $dialogIndexXml, $dialogLibraryXml, $dialogXml, $orphanDialogXml): void {
        $result = (new OdfReader())->readPackage(ZipPackage::fromParts($parts, 'rich odt dialog sidecars'));
        $scripts = $result['scriptMetadata'];
        $partsByPart = [];
        foreach ($scripts['parts'] as $part) {
            $partsByPart[$part['part']] = $part;
        }
        $manifestByPart = [];
        foreach ($result['manifest'] as $item) {
            if (is_string($item['part'] ?? null)) {
                $manifestByPart[$item['part']] = $item;
            }
        }
        $provenance = $result['importReport']['manifest']['packageProvenance'];

        $t->same($scripts, $result['document']->attr('scriptMetadata'));
        $t->same($scripts, $result['importReport']['scriptMetadata']);
        $t->same(4, $scripts['partCount']);
        $t->same(1, $scripts['directoryCount']);
        $t->same(3, $scripts['declaredPartCount']);
        $t->same(1, $scripts['undeclaredPartCount']);
        $t->same(0, $scripts['missingPartCount']);
        $t->same(0, $scripts['encryptedPartCount']);
        $t->same(0, $scripts['referenceCount']);
        $t->same(['Dialogs/' => 'Dialogs/'], array_column($scripts['directories'], 'part', 'part'));
        $t->same(2, $scripts['kindCounts']['basic-dialog']);
        $t->same(2, $scripts['kindCounts']['basic-library-index']);
        $t->same(4, $scripts['languageCounts']['Basic']);

        $index = $partsByPart['Dialogs/Standard/dialog-lb.xml'];
        $library = $partsByPart['Dialogs/Standard/dialog.xlb'];
        $dialog = $partsByPart['Dialogs/Standard/ReviewDialog.xdl'];
        $orphan = $partsByPart['Dialogs/Standard/OrphanDialog.xdl'];

        $t->same('basic-library-index', $index['kind']);
        $t->same('Basic', $index['language']);
        $t->same('Standard', $index['libraryName']);
        $t->same('dialog-lb', $index['moduleName']);
        $t->same(strlen($dialogIndexXml), $index['storedByteLength']);
        $t->same(null, $index['byteLength']);
        $t->same([], $index['diagnostics']);

        $t->same('basic-library-index', $library['kind']);
        $t->same('Basic', $library['language']);
        $t->same('Standard', $library['libraryName']);
        $t->same('dialog', $library['moduleName']);
        $t->same(strlen($dialogLibraryXml), $library['storedByteLength']);
        $t->same(null, $library['byteLength']);
        $t->same([], $library['diagnostics']);

        $t->same('basic-dialog', $dialog['kind']);
        $t->same('Basic', $dialog['language']);
        $t->same('Standard', $dialog['libraryName']);
        $t->same('ReviewDialog', $dialog['moduleName']);
        $t->same(true, $dialog['exists']);
        $t->same(false, $dialog['canExposeBytes']);
        $t->same(null, $dialog['byteLength']);
        $t->same(strlen($dialogXml), $dialog['storedByteLength']);
        $t->same([], $dialog['diagnostics']);

        $t->same(false, $orphan['declared']);
        $t->same(true, $orphan['undeclared']);
        $t->same('basic-dialog', $orphan['kind']);
        $t->same(strlen($orphanDialogXml), $orphan['storedByteLength']);
        $t->same(['odf-script-package-undeclared-part'], $orphan['diagnostics']);

        $t->same(false, $manifestByPart['Dialogs/Standard/ReviewDialog.xdl']['canExposeBytes']);
        $t->same('script-package-bytes-blocked', $manifestByPart['Dialogs/Standard/ReviewDialog.xdl']['byteExposurePolicy']);
        $t->same(null, $manifestByPart['Dialogs/Standard/ReviewDialog.xdl']['byteSha256']);
        $t->same(5, $provenance['scriptPackagePartCount']);
        $t->same([
            'script-package' => 1,
            'undeclared-package-entry' => 1,
        ], $provenance['undeclaredRoleCounts']);
        $t->same(['manifest-declared', 'script-package'], $provenance['parts']['Dialogs/Standard/ReviewDialog.xdl']['roles']);
        $t->same(['manifest-declared', 'script-package'], $provenance['parts']['Dialogs/Standard/dialog.xlb']['roles']);
        $t->same(['script-package', 'undeclared-package-entry'], $provenance['parts']['Dialogs/Standard/OrphanDialog.xdl']['roles']);
        $t->same(1, count($result['media']));
        $t->same('Pictures/hero.png', $result['media'][0]['part']);
        $t->same('Dialogs/Standard/OrphanDialog.xdl', $result['importReport']['manifest']['undeclaredEntries'][0]['part']);

        $blocksHtml = (new WordPressBlockWriter())->write($result['document']);
        $t->contains('Dialog package sidecar review.', $blocksHtml);
        $t->true(!str_contains($blocksHtml, 'ReviewDialog'), 'Dialog sidecar bytes must not be rendered into WordPress output');
        $t->true(!str_contains($blocksHtml, 'OrphanDialog'), 'Undeclared dialog sidecar bytes must not be rendered into WordPress output');
    },
];
