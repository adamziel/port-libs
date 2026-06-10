# Legacy BibTeX/CSL Identifier Handoff

## Scope

- Preserve legacy `BibtexCslProcessor` CSL item metadata for BibTeX/BibLaTeX `pmid`, `pmcid`, `mrnumber`, `mrclass`, `zbl`, `jstor`, `hdl`, `lccn`, and `oclc` fields.
- Render those identifiers in the simple legacy bibliography text used by reviewer handoff packets.
- Keep the slice bounded to native PHP under `lanes/pandoc`.

## Verification

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`
  - 1 file, 100 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 files, 59891 assertions, 0 failures after rebasing on current `origin/main`.

## Accounting

- `phpPass` increments from 2960 to 2961 on top of the rebased `origin/main`.
- `phpFail` remains 0.
- No Pandoc, citeproc, BibTeX, Biber, bibliography manager, external validator, online service, or live provider test was invoked.
