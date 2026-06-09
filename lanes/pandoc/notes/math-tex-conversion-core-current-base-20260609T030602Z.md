# Math/TeX conversion core current-base: tortoise shell delimiters

Slice: `pandoc-math-tex-conversion-core-current-base-20260609T030602Z`
Base: `82ece526c3b1abf329ce3c42e1c2113cbac669aa`

## Source truth

The bounded upstream behavior comes from texmath's TeX command table in
`Text.TeXMath.Readers.TeX.Commands`:

- `\lbrbrak` and `\rbrbrak` map to open/close tortoise shell bracket symbols.
- `\Lbrbrak` and `\Rbrbrak` map to open/close white tortoise shell bracket symbols.

Primary source link inspected:

- https://raw.githubusercontent.com/jgm/texmath/master/src/Text/TeXMath/Readers/TeX/Commands.hs

This slice ports only the local format contract. It does not run Pandoc,
texmath, MathJax, KaTeX, TeX engines, Cabal, Haskell test binaries, external
renderers, office tools, zip/unzip, or online conversion services.

## Implementation

- `MathTexConverter` now recognizes `\lbrbrak`, `\rbrbrak`, `\Lbrbrak`, and
  `\Rbrbrak` in the shared delimiter map.
- Plain delimiter commands render as MathML `<mo>` tokens instead of leaking raw
  TeX command identifiers.
- The same aliases work through `\left...\right` and sized delimiter commands
  such as `\Bigl...\Bigr`.
- Accessibility text and intent tokens now describe the new bracket glyphs.
- The WordPress math handoff example now includes a tortoise shell delimiter
  audit while preserving editable TeX source annotations.

## Red-first evidence

Before the patch, this focused probe leaked raw TeX command names:

`php -r 'require "tools/bootstrap.php"; $c = new PortLibs\Pandoc\MathTexConverter(); echo $c->texToMathMl("\\lbrbrak x \\rbrbrak + \\Lbrbrak y \\Rbrbrak");'`

Observed pre-fix output included:

- `<mi>\lbrbrak</mi>`
- `<mi>\rbrbrak</mi>`
- `<mi>\Lbrbrak</mi>`
- `<mi>\Rbrbrak</mi>`

## Verification

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 1132 assertions, 0 failures`
- Final: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - `1 test files, 1144 assertions, 0 failures`
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

No new support component is needed. The slice reuses the existing native PHP
delimiter parser, MathML serializer, accessibility metadata path, Markdown
reader, and WordPress block writer. Full upstream runner parity remains gated on
a hydrated pinned upstream checkout and reviewed Cabal/Haskell test plan.

## Non-overlap

This avoids accepted Math/TeX slices for source annotations, plain roots,
texToken operands, color/layout wrappers, prime notation, equation metadata,
array and AMS environment metadata, variant Greek/underbar aliases, and
extensible-arrow token labels. The mapped manifest moves one native Math/TeX
core case and focused math assertions increase by 12.
