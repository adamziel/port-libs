# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T025233Z`

Base accepted HEAD: `04b66f6a0b9626c46b7280591152936e542cf1e1`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX infix fraction handoff.
- Supports `\over`, `\atop`, `\choose`, `\brack`, and `\brace` inside the
  current expression or group, splitting already-parsed numerator nodes from
  the remaining denominator expression.
- Emits regular `mfrac` for `\over`, zero-line `mfrac` for `\atop`, and
  TeX-style fenced zero-line fractions for `\choose`, `\brack`, and `\brace`.
- Preserves escaped source TeX annotations in the surrounding MathML
  `semantics` block.
- Rejects missing numerator or denominator content without invoking Pandoc,
  texmath, MathJax, KaTeX, a TeX engine, or any online service.
- Updated the WordPress math handoff example so editable reviewer formulas
  preserve infix fraction source while exposing matching bounded MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The previous generalized-fraction math note left infix `\choose` and
  `\atop` as follow-up after direct fractions, binomial commands, roots,
  scripts, fences, operators, matrices, cases, arrays, and above/below
  wrappers. This slice ports that bounded support-library contract and includes
  adjacent plain/fenced infix fraction forms.
- This does not attempt full `texmath` parity, `\overwithdelims`,
  `\atopwithdelims`, `\abovewithdelims`, color/phantom/cancel commands,
  optional macro arguments, MathML intent annotations, or renderer execution.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 115 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 110 assertions, 2 failures`.
  - Failure reason: infix commands were emitted as literal identifiers and
    malformed infix uses were accepted.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 126 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+11` focused assertions.
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5973 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `555`.
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

- `phpPass`: refreshed from `553` to the exact focused-suite PASS-line count
  `555`.
- `benchmarkDenominator.mapped`: `1033` -> `1034`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `65`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, roots, scripts, source annotation, delimiter/fence,
  large-operator/function/operator-name, relation/set/logic/arrow, accent,
  macro-expansion, indexed-root, matrix/aligned environment, cases environment,
  array column-spec, above/below/style wrapper, or binomial command conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep `\overwithdelims`, `\atopwithdelims`, `\abovewithdelims`, color/phantom/
cancel policy, optional macro arguments, richer MathML intent/accessibility
annotations, deeper TeX parsing, and full upstream runner dependency planning as
separate bounded slices.
