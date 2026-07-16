# Pandoc Citation/CSL Archive Collection Handoff

Slice: `pandoc-citation-csl-core-current-base-20260609T071750Z`

Base accepted HEAD: `606e24ec818a38feb2a796c2f2b7d182ce531afd`

## Behavior

Implemented one bounded Citation/CSL support-library cluster for archive collection metadata:

- Normalizes direct CSL item aliases `archive_collection`, `archive-collection`, and `archiveCollection` into `archiveCollection`.
- Renders CSL text variables `archive_collection`, `archive-collection`, and `archiveCollection`.
- Includes archive collection in generated archive summaries while preserving previous archive/place/location summaries when no collection is present.
- Adds archive collection to default bibliography archive metadata.
- Allows archive-related variables to participate in CSL sort keys.
- Adds a WordPress handoff smoke proving archive collection citation sorting, bibliography summaries, and review blocks.

## Evidence

- Baseline focused run before this patch:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  `1 test files, 3933 assertions, 0 failures`
- Red-first focused run after adding the test before the processor fix:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  `1 test files, 3936 assertions, 1 failures`
  Failure: `archive-collection` did not populate normalized `archiveCollection`.
- Final focused run after the processor fix:
  `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  `1 test files, 3950 assertions, 0 failures`

## Status Delta

- Added one focused PHP PASS case.
- Added 17 focused assertions over the current baseline.
- `lane-status.json` `phpPass`: `2482 -> 2483`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2861 -> 2862`.
- `mappedCitationCslCoreCases`: `12 -> 13`.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP `CitationCslProcessor`, `CslStyle` variable/sort handling, `MarkdownReader`, `WordPressBlockWriter`, and focused Citation/CSL test harness. It does not require Pandoc, Cabal/Haskell runners, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF engines, browser renderers, online services, BibTeX, Biber, or citeproc.

## Non-Overlap

This does not repeat the accepted BibTeX `@misc` document-type routing, camelCase publication aliases, source sort keys, eprint archive summaries, source variables, locator/date/numeric conditionals, choose match semantics, creator conditionals, or BibLaTeX metadata slices. It owns only CSL archive collection variable aliases and their native citation, bibliography, sorting, and WordPress handoff behavior.

## Follow-Up

A next non-overlapping Citation/CSL slice can target another CSL variable alias or style rendering/sort edge. Broader citeproc parity and external bibliography manager execution remain out of scope for this lane.
