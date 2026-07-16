# Pandoc Markdown Reader Raw Container Stabilization - 2026-06-26

Scope: bounded Markdown reader fixture stabilization for standalone single-line
raw HTML containers at block start.

This slice fixes the local parser ordering that let the generic HTML inline
fragment reader consume block-start `<del>...</del>`, `<ins>...</ins>`, and
`<button>...</button>` before the raw-container splitter. The dedicated upstream
mapped raw-container fixture now preserves these starts as raw open tag, parsed
plain Markdown body, and raw close tag blocks, including quoted attributes that
contain `>`.

It does not change ordinary paragraph-inline raw HTML behavior. The focused
inline guard still parses `Lead <del>...</del> trail`, `Lead <ins>...</ins>
trail`, and `Lead <button>...</button> trail` as paragraph raw inline tags.

Validation:

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderRawHtmlContainerSurgeTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderRawHtmlContainerSurgeTest.php`
  - 1 file, 487 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderRawInlineSurgeTest.php lanes/pandoc/tests/MarkdownReaderStandaloneVoidInlineTest.php lanes/pandoc/tests/MarkdownReaderInlineSurgeTest.php`
  - 3 files, 618 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php`
  - 1 file, 4335 assertions, 36 failures
  - Failures remain in the existing plain-writer/doctemplate backlog; the raw
    HTML regression block touched by this slice passes.

No Pandoc binary, Haskell/Cabal runner, browser, network fetch, or broad
converter shell-out was invoked.
