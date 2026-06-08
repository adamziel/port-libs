# Pandoc Archive Compression Streams Current Base 2026-06-08

- Lane: `pandoc`
- Slice: `pandoc-archive-compression-streams-current-base-20260608T094120Z`
- Base accepted HEAD: `bc200aef66601a21c11500cbacbc2cbed269780c`

## Behavior

Added decoded source-segment provenance to TAR entry-layout inspection for
archive review packets. `ArchiveCompressionStream::tarStreamInspection()` now
maps each regular TAR entry record range back to the decoded compression stream
segment that contributed it:

- gzip members become `gzip-member` source segments labeled from member
  filename metadata when available;
- LZ4 data frames become `lz4-frame` source segments while skippable frames stay
  out of entry byte provenance;
- plain TAR, zlib-deflate, and raw-deflate streams expose a single decoded
  source segment for the inspected TAR bytes.

The WordPress archive stream preflight example now self-tests a split LZ4 TAR
entry whose record spans two decoded frame ranges and a gzip-wrapped TAR entry
whose source member label remains visible for reviewer provenance.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. The native PHP contract is bounded to package-fixture review metadata: TAR
entry byte layouts must retain enough decoded source provenance for WordPress
and package import review without extracting files or running external archive
tools.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, `gzip`,
`zip`, `unzip`, `lz4`, `ZipArchive`, Word, LibreOffice, external archive tool,
online service, live provider test, or live-service provider test was executed.

## Verification

- Baseline focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1619 assertions, 0 failures`.
- Intermediate focused test after adding decoded source segment metadata:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1633 assertions, 1 failures`.
  - Failure: an existing plain/gzip layout equality assertion still compared the
    whole entry layout after the new provenance fields were added.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 1639 assertions, 0 failures`.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- PHP lint:
  - `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/ArchiveCompressionStream.php`.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/ArchiveCompressionStreamTest.php`.
  - `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-archive-stream-preflight.php`.
- Lane JSON validation:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both lane JSON files valid.
- Whitespace:
  - `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `1619` to `1639` assertions.
- `lane-status.json` `phpPass` moves from `1600` to `1601`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator moves from `2019` to `2020`.
- Archive compression stream mapped core cases move from `11` to `12`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`ArchiveCompressionStream`, `GzipStream`, `Lz4Frame`, `TarArchive`, focused
in-memory fixtures, and the existing WordPress archive stream preflight example.
Full upstream Pandoc/Haskell runner parity, external archive-tool validation,
filesystem extraction, and new compression formats remain separate bounded
follow-up tasks.

## Non-Overlap

This does not repeat accepted unsupported bzip2/xz policy, gzip member
decompression alone, raw/zlib DEFLATE provenance, zlib/LZ4 dictionary decode,
split LZ4 frame byte ranges alone, TAR PAX timestamp/charset/duplicate-key,
sparse, multivolume, incremental, link/special-file policies, nested package
discovery, archive-bomb ratio checks, ZIP package primitives, or ZIP
encryption/compression-method preflights. The patch is limited to per-entry TAR
record source provenance after native decoded stream inspection.

## Follow-Up

Keep ZIP data-descriptor provenance, recursive nested archive limits,
additional unsupported compression policies, sparse-file reconstruction,
hardlink/symlink materialization, filesystem extraction, external archive-tool
validation, and full upstream-runner parity as separate bounded slices.
