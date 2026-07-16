# Pandoc Math/TeX Relation and Harpoon Alias Slice

Micro-slice: `pandoc-math-tex-conversion-core-current-base-20260608T220641Z`

Base accepted HEAD: `5ca5ed5c01549ddcb5727c8343ae1666cecfe98d`

## Behavior

- Added bounded native MathML handoff for texmath relation, diagonal arrow, harpoon, and logic aliases:
  `\prec`, `\succ`, `\ll`, `\gg`, `\precsim`, `\succsim`, `\subsetneq`, `\supsetneq`,
  `\nearrow`, `\searrow`, `\swarrow`, `\nwarrow`, `\leftharpoonup`,
  `\rightharpoondown`, `\rightleftharpoons`, `\leftrightharpoons`,
  `\because`, `\multimap`, `\pitchfork`, and `\leadsto`.
- The same aliases now get accessibility labels through `texToAccessibleMathMl()` and remain source-annotated for WordPress review handoff.

## Source Truth

- Static upstream texmath source inspection used `Text/TeXMath/Unicode/ToTeX.hs` and command-table mappings as source truth for these bounded aliases.
- No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This slice avoids the accepted Math/TeX clusters for alignedat, multline, bangle infix fractions, modular commands, TeX comments, array width columns, generated operator aliases, symbol override aliases, and AMS intertext rows.

## Evidence

- Baseline focused test before adding the new case:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 901 assertions, 0 failures`.
- Red-first after adding the new case:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  failed with `1 test files, 903 assertions, 1 failures`; `\prec` rendered as a literal identifier.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 914 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed.

## Dependency Closure

No new support component is needed. The patch reuses native `MathTexConverter`, `MarkdownReader`, `WordPressBlockWriter`, focused PHP tests, and the existing WordPress math handoff example.

Root harness: not run - isolated micro-slice.
