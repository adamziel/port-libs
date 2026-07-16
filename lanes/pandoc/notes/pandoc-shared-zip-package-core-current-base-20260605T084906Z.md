# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T084906Z`

Base accepted HEAD: `980ef492bfe4c1ebea9d77eeee80c623451a7e76`

## Implementation

- `ZipPackage::fromString()` now rejects ZIP packages with bytes before the
  first local file header, bytes between local entry records, or bytes between
  the last local entry record and the central directory.
- Valid local data descriptors with and without the optional descriptor
  signature remain supported; the new check treats the descriptor as part of
  the local entry record before comparing offsets.
- The WordPress ZIP package preflight smoke now exposes the hidden local-entry
  slack policy before any DOCX/ODT/EPUB package reader treats embedded media
  bytes as importable attachments.

## Source Truth

Pandoc consumes DOCX, ODT, and EPUB as ZIP-backed package containers. The
central directory defines the package inventory, but package readers should not
ignore hidden bytes outside declared local entry records when handing office
parts or media to higher-level import code. This bounded PHP reader therefore
keeps the accepted central/local metadata checks and closes the remaining local
record coverage gap without invoking external archive tools.

No Pandoc, Cabal build, Haskell runner, `ZipArchive`, Word, LibreOffice,
`zip`, `unzip`, tar CLI, LZ4 CLI, TeX/PDF engine, external template engine,
browser renderer, online sanitizer, or online service was executed.

## Verification

- Baseline `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 283 assertions, 0 failures`.
- Focused `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 288 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " ok\n"; }'`
  - Result: both JSON files decoded successfully.
- `git diff --check -- lanes/pandoc`
  - Result: no whitespace errors.

Root harness not run - isolated micro-slice.

## Delta

- Focused ZIP PASS cases: `42 -> 43`, adding 1 PASS case.
- Focused ZIP assertions: `283 -> 288`, adding 5 assertions.
- Manifest mapped checks: `1246 -> 1247`.
- ZIP package support cases: `21 -> 22`.
- ZIP package core assertions: `131 -> 136`.
- Lane PHP pass count: `786 -> 787`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`ZipPackage` and `ZipPackageEntry` primitives, in-process CRC/DEFLATE handling,
and the accepted ZIP/OPC package support row. Full upstream Pandoc runner
parity remains blocked on hydrating/building the Haskell Pandoc checkout at the
manifest commit, but local-entry record coverage preflight is not blocked by
that runner.

## Non-Overlap

This does not repeat accepted central-directory parsing, package writing, local
entry order exposure, data descriptors, CRC/local-header integrity checks,
central/local extra-field parsing, extended or NTFS timestamp metadata, ZIP64
extra-field rejection, Unix symlink rejection, raw/decoded unsafe path
rejection, directory payload rejection, local-entry overlap rejection, duplicate
local-header-offset rejection, central-directory tail rejection, aggregate size
preflight, ZIP version-needed exposure, bounded per-entry reads, gzip/tar/LZ4
archive streams, OPC relationships/content types, DOCX/ODT/EPUB readers, syntax
highlighting, table geometry, math/TeX, doctemplates, or Markdown/HTML reader
and writer behavior. It only closes hidden local-record prefix/slack handling
for ZIP-backed package containers.

## Follow-Up

Keep AES/encrypted archive payload support, spanning archives, verified
central-directory signature parsing, full ZIP64 large-archive support, and
non-deflate compression methods as separate bounded ZIP package slices unless a
concrete DOCX/ODT/EPUB fixture requires them.
