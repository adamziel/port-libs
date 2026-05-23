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

It was rerun on 2026-05-22 after the native title separator cleanup slice and still passed:

```text
npm test
1984 passing (58s)
```

It was rerun on 2026-05-22 after the native clean-links URI trim slice and still passed:

```text
npm test
1984 passing (46s)
```

It was rerun on 2026-05-22 after the native hash-link-density and ordered-list fixture slice and still passed:

```text
npm test
1984 passing (41s)
```

It was rerun on 2026-05-22 after the native transparent section-wrapper slice and still passed:

```text
npm test
1984 passing (42s)
```

It was rerun on 2026-05-22 after the native readability-page wrapper/author-wrapper slice and still passed:

```text
npm test
1984 passing (37s)
```

It was rerun on 2026-05-22 after the native WordPress articleBody microdata slice and still passed:

```text
npm test
1984 passing (41s)
```

The `keep-images` slice also has targeted upstream runner evidence:

```text
npm test -- --grep keep-images
15 passing (825ms)
```

The `schema-org-context-object` slice also has targeted upstream runner evidence:

```text
npm test -- --grep schema-org-context-object
17 passing (759ms)
```

The block-boundary spacing slice also has targeted upstream runner evidence:

```text
npm test -- --grep 'keep-tabular-data|replace-brs'
26 passing (648ms)
```

The clean-links selected-root boundary whitespace slice also has targeted upstream runner evidence:

```text
npm test -- --grep clean-links
13 passing (599ms)
```

The medium-1 empty heading/boundary slice also has targeted upstream runner evidence:

```text
npm test -- --grep medium-1
15 passing (750ms)
```

The cnet-svg-classes duplicate SVG sprite slice also has targeted upstream runner evidence:

```text
npm test -- --grep cnet-svg-classes
15 passing (639ms)
```

The ars-1 figure-caption credit cleanup slice also has targeted upstream runner evidence:

```text
npm test -- --grep ars-1
15 passing (200ms)
```

