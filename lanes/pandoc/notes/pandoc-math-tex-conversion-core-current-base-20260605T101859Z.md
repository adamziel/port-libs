# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T101859Z`

Base accepted HEAD: `ba5d3716ae151c5706a8fd13f14f0006f8bc18f9`

## Behavior Added

- Extended `MathTexConverter` with bounded top-level TeX equation metadata
  handoff.
- Strips `\label{...}`, `\tag{...}`, and `\tag*{...}` before the existing
  MathML expression parser so equation metadata no longer leaks as literal
  identifier nodes.
- Renders `\tag{...}` as a MathML `<mtable><mlabeledtr>` row with automatic
  parentheses and renders `\tag*{...}` without automatic parentheses.
- Preserves `\label{...}` as a sanitized MathML id on the labeled equation
  body plus an `application/x-tex-label` annotation, while keeping the original
  source TeX in the existing `application/x-tex` annotation.
- Rejects empty/missing/duplicate bounded equation metadata before exposing
  MathML to WordPress review handoffs.
- Updated the WordPress math handoff example with a tagged/labeled display
  equation that remains editable as source TeX and addressable in MathML.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The prior math note explicitly left equation numbering/labels as follow-up.
  This slice owns only bounded top-level equation `\label`/`\tag` metadata
  handoff in the native PHP converter.
- No local Pandoc checkout is present under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream Haskell
  runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 251 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 251 assertions, 2 failures`.
  - Failure reason: `\label` and `\tag` leaked as literal MathML identifiers,
    and malformed metadata was not rejected.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 260 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+9` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 10168 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `814`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- PHP lint:
  `php -l lanes/pandoc/src/MathTexConverter.php`
  `php -l lanes/pandoc/tests/MathTexConverterTest.php`
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

- `benchmarkDenominator.mapped`: `1287` -> `1288`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `63`.
- `phpPass`: `827` -> `828` in lane status by one newly passing focused math
  case. The local full lane PASS-line count command returned `814`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  accent, simple/optional macro expansion, indexed-root, matrix/aligned
  environment, cases environment, array column-spec, above/below/style wrapper,
  binomial command, color, phantom, `\cancel`, `\bcancel`, `\xcancel`,
  `\cancelto`, math alphabet conversion, `\substack`, AMS
  align/gather/split/alignedat environment conversion, named spacing command
  conversion, or explicit `\hspace`/`\mspace` dimension parsing.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, or
  upstream-runner dependency closure.

## Follow-Up

Keep row-level tags inside alignment environments, equation reference
resolution, Unicode mathematical alphanumeric rewriting, MathML intent and
accessibility annotations, deeper TeX parsing, full `texmath` parity, and full
upstream runner dependency planning as separate bounded slices.
