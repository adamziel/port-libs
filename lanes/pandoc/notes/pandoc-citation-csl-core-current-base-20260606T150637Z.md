# pandoc-citation-csl-core-current-base-20260606T150637Z

Accepted base: `bc375bdb07bbeeec6db609f2a5c69fe6a4b80ba4`

## Slice

Implemented bounded native Citation/CSL bibliography display-part handoff for display elements nested inside non-display groups, macro references, and active `cs:choose` branches. This keeps existing top-level `display="left-margin|right-inline|block|indent"` behavior intact while allowing WordPress CSL bibliography output to preserve second-field layout metadata from realistic macro-structured CSL styles.

## Evidence

- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` failed at `1 test files, 1717 assertions, 1 failures` because `cslDisplayParts` was `NULL` for the nested macro/choose bibliography layout.
- Final: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed at `1 test files, 1720 assertions, 0 failures`.
- Example smoke: `php lanes/pandoc/examples/wordpress-citation-csl-nested-display-handoff.php --self-test` passed with `wordpress-citation-csl-nested-display-handoff self-test passed`.

## Non-Overlap

This does not repeat the accepted top-level CSL display handoff slice. The new case exercises nested display elements reached through a bibliography layout group, a macro reference, and URL-dependent `cs:choose` branches.

## Dependency Closure

No new support component is needed. The slice reuses the native `CslStyle`, `CitationCslProcessor`, AST, Markdown reader, and WordPress block writer support rows. No Pandoc, citeproc, BibTeX, Biber, Cabal/Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Next

Remaining Citation/CSL follow-up should stay bounded to native PHP style semantics such as richer bibliography sorting/collapse interactions, locale inheritance, note-style edge cases, or disambiguation details.
