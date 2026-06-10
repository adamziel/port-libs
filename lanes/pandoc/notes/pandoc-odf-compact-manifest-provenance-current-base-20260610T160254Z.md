# Pandoc ODF Compact Manifest Provenance Slice

Slice: `pandoc-odf-compact-manifest-provenance-current-base-20260610T160254Z`
Date: 2026-06-10 UTC

## Behavior

This bounded ODF/ODT package-ingestion slice extends compact
`OpenDocumentPackage` manifest handoff. Manifest entries now include ZIP package
part provenance:

- `exists`
- `isDirectory`
- `byteLength`
- `compressedByteLength`
- `crc32`
- `declaredSizeMismatch`

`summarize()` now also exposes `missingManifestParts` and
`declaredSizeMismatches` so WordPress/package review queues can see missing
declared media and false `manifest:size` claims without invoking external
validators or exposing extra bytes.

## Evidence

- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`
  - `1 test files, 108 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 60586 assertions, 0 failures`

No Pandoc, office suite, zip/unzip, browser renderer, external validator, online
service, live provider test, or live-service provider test was executed.

## Accounting

- `phpPass`: `2987 -> 2988`
- `phpFail`: `0`
- `benchmarkDenominator.mapped`: `3145 -> 3146`
- Added `mappedOdfCompactManifestProvenanceCases=1`
- Added `odfCompactManifestProvenanceAssertions=37`

## Non-Overlap

This does not repeat rich `OdfReader` manifest encryption, mimetype-entry
preflight, signature sidecar, undeclared-entry, directory-entry, or declared-size
import-report slices. It only adds compact `OpenDocumentPackage` manifest ZIP
part provenance and summary review fields for native PHP package handoff.
