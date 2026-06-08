# pandoc-math-tex-conversion-core-current-base-20260608T181632Z

## Scope

Implemented bounded native TeX color declaration scoping in `MathTexConverter`.
The converter now treats declaration-style `\color{...}` as applying to the
remaining current parse scope: a full expression, a braced group, or an
environment cell. Existing grouped `\color{...}{...}` and `\textcolor{...}{...}`
behavior is preserved.

This maps one additional native Math/TeX support case for richer Pandoc-style
math handoff without executing Pandoc, texmath, MathJax, KaTeX, TeX/PDF
engines, Cabal, Haskell runners, external converters, online services, live
provider tests, or live-service provider tests.

## Evidence

- `php -l lanes/pandoc/src/MathTexConverter.php`: no syntax errors
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`: no syntax errors
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`: no syntax errors
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`: 1 test files, 804 assertions, 0 failures
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`: math tex handoff self-test ok
- `git diff --check -- lanes/pandoc`: passed

## Dependency Closure

No new support component is needed. The slice reuses `MathTexConverter`,
MathML semantics annotations, `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter`.

Full Pandoc/texmath runner parity, xcolor color-model conversion, global TeX
color stacks, renderer parity, and external TeX/PDF engine behavior remain out
of scope for this isolated native PHP slice.

## Next

A non-overlapping Math/TeX follow-up could cover bounded xcolor model
normalization, `\colorbox`/`\fcolorbox` metadata handoff, or AMS `\intertext`
row handoff.
