# Pandoc Shared ZIP Package Core Current Base

Slice: `pandoc-shared-zip-package-core-current-base-20260605T000554Z`

Base accepted HEAD: `d93839bf1059e9e384bbc118a734c74a08e4f5ec`

## Implementation

- Added bounded ZIP filename metadata decoding for Pandoc DOCX/EPUB/ODT-style
  package containers.
- `ZipPackage` now decodes entry names and central comments as UTF-8 when the
  language encoding flag is set, and as IBM Code Page 437 when it is absent.
- Added CRC-validated Info-ZIP Unicode Path `0x7075` and Unicode Comment
  `0x6375` extra-field handling before decoded names/comments are exposed.
- `ZipPackageEntry` now preserves `rawName`, `rawComment`, `nameEncoding`, and
  `commentEncoding` so WordPress import preflight can audit legacy package
  bytes while using decoded media paths.
- Local header checks remain byte-for-byte against the raw central-directory
  name, and local Unicode Path extras are rejected when they conflict with the
  central decoded path.
- Updated the WordPress ZIP package preflight example to self-test a legacy raw
  media path whose reviewer-facing import path and comment come from Unicode
  extra fields.

## Source Truth

This stays inside the shared ZIP package support row for richer Pandoc
conversion. ZIP package filenames/comments use the general-purpose UTF-8 flag;
when that flag is absent, the classic ZIP contract falls back to IBM Code Page
437. Info-ZIP Unicode Path and Comment extras carry a version byte, CRC32 of
the raw path/comment bytes, and UTF-8 replacement text. The slice ports that
bounded package contract only, without invoking Pandoc, Haskell runners, Word,
LibreOffice, `zip`, `unzip`, TeX/PDF engines, browser renderers, or online
services.

## Verification

- Red-first `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 185 assertions, 3 failures`.
- `php -l lanes/pandoc/src/ZipPackage.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/src/ZipPackageEntry.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/ZipPackageTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-zip-package-preflight.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php`
  - Result: `1 test files, 203 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-zip-package-preflight.php --self-test`
  - Result: `zip package writer preflight self-test passed`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `18 test files, 4,346 assertions, 0 failures`, `438` PASS lines.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the native PHP `ZipPackage`
reader/writer, `ZipPackageEntry` extra-field parser, in-process `crc32`, and a
small internal CP437 decoder so the lane does not depend on external ZIP tools
or fragile `iconv`/`mbstring` aliases for package filenames.

## Non-Overlap

This does not repeat accepted central-directory parsing, data-descriptor
handling, package writing, extended timestamp parsing, NTFS timestamp parsing,
Unix symlink rejection, OPC content-types or relationship graph behavior,
archive compression stream handling, DOCX/ODT/EPUB body parsing, PDF engine
handoff diagnostics, syntax highlighting, doctemplate rendering, YAML metadata,
CSL/BibTeX, table geometry, math/TeX, charset/Unicode text helpers, or legacy
DOC/CFB slices. It only adds ZIP filename/comment charset and Unicode
extra-field package metadata behavior.

## Follow-Up

Keep ZIP64, AES/encrypted archives, central-directory encryption, non-deflate
compression methods, executable permission policy, and broader malformed
Unicode extra-field corpus coverage as separate bounded ZIP package slices.
