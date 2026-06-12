# csl-publisher-authority-aliases-20260612T022942Z

Slice: `plib-2ur07`, citation/bibliography CSL handoff.

This slice maps direct CSL JSON `institution`, `organization`, and `school`
publisher-authority aliases into canonical publisher metadata while preserving
the raw source fields for review. CSL style text variables with those names now
render from the normalized publisher value, so citation clusters, bibliography
entries, and WordPress bibliography handoff keep report, organization, and
thesis publisher authority visible.

Verification on current main `1cc64b1e16`:

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - `1 test files, 5087 assertions, 0 failures`
- `php tools/run-tests.php lanes/pandoc/tests`
  - `44 test files, 69662 assertions, 0 failures`

No Pandoc, citeproc, BibTeX, Biber, bibliography managers, browser renderers,
external validators, online services, live provider tests, or live-service
provider tests were invoked.
