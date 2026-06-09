# Pandoc Archive Compression Streams Current Base Duplicate

Slice: `pandoc-archive-compression-streams-current-base-duplicate-20260609T060703Z`

Base accepted HEAD: `aea0bbc5620fdf1b622909ec6e5a23e6c3713930`

## Implementation

- Added `ArchiveCompressionStream::inspectZipDuplicateEntryNamePolicy()` for
  duplicate ZIP central-directory entry-name preflight across plain ZIP,
  gzip-wrapped ZIP, zlib-wrapped ZIP, raw-deflate ZIP, and LZ4-framed ZIP
  streams.
- The policy decodes only through the bounded native stream helpers and then
  reuses `ZipPackage::centralDirectoryInventoryPreflight()` so duplicate names
  are reviewable without instantiating the rejected `ZipPackage`.
- Extended `wordpress-archive-stream-preflight.php --self-test` with a
  compressed duplicate-name ZIP packet so WordPress import queues can surface
  spoofed duplicate media entries before attachment bytes are accepted.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. ZIP central directories are the authoritative package inventory for DOCX,
EPUB, ODT, and other Pandoc package fixtures; duplicate central-directory names
are ambiguous and already rejected by the bounded `ZipPackage` reader. This
slice ports the importer-facing compressed-stream policy handoff only. It does
not implement external unzip behavior, ZIP repair, encrypted archives, ZIP64
materialization, or a generic archive ecosystem.

## Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before implementation: `1 test files, 5182 assertions, 1 failures`.
  - Failure: `Call to undefined method PortLibs\Pandoc\ArchiveCompressionStream::inspectZipDuplicateEntryNamePolicy()`.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result after implementation: `1 test files, 5332 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-archive-stream-preflight.php --self-test`
  - Result: `wordpress-archive-stream-preflight self-test passed`.
- `php -l lanes/pandoc/src/ArchiveCompressionStream.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/ArchiveCompressionStream.php`.
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/ArchiveCompressionStreamTest.php`.
- `php -l lanes/pandoc/examples/wordpress-archive-stream-preflight.php`
  - Result: `No syntax errors detected in lanes/pandoc/examples/wordpress-archive-stream-preflight.php`.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no whitespace errors.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2420 -> 2421`.
- `benchmarkDenominator.mapped`: `2809 -> 2810`.
- `archiveCompressionStreamCoreCases`: `11 -> 12`.
- `archiveCompressionStreamCoreAssertions`: `120 -> 270`.
- Focused assertion growth: `+150` in
  `ArchiveCompressionStreamTest.php`.

## Dependency Closure

No new support component is needed. This reuses native PHP
`ZipPackage::centralDirectoryInventoryPreflight()`, gzip/zlib/raw-deflate/LZ4
stream decoding, and archive stream inspection support. Full upstream Pandoc
runner parity remains a separate upstream-runner dependency task requiring a
hydrated Pandoc checkout and Haskell test executables.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip member package
boundaries, source-name policies, ZIP central-directory signature provenance,
split ZIP disk markers, ZIP extra field policies, ZIP encryption policy, ZIP
comments/attributes, TAR duplicate PAX keyword policy, TAR case-insensitive
name collisions, LZ4 content-size/source-boundary policies, OPC relationships,
DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table
geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset,
or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep TAR duplicate-entry metadata preflight, compressed ZIP raw-name collision
surfacing, ZIP duplicate local-header offset stream policy, and archive stream
ambiguity diagnostics as separate bounded slices.
