# Pandoc Markdown Writer GitHub Raw Format Alias

## Status delta

- Added bounded native `MarkdownWriter` support for the legacy Pandoc `markdown_github` raw format alias.
- Raw inline and block payloads tagged `markdown_github` now stay on the Markdown writer path alongside the existing `markdown_strict`, `markdown_phpextra`, `markdown_mmd`, `commonmark_x`, `gfm`, and extension-qualified Markdown-family aliases.
- Extended the focused Markdown writer raw-format fixture to cover `~~github~~` inline raw payloads and task-list block payloads.

## Accounting

- `lane-status.json` `phpPass`: `3714 -> 3715`.
- `UPSTREAM_TEST_MANIFEST.json` `upstream.mapped`: `3736 -> 3737`.
- `UPSTREAM_TEST_MANIFEST.json` `inventory.mappedMarkdownWriterRawFormatAliasCases`: `1 -> 2`.

## Verification

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownReaderTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed: 1 file, 6778 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 46 files, 87987 assertions, 0 failures.
- `jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`
- `git diff --check -- lanes/pandoc/src/MarkdownWriter.php lanes/pandoc/tests/MarkdownReaderTest.php lanes/pandoc/UPSTREAM_TEST_MANIFEST.json lanes/pandoc/lane-status.json`

No Pandoc binary, Cabal/Haskell runner, browser renderer, external validator, online service, live provider test, or live-service provider test was invoked.
