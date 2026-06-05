# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T015356Z`

Base accepted HEAD: `d08cac00333b4576903cf5223e57290c6b98686a`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX binomial command handoff for
  `\binom`, `\tbinom`, and `\dbinom`.
- Emits binomials as fenced MathML zero-line fractions using `mfrac
  linethickness="0"`, with `\tbinom` and `\dbinom` wrapped in text/display
  `mstyle` hints.
- Preserves escaped source TeX annotations in the surrounding MathML
  `semantics` block.
- Rejects missing or empty binomial numerator/denominator groups without
  invoking Pandoc, texmath, MathJax, KaTeX, or a TeX engine.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress review
  smoke preserves editable binomial TeX source and emits matching bounded
  MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, `test/markdown-reader-more.txt`,
  and `test/markdown-reader-more.native`: Pandoc preserves inline/display math
  source as TeX strings in math nodes.
- Prior math notes explicitly left binomial commands as follow-up after
  fraction/root/script, matrix/aligned/cases/array, and above/below/style
  handoff. This slice ports that bounded support-library contract only.
- This does not attempt full `texmath` parity, TeX rendering, MathJax, KaTeX,
  Pandoc execution, TeX/PDF engines, infix `\choose`/`\atop`, generalized
  `\genfrac`, color/phantom/cancel commands, optional macro arguments, or full
  MathML intent annotations.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 94 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 89 assertions, 2 failures`.
  - Failure reason: `\binom`, `\tbinom`, and `\dbinom` were emitted as literal
    identifiers, and malformed binomial groups were accepted.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 102 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+8` focused assertions.
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5418 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

## Non-Overlap

- Does not repeat accepted Math/TeX fraction/root/script/text, source
  annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root,
  matrix/aligned environment, cases environment, array column-spec, or
  above/below/style wrapper conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep generalized fractions, infix `\choose`/`\atop`, color/phantom/cancel
policy, optional macro arguments, richer MathML intent/accessibility
annotations, deeper TeX parsing, and full upstream runner dependency planning
as separate bounded slices.
