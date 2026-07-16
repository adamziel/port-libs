# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T070159Z`

Base accepted HEAD: `eaacf8bbd0fe9974a974530fef58314b019631bc`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX sized delimiter command handoff.
- Converts `\big`, `\Big`, `\bigg`, and `\Bigg`, plus `l`/`r`/`m` variants,
  to MathML `mo` fence operators with fixed `minsize`/`maxsize` hints.
- Marks `m` variants such as `\bigm|` as MathML separators while preserving the
  same bounded delimiter validation used by `\left` and explicit infix
  delimiter fractions.
- Preserves the existing MathML `semantics` wrapper and escaped
  `application/x-tex` source annotation.
- Rejects missing sized delimiters, unknown delimiter commands, and invalid
  delimiter characters before exposing MathML, without invoking Pandoc,
  texmath, MathJax, KaTeX, TeX/PDF engines, or online services.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review
  packets keep editable sized-delimiter TeX source and verify matching bounded
  MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The accepted spacing-command math note left explicit `\hspace`/`\mspace`,
  `\middle`, `alignedat`, equation numbering, optional macro arguments,
  Unicode mathematical alphanumeric rewriting, and MathML intent annotations as
  follow-up work. This slice ports only bounded sized delimiter commands.
- The local upstream Pandoc checkout remains absent under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream runner was
  executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 204 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 204 assertions, 2 failures`.
  - Failure reason: sized delimiters emitted literal identifiers such as
    `<mi>\bigl</mi>` and malformed `\big` input did not throw.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 215 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+11` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8347 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `711`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MathTexConverter.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: no syntax errors.
  `php -l lanes/pandoc/examples/wordpress-math-tex-handoff.php`
  - Result: no syntax errors.
- JSON validation:
  `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
  - Result: both JSON files parsed successfully.
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Result: passed with no output.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

## Status Delta

- `phpPass`: `729` -> `730` by one newly passing focused math test case.
- `benchmarkDenominator.mapped`: `1188` -> `1189`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `65`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, large-operator/function/
  operator-name, relation/set/logic/arrow, accent, macro-expansion,
  indexed-root, matrix/aligned environment, cases environment, array
  column-spec, above/below/style wrapper, binomial command, color, phantom,
  `\cancel`, `\bcancel`, `\xcancel`, `\cancelto`, math alphabet conversion,
  `\substack`, AMS align/gather/split environment conversion, or spacing
  command conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep explicit `\hspace`/`\mspace` dimension parsing, `\middle` fence
validation, `alignedat`, equation numbering/labels, optional macro arguments,
Unicode mathematical alphanumeric rewriting, MathML intent/accessibility
annotations, deeper TeX parsing, and full upstream runner dependency planning
as separate bounded slices.
