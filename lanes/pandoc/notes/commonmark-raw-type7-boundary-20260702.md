# CommonMark Raw HTML Type 7 Boundary

Slice: `plib-cfe`

Implemented CommonMark-like raw HTML type 7 handling for the Markdown reader. Line-alone opening tags that are not covered by the higher-precedence CommonMark raw HTML classes now start blank-line-bounded raw HTML blocks, while custom tags with same-line content stay inline so Markdown inside the paragraph continues to parse.

Parity accounting: `markdownReaderCommonMarkRawHtmlInterruptCases` and `mappedMarkdownReaderCommonMarkRawHtmlInterruptCases` are 17 in `UPSTREAM_TEST_MANIFEST.json`.

Validation:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkRawClosingBoundaryCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawPrecedenceCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawListItemBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawClosingBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderRawHtmlContainerSurgeTest.php lanes/pandoc/tests/MarkdownReaderInlineGenericHtmlSurgeTest.php`

Broader exploratory gate `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderRawHtmlBlockFourthWaveTest.php lanes/pandoc/tests/MarkdownReaderRawHtmlBlockSurgeTest.php lanes/pandoc/tests/MarkdownCommonMarkSurgeTest.php` remains red on existing broad raw-block/CommonMark baseline expectations outside this slice: 3 files, 679 assertions, 19 failures.
