# Mozilla Readability Upstream Inventory

Upstream cache: `.upstream-cache/readability`

Commit: `08be6b4bdb204dd333c9b7a0cfbc0e730b257252`

License: Apache-2.0

## Counted Denominator

- Package runner: `npm test` -> `mocha test/test-*.js`
- Static Mocha denominator: 1984 tests
- Fixture pages under `test/test-pages`: 130
- Fixture files: 390 (`source.html`, `expected.html`, `expected-metadata.json` for each page)
- `test-readability.js`: 1817 tests
- `test-isProbablyReaderable.js`: 139 tests
- `test-jsdomparser.js`: 28 tests

The `test-readability.js` count includes both jsdom and upstream `JSDOMParser` runs for every fixture. Per DOM parser pass, each fixture has six required assertions plus conditional `dir`, `lang`, and `publishedTime` checks. The metadata inventory contains 17 `dir`, 73 `lang`, and 33 `publishedTime` conditional checks.

## Runner Evidence

The previous sparse-cache probe reached the package script but failed before executing upstream tests because `node_modules` was absent:

```text
sh: line 1: mocha: command not found
```

That blocker is now resolved. The sparse checkout was expanded with the upstream implementation files and lockfile required by the test harness:

```text
git sparse-checkout add index.js Readability.js Readability-readerable.js JSDOMParser.js package-lock.json LICENSE.md
```

Dependencies were installed from the lockfile:

```text
npm ci --no-audit --fund=false
```

The canonical upstream runner now passes locally:

```text
npm test
1984 passing (30s)
```

It was rerun on 2026-05-22 after the lazy-image-2 slice and still passed:

```text
npm test
1984 passing (35s)
```

It was rerun again on 2026-05-22 after the native title-heading cleanup slice and still passed:

```text
npm test
1984 passing (35s)
```

It was rerun on 2026-05-22 after the native out-of-band full-width figure cleanup slice and still passed:

```text
npm test
1984 passing (37s)
```

It was rerun on 2026-05-22 after the native leading byline/action-bar cleanup slice and still passed:

```text
npm test
1984 passing (40s)
```

It was rerun on 2026-05-22 after the native single-paragraph wrapper cleanup slice and still passed:

```text
npm test
1984 passing (36s)
```

It was rerun on 2026-05-22 after the native base URL relative-URI cleanup slice and still passed:

```text
npm test
1984 passing (40s)
```

It was rerun on 2026-05-22 after the native single-article/empty-paragraph cleanup slice and still passed:

```text
npm test
1984 passing (42s)
```

## PHP Mapping

Current PHP tests map a narrow readerable/extraction slice:

