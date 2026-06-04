# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260604T232831Z`

Base accepted HEAD: `4e5b254a36b80b692f93413b376a79f6d854dcc7`

## Implementation

- Extended `TarArchive::fromString()` with bounded GNU tar long-name metadata
  handling for regular file and directory package-fixture entries.
- Type `L` metadata records are now parsed as a safe relative path for only the
  next real archive entry; PAX `path` metadata remains higher priority.
- Unsafe GNU long-name paths and dangling long-name metadata are rejected before
  any bytes are exposed to higher-level package or WordPress import handoffs.
- The WordPress ZIP/package preflight example now verifies a GNU long-name tar
  packet path without invoking `tar`, `gzip`, `zip`, `unzip`, or `lz4`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. GNU long-name records are a bounded tar metadata compatibility path for
package fixtures emitted by common tar writers. This ports the archive format
contract for safe regular file and directory entries only; it does not add
filesystem extraction, sparse-file reconstruction, link extraction, device node
handling, encrypted archive support, or broad tar implementation scope.

## Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before implementation: `1 test files, 68 assertions, 1 failures`.
  - Failure: `Unsafe TAR entry name: ././@LongLink`.
- `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 77 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `2 test files, 243 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `14 test files, 3,744 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `393 -> 394`.
- `benchmarkDenominator.mapped`: `850 -> 851`.
- `archiveCompressionStreamCoreCases`: `10 -> 11`.
- `archiveCompressionStreamCoreAssertions`: `101 -> 112`.

## Dependency Closure

No new support component is needed. This reuses the existing native
`TarArchive` helper and keeps GNU long-name parsing in the same bounded archive
stream support component. External archive tools and full Pandoc/Cabal runner
parity remain out of scope for this slice.

## Non-Overlap

This does not repeat accepted gzip member framing, POSIX tar regular
file/directory handling, PAX long-path metadata, link/device rejection, LZ4
frame parsing/writing, ZIP/OPC package primitives, XML/HTML5 DOM helpers,
DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX, table
geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset,
or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep tar sparse files, hardlink/symlink extraction policy, encrypted archive
preflight, filesystem extraction policy, ZIP64 policy, dependent-block LZ4
streams, and dictionary-backed LZ4 frames as separate bounded slices unless
concrete Pandoc package fixtures require them.
