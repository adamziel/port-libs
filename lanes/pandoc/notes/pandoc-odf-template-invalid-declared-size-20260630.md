# ODF Template Invalid Declared Size Provenance

Slice: `pandoc-odf-template-invalid-declared-size-20260630`
Issue: `plib-v65jq`

## Scope

- `OdfReader` now preserves invalid `manifest:size` provenance for
  `Templates/` package sidecars as metadata-only review data.
- `OpenDocumentPackage` carries the same compact template package sidecar
  fields: `declaredSizeRaw`, `declaredSizeValid`, `declaredSizeInvalid`,
  `invalidDeclaredSizeCount`, and
  `odf-template-package-invalid-declared-size`.
- Template sidecar bytes remain blocked under the existing
  `template-package-bytes-blocked` policy and are not exposed as media or
  WordPress handoff resources.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php`
  - 1 test file, 147 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderFlatOpenDocumentSidecarTest.php lanes/pandoc/tests/OdfReaderAttachmentPackageSidecarTest.php lanes/pandoc/tests/OdfReaderReportPackageSidecarTest.php lanes/pandoc/tests/OdfReaderTemplatePackageSidecarTest.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - 5 test files, 2,697 assertions, 0 failures

No Pandoc executable, Cabal/Haskell command, office suite, `zip`/`unzip`,
browser engine, TeX/PDF engine, external validator, online service, or live
provider test was executed.

## Accounting

- `UPSTREAM_TEST_MANIFEST.json`:
  - `mappedOdfTemplateInvalidDeclaredSizeCases`: `1`
  - `odfTemplateInvalidDeclaredSizeAssertions`: `12`
  - `benchmarkDenominator.mapped`: `2309 -> 2310`
