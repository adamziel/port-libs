# Readability/content rewrite engine WordPress Scenario

Migration-aware article cleanup into clean WordPress blocks with page-builder navigation, comment widgets, share UI, and surrounding theme chrome removed.

## Current Native Slice

Native DOM extractor removes chrome and upstream-style unlikely candidates, scores semantic content containers and article-body containers, parses UTF-8 HTML safely, implements Mozilla-style `isProbablyReaderable` thresholds, extracts document titles before in-article headings, exposes byline/site/published/dir/lang metadata fields from meta tags and JSON-LD, preserves allowed video embeds while removing generic embedded widgets, promotes lazy `data-src`/`data-srcset` images including short base64 placeholder cases, maps Mozilla fixture pages including `normalize-spaces`, `embedded-videos`, `videos-2`, and `lazy-image-3`, and emits simple WordPress blocks.

## Scenario Fixture

- `lanes/readability/fixtures/wordpress-page-builder.html` models a legacy WordPress page-builder article with sidebar navigation, comments, share widgets, retained media, and article paragraphs.
- `lanes/readability/fixtures/mozilla/normalize-spaces/` copies Mozilla's `source.html`, `expected.html`, and `expected-metadata.json` fixture to keep whitespace-normalized article extraction tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/embedded-videos/` copies Mozilla's video fixture to keep YouTube, YouTube-nocookie, and Vimeo embeds available during migration while unrelated widgets are stripped by the native cleanup slice.
- `lanes/readability/fixtures/mozilla/videos-2/` copies Mozilla's video-heavy Liberation fixture to keep JSON-LD byline/site/date metadata, UTF-8 article text, article-body selection, and seven editorial video embeds tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/lazy-image-3/` copies Mozilla's small lazy image fixture to keep JavaScript-free `data-src` image promotion tied to a named upstream page.
- `lanes/readability/examples/wordpress-migration-blocks.php` extracts that fixture and emits core block comments for migration workflows.

## Next Task

Copy Mozilla `lazy-image-1` or `lazy-image-2` locally for broader exact expected-HTML parity, then improve metadata/media cleanup.
