# Pandoc Math/TeX Conversion Core: Array Multicolumn

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T151641Z`
Base: `1fe4fabf88654dad0045232c1f7682f4d344b4f4`
Date: 2026-06-06 UTC

## Behavior

- Added bounded native `\multicolumn{n}{spec}{content}` support for TeX `array` cells in `MathTexConverter`.
- Emits MathML `mtd` metadata: `columnspan`, cell `columnalign`, optional boundary-line provenance, and optional width/vertical-alignment/hook metadata when the one-column multicolumn spec uses existing bounded `array` preamble parsing.
- Rejects malformed spans, empty content, multi-column replacement specs, trailing tokens, and spans that exceed the declared array preamble columns.
- The pre-change red-first probe for `\begin{array}{lcr}p_i & \multicolumn{2}{c}{m_i + q_i} \\ a & b & c\end{array}` produced literal `<mi>\multicolumn</mi>` output inside the second cell.

## Evidence

- `php -l lanes/pandoc/src/MathTexConverter.php`
  - `No syntax errors detected in lanes/pandoc/src/MathTexConverter.php`
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/MathTexConverterTest.php`
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - `No syntax errors detected in lanes/pandoc/examples/wordpress-math-tex-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Before this slice: `1 test files, 501 assertions, 0 failures`
  - After this slice: `1 test files, 513 assertions, 0 failures`
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - `math tex handoff self-test ok`
- `git diff --check -- lanes/pandoc`
  - clean

## Mapping

- `lane-status.json` `phpPass`: `1353 -> 1354`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1767 -> 1768`
- `mathTexConversionCoreCases`: `13 -> 14`
- `mappedMathTexConversionCoreCases`: `13 -> 14`
- `mathTexConversionCoreAssertions`: `72 -> 84`

## Dependency Closure

No new support component is needed. This reuses the existing native `MathTexConverter` array preamble parser and WordPress math handoff example. Full upstream runner parity remains blocked on a hydrated Pandoc checkout plus explicitly authorized Cabal/Haskell runner work. No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, browser renderer, online service, or live provider test was executed.

## Follow-Up

Keep broader TeX array/table constructs such as `\multirow`, `\omit`, `\noalign`, full texmath parity, and upstream-runner comparison as separate bounded slices.
