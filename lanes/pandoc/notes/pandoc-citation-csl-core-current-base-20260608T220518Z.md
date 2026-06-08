# Pandoc Citation/CSL Current-Base Editortranslator Term

Slice: `pandoc-citation-csl-core-current-base-20260608T220518Z`  
Accepted base: `744d742adbbbf391182231a7a5b4f2d0d558edc2`

## Source Truth

- CSL 1.0.2 `cs:names` can select multiple name variables in order.
- CSL locales define combined editor/translator role terms with `editortranslator`; when one creator list is both editor and translator, citeproc-style rendering uses the combined term instead of repeating the same names under two role labels.
- Source URL: https://docs.citationstyles.org/en/v1.0.2/specification.html#names

## Patch

- `CslStyle` now includes bounded default `editortranslator` long, short, verb, and verb-short terms.
- `CitationCslProcessor` detects `cs:names variable="editor translator"` when both populated role lists are identical and renders one name group labeled with `editortranslator`.
- Distinct editor and translator lists still use the existing independent role-group path.
- Added a focused Citation/CSL regression and a WordPress handoff smoke example.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` notes existed before the slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2973 assertions, 0 failures`
- Red-first after adding the focused regression:
  - `1 test files, 2977 assertions, 1 failures`
  - Actual output repeated the identical names as `(edited by Curator and Ng; translated by Curator and Ng 2026)` instead of using the combined `editortranslator` term.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2980 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-citation-csl-editortranslator-handoff.php --self-test`
  - `wordpress-citation-csl-editortranslator-handoff self-test passed`

## Status Delta

- `lane-status.json` `phpPass`: `1900 -> 1901`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2322 -> 2323`
- `mappedCitationCslCoreCases`: `12 -> 13`

## Dependency Closure

No new support component is needed. This slice reuses native `CslStyle` locale-term parsing, `CitationCslProcessor` name rendering, `MarkdownReader` citation handoff, and `WordPressBlockWriter` output. No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat recent Citation/CSL date-part, subsequent et-al, et-al-use-last, subsequent-author substitution rule, uncertain-date, institution short-parts, or the prior independent editor/translator variable-list slice. That prior slice remains covered for non-identical role lists; this patch only adds the identical-list `editortranslator` exception.

## Follow-Up

Broader independent multi-variable `cs:names` migration is still open outside the bounded `editor translator` path. A later slice can also map additional BibLaTeX editorial role combinations into explicit CSL editor-translator creator variables.
