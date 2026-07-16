# EPUB Direct OCF Sidecars

Bead: `plib-7bwqd`
Base: `f962b89bb1`
Date: 2026-06-15 UTC

This slice extends native PHP EPUB3 directory package ingestion so `EpubPackageReader` reports OCF sidecar files from `META-INF`.

`ocfSidecars` now exposes sidecar kinds, XML root validation, byte length/SHA-256 provenance, metadata-only byte-exposure policy, and diagnostics for `META-INF/manifest.xml`, `rights.xml`, and `signatures.xml`. The directory `manifest.xml` report also preserves ODF-style `manifest:file-entry` references, including local, external, missing, and missing-full-path diagnostics without exposing sidecar bytes as document content.

Verification:
- `php -l lanes/pandoc/src/EpubPackageReader.php`
- `php -l lanes/pandoc/tests/EpubPackageReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageReaderTest.php`
  - 1 test file, 1537 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 88910 assertions, 0 failures

Accounting:
- `phpPass`: `3739 -> 3740`
- mapped upstream cases: `3755 -> 3756`
- `mappedEpubDirectOcfSidecarCases = 1`
- `epubDirectOcfSidecarAssertions = 57`

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests were run.
