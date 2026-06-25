# Pandoc CommonMark Raw Boundary Completion - 2026-06-25

Bead: `plib-2l9y`

Scope:
- Restored native PHP Markdown/CommonMark raw HTML block capture for special raw starts, named raw elements, blank-line CommonMark block tags, and standalone custom elements.
- Preserved paragraph interruption for CommonMark raw block starts without letting generic custom tags interrupt an existing paragraph.
- Added strict raw opening tag validation before malformed tag capture.
- Preserved raw HTML continuations inside list items after stripping list indentation.
- Restored inline raw HTML for known/custom tags while leaving unknown non-custom tag names such as `<unsafe>` as escaped text.
- Restored partial `<html lang>` metadata imports while keeping declaration-only `<!DOCTYPE html>` inputs in the raw HTML path.

Verification:
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkParagraphRawBoundaryCompletionTest.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawHtmlInterruptCompletionTest.php lanes/pandoc/tests/MarkdownReaderRawInlineSurgeTest.php lanes/pandoc/tests/MarkdownReaderInlineGenericHtmlSurgeTest.php` - 4 files, 617 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderCommonMarkRawContainerBoundaryTest.php` - 1 file, 109 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderRawHtmlBlockFourthWaveTest.php` - 1 file, 401 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` - 1 file, 4,481 assertions, 0 failures

Residual:
- `MarkdownReaderRawHtmlBlockSurgeTest.php` still has 15 failures from structured HTML reader precedence for tags such as `pre`, `figure`, `details`, `svg`, `math`, `object`, `picture`, `meter`, and `progress`. That is a broader precedence slice and was not changed here because `MarkdownReaderTest.php` currently depends on several of those structured imports.
