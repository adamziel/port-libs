# Pandoc Math/TeX Conversion Core Current-Base Slice

Session: `port-dev-pandoc-math-tex-20260608T125145Z`
Base accepted HEAD: `cf694c999fba2a9ae966ad0c44be82830abea0f8`

## Behavior

This slice adds bounded native support for AMS `\DeclareMathOperator` handoff:

- `MathTexConverter::macroDefinitionsFromDocument()` now recognizes raw TeX `\DeclareMathOperator{\name}{operator text}` and `\DeclareMathOperator*{\name}{operator text}` declarations.
- Declared operators are mapped into the existing bounded macro table as zero-arity `\operatorname{...}` or `\operatorname*{...}` templates.
- Simple operator-name spacing escapes such as `\,` and `\;` normalize to a single space so MathML renders names like `review score` instead of literal TeX spacing commands.
- Command-bearing operator names such as `\input{...}` and empty names are rejected before conversion. No TeX engine or external renderer is invoked.
- Direct `\operatorname{arg\,max}` rendering uses the same normalization path.

The WordPress math handoff smoke now includes a document-level `\DeclareMathOperator{\wpreviewscore}{review\,score}` declaration and verifies the declared operator expands to native MathML while the original TeX source annotation remains intact.

## Evidence

Red-first check:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result before implementation: `1 test files, 735 assertions, 1 failures`
- Failure: the new declared-operator case expected `reviewop` and `argreview` macro definitions, but `macroDefinitionsFromDocument()` returned an empty array.

Final focused checks:

- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php`
- Result: `1 test files, 745 assertions, 0 failures`
- Baseline before this slice: `1 test files, 734 assertions, 0 failures`
- Focused assertion delta: `+11`

Example smoke:

- `php lanes/pandoc/examples/wordpress-math-tex-handoff.php --self-test`
- Result: `math tex handoff self-test ok`

Counters:

- `lane-status.json` `phpPass`: `1645 -> 1646`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2065 -> 2066`
- `mathTexConversionCoreCases`: `14 -> 15`
- `mappedMathTexConversionCoreCases`: `14 -> 15`
- `mathTexConversionCoreAssertions`: `85 -> 96`

## Non-Overlap

This does not touch ZIP/OPC/package primitives, DOCX/ODF/EPUB/PDF handoff, CSL/BibTeX, YAML, charset, table geometry, syntax highlighting, or upstream-runner audit behavior. It also does not shell out to Pandoc, texmath, MathJax, KaTeX, TeX/PDF engines, Cabal, Haskell runners, Word, LibreOffice, zip/unzip, or online services.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP primitives in `MathTexConverter`: raw TeX collection from AST nodes, brace/bracket TeX argument parsing, macro expansion, and MathML serialization. The local worktree still has no Pandoc upstream checkout or texmath runner, so full upstream runner parity remains out of scope for this isolated micro-slice.

## Follow-Up

A non-overlapping follow-up would be MarkdownReader capture of starred `\DeclareMathOperator*` source lines as raw TeX declarations, or another bounded texmath command handoff, still without invoking external converters or TeX engines.
