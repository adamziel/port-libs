# Pandoc CommonMark Type 7 Raw Boundary - 2026-07-02

Bead: `plib-nzf`

Scope:
- Added CommonMark blank-line raw HTML block handling for complete generic type-7 HTML tag lines at block start, such as `<span data-review="type-7">`.
- Extended the same complete-tag boundary rule to initial list items after list marker stripping, preserving the raw source lines inside the list item until the next blank line.
- Kept paragraph non-interruption intact: complete generic tag lines inside an open paragraph remain inline raw HTML/text on the Markdown paragraph path, and malformed tag starts remain Markdown paragraphs.
- Preserved direct-format parity accounting with 4 mapped CommonMark type-7 raw-boundary cases and 13 focused assertions in `UPSTREAM_TEST_MANIFEST.json`.

Verification:
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkType7RawBoundaryCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkType7RawBoundaryCompletionTest.php` - 1 file, 13 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawListItemBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawPrecedenceCompletionTest.php` - 4 files, 164 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderStandaloneVoidInlineTest.php` - 1 file, 24 assertions, 0 failures

Baseline:
- `MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php` and `MarkdownReaderCommonMarkRawClosingBoundaryCompletionTest.php` still have two existing paragraph `attr('text')` expectation failures on `origin/main`; the failing assertions are unchanged by this slice and were reproduced in a temporary origin/main worktree.

External tools:
- No Pandoc, citeproc, BibTeX, Biber, office suite, TeX/browser engine, Typst, Jupyter, Node, zip/unzip, validators, or live services were invoked.
