# Pandoc CommonMark Complete Raw Tag Boundary - 2026-06-29

Bead: `plib-nzf`

Scope:
- Added native `MarkdownReader` handling for CommonMark-family type-7 HTML block starts.
- Standalone complete HTML tag lines such as `span`, `strong`, `source`, `meta`, and closing tag lines now remain blank-line-bounded `raw_html` blocks.
- Higher-priority raw HTML forms, CommonMark block tags, and custom tags continue through the existing raw-block rules, so `textarea` closes at `</textarea>` and paragraph-contained generic tags remain inline raw HTML.
- The slice is scoped to `commonmark`, `commonmark_x`, and `gfm`; default Markdown standalone void-inline behavior remains unchanged.

Verification:
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkCompleteRawTagBoundaryTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkCompleteRawTagBoundaryTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkSearchRawBlockCompletionTest.php lanes/pandoc/tests/MarkdownReaderStandaloneVoidInlineTest.php` - 6 files, 240 assertions, 0 failures

No Pandoc executable, browser, office suite, TeX engine, external validator, Node tooling, or network service was invoked.

Accounting:
- `phpPass` moves from 458 to 459 with one focused CommonMark complete raw-tag boundary slice.
- `phpFail` remains 0.
