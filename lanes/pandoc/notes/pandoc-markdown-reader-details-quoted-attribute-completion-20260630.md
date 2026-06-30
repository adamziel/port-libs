# Pandoc Markdown Reader Details Quoted Attribute Completion

## Scope

- Native PHP Markdown reader completion for upstream-style `<details>` / `<summary>` raw HTML handoff when attributes contain quoted `>` characters.
- No Pandoc, CommonMark runner, browser, HTML validator, Node tooling, online service, live provider test, or external parser is invoked.

## Implementation

- `MarkdownReader` now uses quote-aware HTML tag spans when balancing raw HTML element blocks.
- The details/summary raw HTML path now preserves full `<details ...>` openers instead of splitting at `>` characters inside quoted attribute values.
- Disabled raw HTML fallback remains unchanged: summary text becomes plain content and the Markdown body is still parsed.

## Mapping

- `lane-status.json` `phpPass`: `459 -> 460`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `2308 -> 2311`.
- Added `markdownReaderDetailsQuotedAttributeCases`: `3`.
- `mappedMarkdownReaderDetailsSummaryCases`: `13 -> 16`.
- `mappedMarkdownReaderDetailsSummaryWordPressCases`: `4 -> 5`.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderDetailsQuotedAttributeCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderDetailsQuotedAttributeCompletionTest.php`: 1 file, 17 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderDetailsQuotedAttributeCompletionTest.php lanes/pandoc/tests/MarkdownReaderRawHtmlContainerSurgeTest.php lanes/pandoc/tests/MarkdownReaderInlineGenericHtmlSurgeTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkCompleteRawTagBoundaryTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkSearchRawBlockCompletionTest.php lanes/pandoc/tests/MarkdownReaderStandaloneVoidInlineTest.php`: 7 files, 896 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 299 files, 117404 assertions, 9776 failures. This matches the existing broad baseline-red lane state; visible failures are outside this details quoted-attribute slice.
