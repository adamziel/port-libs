# Pandoc Archive Compression Streams Current Base - Zstandard Unsupported Policy

Slice: `pandoc-archive-compression-streams-current-base-20260608T163718Z`
Base accepted HEAD: `5b8ea24af48dcb3ad921ab7b94f34569273f4087`
Date: 2026-06-08 UTC

## Behavior

This slice extends the existing native archive compression preflight policy to
recognize Zstandard package streams without decoding them. `ArchiveCompressionStream`
now detects the Zstandard frame magic bytes and `.tar.zst`, `.tar.zstd`,
`.tzst`, `.zip.zst`, and `.zip.zstd` source names, classifies the package kind
when it can be inferred, records header/flag metadata, and keeps the extraction
policy fail-closed with `archive-compression-format-zstandard-not-decoded`.

No package bytes are passed to TAR, ZIP, OPC, or document readers for this
format. The behavior is intentionally a bounded unsupported-compression
handoff, not a Zstandard decoder.

## Verification

- Baseline before the slice:
  `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  passed with `1 test files, 2096 assertions, 0 failures`.
- Red-first check after adding Zstandard expectations and before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  failed with `1 test files, 2060 assertions, 1 failures`.
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  passed with `1 test files, 2125 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  passed.

Root harness was not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1695` -> `1696`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2115` -> `2116`.
- Archive compression stream core cases: `11` -> `12`.
- Archive compression stream core assertions: `120` -> `149`.

## Dependency Closure

No new native PHP support component is needed for this slice. It reuses the
existing bounded `ArchiveCompressionStream` unsupported-compression preflight
and package-kind policy. Zstandard decoding remains out of scope unless a
future slice explicitly approves and implements a bounded native decoder.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, tar, zip/unzip, zstd,
external archive tool, online service, live provider test, or live-service
provider test was executed.

## Non-Overlap

This does not duplicate the existing Deflate/Gzip/LZ4 decode paths, TAR source
provenance, ZIP64 data descriptor integrity handling, or prior BZip2/XZ
unsupported stream policy. The new mapped behavior is Zstandard-only fail-closed
archive preflight metadata.

## Follow-Up

A useful next archive slice should pick a non-overlapping native support gap,
such as ZIP central-directory encryption metadata, TAR PAX global policy edges,
or nested archive-bomb limits across compressed package streams, while keeping
external archive tools and Pandoc/Cabal/Haskell runners out of the lane.
