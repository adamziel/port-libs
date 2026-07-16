# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260605T060427Z`

Base accepted HEAD: `2907bf38f9a3eb1f3184213b0a14b98edba6225e`

## Implementation

- Tightened `DeflateStream` so zlib-wrapped and raw DEFLATE streams must
  consume the complete byte stream.
- `inspectZlib()` and raw `decode()` now use an inflate context and reject
  trailing bytes after the first complete DEFLATE payload, including the zlib
  case where trailing bytes copy the original Adler-32 trailer.
- Updated the WordPress ZIP/package preflight smoke to report
  `deflate.trailingBytesPolicy=rejected` before archive packet bytes are handed
  to higher-level import code.

## Source Truth

This stays inside the accepted `pandoc-archive-compression-streams-*` support
row. PHP's convenience inflate helpers accept a valid compressed prefix and can
ignore later bytes; bounded package streams should fail closed instead of
treating a prefix as a trustworthy tar/review packet. The implementation keeps
DEFLATE decoding native PHP/zlib only and does not call Pandoc, Cabal, Haskell
runners, Word, LibreOffice, tar, zip/unzip, lz4, external template engines,
TeX/PDF engines, browser renderers, EPUBCheck, or online services.

## Evidence

- No current-base Pandoc rework note was present in
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Baseline:
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`
  - Result before edits: `1 test files, 212 assertions, 0 failures`.
- Regression target:
  - Direct PHP probe showed `gzinflate(gzdeflate("a") . "junk")` and
    `zlib_decode(zlib_encode("a", ZLIB_ENCODING_DEFLATE) . "junk")` return
    the valid prefix.
  - The new focused assertions cover the corresponding `DeflateStream` paths:
    a zlib stream followed by `review-garbage` plus a copied Adler-32 trailer,
    and a raw DEFLATE stream followed by `review-garbage`.
- After implementation:
  - `php -l lanes/pandoc/src/DeflateStream.php`: no syntax errors.
  - `php -l lanes/pandoc/tests/ArchiveCompressionStreamTest.php`: no syntax
    errors.
  - `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`: no
    syntax errors.
  - `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamTest.php`:
    `1 test files, 214 assertions, 0 failures`.
  - `php tools/run-tests.php lanes/pandoc/tests`: `20 test files, 7933
    assertions, 0 failures`; current worktree PASS-line count was `678`.
  - `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`:
    `zip package writer preflight self-test passed`.

Root harness not run - isolated micro-slice.

## Status Delta

- Focused archive coverage: `212 -> 214` assertions in
  `ArchiveCompressionStreamTest.php`.
- `phpPass`: no counter change claimed; this adds two assertions inside an
  existing malformed deflate PASS case rather than adding a new PASS case.
- WordPress example smoke now reports `deflate.trailingBytesPolicy=rejected`.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`DeflateStream`, `TarArchive`, and WordPress package preflight paths plus PHP
zlib inflate contexts already required by the archive stream helper. Full
upstream Pandoc runner parity remains blocked on hydrating/building the pinned
Haskell test executables, but this stream-integrity slice is covered by focused
native PHP tests.

## Non-Overlap

This does not repeat accepted gzip member framing, gzip extra subfield
validation, explicit or auto-detected archive dispatch, POSIX TAR file and
directory read/write paths, PAX path/size/owner/global metadata, GNU long-name
metadata, TAR end-marker validation, TAR drive-letter rejection, base-256
numeric decoding, TAR sparse-file rejection, zlib header/checksum validation,
independent/skippable/dependent LZ4 frame decoding, ZIP/OPC package
primitives, DOCX/ODT/EPUB readers, doctemplates, YAML metadata, CSL/BibTeX,
table geometry, math/TeX conversion, PDF handoff planning, legacy DOC/CFB,
charset, syntax highlighting, or Markdown/HTML reader and writer behavior.

## Follow-Up

Keep recursive nested archive policy, encrypted archive preflight, filesystem
extraction, compressed ZIP dispatch, multi-volume tar/zip handling, sparse-file
reconstruction, hardlink/symlink extraction policy, and non-deflate compression
methods as separate bounded slices unless concrete Pandoc package fixtures
require them.
