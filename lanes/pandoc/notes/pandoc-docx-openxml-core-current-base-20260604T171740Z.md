# Pandoc DOCX OpenXML Core Current Base

Slice: `pandoc-docx-openxml-core-current-base-20260604T171740Z`

Base accepted HEAD: `6eb936c5de656c80d0d4409622e8c33b18f3a6ff`

## Behavior Added

- Added bounded native DOCX OMML handling in `DocxReader`.
- `m:oMath` now maps to existing inline `math` AST nodes with
  `sourceFormat=docx-omml`.
- `m:oMathPara` now maps to display `math` AST nodes.
- The bounded TeX handoff covers OMML math runs/text, subscript, superscript,
  subscript+superscript, fractions, and radicals.
- The DOCX WordPress body smoke now proves imported Word formulas render as
  WordPress math spans without Word, LibreOffice, Pandoc, MathJax, KaTeX, or a
  TeX engine.

## Source Truth

- DOCX math is stored as Office Math Markup Language under the standard
  `http://schemas.openxmlformats.org/officeDocument/2006/math` namespace.
- Pandoc's DOCX reader imports Office formulas as Pandoc math inlines rather
  than dropping them as plain WordprocessingML text.
- This slice ports only the bounded handoff contract needed by the PHP lane:
  preserve common formula source in the existing math AST/writer path.
- This is not full OMML parity. Matrices, equation arrays, functions, accents,
  n-ary operators, delimiters, equation numbering, and accessibility
  annotations remain separate gates.

## Verification

- Baseline before the patch:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 168 assertions, 0 failures`.
- Red-first check:
  - New OMML test initially failed because XML formatting whitespace leaked
    into the TeX handoff.
- Focused after the patch:
  - `php tools/run-tests.php lanes/pandoc/tests/DocxReaderTest.php`
  - Result: `1 test files, 183 assertions, 0 failures`.
  - Delta: +1 focused PASS line and +15 assertions.
- Focused lane suite:
  - `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `11 test files, 3292 assertions, 0 failures`.
- Syntax checks:
  - `php -l lanes/pandoc/src/DocxReader.php`
  - `php -l lanes/pandoc/tests/DocxReaderTest.php`
  - `php -l lanes/pandoc/examples/wordpress-docx-body-handoff.php`
  - Result: no syntax errors.
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-docx-body-handoff.php --self-test`
  - Result: `docx body handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the accepted native PHP DOCX
package reader plus existing Markdown and WordPress math writers. It does not
invoke Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, `zip`, `unzip`,
external template engines, TeX/PDF engines, MathJax, KaTeX, Typst, browser
renderers, roff, or online services.

## Non-Overlap

This patch does not repeat accepted ZIP/OPC package parsing, OPC relationship
preflight/closure, DOCX body/core properties, DOCX styles/numbering, DOCX table
spans, DOCX comments/endnotes, DOCX media import reports, ODT/legacy DOC/CFB,
YAML, doctemplate, CSL, Markdown/HTML reader/writer, table geometry, math/TeX
conversion internals, PDF handoff planning, or upstream-runner dependency audit
work. It only wires bounded DOCX OMML formulas into the already accepted math
AST and writers.

## Follow-Up

Keep DOCX nested numbering, comment range highlighting, tracked changes,
field-code interpretation, richer media extraction/export policy, charts,
diagrams, full OMML matrix/array/function/accent/n-ary/delimiter support, and
full upstream Haskell runner parity as separate bounded gates.
