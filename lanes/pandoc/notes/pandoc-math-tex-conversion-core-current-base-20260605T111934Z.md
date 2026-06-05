# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T111934Z`

Base accepted HEAD: `490b25f5b27ded338ec316c5d5be7821bb0c7237`

## Behavior Added

- Extended `MathTexConverter` with bounded AMS row-level equation metadata
  handoff for `align`, `align*`, `gather`, `gather*`, `gathered`, `split`,
  `alignat`, `alignat*`, `alignedat`, and `alignedat*` rows.
- Strips top-level row `\tag{...}`, `\tag*{...}`, and `\label{...}` metadata
  before parsing environment cells, so those commands no longer leak as
  literal MathML identifiers.
- Renders row tags as MathML `mlabeledtr` rows, adds automatic parentheses for
  unstarred `\tag`, preserves starred `\tag*` text as-is, and places row
  labels on the MathML row as stable sanitized ids.
- Rejects empty row tags, duplicate row tags, row metadata with no math cells,
  and empty row labels before exposing MathML to the WordPress review handoff.
- Updated `examples/wordpress-math-tex-handoff.php` so row-tagged alignment
  formulas remain editable as TeX and visible as labeled MathML review rows.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- The prior math notes accepted top-level equation `\label`/`\tag` metadata
  and left row-level tags inside alignment environments as follow-up. This
  slice owns only bounded AMS row metadata inside native PHP MathML handoff.
- No local Pandoc checkout is present under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream Haskell
  runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 271 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 274 assertions, 2 failures`.
  - Failure reason: row-level `\tag` and `\label` leaked as literal MathML
    identifiers, and malformed row metadata was accepted.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 279 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+8` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 10738 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `845`.
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
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity
remains the existing Cabal/upstream-checkout blocker recorded in lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX/PDF engine, browser renderer, online sanitizer, or online service
was executed.

## Status Delta

- `phpPass`: `859` -> `860` by one newly passing focused math test case.
- `benchmarkDenominator.mapped`: `1317` -> `1318`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `62`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  ordinary/extensible arrow accents, simple/optional macro expansion,
  indexed-root, matrix/aligned/cases/array/alignedat conversion,
  above/below/style wrappers, binomial commands, color, phantom, cancel,
  math alphabet conversion, `\substack`, named spacing, explicit
  `\hspace`/`\mspace`, or top-level equation label/tag metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep equation reference resolution, Unicode mathematical alphanumeric
rewriting, MathML intent/accessibility annotations, nested macro-body
admission, deeper TeX parsing, full `texmath` parity, and full upstream runner
dependency planning as separate bounded slices.
