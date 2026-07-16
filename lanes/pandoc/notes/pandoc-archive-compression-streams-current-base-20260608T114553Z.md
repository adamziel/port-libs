# Pandoc Archive Compression Streams Current Base

- Lane: `pandoc`
- Slice: `pandoc-archive-compression-streams-current-base-20260608T114553Z`
- Base accepted HEAD: `92b623591d4c99d822de39f3b30bbbffe20bf3a9`

## Behavior

Added archive-stream-level split ZIP disk-marker policy preflight.
`ArchiveCompressionStream::inspectZipSplitArchivePolicy()` decodes plain ZIP,
gzip ZIP, zlib ZIP, raw-deflate ZIP, and LZ4 ZIP package streams, then reuses
`ZipPackage::splitArchivePreflight()` to expose EOCD disk markers,
central-directory `diskStart` markers, unsupported split-entry counts, decoded
ZIP bytes, package byte size, and stream provenance.

The policy remains fail-closed: split/spanned ZIP package entries are reported
for WordPress review, but the bounded package reader still does not expose
entries for conversion or extraction.

## Source Truth

This ports the bounded ZIP package stream contract already represented by the
native ZIP package primitive. ZIP EOCD disk fields and central-directory
`diskStart` fields identify spanned archives; the pandoc lane reports those
markers before conversion handoff instead of trying to recover or extract split
volumes.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, `zip`,
`unzip`, `lz4`, `ZipArchive`, external archive tool, online service, live
provider test, or live-service provider test was executed.

## Verification

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1756 assertions, 0 failures`
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1859 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors
- Lane JSON validation:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both lane JSON files valid
- Whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: no output

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `1756` to `1859` assertions.
- `lane-status.json` `phpPass`: `1629 -> 1630`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2048 -> 2049`.
- Archive compression mapped core cases: `11 -> 12`.
- Archive compression focused assertion counter: `120 -> 223`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ArchiveCompressionStream`, `ZipPackage::splitArchivePreflight()`,
`GzipStream`, `DeflateStream`, `Lz4Frame`, the focused archive test file, and
the existing WordPress archive preflight example.

Full upstream Pandoc/Haskell runner parity, split ZIP extraction/recovery,
ZIP64 data-descriptor stream policy, nested archive recursion changes,
filesystem extraction, and external archive-tool validation remain separate
bounded follow-up work.

## Non-Overlap

This does not repeat accepted ZIP package split-archive preflight itself,
ZIP data-descriptor stream provenance, ZIP encryption/compression-method stream
wrappers, unsupported BZip2/XZ policy, gzip member provenance, raw/zlib
DEFLATE provenance, zlib/LZ4 dictionary package inspection, split LZ4 frame
range provenance, TAR entry source segments, TAR sparse/multi-volume/
incremental/link/special-file policies, nested package discovery, archive-bomb
ratio checks, or ZIP64 package primitive preflights. The patch is limited to
wrapping existing split ZIP disk-marker diagnostics across archive compression
streams.
