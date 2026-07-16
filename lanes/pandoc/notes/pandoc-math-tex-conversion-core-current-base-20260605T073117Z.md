# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T073117Z`

Base accepted HEAD: `7be7cb0f86830fe3775224c988273aed9f59671f`

## Behavior Added

- Extended `MathTexConverter` with bounded explicit TeX spacing dimension
  handoff.
- Converts `\hspace{...}`, starred `\hspace*{...}`, and `\mspace{...}` to
  MathML `<mspace>` nodes with preserved `width` dimensions.
- Marks starred `\hspace*` as `linebreak="nobreak"` so the WordPress review
  packet keeps the author's nonbreaking spacing intent.
- Accepts only bounded numeric dimensions with explicit units:
  `em`, `ex`, `px`, `pt`, `pc`, `in`, `cm`, `mm`, and `mu`.
- Rejects missing, empty, unitless, function-like, unknown-unit, and unsupported
  starred `\mspace` inputs before exposing MathML.
- Preserves the existing MathML `semantics` wrapper and escaped
  `application/x-tex` source annotation.

## Source Truth

- Existing accepted Pandoc inventory maps the Markdown math path from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The accepted sized-delimiter math note left explicit `\hspace`/`\mspace`
  dimension parsing as a follow-up. This slice owns only that bounded spacing
  cluster.
- The local upstream Pandoc checkout remains absent under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream runner was
  executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 215 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 215 assertions, 2 failures`.
  - Failure reason: explicit `\hspace` and `\mspace` emitted literal
    identifiers, and malformed dimensions did not throw.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 226 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+11` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 8664 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `728`.
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

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX/PDF engine, browser renderer, online sanitizer, or online service
was executed.

## Status Delta

- `phpPass`: `746` -> `747` by one newly passing focused math test case.
- `benchmarkDenominator.mapped`: `1205` -> `1206`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `65`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter, large
  operator/function/operator-name, relation/set/logic/arrow, accent,
  macro-expansion, indexed-root, matrix/aligned environment, cases environment,
  array column-spec, above/below/style wrapper, binomial command, color,
  phantom, `\cancel`, `\bcancel`, `\xcancel`, `\cancelto`, math alphabet
  conversion, `\substack`, AMS align/gather/split environment conversion, or
  named spacing command conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep `\middle` fence validation, `alignedat`, equation numbering/labels,
optional macro arguments, Unicode mathematical alphanumeric rewriting, MathML
intent/accessibility annotations, deeper TeX parsing, and full upstream runner
dependency planning as separate bounded slices.
