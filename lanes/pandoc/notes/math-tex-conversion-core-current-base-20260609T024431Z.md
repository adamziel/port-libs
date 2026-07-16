# Math/TeX conversion core current-base: arrow texToken labels

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T024431Z`
Base: `12507a9792ad5cde3ccd9d84d97d5835d2a8ef77`

## Source truth

The bounded upstream behavior comes from texmath's TeX reader:

- `Text.TeXMath.Readers.TeX.texToken` accepts a TeX symbol, braced group, or one TeX character.
- `Text.TeXMath.Readers.TeX.tSymbol` applies accent, over-accent, and under-accent symbols to a `texToken`.
- `Text.TeXMath.Readers.TeX.Commands.symbolMapOverrides` maps `\overrightarrow` and related accent-style commands into that symbol path.

Primary source links inspected:

- https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX.hs
- https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs

This slice ports only the local format contract. It does not run Pandoc, texmath, MathJax, KaTeX, TeX engines, Cabal, Haskell test binaries, external renderers, or online services.

## Implementation

- `MathTexConverter::parseExtensibleArrowCommand()` now accepts an upper label as the existing bounded TeX token parser, so unbraced labels such as `\xrightarrow\alpha` and `\xhookrightarrow[map] f` render as native MathML while existing braced labels remain unchanged.
- `MathTexConverter::parseArrowAccentCommand()` now accepts a base as a TeX token, so unbraced bases such as `\overrightarrow A_i` and `\underrightarrow\operatorname{media}` render without leaking raw command names.
- Empty/missing arrow labels and arrow-accent bases still throw before handoff.
- The WordPress math handoff example now includes the unbraced arrow-token path while preserving the editable TeX source span.

## Red-first evidence

Before the patch, focused probes failed with group-required diagnostics:

- `\xrightarrow x_i` -> `Expected TeX xrightarrow upper label group`
- `\xleftarrow[\text{low}] y_j` -> `Expected TeX xleftarrow upper label group`
- `\xhookrightarrow[map] f` -> `Expected TeX xhookrightarrow upper label group`
- `\overrightarrow A_i` -> `Expected TeX overrightarrow base group`
- `\underrightarrow \operatorname{media}` -> `Expected TeX underrightarrow base group`

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 1120 assertions, 0 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 1128 assertions, 0 failures`
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - `math tex handoff self-test ok`
- Syntax:
  - `php -l lanes/pandoc/src/MathTexConverter.php`
  - `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`

Root harness not run: isolated micro-slice.

## Dependency closure

No new support component is needed. The slice reuses the existing native PHP TeX-token parser, MathML serializer, source annotation handoff, Markdown reader, and WordPress block writer. Full upstream runner parity remains gated on a hydrated pinned upstream checkout and reviewed Cabal/Haskell test plan.

## Non-overlap

This avoids accepted Math/TeX slices for unbraced `\operatorname`, roots/fractions/binomial/wrapper texToken operands, color operands, layout wrappers, prime notation, equations/tags/references, array and AMS environment metadata, and braced extensible-arrow/arrow-accent coverage. The mapped manifest moves one native Math/TeX core case and focused math assertions increase by 8.
