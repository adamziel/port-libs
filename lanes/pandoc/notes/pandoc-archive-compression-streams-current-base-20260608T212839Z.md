# Pandoc Archive Compression Streams Current Base

Micro-slice: `pandoc-archive-compression-streams-current-base-20260608T212839Z`

Accepted base: `9fca7a8f155d1a30d46db28e808e4b225a69a919`

## Summary

This slice tightens the bounded native LZ4 archive support so caller-supplied external dictionaries must be non-empty byte strings before dictionary-ID frames can decode. The guard now exists in both direct `Lz4Frame::decodeWithDictionaries()` normalization and the `ArchiveCompressionStream::inspectPackageStreamWithLz4Dictionaries()` package-inspection path.

The previous behavior could satisfy an LZ4 Dict-ID frame with an empty dictionary when the frame happened to contain uncompressed blocks. That was inconsistent with the existing zlib dictionary guard and too permissive for package fixture preflight.

## Non-Overlap

This is limited to LZ4 external-dictionary input validation. It does not repeat the existing gzip provenance, PAX timestamp/hdrcharset, supplied LZ4 dictionary decode, TAR sparse-map, ZIP package, ODT, DOCX, or legacy DOC/CFB support slices.

No Pandoc, Cabal solver/build/test command, Haskell runner, tar, gzip, zip/unzip, lz4 CLI, ZipArchive, Word, LibreOffice, external archive tool, online service, live provider test, or live-service provider test was executed.

## Red-First Evidence

After adding empty-dictionary assertions, the focused test failed before the implementation change:

`php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`

Result: `1 test files, 2741 assertions, 1 failures`

Failure: `Expected exception RuntimeException was not thrown`

## Final Verification

`php -l lanes/pandoc/src/Lz4Frame.php`

Result: no syntax errors.

`php -l lanes/pandoc/src/ArchiveCompressionStream.php`

Result: no syntax errors.

`php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`

Result: no syntax errors.

`php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`

Result: no syntax errors.

`php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`

Result: `1 test files, 2744 assertions, 0 failures`

`php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`

Result: `wordpress-archive-stream-preflight self-test passed`

`php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'`

Result: `json ok`

`git diff --check -- lanes/pandoc`

Result: no whitespace errors.

Root harness: not run - isolated micro-slice.

## Status Delta

`lanes/pandoc/lane-status.json` now records `phpPass` as `1869`, reflecting one additional mapped native archive-compression support case.

`lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` now records:

- `benchmarkDenominator.mapped`: `2296`
- `inventory.archiveCompressionStreamCoreCases`: `12`
- `inventory.mappedArchiveCompressionStreamCoreCases`: `12`
- `inventory.archiveCompressionStreamCoreAssertions`: `123`
- `inventory.mappedArchiveCompressionStreamEmptyDictionaryCases`: `1`
- `inventory.archiveCompressionStreamEmptyDictionaryAssertions`: `3`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP `Lz4Frame`, `ArchiveCompressionStream`, `TarArchive`, and the existing WordPress archive stream preflight example. Full upstream Pandoc/Haskell runner parity and external archive-tool parity remain outside this isolated slice.

## Follow-Up

A non-overlapping archive-compression follow-up could cover stricter LZ4 block-size boundaries, TAR package checksum provenance, gzip member limit diagnostics, or LZ4 skippable-frame metadata.
