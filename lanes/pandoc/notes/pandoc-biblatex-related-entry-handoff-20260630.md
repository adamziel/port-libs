# Pandoc BibLaTeX Related Entry Handoff

Slice: `plib-erxda`, Citation/CSL core blocker.

Implemented a bounded native PHP legacy BibLaTeX related-entry handoff:

- `BibtexCslProcessor` now resolves `related` keys against entries in the same bibliography and carries matched records as `relatedItems`.
- Missing related keys are preserved as `missingRelatedKeys` diagnostics instead of being dropped.
- Raw `related`, `relatedtype`, `relatedstring`, and `relatedoptions` remain intact, while parsed `relatedKeys` and `relatedOptions` feed CSL relation variables.
- Direct bibliography text and styled CSL rendering can now show resolved related entry titles, missing-key diagnostics, and relation options without external citeproc.

Validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `1 test files, 1141 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - `2 test files, 7253 assertions, 0 failures`
- `git diff --check -- lanes/pandoc`

No Pandoc, citeproc, BibTeX, Biber, bibliography manager, office suite, TeX/PDF engine, browser, Node tooling, network lookup, or external validator was invoked.
