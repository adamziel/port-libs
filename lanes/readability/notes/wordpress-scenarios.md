# Readability/content rewrite engine WordPress Scenario

Migration-aware article cleanup into clean WordPress blocks with page-builder navigation, comment widgets, share UI, in-article ad slots, and surrounding theme chrome removed.

## Current Native Slice

Native DOM extractor removes chrome and upstream-style unlikely candidates, removes ad wrappers such as `ad-container`, `ad-mobile`, `dfp-slot`, and `js_ad`, removes layout-only full-width figure wrappers while keeping editorial in-column figures, removes interactive article controls and leading byline/follow/read-time/share action bars before the first content heading while preserving avatar media, scores semantic content containers and article-body containers including body-only fallback pages, parses UTF-8 HTML safely, implements Mozilla-style `isProbablyReaderable` thresholds, extracts document titles before in-article headings, removes duplicate title headings from content, demotes remaining `h1` body headings to `h2` before block serialization, simplifies nested `div`/`section` wrappers, converts text/phrasing-only `div` blocks to paragraphs, collapses low-link-density `div` wrappers around a single paragraph, strips source CSS classes during post-processing, resolves relative `href`, `src`, `poster`, and `srcset` URLs against the source document URL and base element, replaces `javascript:` links with inert retained content, gives entity-decoded plain meta descriptions precedence for excerpts, exposes byline/site/published/dir/lang metadata fields from meta tags and JSON-LD, preserves allowed video embeds while removing generic embedded widgets, promotes lazy `data-src`/`data-srcset` images including short base64 placeholder cases, removes post-article recommendation/signup chrome after selected article wrappers, maps Mozilla fixture pages including `normalize-spaces`, `embedded-videos`, `videos-2`, `lazy-image-1`, `lazy-image-2`, `lazy-image-3`, `base-url`, `base-url-base-element`, `base-url-base-element-relative`, and `js-link-replacement`, and emits simple WordPress blocks.

## Scenario Fixture

- `lanes/readability/fixtures/wordpress-page-builder.html` models a legacy WordPress page-builder article with sidebar navigation, in-article ad slots, comments, share widgets, retained media, and article paragraphs.
- `lanes/readability/fixtures/mozilla/normalize-spaces/` copies Mozilla's `source.html`, `expected.html`, and `expected-metadata.json` fixture to keep whitespace-normalized article extraction tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/embedded-videos/` copies Mozilla's video fixture to keep YouTube, YouTube-nocookie, and Vimeo embeds available during migration while unrelated widgets are stripped by the native cleanup slice.
- `lanes/readability/fixtures/mozilla/videos-2/` copies Mozilla's video-heavy Liberation fixture to keep JSON-LD byline/site/date metadata, UTF-8 article text, article-body selection, and seven editorial video embeds tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/lazy-image-1/` copies Mozilla's Medium lazy image fixture to keep meta description precedence, promoted `data-old-src` article images, and post-article recommendation/signup cleanup tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/lazy-image-2/` copies Mozilla's Kinja lazy image fixture to keep entity-decoded excerpts, in-article ad cleanup, and 56 responsive image rows tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/lazy-image-3/` copies Mozilla's small lazy image fixture to keep JavaScript-free `data-src` image promotion tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/base-url/` copies Mozilla's no-base-element URL fixture to keep source-page relative links, root-relative links, hash links, and image sources tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/base-url-base-element/` copies Mozilla's root-base-element URL fixture to keep base href resolution tied to a named upstream page.
- `lanes/readability/fixtures/mozilla/base-url-base-element-relative/` copies Mozilla's base URL fixture to keep relative link and image URL cleanup tied to the upstream `http://fakehost/test/page.html` fixture URL.
- `lanes/readability/fixtures/mozilla/js-link-replacement/` copies Mozilla's `javascript:` link replacement fixture to keep inert span replacement and child-node preservation tied to a named upstream page.
- `lanes/readability/examples/wordpress-migration-blocks.php` extracts that fixture and emits core block comments for migration workflows.
- `lanes/readability/examples/wordpress-absolute-uri-migration.php` demonstrates resolving legacy relative links, image `src`, and responsive `srcset` candidates before WordPress block output.
- The focused WordPress test covers migration output where `post_title` stores the article title separately: duplicate source `h1` content is removed, while real body section headings remain as `h2` block headings.
- The focused WordPress full-width media test covers imports where a theme/page builder emits a layout-only crop outside the paragraph column: the decorative wrapper is removed while the editorial figure remains available for image block serialization.
- The focused WordPress class-cleanup test covers imports where legacy themes or block wrappers carry source CSS classes: nested layout wrappers are simplified, class attributes are stripped like upstream Readability default post-processing, and IDs plus promoted image sources remain available for block output.
- The focused WordPress leading-action test covers imports where a source platform places author, follow, read-time, and share controls before the first content heading: the controls are removed while avatar media and editorial body content remain available for block output.
- The focused WordPress quote-wrapper test covers imports where a source theme wraps a pull quote paragraph in a layout `div`: the quote text remains while the wrapper collapses to match upstream Medium blockquote output.
- The focused WordPress URL cleanup test covers imports where legacy relative links and images must remain usable after leaving the source domain: URLs are made absolute and `javascript:` links are neutralized while their child content is retained.
- The focused div-to-paragraph and body-fallback slice keeps legacy exports that lack semantic article wrappers from importing document-head markup, and makes text-only layout `div` blocks arrive as normal paragraph blocks.

## Next Task

Return to remaining Medium lazy-image structural HTML parity, especially root wrapper, blockquote/id, and figure paragraph wrapper differences, after the base URL and javascript link fixture family mapping.
