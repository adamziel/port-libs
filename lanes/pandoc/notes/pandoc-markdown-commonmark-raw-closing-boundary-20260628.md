# Pandoc Markdown CommonMark Raw Closing Boundary - 2026-06-28

Scope: bounded native PHP MarkdownReader CommonMark raw HTML handling for
complete closing-tag lines at raw-block starts.

This slice restores blank-line-bounded raw HTML preservation for lines such as
`</span>`, `</section>`, and `</review-block>` when they start a block. The
behavior covers top-level blocks, first content in list items, and indented list
content after a blank line. Closing-tag lines still do not interrupt an active
paragraph; those remain inline raw HTML.

It does not broaden generic opening-tag behavior, so existing inline anchor and
generic-inline handling remains unchanged.

Status movement:

- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  `mappedMarkdownReaderCommonMarkRawClosingBoundaryCases`: `0 -> 5`.
- `lanes/pandoc/lane-status.json` `phpPass`: `459 -> 460`.

Validation:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkRawClosingBoundaryCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawClosingBoundaryCompletionTest.php`
  - 1 file, 17 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawClosingBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawListItemBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderInlineGenericHtmlSurgeTest.php`
  - 5 files, 459 assertions, 0 failures
