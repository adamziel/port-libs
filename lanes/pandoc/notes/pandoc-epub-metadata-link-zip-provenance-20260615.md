# EPUB Metadata Link ZIP Provenance

Bead: `plib-z39wx`
Base: `f4f676ae2c`
Date: 2026-06-15 UTC

This slice extends native PHP EPUB3 OPF metadata link handoff so package-local `<link>` targets carry ZIP provenance through `EpubReader`.

`packageReference()` now includes compressed byte length, compression method/name, compression support, and CRC32 alongside existing byte length/SHA-256 policy. `resolveMetadataLinks()` exposes those fields on metadata links and the metadata link target report carries them into import/document review packets. Remote or missing links preserve null provenance.

Verification:
- `php -l lanes/pandoc/src/EpubReader.php`
- `php -l lanes/pandoc/tests/EpubReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/EpubReaderTest.php`
  - 1 test file, 4709 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 46 test files, 88252 assertions, 0 failures
- `jq empty lanes/pandoc/lane-status.json`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

Accounting:
- `phpPass`: `3721 -> 3722`
- mapped upstream cases: `3740 -> 3741`
- `mappedEpubMetadataLinkZipProvenanceCases = 1`
- `epubMetadataLinkZipProvenanceAssertions = 22`

No Pandoc, EPUBCheck, zip/unzip, ZipArchive, browser renderers, external validators, online services, live provider tests, or live-service provider tests were run.
