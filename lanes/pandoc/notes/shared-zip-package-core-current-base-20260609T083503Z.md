# ZIP Selected Entry Handoff Preflight

Micro-slice: `pandoc-shared-zip-package-core-current-base-20260609T083503Z`

Base accepted HEAD: `436db66ac9717cbf75ff2ec29905ae0ddef22b3a`

## Behavior

`ZipPackage::entryHandoffPreflight()` now builds a bounded selected-entry
handoff report for DOCX/ODT/EPUB-style readers before they expose package
bytes. Each request records the requested name, normalized package name, role,
required/optional policy, expected kind (`file`, `directory`, or `any`), read
limit, CRC, SHA-256 hash for readable entries, and status.

The report keeps optional missing parts reviewable without failing the handoff,
while blocking missing required entries, directory/file-kind mismatches,
oversized entries, and unreadable entries such as unsupported compression
methods.

The WordPress ZIP package preflight smoke now prints `zipEntryHandoff*` fields
for a clean package with required `word/document.xml`, optional media bytes, an
optional media directory, and an optional missing comments sidecar.

## Verification

Red-first focused run after adding the new test:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - failed as expected on `Call to undefined method PortLibs\Pandoc\ZipPackage::entryHandoffPreflight()`

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - `1 test files, 2858 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - `zip package writer preflight self-test passed`
- `php -l lanes/pandoc/src/ZipPackage.php`
  - `No syntax errors detected`
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - `No syntax errors detected`
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - `No syntax errors detected`
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - both decoded successfully

Focused movement: +1 PHP PASS line, +65 focused assertions, +1 mapped ZIP
package core support case.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ZipPackage`
central/local parsing, bounded read limits, CRC/read-integrity handling,
SHA-256 hashing, in-memory ZIP fixtures, the existing WordPress ZIP preflight
example, and the lane TestRunner.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`/`unzip`,
`ZipArchive`, external archive tool, external template engine, TeX/PDF engine,
browser renderer, online service, live provider test, or live-service provider
test was executed.

## Non-Overlap

This does not repeat accepted ZIP work for EOCD/ZIP64 accounting, local/central
name or metadata spoofing, extra-field structure/id/value policy, Unicode
name/comment policy, platform sidecars, archive extra records, comments,
central-directory repair metadata, data descriptors, encryption, compression
policy, symlink/special-file rejection, timestamp policy, or package-prefix
handling. It only adds selected-entry reader handoff readiness after a package
has been instantiated.

## Next

Good follow-ups are DOCX/EPUB/ODT reader consumption of the selected-entry
handoff report, central-directory repair reporting in reader import packets, or
remaining ZIP64/data-descriptor diagnostics as separate native PHP slices.
