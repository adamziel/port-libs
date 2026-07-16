# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T105018Z`

Base accepted HEAD: `e7428ba9eda23e1e08d47b2021a4ef6e529d4e53`

## Behavior Added

- Extended `MathTexConverter` with bounded TeX extensible-arrow handoff.
- Converts `\xrightarrow`, `\xleftarrow`, `\xleftrightarrow`, `\xmapsto`,
  and bounded uppercase double-arrow variants to MathML arrow operators with
  upper labels and optional lower labels.
- Converts `\overrightarrow`, `\overleftarrow`, `\overleftrightarrow`,
  `\underrightarrow`, `\underleftarrow`, and `\underleftrightarrow` to
  MathML arrow accents using `mover`/`munder`.
- Preserves the existing `semantics` wrapper and escaped
  `application/x-tex` source annotation so WordPress review packets keep the
  original editable TeX.
- Rejects missing or empty extensible-arrow labels and missing arrow-accent
  bases before exposing MathML.
- Updated `examples/wordpress-math-tex-handoff.php` so the WordPress review
  smoke covers editable extensible-arrow source plus bounded MathML output.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Prior math slices accepted direct fractions, generalized/infix fractions,
  roots, scripts, fences, sized delimiters, `\middle`, operators, matrices,
  cases, arrays, AMS rows/alignedat, spacing, explicit dimensions, color,
  phantom, cancel, math alphabets, macro expansion, equation metadata, and
  ordinary accents. This slice owns only bounded extensible-arrow labels and
  over/under arrow accents.
- No local Pandoc checkout is present under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream Haskell
  runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 260 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 263 assertions, 2 failures`.
  - Failure reason: `\xrightarrow`/`\xleftarrow` emitted literal identifiers,
    and malformed arrow commands were accepted.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 271 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+11` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 10464 assertions, 0 failures`.
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

- `phpPass`: `846` -> `847` by one newly passing focused math test case.
- `benchmarkDenominator.mapped`: `1305` -> `1306`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `65`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  ordinary accent, simple/optional macro expansion, indexed-root,
  matrix/aligned/cases/array/alignedat environment conversion,
  above/below/style wrappers, binomial commands, color, phantom, cancel,
  math alphabet conversion, `\substack`, named spacing, explicit
  `\hspace`/`\mspace`, or equation label/tag metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, or
  upstream-runner dependency closure.

## Follow-Up

Keep row-level tags inside alignment environments, equation reference
resolution, Unicode mathematical alphanumeric rewriting, MathML intent and
accessibility annotations, deeper TeX parsing, full `texmath` parity, and full
upstream runner dependency planning as separate bounded slices.
