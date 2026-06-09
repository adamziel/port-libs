# pandoc-archive-compression-streams-current-base-20260609T023343Z

## Scope

Implemented one bounded archive/compression stream behavior cluster for ZIP local-header mismatch preflight on accepted base `baf3ce2966b31d81f7576b68e2155b8538ba2649`.

The new `ArchiveCompressionStream::inspectZipLocalHeaderNamePolicy()` wrapper decodes ZIP package bytes from these stream formats before delegating to the existing `ZipPackage::localHeaderNamePreflight()` support:

- plain ZIP
- gzip-wrapped ZIP
- zlib-wrapped ZIP
- raw-deflate-wrapped ZIP
- LZ4-wrapped ZIP

The focused fixture mutates the local ZIP header so the local file name and general-purpose UTF-8 flag disagree with the central directory entry. The preflight reports central/local names, decoded-name equality, raw-name equality, central/local flags, name encodings, mismatch count, and extraction issues without exposing a strict package object.

## Evidence

Red-first focused check before implementation:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 3554 assertions, 1 failures
Failure: Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectZipLocalHeaderNamePolicy()
```

Final focused verification:

```text
php -l lanes/pandoc/src/ArchiveCompressionStream.php
No syntax errors detected in lanes/pandoc/src/ArchiveCompressionStream.php

php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php
No syntax errors detected in lanes/pandoc/tests/ArchiveCompressionStreamTest.php

php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php
No syntax errors detected in lanes/pandoc/examples/wordpress-archive-stream-preflight.php

php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok

php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
1 test files, 3661 assertions, 0 failures

php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test
wordpress-archive-stream-preflight self-test passed
```

Focused delta: `+1` PHP PASS case and `+107` focused assertions in `ArchiveCompressionStreamTest.php`. `lane-status.json` moves `phpPass` from `2157` to `2158`; `UPSTREAM_TEST_MANIFEST.json` moves the mapped denominator from `2581` to `2582`, archive stream core cases from `11` to `12`, and archive stream core assertions from `120` to `227`.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP ZIP local-header preflight in `ZipPackage` plus the existing bounded stream decoders in `ArchiveCompressionStream`, `GzipStream`, and `Lz4Frame`.

No Pandoc, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, tar/lz4 CLI, ZipArchive, external converter, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This slice does not repeat accepted archive extra-data, general-purpose flag, encrypted ZIP, split ZIP, ZIP64 EOCD/extra-field, central-directory provenance, descriptor integrity, gzip member, tar sparse/multi-volume/incremental/link/special-file, or LZ4 dictionary/skippable/block-size policy slices. It specifically owns the stream wrapper for already-bounded local-header name and flag mismatch diagnostics.

## Follow-Up

Keep ZIP unsupported central-directory/local-header policy wiring, tar PAX/GNU longlink decisions, LZ4 edge cases, and nested package preflights as separate archive/compression slices.
