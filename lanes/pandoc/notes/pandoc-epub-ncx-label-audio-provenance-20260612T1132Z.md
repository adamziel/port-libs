# Pandoc EPUB NCX label audio provenance

Implemented a bounded compact EPUB package ingestion slice for legacy NCX `navLabel` audio review metadata. `EpubPackage` now preserves label audio records on NCX fallback navigation entries, including local query/fragment targets, manifest media type, ZIP byte length, compression/CRC provenance, SHA-256 for exposed local bytes, missing/remote diagnostics, and bounded SMIL clip timing.

The WordPress package handoff summary now exposes `ncxAudioLabels`, `ncxAudioLabelReport`, and `ncxAudioLabelDiagnostics` so review queues can inspect spoken-label sidecars without treating them as document media.

Verification:

- `php -l lanes/pandoc/src/EpubPackage.php`
- `php -l lanes/pandoc/tests/EpubPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubPackageTest.php`: 1 file, 2063 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 44 test files, 70855 assertions, 0 failures after rebase.

Lane accounting:

- Added one mapped compact EPUB package case: `mappedEpubNcxLabelAudioPackageCases = 1`.
- Added 39 focused assertions: `epubNcxLabelAudioPackageAssertions = 39`.
- Moved `phpPass` from 3200 to 3201 after rebase; `phpFail` remains 0.

No Pandoc, EPUBCheck, zip/unzip, playback engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests were invoked.
