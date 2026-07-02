# Pandoc CSL direct sort-initials alias

Slice: `pandoc-csl-direct-sort-initials-alias-20260702`

Scope:

- Direct CSL JSON items now accept `sort-initials`, `sortInitials`, and
  `sortinitials` as aliases for normalized `sortInitial` metadata.
- CSL styles can use `sort-initials` in `<key variable="...">` and
  `<text variable="...">` paths with the same value as canonical
  `sort-initial`.
- Focused coverage keeps direct JSON citation clusters and appended WordPress
  bibliography review output ordered by the plural alias.

Validation:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  -> 1 file, 6193 assertions, 0 failures

No external Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine,
Typst, Jupyter, Node, zip/unzip, validators, or live services were invoked.
