# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T115354Z`

Base accepted HEAD: `0e6781ae8b76f0d938e737f367e60c0dfb521f96`

## Behavior Added

- Added bounded native TeX equation-reference handoff for `\ref{...}` and
  `\eqref{...}` inside `MathTexConverter`.
- `\ref{label}` now renders as a MathML `mtext` reference with an `href`
  pointing at the same normalized id shape used by accepted `\label{...}`
  output.
- `\eqref{label}` wraps the same reference in MathML parentheses, preserving
  the unresolved label text for reviewer queues instead of inventing equation
  numbers without a document-level resolver.
- Empty or unsupported reference labels are rejected before MathML is exposed.
- Updated the WordPress math handoff example so editable TeX references and
  linked MathML output are covered by the local smoke path.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Prior math slices accepted top-level labels/tags and row-level labels/tags,
  leaving equation reference resolution as follow-up. This slice owns only the
  bounded reference handoff link shape; document-level numbering and
  cross-document resolution remain separate follow-up work.
- No local Pandoc checkout is present under
  `/home/claude/port-libs/.upstream-cache/pandoc`, so no upstream Haskell
  runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 279 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 273 assertions, 2 failures`.
  - Failure reason: `\eqref` and `\ref` were emitted as literal identifiers,
    and malformed reference commands were accepted.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 286 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+7` focused assertions.
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

- `phpPass`: `880` -> `881` by one newly passing focused math test case.
- `benchmarkDenominator.mapped`: `1338` -> `1339`.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `61`.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  ordinary/extensible arrow accents, simple/optional macro expansion,
  indexed-root, matrix/aligned/cases/array/alignedat conversion,
  above/below/style wrappers, binomial commands, color, phantom, cancel,
  math alphabet conversion, `\substack`, named spacing, explicit
  `\hspace`/`\mspace`, top-level equation label/tag metadata, or AMS row-level
  equation metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep reference-number resolution across parsed document label maps,
cross-document equation references, Unicode mathematical alphanumeric
rewriting, MathML intent/accessibility annotations, nested macro-body
admission, deeper TeX parsing, full `texmath` parity, and full upstream runner
dependency planning as separate bounded slices.
