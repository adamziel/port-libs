# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T233827Z`
Base accepted HEAD: `475b85e029e16dfc514361ae0145c8d6dab388cb`
Lane: `pandoc`

## Behavior

- Added bounded native MathML command-table coverage for extended texmath named identifiers: `\beth`, `\gimel`, `\daleth`, `\eth`, `\imath`, `\jmath`, `\Finv`, and `\Game`.
- Added dotted/order/negated relation aliases including `\leqq`, `\geqq`, `\lneqq`, `\gneqq`, `\lessgtr`, `\gtrless`, `\lll`, `\ggg`, `\doteq`, `\Doteq`, `\fallingdotseq`, `\risingdotseq`, `\triangleq`, `\backsimeq`, `\nsubseteq`, `\nsupseteq`, `\nmid`, `\nparallel`, `\nprec`, and `\nsucceq`.
- Added accessibility token labels so `texToAccessibleMathMl()` emits useful `alttext` and `intent` metadata for the new symbols instead of raw Unicode fallback tokens.
- Extended the WordPress math TeX handoff smoke so Markdown parsing, WordPress span preservation, MathML summary output, and self-test checks include the new alias cluster.

## Source Truth And Non-Overlap

- Source truth is the pinned Pandoc/texmath command-table contract already tracked in the lane manifest plus existing accepted native Math/TeX command-table behavior in `MathTexConverter`.
- No current `port-pandoc-*.needs-lane-rework.md` note existed for this lane before editing.
- This slice does not overlap the accepted math slices for alignedat, multline/multlined, array width-column metadata, bangle infix fractions, modulo commands, TeX comments, binary/relation aliases, generated operator/relation aliases, relation/harpoon aliases, or symbol override aliases.
- No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 984 assertions, 0 failures`.
- Red-first: same focused command failed with `1 test files, 994 assertions, 1 failures` because the new glyphs rendered but accessibility `alttext`/`intent` fell back to raw Unicode tokens.
- Final: same focused command passed with `1 test files, 999 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.
- Final lint and whitespace checks are recorded in the worker final response.

## Status Delta

- `lane-status.json` `phpPass`: `1978 -> 1979`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2397 -> 2398`.
- `mathTexConversionCoreCases`: `14 -> 15`.
- `mappedMathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 100`.

## Dependency Closure

No new support component is needed. This reuses the native PHP `MathTexConverter` command dispatch, MathML source annotations, accessibility metadata generation, `MarkdownReader`, `WordPressBlockWriter`, and the existing WordPress math TeX handoff smoke.

Full upstream runner parity remains gated on a hydrated pinned Pandoc/texmath checkout and a reviewed non-mutating Cabal plan. This slice intentionally stays within bounded native PHP conversion behavior.

## Next

A next non-overlapping Math/TeX slice could cover additional texmath delimiter/operator command-table aliases or parser-level relation placement not already covered by this slice and the accepted command-table/environment slices.
