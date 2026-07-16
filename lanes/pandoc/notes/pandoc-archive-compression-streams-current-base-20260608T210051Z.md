# Pandoc Archive Compression Streams Current Base 2026-06-08

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-archive-compression-streams-current-base-20260608T210051Z`
- Accepted base: `aff8ca4f9f2fc3fdfd4b02cd326e0b86bc931fad`
- Behavior cluster: ZIP general-purpose flag preflight after native archive stream decoding.

## Implementation

`ArchiveCompressionStream::inspectZipGeneralPurposeFlagPolicy()` now decodes a ZIP package from plain ZIP, gzip-wrapped ZIP, zlib-deflated ZIP, raw-deflated ZIP, or LZ4-framed ZIP bytes and delegates to the existing `ZipPackage::generalPurposeFlagPreflight()` policy. The preflight exposes UTF-8 name flags, data-descriptor use, deflate option flags, strict-review entries, and unsupported flag entries without returning a package object or invoking external archive tools.

The focused test covers the same ZIP fixture through:

- `zip`
- `gzip-zip`
- `zlib-zip`
- `raw-deflate-zip`
- `lz4-zip`

The WordPress archive-stream preflight example now includes a gzip-wrapped ZIP fixture with UTF-8 name flags, one data-descriptor entry, and one deflate option flag entry so reviewer queues can see strict-review metadata before DOCX/EPUB/ODT handoff.

## Evidence

- Rework-note check: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` files existed.
- Baseline focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2546 assertions, 0 failures`
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 2713 assertions, 0 failures`
  - Delta: `+167` focused assertions in the existing archive-stream PHP PASS case.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`

## Dependency Closure

No new support component is needed. This slice reuses native `ArchiveCompressionStream` ZIP decoding, `ZipPackage::generalPurposeFlagPreflight()`, `GzipStream`, `DeflateStream`, and `Lz4Frame`. No Pandoc, Cabal/Haskell runner, tar, gzip, zip/unzip, lz4 binary, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice does not duplicate accepted archive compression work for TAR PAX hdrcharset/access/change timestamps, gzip member source-name policy, gzip package-boundary/chunk mapping, unsupported nested compression candidates, ZIP data descriptor policy/integrity, ZIP64 EOCD policy, split archive policy, encrypted ZIP policy, unsupported ZIP compression method policy, supplied LZ4 dictionaries, or archive expansion-ratio policy. It adds only the stream-layer handoff for the existing supported ZIP general-purpose flag review contract.

## Root Harness

Not run - isolated micro-slice.

## Next

A useful non-overlapping archive-compression follow-up would be ZIP local-header flag/name mismatch policy across stream wrappers or compressed central-directory extra-field review, still bounded to native PHP preflight without external archive tools.
