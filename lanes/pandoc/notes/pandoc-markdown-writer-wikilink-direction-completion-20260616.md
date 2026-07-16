# Pandoc Markdown writer wikilink direction completion

## Scope

- Added native PHP `MarkdownWriter` support for `wikilinks_title_after_pipe` and `wikilinks_title_before_pipe` output direction.
- Direction extensions now enable compact wikilink output for CommonMark/GFM writer profiles when explicitly requested.
- Added writer aliases for `wiki_link`, `wikilink`, singular title-direction names, and plural title-direction names.
- Kept attributed/titled wikilinks on explicit link syntax and preserved existing HTML fallback behavior when wikilinks are disabled.

## Accounting

- `mappedMarkdownWriterWikiLinkDirectionCompletionCases`: 6
- `markdownWriterWikiLinkDirectionCompletionAssertions`: 32
- `phpPass`: 16779 -> 16786
- `phpFail`: 0
- Mapped upstream/root inventory cases: 16333 -> 16339
- Benchmark denominator mapped cases: 3471 -> 3477
- Full Pandoc PHP coverage: 219 -> 220 files, 171503 -> 171535 assertions

## Verification

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownWriterWikiLinkDirectionCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterWikiLinkDirectionCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterWikiLinkDirectionCompletionTest.php lanes/pandoc/tests/MarkdownReaderWikiLinkDirectionCompletionTest.php lanes/pandoc/tests/MarkdownWriterWikiLinkSurgeTest.php lanes/pandoc/tests/MarkdownWriterFlavorFallbackSurgeTest.php lanes/pandoc/tests/MarkdownWriterInlineHtmlFallbackAliasSurgeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests`

No Pandoc, cmark/commonmark runners, browser renderers, Node tooling, external validators, online services, live provider tests, or live-service provider tests are invoked.
