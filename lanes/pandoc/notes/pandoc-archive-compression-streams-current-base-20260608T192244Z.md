# Pandoc Archive Compression Streams Current Base 20260608T192244Z

## Slice

- Lane: `pandoc`
- Micro-slice: `pandoc-archive-compression-streams-current-base-20260608T192244Z`
- Base accepted HEAD: `e97bdf9331ef05dac3f6237d837a28df8dd53eb5`
- Scope: compressed OPC/EPUB package source-name classification for archive preflight.

## Behavior

Added source-name policy coverage for compressed ZIP-package document aliases.

`ArchiveCompressionStream::inspectPackageSourceNamePolicyAuto()` now treats
`.docx`, `.dotx`, `.docm`, `.odt`, `.ods`, `.odp`, and `.epub` names wrapped
with `.gz`, `.zlib`, `.deflate`, or `.lz4` as ZIP package candidates with the
matching supported compression format. The unsupported compression policy now
also treats the same package aliases wrapped with `.bz2`, `.xz`, `.zst`, or
`.zstd` as blocked ZIP package candidates.

This keeps the handoff metadata-only: unsupported compressed package streams
remain fail-closed and no external decompressor is run.

## Verification

Baseline focused test before this patch:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 2175 assertions, 0 failures
```

Red-first focused run after adding the compressed `.DOCX.GZ` source-name test:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 2158 assertions, 1 failures
```

The failure was the intended gap: `WORD-EXPORT.DOCX.GZ` decoded as a gzip ZIP
package but `sourceNameCandidate` was still `false`.

Final focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 2201 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test
wordpress-archive-stream-preflight self-test passed
```

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive test coverage grew from `2175` to `2201` assertions.
- `lane-status.json` `phpPass`: `1750 -> 1751`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2166 -> 2167`.
- Archive compression mapped core cases: `11 -> 12`.
- Archive compression focused assertion counter: `120 -> 146`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses
`ArchiveCompressionStream` source-name policy, the existing bounded
gzip/zlib/raw-deflate/LZ4 decoders, unsupported compression fail-closed policy,
the focused archive tests, and the WordPress archive preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, `tar`, external
`gzip`, `zip`, `unzip`, `lz4`, `ZipArchive`, Word, LibreOffice, external
archive tool, online service, live provider test, or live-service provider test
was executed.

## Non-Overlap

This does not repeat accepted gzip member parsing, gzip byte-layout offsets,
gzip FTEXT binary-payload policy, TAR entry-layout source segments, decoded
chunk provenance, LZ4 frame range provenance, zlib/LZ4 dictionary package
inspection, unsupported BZip2/XZ/Zstandard signature detection, ZIP
data-descriptor stream policies, split-ZIP disk markers, archive-bomb ratio
checks, nested package discovery, TAR PAX timestamp/hdrcharset/duplicate-key
policy, sparse/multi-volume/incremental/link/special-file TAR policies, or ZIP
package primitives.

## Follow-Up

Useful non-overlapping archive follow-ups remain nested archive-bomb limits
across compressed package streams, ZIP central-directory encryption metadata,
or additional source-name policy only when a real package fixture needs it.
