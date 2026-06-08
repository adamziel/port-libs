# Pandoc Archive Compression Streams Current Base 20260608T154248Z

## Slice

- Lane: pandoc
- Micro-slice: pandoc-archive-compression-streams-current-base-20260608T154248Z
- Accepted base: b74dfb666585975f95b4cdb08212431ed64ad41f
- Scope: bounded ZIP64 end-of-central-directory accounting across supported ZIP compression streams.

## Implementation

Added `ArchiveCompressionStream::inspectZip64EndOfCentralDirectoryPolicy()`.
The policy decodes plain ZIP, gzip-wrapped ZIP, zlib-wrapped ZIP, raw-deflate ZIP, and LZ4-framed ZIP bytes using the existing bounded stream decoders, then reuses `ZipPackage::zip64EndOfCentralDirectoryAccountingPreflight()` so WordPress review queues can detect ZIP64 EOCD records and EOCD sentinel fields before any package entries are exposed.

The stream-level preflight preserves decoded package bytes, byte sizes, ZIP64 record/locator accounting, single-disk versus split diagnostics, and gzip/LZ4 provenance. The bounded `ZipPackage::fromString()` reader still rejects ZIP64 EOCD packages for import/extraction; this slice only surfaces metadata needed for safe review.

The WordPress archive-stream preflight smoke now includes a gzip-wrapped ZIP64 EOCD package fixture and confirms that the package is blocked before extraction while retaining the ZIP64 issues and gzip filename provenance.

## Focused Evidence

Baseline focused test before this patch:

```sh
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
```

Result: `1 test files, 2096 assertions, 0 failures`.

Red-first focused test after adding the new case:

```sh
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
```

Result: `1 test files, 2097 assertions, 1 failures`.
Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectZip64EndOfCentralDirectoryPolicy()`.

Final focused test after implementation:

```sh
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
```

Result: `1 test files, 2255 assertions, 0 failures`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test
```

Result: `wordpress-archive-stream-preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- Added one mapped native archive-compression support case.
- Focused archive-stream assertions grew from `2096` to `2255` (`+159`).
- `lane-status.json` `phpPass`: `1695 -> 1696`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2115 -> 2116`.
- Archive compression stream mapped core cases: `11 -> 12`.
- Archive compression focused assertion counter: `120 -> 279`.

## Dependency Closure

No new support component is needed. This slice reuses native PHP `ArchiveCompressionStream`, `ZipPackage`, `GzipStream`, `DeflateStream`, `Lz4Frame`, the focused archive-stream test, and the existing WordPress archive-stream preflight example.

No Pandoc, Cabal solver/build/test command, Haskell runner, tar, zip/unzip, gzip, lz4, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted archive-stream slices for ZIP data descriptors, ZIP64 data-descriptor integrity, split-ZIP disk markers, source-name mismatch policy, gzip FTEXT binary-payload policy, gzip provenance, unsupported BZip2/XZ blocking, archive-bomb ratios, nested archive discovery, TAR PAX/sparse/multi-volume/link/special-file policy, zlib/LZ4 dictionary streams, split LZ4 frame provenance, or decoded TAR entry source segments.

It also does not implement full ZIP64 import or extraction. ZIP64 EOCD packages remain blocked by the bounded ZIP reader and are surfaced only as review metadata.

## Follow-Up

Useful follow-up archive-compression slices include additional stream-integrity metadata, recursive nested archive limit reporting, filesystem extraction-policy boundaries, or ZIP central-directory encryption metadata. Full upstream Pandoc/Haskell runner parity remains a separate upstream-runner dependency audit track.
