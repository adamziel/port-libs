# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T022616Z`

Base accepted HEAD: `9175aaed2fff50dba03f4d62df09a6b8d4ac9fe1`

## Behavior Added

- Extended `MathTexConverter` with bounded generalized TeX fraction handoff.
- Added style-aware `\dfrac` and `\tfrac` conversion as `mstyle`-wrapped
  MathML fractions while preserving accepted `\frac` output.
- Added bounded `\genfrac` support for simple fence delimiters, optional line
  thickness, TeX style hints `0` through `3`, and non-empty numerator and
  denominator groups.
- Rejects malformed `\genfrac` delimiters, line thicknesses, style hints, and
  missing or empty numerator/denominator groups without invoking Pandoc,
  texmath, MathJax, KaTeX, or a TeX engine.
- Updated the WordPress math handoff example so editable reviewer formulas
  preserve `\dfrac` and `\genfrac` source while exposing matching bounded
  MathML with source annotations.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`, `test/markdown-reader-more.txt`,
  and `test/markdown-reader-more.native`: Pandoc preserves inline/display math
  source as TeX strings in math nodes.
- The previous math slice note left generalized fractions as follow-up after
  direct fractions, binomial commands, roots, scripts, fences, operators,
  matrices, cases, arrays, and above/below wrappers. This slice ports that
  bounded support-library contract only.
- This does not attempt full `texmath` parity, TeX rendering, MathJax, KaTeX,
  Pandoc execution, TeX/PDF engines, infix `\choose`/`\atop`, color/phantom/
  cancel commands, optional macro arguments, or full MathML intent annotations.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 102 assertions, 0 failures`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 115 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+13` focused assertions.
- Full focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `19 test files, 5731 assertions, 0 failures`.
  - PASS-line count: `538`.
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

- `phpPass`: `537` -> `538`.
- `benchmarkDenominator.mapped`: `1015` -> `1016`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `67`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fraction/root/script/text, source
  annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root,
  matrix/aligned environment, cases environment, array column-spec,
  above/below/style wrapper, or binomial command conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep infix `\choose`/`\atop`, color/phantom/cancel policy, optional macro
arguments, richer MathML intent/accessibility annotations, deeper TeX parsing,
and full upstream runner dependency planning as separate bounded slices.
