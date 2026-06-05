# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260604T235851Z`

Base accepted HEAD: `43d6c6085912b0a2e7f68f49d9869c535f444985`

## Implementation

- Extended `TarArchive::fromString()` with bounded POSIX PAX metadata overrides
  for regular package-fixture entries.
- PAX `size` now defines the following entry payload length before bytes are
  exposed, which covers tar packets whose ustar header carries a placeholder
  size while PAX carries the real stream size.
- PAX `mtime`, `uid`, `gid`, `uname`, and `gname` now override the following
  entry metadata. Fractional `mtime` is floored to the existing integer import
  timestamp shape.
- Invalid PAX non-negative integer fields are rejected before the next entry is
  returned to package readers.
- The WordPress ZIP/package preflight example now verifies a PAX-backed tar
  review packet with document bytes and owner metadata without invoking `tar`,
  `gzip`, `zip`, `unzip`, or `lz4`.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. POSIX PAX extended headers are the tar stream compatibility layer used by
common archive writers when path, size, timestamp, or owner metadata cannot be
represented directly in the ustar header. This slice ports the bounded package
fixture contract for safe regular file entries only; it does not implement
filesystem extraction, sparse-file reconstruction, hardlink/symlink materialize
policy, device nodes, encrypted archives, or a generic tar ecosystem.

## Evidence

- Red-first command:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before implementation: `1 test files, 77 assertions, 2 failures`.
  - Failures: PAX size was ignored so payload bytes were parsed as a TAR header,
    and invalid PAX size metadata did not throw.
- `php -l lanes/pandoc/src/TarArchive.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result: `1 test files, 89 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `2 test files, 273 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `17 test files, 4222 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'`
  - Result: both JSON files valid.
- `git diff --check -- lanes/pandoc`
  - Result: clean.

Root harness not run - isolated micro-slice.

## Status Delta

- `phpPass`: `431 -> 432`.
- `benchmarkDenominator.mapped`: `899 -> 900`.
- `archiveCompressionStreamCoreCases`: `10 -> 11`.
- `archiveCompressionStreamCoreAssertions`: `101 -> 113`.

## Dependency Closure

No new support component is needed. This reuses the existing native
`TarArchive` helper and keeps PAX metadata handling in the bounded archive
stream support component. External archive tools and full Pandoc/Cabal runner
parity remain out of scope for this slice.

## Non-Overlap

This does not repeat accepted gzip member framing, POSIX tar file/directory
handling, PAX long-path metadata, GNU long-name metadata, link/device
rejection, LZ4 frame parsing/writing, ZIP/OPC package primitives, XML/HTML5
DOM helpers, DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX,
table geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB,
charset, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep tar sparse files, hardlink/symlink extraction policy, filesystem
extraction policy, ZIP64 policy, dependent-block LZ4 streams, and
dictionary-backed LZ4 frames as separate bounded slices unless concrete Pandoc
package fixtures require them.
