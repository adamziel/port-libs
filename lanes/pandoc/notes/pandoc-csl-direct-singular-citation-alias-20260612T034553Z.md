## Pandoc CSL Direct Singular Citation Alias Keys

Slice: `pandoc-csl-direct-singular-citation-alias-20260612T034553Z`

### Behavior

- Accepts direct CSL JSON singular citation alias fields `citationAlias`, `citation-alias`, and `citationalias` during native item normalization.
- Reuses the existing bounded alias list handling, so scalar singular aliases can still carry comma- or semicolon-delimited alias lists.
- Keeps canonical alias lookup behavior unchanged: alias citation keys resolve to the canonical item while preserving the resolved `citationAlias` provenance on the alias item.
- Keeps existing CSL output variables unchanged: `citation-alias`, `citation-aliases`, `citation-alias-summary`, and `citation-aliases-summary` render from the normalized alias list/summary.

### Evidence

- Focused regression added in `lanes/pandoc/tests/CitationCslProcessorTest.php` for direct CSL JSON singular alias keys, alias lookup, CSL rendering, and WordPress bibliography output.
- PHP lint passed for `lanes/pandoc/src/CitationCslProcessor.php` and `lanes/pandoc/tests/CitationCslProcessorTest.php`.
- Focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 5124 assertions, 0 failures`.
- Full lane after rebase: `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 70220 assertions, 0 failures`.
- No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test is required for this bounded native normalization path.
