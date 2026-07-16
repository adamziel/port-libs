# Pandoc Math/TeX Current-Base Optional Row Spacing

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T172206Z`
Base: `19e469ac5fba851474b6c82ad19f3b8c0f411282`

## Behavior

- Preserves bounded optional TeX row-spacing brackets after top-level row separators, such as `\\[.5em]`, as MathML `rowspacing` plus `data-tex-rowspacing` review metadata.
- Applies the metadata path to table-like math handoffs that already share the bounded row splitter: matrix, array, AMS row environments, alignedat, flalign, eqnarray, and multline.
- Keeps source TeX annotations intact so WordPress review tooling can still expose editable TeX source.
- Rejects empty or unsupported row-spacing dimensions with the existing bounded TeX spacing dimension validator.

## Evidence

- Baseline before patch:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  Result: `1 test files, 783 assertions, 0 failures`.
- Red-first after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  Result: `1 test files, 785 assertions, 1 failures`; expected failure was missing rowspacing metadata on aligned MathML.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  Result: `1 test files, 793 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  Result: passed.

## Dependency Closure

No new native PHP support component is needed. This reuses `MathTexConverter` row splitting and MathML table rendering plus the existing Markdown/WordPress example path. No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat recent Math/TeX slices for `mathchoice`, prescript, symbol aliases, environment comments, declared operators, declared paired delimiters, array width columns, bangle fractions, or modular commands. The prior multline slice consumed optional row-spacing brackets; this slice adds explicit MathML review metadata for those row-spacing dimensions.
