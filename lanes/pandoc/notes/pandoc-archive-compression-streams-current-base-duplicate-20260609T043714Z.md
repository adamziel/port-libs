# Pandoc archive compression streams current-base duplicate slice

Session: `port-dev-pandoc-archive-streams-20260609T043714Z`
Micro-slice: `pandoc-archive-compression-streams-current-base-duplicate-20260609T043714Z`
Base accepted HEAD: `07a72489fb26b6c1406952193d9f53ff0495c0b3`

## Behavior

- Added `ArchiveCompressionStream::inspectDeflateWrapperPolicy()` for zlib and raw-deflate TAR/ZIP package streams.
- Zlib policy now reports wrapper metadata needed before conversion handoff: header offset, payload offset/size, trailer offset/size, consumed bytes, Adler-32 value/hex, compression method, compression-level hint, and window size.
- Raw-deflate policy now keeps the stream metadata-only and reports `raw-deflate-wrapper-integrity-missing`, with `handoffPolicy=review-before-conversion`, because raw deflate has no wrapper checksum/trailer.
- Both policies intentionally avoid exposing decoded `archive`, `package`, `tarBytes`, `zipBytes`, or stream `data` fields.
- Updated the WordPress archive stream preflight smoke to print zlib wrapper policy/Adler-32 and raw-deflate review diagnostics.

## Focused Evidence

Baseline before edits:

```text
php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 4386 assertions, 0 failures
```

After implementation:

```text
php -l lanes/pandoc/src/ArchiveCompressionStream.php
No syntax errors detected in lanes/pandoc/src/ArchiveCompressionStream.php

php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php
No syntax errors detected in lanes/pandoc/tests/ArchiveCompressionStreamTest.php

php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php
No syntax errors detected in lanes/pandoc/examples/wordpress-archive-stream-preflight.php

php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 4449 assertions, 0 failures

php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test
wordpress-archive-stream-preflight self-test passed
```

Delta: +1 focused PHP PASS case, +63 focused assertions, mapped archive compression stream core cases 11 -> 12, mapped denominator 2710 -> 2711, `phpPass` 2310 -> 2311.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `DeflateStream`, `ArchiveCompressionStream`, `TarArchive`, `ZipPackage`, focused PHP runner, and lane-local WordPress smoke. No Pandoc, Cabal/Haskell runner, Word, LibreOffice, `zip`/`unzip`, `tar`, external LZ4 binary, external converter, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat the accepted gzip member/source-boundary policies, unsupported compression fingerprints, TAR sparse/multi-volume policy, LZ4 dictionary/content-size/block-size/frame-boundary policies, archive bomb thresholds, zlib preset dictionary policy, or package extraction behavior. It only adds wrapper-integrity handoff metadata for ordinary zlib and raw-deflate archive streams.

## Follow-Up

Possible next archive stream gaps: nested archive provenance limits, additional ZIP local-entry policy wrappers, or source-segment diagnostics that are not already covered by gzip member boundaries, unsupported compression fingerprints, sparse TAR policy, zlib/raw-deflate wrapper policy, LZ4 policies, or archive bomb thresholds.
