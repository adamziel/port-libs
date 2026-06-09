# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260609T034225Z`

Base accepted HEAD: `91cca3175da49493fc1f64ed296d9fb56109fdfc`

## Scope

Added bounded ZIP local-header span preflight coverage for archive stream carriers. `ArchiveCompressionStream::inspectZipLocalHeaderSpanPolicy()` now decodes ZIP bytes from native ZIP, gzip, zlib, raw-deflate, and LZ4 streams, delegates central/local span analysis to `ZipPackage::localHeaderSpanPreflight()`, preserves stream provenance, and avoids exposing a `ZipPackage` when central directory spans leave unclaimed local-entry bytes.

This avoids overlap with the already accepted archive stream descriptor, ZIP64, split-disk, archive-extra-record, encrypted package, general-purpose flag, unsupported compression, tar sparse, tar multivolume, gzip member, zlib dictionary, and LZ4 skippable/dictionary/block-size preflight clusters.

## Evidence

Baseline focused command before this patch:

```sh
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
```

Result: `1 test files, 3961 assertions, 0 failures`.

Red-first check: the focused test was added before implementation and failed on the missing `ArchiveCompressionStream::inspectZipLocalHeaderSpanPolicy()` method.

Final focused command:

```sh
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
```

Result: `1 test files, 4079 assertions, 0 failures`.

WordPress example smoke:

```sh
php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test
```

Result: `wordpress-archive-stream-preflight self-test passed`.

Mapped/status delta: +1 PHP PASS case, +118 focused assertions, mapped denominator `2651 -> 2652`, mapped archive compression stream core cases `11 -> 12`, archive compression stream core assertions `120 -> 238`, lane `phpPass` `2243 -> 2244`.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP stream decoders (`GzipStream`, `DeflateStream`, `Lz4Frame`), `ArchiveCompressionStream::decodeZipBytes()`, and `ZipPackage::localHeaderSpanPreflight()`.

Full upstream Pandoc package-reader parity remains a separate upstream-runner dependency task requiring a hydrated pinned checkout and Haskell test executables. No Pandoc, Cabal solver/build/test command, Haskell runner, TeX/PDF engine, Word, LibreOffice, `zip`/`unzip`, `tar`, browser renderer, external converter, online service, live provider test, or live-service provider test was executed for this slice.

## Next

Choose a non-overlapping archive/compression gap such as ZIP central directory ordering policy, nested archive handoff limits, or tar/LZ4 provenance not already covered by local-header span, descriptor, ZIP64, split, archive-extra, encrypted, general-purpose flag, sparse, multivolume, or skippable-frame checks.
