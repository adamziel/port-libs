# Pandoc Math/TeX Conversion Core - Mathchoice

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T054328Z`

Accepted base: `4cdbc422e45adc25f1ad62ce24e13ad1c7bd277e`

## Behavior

- Added bounded native TeX `\mathchoice{display}{text}{script}{scriptscript}` handling in `MathTexConverter`.
- The converter now selects the display branch for display math, the text branch for inline math, the script branch in first-level subscript/superscript or `\scriptstyle`, and the scriptscript branch for nested script contexts.
- All four branches are parsed in their matching bounded style so malformed non-selected branches still fail closed instead of being silently ignored.
- Source annotations and accessible MathML continue to use the original TeX source while the rendered MathML receives the selected branch.

## Evidence

- Baseline focused check before the slice: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 701 assertions, 0 failures`.
- Final focused check after the slice: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed with `1 test files, 713 assertions, 0 failures`.
- WordPress example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test` passed with `math tex handoff self-test ok`.
- PHP lint passed for changed PHP files.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native Math/TeX parser, MathML serializer, accessibility helpers, focused PHP test harness, and WordPress Math/TeX handoff example. Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, Cabal/Haskell runners, external converters, online services, live provider tests, and live-service provider tests were not run.

## Non-Overlap

This slice does not repeat accepted Math/TeX work for alignedat, multline/multlined, array width columns, bangle fractions, modular commands, TeX comments, hyperref, texmath command wrappers, compact matrix/subarray environments, or siunitx scalar commands. The new mapped case is only bounded style-aware `\mathchoice` branch selection.
