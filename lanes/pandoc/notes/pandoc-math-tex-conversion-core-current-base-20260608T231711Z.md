# Pandoc Math/TeX colorbox/fcolorbox current-base slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T231711Z`
Base accepted HEAD: `2e9d106a5085fd98176497cfade7ca0a16be2709`

## Behavior

This slice adds bounded native Math/TeX conversion for `\colorbox` and `\fcolorbox`.

- `\colorbox{background}{math}` now emits MathML `mstyle mathbackground`.
- `\colorbox[HTML]{fff9cc}{...}` and other existing xcolor model arguments reuse the current color-model normalization.
- `\fcolorbox{frame}{background}{math}` now emits `menclose notation="box"` with `mathbackground` and inert `data-tex-framecolor` review metadata.
- Source TeX annotations remain intact for WordPress review handoff.
- Malformed missing/empty box color/content and repeated color-model syntax fail closed before any external TeX engine would be needed.

## Source Truth And Non-Overlap

Source truth is the bounded TeX reader behavior already represented in `MathTexConverter` and recent math notes for xcolor, boxed expressions, TeX comments, modular commands, and bangle infix fractions. This avoids overlapping the accepted xcolor foreground-color slice, `\boxed`/cancel/phantom slice, comments slice, AMS layout slices, and math alphabet/alias slices.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 964 assertions, 0 failures`.
- Red-first probe before implementation: `\colorbox{yellow}{p_i}` and `\fcolorbox{red}{yellow}{p_i}` rendered as literal identifiers.
- Syntax: `php -l lanes/pandoc/src/MathTexConverter.php` passed.
- Syntax: `php -l lanes/pandoc/tests/MathTexConverterTest.php` passed.
- Syntax: `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` passed.
- Focused: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 984 assertions, 0 failures`.
- Example: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1961 -> 1962`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2382 -> 2383`.
- `mathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 105`.

## Dependency Closure

No new support component is needed. The implementation reuses native `MathTexConverter` command parsing, existing xcolor color-model normalization, Markdown/AstNode math handoff, `LatexWriter` source preservation, and `WordPressBlockWriter` review output. Pandoc, Cabal/Haskell runners, texmath, MathJax, KaTeX, TeX/PDF engines, browser renderers, online services, live provider tests, and live-service provider tests were not run.

## Next

For Math/TeX follow-up, choose a non-overlapping bounded TeX reader gap such as additional box/color metadata, mathchoice-style display variants, or remaining texmath command aliases.
