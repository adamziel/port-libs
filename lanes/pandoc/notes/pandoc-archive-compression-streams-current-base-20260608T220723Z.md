# Pandoc Archive Compression Streams Current Base 2026-06-08T22:07:23Z

Slice: `pandoc-archive-compression-streams-current-base-20260608T220723Z`

Base accepted HEAD: `5ca5ed5c01549ddcb5727c8343ae1666cecfe98d`

## Behavior

- Added decoded source-segment provenance for zlib package streams that require a preset dictionary.
- `ArchiveCompressionStream::inspectPackageStreamWithZlibDictionaries()` now marks decoded TAR/ZIP entry source segments from a Dict-ID-backed stream as `zlib-preset-dictionary-deflate`.
- When the zlib wrapper exposes a preset dictionary ID, decoded entry source segments now include a stable `dictid:0x...` source label for review handoff.
- Plain zlib/deflate/gzip/LZ4 source segment behavior remains unchanged.

## Evidence

- Focused baseline before edits: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2826 assertions, 0 failures`.
- Red-first check after adding the dictionary provenance assertion: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2836 assertions, 1 failures`; expected `zlib-preset-dictionary-deflate`, actual `zlib-deflate`.
- Focused test after implementation: `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php` -> `1 test files, 2844 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test` -> `wordpress-archive-stream-preflight self-test passed`.

## Status Delta

- `lane-status.json` `phpPass`: `1902 -> 1903`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2324 -> 2325`.
- Archive compression mapped core cases: `11 -> 12`.
- Archive compression focused assertion counter: `120 -> 138`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP zlib dictionary decoder, `ArchiveCompressionStream` package inspector, TAR/ZIP entry layout mapping, and the WordPress archive preflight example.

Pandoc, Cabal/Haskell runners, Word, LibreOffice, `tar`, `zip`, `unzip`, external decompression tools, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This does not repeat accepted gzip member parsing, gzip byte-layout offsets, gzip FTEXT binary-payload policy, TAR entry-layout source segments for ordinary streams, decoded package chunk source mapping, LZ4 frame range provenance, supplied LZ4 dictionary decoding, zlib/LZ4 dictionary package inspection success/failure, source-name mismatch policy, unsupported compression stream policy, ZIP data-descriptor provenance, ZIP64 descriptor integrity, split-ZIP disk markers, nested package discovery, nested archive-bomb policy, or TAR PAX policy edges. It adds the missing entry-level source identity for zlib preset-dictionary package streams.

## Next

A useful non-overlapping archive follow-up would be compressed ZIP central-directory encryption metadata, nested depth-limit diagnostics across unsupported compression candidates, or another TAR PAX policy edge needed by real package fixtures.
