# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260604T232026Z`

Base accepted HEAD: `dfccfd252d4ec7968da59da8d0cbc92468a86823`

## Behavior Added

- Extended `MathTexConverter` with bounded raw TeX macro extraction from AST
  `raw_tex` nodes produced by the Markdown reader.
- Added bounded brace-argument macro expansion before MathML presentation
  conversion, while keeping the original macro invocation in the
  `application/x-tex` source annotation.
- Added macro-definition preflight for unsupported names, missing templates,
  and out-of-bounds arities.
- Updated the WordPress math handoff smoke to prove raw macro definitions can
  drive MathML handoff without leaving an unresolved macro token.

## Source Truth

The accepted Pandoc inventory maps `test/markdown-reader-more.txt` and
`test/markdown-reader-more.native` lines for a raw `newcommand` block followed
by math using that macro. Pandoc preserves the raw macro block and expands the
math source to the corresponding TeX. This slice ports the bounded support
library handoff for macro-aware MathML conversion. It does not attempt full
`texmath` parity, optional macro arguments, TeX rendering, MathJax, KaTeX,
Pandoc execution, or TeX/PDF engines.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 54 assertions, 0 failures`
- `php -l lanes/pandoc/src/MathTexConverter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 62 assertions, 0 failures`
  - Delta: `+8` focused assertions and `+2` focused PASS lines.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

## Non-Overlap

- Does not repeat accepted Math/TeX fraction/root/script/text, source
  annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, or matrix/aligned environment conversion.
- Does not touch DOCX OMML, ODT formulas, PDF engine handoff, OPC, archive
  compression, citations, YAML, doctemplates, tables, legacy DOC/CFB, XML/HTML5
  DOM, or upstream-runner dependency closure.

## Follow-Up

Keep optional-argument macros, broader TeX macro bodies, richer MathML intent
annotations, deeper TeX parsing, DOCX OMML extraction, and full upstream runner
dependency planning as separate bounded slices.
