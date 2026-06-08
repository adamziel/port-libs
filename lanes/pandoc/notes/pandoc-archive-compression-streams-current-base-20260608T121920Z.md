# Pandoc Archive Compression Streams Current Base: ZIP64 Data-Descriptor Integrity

Date: 2026-06-08 UTC
Base accepted HEAD: `1216d2e660c60a15fb578b6dfd0473fc7e462592`
Slice: `pandoc-archive-compression-streams-current-base-20260608T121920Z`

## Behavior

Added a stream-level ZIP data-descriptor integrity preflight:

- `ArchiveCompressionStream::inspectZipDataDescriptorIntegrityPolicy()` decodes plain ZIP, gzip-wrapped ZIP, zlib ZIP, raw-deflate ZIP, and LZ4-framed ZIP bytes using the existing bounded stream decoders.
- The policy reuses `ZipPackage::dataDescriptorIntegrityPreflight()` so ZIP64-sized signed and unsigned data descriptors can be diagnosed from raw package bytes without requiring `ZipPackage::fromString()` to accept the archive.
- The bounded ZIP reader still rejects ZIP64-sized descriptors for import/extraction. This slice only preserves preflight evidence: descriptor length, signature presence, value offset, central-directory match state, ZIP64 descriptor count, issues, decoded ZIP bytes, and compression-stream provenance.

## Verification

Baseline focused test before this patch:

```sh
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
```

Result: `1 test files, 1859 assertions, 0 failures`.

Final focused test:

```sh
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
```

Result: `1 test files, 2012 assertions, 0 failures`.

Focused assertion delta: `+153`.

Example smoke:

```sh
php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test
```

Result: passed.

Additional required checks run for this slice:

```sh
php -l lanes/pandoc/src/ArchiveCompressionStream.php
php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php
php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php
php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane JSON valid\n";'
git diff --check -- lanes/pandoc
```

All passed.

## Status Delta

- `lane-status.json` `phpPass`: `1640 -> 1641`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2060 -> 2061`
- `mappedArchiveCompressionStreamCoreCases`: `11 -> 12`
- `archiveCompressionStreamCoreAssertions`: `120 -> 273`

## Dependency Closure

No new native PHP support component is needed. This slice reuses the existing bounded `ArchiveCompressionStream` ZIP decoders and the existing `ZipPackage::dataDescriptorIntegrityPreflight()` byte-level scanner.

No Pandoc, Cabal solver/build/test command, Haskell runner, tar, zip/unzip, lz4, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This avoids the already accepted archive-stream slices for unsupported BZip2/XZ policy, decoded source-segment provenance, accepted-package ZIP data-descriptor provenance, and split-ZIP disk-marker policy. It also avoids implementing full ZIP64 package import; ZIP64-sized descriptors remain a bounded-reader rejection surfaced through diagnostics.

## Follow-Up

A useful next archive-compression gap is ZIP central-directory encryption metadata, TAR PAX global policy edges, or nested archive-bomb limits across compressed package streams.