- `isProbablyReaderable` default `minContentLength` and `minScore` thresholds.
- Custom `minContentLength` and `minScore` options.
- Visibility callback rejection.
- Hidden, `aria-hidden`, `li p`, and unlikely class/id candidate rejection.
- Unlikely candidate, role, and share-widget cleanup during extraction.
- Kinja/WordPress migration ad wrapper cleanup for `ad-container`, `ad-mobile`, `dfp-slot`, and `js_ad` wrappers.
- Semantic article/main/section scoring preference.
- WordPress block serialization for extracted heading/paragraph content.
- Entity-decoded metadata descriptions for excerpt parity with upstream metadata behavior.
- Mozilla `test-pages/normalize-spaces` source/expected/metadata fixture copied into the lane and mapped for document-title precedence, readerable classification, null byline/site/published/dir metadata, whitespace-normalized excerpt, and extracted article text parity against `expected.html`.
- Mozilla `test-pages/parsely-metadata` copied into the lane and mapped for Parse.ly title, author, publication date, readerable classification, excerpt normalization, and text parity.
- Mozilla `test-pages/mozilla-2` copied into the lane and mapped for OpenGraph site name/description metadata, lang/dir extraction, false readerable classification, and preserved in-main header markers.
- Mozilla `test-pages/embedded-videos` copied into the lane and mapped for readerable classification, excerpt normalization, and preservation of the five expected YouTube, YouTube-nocookie, and Vimeo iframe sources.
- Mozilla `test-pages/videos-2` copied into the lane and mapped for UTF-8 DOM parsing, JSON-LD author/publisher/datePublished metadata, readerable classification, excerpt normalization, article-body selection, exact whitespace-normalized article text parity, and preservation of seven expected YouTube/Dailymotion iframe sources.
- Mozilla `test-pages/lazy-image-1` copied into the lane and mapped for metadata description precedence over shorter OpenGraph/Twitter snippets, readerable classification, lazy image `data-old-src` promotion, exact expected article image source row retention, Medium-style out-of-band full-width figure wrapper removal, leading follow/read-time/share action cleanup, and post-article recommendation/signup chrome removal.
- Mozilla `test-pages/lazy-image-2` copied into the lane and mapped for HTML entity-decoded excerpt metadata, readerable classification, Kinja in-article ad wrapper removal, exact whitespace-normalized article text parity, and 56 responsive image rows with `data-srcset`/`srcset` parity.
- Mozilla `test-pages/lazy-image-3` copied into the lane and mapped for full-fixture `data-src` jpg/png image promotion, expected title/null metadata, and false readerable classification.
- Mozilla `test-pages/base-url-base-element-relative` copied into the lane and mapped for relative anchor and image URL absolutization through a relative `<base href>` plus the upstream fixture document URL.
- Mozilla `test-pages/base-url` and `test-pages/base-url-base-element` copied into the lane and mapped for source-URL and root `<base href>` resolution across relative links, root-relative links, hash links, fragment links, absolute links, and images.
- Mozilla `test-pages/js-link-replacement` copied into the lane and mapped for `javascript:` link replacement where a multi-child anchor becomes an inert `span` while preserving child paragraphs and text.
- Mozilla default video whitelist cleanup semantics: generic `iframe`, `embed`, and `object` nodes are removed while allowed video hosts are retained.
- Focused Mozilla lazy-image semantics for noscript fallback promotion, `data-old-src` placeholder preservation, and `data-srcset` promotion.
- Focused Mozilla short non-SVG base64 data URI placeholders are removed before `data-srcset` promotion so responsive candidates survive JavaScript-free extraction.
- Mozilla `_fixRelativeUris` post-processing: `javascript:` links are replaced by inert text/span content, and `href`, `src`, `poster`, and `srcset` values are resolved against the document URL and first base element when a source URL is supplied.
- Mozilla title/header cleanup semantics from `_headerDuplicatesTitle` and `_prepArticle`: the first content `h1`/`h2` that closely duplicates the extracted title is removed, and remaining `h1` elements are demoted to `h2` because the title is emitted separately.
- Layout-only full-width figure wrapper cleanup: wrappers whose only payload is a single image figure with a short caption are removed when surrounded by paragraph-rich article siblings, while in-column editorial figures are retained for WordPress block image output.
- Mozilla post-process semantics from `_simplifyNestedElements`, div-to-paragraph cleanup, and `_cleanClasses`: empty `div`/`section` containers are removed, single nested `div`/`section` wrappers are collapsed, `div` nodes without descendant block elements are converted to paragraphs, and source `class` attributes are stripped by default while the reserved `page` class remains eligible for preservation.
- Mozilla scoring cleanup semantics: `div` nodes that only wrap one paragraph and have link density below `0.25` are collapsed to the paragraph, matching the expected Medium blockquote shape.
- Mozilla div preprocessing semantics: consecutive phrasing children inside `div` nodes are wrapped in `p` elements, including image and anchor-wrapped image payloads; media-bearing single-paragraph `div` wrappers are retained so copied Medium figure image wrappers move closer to expected HTML parity.
- Mozilla `_prepArticle` extra paragraph cleanup: empty `p` nodes with no image/embed/object/iframe payload are removed before WordPress block serialization.
- Single-article body promotion for Medium/WordPress exports: when the selected body/main wrapper contains one substantial article, the native extractor uses that article as the content scope so surrounding document wrappers and empty body placeholders are not imported.
- WordPress migration class cleanup: source theme and block wrapper classes are removed while IDs, article text, and promoted media sources remain available for clean block serialization.
- Mozilla `_prepArticle` interactive cleanup: `button`, `input`, `textarea`, and `select` controls plus source platform share/action links are removed from article content.
- WordPress/Medium migration leading action-bar cleanup: byline, follow, read-time, and share controls before the first content heading are removed while author/avatar media remains available.
- WordPress migration URL cleanup: relative editorial links, image `src`, and responsive `srcset` candidates are made absolute against the source URL/base element before block output so import previews and media sideloading are deterministic.
- Body-only fallback extraction: when a page has no positive-scoring article/main/div candidate, the native selector still extracts the body rather than falling back to document-head markup.

## Current Lane Status

- Phase: cloned static inventory plus upstream npm runner evidence and Mozilla fixture/JSON-LD/video/lazy media/ad wrapper/title-heading/out-of-band figure/post-process/leading-action-bar/single-paragraph-wrapper/base-url-relative-uri/div-to-paragraph/js-link/single-article/empty-paragraph mappings.
- Native PHP lane tests: 31 passing, 0 failing, 222 assertions.
- Latest root verification: `php tools/run-tests.php` passes 94 test files, 6277 assertions, and 0 failures.
- Upstream runner verification: `npm test` passes 1984 Mozilla Mocha tests, 0 failures.
- Blocker: no readability-local execution blocker remains. Exact structural HTML parity is still incomplete for copied Medium lazy-image fixtures, including the readability-page root wrapper, author/avatar wrapper nesting, and remaining wrapper/id differences.
- Current work: native extraction now removes duplicate title headers from content, demotes body `h1` headings to `h2`, promotes a single substantial article out of body/main wrappers, removes empty paragraphs with no media/embed payload, removes interactive article controls and leading byline/action bars, preserves lazy media/video fixtures, removes layout-only full-width figure wrappers, simplifies nested `div`/`section` wrappers, wraps consecutive phrasing content inside `div` nodes into paragraphs, preserves media-bearing single-paragraph `div` wrappers, converts text/phrasing-only `div` blocks to paragraphs, collapses low-link-density non-media `div` wrappers around a single paragraph, strips source classes, resolves relative links/media against source/base URLs, replaces `javascript:` links with inert retained content, avoids document-head fallback on body-only pages, cleans platform chrome, and emits WordPress block output.

## Next Slice

Continue remaining Medium lazy-image structural HTML parity, especially the readability-page root wrapper, author/avatar wrapper nesting, and exact fixture wrapper/id differences now that figure image divs preserve paragraph-wrapped media.
