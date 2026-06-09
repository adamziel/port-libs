# pandoc archive compression streams current-base 2026-06-09

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-archive-compression-streams-current-base-20260609T011121Z`
- Accepted base: `09109401d59cee7a589aaf8125432abbe4aef718`
- Bounded behavior: gzip wrapper platform and compression-strategy metadata preflight before TAR/ZIP package handoff.

## Behavior

`ArchiveCompressionStream::inspectGzipPlatformMetadataPolicy()` now inspects
gzip-wrapped TAR and ZIP package streams without exposing decoded payload bytes
in the policy packet. It reports per-member gzip operating-system metadata,
extra-flag meanings, filename/comment provenance, decoded offsets, compressed
sizes, uncompressed sizes, and review diagnostics.

Known gzip operating-system metadata and nonzero compression extra flags are
marked `review-before-conversion`; clean reproducible gzip wrappers remain
`within-thresholds`. Split gzip streams with differing OS metadata now emit
`gzip-platform-operating-system-varies`, and policy reports stay
metadata-only so DOCX/EPUB/ODT/WordPress import review can make a handoff
decision before package bytes are exposed.

The WordPress archive stream preflight example now includes a split gzip TAR
upload with Unix and NTFS wrapper metadata plus maximum/fastest compression
flags, proving the handoff surfaces wrapper platform metadata without shelling
out to archive tools.

## Verification

- Rework notes: none matched `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3157 assertions, 0 failures`.
- Red-first: the new focused test failed because `ArchiveCompressionStream::inspectGzipPlatformMetadataPolicy()` was missing.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3192 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` passed.
- PHP lint passed for:
  - `lanes/pandoc/src/ArchiveCompressionStream.php`
  - `lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - `lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
- JSON validation passed for:
  - `lanes/pandoc/lane-status.json`
  - `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `2027 -> 2028`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2442 -> 2443`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 155`.
- Focused archive stream assertions: `3157 -> 3192`.

## Dependency Closure

No new support component is needed. This reuses the native `GzipStream`,
`ArchiveCompressionStream`, `TarArchive`, and `ZipPackage` helpers. No Pandoc,
Cabal/Haskell runner, tar, gzip, zip/unzip, lz4, ZipArchive, Word,
LibreOffice, external archive tool, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This slice does not repeat gzip MTIME policy, member-count and byte-limit
preflight, source-name policy, text-hint policy, split-source layout,
package-boundary guards, TAR PAX timestamp/hdrcharset policy, TAR filesystem
attribute policy, TAR case-insensitive name collision policy, LZ4 dictionary
handling, ZIP payload-integrity checks, or nested archive-bomb policy. It is
limited to gzip wrapper platform and compression-strategy metadata needed
before richer package conversion handoff.
