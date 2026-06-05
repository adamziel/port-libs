# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T005824Z`

Base accepted HEAD: `41cae8b6fd1e5314059c74ad58c304aea88484db`

## Implementation

- Extended `Lz4Frame::frames()` and `Lz4Frame::decode()` to support bounded
  dependent-block LZ4 frames.
- Dependent frames now retain a rolling 64 KiB decoded block history so a
  compressed block can reference bytes from previous blocks in the same frame.
- Frame metadata now exposes `blockIndependent` so importer diagnostics can
  distinguish independent and dependent LZ4 streams.
- External dictionary-backed LZ4 frames remain rejected before bytes are
  exposed to WordPress or package readers.
- The WordPress ZIP/package preflight smoke now verifies a dependent-block LZ4
  review index without invoking `lz4`, `tar`, `gzip`, `zip`, `unzip`, Pandoc,
  or online services.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. LZ4 frame streams may omit the block-independence flag, in which case
later blocks can reference the previous 64 KiB of decoded content. The slice
ports that bounded stream contract for package/review fixtures only. It does
not add external dictionary support, filesystem extraction policy, encrypted
archives, ZIP64 materialization, tar sparse reconstruction, or broad archive
tooling.

No Pandoc, Cabal solver/build/test command, Haskell runner, Word, LibreOffice,
office tool, `zip`, `unzip`, `tar`, `gzip`, `lz4`, external template engine,
TeX/PDF engine, browser renderer, online sanitizer, or online service was
executed.

## Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before implementation: `1 test files, 110 assertions, 1 failures`.
  - Failure: the new dependent-block LZ4 case failed with
    `Dependent LZ4 frame blocks are not supported by the pandoc archive reader`.
- After implementation:
  - `php -l lanes/pandoc/src/Lz4Frame.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 117 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 4938 assertions, 0 failures`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `483 -> 484`.
- `benchmarkDenominator.mapped`: `956 -> 957`.
- `mappedArchiveCompressionStreamCoreCases`: corrected to the current focused
  archive shape, now `12`.
- Focused archive test coverage adds one new PASS case and seven assertions
  over the current accepted baseline.

## Dependency Closure

No new support component is needed. This reuses the existing native
`Lz4Frame` component and keeps dependent-block history handling in lane-local
PHP. External dictionary-backed LZ4 frames remain an explicit future slice
because they require an external dictionary selection policy that package
fixtures do not currently provide.

## Non-Overlap

This does not repeat accepted gzip member framing, POSIX tar file/directory
handling, PAX metadata, GNU long-name metadata, raw/zlib DEFLATE support,
independent LZ4 frame parsing/writing, skippable LZ4 metadata, ZIP/OPC package
primitives, XML/HTML5 DOM helpers, DOCX/ODT/EPUB readers, doctemplates, YAML
metadata, CSL/BibTeX, table geometry, math/TeX conversion, PDF handoff
planning, legacy DOC/CFB, charset, or Markdown/HTML reader and writer
behavior.

## Follow-Up

Keep dictionary-backed LZ4 frames, tar sparse files, hardlink/symlink
extraction policy, filesystem extraction policy, encrypted archive preflight,
ZIP64 materialization policy, and broader archive extraction as separate
bounded slices unless concrete Pandoc package fixtures require them.
