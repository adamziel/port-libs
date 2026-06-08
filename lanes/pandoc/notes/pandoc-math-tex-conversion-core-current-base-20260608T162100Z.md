# Pandoc Math/TeX Paired Delimiter Invocation Slice

Slice: `pandoc-math-tex-conversion-core-current-base-20260608T162100Z`

Accepted base: `bc9489e331853d7b5b38ea37ea420a29310b5ae4`

## Behavior

- `MathTexConverter` now recognizes starred and explicitly sized calls for
  macros produced by bounded `\DeclarePairedDelimiter`.
- `\wpabs*{...}` expands through the existing `\left...\right...` MathML fence
  path instead of rendering a literal `*`.
- `\wpabs[\Big]{...}` and `\wpangle[\bigg]{...}` expand to native sized
  delimiter commands, preserving MathML `minsize`/`maxsize` metadata.
- Unsupported paired-delimiter size commands fail closed before MathML is
  exposed.
- Source TeX annotations preserve the caller's original starred/sized syntax
  for WordPress review.

## Evidence

- No current Pandoc lane rework note existed before implementation.
- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 783 assertions, 0 failures`.
- Red-first focused test after adding the case and before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  failed with `1 test files, 785 assertions, 1 failures` because the converter
  emitted literal `\wpabs`, `\wpangle`, `*`, and bracket tokens.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  passed with `1 test files, 793 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  passed with `math tex handoff self-test ok`.

## Status Delta

- Added one focused PHP PASS case.
- Focused Math/TeX assertions moved from `783` to `793` (`+10`).
- `lane-status.json` `phpPass`: `1695 -> 1696`.
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2115 -> 2116`.
- Math/TeX mapped core cases: `14 -> 15`.
- Math/TeX mapped core assertions: `85 -> 95`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses
`MathTexConverter` macro extraction/expansion, delimiter parsing, MathML
serialization, accessibility metadata, `MarkdownReader`, `WordPressBlockWriter`,
and the existing WordPress math handoff example.

No Pandoc, texmath, MathJax, KaTeX, TeX/PDF engine, Cabal solver/build/test
command, Haskell runner, external converter, online service, live provider
test, or live-service provider test was executed.

## Non-Overlap

This does not repeat accepted Math/TeX work for `\DeclarePairedDelimiter`
declaration capture, ordinary one-argument paired delimiter expansion,
`\mathchoice`, prescripts, declared math operators, TeX comments,
environment-row comments, alignedat, multline/multlined, equation wrappers,
array width columns/hooks/multicolumns/rules, bangle infix fractions,
modulo commands, hyperref wrappers, siunitx commands, or color/phantom/cancel
commands. It owns only starred and explicitly sized invocation syntax for
already-declared paired delimiter macros.

## Follow-Up

A non-overlapping follow-up could add bounded `\DeclarePairedDelimiterX` body
templates or additional paired-delimiter diagnostics, still using native PHP
and focused lane tests only.
