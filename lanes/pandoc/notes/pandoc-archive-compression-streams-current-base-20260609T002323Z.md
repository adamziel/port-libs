# pandoc archive compression streams current-base 2026-06-09

## Scope

- Lane: `pandoc`
- Micro-slice: `pandoc-archive-compression-streams-current-base-20260609T002323Z`
- Accepted base: `72cabc3f4f492b184408152fdc147cadc8cc603f`
- Bounded behavior: gzip member MTIME metadata preflight before DOCX/EPUB/ODT package handoff.

## Behavior

`ArchiveCompressionStream::inspectGzipTimestampPolicy()` now inspects gzip-wrapped
TAR and ZIP package streams without extracting payload data into the handoff
report. It records per-member MTIME provenance, filename/comment metadata,
extra-flag meaning, operating-system names, decoded offsets, and compressed and
uncompressed sizes.

The policy marks nonzero gzip member MTIME metadata as
`review-before-conversion`, reports `gzip-member-timestamp-metadata-present`,
and adds `gzip-member-timestamp-metadata-varies` when timestamped split members
disagree. Zero-MTIME members remain metadata-only, preserving reproducible
package handling. The existing stream openers still decode concatenated gzip TAR
members for normal archive reads.

The WordPress archive stream preflight example now includes a split gzip TAR
upload with one reproducible member and one timestamped member, proving the
package handoff can surface mutable gzip wrapper metadata without shelling out
to archive tools or exposing package bytes.

## Verification

- Rework notes: none matched `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3107 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` passed with `1 test files, 3157 assertions, 0 failures`.
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

- `lane-status.json` `phpPass`: `2009 -> 2010`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2425 -> 2426`.
- Focused archive stream assertions: `3107 -> 3157`.

## Dependency Closure

No new support component is needed. This reuses the native `GzipStream`,
`ArchiveCompressionStream`, `TarArchive`, and `ZipPackage` helpers. No Pandoc,
Cabal/Haskell runner, tar, gzip, zip/unzip, lz4, ZipArchive, Word,
LibreOffice, external archive tool, online service, live provider test, or
live-service provider test was executed.

## Non-Overlap

This slice does not repeat member-count and byte-limit preflight, source-name
policy, text-hint policy, split-source layout, package-boundary guards, TAR PAX
timestamp policy, TAR filesystem attribute policy, TAR case-insensitive name
collision policy, LZ4/zlib dictionary handling, or nested archive-bomb policy.
It only covers gzip wrapper MTIME metadata needed before richer package
conversion handoff.
