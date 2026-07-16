# Math/TeX conversion core current-base: unbraced operatorname tokens

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T001956Z`
Base: `e681d9cd3726e0b2d0a8b66aaf879a79d22125f0`

## Source truth

The bounded upstream behavior is texmath's TeX reader accepting `\operatorname` arguments as either a braced expression or a single token/character. Source checked: `Text.TeXMath.Readers.TeX.operatorname` in texmath:
https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs

This slice ports that contract without running Pandoc, texmath, MathJax, KaTeX, TeX engines, Cabal, Haskell runners, or online services.

## Implemented behavior

- `MathTexConverter` now accepts bounded unbraced `\operatorname` and `\operatorname*` arguments when the argument is one plain character or one known TeX command token from the existing identifier/function/operator/delimiter command tables.
- Scripts remain attached to the parsed operator name token, so `\operatorname\alpha_i` becomes a scripted MathML identifier.
- Starred operator names still default to limits placement, so display math such as `\operatorname*\max_{i=1}^{n} p_i` renders with `munderover`.
- Unsupported unbraced commands and empty/script-only operator names still throw before handoff.
- The WordPress math handoff example now includes the unbraced operatorname path and asserts both the editable source span and MathML annotation.

## Red-first evidence

Native probes before the change failed with `Expected TeX text group`:

- `\operatorname x_i`
- `\operatorname\alpha_i`
- `\operatorname*\max_{i=1}^{n} p_i`
- `\operatorname\alpha_i + \operatorname*\max_{j}^{n} p_j`

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 1021 assertions, 0 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 1036 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - `math tex handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/MathTexConverter.php`
  - `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - all reported no syntax errors
- Whitespace: `git diff --check -- lanes/pandoc`
  - passed

Root harness not run: isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses native PHP `MathTexConverter` command tables plus the existing MathML source annotation and accessibility helpers. Full upstream Pandoc/texmath runner parity remains gated on a hydrated pinned checkout and reviewed non-mutating Cabal plan.

## Non-overlap

This avoids accepted braced/starred `\operatorname`, `\displaylimits`/`\nolimits`, large operator aliases, modular command spacing, TeX comment handling, starred matrix environments, and array preamble slices. The mapped denominator moved by one native Math/TeX reader case and focused math assertions increased by 15.
