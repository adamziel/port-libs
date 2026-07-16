# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T002908Z`

Base accepted HEAD: `810d0706bf9e20b666c6562cd776779e2c68b0d5`

## Implementation

- Added `DeflateStream` as a bounded native PHP helper for raw DEFLATE and
  zlib-wrapped DEFLATE package fixture streams.
- `DeflateStream::build()` emits either raw DEFLATE payload bytes or zlib
  wrapper streams with caller-selected compression level.
- `DeflateStream::decode()` decodes raw or zlib-wrapped streams and enforces a
  caller-supplied uncompressed byte limit before exposing package bytes.
- `DeflateStream::inspectZlib()` validates zlib header check bits, DEFLATE
  method, window size, preset-dictionary policy, and Adler-32 trailer metadata.
- The WordPress ZIP/package preflight example now verifies zlib-wrapped and raw
  deflate tar review packets without invoking external archive tools.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Raw DEFLATE is already the compression method used by ZIP entries, while
zlib-wrapped DEFLATE is a common standalone stream wrapper around the same
payload contract. This slice ports only the bounded stream behavior needed by
package fixtures and import handoff packets; it does not implement a generic
archive extractor, filesystem extraction policy, ZIP64, sparse tar files,
encrypted archives, dependent-block LZ4, or dictionary-backed compression.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
office tool, `zip`, `unzip`, `tar`, `gzip`, `lz4`, external template engine,
TeX/PDF engine, browser renderer, online sanitizer, or online service was
executed.

## Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before implementation: `1 test files, 89 assertions, 2 failures`.
  - Failures: both new deflate cases failed with
    `Class "PortLibs\\Pandoc\\DeflateStream" not found`.
- After implementation:
  - `php -l lanes/pandoc/src/DeflateStream.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 110 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 203 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4643 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
  - `git diff --check -- lanes/pandoc`
  - Result: clean.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `463 -> 465`.
- `benchmarkDenominator.mapped`: `934 -> 936`.
- `archiveCompressionStreamCoreCases`: `10 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `101 -> 122`.
- Focused archive test file moved from the red-first `89 assertions / 2
  failures` state to `110 assertions / 0 failures`.

## Dependency Closure

No new external support component is needed. This adds the missing bounded
native `DeflateStream` support component inside the existing archive compression
row and reuses PHP zlib for raw DEFLATE/zlib encode-decode primitives while
keeping wrapper validation, metadata, limits, and policy checks in lane-local
PHP.

## Non-Overlap

This does not repeat accepted gzip member framing, POSIX tar regular
file/directory handling, PAX metadata, GNU long-name metadata, link/device
rejection, LZ4 frame parsing/writing, ZIP/OPC package primitives, XML/HTML5 DOM
helpers, DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table
geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset,
or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep ZIP64 policy, tar sparse files, hardlink/symlink extraction policy,
filesystem extraction policy, encrypted archive preflight, dependent-block LZ4
streams, dictionary-backed LZ4 frames, and preset-dictionary deflate streams as
separate bounded slices unless concrete Pandoc package fixtures require them.
