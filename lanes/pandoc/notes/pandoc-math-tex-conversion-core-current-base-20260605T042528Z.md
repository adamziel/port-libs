# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T042528Z`

Base accepted HEAD: `79f7b37d233bf2b4c9e836c5623f91107e0a407a`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX `\cancelto{target}{content}`
  handoff.
- Renders the canceled content as MathML `menclose` with an `updiagonalstrike`
  and places the target above it with `mover`, so reviewer and accessibility
  tooling can keep the cancellation target attached to the struck expression.
- Preserves the escaped source TeX annotation in the surrounding MathML
  `semantics` block.
- Supports scripts on the resulting `\cancelto` expression through the existing
  script parser.
- Rejects missing target/content groups and empty target/content groups without
  invoking Pandoc, texmath, MathJax, KaTeX, a TeX engine, or any online service.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review packets
  preserve editable `\cancelto` source and verify matching bounded MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The accepted color/phantom/cancel math note left `\cancelto` target
  annotations as follow-up after grouped `\color`, `\textcolor`, `\phantom`,
  `\hphantom`, `\vphantom`, `\cancel`, `\bcancel`, and `\xcancel`.
- This slice ports only that bounded support-library contract. It does not
  attempt full `texmath` parity, xcolor model conversion, color declaration
  scoping, MathML intent annotations, optional macro arguments, or renderer
  execution.
- The local upstream Pandoc checkout remains absent under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream runner was
  executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 157 assertions, 0 failures`.
- Red check after adding focused coverage and the example expectation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 160 assertions, 2 failures`.
  - Failure reason: `\cancelto` was emitted as a literal identifier, and
    malformed `\cancelto` inputs were not rejected.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 165 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+8` focused assertions.
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

- `phpPass`: `618` -> `619`.
- `benchmarkDenominator.mapped`: `1093` -> `1094`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `62`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, explicit-delimiter infix fractions, roots, scripts,
  source annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root, matrix/
  aligned environment, cases environment, array column-spec, above/below/style
  wrapper, binomial command, color, phantom, or the existing `\cancel`,
  `\bcancel`, and `\xcancel` conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep color declaration scoping, named xcolor model conversion, MathML intent and
accessibility annotations, optional macro arguments, deeper TeX parsing, and
full upstream runner dependency planning as separate bounded slices.
