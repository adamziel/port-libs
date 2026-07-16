# PlainMath Upstream Fixture Expansion

Date: 2026-06-26 UTC
Bead: `plib-wj70q.18`

## Scope

Expanded the static PlainMath conformance corpus with passing TexMath reader
fixtures. This is fixture promotion only: no Pandoc, TexMath, Haskell, Cabal,
TeX engine, MathJax, KaTeX, browser renderer, external converter, online
service, or runtime shell-out was used to derive expected output.

## Source Truth

- Local fixture cache inspected read-only:
  `/home/claude/port-libs/polecats/flint/port_libs/.upstream-cache/texmath`
- TexMath cache commit: `170899673ee31de9096e178605e8da31a36e4185`
- Source family: `test/reader/tex/*.test`

## Promoted Passing Fixtures

Added seven fixture-backed cases to `PlainMathStaticTexmathFixtureTest.php`:

- `test/reader/tex/quadratic_formula.test`
- `test/reader/tex/simple_sum_formula.test`
- `test/reader/tex/binomial_coefficient.test`
- `test/reader/tex/boxed.test`
- `test/reader/tex/phantom.test`
- `test/reader/tex/stackrel.test`
- `test/reader/tex/substack.test`

Each case asserts native PHP MathML structure plus the exact source TeX
annotation preserved by the PlainMath handoff.

## 2026-06-29 Addendum

Added four more passing fixture-backed cases from the same TexMath reader
corpus:

- `test/reader/tex/choose.test`
- `test/reader/tex/genfrac.test`
- `test/reader/tex/notin.test`
- `test/reader/tex/cancel.test`

The added cases cover infix `\choose`/`\brace` no-line fractions, explicit
`\genfrac` delimiters, negated relation normalization, and cancel/boxed
MathML enclosure mapping. They remain static PHP assertions against
`MathTexConverter`; no TexMath, Pandoc, TeX engine, browser renderer, or
external converter is invoked by the test.

## 2026-06-30 Addendum

Added three more passing fixture-backed cases from `jgm/texmath`
`test/reader/tex` source fixtures:

- `test/reader/tex/complex_number.test`
- `test/reader/tex/deMorgans_law.test`
- `test/reader/tex/divergence.test`

The added cases cover nested overbrace/underbrace text annotations, Boolean and
set relation operators with overlines, and vector divergence partial
derivatives. The current local worktree did not include a hydrated TexMath
checkout, so fixture names and bodies were confirmed read-only from the primary
`jgm/texmath` raw fixture source on 2026-06-30. The committed PHP tests remain
static assertions against `MathTexConverter`; no TexMath, Pandoc, TeX engine,
browser renderer, or external converter is invoked by the test.

## 2026-06-30 Addendum 2

Added four more passing fixture-backed cases from the same TexMath reader
corpus:

- `test/reader/tex/axiom_of_power_set.test`
- `test/reader/tex/span.test`
- `test/reader/tex/sophomores_dream.test`
- `test/reader/tex/moore_determinant.test`

The added cases cover quantified logic and set membership, labeled horizontal
arrows, an integral/series identity with normal differential text, and bmatrix
rows with nested powers and diagonal ellipses. Fixture names and TeX bodies
were confirmed read-only from local cache path
`/home/claude/port-libs/polecats/1763/port_libs/.upstream-cache/texmath` at
the same TexMath commit recorded above. The committed PHP tests remain static
assertions against `MathTexConverter`; no TexMath, Pandoc, TeX engine, browser
renderer, or external converter is invoked by the test.

## Counts

- Static upstream denominator remains `2,276` inspected upstream Pandoc
  test/data/benchmark files.
- Focused mapped behavior checks: `2,304 -> 2,308` after the 2026-06-29
  addendum.
- PlainMath static TexMath fixture cases: `7 -> 11` after the 2026-06-29
  addendum.
- Focused mapped behavior checks: `2,310 -> 2,313` after the 2026-06-30
  addendum.
- PlainMath static TexMath fixture cases: `11 -> 14` after the 2026-06-30
  addendum.
- Focused mapped behavior checks: `2,313 -> 2,317` after the second
  2026-06-30 addendum.
- PlainMath static TexMath fixture cases: `14 -> 18` after the second
  2026-06-30 addendum.

## Verification

Focused check:

```text
php tools/run-tests.php lanes/pandoc/tests/PlainMathStaticTexmathFixtureTest.php
```

Expected result: the focused PlainMath conformance test file passes without
external converter execution.

2026-06-29 result: `1` file, `103` assertions, `0` failures.

2026-06-30 result: `1` file, `126` assertions, `0` failures.

2026-06-30 addendum 2 result: `1` file, `154` assertions, `0` failures.
