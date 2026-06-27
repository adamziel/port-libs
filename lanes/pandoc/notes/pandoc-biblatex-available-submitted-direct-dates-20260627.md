# BibLaTeX Available/Submitted Direct Date Handoff

Date: 2026-06-27
Slice: `plib-iauje`

## Scope

This slice closes a bounded direct `BibtexCslProcessor` handoff gap for basic legacy BibLaTeX availability/submission dates. It maps:

- `available-date` / `availabledate`
- `availableyear` / `availablemonth` / `availableday`
- `submitted` / `submitted-date` / `submitteddate`
- `submittedyear` / `submittedmonth` / `submittedday`

into CSL `available-date` and `submitted` `date-parts` arrays.

The older `BibtexCslParser` path already supports richer available/submitted date metadata. This direct parser slice only claims simple year, year-month, and year-month-day date-parts.

## Evidence

Focused validation:

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`

Result: `1 test files, 719 assertions, 0 failures`.

The focused regression checks raw BibLaTeX field preservation, direct bibliography labels, styled CSL date rendering, citation handoff, and WordPress bibliography output without external citeproc.
