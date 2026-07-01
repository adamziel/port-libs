# ZIP package manifest archive source records

Date: 2026-07-01
Slice: `plib-en267`

## Change

- `ZipPackage::packageManifestPreflight()` now reports archive-level source
  provenance for shared ZIP/OPC handoff:
  - whole archive bytes and SHA-256;
  - central directory offset, byte length, end offset, and SHA-256;
  - end-of-central-directory offset, byte length, end offset, package comment
    byte count, and SHA-256;
  - central-directory digital-signature presence, offset, byte length, and
    SHA-256 when present.
- The deterministic `manifestSha256` payload now includes the archive, central
  directory, EOCD, and central-directory-signature source hashes, so package
  manifest identity changes when review-relevant archive wrapper bytes change.

## Coverage

- Added an in-memory ZIP fixture with an EOCD package comment to prove EOCD byte
  accounting and hashes are exposed without external ZIP tools.
- Reused the existing central-directory digital-signature fixture to prove
  signature bytes and SHA-256 are carried through package manifest preflights.
- Verified constructed and raw strict import paths return the same package
  manifest.

## Validation

- `php -l lanes/pandoc/src/ZipPackage.php`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - 1 file, 4,942 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 1 file, 4,668 assertions, 0 failures
- Post-rebase focused gate:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php lanes/pandoc/tests/OpenPackagingConventionsTest.php`
  - 2 files, 9,610 assertions, 0 failures
- Post-rebase broad lane check:
  `php tools/run-tests.php lanes/pandoc/tests`
  - 304 files, 118,842 assertions, 9,634 failures
  - The visible failures are in existing non-slice Markdown/YAML/native reader
    backlog tests; the changed ZIP/OPC tests passed in this run. The lane status
    already tracks broad full-suite baseline failures separately.

Direct-format parity remains active in lane status. This slice only extends
bounded native PHP shared ZIP/OPC package provenance and does not invoke Pandoc,
office suites, TeX/browser engines, `zip`/`unzip`, Node tooling, Jupyter, live
services, or external validators.
