# Markdown reader inline-note citations

Work item: `plib-bwkgc`

## Summary

The Markdown reader now has a fixture-backed completion slice for the upstream
`cite-in-inline-note` command case plus adjacent Markdown note bodies that keep
citations nested inside inline notes. The fixture covers a normal bracketed
citation, an inline link with title followed by a bare citation locator, and a
code span containing `]` before a grouped citation.

The focused test asserts the reader AST, textual native writer output,
Markdown footnote serialization, and WordPress endnote handoff so citations,
links, and code inside notes survive the reader/writer path without shelling
out to Pandoc or external validators.

## Non-overlap

This slice does not change citation parsing, footnote definition collection,
table handling, raw HTML precedence, citeproc, or bibliography rendering. It
only completes bounded upstream-mapped Markdown reader fixture coverage and
fixes the focused native assertion to match the lane's textual NativeWriter
output.

## Validation

- `php -l lanes/pandoc/tests/MarkdownReaderInlineNoteCitationFixtureTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderInlineNoteCitationFixtureTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderInlineNoteCitationFixtureTest.php lanes/pandoc/tests/MarkdownReaderCitationSpanBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderAngleAutolinkFixtureCompletionTest.php`
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc`
- The latest `php tools/run-tests.php lanes/pandoc/tests` run for this slice
  remained baseline-red outside the focused path with 354 files, 128,089
  assertions, and 9,266 existing failures.

No Pandoc binary, office suite, TeX/browser engine, Node tooling, or external
validator was invoked.
