# pandoc-citation-csl-core-current-base-20260608T170447Z

## Scope

Implemented bounded CSL `part-number` number-variable rendering in the native PHP Citation/CSL renderer. Styles can now render normalized item `part` metadata through:

- `cs:label variable="part-number"` using existing localized `part` terms;
- `cs:number variable="part-number"` using existing numeric, ordinal, long-ordinal, and roman formatting;
- `cs:text variable="part-number" form="ordinal|long-ordinal|roman|numeric"` using the same bounded number formatter.

## Source Truth

- CSL 1.0.2 specifies `part-number` as a number variable; number variable terms are rendered through the corresponding `part` term. The lane already normalized item `part` metadata and carried default `part` terms, so this slice maps the source style variable onto the existing native CSL number, label, and text-number rendering contracts.
- Source link: https://docs.citationstyles.org/en/v1.0.2/specification.html#number-variables

## Evidence

- Rework notes: no `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-pandoc-*.needs-lane-rework.md` note existed for this lane slice before editing.
- Red-first: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2575 assertions, 1 failures`
- Initial failure: `CSL citation label variable is not supported: part`. The final test was then tightened to the source style spelling `part-number` while preserving the same unsupported number/label path.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 2587 assertions, 0 failures`
- WordPress smoke: `php lanes/pandoc/examples/wordpress-citation-csl-part-number-handoff.php --self-test`
  - `wordpress-citation-csl-part-number-handoff self-test passed`

## Status Delta

- `lane-status.json` `phpPass`: `1695 -> 1696`
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2115 -> 2116`
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedCitationCslCoreCases`: `12 -> 13`
- Focused Citation/CSL assertion result: red-first `2575 assertions / 1 failure`; final `2587 assertions / 0 failures`.

## Dependency Closure

No new native PHP support component is needed. This slice reuses `CslStyle`, `CitationCslProcessor`, `MarkdownReader`, `WordPressBlockWriter`, focused Citation/CSL tests, and a WordPress handoff example. Full upstream Pandoc/citeproc runner parity remains separate and was not attempted.

## Non-Overlap

This slice is limited to CSL `part-number` rendering against normalized item `part` metadata. It does not repeat prior locator-label `part` handling, page-range formatting, citation-number numeric text forms, first-reference-note-number rendering, date ordinals, display-part metadata, name/substitute handling, BibTeX/BibLaTeX metadata mapping, or external citeproc behavior.

## Exclusions

Did not run Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, external bibliography managers, browser renderers, online services, live provider tests, or live-service provider tests.

Root harness not run - isolated micro-slice.
