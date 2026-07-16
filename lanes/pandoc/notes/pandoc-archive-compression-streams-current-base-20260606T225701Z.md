# Pandoc Archive Compression Streams Current Base 2026-06-06T22:57:01Z

Lane: `pandoc`
Slice: `pandoc-archive-compression-streams-current-base-20260606T225701Z`
Base: `72b74d8bf978910fedcbf4b3ed6fbaee9456d76b`

## Behavior

Added a bounded metadata-only TAR PAX duplicate-key policy preflight:

- `TarArchive::paxDuplicateKeywordPreflight()` scans global and local PAX header
  payloads, preserves duplicate keywords, first values, repeated values,
  occurrence counts, byte offsets, and a blocked extraction policy.
- `ArchiveCompressionStream::inspectTarPaxDuplicateKeywordPolicy()` exposes the
  same policy through gzip/zlib/raw-deflate/LZ4/plain TAR stream decoding.
- Strict archive exposure still rejects duplicate PAX keywords through
  `TarArchive::fromString()`, so package bytes are not exposed when metadata is
  contradictory.
- `wordpress-archive-stream-preflight.php` now surfaces duplicate PAX keyword
  evidence for review packets and proves strict extraction remains blocked.

## Source Truth

This ports a bounded POSIX/PAX TAR support behavior needed by package fixture
review: repeated PAX keywords are unsafe for extraction/import decisions, but
the exact duplicate records are useful provenance for WordPress reviewers and
for diagnosing why a TAR package stayed blocked.

No Pandoc, Cabal build/test command, Haskell runner, Word, LibreOffice, `tar`,
external `gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, external archive tool,
browser renderer, online sanitizer, online service, live provider test, or
live-service provider test was executed.

## Verification

- Focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 794 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- Syntax checks:
  - `php -l lanes/pandoc/src/TarArchive.php`
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: no syntax errors.
- Lane diff check:
  - `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test grew from `767` to `794` assertions.
- `lane-status.json` `phpPass` moves from `1410` to `1411`.
- `UPSTREAM_TEST_MANIFEST.json` mapped count moves from `1823` to `1824`.
- Archive-compression counters move from `11` to `12` mapped support cases and
  from `120` to `147` focused support assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `TarArchive`,
`ArchiveCompressionStream`, `GzipStream`, in-memory TAR/PAX fixtures, the
WordPress archive stream preflight example, and the focused PHP test harness.
Full upstream Pandoc/Haskell runner parity, external archive-tool validation,
sparse-file reconstruction, filesystem extraction, recursive conversion,
archive-bomb heuristics, non-deflate ZIP methods, dictionary-backed LZ4 frames,
and multi-volume archive handling remain separate bounded follow-up work.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip CRC/header validation,
split-gzip XFL/OS/CRC32 and byte-layout provenance, raw/zlib DEFLATE
provenance, LZ4 frame parsing or writing, ZIP/OPC package primitives, ZIP
encryption flag rejection, TAR PAX path/size/owner metadata, PAX access/change
timestamp parsing, PAX deletion application, PAX hdrcharset policy, strict
duplicate PAX keyword rejection, GNU long-name parsing, TAR link-policy
preflight, sparse-entry blocking and sparse-map provenance, GNU long-link
rejection, typeflag `7` contiguous file handling, trailing-slash regular-entry
directory normalization, TAR end-marker validation, TAR drive-letter rejection,
base-256 numeric decoding, TAR device rejection, signed checksum compatibility,
nested package discovery, or generic TAR/ZIP package-kind detection.

## Follow-Up

Keep archive-bomb heuristics, global PAX provenance summaries, sparse-file
reconstruction, hardlink/symlink extraction, filesystem extraction, recursive
content conversion, non-deflate ZIP methods, dictionary-backed LZ4 frames,
multi-volume archive handling, external archive-tool validation, and full
upstream-runner parity as separate bounded slices unless concrete package
fixtures require them.