For the JSON-LD title disambiguation, replace-font-tags, compact metadata, RTL direction, and data URL image slices, the upstream implementation and fixture inventory were inspected statically rather than rerunning the full upstream JavaScript suite again. The hydrated sparse checkout still exposes 130 fixture pages and 390 fixture files, and the lane now copies 50 Mozilla fixture pages, including `test-pages/ars-1`, `test-pages/heise`, `test-pages/cnet-svg-classes`, `test-pages/medium-1`, `test-pages/medium-2`, `test-pages/medium-3`, `test-pages/data-url-image`, `test-pages/keep-images`, `test-pages/wordpress`, `test-pages/schema-org-context-object`, `test-pages/003-metadata-preferred`, `test-pages/004-metadata-space-separated-properties`, `test-pages/title-and-h1-discrepancy`, `test-pages/replace-font-tags`, and `test-pages/rtl-1` through `test-pages/rtl-4`.

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
- Mozilla `_unescapeHtmlEntities` metadata behavior for valid numeric references, invalid nonnumeric references, and zero/out-of-range numeric references that become U+FFFD replacement characters.
- Mozilla `test-pages/normalize-spaces` source/expected/metadata fixture copied into the lane and mapped for document-title precedence, readerable classification, null byline/site/published/dir metadata, whitespace-normalized excerpt, and extracted article text parity against `expected.html`.
- Mozilla `test-pages/parsely-metadata` copied into the lane and mapped for Parse.ly title, author, publication date, readerable classification, excerpt normalization, and text parity.
- Mozilla `test-pages/metadata-content-missing` copied into the lane and mapped for space-separated `property` metadata matching, `dc:title`/`dc:creator` precedence over fallback title/author tags, missing `content` metadata skipping, `dc:description` excerpt precedence, readerable classification, and text parity.
- Mozilla `test-pages/003-metadata-preferred` and `test-pages/004-metadata-space-separated-properties` copied into the lane and mapped for higher-priority metadata selection, space-separated property matching, fallback author rejection, readerable classification, excerpt normalization, and text parity.
- Mozilla `test-pages/005-unescape-html-entities` copied into the lane and mapped for upstream metadata entity unescaping: valid numeric references decode, invalid nonnumeric references remain literal, and zero/out-of-range numeric references become U+FFFD replacement characters.
- Mozilla `test-pages/mozilla-2` copied into the lane and mapped for OpenGraph site name/description metadata, lang/dir extraction, false readerable classification, and preserved in-main header markers.
- Mozilla `test-pages/embedded-videos` copied into the lane and mapped for readerable classification, excerpt normalization, and preservation of the five expected YouTube, YouTube-nocookie, and Vimeo iframe sources.
- Mozilla `test-pages/videos-2` copied into the lane and mapped for UTF-8 DOM parsing, JSON-LD author/publisher/datePublished metadata, readerable classification, excerpt normalization, article-body selection, exact whitespace-normalized article text parity, and preservation of seven expected YouTube/Dailymotion iframe sources.
- Mozilla `test-pages/lazy-image-1` copied into the lane and mapped for metadata description precedence over shorter OpenGraph/Twitter snippets, readerable classification, lazy image `data-old-src` promotion, exact expected article image source row retention, expected relative href resolution against the fixture URL, transparent source section wrapper removal, Medium-style out-of-band full-width figure wrapper removal, leading follow/read-time/share action cleanup, and post-article recommendation/signup chrome removal.
- Mozilla `test-pages/lazy-image-2` copied into the lane and mapped for HTML entity-decoded excerpt metadata, readerable classification, Kinja in-article ad wrapper removal, exact whitespace-normalized article text parity, and 56 responsive image rows with `data-srcset`/`srcset` parity.
- Mozilla `test-pages/lazy-image-3` copied into the lane and mapped for full-fixture `data-src` jpg/png image promotion, expected title/null metadata, and false readerable classification.
- Mozilla `test-pages/base-url-base-element-relative` copied into the lane and mapped for relative anchor and image URL absolutization through a relative `<base href>` plus the upstream fixture document URL.
- Mozilla `test-pages/base-url` and `test-pages/base-url-base-element` copied into the lane and mapped for source-URL and root `<base href>` resolution across relative links, root-relative links, hash links, fragment links, absolute links, and images.
- Mozilla `test-pages/js-link-replacement` copied into the lane and mapped for `javascript:` link replacement where a multi-child anchor becomes an inert `span` while preserving child paragraphs and text.
- Mozilla `test-pages/clean-links` copied into the lane and mapped for popup-note `javascript:` anchor neutralization, event-handler attribute removal, exact retained editorial link/image sequences, matching paragraph count, exact normalized content text, trailing footer/home link and image removal, selected-root NBSP boundary cleanup, and whitespace-trimmed `href`/`src` values before URI absolutization.
- Mozilla default video whitelist cleanup semantics: generic `iframe`, `embed`, and `object` nodes are removed while allowed video hosts are retained.
- Focused Mozilla lazy-image semantics for noscript fallback promotion, `data-old-src` placeholder preservation, and `data-srcset` promotion.
- Focused Mozilla short non-SVG base64 data URI placeholders are removed before `data-srcset` promotion so responsive candidates survive JavaScript-free extraction.
- Mozilla `test-pages/data-url-image` copied into the lane and mapped for data URL media boundaries: standalone tiny GIF payloads are preserved, short placeholder GIF `src` values are removed when `data-srcset` exists, `data-srcset` is promoted to `srcset`, inline SVG data URI literal spaces are preserved in serialized content, and base64 SVG/JPEG payloads plus editorial paragraph/image boundaries remain aligned with upstream expectations.
- Mozilla `test-pages/keep-images` copied into the lane and mapped for Medium full-width editorial media retention: all 16 expected image payloads, 16 figures, 65 paragraphs, captions, metadata, readerable classification, source image order, and the named `div#readability-page-1 > div[name=ef8c]` section boundary remain while editor/metabar chrome is removed.
- Mozilla `test-pages/medium-1` copied into the lane and mapped for Medium metadata, readerable classification, empty spacer heading cleanup, and lead heading-to-paragraph text boundary preservation so `Better Student Journalism` does not concatenate with the first paragraph.
- Mozilla `test-pages/cnet-svg-classes` copied into the lane and mapped for Spanish CNET metadata, readerable classification, article text/image parity, and duplicate inline SVG symbol-sprite dedupe while preserving retained SVG content.
- Mozilla `test-pages/heise` copied into the lane and mapped for German metadata, readerable classification, single substantial article promotion out of navigation-heavy page chrome, short leading timestamp cleanup, exact article text/image/link parity, and `classesToPreserve: ["caption"]` class retention.
- Mozilla `test-pages/ars-1` copied into the lane and mapped for ARS metadata, readerable classification, retained hero image source, empty caption text parity, caller-preserved caption class retention, and removal of the source `caption-credit` link-only wrapper.
- Mozilla `_fixRelativeUris` post-processing: `javascript:` links are replaced by inert text/span content, and `href`, `src`, `poster`, and `srcset` values are resolved against the document URL and first base element when a source URL is supplied.
- Mozilla title/header cleanup semantics from `_headerDuplicatesTitle` and `_prepArticle`: the first content `h1`/`h2` that closely duplicates the extracted title is removed, and remaining `h1` elements are demoted to `h2` because the title is emitted separately.
- Layout-only full-width figure wrapper cleanup: wrappers whose only payload is a single image figure with a short caption are removed when surrounded by paragraph-rich article siblings, while in-column editorial figures are retained for WordPress block image output.
- Mozilla post-process semantics from `_simplifyNestedElements`, div-to-paragraph cleanup, and `_cleanClasses`: empty `div`/`section` containers are removed, single nested `div`/`section` wrappers are collapsed, transparent section wrappers whose only direct element children are containers are unwrapped after source class cleanup, `div` nodes without descendant block elements are converted to paragraphs, and source `class` attributes are stripped by default while the reserved `page` class remains eligible for preservation.
- Mozilla parse paging semantics: the native extractor can opt into serializing cleaned content children inside `div#readability-page-1.page` for expected-HTML comparison, while default extraction remains rootless for WordPress block migration.
- Mozilla post-process cleanup now reruns wrapper simplification after empty paragraph removal, so emptied Medium author/action wrapper stacks collapse toward the upstream `lazy-image-1` avatar shape.
- Mozilla scoring cleanup semantics: `div` nodes that only wrap one paragraph and have link density below `0.25` are collapsed to the paragraph, matching the expected Medium blockquote shape.
- Mozilla div preprocessing semantics: consecutive phrasing children inside `div` nodes are wrapped in `p` elements, including image and anchor-wrapped image payloads; media-bearing single-paragraph `div` wrappers are retained so copied Medium figure image wrappers move closer to expected HTML parity.
- Mozilla `_prepArticle` extra paragraph cleanup: empty `p` nodes with no image/embed/object/iframe payload are removed before WordPress block serialization.
- Mozilla `_prepArticle` single-cell table cleanup: a table whose only row contains one `td` is replaced by a `p` when the cell contains only phrasing content, or a `div` when the cell contains block children; multi-cell data tables are retained.
- Mozilla `test-pages/table-style-attributes` copied into the lane and mapped for table retention, source `font` to `span` normalization, HTML comment removal, presentational attribute cleanup, table/cell `width`/`height` cleanup, source class cleanup, readerable classification, metadata, excerpt, and whitespace-normalized article text parity.
- Mozilla `test-pages/replace-font-tags` copied into the lane and mapped for upstream `_replaceNodeTags` behavior: legacy `font` elements become `span` elements while retaining `face`/`size`, text-only `div` blocks become paragraphs, duplicate title `h1` content is removed, remaining body `h1` content is demoted, and no `font` elements survive extraction or WordPress block output.
- Mozilla `test-pages/rtl-1`, `rtl-2`, `rtl-3`, and `rtl-4` copied into the lane and mapped for article direction metadata: direction is read from the selected content scope's parent/ancestor chain, preserving upstream `html`/`body`/`main` RTL wrappers while leaving article-only `dir` unset.
- Mozilla `test-pages/links-in-tables` copied into the lane and mapped for table link retention, expected href parity, row/column structure, metadata, readerable classification, excerpt, and whitespace-normalized article text parity.
- Mozilla `test-pages/keep-tabular-data` copied into the lane and mapped for preserving a large GUI status data table, expected row count, expected status image sources, metadata, readerable classification, excerpt, source style/class cleanup, and exact normalized content text across adjacent paragraph/heading/table boundaries.
- Mozilla `_markDataTables` semantics are now partially native: `summary`, populated `caption`, data-table descendants (`col`, `colgroup`, `tfoot`, `thead`, `th`), and row/column thresholds preserve tables, while `role="presentation"`, `datatable="0"`, nested tables, and single-row/column layout tables remain layout-table candidates.
- Mozilla `test-pages/remove-aria-hidden` copied into the lane and mapped for `aria-hidden` text removal during extraction, expected paragraph text retention, metadata, first-paragraph fallback excerpt, and readerable classification.
- Mozilla `test-pages/hidden-nodes` copied into the lane and mapped for inline `display:none` plus hidden-attribute paragraph removal while preserving visible sibling headers.
- Mozilla `test-pages/visibility-hidden` copied into the lane and mapped for `visibility:hidden` section removal, unsafe embed cleanup, and dropping scaffold `h1`/`h2` nodes that only surround a visible paragraph-bearing section container.
- Mozilla `test-pages/replace-brs` copied into the lane and mapped for repeated `<br>` chains becoming paragraph boundaries while single soft breaks remain inside paragraphs, including exact normalized content text across generated paragraph boundaries.
- Mozilla `test-pages/remove-extra-brs` copied into the lane and mapped for removing stray `<br>` elements before paragraphs and preserving expected paragraph text.
- Mozilla `test-pages/basic-tags-cleaning` copied into the lane and mapped for title metadata, readerable classification, generic `iframe`/`object`/`embed` removal, scaffold `h1`/`h2` cleanup, direct child container parity, and expected editorial paragraph sequence.
- Mozilla `test-pages/remove-extra-paragraphs` copied into the lane and mapped for title metadata, readerable classification, empty and whitespace-only paragraph removal, scaffold heading cleanup, direct child container parity, and expected editorial paragraph sequence.
- Mozilla `test-pages/style-tags-removal` copied into the lane and mapped for removing style tags from head, article, and trailing body chrome while retaining expected headings, paragraphs, metadata, and readerable classification.
- Mozilla `test-pages/remove-script-tags` copied into the lane and mapped for removing JavaScript and VBScript source fragments before extraction while retaining expected editorial paragraphs, metadata, and readerable classification.
- Mozilla `test-pages/social-buttons` copied into the lane and mapped for removing WordPress.com Sharedaddy/Jetpack like widget chrome while retaining the five expected article paragraphs and demoted heading.
- Mozilla `test-pages/title-en-dash` copied into the lane and mapped for `_getArticleTitle` separator cleanup where an en-dash site suffix is removed from the document title, while readerable classification, fallback excerpt, and expected article text remain aligned with upstream.
- Mozilla `test-pages/title-and-h1-discrepancy` copied into the lane and mapped for JSON-LD `name`/`headline` disagreement where the JSON-LD field matching the cleaned document title remains the article title while the body h1 is demoted/cleaned in extracted content.
- Mozilla `test-pages/schema-org-context-object` copied into the lane and mapped for object-valued JSON-LD `@context`, NewsArticle title/byline/site/date/lang/excerpt metadata, readerable classification, full paragraph sequence parity, React/Next comment-delimited contributor text preservation, and leading timestamp/inline-byline chrome removal before the first editorial paragraph.
- Mozilla post-process comment cleanup semantics are now native for the current slice: DOM comment nodes are removed before div phrasing content is wrapped into paragraphs, so hydration markers such as `<!-- -->` do not split a text run into multiple paragraph blocks.
- Mozilla `test-pages/v8-blog` copied into the lane and mapped for the upstream published-time metadata boundary: visible body/header `<time datetime>` values do not populate `publishedTime` unless JSON-LD, `article:published_time`, or `parsely-pub-date` supplies the date.
- Mozilla `test-pages/medium-2` copied into the lane and mapped for trailing Medium syndication/source-note cleanup: a nested last-child section starting `Originally published at` after substantial article content is removed so expected article text and link rows match upstream.
- Mozilla `test-pages/medium-3` copied into the lane and mapped for hr-separated Medium page-section cleanup: metadata, readerable classification, text, links, images, blockquotes, ordered list rows, and direct `readability-page-1` child sections match upstream output while separator rules are removed.

