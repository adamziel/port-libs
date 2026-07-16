# Archive Compression Streams Current Base - ZIP Unicode Extra Fields

Base accepted HEAD: `75e61bcf0bd749a29b9d57093a23d6f3b6828b00`

Micro-slice: `pandoc-archive-compression-streams-current-base-20260609T043141Z`

## Behavior

This slice adds `ArchiveCompressionStream::inspectZipUnicodeExtraFieldPolicy()`, a bounded stream wrapper around the existing native `ZipPackage::unicodeExtraFieldPolicyPreflight()` support. The wrapper decodes the assigned ZIP carrier, preserves the decoded `zipBytes`, reports `packageByteSize`, and attaches stream provenance without exposing a `ZipPackage` object.

Focused coverage now exercises the same ZIP Unicode path/comment extra-field policy across:

- plain ZIP bytes;
- gzip-wrapped ZIP;
- zlib-wrapped ZIP;
- raw-deflate ZIP;
- LZ4-framed ZIP with a skippable reviewer metadata frame.

The tests verify Info-ZIP Unicode path `0x7075` and Unicode comment `0x6375` handling, CP437 fallback name metadata, matching central/local Unicode path entries, stream provenance, and the unsupported missing-local-path policy before package exposure.

The WordPress archive stream preflight example now includes a gzip ZIP fixture with Unicode path/comment extra fields and validates the metadata-only review handoff in `--self-test`.

## Verification

Baseline before this patch:

- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- Result: `1 test files, 4340 assertions, 0 failures`

Focused verification after this patch:

- `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
- Result: no syntax errors
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- Result: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
- Result: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
- Result: `1 test files, 4448 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
- Result: `wordpress-archive-stream-preflight self-test passed`

Focused delta: `+1` PHP PASS line and `+108` assertions.

Root harness status: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses native PHP `ArchiveCompressionStream`, `ZipPackage::unicodeExtraFieldPolicyPreflight()`, `GzipStream`, `DeflateStream`, `Lz4Frame`, focused PHP tests, and the existing WordPress archive stream preflight example. No Pandoc, Cabal/Haskell runner, tar, gzip, lz4, zip/unzip, ZipArchive, office tool, TeX/PDF engine, browser renderer, external validator, online service, live provider test, or live-service provider test was run.

## Non-Overlap

This does not repeat accepted archive stream coverage for ZIP data descriptors, ZIP64 EOCD, ZIP64 extra fields, central-directory signatures, split ZIP markers, archive extra data records, local-header mismatch/span/order policy, general-purpose flags, unsupported compression methods, LZ4 source boundaries, TAR sparse files, or nested archive expansion-ratio checks. The owned behavior is the Unicode path/comment extra-field policy surfaced through compressed ZIP carriers before package exposure.

## Follow-Up

Reasonable next archive-stream work is a distinct wrapper for ZIP external attributes/creator-host policy across compressed carriers, raw strict import policy across compressed package streams, or additional decoded-source byte provenance for package fixtures.
