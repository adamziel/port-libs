# Pandoc Math/TeX Command Wrapper Slice

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260606T165337Z`

Accepted base: `5b1009757b1754da91812e308050cf22a7c1fb8d`

## Behavior Added

- Added bounded texmath-reader command wrapper support in `MathTexConverter`:
  `\stackrel{above}{base}` renders as MathML `<mover>`, `\ensuremath{...}`
  unwraps its braced math content, and `\surd` renders as a square-root alias
  over the following atom or braced group.
- Kept the existing source-TeX `<semantics>` annotation path so WordPress review
  packets can preserve editable TeX source for these command wrappers.
- Extended the WordPress math handoff example with one source formula that uses
  all three command wrappers.

## Source Truth

- Upstream texmath source truth was inspected statically from
  `Text.TeXMath.Readers.TeX`: the reader handles `\stackrel`, `\ensuremath`,
  and `\surd` as TeX math commands.
- No Pandoc executable, texmath executable, Cabal solver/build/test command,
  Haskell runner, MathJax, KaTeX, TeX/PDF engine, browser renderer, online
  service, live provider test, or live-service provider test was executed.

## Red-First Evidence

Before implementation, a focused `php -r` probe through `MathTexConverter`
showed literal identifier MathML for the selected commands:

- `\stackrel{\text{audit}}{p_i}` emitted literal `\stackrel` text before the
  grouped arguments.
- `\ensuremath{p_i + m_i}` emitted literal `\ensuremath` text before the group.
- `\surd x` emitted literal `\surd` text before `x`.

## Verification

- Baseline focused test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 523 assertions, 0 failures`.
- Final focused test after this slice:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 540 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed with `math tex handoff self-test ok`.
- Syntax and diff checks:
  `php -l` on changed PHP files and `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1368 -> 1369`
- `benchmarkDenominator.mapped`: `1781 -> 1782`
- `mathTexConversionCoreCases`: `13 -> 14`
- `mappedMathTexConversionCoreCases`: `13 -> 14`
- `mathTexConversionCoreAssertions`: `72 -> 89`
- Focused `MathTexConverterTest.php`: `523 -> 540` assertions.

## Dependency Closure

No new support component is needed. This slice reuses native PHP
`MathTexConverter` parsing, MathML rendering, source-TeX annotation, and the
existing WordPress math handoff example.

## Non-Overlap

This does not repeat accepted Math/TeX work for source annotations, infix
fractions, indexed `\sqrt`, array preambles, math class wrappers, accents,
boxed/cancel wrappers, AMS aligned/alignedat/flalign/multline environments, or
table handoffs. It is limited to the texmath command-wrapper cluster above.

## Follow-Up

Keep broader texmath macro expansion, additional command aliases, TeX parser
recovery, MathJax/KaTeX rendering, TeX/PDF engine output, and full upstream
Pandoc runner parity as separate bounded slices.
