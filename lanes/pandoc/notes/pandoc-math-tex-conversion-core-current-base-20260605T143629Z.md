# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T143629Z`

Base accepted HEAD: `964e39c56cd711357eb623d478058e1d1cec05ec`

## Behavior Added

- Added bounded automatic equation-reference numbering to
  `MathTexConverter::equationReferenceLabelsFromDocument()`.
- Untagged display math labels now resolve to sequential reference text such
  as `1`, `2`, and so on when a document label map is passed to `texToMathMl()`.
- Untagged AMS row labels inside display math participate in the same sequence.
- Inline labels keep label-text references, and explicit `\tag` or `\tag*`
  references continue to win over automatic numbering.
- Updated the WordPress math handoff example so review packets expose automatic
  display equation and AMS row references without invoking external renderers.

## Source Truth

- Existing accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`:
  Pandoc preserves inline/display math source as TeX strings in math nodes.
- Prior Math/TeX slices accepted explicit equation tags, row tags, unresolved
  reference fallback, and document label maps. This slice only fills the
  bounded automatic-number reference gap for untagged display equations.
- No local Pandoc or texmath checkout is present under this isolated worktree,
  so no upstream Haskell runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 322 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 323 assertions, 1 failures`.
  - Failure reason: untagged display labels and AMS row labels resolved to
    their label text instead of bounded automatic references.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 325 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+3` focused assertions.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
  - Result: `math tex handoff self-test ok`.
- Final PHP lint, JSON validation, and whitespace checks are recorded in the
  worker final report.

Root harness not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This extends the existing native PHP
`MathTexConverter` and reuses the current `MarkdownReader`, `LatexWriter`, and
`WordPressBlockWriter` handoff paths. Full upstream Pandoc runner parity remains
the existing Cabal/upstream-checkout blocker recorded in lane status.

No Pandoc, Cabal solver/build/test command, Haskell runner, texmath, MathJax,
KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, or
online service was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1402` -> `1403`.
- `phpPass`: `947` -> `948` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `57`.
- Focused `MathTexConverterTest.php`: `42` -> `43` PASS cases and `322` ->
  `325` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  accent, macro-expansion, indexed-root, matrix/aligned environment, cases
  environment, array column-spec, above/below/style wrapper, binomial command,
  color, phantom, `\cancel`, `\bcancel`, `\xcancel`, `\cancelto`, math
  alphabet conversion, `\substack`, AMS align/gather/split environment
  conversion, alignedat conversion, explicit equation tag rendering, resolved
  tagged document references, named spacing command conversion, explicit
  `\hspace`/`\mspace` dimension parsing, Unicode math alphabet rewriting,
  layout boxes, or accessible MathML metadata.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, EPUB3, ZIP, or
  upstream-runner dependency closure.

## Follow-Up

Keep cross-document equation-reference maps, TeX package macro expansion,
additional unsupported environments, complex accessibility intent grammar,
renderer validation, full `texmath` parity, and full upstream runner dependency
planning as separate bounded slices.
