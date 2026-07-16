# Pandoc Math/TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260606T210901Z`
Base accepted HEAD: `4df8e352f25033d0564141d0276d624c15569721`

## Source Truth

Upstream texmath's TeX reader treats `\allowbreak` as an ignorable control
sequence in the `ignorable` parser, so it contributes no expression node while
normal TeX source still remains available to callers that preserve the original
input. Reference: <https://github.com/jgm/texmath/blob/master/src/Text/TeXMath/Readers/TeX.hs>.

## Implementation

- `MathTexConverter` now consumes `\allowbreak` as a zero-node command before
  spacing/modulo/operator command fallback.
- Direct scripts on `\allowbreak` now fail closed with a bounded parser
  diagnostic instead of producing an empty scripted MathML base.
- Source TeX annotations still preserve the original `\allowbreak` source, so
  WordPress review packets remain editable while rendered MathML and accessible
  intent omit the ignorable command.

## Evidence

- Baseline: `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 559 assertions, 0 failures`.
- Red-first: same command failed with `1 test files, 561 assertions, 1 failures`
  because `\allowbreak` emitted as literal `<mi>\allowbreak</mi>`.
- Final: same command passed with `1 test files, 568 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed with `math tex handoff self-test ok`.

No Pandoc, Cabal build/test command, Haskell runner, texmath executable,
MathJax, KaTeX, TeX/PDF engine, browser renderer, online service, live provider
test, or live-service provider test was executed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`MathTexConverter`, source-TeX MathML annotations, accessibility annotation
generation, and the existing WordPress math handoff example. Full Pandoc runner
parity, exact texmath parser parity, browser rendering, MathJax/KaTeX rendering,
and TeX/PDF engine execution remain out of scope for this isolated micro-slice.

## Next

Continue bounded Math/TeX closure with non-overlapping texmath reader gaps such
as additional ignorable/control commands, delimiter variants, or MathML
accessibility handoff edges.
