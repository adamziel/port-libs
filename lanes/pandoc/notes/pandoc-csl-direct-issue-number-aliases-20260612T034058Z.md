## Pandoc CSL Direct Issue Number Aliases

Slice: `pandoc-csl-direct-issue-number-aliases-20260612T034058Z`

### Behavior

- Accepts direct CSL JSON `issue-number`, `issueNumber`, and `issuenumber` fields as aliases for canonical `issue`.
- Renders `issue-number` and `issuenumber` style variables from the normalized issue value for bounded compatibility handoff.
- Maps `issue-number` labels back to the CSL `issue` term, so label, number, text number-form, sort, and `is-numeric` behavior remain consistent with canonical `issue`.

### Evidence

- Added a focused Citation/CSL fixture covering direct JSON normalization, citation sort, `is-numeric`, `<label>`, `<number>`, `<text form="roman">`, bibliography rendering, and WordPress output.
- PHP lint passed for `lanes/pandoc/src/CitationCslProcessor.php`, `lanes/pandoc/src/CslStyle.php`, and `lanes/pandoc/tests/CitationCslProcessorTest.php`.
- Focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` -> `1 test files, 5159 assertions, 0 failures`.
- Full lane after rebase: `php tools/run-tests.php lanes/pandoc/tests` -> `44 test files, 70378 assertions, 0 failures`.
- No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test is required for this bounded native normalization path.
