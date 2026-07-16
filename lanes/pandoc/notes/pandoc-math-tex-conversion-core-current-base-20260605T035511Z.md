# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T035511Z`

Base accepted HEAD: `24c4644c214503440645874cb6dbfb7ef8927022`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX explicit-delimiter infix
  fraction handoff.
- Supports `\overwithdelims`, `\atopwithdelims`, and `\abovewithdelims`
  inside the current expression or group, splitting already-parsed numerator
  nodes from the remaining denominator expression.
- Reads the two delimiter operands with the same bounded delimiter policy used
  for fences, including invisible `.` delimiters and known TeX delimiter
  commands such as `\langle` / `\rangle`.
- Emits regular fenced `mfrac` output for `\overwithdelims`, zero-line fenced
  fractions for `\atopwithdelims`, and bounded line-thickness fenced fractions
  for `\abovewithdelims`.
- Preserves escaped source TeX annotations in the surrounding MathML semantics
  block.
- Rejects malformed delimiters, missing numerators, missing denominators, and
  unsupported `\abovewithdelims` line-thickness operands without invoking
  Pandoc, texmath, MathJax, KaTeX, TeX engines, or any online service.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review packets
  preserve editable explicit-delimiter TeX source and expose matching bounded
  MathML.

## Source Truth

- Existing accepted inventory maps Pandoc Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Previous math notes left `\overwithdelims`, `\atopwithdelims`, and
  `\abovewithdelims` as a separate follow-up after direct fractions,
  generalized/infix fractions, roots, scripts, fences, operators, matrices,
  cases, arrays, binomials, accents, macros, and above/below/style/color/
  phantom/cancel wrappers.
- This slice ports that bounded support-library contract only. It does not
  attempt full `texmath` parity, optional macro arguments, xcolor model
  conversion, `\cancelto`, MathML intent annotations, or renderer execution.
- The local upstream Pandoc checkout remains absent under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream runner was
  executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 146 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 141 assertions, 2 failures`.
  - Failure reason: `\overwithdelims`, `\atopwithdelims`, and
    `\abovewithdelims` were emitted as literal identifiers, and malformed forms
    were not rejected.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 157 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+11` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- Broader lane directory check:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: not used as this slice's acceptance gate. This accepted base already
    reports an unrelated `MarkdownReaderTest.php` table-footer mismatch in
    `writes wordpress structured html table sections from import notes`.
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

- `phpPass`: `599` -> `600`.
- `benchmarkDenominator.mapped`: `1073` -> `1074`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `65`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, plain/fenced infix fractions, roots, scripts, source
  annotation, delimiter/fence, large-operator/function/operator-name,
  relation/set/logic/arrow, accent, macro-expansion, indexed-root,
  matrix/aligned environment, cases environment, array column-spec,
  above/below/style wrapper, binomial command, color, phantom, or cancel
  conversion.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep color declaration scoping, named xcolor model conversion, `\cancelto`
target annotations, optional macro arguments, richer MathML intent/accessibility
annotations, deeper TeX parsing, and full upstream runner dependency planning
as separate bounded slices.
