# BibTeX CSL Publication Detail Alias Handoff

Date: 2026-06-30
Issue: plib-mvdk4

## Summary

`BibtexCslProcessor` now preserves legacy BibLaTeX publication and review-detail
fields that the canonical CSL processor already normalizes:

- `pagination`
- `bookpagination` / `book-pagination`
- `part` / `part-number` / `partnumber`
- `printingnumber` / `printing-number` / `printnumber` / `print-number`
- `references`
- `dimensions` / `dimension`
- `division` / `subdivision`
- `scale`
- `entrysubtype` / `entry-subtype`

The fields flow into CSL item arrays, raw BibTeX provenance, direct bibliography
review text, styled CSL rendering, citation handoff, and WordPress bibliography
output without invoking Pandoc, BibTeX, Biber, citeproc, office tools, browser
engines, TeX engines, Node, zip/unzip, or external validators.

## Validation

- `php -l lanes/pandoc/src/BibtexCslProcessor.php`
- `php -l lanes/pandoc/tests/BibtexCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/BibtexCslProcessorTest.php`

Focused result: `1 test files, 682 assertions, 0 failures`.

## Non-Overlap

This slice does not change CSL style parsing, sorting, date rendering, name
rendering, source-file attachment policy, identifier lookup, package readers, or
external conversion behavior. It only closes the legacy `BibtexCslProcessor`
handoff gap for already-native BibLaTeX publication-detail aliases and keeps
direct-format parity accounting active for the broader Pandoc lane.
