# Pandoc CSL direct sourcetitle alias

Slice: plib-0cxc3, 2026-06-11T17:00:25Z
Base: origin/main 0091a9f731aa3bd94f6a73c6ba46f204c49c66f1

This slice keeps CSL citation and bibliography handoff native to PHP while
normalizing compact direct CSL JSON `sourcetitle` fields into the shared
`source` metadata used by `source`, `sourceTitle`, and `source-title`.

## Coverage

- Direct CSL JSON `sourcetitle` input normalizes to `source`.
- CSL `source-title` and `sourcetitle` variables render the normalized value.
- Citation and bibliography sort keys can use the compact alias.
- WordPress review output keeps the same rendered citation and bibliography
  provenance.

## Verification

- `php -l lanes/pandoc/src/CitationCslProcessor.php`
- `php -l lanes/pandoc/tests/CitationCslProcessorTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/CitationCslProcessorTest.php`
  - 1 test files, 4649 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 64235 assertions, 0 failures
