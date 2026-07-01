# Pandoc Markdown Reader Raw Attribute Inline Family Completion - 2026-07-01

## Scope

Completed a bounded native PHP `MarkdownReader` slice for upstream
`test/command/parse-raw.md`-style raw attribute inline handoff.

Code spans followed by raw attributes now classify known Pandoc raw families
the same way as JSON/native ingestion:

- HTML-family formats (`html`, `html4`, `html5`, `xhtml`, and extension
  variants) become `raw_html_inline` while preserving the original format.
- TeX-family formats (`tex`, `latex`, `context`, and extension variants)
  become `raw_tex_inline`.
- Markdown-family formats (`markdown`, `pandoc`, `commonmark`, `commonmark_x`,
  `gfm`, and extension variants) become `raw_markdown`.
- Unknown formats such as `opml` remain generic `raw_inline`.

The slice also keeps typed raw inline payload text visible in paragraph, image
caption, and link-label summaries, and applies the existing Markdown
escape/entity decoding path to attribute ids and classes.

## Validation

- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderRawAttributeInlineFamilyCompletionTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderFlavorExtensionProfileCompletionTest.php`
- `php -l lanes/pandoc/tests/MarkdownReaderInlineRawLabelResidualSurgeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderRawAttributeInlineFamilyCompletionTest.php lanes/pandoc/tests/MarkdownReaderFlavorExtensionProfileCompletionTest.php lanes/pandoc/tests/MarkdownReaderRawInlineSurgeTest.php`
  - 3 files, 379 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderInlineRawLabelResidualSurgeTest.php`
  - 1 file, 56 assertions, 1 existing dollar-math label residual failure
- `php tools/run-tests.php lanes/pandoc/tests`
  - remains baseline-red outside this slice with 345 files, 126934
    assertions, and 9261 failures

No Pandoc, Haskell/Cabal, browser, office suite, TeX/PDF engine, external
validator, online service, or live provider was invoked.
