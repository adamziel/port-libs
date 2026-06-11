# ODF Manifest Missing Media-Type Diagnostics

Bead: `plib-rpbgw`
Date: 2026-06-11 UTC
Base target: `origin/main de90794e7`

## Scope

This bounded ODF/ODT package-ingestion slice keeps malformed producer manifests
inspectable while making empty `manifest:media-type` file entries explicit.

`OdfReader` now:

- flags non-directory `manifest:file-entry` records with empty
  `manifest:media-type` as `odf-manifest-file-entry-missing-media-type`;
- keeps those file entries out of byte-exposable manifest/package provenance;
- separates empty-media-type directory declarations from empty-media-type file
  entries in `mediaTypeSummary`;
- carries manifest diagnostics into package provenance inventory rows.

This does not reject the package and does not change valid empty media-type
directory handling.

## Verification

- `php -l lanes/pandoc/src/OdfReader.php`
- `php -l lanes/pandoc/tests/OdfReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OdfReaderTest.php`
  - Result: `1 test files, 4054 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `44 test files, 66308 assertions, 0 failures`

No Pandoc, office suite, zip/unzip, browser renderer, external validator,
online service, live provider test, or live-service provider test was invoked.

## Accounting

- `phpPass`: `3125 -> 3126`
- `phpFail`: `0`
- Mapped denominator: `3214 -> 3215`
- `mappedOdfManifestMissingMediaTypeCases`: `1`
- `odfManifestMissingMediaTypeAssertions`: `22`