## Verification 2026-05-23

- Upstream targeted oracle: `npm test -- --grep medium-1` passes 15 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep cnet-svg-classes` passes 15 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep v8-blog` passes 15 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep lazy-image-1` passes 17 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep medium-2` passes 15 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep medium-3` passes 17 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep heise` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the canonical fixture harness `classesToPreserve: ["caption"]` boundary.
- Upstream targeted oracle: `npm test -- --grep ars-1` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the ARS figure-caption credit cleanup fixture.
- Readability-local PHP coverage: 94 behavior tests, 871 assertions, 0 failures.
- Required root `php tools/run-tests.php`: passes with 170 test files, 15703 assertions, and 0 failures.
- WordPress React/Next migration cleanup: contributor or byline text split by parser comment delimiters serializes as one paragraph block instead of multiple fragment blocks.
- Mozilla/fixture text projection boundary semantics are now native for the current slice: text-bearing block, table, and list siblings receive separator whitespace after cleanup so expected text does not concatenate across paragraph-heading, paragraph-paragraph, generated-br paragraph, and table-cell boundaries.
- WordPress block-boundary spacing cleanup: imported article text, search excerpts, and review logs keep paragraph-to-heading and table-cell words separated even when source HTML omits whitespace between adjacent tags.
- Mozilla `test-pages/wordpress` copied into the lane and mapped for WordPress Tavern BlogPosting metadata, articleBody paragraph parity, wp.com image/srcset parity, and Jetpack share/related/comment cleanup.
- Mozilla `test-pages/ol` copied into the lane and mapped for preserving ordered-list content while keeping readerable preflight false because `li p` candidates are skipped.
- Mozilla `test-pages/001` copied into the lane and mapped for metadata-free body byline extraction, nested `itemprop=name` preference, byline metadata parity, and removing the body byline node from extracted content.
- Mozilla `_getClassWeight` semantics are now partially native: upstream positive/negative class/id patterns influence content candidate scoring alongside existing semantic article/main/section weights.
- WordPress schema.org `itemprop=articleBody` candidate selection is now native for BlogPosting-style source pages that omit standard `entry-content` classes.
- Mozilla `_getArticleTitle` separator semantics are now partially native: spaced `|`, hyphen, en dash, em dash, slash, backslash, `>`, and `»` separators remove the final hierarchy/site segment when the retained title remains substantial, with the upstream short-title fallback boundary.
- Mozilla `_getJSONLD` title semantics are now partially native: when JSON-LD `name` and `headline` both exist and disagree, the native extractor compares each field to the cleaned document title and chooses the matching field, otherwise preserving upstream `name` before `headline` preference.
- Mozilla `_getLinkDensity` hash URL semantics are now native: hash-only anchors use the upstream `0.3` coefficient, so local footnote/citation links do not over-inflate wrapper link density.
- Mozilla `_isValidByline` body byline semantics are now partially native: `rel=author`, `itemprop` containing `author` or `name`, and `byline`/`author` class or id candidates populate byline metadata when under the upstream 100-character boundary, and matched nodes are removed from article output when no stronger metadata byline already exists.
- Mozilla `_isProbablyVisible` extraction cleanup semantics: `display:none`, `visibility:hidden`, `hidden`, and non-`fallback-image` `aria-hidden=true` nodes are removed before scoring/content selection, while `fallback-image` media can remain.
- Mozilla article-grab cleanup removes `aria-modal=true` `role=dialog` nodes before content scoring, covering modal/cookie-consent chrome in WordPress exports.
- Mozilla parse fallback excerpt semantics now prefer the first article paragraph when metadata does not supply an excerpt, rather than a wrapping container's combined text.
- Single-article body promotion for Medium/WordPress exports: when the selected body/main/div wrapper contains one substantial article, the native extractor uses that article as the content scope so surrounding document wrappers, navigation, sidebars, and empty body placeholders are not imported.
- WordPress migration class cleanup: source theme and block wrapper classes are removed while IDs, article text, and promoted media sources remain available for clean block serialization; callers can now preserve explicit class tokens such as `wp-caption`, `aligncenter`, and `wp-caption-text` for media review workflows.
- WordPress migration presentational markup cleanup: legacy `font` tags are normalized to spans, commented-out source tables/links are removed, and retained multi-cell tables drop obsolete presentational attributes before core table block serialization.
- Mozilla `_prepArticle` interactive cleanup: `button`, `input`, `textarea`, and `select` controls plus source platform share/action links are removed from article content.
- Mozilla `_prepArticle` source-fragment cleanup: `link` and `fieldset` nodes are removed before block serialization so inline source stylesheets and subscription controls do not become WordPress blocks.
- WordPress/Medium migration leading action-bar cleanup: byline, follow, read-time, and share controls before the first content heading are removed while author/avatar media remains available.
- WordPress Jetpack social-widget cleanup: inline `script`/`style` fragments plus Sharedaddy like widgets are removed before block serialization while editorial paragraph blocks remain.
- WordPress migration URL cleanup: relative editorial links, image `src`, and responsive `srcset` candidates are made absolute against the source URL/base element before block output so import previews and media sideloading are deterministic.
- WordPress popup-link cleanup: source note anchors with `javascript:` hrefs and event handlers are neutralized while whitespace-padded editorial link and media URLs are trimmed before absolutization.
- WordPress selected-root NBSP cleanup: classic body-only table exports with leading or trailing `&nbsp;` layout padding now serialize paragraph blocks without padding text while preserving internal nonbreaking editorial spacing.
- WordPress trailing footer-bar cleanup: compact source-theme footer bars with multiple navigation links/images after substantial article content are removed while editorial body links remain absolute and available for block output.
- WordPress syndication footer cleanup: nested last-child source notes such as `Originally published at ...` are removed after substantial article content so stale original-source links do not become migrated paragraph blocks.
- WordPress Medium page-break cleanup: source `<hr>` separators between Medium article page sections are removed before block serialization, so synthetic page rules do not become WordPress paragraph blocks.
- Mozilla `_cleanClasses` option semantics: source classes are stripped by default while the upstream default `page` class and caller-supplied `classesToPreserve` tokens are retained exactly by class token.
- WordPress footnote wrapper cleanup: legacy wrappers around local citation links collapse like upstream low-link-density single-paragraph wrappers while preserving the hash link and target.
- WordPress body byline cleanup: legacy source templates that put `itemprop=author` or `rel=author` bylines in the article body now populate the extracted byline and drop that byline paragraph before block serialization, while author links inside unlikely footer chrome are ignored.
- WordPress news template chrome cleanup: heading-less legacy news bodies that place timestamp and inline byline wrappers before article copy now serialize blocks starting at the first editorial paragraph while keeping title/byline/date metadata separate.
- WordPress migration title cleanup: source site suffixes such as `Reusable Pattern Migration Planning Guide – Legacy Agency Site` are removed before selecting a `post_title`, while duplicate body title headings are still removed from block content.
- WordPress structured-data title cleanup: plugin-injected JSON-LD headlines that do not match the cleaned source document title no longer replace the imported post title when JSON-LD `name` matches the canonical title.
- WordPress metadata entity cleanup: double-escaped metadata excerpts from old feeds/templates decode valid numeric references while invalid zero/out-of-range numeric references become replacement characters before post metadata import.
- WordPress RTL import metadata cleanup: right-to-left source wrappers populate article direction metadata for downstream post/meta handling without importing duplicate title chrome.
- WordPress visible-date metadata cleanup: visible theme `<time datetime>` nodes are not promoted to trusted `publishedTime` metadata unless upstream-supported metadata fields supply the date.
- WordPress table block serialization: retained multi-cell data tables are emitted as core `wp:table` blocks while one-cell layout tables are removed before block output.
- WordPress section wrapper cleanup: legacy page-builder sections that only carry layout classes and container children are unwrapped before block serialization, keeping editorial paragraphs without importing `<section>` shells.
- WordPress/Medium section wrapper parity: generic `section-content` inner wrappers no longer receive the large generic content boost when surrounded by stronger article/section boundaries, so optional oracle output can preserve named Medium section wrappers while WordPress block serialization remains flattened.
- Body-only fallback extraction: when a page has no positive-scoring article/main/div candidate, the native selector still extracts the body rather than falling back to document-head markup.
- Mozilla `_replaceBrs` and trailing-br cleanup semantics: two or more successive `br` elements with optional whitespace between them become paragraph boundaries, `br` elements immediately before paragraphs are removed, and legacy WordPress exports with hard-break paragraph boundaries serialize as separate paragraph blocks.
- Mozilla conditional-cleanup slice for ARS media captions: short link-heavy `div` wrappers inside `figcaption` are removed so photo-credit-only links do not enter article text while caller-requested caption classes can survive.
- WordPress figure credit cleanup: link-only source photo-credit wrappers inside media captions are removed before block serialization while the image and requested caption class contract remain available.

