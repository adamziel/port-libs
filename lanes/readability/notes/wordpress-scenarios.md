# Readability/content rewrite engine WordPress Scenario

Migration-aware article cleanup into clean WordPress blocks with page-builder navigation, comment widgets, share UI, and surrounding theme chrome removed.

## Current Native Slice

Native DOM extractor removes chrome and upstream-style unlikely candidates, scores semantic content containers, implements Mozilla-style `isProbablyReaderable` thresholds, extracts document titles before in-article headings, exposes basic byline/site/published/dir/lang metadata fields, preserves allowed video embeds while removing generic embedded widgets, maps Mozilla fixture pages including `normalize-spaces` and `embedded-videos`, and emits simple WordPress blocks.

## Scenario Fixture

- `lanes/readability/fixtures/wordpress-page-builder.html` models a legacy WordPress page-builder article with sidebar navigation, comments, share widgets, retained media, and article paragraphs.
- `lanes/readability/fixtures/mozilla/normalize-spaces/` copies Mozilla's `source.html`, `expected.html`, and `expected-metadata.json` fixture to keep whitespace-normalized article extraction tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/embedded-videos/` copies Mozilla's video fixture to keep YouTube, YouTube-nocookie, and Vimeo embeds available during migration while unrelated widgets are stripped by the native cleanup slice.
- `lanes/readability/examples/wordpress-migration-blocks.php` extracts that fixture and emits core block comments for migration workflows.

## Next Task

Map Mozilla `videos-2` or copy a full lazy-image fixture locally for closer expected-HTML parity, then improve metadata/media cleanup.
