# Pandoc ODT Package Scripts Review Metadata

Slice: Pandoc ODF/ODT OpenDocument package ingestion core blocker `plib-zzugy`.

Implemented bounded native PHP `OpenDocumentPackage` review metadata for compact ODT `Basic/` and `Scripts/` package payloads. The new `packageScripts` summary reports declared, missing, encrypted, and undeclared macro/script sidecars as metadata-only package review items, including script container, kind, library/module path provenance, media-type details, byte/CRC/compression metadata, issue codes, and script-package byte-exposure policy.

Script payloads remain excluded from document media handoff. This slice does not parse, evaluate, run, or expose macro source as document content.

No Pandoc binary, office suite, zip/unzip CLI, ZipArchive, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.

Metric movement:
- `phpPass`: `3264 -> 3265`
- `phpFail`: `0`
- `mappedOdtCompactPackageScriptReviewCases`: `1`
- `odtCompactPackageScriptReviewAssertions`: `59`

Verification after final rebase onto `624a3caf3e`:
- `php -l lanes/pandoc/src/OpenDocumentPackage.php`
- `php -l lanes/pandoc/tests/OpenDocumentPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenDocumentPackageTest.php`: 1 test file, 1054 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 73076 assertions, 0 failures
