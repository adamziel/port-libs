# Pandoc Citation/CSL Current-Base Subsequent Author Substitute

## Source Truth

- Lane: `pandoc`
- Micro-slice: `pandoc-citation-csl-core-current-base-20260605T100451Z`
- Accepted base: `8be86cd41bb40ca9b82306af945c892eeca809a2`
- Upstream behavior cluster: CSL bibliography `subsequent-author-substitute` / `subsequent-author-substitute-rule` handoff for repeated rendered creator lists.
- Scope limit: native PHP support-library behavior only. No Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runner, Word, LibreOffice, zip/unzip, TeX/PDF engine, browser renderer, online service, or external bibliography manager was executed.

## Implementation

- `CslStyle` now parses bibliography `subsequent-author-substitute` and validates `subsequent-author-substitute-rule` against CSL rule names.
- `CitationCslProcessor` now keeps ordered-bibliography substitution state so a repeated rendered creator list can be replaced in subsequent bibliography entries while single-entry rendering remains unchanged.
- The bounded renderer applies complete repeated rendered-name substitution for bibliography lists and preserves the configured rule in the style summary for downstream handoff.
- Added a WordPress smoke example that imports Markdown citations, appends a CSL bibliography, and verifies the second consecutive Smith entry renders with the substitute marker in a definition-list block.

## Verification

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed before implementation with `1 test files, 967 assertions, 1 failures` because `subsequentAuthorSubstitute` was not parsed.
- `php -l lanes/pandoc/src/CslStyle.php`
- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php -l lanes/pandoc/examples/wordpress-citation-csl-subsequent-author-handoff.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`: `1 test files, 981 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-citation-csl-subsequent-author-handoff.php --self-test`: passed.
- `php tools/run-tests.php lanes/pandoc/tests`: `20 test files, 10083 assertions, 0 failures`.
- JSON validation for `lanes/pandoc/lane-status.json` and `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`: passed.
- `git diff --check -- lanes/pandoc`: passed.

## Status Delta

- `lanes/pandoc/lane-status.json` `phpPass`: `821` -> `822`.
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` mapped checks: `1281` -> `1282`.
- `mappedCitationCslCoreCases`: `10` -> `11`.
- Focused citation test assertion count: `967` red-first baseline -> `981` green after implementation.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, and `WordPressBlockWriter` paths. Full upstream Pandoc/citeproc runner parity still requires a hydrated Pandoc checkout and Cabal test-suite dependency plan, which is outside this isolated micro-slice.

## Non-Overlap And Follow-Up

This slice does not repeat the accepted CSL date-part, name-particle, locator/page label, citation-number/collapse, year-suffix, BibTeX shorthand, or BibLaTeX crossref handoff clusters. Follow-up CSL work should split `partial-each`, `partial-first`, and `complete-each` name-part substitution semantics, punctuation-in-quote locale behavior, note-style near-note behavior, and full citeproc parity into separate bounded slices.
