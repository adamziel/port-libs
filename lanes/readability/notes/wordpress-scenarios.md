# Readability/content rewrite engine WordPress Scenario

Migration-aware article cleanup into clean WordPress blocks with page-builder navigation, comment widgets, share UI, and surrounding theme chrome removed.

## Current Native Slice

Native DOM extractor removes chrome and upstream-style unlikely candidates, scores semantic content containers, implements Mozilla-style `isProbablyReaderable` thresholds, and emits simple WordPress blocks.

## Scenario Fixture

- `lanes/readability/fixtures/wordpress-page-builder.html` models a legacy WordPress page-builder article with sidebar navigation, comments, share widgets, retained media, and article paragraphs.
- `lanes/readability/examples/wordpress-migration-blocks.php` extracts that fixture and emits core block comments for migration workflows.

## Next Task

Map the first Mozilla `test-pages/*/{source.html,expected.html,expected-metadata.json}` fixture into a PHP parity fixture, then improve metadata/byline/media handling.
