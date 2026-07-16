# Pandoc Archive Compression Streams Current Base

Slice: `pandoc-archive-compression-streams-current-base-20260604T011543Z`

Base accepted HEAD: `59ad35343f0b979589ac3a508925c996eae4a547`

## Implementation

- Added `ArchiveCompressionStreams` as a bounded native PHP support component
  for compressed source-packet fixtures:
  - parses one or more gzip members, including optional extra/name/comment
    header fields and FHCRC validation;
  - validates gzip trailer CRC32 and ISIZE before exposing decompressed bytes;
  - decodes concatenated raw-DEFLATE gzip members in order via PHP zlib;
  - parses tar entries with checksum validation, safe relative paths,
    directory/file type handling, and USTAR prefix name reconstruction;
  - rejects empty, truncated, unsafe, unsupported, or trailing-garbage tar/gzip
    streams before WordPress import handoff code sees entry bytes.
- Added `wordpress-archive-compression-preflight.php` to exercise a compressed
  WordPress migration packet containing Markdown body and media sidecar entries
  without invoking external `tar`, `gzip`, `zip`, `unzip`, Pandoc, or office
  tooling.

## Source Truth

The lane has no hydrated Pandoc upstream checkout in the local cache, so this
slice stays on format-contract support needed by Pandoc-style data/package
fixtures. Gzip member structure, raw DEFLATE payloads, gzip CRC/ISIZE trailers,
and POSIX tar checksum/path/type handling are the bounded support behavior
covered here. This is not an EPUB/DOCX ZIP extension and does not duplicate the
accepted shared ZIP package extra-field slice.

## Verification

- `php -l lanes/pandoc/src/ArchiveCompressionStreams.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ArchiveCompressionStreamsTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-archive-compression-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ArchiveCompressionStreamsTest.php`
  - Result: `1 test files, 47 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-archive-compression-preflight.php --self-test`
  - Result: `archive compression preflight self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `8 test files, 2885 assertions, 0 failures`.
- `git diff --check -- lanes/pandoc`
  - Result: passed.

Root harness not run - isolated micro-slice.

## Dependency Closure

This activates the existing `archive-compression-streams` support row using
native PHP plus the already-available zlib extension for raw DEFLATE. No new
external dependency is required. LZ4, tar PAX/GNU longlink metadata, symlink
policy, and full upstream Haskell runner dependency closure remain separate
gates until a concrete Pandoc fixture requires them.

## Non-Overlap

This does not repeat accepted ZIP central-directory parsing, ZIP writing, ZIP
extra fields, local-header/data-descriptor validation, OPC content-types/
relationships XML, OPC relationship graph preflight, DOCX body parsing,
doctemplates, YAML metadata, CSL/citations, math/TeX conversion, or
Markdown/HTML reader and writer behavior. The new coverage is limited to
gzip/tar compressed stream handling for package fixtures.

## Follow-Up

If a later fixture requires it, add a separate bounded slice for tar PAX/GNU
longlink headers or LZ4 frame/block decoding. Keep archive symlinks rejected
until a source-path policy is explicitly designed for WordPress import safety.
