# Pandoc Math/TeX Conversion Core - Prescript

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T065516Z`

Accepted base: `020e2ea23f5994952f6082bab5de6c073c83d6be`

## Behavior

- Added bounded native TeX `\prescript{sup}{sub}{base}` handling in `MathTexConverter`.
- The converter emits MathML `mmultiscripts` with `<mprescripts/>`, using MathML pre-sub/pre-sup ordering while preserving TeX argument order.
- Single missing pre-script slots render as `<none/>`; both missing pre-script slots, missing base, and empty base fail closed.
- Normal TeX scripts after the prescripted base still bind through the existing script parser.
- Source annotations and accessibility metadata continue to use the original TeX source.

## Evidence

- Baseline focused check before this slice: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 713 assertions, 0 failures`.
- Red-first focused check after adding the test failed as expected: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` reported `1 test files, 715 assertions, 1 failures` because `\prescript` rendered as `<mi>\prescript</mi>`.
- Final focused check: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 724 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.
- PHP lint passed for changed PHP files.
- JSON validation passed for lane JSON.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native Math/TeX parser, MathML serializer, `mmultiscripts` accessibility helpers, focused PHP test harness, and WordPress Math/TeX handoff example. Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, Cabal/Haskell runners, external converters, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This slice does not repeat accepted Math/TeX work for sideset operator scripts, mathchoice branch selection, alignedat, multline/multlined, array width columns, bangle fractions, modular commands, TeX comments, hyperref, texmath command wrappers, compact matrix/subarray environments, or siunitx commands. The new mapped case is only bounded `\prescript` atom pre-script MathML handoff.
