# Markdown Writer Reference Attribute Brace Surge

Date: 2026-06-15 UTC

Scope: native PHP Markdown writer reference-link/image adjacency escaping. No
Pandoc, cmark/commonmark runner, Cabal/Haskell runner, browser renderer, Node
tooling, online service, live provider test, live-service provider test, or
external validator is invoked.

## Implemented

- `MarkdownWriter` now escapes literal leading `{...}` text immediately after
  link and image inlines, preventing reference links/images from absorbing the
  following text as an inline attribute block.
- Added `MarkdownWriterReferenceAttributeBraceSurgeTest.php` with 55 mapped
  reference-link/image cases covering titles, attributes, spaced destinations,
  softbreak labels, empty image labels, and reader round-trip behavior.

## Verification

- `php -l lanes/pandoc/src/MarkdownWriter.php`
- `php -l lanes/pandoc/tests/MarkdownWriterReferenceAttributeBraceSurgeTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterReferenceAttributeBraceSurgeTest.php`
  - 1 file, 166 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownWriterReferenceAttributeBraceSurgeTest.php lanes/pandoc/tests/MarkdownWriterInlineCompletionSurgeTest.php lanes/pandoc/tests/MarkdownWriterInlineLinkEscapeSurgeTest.php lanes/pandoc/tests/MarkdownWriterInlineSurgeTest.php`
  - 4 files, 597 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 73 files, 104107 assertions, 0 failures

## Accounting

- Rebased onto current main `2ea3da99f7`.
- `phpPass`: 6044 -> 6099
- `phpFail`: 0
- `UPSTREAM_TEST_MANIFEST` mapped cases: 6034 -> 6089
- `mappedMarkdownWriterReferenceAttributeBraceSurgeCases`: 55
- `markdownWriterReferenceAttributeBraceSurgeAssertions`: 166
