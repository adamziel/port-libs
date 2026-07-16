# Pandoc Archive Compression Streams Slice 2026-06-06 02:28 UTC

Slice: `pandoc-archive-compression-streams-current-base-20260606T022823Z`

Base accepted HEAD: `8939543119a291af01b67d59e9e9d7db95241345`

## Behavior

- Added bounded gzip FTEXT provenance handling to `GzipStream`.
- `GzipStream::build()` now accepts `textHint => true` and sets the gzip
  member FTEXT flag while preserving existing filename/comment/extra/header-CRC
  behavior.
- `GzipStream::inspect()` now exposes the raw gzip header `flags` byte and a
  boolean `textHint` field for review packets produced by native PHP or external
  archive sources.
- `ArchiveCompressionStream` projects `flags` and `textHint` into gzip-backed
  TAR/ZIP inspection metadata so WordPress package preflight can audit
  text/binary source hints before exposing package bytes.
- The WordPress ZIP/package preflight example now verifies an FTEXT-marked
  gzip-wrapped TAR packet without invoking external archive tools.

## Evidence

- Rework notes: only stale May
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/stale/port-pandoc-*.needs-lane-rework.md`
  files were present; no current-base archive rework note was found.
- Red-first:
  `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  failed before implementation at `exposes gzip text hint flag for archive
  review provenance` because `textHint` metadata was missing.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  passed with `1 test files, 478 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  passed with `zip package writer preflight self-test passed`.
- Root harness status: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1156 -> 1157`.
- `benchmarkDenominator.mapped`: `1606 -> 1607`.
- Focused archive compression coverage: `49` PASS cases / `478` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `GzipStream`
and `ArchiveCompressionStream` inspection. It does not require Pandoc, Cabal,
Haskell runners, tar, gzip, zip/unzip, lz4, ZipArchive, Word, LibreOffice,
external archive tools, browser renderers, online sanitizers, online services,
or live provider tests.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip Latin-1 filename/comment
text decoding, gzip XFL/OS labels, gzip extra subfield parsing, gzip trailing
NUL padding, split gzip member provenance, raw/zlib DEFLATE integrity, TAR PAX
metadata, TAR sparse/link/device rejection, ZIP central-directory/data
descriptor work, ZIP trailing-deflate payload integrity, LZ4 framing/checksums,
or archive package auto-detection. It only adds the previously unexposed gzip
FTEXT header bit and raw flags provenance.

## Follow-Up

Keep recursive nested-archive discovery, encrypted archive preflight, sparse
file reconstruction, hardlink/symlink extraction, non-deflate ZIP methods, full
upstream runner parity, and external-tool-backed validation as separate bounded
slices.
