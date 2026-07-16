# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T042026Z`
Base accepted HEAD: `a4eb702f7ee7d99c8c98d4d754371b79ebaa9e9b`

## Behavior Added

- Added bounded MS-CFB header preflight to `CompoundFileBinary`.
- The CFB reader now rejects unsupported major versions before FAT, MiniFAT,
  directory, or stream traversal.
- The CFB reader now rejects version 3 compound files whose header declares a
  nonzero Number of Directory Sectors.
- The WordPress legacy DOC handoff example now mutates both corrupt header
  forms and proves they are rejected before WordDocument stream lookup or block
  output.

## Source Truth

- Microsoft MS-CFB `Compound File Header` states the major version must be
  version 3 or version 4, and version 3 files must have Number of Directory
  Sectors set to zero:
  `https://learn.microsoft.com/en-us/openspecs/windows_protocols/ms-cfb/05060311-bfce-4b12-874d-71fd4ce63aea`

This slice intentionally validates only those two header invariants. It does
not implement CFB repair, header CLSID/reserved-byte validation, full DIFAT/FAT
integrity, directory tree balancing repair, Word automation, LibreOffice
fallbacks, encrypted DOC decryption, or upstream Haskell runner parity.

## Verification

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 166 assertions, 0 failures`
  - PASS lines: `24`
- Red-first after adding CFB header expectations:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 167 assertions, 1 failures`
  - Expected failure: unsupported CFB major version 5 was accepted.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 168 assertions, 0 failures`
  - PASS lines: `25`
  - Delta: `+1` PASS line / `+2` assertions over the prior focused legacy
    DOC/CFB run.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block
writer. It does not invoke Pandoc, Cabal, Haskell test binaries, Word,
LibreOffice, macro engines, `zip`, `unzip`, external template engines, TeX/PDF
engines, browser renderers, roff, Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted PDF engine fake-runner diagnostics, EPUB3,
BibTeX/CSL, CSL, YAML, doctemplate rendering, ZIP/OPC, archive compression,
DOCX/ODT, table geometry, math/TeX, charset/Unicode, XML/HTML5 DOM,
Markdown/HTML reader/writer, CFB FAT/MiniFAT parsing, CFB storage hierarchy
traversal, CFB directory ordering/red-black validation, standard and custom
OLE property metadata, Word FIB encrypted-stream preflight, fExtChar Unicode
direct text-range decoding, CLX text extraction, CLX PCD flag validation,
field-code result handoff, ObjectPool embedded OLE object preflight, or
MS-OVBA macro-project stream preflight. It owns only bounded CFB header
major-version and version-3 directory-sector-count rejection before stream
lookup.

## Follow-Up

Keep header CLSID/reserved-byte validation, mini stream cutoff validation,
DIFAT/FAT duplicate-sector preflight, directory color-depth balancing, style
and list-table parsing, footnote/endnote PLCs, revision-mark format property
inspection, image extraction, encrypted DOC password/decryption policy, full
MS-OVBA `dir` decompression, macro digital signature/trust decisions, and full
upstream Pandoc runner parity as separate bounded slices.
