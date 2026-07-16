# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260604T150115Z`

Base accepted HEAD: `5d679f78d9a10cdeb692d4c84f641aab592cc862`

## Behavior Added

- Added a native `DocxReader::readPackage()` import report for higher-level
  WordPress DOCX handoff audits.
- The report reuses the accepted OPC relationship graph preflight and reachable
  closure traversal to expose:
  - document relationship part and relationship counts;
  - invalid reachable relationship issues such as missing package targets;
  - image relationship inventory for embedded and missing media targets;
  - embedded media byte counts and AST-used alt/title metadata.
- Updated the DOCX body WordPress smoke to expose the import report alongside
  rendered blocks, so attachment import preflight does not need to walk the AST
  or rerun OPC resolution.

## Source Truth

- Pandoc's DOCX reader treats DOCX as an OPC package, resolves the main
  `officeDocument` part, and follows document-level relationships for package
  assets before converting WordprocessingML to Pandoc AST nodes.
- This slice ports the bounded package-reporting contract needed by the PHP
  lane: report reachable relationship/media diagnostics at the DOCX reader
  boundary while preserving the accepted AST image behavior.
- This is not a full media extraction/export implementation. Missing image
  targets remain diagnostics instead of emitted broken image nodes.

## Verification

- Baseline before the patch:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 135 assertions, 0 failures`.
- Focused after the patch:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 168 assertions, 0 failures`.
  - Delta: +1 focused PASS line and +33 assertions.
- `php -l lanes/pandoc/src/DocxReader.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP
`ZipPackage`, OPC content-type parser, OPC relationship graph preflight,
reachable closure traversal, and DOCX body reader. It does not invoke Pandoc,
Cabal, Haskell test binaries, Word, LibreOffice, `zip`, `unzip`, external
template engines, TeX/PDF engines, browser renderers, or online services.

## Non-Overlap

This patch does not repeat accepted ZIP central-directory metadata, local
header validation, OPC content-type parsing, OPC relationship graph loading,
relationship target integrity preflight, reachable closure traversal,
doctemplate, YAML, Citation/CSL, Markdown reader/writer, HTML reader,
WordPress Markdown handoff, DOCX body/core-property parsing, DOCX
style/numbering handoff, DOCX table span handoff, DOCX comments/endnotes
mapping, ODT handoff, Math/TeX conversion, PDF engine handoff planning, or
legacy DOC/CFB extraction. It wires those accepted OPC/DOCX primitives into a
bounded DOCX import-report surface only.

## Follow-Up

Keep DOCX nested numbering, richer media extraction/export policy beyond
inventory diagnostics, comment range highlighting, tracked changes, field-code
interpretation, OMML/math, charts/diagrams, and full upstream Haskell runner
parity as separate bounded gates.
