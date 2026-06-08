# Pandoc Math/TeX Generated Symbol Aliases

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T214118Z`
Session: `port-dev-pandoc-math-tex-20260608T214118Z`
Base accepted HEAD: `a0d85bbfea71fbea16acdfcda87bce21bb3681b0`

## Behavior

This slice maps a bounded texmath generated command-table cluster into native
MathML output instead of falling back to literal command identifiers. The new
aliases cover boxed and dotted operators, square subset/superset relations,
less/gtr approximate relations, bumpy equals, curly precedence/success
relations, squiggle/two-head/hook arrows, and negated relation aliases:

`\\dotplus`, `\\boxplus`, `\\boxminus`, `\\boxtimes`, `\\boxdot`,
`\\sqsubset`, `\\sqsupset`, `\\sqsubseteq`, `\\sqsupseteq`, `\\lesssim`,
`\\gtrsim`, `\\lessapprox`, `\\gtrapprox`, `\\Bumpeq`, `\\bumpeq`,
`\\curlyeqprec`, `\\curlyeqsucc`, `\\rightsquigarrow`, `\\nRightarrow`,
`\\twoheadrightarrow`, `\\hookrightarrow`, `\\nleq`, `\\ngeq`,
`\\nleqslant`, and `\\ngeqslant`.

The conversion preserves source TeX annotations and adds accessibility labels
for the newly mapped MathML operator glyphs.

## Source Truth

Primary upstream source inspected:

- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Unicode/ToTeX.hs`
- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs`
- `https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs`

No Pandoc, texmath executable, MathJax, KaTeX, TeX engine, Cabal solver/build
or test command, Haskell runner, external renderer, online service, live
provider test, or live-service provider test was executed.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` note existed for this lane before
  editing.
- Red-first native probe showed generated texmath aliases such as `\\dotplus`,
  `\\boxplus`, `\\sqsubset`, and `\\rightsquigarrow` rendering as literal
  `<mi>\\command</mi>` fallback identifiers before this patch.
- Focused test: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 901 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed with `math tex handoff self-test ok`.
- PHP lint and `git diff --check -- lanes/pandoc` are recorded in the final
  handoff response.

## Status Delta

- `lane-status.json` `phpPass`: `1880 -> 1881`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2303 -> 2304`.
- Math/TeX inventory: `mathTexConversionCoreCases` and
  `mappedMathTexConversionCoreCases` `14 -> 15`.
- Math/TeX assertion inventory: `85 -> 98`.

## Dependency Closure

No new support component is needed. The slice reuses native
`MathTexConverter` symbol-command parsing, `MarkdownReader` math handoff,
`LatexWriter` source annotations, and the WordPress math handoff example.

## Non-Overlap

This is not a repeat of the accepted Math/TeX alignedat, multline/multlined,
array column-width, bangle, modular command, or percent-comment slices. It is
also distinct from syntax highlighting, ODF, DOCX, PDF-engine, table geometry,
and runner-dependency audit work.

Root harness status: not run - isolated micro-slice.
