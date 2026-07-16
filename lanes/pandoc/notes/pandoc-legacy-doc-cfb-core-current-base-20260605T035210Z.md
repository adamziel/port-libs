# Pandoc Legacy DOC CFB Core Current Base

Slice: `pandoc-legacy-doc-cfb-core-current-base-20260605T035210Z`
Base accepted HEAD: `6d64cdd094e3b18966c99f1b9175eeb1c0e36714`

## Behavior Added

- Added bounded VBA macro-project preflight to `LegacyDocReader`.
- The reader now scans legacy DOC CFB streams under recognized MS-OVBA project
  roots such as `Macros` and `_VBA_PROJECT_CUR`.
- Each macro project report includes:
  - project storage path;
  - total stream count and byte size;
  - stream roles for `PROJECT`, `PROJECTwm`, `VBA/dir`, `VBA/_VBA_PROJECT`,
    VBA module streams, and private macro streams;
  - sorted module stream names;
  - `policy=macro-execution-disabled`, `canExecute=false`, and
    `canExposeBytes=false`.
- The document AST and `readBytes()` result now expose `macroProjects`, while
  metadata exposes `containsMacros`, `macroProjectCount`, and
  `macroPolicy=disabled-native-review`.
- Updated the WordPress legacy DOC handoff example so macro-enabled review
  packets are flagged without rendering or executing VBA module bytes.

## Source Truth

- Microsoft MS-OVBA Project Root Storage documents the project root as a
  storage containing VBA storage and a `PROJECT` stream:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-ovba/637d884f-1593-4456-9d2f-7378ba969c96`
- Microsoft MS-OVBA `dir` Stream documents compressed version-independent VBA
  project, reference, and module metadata:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-ovba/3d07f2c3-dee0-4ae3-b91f-3e32b789c534`
- Microsoft MS-OVBA `_VBA_PROJECT` Stream documents version-dependent VBA
  project information:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-ovba/ef7087ac-3974-4452-aab2-7dba2214d239`
- Microsoft MS-OVBA `PROJECT` Stream example shows project text records such
  as `Document=` and `Module=` entries:
  `https://learn.microsoft.com/en-us/openspecs/office_file_formats/ms-ovba/dfd72140-85a6-4f25-8a17-70a89c00db8c`

This slice intentionally reports macro project metadata and stream sizes only.
It does not decompress `VBA/dir`, extract VBA source, evaluate module code,
trust digital signatures, run Word or LibreOffice, decrypt documents, or run
the upstream Haskell runner.

## Verification

- Baseline before edits:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 145 assertions, 0 failures`
- Red-first after adding macro-project expectations:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 146 assertions, 1 failures`
  - Expected failure: `LegacyDocReader` did not expose `macroProjects` or
    macro policy metadata.
- After implementation:
  `php tools/run-tests.php lanes/pandoc/tests/LegacyDocReaderTest.php`
  - Result: `1 test files, 166 assertions, 0 failures`
  - PASS lines: `24`
  - Delta: `+1` PASS line / `+21` assertions over the prior focused legacy
    DOC/CFB test run.
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
field-code result handoff, or ObjectPool embedded OLE object preflight. It
owns only bounded macro-project stream preflight after the legacy DOC CFB has
already been parsed.

## Follow-Up

Keep full MS-OVBA `dir` decompression, module source extraction, macro digital
signature/trust decisions, WordBasic macro policy, footnote/endnote PLCs,
style/list tables, revision-mark format property inspection, image extraction,
encrypted DOC password/decryption policy, and full upstream Pandoc runner
parity as separate bounded slices.
