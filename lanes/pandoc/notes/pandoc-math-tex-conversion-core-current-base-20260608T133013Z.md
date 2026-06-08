# pandoc-math-tex-conversion-core-current-base-20260608T133013Z

Base accepted HEAD: `1ae0859e60102323dd11b913da6001a073a626eb`

## Behavior

This slice maps one bounded Math/TeX source-capture case for Markdown reader
macro declarations:

- `MarkdownReader` now recognizes `\DeclareMathOperator` and
  `\DeclareMathOperator*` declaration lines before paragraph parsing.
- Starred declarations are emitted as `raw_tex` blocks and as zero-arity macro
  definitions using `\operatorname*{...}` so the existing `MathTexConverter`
  macro expansion path can produce operator-limits MathML.
- WordPress math handoff now keeps the source declaration visible as raw TeX
  while rendering the declared starred operator in editable math spans and
  MathML review packets.

The slice deliberately stays within bounded native PHP conversion. It does not
run Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, Cabal, Haskell runners,
browser renderers, online services, live provider tests, or live-service
provider tests.

## Evidence

- Rework note preflight:
  `find /home/claude/port-libs/.tmux-team/tmp/handoff-candidates -maxdepth 1 -type f -name 'port-pandoc-*.needs-lane-rework.md' ...`
  found no current Pandoc lane rework note for this slice.
- Baseline before the new assertion:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 745 assertions, 0 failures`.
- Red-first after adding the starred declaration capture test and before the
  implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  failed with `1 test files, 746 assertions, 1 failures` because the
  `\DeclareMathOperator*` source line parsed as a `paragraph` instead of a
  `raw_tex` declaration block.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 756 assertions, 0 failures`.
- WordPress example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed with `math tex handoff self-test ok`.
- PHP lint passed for:
  `lanes/pandoc/src/MarkdownReader.php`,
  `lanes/pandoc/tests/MathTexConverterTest.php`, and
  `lanes/pandoc/examples/wordpress-math-tex-handoff.php`.

## Counters

- `lane-status.json` `phpPass`: `1654 -> 1655`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2074 -> 2075`.
- `mathTexConversionCoreCases`: `14 -> 15`.
- `mappedMathTexConversionCoreCases`: `14 -> 15`.
- `mathTexConversionCoreAssertions`: `85 -> 96`.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the existing
MarkdownReader raw TeX declaration handoff and the existing MathTexConverter
macro-definition and `\operatorname*` MathML paths. Full Pandoc/texmath/TeX
engine parity remains out of scope for this isolated support-library slice.

## Non-Overlap

This does not repeat earlier math slices for multline/multlined environments,
array width columns, bangle infix fractions, modular commands, TeX comments, or
mathchoice branch selection. It narrows the prior declared-operator support to
the missing Markdown source-capture path for starred declarations.

## Follow-Up

Choose a non-overlapping bounded Math/TeX command handoff such as
`\DeclarePairedDelimiter` capture, mathtools arrow variants, or additional safe
operator-name escapes. Keep the next slice native PHP and external-tool free.
