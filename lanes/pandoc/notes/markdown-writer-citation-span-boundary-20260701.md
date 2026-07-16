# Markdown writer citation span boundary

Work item: `plib-auoi4`

## Summary

The Markdown writer now has a fixture-backed completion slice for the upstream
Markdown citation-span boundary fixture. The mapped cases preserve
author-in-text citations followed by attributed bracketed spans, marked
attributed spans, and note-style author citation suffixes.

The writer also keeps citation locators distinct from suffixes and escapes
braced citation ids, so locator forms such as `@roe, p. 9` and bracketed
citations with spaces or closing braces regenerate without collapsing into
note-style suffixes or malformed ids.

## Non-overlap

This slice does not change citation parsing, citeproc, bibliography rendering,
footnote collection, span attribute parsing, or reader fixture expectations. It
only completes bounded upstream-mapped Markdown writer fixture coverage and the
small citation rendering rules needed by that writer path.

## Validation

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownWriterCitationSpanBoundaryFixtureCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterCitationSpanBoundaryFixtureCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCitationSpanBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderInlineNoteCitationFixtureTest.php`
- `php tools/run-tests.php lanes/pandoc/tests` remained baseline-red with
  466 test files, 137,915 assertions, and 8,983 failures.

No Pandoc binary, office suite, TeX/browser engine, Node tooling, or external
validator was invoked.
