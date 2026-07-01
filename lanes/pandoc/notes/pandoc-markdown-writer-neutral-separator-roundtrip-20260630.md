# Pandoc Markdown Writer Neutral Separator Round Trip - 2026-06-30

Bead: `plib-ya3ce`

Scope:
- Completed the upstream-mapped Markdown writer top-level fixture where an
  indented code block follows a list.
- Preserved the writer's Pandoc-style neutral `<!-- -->` separator output.
- Taught the Markdown reader to treat only a standalone neutral separator line
  as non-semantic during top-level round trips.
- Kept ordinary HTML comments, including `<!-- writer comment -->`, on the
  raw HTML path.

Accounting:
- `phpPass`: `469 -> 470`
- Existing `mappedMarkdownWriterTopLevelCases` remains the relevant manifest
  bucket; this slice turns its indented-code-after-list fixture green.

Validation:
- `php -l lanes/pandoc/src/MarkdownReader.php`
- `php -l lanes/pandoc/tests/MarkdownWriterTopLevelFixtureCompletionTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterTopLevelFixtureCompletionTest.php`
  - 1 file, 18 assertions, 0 failures

Residual:
- Broader Markdown writer and raw HTML sweeps remain baseline-red outside this
  slice, including block-boundary, code-info, raw HTML block, and table/list
  geometry failures.
- Full `lanes/pandoc/tests` remains baseline-red: 303 files, 118,745
  assertions, 9,628 failures.
