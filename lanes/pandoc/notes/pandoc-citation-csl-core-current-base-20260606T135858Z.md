# pandoc-citation-csl-core-current-base-20260606T135858Z

Accepted base: `7cab681ac262f77d28f83c3f5e2d54da93e01472`

## Slice

Implemented one bounded Citation/CSL support-library behavior: CSL `cs:if` and
`cs:else-if` branches can now declare `is-circa-date`. The native style parser
keeps the predicate in the style summary, and the citation processor evaluates
it against normalized CSL date variables (`issued`, `accessed`,
`original-date`, and `event-date`) by checking the existing `circa` marker.

This remains distinct from the existing bounded `is-uncertain-date` behavior:
`is-uncertain-date` still treats approximate dates as uncertain-compatible for
the prior accepted slice, while `is-circa-date` matches only dates whose
`circa` marker is true.

## Evidence

- `php -l lanes/pandoc/src/CslStyle.php` passed.
- `php -l lanes/pandoc/src/CitationCslProcessor.php` passed.
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-citation-csl-circa-date-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php` passed: `1 test files, 1712 assertions, 0 failures`.
- `php lanes/pandoc/examples/wordpress-citation-csl-circa-date-handoff.php --self-test` passed.
- Root harness: not run - isolated micro-slice.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP
`CslStyle` XML parser, `CitationCslProcessor` rendering pipeline, date-marker
normalization, Markdown reader, and WordPress block writer.

Out of scope for this slice: full citeproc parity, note-style output, richer
locale inheritance, citation-position disambiguation beyond existing bounded
predicates, hydrated upstream Pandoc/citeproc runner parity, and any external
Pandoc, BibTeX, Biber, or citeproc execution.
