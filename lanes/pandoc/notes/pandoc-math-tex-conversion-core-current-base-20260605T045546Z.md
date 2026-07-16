# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T045546Z`

Base accepted HEAD: `d10c41aa298504539e2d705554266ce62d95aed3`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX math alphabet variant commands:
  `\mathrm`, `\mathbf`, `\mathit`, `\mathsf`, `\mathtt`, `\mathcal`,
  `\mathbb`, `\mathfrak`, `\mathscr`, and `\boldsymbol`.
- Renders these commands as MathML `mstyle mathvariant="..."` wrappers while
  preserving the source TeX in the existing `application/x-tex` annotation.
- Supports grouped arguments such as `\mathbf{v_i}` and single-token arguments
  such as `\mathbb R`.
- Keeps scripts attached through the existing parser, including
  `\mathcal{F}_n` and `\boldsymbol{\alpha}_i`.
- Rejects missing, empty, or script-marker-only variant arguments without
  invoking Pandoc, texmath, MathJax, KaTeX, TeX engines, or online services.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review
  packets keep editable math alphabet source and verify matching bounded
  MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- This slice ports only a bounded support-library contract for common TeX math
  alphabet handoff into MathML presentation metadata. It does not attempt full
  texmath parity, Unicode mathematical alphanumeric codepoint rewriting,
  xcolor model conversion, MathML intent annotations, optional macro
  arguments, or renderer execution.
- The local upstream Pandoc checkout remains absent under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream runner was
  executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 165 assertions, 0 failures`.
- Red check after adding focused coverage and the example expectation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 168 assertions, 2 failures`.
  - Failure reason: math alphabet commands were emitted as literal TeX command
    identifiers, and malformed variant inputs were not rejected.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 178 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+13` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MathTexConverter.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - Result: no syntax errors.
- JSON validation:
  `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both JSON files parsed successfully.
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

## Status Delta

- `phpPass`: `634` -> `635`.
- `benchmarkDenominator.mapped`: `1109` -> `1110`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `67`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, explicit-delimiter infix fractions, roots, scripts,
  source annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root, matrix/
  aligned environment, cases environment, array column-spec, above/below/style
  wrapper, binomial command, color, phantom, `\cancel`, `\bcancel`, `\xcancel`,
  or `\cancelto` conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep `\substack`, AMS align/gather/split environments, spacing commands,
Unicode mathematical alphanumeric codepoint rewriting, MathML intent and
accessibility annotations, optional macro arguments, deeper TeX parsing, and
full upstream runner dependency planning as separate bounded slices.
