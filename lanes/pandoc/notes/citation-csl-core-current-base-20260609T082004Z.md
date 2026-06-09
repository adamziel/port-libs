# Citation CSL Localized Date-Part Overrides

Micro-slice: `pandoc-citation-csl-core-current-base-20260609T082004Z`
Base accepted HEAD: `e8462716baed1244ed5b9f195429af80b17d479b`
Date: 2026-06-09 UTC

## Scope

Implemented bounded CSL localized date form support for child `date-part`
overrides on `date form="text"` and `date form="numeric"` elements. The port
now applies month/day/year override forms, `strip-periods`, `text-case`, and
`range-delimiter` while preserving unlisted localized date parts such as the
default year in text dates or default day in numeric dates.

The slice also fixes the CSL style parser so a date element's own `form`
attribute is not overwritten by the last child `date-part` form during summary
and rendering handoff.

## Source Truth

The bounded behavior follows CSL localized date semantics: localized date forms
are selected with `form="text"` or `form="numeric"`, `date-part` elements
control date-part rendering attributes such as month `form`, `strip-periods`,
day `form`, and `range-delimiter`, and date ranges choose the delimiter for the
largest rendered differing part.

No upstream Pandoc runner was executed. No external citeproc, BibTeX, Biber,
Cabal/Haskell test binary, Word, LibreOffice, zip/unzip, TeX/PDF engine,
browser renderer, online service, live-service provider test, or office
automation was run.

## Evidence

Before behavior test addition:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 4004 assertions, 0 failures`.

Red-first after adding the localized override assertion:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  failed with `1 test files, 4006 assertions, 1 failures`; the parser summary
  reported the date element form as child `ordinal` instead of parent `text`.

Final focused verification:

- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  passed with `1 test files, 4022 assertions, 0 failures`.

Expected status delta:

- `phpPass`: `2524 -> 2525`.
- mapped upstream inventory handoff: `2895 -> 2896`.
- Focused assertion count in the citation test file increased by 18.

## WordPress Handoff

Added `examples/wordpress-citation-csl-localized-date-part-handoff.php` to
exercise WordPress review output for:

- stripped short month terms in localized text dates;
- ordinal day overrides without losing the year;
- leading-zero month/day numeric accessed date ranges;
- year-month bibliography precision with month range delimiters.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
CSL XML parser, locale term lookup, date-part renderer, Markdown reader/writer,
and WordPress block writer. Follow-up citation work can continue on larger CSL
layout and bibliography behaviors without activating an external citeproc or
bibliography manager.

## Non-Overlap

This slice does not touch DOCX/OpenXML, ODT/ODF, EPUB3, ZIP/OPC, PDF engine
handoff, math/TeX conversion, syntax highlighting, archive compression, HTML5
DOM/raw controls, YAML metadata, or root dashboard/progress files.
