# Pandoc Markdown Reader List Setext Heading Surge

## Scope

- Native PHP Markdown reader block/list completion for list-contained equals-underlined setext headings.
- No Pandoc, cmark/commonmark runners, Cabal/Haskell runners, browser renderers, Node tooling, online services, live provider tests, live-service provider tests, or external validators are invoked.

## Implementation

- `MarkdownReader` now carries accumulated list-item paragraph lines into equals-underlined setext heading parsing instead of flushing the text as a paragraph.
- Wrapped list-item continuation lines followed by equals setext underlines are guarded from simple-table classification.
- Nested list marker stripping now normalizes marker content at wide ordered-marker content columns while preserving existing base-indent behavior.
- Dash underline continuations remain covered as thematic-break behavior; this slice maps equals setext headings only.

## Mapping

- `phpPass`: 6085 -> 6135.
- `phpFail`: remains 0.
- Upstream mapped cases: 6075 -> 6125.
- `mappedMarkdownReaderBlockListSurgeCases`: 330 -> 380.
- `mappedMarkdownReaderListSetextSurgeCases`: 50.
- `markdownReaderBlockListSurgeAssertions`: 11257 -> 11933.
- `markdownReaderListSetextSurgeAssertions`: 676.

## Verification

- `php -l lanes/pandoc/src/MarkdownReader.php`.
- `php -l lanes/pandoc/tests/MarkdownReaderListSetextSurgeTest.php`.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderListSetextSurgeTest.php`: 1 file, 676 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderBlocksSurgeTest.php`: 1 file, 3355 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests`: 74 files, 104947 assertions, 0 failures.
- PHP JSON status/manifest validation, `git diff --check`, and exact conflict-marker scan passed.
