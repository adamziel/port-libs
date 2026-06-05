# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T091118Z`

Base accepted HEAD: `a45ca97f406d7ee0c5dd0511dc2a10ff6abec006`

## Behavior Added

- Extended `MathTexConverter` raw macro extraction with bounded optional
  argument defaults from `\newcommand`, `\renewcommand`, and
  `\providecommand` definitions.
- Preserves the optional default as `optionalDefault` metadata and expands
  either the default value or an explicit bracket override before required
  brace arguments.
- Validates externally supplied optional-default macro definitions before
  expansion, including non-string defaults and invalid zero-arity optional
  macros.
- Keeps the original TeX invocation in the existing
  `application/x-tex` MathML annotation and in WordPress editable math spans.
- Updated `examples/wordpress-math-tex-handoff.php` so review packets expose
  optional macro defaults and overrides as expanded MathML without invoking
  external renderers.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes and
  preserves raw macro definition blocks for math handoff.
- Earlier math slices accepted direct fractions, generalized/infix fractions,
  roots, scripts, fences, sized delimiters, `\middle`, operators, matrices,
  cases, arrays, AMS align/gather/split/alignedat tables, spacing, explicit
  spacing dimensions, color/phantom/cancel, math alphabets, and simple raw
  macro expansion. This slice owns only bounded optional-argument raw macro
  expansion.
- No local Pandoc checkout is present under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream Haskell
  runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 244 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 246 assertions, 2 failures`.
  - Failure reason: optional defaults were discarded during macro extraction,
    macros with optional arguments were not expanded, and malformed
    optional-default definitions were accepted.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 251 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+7` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `20 test files, 9588 assertions, 0 failures`.
  - PASS-line count command:
    `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `781`.
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

- `benchmarkDenominator.mapped`: `1255` -> `1256`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `61`.
- `phpPass`: preserved at `795` in lane status to avoid overwriting newer
  accepted/pending status evidence; this patch adds one focused
  `MathTexConverterTest.php` PASS case and local lane tests produced `781`
  PASS lines.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  accent, simple macro-expansion, indexed-root, matrix/aligned environment,
  cases environment, array column-spec, above/below/style wrapper, binomial
  command, color, phantom, `\cancel`, `\bcancel`, `\xcancel`, `\cancelto`,
  math alphabet conversion, `\substack`, AMS align/gather/split/alignedat
  environment conversion, named spacing command conversion, or explicit
  `\hspace`/`\mspace` dimension parsing.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, or upstream-runner dependency closure.

## Follow-Up

Keep equation numbering/labels, Unicode mathematical alphanumeric rewriting,
MathML intent/accessibility annotations, nested macro-body admission in
MarkdownReader raw TeX blocks, deeper TeX parsing, full `texmath` parity, and
full upstream runner dependency planning as separate bounded slices.
