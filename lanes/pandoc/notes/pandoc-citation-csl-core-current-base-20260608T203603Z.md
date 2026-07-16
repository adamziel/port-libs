# Pandoc Citation/CSL Current-Base Names Variable List

Slice: `pandoc-citation-csl-core-current-base-20260608T203603Z`  
Accepted base: `e76c4cc82ad1172514b0791041ad64c954f9e499`

## Source Truth

- CSL 1.0.2 specification, Names section: `cs:names` can select one or more name variables, and multiple selected variables are independently rendered in order. The spec example uses `variable="editor translator"` with a delimiter separating role groups.
- Source URL: https://docs.citationstyles.org/en/v1.0.2/specification.html#names

## Patch

- `CitationCslProcessor` now keeps grouped name-variable lookup data.
- Labeled `cs:names variable="editor translator"` renders editor and translator groups independently when both roles are populated, preserving each role label.
- Existing accepted fallback behavior for common `author editor` creator styles is preserved; the broad independent multi-variable migration remains a follow-up because current lane fixtures intentionally use fallback-style lists.
- Added a focused Citation/CSL regression and a WordPress handoff smoke example.

## Evidence

- No `port-pandoc-*.needs-lane-rework.md` notes existed before the slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2834 assertions, 0 failures`
- Red-first after adding the focused regression:
  - `1 test files, 2840 assertions, 1 failure`
  - Expected translator group was omitted: actual citation was `(edited by Curator and Ng 2026)`.
- Final focused test:
  - `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2843 assertions, 0 failures`
- Example smoke:
  - `php lanes/pandoc/examples/wordpress-citation-csl-names-variable-list-handoff.php --self-test`
  - `wordpress-citation-csl-names-variable-list-handoff self-test passed`
- PHP lint:
  - `php -l lanes/pandoc/src/CitationCslProcessor.php`
  - `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `php -l lanes/pandoc/examples/wordpress-citation-csl-names-variable-list-handoff.php`
  - all reported no syntax errors.
- JSON validation:
  - `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } } echo "pandoc JSON validation passed\n";'`
  - `pandoc JSON validation passed`
- Diff hygiene:
  - `git diff --check -- lanes/pandoc`
  - passed with no output.

## Status Delta

- `lane-status.json` `phpPass`: `1820 -> 1821`
- `UPSTREAM_TEST_MANIFEST.json` mapped denominator: `2244 -> 2245`
- `mappedCitationCslCoreCases`: `12 -> 13`

## Dependency Closure

No new support component is needed. This slice reuses native `CslStyle` parsing, `CitationCslProcessor` name rendering, `MarkdownReader` citation handoff, and `WordPressBlockWriter` output. No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell runner, external bibliography manager, online service, live provider test, or live-service provider test was executed.

## Non-Overlap

This does not repeat recent Citation/CSL date-part, subsequent et-al, et-al-use-last, subsequent-author substitution rule, uncertain-date, or institution short-parts slices. It also avoids unrelated charset, ODF, DOCX, ZIP/OPC, math, table, and upstream-runner audit surfaces.

## Follow-Up

Broader independent multi-variable `cs:names` rendering should be migrated separately after the accepted fallback-style `author editor` and extended creator fixtures are updated. The identical editor/translator `editortranslator` term exception is also still open.
