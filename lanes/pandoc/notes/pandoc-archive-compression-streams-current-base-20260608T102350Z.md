# Pandoc Archive Compression Streams Current Base

## Slice

- Lane: pandoc
- Micro-slice: pandoc-archive-compression-streams-current-base-20260608T102350Z
- Accepted base: a54545a529de1862e6e524e6822e40ce7f7c6600

## Behavior

Added bounded ZIP data-descriptor provenance inspection at the archive-stream layer. `ArchiveCompressionStream::inspectZipDataDescriptorPolicy()` now decodes plain ZIP, gzip ZIP, zlib ZIP, raw-deflate ZIP, and LZ4 ZIP package streams, then reuses the existing native `ZipPackage::dataDescriptorPreflight()` contract to expose descriptor entry counts, signed vs unsigned descriptor records, local-header zero placeholders, package byte size, decoded ZIP bytes, and compression-stream metadata before conversion handoff.

The focused test fixture covers one deflated signed descriptor entry and one stored unsigned descriptor entry, plus a metadata entry without a descriptor. The WordPress archive-stream preflight example now builds a descriptor-backed DOCX-style ZIP package and reports the same policy metadata through a gzip ZIP upload path.

## Source Truth

This slice ports the ZIP format contract already represented by the lane's native ZIP package primitive: general-purpose bit 3 defers CRC and sizes to a data descriptor, local headers may carry zero placeholders, and descriptors may be signed or unsigned. No external archive implementation was invoked.

## Evidence

Baseline before test change:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 1639 assertions, 0 failures
```

Red-first after adding the descriptor stream test:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 1639 assertions, 1 failures
FAIL preflights zip data descriptors across archive streams without losing provenance
Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectZipDataDescriptorPolicy()
```

Final focused checks after implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 1756 assertions, 0 failures

php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test
wordpress-archive-stream-preflight self-test passed
```

Focused delta: +1 TestRunner PASS case and +117 focused assertions.

Required finishing checks:

```text
php -l lanes/pandoc/src/ArchiveCompressionStream.php
No syntax errors detected in lanes/pandoc/src/ArchiveCompressionStream.php

php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php
No syntax errors detected in lanes/pandoc/tests/ArchiveCompressionStreamTest.php

php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php
No syntax errors detected in lanes/pandoc/examples/wordpress-archive-stream-preflight.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $path) { json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo $path, " OK\n"; }'
lanes/pandoc/lane-status.json OK
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK

git diff --check -- lanes/pandoc
passed with no output
```

## Status Delta

- `lane-status.json` `phpPass`: 1616 -> 1617.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: 2035 -> 2036.
- `mappedArchiveCompressionStreamCoreCases`: 11 -> 12.
- `archiveCompressionStreamCoreAssertions`: 120 -> 237.

## Dependency Closure

No new support component is needed. This slice reuses existing native PHP `DeflateStream`, `GzipStream`, `Lz4Frame`, `ArchiveCompressionStream` ZIP stream decoding, and `ZipPackage::dataDescriptorPreflight()` package semantics.

## Non-Overlap

Avoided prior accepted archive slices for split gzip member provenance, TAR entry source segments, unsupported BZip2/XZ policy, zlib/LZ4 dictionary package streams, TAR PAX metadata, sparse/multi-volume/link/special-file policies, ZIP encryption policy, and unsupported ZIP compression method policy. This patch is specifically the package-stream wrapper for ZIP data-descriptor provenance.

## Exclusions

Did not run Pandoc, Cabal/Haskell runners, tar, zip/unzip, lz4, ZipArchive, Word, LibreOffice, external archive tools, online services, live provider tests, live-service provider tests, or the root harness.

## Follow-Up

Non-overlapping archive follow-ups: ZIP64 data descriptors, multi-disk ZIP rejection provenance, split ZIP central-directory preflight, or additional TAR/ZIP stream-filter metadata.
