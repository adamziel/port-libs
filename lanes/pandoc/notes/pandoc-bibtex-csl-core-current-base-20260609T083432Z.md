# Pandoc BibTeX/CSL Core Current-Base Slice 2026-06-09T08:34:32Z

## Scope

Implemented bounded BibLaTeX split-field season month support for the native PHP BibTeX/CSL handoff. This maps split `year`/`month` fields with BibLaTeX season month codes `21` through `24` into CSL `season` metadata for `issued`, `accessed`, `original-date`, and `event-date` values.

This does not repeat the already accepted `date={YYYY-21}` string-season path. The new behavior covers split fields such as `year={2026}, month={21}`, `urlyear={2026}, urlmonth={24}`, `origyear={1999}, origmonth={23}`, and `eventyear={2025}, eventmonth={22}`.

## Implementation

- `BibtexCslParser` now carries split-date part metadata as `parts` plus optional `season`, so split season month codes can be emitted as CSL season dates without adding an invalid calendar month.
- Split season dates with a `day` field are rejected, matching the existing date-string season guard.
- Split date ranges using season month codes are rejected for this bounded slice because the current CSL handoff stores one season marker on the date object, not a separate season per range endpoint.
- Added a WordPress handoff smoke example showing season labels in citation and bibliography output.

## Evidence

Baseline before patch:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 4004 assertions, 0 failures
```

Red-first after adding the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL maps bounded biblatex split season month fields into csl date metadata
BibTeX month month must be between 1 and 12
1 test files, 4004 assertions, 1 failures
```

Final focused verification:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 4026 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-bibtex-csl-split-season-handoff.php --self-test
wordpress-bibtex-csl-split-season-handoff self-test passed
```

## Status Delta

- `phpPass`: `2530` -> `2531`
- `benchmarkDenominator.mapped`: `2898` -> `2899`
- `mappedBibtexCslCoreCases`: `7` -> `8`
- `bibtexCslCoreAssertions`: `121` -> `143`
- Focused assertion delta: `+22`

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP BibTeX parser, CSL date normalization/rendering, Markdown reader, and WordPress block writer. No Pandoc, BibTeX, Biber, Cabal/Haskell runner, Word, LibreOffice, zip/unzip, external template engine, TeX/PDF engine, browser renderer, online service, live provider, or live-service provider test was executed.

## Non-Overlap

This slice avoids the accepted BibTeX/CSL clusters for date-string season codes, split end-date ranges, available/submitted date variables, canonical three-part name suffixes, supplemental periodical type aliases, and existing CSL date rendering. It adds only the split-field season month-code path and the directly coupled WordPress smoke.
