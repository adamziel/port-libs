# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260604T235146Z`

Base accepted HEAD: `edd43bb4e653c0e25aeb7cecaff3afca7bdfd8da`

## Behavior Added

- Extended `MathTexConverter` with bounded optional-degree `\sqrt[n]{...}`
  parsing.
- Emits MathML `mroot` for indexed roots while preserving the existing `msqrt`
  output for ordinary `\sqrt{...}`.
- Reuses the existing expression parser for root degrees, so numeric degrees,
  identifier degrees, and expression degrees such as `n+1` are represented in
  MathML without invoking TeX, MathJax, or KaTeX.
- Added malformed guards for empty and unclosed root-degree brackets.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress review
  smoke includes an OMML-style indexed root formula and verifies both the
  visible TeX handoff and the bounded MathML `mroot` output.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, `test/markdown-reader-more.txt`,
  and `test/markdown-reader-more.native`: Pandoc preserves inline/display math
  source as TeX strings in math nodes.
- The lane's `DocxReader` already maps DOCX OMML radicals with degrees into
  TeX source strings shaped as `\sqrt[degree]{body}`. This slice makes the
  bounded MathML handoff understand that existing native source shape.
- This is a bounded support-library contract. It does not attempt full
  `texmath` parity, TeX rendering, MathJax, KaTeX, Pandoc execution, TeX/PDF
  engines, or broader OMML conversion.

## Verification

- Baseline red check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: failed the new indexed-root case with
    `Expected TeX group at offset 5`.
- `php -l lanes/pandoc/src/MathTexConverter.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: no syntax errors.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - Result: no syntax errors.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 69 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+7` focused assertions from the
    pre-edit `62` assertion baseline.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `16 test files, 4,108 assertions, 0 failures`.
- `php tools/run-tests.php lanes/pandoc/tests | awk '/^PASS /{c++} END{print c+0}'`
  - Result: `416`.
- `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`,
`WordPressBlockWriter`, and DOCX OMML math handoff surfaces. Full upstream
Pandoc runner parity remains the existing Cabal/upstream-checkout blocker
recorded in lane status.

## Non-Overlap

- Does not repeat accepted Math/TeX fraction/root/script/text, source
  annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, or matrix/aligned
  environment conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, or upstream-runner dependency closure.

## Follow-Up

Keep binomial commands, cases/array environments, optional macro arguments,
richer MathML intent annotations, deeper TeX parsing, and full upstream runner
dependency planning as separate bounded slices.
