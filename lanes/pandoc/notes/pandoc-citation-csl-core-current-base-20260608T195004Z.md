# pandoc-citation-csl-core-current-base-20260608T195004Z

## Scope

Added bounded native Citation/CSL support for CSL date variables `available-date`
and `submitted`, reusing the existing `CitationCslProcessor` date
normalization, rendering, conditional, and bibliography sort paths.

Source truth: CSL 1.0.2 lists `available-date` and `submitted` as date
variables. This slice ports the date-variable contract only; it does not call
Pandoc, citeproc, BibTeX, Biber, Cabal, Haskell runners, or external
bibliography managers.

## Behavior Added

- Normalizes direct CSL item `available-date`, `availableDate`, `submitted`,
  `submitted-date`, and `submittedDate` date objects into native date metadata.
- Renders `cs:date variable="available-date"` and
  `cs:date variable="submitted"` through the existing date formatter.
- Supports `cs:text` handoff variables for status, raw value, time/end-time,
  and season/season-name metadata for both date variables.
- Includes both variables in date marker/time/season summaries and in
  `is-uncertain-date` / `is-circa-date` presence checks.
- Allows bibliography sort keys to use `available-date` and `submitted`.
- Adds a WordPress smoke for Markdown citation replacement and bibliography
  sort/rendering using the new variables.

## Evidence

Baseline:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 2700 assertions, 0 failures
```

Red-first after adding only the focused test:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
FAIL renders bounded csl available and submitted date variables
Expected: array (0 => 2026, 1 => 6)
Actual: NULL
1 test files, 2701 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php
1 test files, 2723 assertions, 0 failures
```

Example smoke:

```text
php lanes/pandoc/examples/wordpress-citation-csl-available-submitted-date-handoff.php --self-test
wordpress-citation-csl-available-submitted-date-handoff self-test passed
```

## Non-Overlap

This slice avoids the already accepted Citation/CSL clusters for locator/page
ranges, date-parts precision, date markers for `issued`/`accessed`/
`original-date`/`event-date`, creator-role rendering, disambiguation, et-al
behavior, subsequent-author substitution, and audiovisual creator roles.

## Dependency Closure

No new support component is needed. The implementation reuses existing native
CSL style parsing and date rendering. Full upstream Pandoc runner parity,
external citeproc behavior, BibTeX/Biber execution, Cabal/Haskell builds,
online services, live provider tests, and live-service provider tests remain
out of scope for this bounded PHP support-library slice.

## Next Task

Pick another non-overlapping Citation/CSL or BibTeX/BibLaTeX handoff gap with
focused PHP tests, such as an unmapped CSL variable family or bounded
style-rendering edge. Do not execute external converters or bibliography
engines from this lane.
