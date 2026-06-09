# Math/TeX Color TeX-Token Handoff

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T014907Z`
Base: `08f16fc4bbcf45b83d9ea2497b2ad817ee73416e`

## Source Truth

Upstream texmath reader source treats a color operand as a single `texToken`: `texToken = texSymbol <|> inbraces <|> texChar`, and the `colored "\\color"` parser consumes that token after the color group. This patch ports that bounded operand contract into the native PHP MathML handoff while preserving the lane's existing scoped `\color{...}` declaration extension.

Source inspected: `jgm/texmath` `src/Text/TeXMath/Readers/TeX.hs` around `texToken` and `colored`.

## Implementation

- `MathTexConverter::parseColorCommand()` now uses the existing bounded TeX-token parser for command-style `\color` and `\textcolor` content instead of requiring only a braced group.
- Existing scoped `\color{red} p_i + ...` declaration behavior is preserved by the declaration reader before command parsing.
- WordPress math handoff example coverage now checks unbraced `\textcolor`, xcolor model token operands, and `\color[RGB]` token handoff.

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 1080 assertions, 0 failures`
- Red-first probe before patch:
  - `\textcolor{red}x_i` failed with `Expected TeX textcolor content group`
  - `\textcolor[HTML]{336699}\operatorname{media}` failed with `Expected TeX textcolor content group`
- Final: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 1094 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - `math tex handoff self-test ok`
- PHP lint passed for:
  - `lanes/pandoc/src/MathTexConverter.php`
  - `lanes/pandoc/tests/MathTexConverterTest.php`
  - `lanes/pandoc/examples/wordpress-math-tex-handoff.php`

## Dependency Closure

No new support component is needed. The slice reuses the native `MathTexConverter` TeX-token parser and does not run Pandoc, texmath, MathJax, KaTeX, TeX engines, Cabal/Haskell runners, external converters, online services, live provider tests, or live-service provider tests.

## Next

Choose a non-overlapping Math/TeX gap such as additional TeX-token command consumers, accent/wrapper parity, or bounded MathML metadata handoff. Keep the local `\color` declaration extension covered when adding more upstream texmath token parity.
