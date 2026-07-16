# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T071306Z`

Base accepted HEAD: `4da54872051c78af7bf84f46f2236797aa126481`

## Implementation

- Added aggregate ZIP package size preflight to `ZipPackage`.
- `ZipPackage::sizePreflight()` now reports entry/file/directory counts,
  stored/deflated/unsupported method counts, compressed and uncompressed byte
  totals, expansion ratios, per-entry size summaries, and the largest entry by
  uncompressed size.
- `ZipPackage::assertSizePreflight()` rejects packages that exceed a bounded
  total uncompressed byte limit or aggregate expansion-ratio limit before any
  Office/EPUB/ODT media bytes are read.
- Updated the WordPress ZIP preflight smoke to expose aggregate package size
  metadata and rejection policy for import review queues.

## Source Truth

Pandoc DOCX, EPUB, and ODT conversion paths depend on ZIP package part
inventories before reader-specific XML/media parsing. The central directory is
already the authoritative bounded inventory in this native PHP support layer;
this slice uses the same central-directory sizes to surface aggregate package
expansion risk before WordPress import queues read embedded media.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, Word, LibreOffice,
`zip`, `unzip`, tar CLI, LZ4 CLI, TeX/PDF engine, external template engine,
browser renderer, online sanitizer, or online service was executed.

## Red-First Evidence

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 252 assertions, 0 failures`.
- Red check after adding expectations:
  `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 252 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\ZipPackage::sizePreflight()`.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 274 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Delta

- Focused ZIP assertions: `252 -> 274`, adding 22 assertions.
- Focused ZIP PASS cases: `39 -> 40`, adding 1 PASS case.
- Manifest mapped checks: `1194 -> 1195`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 153`.
- Lane PHP pass count: `735 -> 736`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives and stays within the accepted
ZIP/OPC package support row. Full upstream Pandoc runner parity remains
blocked on hydrating/building the Haskell Pandoc checkout at the manifest
commit, but aggregate ZIP package size preflight is not blocked by that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, package writing,
local entry order exposure, data descriptors, CRC/local-header checks,
central/local extra-field parsing, extended or NTFS timestamp handling,
ZIP64 extra-field rejection, Unix symlink rejection, raw/decoded unsafe path
rejection, directory payload rejection, local-entry overlap rejection,
duplicate local-header-offset rejection, unsupported compression method
read-time rejection, central-directory tail rejection, bounded per-entry reads,
gzip/tar/LZ4 archive streams, OPC relationships/content types, DOCX/ODT/EPUB
readers, syntax highlighting, table geometry, math/TeX, doctemplates, or
Markdown/HTML reader and writer behavior. It only adds aggregate package-level
size and expansion-risk reporting/rejection before package parts are exposed.

## Follow-Up

Keep compression-method implementations beyond stored/deflate, default
reader-specific size-limit policy, AES/encrypted archives, spanning archives,
full ZIP64 large-archive support, and verified central-directory signature
policy as separate bounded ZIP package slices.
