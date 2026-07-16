# Math TeX Conversion Core Current Base 20260609T015928Z

Base accepted HEAD: `afefe2709cd2d600e733f14d1a2c7daf937774dc`

## Scope

Implemented a bounded TeX token-argument parity slice for brace/bracket and layout wrappers in the native MathML handoff. The parser now accepts a single TeX token after:

- `\overbrace`, `\underbrace`, `\overbracket`, `\underbracket`
- `\smash`, `\mathllap`, `\mathrlap`, `\clap`

This preserves scripts outside the wrapper, so examples like `\overbrace x_i^n`, `\underbracket y_0`, `\smash[t] y^2`, and `\mathllap L_i` emit wrapper MathML first and then apply the following subscript/superscript to that wrapped atom.

## Non-Overlap

This slice does not repeat the accepted `texTokenArgumentMathml` work for `\sqrt`, fractions, `\binom`, `\overset`, `\underset`, `\boxed`, phantom, and cancel commands. It extends the same bounded single-token argument contract to the remaining brace/bracket and layout wrapper commands only. It also avoids upstream-runner, DOCX, ZIP/OPC, CSL/BibTeX, and PDF engine handoff surfaces.

## Evidence

- Baseline focused command before the change: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` -> `1 test files, 1080 assertions, 0 failures`.
- Syntax checks: `php -l lanes/pandoc/src/MathTexConverter.php`, `php -l lanes/pandoc/tests/MathTexConverterTest.php`, and `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php` all reported no syntax errors.
- Focused tests after the change: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` -> `1 test files, 1089 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` -> `math tex handoff self-test ok`.
- Diff hygiene: `git diff --check -- lanes/pandoc` -> no output.

This is assertion-only growth inside existing focused PASS cases, so `lane-status.json` keeps `phpPass` at `2096` and records the `1080 -> 1089` focused assertion delta.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP `parseRequiredTexToken()` and MathML serializer paths. It does not invoke Pandoc, TeX, MathJax, KaTeX, external renderers, Haskell binaries, or online services.

## Follow-Up

Continue with additional one-token TeX wrapper parity for delimiter, arrow, or environment-adjacent commands only where it improves real conversion coverage and can be backed by focused MathML/WordPress handoff assertions.
