# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T014653Z`

Base accepted HEAD: `1a86b009041f206dcbfd3ee76c6da99bd9edeeb9`

## Behavior Added

- Extended `LegacyDocReader` CLX/PlcPcd parsing with bounded MS-DOC PCD flag
  preflight before extracted legacy Word text is exposed:
  - rejects PCD entries whose `fDirty` bit is set;
  - validates `fNoParaLast` by rejecting decoded text pieces that still contain
    a paragraph mark.
- Keeps undefined PCD bits ignored, preserving existing compressed single-byte
  and UTF-16LE text-piece decoding.
- Updated the WordPress legacy DOC handoff smoke so its fixture uses a selected
  `1Table` CLX piece table, marks the first piece as paragraph-free, and keeps
  SummaryInformation plus DocumentSummaryInformation metadata in a chained CFB
  directory-sector fixture.

## Source Truth

- Microsoft MS-DOC `Pcd` structure documents `fNoParaLast` as requiring a text
  piece to contain no paragraph mark, documents `fDirty` as a bit that must be
  zero, and defines the `fc` field as an `FcCompressed` text location:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-doc/498993c9-0a2d-47aa-8ada-fed27616e275`.
- The accepted lane source-truth record already maps CLX/PlcPcd extraction
  through `FibRgFcLcb97.fcClx/lcbClx` and `FcCompressed` pieces for bounded
  legacy DOC text handoff.

This slice is intentionally bounded to CLX piece-table PCD preflight and the
WordPress smoke fixture needed to exercise it. It does not implement Word
style/list tables, footnote/endnote PLCs, field-code extraction, image
extraction, embedded objects, macros, decryption/password policy, Word
automation, LibreOffice conversion, Pandoc execution, or upstream Haskell
runner parity.

## Verification

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 86 assertions, 0 failures`
- Red-first after adding the PCD expectations:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 91 assertions, 2 failures`
  - Expected failures: dirty PCD descriptors and `fNoParaLast` paragraph-mark
    mismatches were not rejected.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 91 assertions, 0 failures`
  - PASS lines: `18`
  - Delta: `+3` PASS lines / `+5` assertions over the prior focused legacy
    DOC/CFB test run.
- `php lanes/pandoc/examples/wordpress-legacy-doc-handoff.php --self-test`
  - Result: `legacy doc handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native CFB reader,
legacy DOC reader, Pandoc-like AST, Markdown writer, and WordPress block writer.
It does not invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice,
`zip`, `unzip`, external template engines, TeX/PDF engines, browser renderers,
roff, Typst, MathJax, KaTeX, or online services.

## Non-Overlap

This patch does not repeat accepted PDF engine fake-runner diagnostics, EPUB3,
BibTeX/CSL, CSL, YAML, doctemplate rendering, ZIP/OPC, archive compression,
DOCX/ODT, table geometry, math/TeX, charset/Unicode, XML/HTML5 DOM,
Markdown/HTML reader/writer, CFB FAT/MiniFAT parsing, CFB storage hierarchy
traversal, CFB directory ordering/red-black validation, OLE property metadata,
Word FIB encrypted-stream preflight, fExtChar Unicode direct text-range
decoding, or accepted basic CLX text extraction. It owns only bounded MS-DOC
PCD flag validation before CLX piece-table text handoff.

## Follow-Up

Keep legacy DOC style and list tables, footnote/endnote PLCs, revision-mark
format property inspection, field-code extraction, image extraction policy,
embedded-object handling, encrypted DOC password/decryption policy, broader CFB
directory-chain fixture coverage in shared helpers, and full upstream Pandoc
runner parity as separate bounded slices.
