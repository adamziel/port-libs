# ZIP OPC CRC32 Fingerprint Handoff

Date: 2026-07-01
Slice: `plib-yhx0o`

`ZipPackage::packageManifestPreflight()` now exposes metadata-only CRC32
fingerprint buckets for shared ZIP/OPC package review. The manifest reports
per-CRC entry counts, file/directory counts, compressed and uncompressed byte
totals, local/source record byte totals, data-descriptor byte totals, directory
roots, compression methods, entry names, duplicate CRC32 hexes, and duplicate
entry totals without reading or exposing package payload bytes.

`OpcRelationshipGraph` carries the same central-directory CRC32 fingerprints
through both constructed package manifests and raw central-directory manifests.
The OPC summary adds role and handoff-kind counts plus entry/part-name maps, so
reviewers can compare repeated XML/media payload fingerprints across the
native package path and raw package triage path.

CRC32 is treated as ZIP central-directory source metadata for deterministic
grouping. It is not used as a cryptographic integrity signal, and this slice
does not change byte exposure policy.

Validation:

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/src/OpcRelationshipGraph.php`
- `php -l lanes/pandoc/tests/OpenPackagingConventionsTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 5,245 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 5,409 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 2 files, 10,654 assertions, 0 failures

Direct-format parity accounting remains unchanged. This slice is limited to
bounded native PHP ZIP/OPC package metadata and does not invoke Pandoc, office
suites, TeX/browser engines, `zip`/`unzip`, Jupyter, Node tooling, live
services, or external validators.
