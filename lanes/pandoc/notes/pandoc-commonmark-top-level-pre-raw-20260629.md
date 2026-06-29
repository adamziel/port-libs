# Pandoc CommonMark Top-Level Pre Raw Slice

Scope: native PHP Markdown/CommonMark reader fixture completion.

- `MarkdownReader` now keeps top-level CommonMark/GFM `<pre>...</pre>` starts on the raw HTML block path instead of routing them through the default HTML-import pre/code helper.
- Default Markdown/HTML import behavior is unchanged: bare `<pre>` without a CommonMark-family format still parses to a code block for existing HTML reader handoff cases.
- Added a focused upstream-mapped CommonMark raw HTML reader case and, after rebasing over the sibling CommonMark raw-block slice, bumped parity accounting to 2,308 mapped checks.

Validation:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php`
- Default/import smoke: default `<pre>` returns `code_block`, CommonMark `<pre>` returns `raw_html`.
