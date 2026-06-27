# Pandoc ODF META-INF Sidecars

Slice: `plib-i5yk3`

## Behavior Added

- Classifies non-manifest, non-signature `META-INF/*` ODF/ODT package entries as metadata-only package sidecars.
- Exposes sidecar review metadata through compact `OpenDocumentPackage::summarize()` as `packageMetaInfSidecars`.
- Exposes rich reader sidecar review metadata through `OdfReader::readPackage()` results, document attributes, metadata, and import reports.
- Blocks sidecar byte exposure under `meta-inf-sidecar-package-bytes-blocked`, including image-like sidecars such as `META-INF/preview.png`.
- Preserves declared, undeclared, missing, and encrypted sidecar issue buckets without treating arbitrary `META-INF/*` payloads as document media.

## Verification

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/OdfReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/OdfReaderMetaInfSidecarTest.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderMetaInfSidecarTest.php`
  - Result: `1 test files, 80 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderMetaInfSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderDatabasePackageSidecarTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php`
  - Result: `4 test files, 2102 assertions, 0 failures`.
- Post-rebase `php tools/run-tests.php lanes/pandoc/tests/OdfReaderMetaInfSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php lanes/pandoc/tests/OdfReaderDatabasePackageSidecarTest.php lanes/pandoc/tests/OdfReaderPackageIdentityTest.php lanes/pandoc/tests/OdfDialogPackageSidecarTest.php`
  - Result: `5 test files, 2216 assertions, 0 failures`.

## Dependency Closure

No new external support component is needed. This slice reuses the native PHP ZIP and ODF package review paths. It does not invoke Pandoc, LibreOffice, Word, unzip, network services, or external validators.

## Non-Overlap

This patch is limited to ODF/ODT package metadata classification for arbitrary `META-INF/*` sidecars. It leaves `META-INF/manifest.xml`, signature sidecars, database sidecars, layout-cache sidecars, document XML parsing, DOCX/EPUB/OPC readers, and writer behavior unchanged.
