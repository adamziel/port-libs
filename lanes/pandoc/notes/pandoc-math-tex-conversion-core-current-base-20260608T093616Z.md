# Pandoc Math/TeX Conversion Core Current Base

- Session: `port-dev-pandoc-math-tex-20260608T093616Z`
- Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260608T093616Z`
- Accepted base: `fc9cc5ac780ad879f0d013a4c9808a06a29c2d50`
- Scope: bounded native Math/TeX extensible-arrow alias handoff.

## Behavior

- Extended `MathTexConverter`'s existing extensible-arrow dispatch table to
  cover common texmath-style labeled arrow aliases:
  `\xlongequal`, `\xhookleftarrow`, `\xhookrightarrow`,
  `\xtwoheadleftarrow`, `\xtwoheadrightarrow`, `\xleftharpoonup`,
  `\xleftharpoondown`, `\xrightharpoonup`, and `\xrightharpoondown`.
- Reuses the current optional-lower-label plus required-upper-label parser used
  by `\xleftarrow`, `\xrightarrow`, `\xleftrightarrow`, and `\xmapsto`.
- Emits native MathML `mover` / `munderover` structures with stretchy operator
  glyphs, escaped `application/x-tex` source annotations, and accessibility
  alttext/intent token names for hook, two-head, and harpoon arrows.
- Extended the WordPress Math/TeX handoff example so review packets preserve
  editable source TeX for the alias cluster while exposing native MathML.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed before this slice.
- Baseline focused check:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 734 assertions, 0 failures`.
- Red-first focused check after adding the test, before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 734 assertions, 1 failures`.
  - Failure reason: `\xlongequal`, `\xhookrightarrow`, and
    `\xtwoheadleftarrow` rendered as literal fallback identifiers.
- Final focused check:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 740 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint passed for changed PHP files.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1597 -> 1598`
- `benchmarkDenominator.mapped`: `2016 -> 2017`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 91`

## Dependency Closure

No new support component is needed. This slice reuses the existing native
`MathTexConverter`, MathML serializer, accessibility text/intent helpers,
focused PHP test harness, and WordPress Math/TeX handoff example.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test
command, Haskell runner, external converter, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This slice does not repeat accepted Math/TeX work for direct fractions,
style-aware fractions, infix fractions, `\bangle`, siunitx commands, math
alphabet aliases, prescripts, sidesets, `\mathchoice`, modular commands,
comments, `alignedat`, multline/multlined, array width columns, sized
delimiters, middle delimiters, or the existing base extensible-arrow commands.
It only maps the bounded extensible-arrow alias cluster above.
