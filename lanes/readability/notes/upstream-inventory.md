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

It was rerun on 2026-05-22 after the native single-cell table cleanup slice and still passed:

```text
npm test
1984 passing (41s)
```

It was rerun on 2026-05-22 after the native presentational table/style cleanup slice and still passed:

```text
npm test
1984 passing (38s)
```

It was rerun on 2026-05-22 after the native br-chain cleanup slice and still passed:

```text
npm test
1984 passing (38s)
```

It was rerun on 2026-05-22 after the native hidden/visibility scaffold-heading slice and still passed:

```text
npm test
1984 passing (41s)
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
- Mozilla `_prepArticle` single-cell table cleanup: a table whose only row contains one `td` is replaced by a `p` when the cell contains only phrasing content, or a `div` when the cell contains block children; multi-cell data tables are retained.
- Mozilla `test-pages/table-style-attributes` copied into the lane and mapped for table retention, source `font` to `span` normalization, HTML comment removal, presentational attribute cleanup, table/cell `width`/`height` cleanup, source class cleanup, readerable classification, metadata, excerpt, and whitespace-normalized article text parity.
- Mozilla `test-pages/links-in-tables` copied into the lane and mapped for table link retention, expected href parity, row/column structure, metadata, readerable classification, excerpt, and whitespace-normalized article text parity.
- Mozilla `test-pages/keep-tabular-data` copied into the lane and mapped for preserving a large GUI status data table, expected row count, expected status image sources, metadata, readerable classification, excerpt, and source style/class cleanup.
- Mozilla `_markDataTables` semantics are now partially native: `summary`, populated `caption`, data-table descendants (`col`, `colgroup`, `tfoot`, `thead`, `th`), and row/column thresholds preserve tables, while `role="presentation"`, `datatable="0"`, nested tables, and single-row/column layout tables remain layout-table candidates.
- Mozilla `test-pages/remove-aria-hidden` copied into the lane and mapped for `aria-hidden` text removal during extraction, expected paragraph text retention, metadata, first-paragraph fallback excerpt, and readerable classification.
- Mozilla `test-pages/hidden-nodes` copied into the lane and mapped for inline `display:none` plus hidden-attribute paragraph removal while preserving visible sibling headers.
- Mozilla `test-pages/visibility-hidden` copied into the lane and mapped for `visibility:hidden` section removal, unsafe embed cleanup, and dropping scaffold `h1`/`h2` nodes that only surround a visible paragraph-bearing section container.
- Mozilla `test-pages/replace-brs` copied into the lane and mapped for repeated `<br>` chains becoming paragraph boundaries while single soft breaks remain inside paragraphs.
- Mozilla `test-pages/remove-extra-brs` copied into the lane and mapped for removing stray `<br>` elements before paragraphs and preserving expected paragraph text.
- Mozilla `test-pages/basic-tags-cleaning` copied into the lane and mapped for title metadata, readerable classification, generic `iframe`/`object`/`embed` removal, scaffold `h1`/`h2` cleanup, direct child container parity, and expected editorial paragraph sequence.
- Mozilla `test-pages/remove-extra-paragraphs` copied into the lane and mapped for title metadata, readerable classification, empty and whitespace-only paragraph removal, scaffold heading cleanup, direct child container parity, and expected editorial paragraph sequence.
- Mozilla `_isProbablyVisible` extraction cleanup semantics: `display:none`, `visibility:hidden`, `hidden`, and non-`fallback-image` `aria-hidden=true` nodes are removed before scoring/content selection, while `fallback-image` media can remain.
- Mozilla article-grab cleanup removes `aria-modal=true` `role=dialog` nodes before content scoring, covering modal/cookie-consent chrome in WordPress exports.
- Mozilla parse fallback excerpt semantics now prefer the first article paragraph when metadata does not supply an excerpt, rather than a wrapping container's combined text.
- Single-article body promotion for Medium/WordPress exports: when the selected body/main wrapper contains one substantial article, the native extractor uses that article as the content scope so surrounding document wrappers and empty body placeholders are not imported.
- WordPress migration class cleanup: source theme and block wrapper classes are removed while IDs, article text, and promoted media sources remain available for clean block serialization.
- WordPress migration presentational markup cleanup: legacy `font` tags are normalized to spans, commented-out source tables/links are removed, and retained multi-cell tables drop obsolete presentational attributes before core table block serialization.
- Mozilla `_prepArticle` interactive cleanup: `button`, `input`, `textarea`, and `select` controls plus source platform share/action links are removed from article content.
- Mozilla `_prepArticle` source-fragment cleanup: `link` and `fieldset` nodes are removed before block serialization so inline source stylesheets and subscription controls do not become WordPress blocks.
- WordPress/Medium migration leading action-bar cleanup: byline, follow, read-time, and share controls before the first content heading are removed while author/avatar media remains available.
- WordPress migration URL cleanup: relative editorial links, image `src`, and responsive `srcset` candidates are made absolute against the source URL/base element before block output so import previews and media sideloading are deterministic.
- WordPress table block serialization: retained multi-cell data tables are emitted as core `wp:table` blocks while one-cell layout tables are removed before block output.
- Body-only fallback extraction: when a page has no positive-scoring article/main/div candidate, the native selector still extracts the body rather than falling back to document-head markup.
- Mozilla `_replaceBrs` and trailing-br cleanup semantics: two or more successive `br` elements with optional whitespace between them become paragraph boundaries, `br` elements immediately before paragraphs are removed, and legacy WordPress exports with hard-break paragraph boundaries serialize as separate paragraph blocks.

## Current Lane Status

- Phase: cloned static inventory plus upstream npm runner evidence and Mozilla fixture/JSON-LD/video/lazy media/ad wrapper/title-heading/out-of-band figure/post-process/leading-action-bar/single-paragraph-wrapper/base-url-relative-uri/div-to-paragraph/js-link/single-article/empty-paragraph/single-cell-table/table-style/links-in-tables/keep-tabular-data/data-table-marker/remove-aria-hidden/hidden-nodes/visibility-hidden/invisible-node/first-paragraph-excerpt/br-chain/scaffold-heading/basic-tag-cleaning/link-fieldset mappings.
- Native PHP lane tests: 47 passing, 0 failing, 381 assertions.
- Latest readability-local verification: direct `ArticleExtractorTest.php` run passes 47 tests, 381 assertions, and 0 failures.
- Latest required root verification: `php tools/run-tests.php` passes 112 test files, 8231 assertions, and 0 failures after this basic tag/link-fieldset slice.
- Upstream runner verification: `npm test` passes 1984 Mozilla Mocha tests, 0 failures.
- Blocker: no readability-local execution blocker and no current root-suite blocker for this lane batch. Exact structural HTML parity is still incomplete for copied Medium lazy-image fixtures, including the readability-page root wrapper, non-title h1 removal, author/avatar wrapper nesting, and remaining wrapper/id differences; the copied br/basic-tag fixtures currently map paragraph, child-tag, and cleanup semantics rather than full wrapper/textContent whitespace parity.
- Current work: native extraction now removes hidden/invisible nodes and modal dialogs before scoring, preserves `fallback-image` media under upstream's `aria-hidden` exception, uses first paragraphs for metadata-free fallback excerpts, removes duplicate title headers from content, removes scaffold `h1`/`h2` nodes that only surround paragraph-bearing section containers, demotes body `h1` headings to `h2`, promotes a single substantial article out of body/main wrappers, removes generic unsafe embeds, removes `link` and `fieldset` source fragments, removes empty paragraphs with no media/embed payload, removes one-cell layout tables while preserving retained data tables and upstream-marked data tables, normalizes legacy `font` tags to spans, removes article comments, strips presentational/style attributes from retained markup, removes interactive article controls and leading byline/action bars, preserves lazy media/video fixtures, maps links-in-tables, keep-tabular-data, remove-aria-hidden, hidden-nodes, visibility-hidden, replace-brs, remove-extra-brs, basic-tags-cleaning, and remove-extra-paragraphs fixtures, removes layout-only full-width figure wrappers, simplifies nested `div`/`section` wrappers, wraps consecutive phrasing content inside `div` nodes into paragraphs, preserves media-bearing single-paragraph `div` wrappers, converts text/phrasing-only `div` blocks to paragraphs, converts repeated br chains into paragraph boundaries, removes stray br elements before paragraphs, collapses low-link-density non-media `div` wrappers around a single paragraph, strips source classes, resolves relative links/media against source/base URLs, replaces `javascript:` links with inert retained content, avoids document-head fallback on body-only pages, cleans platform chrome, and emits WordPress paragraph, heading, image, and table block output while flattening block-only layout containers.

## Next Slice

Map the small Mozilla `style-tags-removal`, `remove-script-tags`, and `social-buttons` cleanup fixtures, or continue remaining Medium lazy-image structural HTML parity around readability-page wrappers and non-title h1 removal.
