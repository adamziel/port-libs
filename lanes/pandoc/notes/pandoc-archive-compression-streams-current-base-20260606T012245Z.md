# Pandoc Archive Compression Streams Slice 2026-06-06 01:22 UTC

Slice: `pandoc-archive-compression-streams-current-base-20260606T012245Z`

Base accepted HEAD: `21883d1cce6e5a3b0da2d2fd54a53e5c7dee4fe1`

## Behavior

- Added bounded TAR PAX `atime` and `ctime` metadata handling to
  `TarArchive`.
- Local and global PAX timestamp headers now populate nullable
  `TarArchiveEntry::$accessedAt` and `TarArchiveEntry::$changedAt` fields while
  preserving raw PAX header strings for review provenance.
- Generated TAR fixtures can emit `atime` and `ctime` PAX records through
  `TarArchive::fromEntries()`.
- Overflowing PAX `atime` and `ctime` values are rejected before package
  exposure.
- The WordPress ZIP/package preflight example now surfaces PAX access/change
  timestamps alongside PAX owner and mtime metadata.

## Evidence

- Rework notes: no
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  files were present before editing.
- `php -l lanes/pandoc/src/TarArchive.php` passed with no syntax errors.
- `php -l lanes/pandoc/src/TarArchiveEntry.php` passed with no syntax errors.
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with no
  syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php` passed
  with no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  passed with `1 test files, 471 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed with `zip package writer preflight self-test passed`.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  passed for both lane JSON files.
- `git diff --check -- lanes/pandoc` passed.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1137 -> 1138`.
- `benchmarkDenominator.mapped`: `1589 -> 1590`.
- Current focused archive compression coverage recorded in the manifest:
  `48` cases / `471` assertions.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
`TarArchive`, `ArchiveCompressionStreamTest` fixtures, and WordPress
ZIP/package preflight example. It does not require Pandoc, Cabal, Haskell
runners, tar, gzip, zip/unzip, lz4, ZipArchive, Word, LibreOffice, external
archive tools, browser renderers, online sanitizers, online services, or live
provider tests.

## Non-Overlap

This does not repeat accepted gzip/zlib/raw-deflate/LZ4 framing work, ZIP data
descriptors, ZIP central-directory signature provenance, ZIP trailing-deflate
payload integrity, PAX long path/size/owner-only handling, duplicate PAX keyword
rejection, PAX global/per-entry path rejection, GNU long-name handling, base-256
numeric parsing, sparse archive rejection, link/device rejection, or symlink
policy.

## Follow-Up

Keep recursive nested-archive discovery, encrypted archive preflight, sparse
file reconstruction, hardlink/symlink extraction, non-deflate ZIP methods, full
upstream runner parity, and external-tool-backed validation as separate bounded
slices.
