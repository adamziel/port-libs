# Pandoc Math/TeX Current-Base Braced Not Relations

## Scope

- `MathTexConverter` now canonicalizes braced one-token TeX relations after `\not`, including `\not{\in}`, `\not{=}`, and `\not{\leqslant}`.
- Direct `\not\geqslant` and `\not\leqslant` now follow the same bounded relation table as the converter's existing `\geqslant` and `\leqslant` aliases.
- Arbitrary braced atoms such as `\not{p_i + m_i}` still use visible MathML `menclose` strike fallback instead of pretending to understand a broader TeX overlay.
- Source TeX annotations remain intact for WordPress review and editable math handoff.

## Source Truth

This is bounded native PHP support for the lane's accepted texmath-style `\not` relation overlay contract. The current isolated upstream cache does not include a Pandoc or texmath checkout, so the source boundary is the existing lane manifest and accepted `MathTexConverter` behavior for direct `\not\in`, `\not=`, `\not\leq`, relation aliases, and generic `\not` fallback.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test command, Haskell runner, external converter, online service, live provider test, or live-service provider test was executed.

## Red Probe

Before the implementation, a direct native probe showed these inputs emitted `menclose` around positive relation operators instead of canonical negated relation operators:

- `x \not{\in} y`
- `x \not{=} y`
- `x \not\geqslant y`
- `x \not\leqslant y`

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result after implementation: `1 test files, 819 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result after implementation: `math tex handoff self-test ok`.
- `php -l lanes/pandoc/src/MathTexConverter.php`
- `php -l lanes/pandoc/tests/MathTexConverterTest.php`
- `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
- `git diff --check -- lanes/pandoc`

## Mapping Delta

- Adds `+1` focused PHP PASS case.
- Adds `+5` focused Math/TeX assertions.
- `benchmarkDenominator.mapped`: `2161 -> 2162`.
- `mathTexConversionCoreCases`: `14 -> 15`.
- `mappedMathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 90`.

## Dependency Closure

No new native PHP support component is needed. This reuses `MathTexConverter`, `MathTexConverterTest`, and the existing WordPress math handoff example. Full upstream Pandoc/texmath runner parity, broader TeX overlay semantics, MathJax/KaTeX rendering, TeX/PDF engines, Cabal/Haskell runners, and external converters remain out of scope.

## Non-Overlap

This avoids accepted Math/TeX surfaces for `\mathchoice`, comments, optional row spacing, alignedat, multline/multlined, array width columns, bangle fractions, modular commands, declared operators, declared paired delimiters, equation wrappers, row tags, and no-number handling. The patch is scoped to canonicalizing one-token relation groups and slanted less/greater aliases after `\not`.
