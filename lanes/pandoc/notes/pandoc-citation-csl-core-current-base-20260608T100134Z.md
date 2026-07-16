# Pandoc Citation/CSL Mixed Disambiguation Ordering Handoff

Micro-slice: `pandoc-citation-csl-core-current-base-20260608T100134Z`
Base accepted HEAD: `0556f6c8205c8f235f6b3f8f8751917296dbd9c3`
Date: 2026-06-08 UTC

## Behavior

- Added bounded native CSL ordering for `disambiguate-add-givenname`,
  `disambiguate-add-names`, and `disambiguate-add-year-suffix`.
- Given-name disambiguation now keeps the best partial improvement before
  hidden-name expansion runs, matching CSL's method order instead of requiring
  given names to make the whole group unique before they are preserved.
- Add-names and year-suffix grouping now use the already selected given-name
  mode, so suffixes are only assigned to entries still ambiguous after given
  names and added visible names.
- Added a WordPress handoff example for imported source packets where one cite
  is separated by given names, one by adding a second name, and only the still
  identical pair receives `2026a` / `2026b`.

## Source Truth And Non-Overlap

Source truth: CSL 1.0.2 citation disambiguation specifies the method order as
given-name expansion first, adding hidden names second, `disambiguate`
conditions third, and year suffixes last. It also states that when
`disambiguate-add-givenname` and `disambiguate-add-names` are both true, given
names are applied first and added names are used only if needed afterward.

Reference: https://docs.citationstyles.org/en/v1.0.2/specification.html

This slice does not repeat standalone given-name disambiguation, standalone
add-names expansion, standalone year-suffix assignment, add-names/year-suffix
collapse, et-al threshold rendering, subsequent-author substitution, locator
labels, page-range formatting, note-style first-reference numbering, or
BibTeX/BibLaTeX metadata handoffs. It only owns the mixed ordering interaction
between given-name preservation, added-name counts, and final year-suffix
grouping.

No Pandoc, citeproc, BibTeX, Biber, Cabal solver/build/test command, Haskell
runner, external bibliography manager, online service, live provider test, or
live-service provider test was executed.

## Evidence

- Rework check: no
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md`
  file existed for this lane.
- Baseline focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2386 assertions, 0 failures`.
- Red-first focused run after adding the regression:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 2390 assertions, 1 failures` because
  add-names/year-suffix produced `Smith, Doe, et al. 2026a,b,c` before
  preserving given-name disambiguation.
- Final focused run:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 2411 assertions, 0 failures`.
- Example smoke:
  `php lanes/pandoc/examples/wordpress-citation-csl-given-add-names-year-handoff.php --self-test`
  passed.
- Root harness: not run - isolated micro-slice.

## Status Delta

- `lane-status.json` `phpPass`: `1607 -> 1608`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2026 -> 2027`.
- `UPSTREAM_TEST_MANIFEST.json` `mappedCitationCslCoreCases`: `12 -> 13`.
- Focused coverage delta: `+1` PHP PASS case and `+25` assertions in
  `CitationCslProcessorTest.php`.

## Dependency Closure

No new support component is needed. The implementation reuses native
`CslStyle` XML parsing, `CitationCslProcessor` disambiguation annotations,
`MarkdownReader` citation parsing, `WordPressBlockWriter` bibliography output,
focused Citation/CSL tests, and the lane-local WordPress handoff example.

Remaining exclusions for future work: bibliography-side disambiguation detail
metadata, note-style disambiguation context, added-name given-name expansion
for non-primary visible names, full citeproc parity, and upstream
Pandoc/Haskell runner parity.
