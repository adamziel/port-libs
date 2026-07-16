# Pandoc Markdown CommonMark Initial List Raw Boundary - 2026-06-28

Scope: bounded native PHP MarkdownReader CommonMark raw HTML boundary handling
for raw HTML that starts as the first content of a list item.

This slice extends the list-item initial HTML branch beyond the existing
`<div>` and `<button>` special cases. Initial list item content that starts with
CommonMark raw block markers now re-enters the block parser, preserving opaque
raw HTML for section-style blank-line blocks, HTML comments, script blocks, and
custom tag blank-line blocks before an indented paragraph resumes in the same
list item.

It does not invoke Pandoc, cmark/commonmark runners, Haskell/Cabal tooling,
browser engines, Node tooling, office suites, TeX/PDF engines, external
validators, network services, or live provider tests.

Status movement:

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `mappedMarkdownReaderRawHtmlListIssueCases`: `1 -> 5`.
- `lanes/pandoc/lane-status.json` `phpPass`: `458 -> 459`.

Validation:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkRawListItemBoundaryCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawListItemBoundaryCompletionTest.php`
  - 1 file, 25 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php`
  - 3 files, 176 assertions, 0 failures
