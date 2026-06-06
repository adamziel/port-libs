# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260606T180617Z`
Base accepted HEAD: `11a2e57d1384f7898502502ab620e40838291fb1`

## Behavior

- Added bounded TAR PAX `hdrcharset` policy in `TarArchive`.
- Supported local and global PAX markers remain visible in package review metadata:
  `ISO-IR 10646 2000 UTF-8`, `UTF-8`, and `BINARY`.
- Unsupported `hdrcharset` values now throw before package bytes are exposed through
  native archive inspection, link-policy preflight, or generated TAR packets.
- The WordPress archive stream preflight smoke now includes a gzip-wrapped TAR with
  global UTF-8 and local BINARY `hdrcharset` provenance, plus an unsupported
  UTF-16LE marker that must remain blocked.

## Source Truth

This ports a bounded archive support-library behavior needed by document conversion
package fixtures: PAX header charset provenance must be preserved for reviewer
audit packets, while unsupported header charset declarations must fail closed before
Office/EPUB/review archive bytes are exposed.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`,
external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool,
browser renderer, online sanitizer, online service, live provider test, or
live-service provider test was executed.

## Verification

- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 736 assertions, 0 failures`.
- Red-first:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 745 assertions, 1 failures`.
  - Failure: unsupported PAX `hdrcharset=UTF-16LE` was accepted before package
    exposure.
- After implementation:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 748 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- Syntax and lane checks:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `736` to `748` assertions.
- `lane-status.json` `phpPass` moves from `1381` to `1382`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1794` to `1795`.
- Archive-compression counters move from `10` to `11` mapped support cases and
  from `101` to `113` focused support assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TarArchive`,
`ArchiveCompressionStream`, `GzipStream`, in-memory TAR fixtures, the WordPress
archive stream preflight example, and the focused PHP test harness. Full upstream
Pandoc runner parity remains gated on hydrated upstream checkout and Haskell Tasty
runner execution, which is intentionally out of scope for this slice.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation,
split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE provenance,
LZ4 frame parsing or writing, ZIP/OPC package primitives, TAR PAX path/size/owner
metadata, PAX access/change timestamp parsing, PAX deletion application, duplicate
PAX keyword rejection, GNU long-name parsing, TAR link-policy or sparse-policy
preflight, GNU long-link rejection, typeflag `7` contiguous file handling,
trailing-slash regular-entry directory normalization, TAR end-marker validation,
TAR drive-letter rejection, base-256 numeric decoding, TAR device rejection,
signed checksum compatibility, nested package discovery, or generic TAR/ZIP
package-kind detection.

## Follow-Up

Keep sparse-file reconstruction, hardlink/symlink extraction, filesystem
extraction, recursive content conversion, encrypted archive preflight, archive-bomb
heuristics, non-deflate ZIP methods, dictionary-backed LZ4 frames, multi-volume
archive handling, external archive-tool validation, and full upstream-runner parity
as separate bounded slices unless concrete package fixtures require them.
