# Direct CSL Entry Subtype Genre Aliases

Scope: `plib-bugsq` citation/bibliography CSL core blocker slice.

This slice adds one bounded native PHP direct-CSL handoff behavior:

- Direct CSL JSON `entrySubtype`, `entry-subtype`, and `entrysubtype` still
  normalize into reviewable `entrySubtype` metadata.
- When an item has no explicit `genre`, those entry subtype aliases now also
  populate CSL `genre`, matching the existing BibTeX/BibLaTeX `entrysubtype`
  handoff path.
- Explicit direct `genre` values keep precedence while `entrySubtype` remains
  independently reviewable.
- CSL rendering exposes the normalized values through `genre` and
  `entry-subtype` in citation clusters, bibliography entries, and WordPress
  review output.

No Pandoc binary, citeproc, BibTeX, Biber, bibliography managers, browser
renderers, external validators, online services, live provider tests, or
live-service provider tests were invoked.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 5342 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `46 test files, 75812 assertions, 0 failures`

## Accounting

- New focused case: `normalizes bounded direct csl json entry subtype aliases into genre metadata`
- `phpPass`: `3362 -> 3363`
- Direct CSL mapped cases: `mappedCslDirectEntrySubtypeGenreAliasCases = 1`
- Focused assertions added: `cslDirectEntrySubtypeGenreAliasAssertions = 21`
