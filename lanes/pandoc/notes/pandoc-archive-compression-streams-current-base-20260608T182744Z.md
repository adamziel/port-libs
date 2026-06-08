# Pandoc Archive Compression Streams Current Base 20260608T182744Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-archive-compression-streams-current-base-20260608T182744Z`
- Base accepted HEAD: `fe02d7a3097ad39446ef113ff421214b36621f31`
- Scope: metadata-only decoded package chunk provenance for split compressed package streams.

## Behavior

Added `ArchiveCompressionStream::inspectDecodedPackageChunksAuto()`.

The new preflight detects TAR or ZIP package streams through the existing
bounded compression decoders, then returns only package-level metadata:
decoded chunk offsets, entry names, stream member/frame provenance, and source
member/frame overlap for each decoded chunk. It does not expose decoded TAR/ZIP
bytes, `TarArchive`, or `ZipPackage` objects.

The focused behavior covers a gzip-wrapped TAR upload split across two gzip
members. A 1024-byte decoded review chunk that crosses the gzip member boundary
now reports both source member labels and exact decoded offset ranges before
WordPress conversion handoff.

## Verification

Baseline focused test before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 2125 assertions, 0 failures
```

Intermediate focused run after adding the test and implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 2155 assertions, 1 failures
```

The failure was a local test-harness mismatch: this lane's `TestRunner` has
`true()` but not `false()`. The assertions were adjusted to the existing
`same(false, ...)` style.

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 2160 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test
wordpress-archive-stream-preflight self-test passed
```

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `2125` to `2160` assertions.
- `lane-status.json` `phpPass`: `1718 -> 1719`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2139 -> 2140`.
- Archive compression mapped core cases: `11 -> 12`.
- Archive compression focused assertion counter: `120 -> 155`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses
`ArchiveCompressionStream` stream inspection, `GzipStream` member provenance,
`TarArchive`/`ZipPackage` package detection, the focused archive tests, and the
WordPress archive preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, Word, LibreOffice, external
archive tool, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This does not repeat accepted gzip member parsing, gzip byte-layout offsets,
gzip FTEXT binary-payload policy, TAR entry-layout source segments, LZ4 frame
range provenance, zlib/LZ4 dictionary package inspection, unsupported BZip2/XZ
or Zstandard fail-closed policies, ZIP data-descriptor stream policies,
split-ZIP disk markers, archive-bomb ratio checks, nested package discovery,
TAR PAX timestamp/hdrcharset/duplicate-key policy, sparse/multi-volume/
incremental/link/special-file TAR policies, or ZIP package primitives.

## Follow-Up

Useful non-overlapping archive follow-ups remain ZIP central-directory
encryption metadata, TAR PAX global policy edges, nested archive-bomb limits
across compressed package streams, or additional chunk provenance only when a
real package fixture needs it.
