# pandoc-shared-zip-package-core-current-base-20260608T090956Z

Accepted base: `e6968ed818a69e9dc12dd229c89caaf4bc025eb5`

## Behavior

Implemented bounded ZIP package preflight metadata for traditional PKWARE
encrypted entries without extracting or decrypting package bytes.

- `ZipPackage::encryptionPolicyPreflight()` now reports the local payload byte
  offset, compressed-data end offset, 12-byte traditional encryption header
  offset/availability, encrypted payload offset/size, and whether the declared
  compressed size includes the encryption header.
- Truncated traditional encryption headers remain blocked and visible through a
  `zip-traditional-encryption-header-truncated` diagnostic plus a
  `truncated-traditional-encryption-header` package issue.
- `ZipPackage::fromString()` still rejects encrypted entries before Office,
  EPUB, ODT, or WordPress media handoff.
- The WordPress ZIP package preflight example now surfaces traditional
  encryption policy/header/payload/truncation counters in `--self-test`.

## Evidence

- No `port-pandoc` rework note existed for this slice.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1354 assertions, 0 failures`.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 1391 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- PHP lint passed for changed PHP files.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1589 -> 1590`.
- `benchmarkDenominator.mapped`: `2009 -> 2010`.
- `zipPackageCoreSupportCases`: `22 -> 23`.
- `mappedZipPackageCoreSupportCases`: `22 -> 23`.
- `zipPackageCoreAssertions`: `161 -> 198`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `ZipPackage`
central/local header parser, encryption preflight, focused ZIP tests, and the
lane-local WordPress ZIP package preflight example. No Pandoc, `zip`/`unzip`,
`ZipArchive`, Word, LibreOffice, Cabal/Haskell runner, external archive tool,
online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted ZIP package slices for central-directory
signatures, Unicode name collisions, invalid DOS timestamps, trailing deflate
consumption, duplicate extra-field rejection, owner extra-field preflight,
WinZip AES extra fields, ZIP64 rejection, NTFS timestamps, split-archive disk
markers, unsupported compression methods, or general-purpose flag policy. It
only maps the byte layout of traditional encrypted payload headers while the
bounded reader remains fail-closed.

## Follow-Up

Useful follow-up ZIP/OPC work remains bounded to native package behavior, such
as ZIP data-descriptor consistency, OPC package cross-checks, or another
import-risk preflight that helps DOCX/ODT/EPUB package readers without invoking
external archive or office tools.
