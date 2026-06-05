# Pandoc Math TeX Conversion Core Current Base

Slice: `pandoc-math-tex-conversion-core-current-base-20260605T140108Z`

Base accepted HEAD: `60b6befaeb74609f083a4f1b6e86f771ce5cb8eb`

## Behavior Added

- Added opt-in accessible MathML handoff methods on `MathTexConverter`:
  `texToAccessibleMathMl()` and `accessibleMathMlFor()`.
- The default `texToMathMl()` output remains unchanged unless the new
  accessibility path is requested.
- Accessible output adds root `alttext` and `intent` attributes plus
  `application/x-portlibs-math-alttext` and
  `application/x-portlibs-math-intent` review annotations inside the existing
  MathML `semantics` wrapper.
- Accessibility text is derived from the generated bounded presentation MathML
  tree, so existing TeX parsing and validation remains the source of truth.
- Updated `examples/wordpress-math-tex-handoff.php` so WordPress review
  packets expose and self-test the accessible MathML audit path.

## Source Truth

- The accepted Pandoc inventory maps Markdown math evidence from
  `test/testsuite.txt`, `test/testsuite.native`,
  `test/markdown-reader-more.txt`, and `test/markdown-reader-more.native`.
  Those fixtures preserve inline/display math source as TeX strings in Pandoc
  math nodes.
- This slice extends the existing native PHP Math/TeX support row with bounded
  accessibility metadata around already-parsed MathML. It does not attempt
  full `texmath` semantic speech parity or renderer validation.
- The local upstream Pandoc checkout was not hydrated in this isolated
  worktree, and no upstream runner was executed.

## Verification

- Baseline before edit:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 315 assertions, 0 failures`.
- Red check after adding focused coverage:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 315 assertions, 1 failures`.
  - Failure reason: `Call to undefined method PortLibs\Pandoc\MathTexConverter::texToAccessibleMathMl()`.
- Focused check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
  - Result: `1 test files, 322 assertions, 0 failures`.
  - Delta: `+1` focused PASS case and `+7` focused assertions.
- Focused lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Result: `21 test files, 12175 assertions, 0 failures`.
- Local PASS-line count:
  `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - Result: `924`.
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
  `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " json ok\n"; }'`
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

No Pandoc, Cabal solver/build/test command, Haskell runner, `texmath`, MathJax,
KaTeX, TeX/PDF engine, browser renderer, JavaScript, online sanitizer, or
online service was executed.

## Status Delta

- `benchmarkDenominator.mapped`: `1391` -> `1392`.
- `phpPass`: `935` -> `936` by one newly passing focused Math/TeX case in
  lane status.
- `mathTexConversionCoreCases`: `11` -> `12`.
- `mappedMathTexConversionCoreCases`: `11` -> `12`.
- `mathTexConversionCoreAssertions`: `54` -> `61`.
- Focused `MathTexConverterTest.php`: `41` -> `42` PASS cases and `315` ->
  `322` assertions.

## Non-Overlap

- Does not repeat accepted Math/TeX direct fractions, style-aware fractions,
  generalized `\genfrac`, infix fractions, explicit-delimiter infix fractions,
  roots, scripts, source annotation, delimiter/fence, sized delimiter,
  `\middle`, large-operator/function/operator-name, relation/set/logic/arrow,
  accent, macro-expansion, indexed-root, matrix/aligned environment, cases
  environment, array column-spec, above/below/style wrapper, binomial command,
  color, phantom, `\cancel`, `\bcancel`, `\xcancel`, `\cancelto`, math
  alphabet conversion, `\substack`, AMS align/gather/split environment
  conversion, alignedat conversion, equation tags/labels/references, named
  spacing command conversion, or explicit `\hspace`/`\mspace` dimension
  parsing.
- Does not touch DOCX OMML extraction, ODT formulas, PDF engine handoff, OPC,
  archive compression, citations, YAML, doctemplates, tables, legacy DOC/CFB,
  XML/HTML5 DOM, charset/Unicode, syntax highlighting, or upstream-runner
  dependency closure.

## Follow-Up

Keep full `texmath` parity, renderer validation, complex accessibility intent
grammar, TeX package macro expansion, additional unsupported environments, and
full upstream runner dependency planning as separate bounded slices.