## Current Lane Status

- Phase: cloned static inventory plus upstream npm runner evidence and Mozilla fixture/JSON-LD/video/lazy media/data-url-image/keep-images-medium-section-wrapper/ad wrapper/title-heading/title-h1-discrepancy/schema-org-context-object/wordpress-articleBody/out-of-band figure/post-process/classesToPreserve/leading-action-bar/heading-less-news-chrome/single-paragraph-wrapper/base-url-relative-uri/div-to-paragraph/js-link/clean-links-uri-trim-footer-parity-selected-root-nbsp/single-article/empty-paragraph/single-cell-table/table-style/links-in-tables/keep-tabular-data/data-table-marker/remove-aria-hidden/hidden-nodes/visibility-hidden/invisible-node/first-paragraph-excerpt/br-chain/scaffold-heading/basic-tag-cleaning/link-fieldset/script-style-social/title-separator/ordered-list/hash-link-density/body-byline/entity-unescape/replace-font-tags/rtl-direction/transparent-section-wrapper/readability-page-wrapper/comment-delimited-phrasing/block-boundary-spacing/medium-1-empty-heading-boundary/cnet-svg-sprite-dedupe/v8-blog-published-time-boundary/medium-2-syndication-footer/medium-3-hr-page-break/heise-single-article-promotion/ars-1-figure-credit-cleanup mappings.
- Native PHP lane tests: 94 passing, 0 failing, 871 assertions.
- Latest readability-local verification: direct `ArticleExtractorTest.php` run passes 94 tests, 871 assertions, and 0 failures.
- Latest required root verification: `php tools/run-tests.php` passes with 170 test files, 15703 assertions, and 0 failures.
- Upstream runner verification: `npm test` passes 1984 Mozilla Mocha tests, 0 failures, including the 2026-05-22 WordPress articleBody microdata rerun in 41s. Targeted oracles pass `npm test -- --grep keep-images` with 15 checks in 825ms, `npm test -- --grep schema-org-context-object` with 17 checks in 759ms, `npm test -- --grep 'keep-tabular-data|replace-brs'` with 26 checks in 648ms, `npm test -- --grep clean-links` with 13 checks in 599ms, `npm test -- --grep medium-1` with 15 checks in 750ms, `npm test -- --grep cnet-svg-classes` with 15 checks in 639ms, `npm test -- --grep v8-blog` with 15 checks in 970ms, `npm test -- --grep lazy-image-1` with 17 checks in 780ms, `npm test -- --grep medium-2` with 15 checks in 157ms, `npm test -- --grep medium-3` with 17 checks in 1s, `npm test -- --grep heise` with 15 checks in 603ms, and `npm test -- --grep ars-1` with 15 checks in 200ms.
- Blocker: no readability-local, upstream targeted, or required root-test blocker is active.
- Current work: native figure-caption cleanup now removes short link-heavy credit wrappers inside figcaptions, maps the copied Mozilla ars-1 fixture for metadata/readerable/image/caption-credit parity, and adds `wordpress-figure-credit-cleanup.php` for a WordPress media-caption import boundary. The default WordPress migration path still strips source classes unless callers request caption class preservation.

## Next Slice

Map another caption/media-heavy copied fixture such as `guardian-1`, or continue exact ARS article/header wrapper parity and Medium image/avatar wrapper parity using `ars-1`, `lazy-image-1`, and `medium-1`.
