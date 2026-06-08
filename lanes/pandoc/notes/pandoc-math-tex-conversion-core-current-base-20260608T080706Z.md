# Pandoc Math/TeX conversion core current-base slice

- Session: `port-dev-pandoc-math-tex-20260608T080706Z`
- Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260608T080706Z`
- Accepted base: `576c1d07db28f80f3749bd13aa6f78dd425d4a62`
- Scope: bounded native Math/TeX command-table alias support for dot ellipses, relation operators, and named symbols.

## Behavior

- Added bounded MathML conversion for `\ldots`, `\cdots`, `\ddots`, `\aleph`, `\ell`, `\Re`, `\Im`, `\wp`, `\cong`, `\simeq`, `\propto`, `\parallel`, `\perp`, `\angle`, `\nabla`, `\top`, and `\bot`.
- Preserved escaped source TeX in MathML semantics annotations while preventing those commands from falling back to literal `<mi>\command</mi>` nodes.
- Added accessibility speech and intent text for the new named-symbol and relation tokens.
- Extended the WordPress Math/TeX handoff smoke so review packets include the alias cluster without external math renderers.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before implementation.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 724 assertions, 0 failures`.
- Red-first: the new focused test failed before implementation with `1 test files, 726 assertions, 1 failures` because `\ldots`, `\cdots`, `\cong`, and related aliases rendered as literal fallback identifiers.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 734 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.
- PHP lint passed for `lanes/pandoc/src/MathTexConverter.php`, `lanes/pandoc/tests/MathTexConverterTest.php`, and `lanes/pandoc/examples/wordpress-math-tex-handoff.php`.
- Lane JSON validation passed for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Status delta

- `phpPass`: `1575 -> 1576`
- `benchmarkDenominator.mapped`: `1996 -> 1997`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 95`

## Dependency closure

No new support component is needed. This slice reuses `MathTexConverter`, its MathML serializer, accessibility text/intent helpers, Markdown math span handoff, and the existing WordPress Math/TeX handoff example.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Non-overlap

This slice does not repeat recent Math/TeX work for `\mathchoice`, prescripts, alignedat, multline/multlined, p/m/b array column widths, `\bangle`, modulo commands, or TeX comment stripping. It only maps the bounded dot, relation, and named-symbol alias cluster above.
