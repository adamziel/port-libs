# Pandoc Math TeX Conversion Core Current Base 20260606T052708Z

Base accepted HEAD: `325c2b0f457f1a0f74e1d2a0b7da113bbb15e2f6`

## Behavior

This slice adds bounded TeX `array` paragraph column support for `p{width}`,
`m{width}`, and `b{width}` preamble entries in the native PHP
`MathTexConverter`.

The converter now:

- accepts `p{...}`, `m{...}`, and `b{...}` only in ordinary `array`
  environments;
- maps the columns to left horizontal alignment;
- preserves the declared absolute width in MathML `columnwidth`;
- records vertical intent as `data-tex-column-valign` with `top`, `middle`,
  and `bottom`;
- keeps normal `l`, `c`, `r`, and column-line behavior intact;
- rejects empty, negative, unknown-unit, or subarray width preambles.

The WordPress math handoff smoke now includes a width-column array so inline
math handoff preserves semantic MathML plus the original TeX annotation.

## Source Truth And Scope

Source truth is the lane's existing Pandoc-like math contract: keep source TeX
annotations, convert bounded math structures to MathML in native PHP, and do
not shell out to Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, Haskell
binaries, or online services. The local upstream Pandoc checkout was not
available in `.upstream-cache`, so this slice stays within the accepted native
support-library contract rather than claiming upstream-runner parity.

This does not implement full LaTeX array preamble parsing. `>{...}`,
`<{...}`, `@{...}`, `!{...}`, decimal alignment, repeated `*{n}{...}`
preambles, and renderer-specific table layout validation remain out of scope.

## Evidence

Red-first evidence before the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 450 assertions, 0 failures`.
- Direct conversion of
  `\begin{array}{p{2cm}|m{1.5em}|b{8pt}}...\end{array}` failed with
  `Unsupported TeX array column specifier p at offset 0`.

Focused verification after the implementation:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 457 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed with `math tex handoff self-test ok`.
- `php -l lanes/pandoc/src/MathTexConverter.php` passed.
- `php -l lanes/pandoc/tests/MathTexConverterTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` passed.
- `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` validated as JSON.

- `git diff --check -- lanes/pandoc` passed with no whitespace errors.

## Status Delta

- `lane-status.json` `phpPass`: `1208` to `1209`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `1654` to `1655`.
- Math/TeX core cases: `12` to `13`.
- Math/TeX core assertions: `63` to `70`.
- Focused `MathTexConverterTest.php`: `450` to `457` assertions.

## Dependency Closure

No new support component is needed. This reuses the native PHP
`MathTexConverter`, existing markdown math handoff, existing WordPress example
handoff, existing AST/writer pipeline, and lane-local manifest/status tracking.

The remaining dependency closure blocker is still the upstream Pandoc runner
family: this environment did not run Pandoc/Cabal/Haskell/texmath/MathJax/
KaTeX/TeX/PDF/browser/online services.

## Non-Overlap

This avoids the accepted math clusters for ordinary l/c/r arrays, row and
column lines, subarray/smallmatrix handling, AMS alignment wrappers,
alignedat/flalign/multline/equation handling, tags/labels/refs, fractions,
roots, delimiters, macros, text mode, color/phantom/cancel/smash/variant, and
accessibility annotations. The new behavior is specifically paragraph-width
array preamble metadata.

## Next Task

Extend bounded math array support only when a focused conversion path needs it:
renderer-safe pre/post column hooks, repeated preambles, decimal alignment, or
a real upstream texmath runner audit once the upstream runner dependency is
available.
