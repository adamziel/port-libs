# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260604T205154Z`

Base accepted HEAD: `4e0b1d60470cefa557c9fb36a5e1720ad8dcfffc`

## Implementation

- Added `TarArchive` and `TarArchiveEntry` as bounded native PHP POSIX tar
  helpers for package fixture streams.
- `TarArchive::build()` emits ustar records for regular files/directories,
  writes PAX `path` metadata when a safe fixture path cannot fit ustar
  name/prefix fields, and terminates archives with zero records.
- `TarArchive::fromString()` validates 512-byte alignment, header checksums,
  ustar magic, payload bounds, duplicate names, an optional cumulative
  unpacked-byte limit, PAX records, and safe relative entry paths.
- Link, symlink, device, and unsupported tar entry types are rejected before
  exposing bytes to higher-level WordPress/Pandoc import handoffs.
- The WordPress ZIP/package preflight example now also verifies a gzip-wrapped
  tar review packet without invoking external `tar`, `gzip`, `zip`, or
  `unzip` tools.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. Tar file entries are a bounded archive stream primitive for package
fixtures and import handoff bundles; this slice ports the format contract for
regular files and directories rather than a general filesystem extractor. It
reuses the existing `GzipStream` wrapper for gzip-composed tar streams and does
not call Pandoc, Cabal, Haskell runners, office tools, external archive
binaries, TeX/PDF engines, browser renderers, or online services.

## Evidence

- `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/TarArchiveEntry.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 33 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 166 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `12 test files, 3,533 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `370 -> 374`.
- `benchmarkDenominator.mapped`: `827 -> 831`.
- `archiveCompressionStreamCoreCases`: `3 -> 7`.
- `archiveCompressionStreamCoreAssertions`: `35 -> 68`.

## Dependency Closure

No new support component is needed beyond the bounded `TarArchive` helper added
here. It composes with the existing `GzipStream` and keeps tar framing, PAX
path metadata, checksums, bounds, and unsafe-entry rejection in native PHP.

## Non-Overlap

This does not repeat accepted gzip member framing, ZIP central-directory
parsing/writing, ZIP extra-field parsing, local-header checks,
data-descriptor handling, OPC relationship/content-type parsing, DOCX/ODT
package readers, doctemplates, YAML metadata, CSL/citation handling, table
geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB, charset,
or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep LZ4 frames, ZIP64 policy, tar sparse files, hardlink/symlink extraction
policy, device nodes, ACL/xattr metadata, and filesystem extraction policy as
separate bounded slices unless concrete Pandoc fixtures require them.
