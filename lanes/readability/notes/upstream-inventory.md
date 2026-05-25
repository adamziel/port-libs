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

The parse-options slice also has targeted upstream runner evidence:

```text
npm test -- --grep 'custom video regex|maxElemsToParse|keepClasses'
5 passing (114ms)
```

The Firefox Nightly WordPress author-header slice also has targeted upstream runner evidence:

```text
npm test -- --grep firefox-nightly-blog
17 passing (944ms)
```

The Mozilla Hacks `002` developer-article slice also has targeted upstream runner evidence:

```text
npm test -- --grep 002
15 passing (996ms)
```

This fixture is now copied under `lanes/readability/fixtures/mozilla/002/` and mapped by native PHP tests for metadata/readerable parity, `div#content-main` oracle-root preservation, `article role="article"` retention, 17 syntax-highlighted `<pre>` examples, absolute-origin URL canonicalization, and exclusion of Mozilla Hacks navigation, comments, author sidebar, and legal footer chrome.

## 2026-05-25 Isolated Slice: Mozilla Developer Edition Expected Text

- Behavior cluster: copied Mozilla `mozilla-2` fixture coverage for the Firefox Developer Edition product page.
- Status delta: the existing marker-only native test now asserts exact normalized expected-content text parity against `fixtures/mozilla/mozilla-2/expected.html`; the WordPress smoke `examples/wordpress-mozilla-developer-edition-import.php` records retained feature copy, 4 paragraph blocks, 2 heading blocks, and no head-comment chrome.
- Focused evidence: `php -l lanes/readability/tests/ArticleExtractorTest.php` passed; `php -l lanes/readability/examples/wordpress-mozilla-developer-edition-import.php` passed; `php tools/run-tests.php lanes/readability/tests` passed 1 selected test file / 1855 assertions / 0 failures; `php lanes/readability/examples/wordpress-mozilla-developer-edition-import.php` printed feature copy retained: yes and head comment chrome retained: no; `git diff --check -- lanes/readability` passed.
- Blocker: no focused readability blocker. The isolated worktree does not contain `.upstream-cache/readability`, so no new upstream oracle command was run for this slice.
- Next task: map `lifehacker-working` or another remaining Kinja/comment-heavy fixture once the upstream cache is present in the isolated worktree, or deliberately expand nested media-wrapper HTML-block serialization with updated long-fixture expectations.
- Dependency closure: no new support component is needed; this slice reuses the lane's existing DOM extraction, expected-fixture comparison, and WordPress block serialization components.

## 2026-05-25 Isolated Slice: Mozilla Social Buttons Fixture

- Behavior cluster: copied Mozilla `social-buttons` fixture coverage for share-widget cleanup.
- Status delta: added a focused native fixture test asserting upstream metadata, readerable classification, exact normalized expected-content text, five retained article paragraphs, and no share-button widget chrome in article HTML or WordPress blocks. Added `examples/wordpress-social-buttons-fixture-cleanup.php` to smoke a WordPress import path with five paragraph blocks and no retained share widget chrome.
- Focused evidence: `php -l lanes/readability/tests/ArticleExtractorTest.php` passed; `php -l lanes/readability/examples/wordpress-social-buttons-fixture-cleanup.php` passed; `php tools/run-tests.php lanes/readability/tests` passed 1 selected test file / 1875 assertions / 0 failures; `php lanes/readability/examples/wordpress-social-buttons-fixture-cleanup.php` printed title `Share buttons removal test`, paragraph blocks: 5, share widget chrome retained: no; `git diff --check -- lanes/readability` passed.
- Blocker: no focused readability blocker. The isolated worktree does not contain `.upstream-cache/readability`, so no new upstream oracle command was run for this slice.
- Next task: map `lifehacker-working` or another remaining Kinja/comment-heavy fixture once the upstream cache is present in the isolated worktree, or map another already-copied small cleanup fixture such as `remove-extra-paragraphs` with exact expected-content parity.
- Dependency closure: no new support component is needed; this slice reuses the lane's existing DOM extraction, Mozilla fixture comparison helpers, share-widget cleanup, and WordPress block serialization components.

## 2026-05-25 Isolated Slice: Mozilla Remove Extra Paragraphs Fixture

- Behavior cluster: copied Mozilla `remove-extra-paragraphs` fixture coverage for upstream empty paragraph cleanup.
- Status delta: promoted the existing grouped coverage into a focused native fixture test asserting upstream metadata, readerable classification, exact normalized expected-content text, exact retained nonempty paragraph text, the absence of empty paragraphs in extracted HTML, and five WordPress paragraph blocks with no blank block serialization. Added `examples/wordpress-empty-paragraph-cleanup.php` to smoke the WordPress import path.
- Focused evidence: `php -l lanes/readability/tests/ArticleExtractorTest.php` passed; `php -l lanes/readability/examples/wordpress-empty-paragraph-cleanup.php` passed; `php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php` passed 154 tests / 1915 assertions / 0 failures; `php lanes/readability/examples/wordpress-empty-paragraph-cleanup.php` printed paragraph blocks: 5, blank source paragraphs retained: no, blank WordPress blocks emitted: no; `git diff --check -- lanes/readability` passed.
- Blocker: no focused readability blocker. The isolated worktree does not contain `.upstream-cache/readability`, so no new upstream oracle command was run for this slice.
- Next task: map `lifehacker-working` or another remaining Kinja/comment-heavy fixture once the upstream cache is present in the isolated worktree, or map another already-copied cleanup fixture with exact expected-content and WordPress block evidence.
- Dependency closure: no new support component is needed; this slice reuses the lane's existing DOM extraction, empty-paragraph cleanup, Mozilla fixture comparison helpers, and WordPress paragraph block serialization.

The Atlas Obscura `article-author-tag` slice also has targeted upstream runner evidence:

```text
npm test -- --grep article-author-tag
17 passing (673ms)
```

This fixture is now copied under `lanes/readability/fixtures/mozilla/article-author-tag/` and mapped by native PHP tests for metadata/readerable parity, article:author byline metadata, `section#article-body` oracle-root preservation, six retained image payloads, two editorial `<hr>` separators, NBSP-only empty paragraph cleanup, and exclusion of Atlas Obscura header/navigation chrome.

The Engadget `engadget` slice also has targeted upstream runner evidence:

```text
npm test -- --grep engadget
17 passing (996ms)
```

This fixture is now copied under `lanes/readability/fixtures/mozilla/engadget/` and mapped by native PHP tests for title/byline/site/date/lang/excerpt metadata parity, readerable classification, review-gallery first-image retention, thumbnail/grid/count chrome removal, product buy-link/product identity cleanup, 10 retained image payloads, one retained YouTube iframe, and exact upstream normalized text parity.

The Google SRE book `google-sre-book-1` slice also has targeted upstream runner evidence:

```text
npm test -- --grep google-sre-book-1
15 passing (311ms)
```

This fixture is now copied under `lanes/readability/fixtures/mozilla/google-sre-book-1/` and mapped by native PHP tests for title/byline/excerpt/lang metadata parity, readerable classification, `section#maia-main[role=main]` chapter-root promotion, 78 expected paragraphs, 13 h2 headings, one symptom/cause table, 15 editorial links, table-of-contents/header/logo chrome removal, and WordPress output with 53 paragraph blocks, 10 heading blocks, 1 table block, 0 image blocks, and no book navigation chrome.

The Wikipedia fixture family has targeted upstream runner evidence:

```text
npm test -- --grep wikipedia
74 passing (8s)
```

The `wikipedia-4` fixture is now copied under `lanes/readability/fixtures/mozilla/wikipedia-4/` and mapped by native PHP tests for Wikimedia title/byline/site/date/lang metadata parity, readerable classification, exact normalized expected text parity, long sortable film-table retention, dynamic-list maintenance note cleanup, category-link cleanup, CentralAutoLogin tracking-pixel cleanup, and WordPress output with 6 paragraph blocks, 2 heading blocks, 1 table block, 0 image blocks, and no portal/category/tracking chrome.

The `wikipedia` fixture is now copied under `lanes/readability/fixtures/mozilla/wikipedia/` and mapped by native PHP tests for Mozilla article title/lang/excerpt metadata parity, readerable classification, exact normalized expected text parity, 69 expected paragraphs, 9 h2 headings, 17 h3 headings, two retained tables, eight retained image payloads, 508 expected links, MediaWiki siteSub/jump/hatnote/printfooter cleanup, and WordPress output with 74 paragraph blocks, 37 heading blocks, 2 table blocks, and no shell chrome.

The `wikipedia-2` fixture is now copied under `lanes/readability/fixtures/mozilla/wikipedia-2/` and mapped by native PHP tests for New Zealand country-page title/byline/site/date/lang/dir metadata parity, readerable classification, excerpt parity, heading and table retention, expected upstream image-source retention, MediaWiki good-article/protection status-indicator cleanup, and WordPress output with 151 paragraph blocks, 30 heading blocks, 4 table blocks, and no status/shell chrome.

The `wikipedia-3` fixture is now copied under `lanes/readability/fixtures/mozilla/wikipedia-3/` and mapped by native PHP tests for Hermitian-matrix title/byline/site/date/lang/dir metadata parity, readerable classification, excerpt parity, MediaWiki math article shell cleanup, expected paragraph and section-heading text parity, one retained review table, retained upstream expected math/editorial image sources, and WordPress output with 62 paragraph blocks, 12 heading blocks, 1 table block, and no siteSub/jump/category/tracking chrome.

The `wikipedia-2` slice also has targeted upstream runner evidence:

```text
npm test -- --grep wikipedia-2
19 passing (6s)
```

The `wikipedia-3` slice also has targeted upstream runner evidence:

```text
npm test -- --grep wikipedia-3
19 passing (979ms)
```

The La Nacion leading-BOM/described-NewsArticle slice also has targeted upstream runner evidence:

```text
npm test -- --grep la-nacion
13 passing (164ms)
```

The dev418 mixed-media/list-retention slice also has targeted upstream runner evidence:

```text
npm test -- --grep dev418
13 passing (485ms)
```

The `charThreshold` retry/null boundary was cross-checked against the upstream implementation with a focused Node oracle in `.upstream-cache/readability`:

```text
retry-null false
retry-text Legacy imports sometimes wrap short editorial copy in containers whose classes look like comment chrome to the first Readability pass.
retry-length 134
empty-null true
```

The caption/media-heavy Guardian fixture is copied from the exact upstream path `.upstream-cache/readability/test/test-pages/guardian-1/` and has targeted upstream runner evidence:

```text
npm test -- --grep guardian-1
17 passing (1s)
```

The NYT rich figure/caption fixture is copied from the exact upstream path `.upstream-cache/readability/test/test-pages/nytimes-1/` and has targeted upstream runner evidence:

```text
npm test -- --grep nytimes-1
15 passing (810ms)
```

The NYT continuation-link/story-interrupter fixture is copied from the exact upstream path `.upstream-cache/readability/test/test-pages/nytimes-2/` and has targeted upstream runner evidence:

```text
npm test -- --grep nytimes-2
15 passing (679ms)
```

The NYT debt article graphics/related-link fixture is copied from the exact upstream path `.upstream-cache/readability/test/test-pages/nytimes-4/` and has targeted upstream runner evidence:

```text
npm test -- --grep nytimes-4
15 passing (581ms)
```

The Telegraph text-section publisher chrome fixture is copied from the exact upstream path `.upstream-cache/readability/test/test-pages/telegraph/` and has targeted upstream runner evidence:

```text
npm test -- --grep telegraph
15 passing (796ms)
```

The CNN storytext publisher chrome fixture is copied from the exact upstream path `.upstream-cache/readability/test/test-pages/cnn/` and has targeted upstream runner evidence:

```text
npm test -- --grep cnn
13 passing (1s)
```

For the JSON-LD title disambiguation, replace-font-tags, compact metadata, RTL direction, and data URL image slices, the upstream implementation and fixture inventory were inspected statically rather than rerunning the full upstream JavaScript suite again. The hydrated sparse checkout still exposes 130 fixture pages and 390 fixture files, and the lane now copies 59 Mozilla fixture pages, including `test-pages/cnn`, `test-pages/telegraph`, `test-pages/nytimes-1`, `test-pages/nytimes-2`, `test-pages/nytimes-3`, `test-pages/nytimes-4`, `test-pages/guardian-1`, `test-pages/ars-1`, `test-pages/heise`, `test-pages/cnet-svg-classes`, `test-pages/medium-1`, `test-pages/medium-2`, `test-pages/medium-3`, `test-pages/data-url-image`, `test-pages/keep-images`, `test-pages/wordpress`, `test-pages/schema-org-context-object`, `test-pages/003-metadata-preferred`, `test-pages/004-metadata-space-separated-properties`, `test-pages/title-and-h1-discrepancy`, `test-pages/replace-font-tags`, and `test-pages/rtl-1` through `test-pages/rtl-4`.

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
- Mozilla `test-pages/bbc-1` copied into the lane and mapped for BBC metadata/readerable parity, RDFa `property=articleBody` candidate selection/root preservation, 32 expected paragraphs, 2 h2 headings, 5 image/data-src rows, unsupported media-placeholder video-shell cleanup after iframe removal, and navigation chrome exclusion.
- Mozilla `test-pages/cnn` copied into the lane and mapped for CNNMoney metadata/readerable parity, `div#storytext` root preservation under the optional readability-page wrapper, retained SmartAsset powered label, and removal of CNN video player, Teads ad, disclosure widget, and masthead chrome.
- Mozilla `test-pages/wapo-1` copied into the lane and mapped for Washington Post metadata/readerable parity, 39 expected paragraphs, 14 expected editorial links, one retained inline map image, two inline gallery widgets removed, linked graphic promo chrome removed while keeping its caption, retained PostTV caption text after unsupported embed removal, and WordPress output with 39 paragraph blocks.
- Mozilla `test-pages/wapo-2` copied into the lane and mapped for Washington Post metadata/readerable parity, lead inline-photo plus article plus post-body author-bio envelope retention, top `pb-sig-line` byline/share cleanup, 28 expected paragraphs, 2 retained image payloads, 4 editorial links, and WordPress output with 28 paragraph blocks.
- Mozilla `test-pages/yahoo-2` copied into the lane and mapped for upstream siteName metadata parity: JSON-LD publisher and `og:site_name` remain the only native site-name sources, so the Yahoo `application-name` page-title value is not imported as `siteName`; content text, byline, excerpt, language, readerable status, 16 WordPress paragraph blocks, one review heading block, and share/ad chrome cleanup are covered.
- Mozilla `test-pages/buzzfeed-1` copied into the lane and mapped for upstream null-byline parity, BuzzFeed print image/link cleanup, author bio/contact/share tail removal, two retained grid images, two h2 headings, 16 expected paragraphs, and WordPress output without publisher bio chrome.
- Mozilla `test-pages/lemonde-1` copied into the lane and mapped for French Le Monde metadata/lang parity, `articleBody` root selection, allowed Dailymotion iframe retention, 28 expected paragraphs, 9 h2 sections, and WordPress output without subscription/navigation/ad chrome.
- Mozilla `test-pages/theverge` copied into the lane and mapped for The Verge metadata/readerable parity, `div#content` readability-page wrapper preservation, pullquote wrapper retention, responsive Vision Pro image/srcset retention, newsletter plan-copy boundaries, subscribe/action/ad/rail chrome cleanup, and WordPress output with 19 paragraph blocks, 3 heading blocks, and 1 image block.

## Verification 2026-05-23

- Upstream targeted oracle: `npm test -- --grep medium-1` passes 15 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep cnet-svg-classes` passes 15 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep v8-blog` passes 15 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep lazy-image-1` passes 17 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep medium-2` passes 15 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep medium-3` passes 17 checks with 0 failures in `.upstream-cache/readability`.
- Upstream targeted oracle: `npm test -- --grep heise` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the canonical fixture harness `classesToPreserve: ["caption"]` boundary.
- Upstream targeted oracle: `npm test -- --grep ars-1` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the ARS figure-caption credit cleanup fixture.
- Upstream targeted oracle: `npm test -- --grep guardian-1` passes 17 checks with 0 failures in `.upstream-cache/readability`, covering the caption/media-heavy Guardian fixture at `test/test-pages/guardian-1`.
- Upstream targeted oracle: `npm test -- --grep nytimes-1` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the NYT rich figure-caption and hidden feedback fixture at `test/test-pages/nytimes-1`.
- Upstream targeted oracle: `npm test -- --grep nytimes-2` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the NYT continuation-link and hidden story-interrupter fixture at `test/test-pages/nytimes-2`.
- Upstream targeted oracle: `npm test -- --grep nytimes-3` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the NYT figure `itemid` lazy-image and related-card cleanup fixture at `test/test-pages/nytimes-3`.
- Upstream targeted oracle: `npm test -- --grep nytimes-4` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the NYT debt article graphics and related-link cleanup fixture at `test/test-pages/nytimes-4`.
- Upstream targeted oracle: `npm test -- --grep nytimes-5` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the NYT Spanish section-front collection fixture at `test/test-pages/nytimes-5`.
- Upstream targeted oracle: `npm test -- --grep bbc-1` passes 17 checks with 0 failures in `.upstream-cache/readability`, covering the BBC RDFa articleBody/video-placeholder fixture at `test/test-pages/bbc-1`.
- Upstream targeted oracle: `npm test -- --grep cnn` passes 13 checks with 0 failures in `.upstream-cache/readability`, covering the CNN storytext/SmartAsset widget fixture at `test/test-pages/cnn`.
- Upstream targeted oracle: `npm test -- --grep wapo-1` passes 13 checks with 0 failures in `.upstream-cache/readability`, covering the Washington Post inline gallery/video/graphic fixture at `test/test-pages/wapo-1`.
- Upstream targeted oracle: `npm test -- --grep wapo-2` passes 13 checks with 0 failures in `.upstream-cache/readability`, covering the Washington Post lead media/article/author-bio envelope fixture at `test/test-pages/wapo-2`.
- Upstream targeted oracle: `npm test -- --grep yahoo-2` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the Yahoo application-name/siteName boundary fixture at `test/test-pages/yahoo-2`.
- Upstream targeted oracle: `npm test -- --grep yahoo-3` passes 17 checks with 0 failures in `.upstream-cache/readability`, covering the Yahoo/GMA provider/action chrome fixture at `test/test-pages/yahoo-3`.
- Upstream targeted oracle: `npm test -- --grep yahoo-4` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the Yahoo Japan `ynDetailText` articleBody fixture at `test/test-pages/yahoo-4`.
- Upstream targeted oracle: `npm test -- --grep telegraph` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the Telegraph text-section publisher chrome fixture at `test/test-pages/telegraph`.
- Upstream targeted oracle: `npm test -- --grep liberation-1` passes 17 checks with 0 failures in `.upstream-cache/readability`, covering the Libération French articleBody, Dailymotion iframe, and trailing AFP source-credit fixture at `test/test-pages/liberation-1`.
- Upstream targeted oracle: `npm test -- --grep buzzfeed-1` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the BuzzFeed print image/bio chrome fixture at `test/test-pages/buzzfeed-1`.
- Upstream targeted oracle: `npm test -- --grep lemonde-1` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the Le Monde French articleBody/Dailymotion fixture at `test/test-pages/lemonde-1`.
- Upstream targeted oracle: `npm test -- --grep theverge` passes 17 checks with 0 failures in `.upstream-cache/readability`, covering The Verge content wrapper, pullquote, responsive image, newsletter, and metadata fixture at `test/test-pages/theverge`.
- Upstream targeted oracle: `npm test -- --grep mozilla-1` passes 17 checks with 0 failures in `.upstream-cache/readability`, covering the Firefox main-content wrapper and trailing Sync CTA cleanup fixture at `test/test-pages/mozilla-1`.
- Upstream targeted oracle: `npm test -- --grep aclu` passes 19 checks with 0 failures in `.upstream-cache/readability`, covering the ACLU Drupal panel/sidebar wrapper fixture at `test/test-pages/aclu`.
- Upstream targeted oracle: `npm test -- --grep 002` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the Mozilla Hacks content-main/code-block fixture at `test/test-pages/002`.
- Upstream targeted oracle: `npm test -- --grep article-author-tag` passes 17 checks with 0 failures in `.upstream-cache/readability`, covering the Atlas Obscura article:author metadata and article-body section fixture at `test/test-pages/article-author-tag`.
- Upstream targeted oracle: `npm test -- --grep engadget` passes 17 checks with 0 failures in `.upstream-cache/readability`, covering the Engadget review-gallery/product-chrome fixture at `test/test-pages/engadget`.
- Upstream targeted oracle: `npm test -- --grep google-sre-book-1` passes 15 checks with 0 failures in `.upstream-cache/readability`, covering the Google SRE book chapter-main/table-of-contents fixture at `test/test-pages/google-sre-book-1`.
- Upstream targeted oracle: `npm test -- --grep wikipedia` passes 74 checks with 0 failures in `.upstream-cache/readability`, covering the Wikipedia fixture family including the mapped plain `test/test-pages/wikipedia` article-shell cleanup boundary and the `wikipedia-4` table/category/tracking cleanup boundary.
- Upstream targeted oracle: `npm test -- --grep wikipedia-2` passes 19 checks with 0 failures in `.upstream-cache/readability`, covering the large New Zealand country-page fixture and MediaWiki status-indicator cleanup boundary.
- Upstream targeted oracle: `npm test -- --grep wikipedia-3` passes 19 checks with 0 failures in `.upstream-cache/readability`, covering the Hermitian-matrix math article shell cleanup boundary.
- Upstream targeted oracle: `npm test -- --grep la-nacion` passes 13 checks with 0 failures in `.upstream-cache/readability`, covering the La Nacion leading-BOM/described-NewsArticle fixture boundary.
- Upstream targeted oracle: `npm test -- --grep dev418` passes 13 checks with 0 failures in `.upstream-cache/readability`, covering mixed standalone image, figure, separator, and media-list retention.
- Readability-local PHP coverage: 135 behavior tests, 1653 assertions, 0 failures.
- Root `php tools/run-tests.php`: final gated rerun passed with 208 test files, 24022 assertions, and 0 failures. An earlier aggregate attempt in this turn returned 1 failure with the failure line outside the captured output; the immediate gated rerun was clean.
- WordPress React/Next migration cleanup: contributor or byline text split by parser comment delimiters serializes as one paragraph block instead of multiple fragment blocks.
- Mozilla/fixture text projection boundary semantics are now native for the current slice: text-bearing block, table, and list siblings receive separator whitespace after cleanup so expected text does not concatenate across paragraph-heading, paragraph-paragraph, generated-br paragraph, and table-cell boundaries.
- WordPress block-boundary spacing cleanup: imported article text, search excerpts, and review logs keep paragraph-to-heading and table-cell words separated even when source HTML omits whitespace between adjacent tags.
- Mozilla `test-pages/wordpress` copied into the lane and mapped for WordPress Tavern BlogPosting metadata, articleBody paragraph parity, wp.com image/srcset parity, and Jetpack share/related/comment cleanup.
- Mozilla `test-pages/ol` copied into the lane and mapped for preserving ordered-list content while keeping readerable preflight false because `li p` candidates are skipped.
- Mozilla `test-pages/001` copied into the lane and mapped for metadata-free body byline extraction, nested `itemprop=name` preference, byline metadata parity, and removing the body byline node from extracted content.
- Mozilla `_getClassWeight` semantics are now partially native: upstream positive/negative class/id patterns influence content candidate scoring alongside existing semantic article/main/section weights.
- WordPress/schema.org articleBody candidate selection is now native for BlogPosting-style source pages that omit standard `entry-content` classes, including `itemprop=articleBody` microdata and RDFa `property=articleBody`.
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
- Mozilla `test-pages/guardian-1` copied into the lane from `.upstream-cache/readability/test/test-pages/guardian-1/` and mapped for Guardian articleBody media-root wrapper retention under the optional readability-page oracle wrapper, metadata/readerable parity, 14 retained figures, 13 image payloads, 112 responsive `source[srcset]` rows, 8 caption list items after figures, and removal of Guardian navigation/contribution/byline chrome from article text.
- WordPress Guardian media import cleanup: retained image figures serialize as `wp:image` blocks instead of paragraph blocks, while adjacent Guardian caption list text remains available for review and media attachment workflows.
- Mozilla `test-pages/nytimes-1` copied into the lane from `.upstream-cache/readability/test/test-pages/nytimes-1/` and mapped for NYT metadata/readerable parity, rich figure structure, retained image source, `data-mediaviewer` caption/credit attributes, caller-preserved `caption` figcaption class, caption credit text, and removal of the CSS-hidden reader feedback prompt.
- WordPress NYT media import cleanup: the retained NYT figure serializes as a `wp:image` block with caption/credit text available for media review, while the hidden publisher feedback survey is dropped before post content generation.
- Mozilla `test-pages/nytimes-2` copied into the lane from `.upstream-cache/readability/test/test-pages/nytimes-2/` and mapped for NYT metadata/readerable parity, retained `article#story` oracle wrapper, lead figure/caption credit retention, 24 expected paragraphs, three upstream continuation anchors, and removal of hidden story-ad interrupters plus related story rail chrome.
- WordPress NYT continuation import cleanup: the retained lead figure serializes as one image block, 23 article/continuation paragraphs serialize as paragraph blocks, story-ad containers are dropped, and the related story rail is excluded from imported article text.
- Mozilla `test-pages/nytimes-3` copied into the lane from `.upstream-cache/readability/test/test-pages/nytimes-3/` and mapped for NYT metadata/readerable parity, retained `article#story` oracle wrapper, upstream figure `itemid` lazy-image repair for figures with no `img`/`picture`, 7 retained figures, 8 expected image sources, 6 h2 sections, and related-card plus bottom-ad cleanup.
- WordPress NYT utility media import cleanup: itemid-only NYT figures now gain native image payloads and serialize as 7 `wp:image` blocks, 43 paragraph blocks remain, and the related interactive card plus advertisement slug are excluded from imported article text.
- Mozilla `test-pages/nytimes-4` copied into the lane from `.upstream-cache/readability/test/test-pages/nytimes-4/` and mapped for NYT metadata/readerable parity, promoted `article#story` oracle wrapper around the selected articleBody section, retained lead image/caption payload, 48 expected paragraphs, 4 h2 sections, share-tool removal, debt chart/interactive cleanup, and related-link-card cleanup.
- WordPress NYT debt article import cleanup: the retained lead image serializes as one `wp:image` block, 47 article paragraphs serialize as paragraph blocks, debt chart/interactive chrome is excluded, related-link cards are removed while the upstream-retained `More about` label remains, and publisher share tools are absent.
- Mozilla `test-pages/nytimes-5` copied into the lane from `.upstream-cache/readability/test/test-pages/nytimes-5/` and mapped for NYT Spanish section-front metadata/readerable parity, expected image-source and h2/h3 heading parity, 22 retained media figures, selected story-summary retention, secondary highlight rail pruning, latest-stream cleanup, and section-front ad wrapper removal.
- WordPress NYT section-front import cleanup: collection pages retain media cards and selected summaries for migration review while latest-stream cards, secondary highlight rails, ad wrappers, and tab navigation stay out of WordPress block output.
- Mozilla `test-pages/bbc-1` copied into the lane from `.upstream-cache/readability/test/test-pages/bbc-1/` and mapped for BBC metadata/readerable parity, RDFa `property=articleBody` root preservation under the optional readability-page oracle wrapper, 32 expected paragraphs, 2 h2 headings, 5 image/data-src rows, and removal of unsupported media-placeholder video shells plus navigation chrome.
- WordPress BBC RDFa import cleanup: the selected BBC article root emits 5 `wp:image` blocks and article paragraphs while navigation, unsupported video placeholder captions, and stripped iframe shells are excluded from imported post content.
- Mozilla `test-pages/cnn` copied into the lane from `.upstream-cache/readability/test/test-pages/cnn/` and mapped for CNNMoney metadata/readerable parity, `div#storytext` root preservation under the optional readability-page oracle wrapper, retained SmartAsset powered label, 14 expected paragraphs, one h2 lead, four editorial links, and removal of CNN video player, Teads ad, disclosure widget, and masthead chrome.
- WordPress CNN storytext import cleanup: the CNN fixture serializes as one heading block plus 14 paragraph blocks while video countdown text, ad labels, disclosure links, and masthead media are excluded from migrated post content.
- Mozilla `test-pages/wapo-1` copied into the lane from `.upstream-cache/readability/test/test-pages/wapo-1/` and mapped for Washington Post metadata/readerable parity, 39 expected paragraphs, 14 expected editorial links, one retained inline map image, inline gallery widget cleanup, linked graphic preview cleanup, retained PostTV caption text, and expected content text parity.
- WordPress Wapo inline gallery import cleanup: Washington Post PageBuilder gallery controls, Buy Photo links, interstitial wait text, and linked graphic preview images are removed before block serialization while video caption text, the map caption, and the final inline map image remain available in 39 paragraph blocks.
- Mozilla `test-pages/wapo-2` copied into the lane from `.upstream-cache/readability/test/test-pages/wapo-2/` and mapped for Washington Post metadata/readerable parity, article-body envelope selection when a lead inline-photo sibling precedes the article, top byline/share cleanup, lead image/caption retention, post-body author bio retention, and expected content text parity.
- WordPress Wapo author/media import cleanup: the lead image/caption and author bio remain available as paragraph blocks for review, while top byline controls, share bars, comment widgets, and most-read chrome are excluded from migrated post content.
- Mozilla `test-pages/yahoo-2` copied into the lane from `.upstream-cache/readability/test/test-pages/yahoo-2/` and mapped for title/byline/excerpt/language/readerable/content parity plus the upstream null `siteName` boundary when only `application-name`/page-title-like metadata is available.
- WordPress Yahoo application-name boundary cleanup: source page-title metadata no longer becomes publisher/source-site metadata during import, while 16 paragraph blocks and one review heading block survive and share/ad chrome remains excluded from migrated post content.
- Mozilla `test-pages/yahoo-3` copied into the lane from `.upstream-cache/readability/test/test-pages/yahoo-3/` and mapped for Yahoo/GMA metadata/readerable/content parity, provider/action/topic chrome cleanup, breaking-news link cleanup, retained related editorial links, and retained story image payload.
- WordPress Yahoo/GMA provider chrome cleanup: provider mastheads, save/like controls, topic bars, share controls, and recipe-promo links are removed before block serialization while upstream-retained editorial related links remain available.
- Mozilla `test-pages/yahoo-4` copied into the lane from `.upstream-cache/readability/test/test-pages/yahoo-4/` and mapped for Japanese title/byline/site/excerpt/lang/readerable/content parity, `ynDetailText` article-body selection, nine expected paragraphs, and exclusion of Yahoo navigation, share, ranking, footer, and thumbnail chrome.
- WordPress Yahoo Japan articleBody import cleanup: navigation-heavy Japanese Yahoo pages now emit 9 clean paragraph blocks with 0 image blocks while source navigation, share controls, ranking lists, and footer timestamps stay out of migrated post content.
- Mozilla `test-pages/buzzfeed-1` copied into the lane from `.upstream-cache/readability/test/test-pages/buzzfeed-1/` and mapped for title/site/excerpt/language/readerable/content parity, upstream null byline parity, removal of print image helpers, author bio/contact cleanup, bottom share cleanup, and expected retained image/text counts.
- WordPress BuzzFeed print/bio cleanup: source print-only image links, author bio/contact text, bottom share controls, and promoted ad byline fragments are removed before block serialization while story headings, paragraphs, and inline grid images remain reviewable.
- Mozilla `test-pages/lemonde-1` copied into the lane from `.upstream-cache/readability/test/test-pages/lemonde-1/` and mapped for French title/byline/site/excerpt/lang/readerable/content parity, selected `div#articleBody` preservation, Dailymotion iframe retention, and expected paragraph/heading counts.
- WordPress Le Monde French video import cleanup: articleBody section headings and trusted Dailymotion embeds survive migration while subscription, navigation, social, ad, and recommendation chrome remain excluded from migrated blocks.
- Mozilla `test-pages/theverge` copied into the lane from `.upstream-cache/readability/test/test-pages/theverge/` and mapped for The Verge title/byline/site/date/lang/excerpt metadata, readerable classification, retained `div#content` wrapper under the optional readability-page oracle wrapper, retained pullquote wrapper, promoted responsive image/srcset payload, figcaption parity, newsletter pricing copy, and expected content text parity.
- WordPress The Verge import cleanup: article paragraphs, pullquote copy, newsletter plan copy, and one editorial image serialize into reviewable blocks while subscribe buttons, comment actions, sponsor labels, ad rails, and most-popular/sidebar chrome stay out of migrated content.
- Mozilla `test-pages/citylab-1` copied into the lane from `.upstream-cache/readability/test/test-pages/citylab-1/` and mapped for CityLab title/byline/site/lang/excerpt metadata, readerable classification, exact normalized expected text parity, author RSS feed-list removal, retained author bio, and editorial image/link boundaries.
- WordPress CityLab author feed cleanup: the retained author biography and editorial images remain available for migration review while the source author RSS feed link/list is removed before WordPress block serialization.
- Mozilla `test-pages/mozilla-1` copied into the lane from `.upstream-cache/readability/test/test-pages/mozilla-1/` and mapped for Firefox customize-page title/site/lang/dir/excerpt metadata, readerable classification, normalized expected text parity, optional `main-content` role wrapper serialization, image/link/heading/list parity, and trailing Firefox Sync CTA cleanup.
- WordPress Mozilla Firefox main-content cleanup: the retained product-page copy remains available for migration review while the trailing Sync sign-in CTA and `sync-button` links are removed before block serialization.
- Mozilla `test-pages/aclu` copied into the lane from `.upstream-cache/readability/test/test-pages/aclu/` and mapped for ACLU title/byline/site/date/lang/dir/excerpt metadata, readerable classification, exact normalized expected text parity, Drupal panel sidebar-wrapper survival, seven h3 section headings, 33 article paragraphs, and removal of comments/share/conference chrome.
- WordPress ACLU Drupal panel import cleanup: article paragraphs and headings serialize cleanly from a sidebar-labeled panel layout while comments, share links, conference banners, and hero/theme images stay out of migrated blocks.
- Mozilla `test-pages/telegraph` copied into the lane from `.upstream-cache/readability/test/test-pages/telegraph/` and mapped for Telegraph metadata/readerable parity, null `publishedTime` from dateCreated-only JSON-LD, six retained text-section wrappers under the optional readability-page oracle wrapper, 13 expected paragraphs, and removal of publisher image interrupter sections, related topics, social/share, comment, sidebar, and ad chrome.
- WordPress Telegraph text-section import cleanup: upstream-excluded publisher image interrupters and media credits are dropped before block serialization while all 13 editorial paragraphs survive as paragraph blocks and no image blocks are emitted.
- Mozilla `test-pages/liberation-1` copied into the lane from `.upstream-cache/readability/test/test-pages/liberation-1/` and mapped for Libération title/byline/site/lang/publishedTime/excerpt metadata, readerable classification, normalized expected text parity, retained Dailymotion iframe, and removal of the trailing AFP author/source-credit container.
- WordPress Libération wire-credit cleanup: French wire-service articles keep the trusted metadata byline and retained video embed while a final short `authors-container`/AFP source link is removed before paragraph block serialization.
- Mozilla `test-pages/simplyfound-1` copied into the lane from `.upstream-cache/readability/test/test-pages/simplyfound-1/` and mapped for SimplyFound title/siteName/lang/excerpt metadata, readerable classification, normalized expected text parity, trailing Bootstrap account-approval modal removal, and trailing adsbygoogle container cleanup.
- WordPress SimplyFound account-modal cleanup: source account approval dialogs and empty ad slots appended after the article body are removed before block serialization, while the Raspberry Pi article remains available as 11 paragraph blocks with source-site and language metadata.
- Mozilla `test-pages/la-nacion` copied into the lane from `.upstream-cache/readability/test/test-pages/la-nacion/` and mapped for La Nacion title/excerpt/readerable/content text parity, leading UTF-8 BOM cleanup before DOM parsing, and described NewsArticle root promotion so the `itemprop=description` lead paragraph remains ahead of the `articleBody` section.
- Mozilla `test-pages/dev418` copied into the lane from `.upstream-cache/readability/test/test-pages/dev418/` and mapped for mixed standalone image, figure, separator, and media-list retention with title/excerpt/readerable parity and absolute image URL cleanup.
- WordPress dev418 media-list import cleanup: retained media-only lists now serialize as core list blocks instead of paragraph-wrapped `<ul>` markup, while text-heavy list fixtures keep the previous paragraph-review shape.
- WordPress La Nacion BOM/description import cleanup: source HTML with a BOM before doctype still imports a clean post title/excerpt, keeps the lead summary paragraph, emits 12 paragraph blocks plus 1 image block, and excludes navigation/compatibility-warning chrome.
- Mozilla `test-pages/toc-missing` copied into the lane from `.upstream-cache/readability/test/test-pages/toc-missing/` and mapped for Haki Benita title/byline/site/date/lang/excerpt/readerable parity, retained table-of-contents details, 26 retained SQL code examples, exact normalized expected text parity, and pruning of the external interactive editor CTA body while keeping the upstream `Interactive Editor` title boundary.
- WordPress toc-missing technical article import cleanup: retained TOC and SQL snippets serialize into 96 paragraph blocks, 18 heading blocks, and 26 code blocks while the external editor CTA copy is excluded from migrated blocks.
- Mozilla `test-pages/tmz-1` copied into the lane from `.upstream-cache/readability/test/test-pages/tmz-1/` and mapped for TMZ title/site/excerpt/readerable parity, compact legacy `post-*` envelope promotion around a single `articleBody`, retained split subheadline/date text, duplicate split title-prefix removal, and three retained inline images.
- WordPress TMZ legacy post-envelope import cleanup: old publisher/WordPress-style posts with a compact headline/date wrapper around `itemprop=articleBody` keep the reviewable date and body media while the duplicate split headline prefix is excluded from block output.
- Mozilla `test-pages/invalid-attributes` copied into the lane from `.upstream-cache/readability/test/test-pages/invalid-attributes/` and mapped for title/excerpt/readerable/text parity, malformed empty-attribute wrapper boundary preservation, and sanitized PHP serialization without retaining invalid source attribute syntax.
- WordPress invalid-attribute wrapper cleanup: malformed source wrappers from old exports flatten to one clean paragraph block while the optional oracle output still keeps the wrapper boundary for upstream comparison.
- Mozilla `test-pages/tumblr` copied into the lane from `.upstream-cache/readability/test/test-pages/tumblr/` and mapped for title/site/lang/published/excerpt metadata parity, upstream null byline when JSON-LD `author` is a bare string, readerable classification, single-post `div#posts` promotion over the Tumblr theme table/sidebar shell, normalized expected text parity, and release-note `br` boundary retention.
- WordPress Tumblr single-post import cleanup: the release-note post serializes as one heading block plus one paragraph block while `Powered by Tumblr`, official links, community links, and theme sidebar chrome stay out of migrated content.
- Mozilla `test-pages/medicalnewstoday` copied into the lane from `.upstream-cache/readability/test/test-pages/medicalnewstoday/` and mapped for title/byline/site/lang/excerpt/readerable parity, exact normalized expected text parity, one retained article image, and the upstream byline boundary where a valid `author_byline` node sits inside an article but below an outer `site_header` wrapper.
- WordPress Medical News Today byline import cleanup: `By Ana Sandoiu` is retained as metadata instead of post body text, publisher ad/history chrome is excluded, and the article emits 26 paragraph blocks plus 3 heading blocks for review.
- Mozilla `test-pages/iab-1` copied into the lane from `.upstream-cache/readability/test/test-pages/iab-1/` and mapped for IAB metadata/readerable parity, exact normalized expected text parity, compact leading `10.15.15` header-date cleanup, leading article header-image cleanup, retained post-author bio aside converted to a reviewable content block, and WordPress output with 21 paragraph blocks, one heading block, and one author image block.
- WordPress IAB LEAN author import cleanup: WordPress-style publisher posts can drop source header date/hero media chrome while retaining the post author box for review, so `Scott Cunningham` survives but `10.15.15` and the header hero image do not become migrated block text/media.
- Mozilla `test-pages/bug-1255978` copied into the lane from `.upstream-cache/readability/test/test-pages/bug-1255978/` and mapped for Independent metadata/readerable parity, exact normalized expected text parity, preservation of an `itemprop=articleBody` node whose id contains share-like text, Video.js hidden-control cleanup, in-article gallery promo cleanup, Taboola recommendation exclusion, six retained image payloads, and the upstream-retained reuse-content link.
- WordPress Independent articleBody import cleanup: publisher columns with `gigya-share-btns` ids no longer lose the real article body to share-widget cleanup, while Taboola recommendations, gallery promos, and push-notification CTAs stay out of migrated blocks; the copied scenario emits 32 paragraph blocks and keeps the reviewable reuse link.
- Mozilla `test-pages/comment-inside-script-parsing` copied into the lane from `.upstream-cache/readability/test/test-pages/comment-inside-script-parsing/` and mapped for parser-boundary parity when a script block contains HTML comment delimiters plus a nested pseudo script tag. Native PHP keeps the expected article paragraphs while excluding `Silly test`, `foo.js`, and script tags from article HTML and WordPress blocks.
- WordPress script-comment parser cleanup: legacy exports that retain old commented script payloads serialize as five clean paragraph blocks, zero heading blocks, and no imported script payload text.
- Mozilla `test-pages/lifehacker-post-comment-load` copied into the lane from `.upstream-cache/readability/test/test-pages/lifehacker-post-comment-load/` and mapped for Lifehacker title/byline/site/lang/excerpt metadata, readerable classification, normalized expected text parity, 37 paragraphs, 8 h3 headings, 16 retained editorial list items, 9 image payloads, and removal of Kinja comment/ad/follow/navigation chrome.
- WordPress Lifehacker/Kinja list cleanup: retained `data-textannotation-id` editorial lists now serialize as core list blocks instead of paragraph-wrapped `<ul>` markup, while comment widgets, follow UI, related-blog chrome, and ad labels stay out of migrated blocks.
- WordPress compact ordered-list cleanup: the copied Mozilla `ol` fixture remains mapped for upstream list extraction and now serializes its single retained ordered editorial item as one core ordered list block without changing long encyclopedia/book/release-note list review behavior.
- Mozilla parse-option semantics now have native PHP coverage for `keepClasses`, custom allowed-video regex/pattern handling, `maxElemsToParse` aborts with the upstream-shaped `"Aborting parsing document; N elements found"` error boundary, and `charThreshold` retry/null-result behavior.
- WordPress custom oEmbed migration cleanup: callers can preserve a trusted non-default iframe provider and full source classes for review while unrelated widget iframes are still removed.
- WordPress standalone video iframe block cleanup: retained upstream `iframe`/`object`/`embed` media that survives Mozilla video allowlist cleanup now serializes as core HTML blocks when it is a standalone block child, including copied `embedded-videos` fixture roots and trusted custom-provider migration HTML. Inline paragraph iframes remain inside their surrounding paragraph text boundary.
- WordPress standalone native media block cleanup: retained `video` and `audio` elements from legacy exports now serialize as core HTML blocks when they are standalone block children, with media URLs resolved during existing post-processing and without changing nested wrapper flattening for long-fixture parity.
- WordPress captioned embed-wrapper block cleanup: retained `div`/`section` wrappers with direct iframe/object/embed/video/audio children and only short caption text now serialize as a single HTML block, keeping provider markup and captions together for import review. The copied Independent `bug-1255978` fixture now records its retained publisher video wrapper as one HTML block and one fewer paragraph block.
- WordPress nested embed-wrapper block cleanup: retained `div`/`section` wrappers with one tightly nested iframe/object/embed/video/audio payload and only short caption text now serialize as a single HTML block, keeping provider wrapper markup and captions together while inline paragraph embeds remain paragraph-scoped.
- WordPress char-threshold import boundary: short editorial content hidden in a comment-like source wrapper can be recovered after the strict unlikely-candidate pass fails, while chrome-only empty documents return null instead of creating blank post blocks.
- WordPress class-weight rearm import boundary: `extractWithOptions()` now retries threshold-limited extraction without class/id candidate weighting after strict and unlikely-candidate-relaxed attempts remain below threshold. This preserves the earlier longest-nonempty/null behavior while recovering longer legacy article bodies when a short high-weight wrapper such as `storytext` would otherwise win candidate selection.
- WordPress script/style tag import cleanup: copied Mozilla `style-tags-removal` and `remove-script-tags` fixtures now have focused block-output evidence that retained upstream paragraphs/headings become matching WordPress blocks while raw executable/style tags are excluded from migration output.

## Current Lane Status

- Phase: cloned static inventory plus upstream npm runner evidence and Mozilla fixture/JSON-LD/video/lazy-media/table/hidden-node/br/script-style/social/title/body-byline/metadata/section-wrapper/readability-page/publisher cleanup mappings, now including Wikipedia table/category/tracking cleanup, Wikipedia math-article shell cleanup, Wikipedia Mozilla article shell cleanup parity, Wikipedia country-page status-indicator cleanup, Firefox Nightly WordPress author-header byline extraction, Medical News Today article-scoped byline extraction below a site-header wrapper, IAB leading header chrome cleanup with retained author bio, Independent `bug-1255978` articleBody/Taboola cleanup, comment-inside-script parser-boundary cleanup, Lifehacker/Kinja text-annotation list block cleanup, compact ordered-list WordPress serialization, standalone native media HTML-block serialization, Libération trailing wire source-credit cleanup, SimplyFound account-modal/ad cleanup, La Nacion leading-BOM/description-lead cleanup, dev418 media-list retention, toc-missing interactive editor CTA pruning, tmz-1 legacy post-envelope retention, invalid-attributes wrapper sanitization, Tumblr single-post container promotion, and script/style fixture WordPress block-output closure.
- Native PHP lane tests: 154 passing, 0 failing, 1927 assertions.
- Latest readability-local verification: `php tools/run-tests.php lanes/readability/tests/ArticleExtractorTest.php` passes 1 selected test file, 1927 assertions, and 0 failures.
- Latest root verification for this lane batch: not run - isolated micro-slice. This worktree intentionally avoided the no-argument root harness by contract.
- Upstream runner verification: `npm test` passes 1984 Mozilla Mocha tests, 0 failures, including the 2026-05-22 WordPress articleBody microdata rerun in 41s. Targeted `npm test -- --grep lifehacker-post-comment-load` passes 15 checks in the upstream checkout and covers the Lifehacker/Kinja comment/ad/follow chrome plus retained text-annotation list boundary. Targeted `npm test -- --grep comment-inside-script-parsing` passes 13 checks in the upstream checkout and covers the parser-boundary fixture. Targeted `npm test -- --grep bug-1255978` passes 15 checks in the upstream checkout and covers the Independent articleBody/share-id/Taboola fixture. Targeted `npm test -- --grep iab-1` passes 17 checks in the upstream checkout and covers the IAB leading header-date/header-image cleanup plus retained post-author fixture. Targeted `npm test -- --grep medicalnewstoday` passes 15 checks in the upstream checkout and covers the Medical News Today article-scoped byline fixture. Targeted `npm test -- --grep tumblr` passes 17 checks in the upstream checkout and covers the Tumblr single-post/theme-sidebar fixture plus bare-string JSON-LD author null-byline boundary. Targeted `npm test -- --grep invalid-attributes` passes 13 checks in the upstream checkout and covers the malformed empty-attribute wrapper fixture. Targeted `npm test -- --grep tmz-1` passes 13 checks in the upstream checkout and covers the TMZ legacy post-envelope fixture. Targeted `npm test -- --grep toc-missing` passes 17 checks in the upstream checkout and covers the Haki Benita TOC/interactive-editor fixture. Targeted `npm test -- --grep dev418` passes 13 checks in the upstream checkout and covers mixed media/list retention. Targeted `npm test -- --grep la-nacion` passes 13 checks in the upstream checkout and covers the leading BOM/described-NewsArticle boundary. Targeted `npm test -- --grep simplyfound-1` passes 15 checks in the upstream checkout and covers the SimplyFound trailing account-approval modal/ad boundary. Targeted `npm test -- --grep liberation-1` passes 17 checks in the upstream checkout and covers the Libération trailing AFP source-credit boundary. Targeted `npm test -- --grep firefox-nightly-blog` passes 17 checks in the upstream checkout and covers the Firefox Nightly WordPress article-header author fixture. Targeted `npm test -- --grep wikipedia-2` passes 19 checks in the upstream checkout and covers the large New Zealand country-page/status-indicator boundary, alongside targeted `npm test -- --grep wikipedia` with 74 checks and `npm test -- --grep wikipedia-3` with 19 checks, plus targeted fixture/oracle runs for google-sre-book-1, engadget, article-author-tag, 002, aclu, mozilla-1, citylab-1, keep-images, schema-org-context-object, keep-tabular-data/replace-brs, clean-links, medium-1/2/3, cnet-svg-classes, v8-blog, lazy-image-1, heise, ars-1, guardian-1, nytimes-1/2/3/4/5, bbc-1, cnn, wapo-1/2, yahoo-2/3/4, buzzfeed-1, lemonde-1, telegraph, theverge, parse options, and charThreshold retry/null behavior.
- Blocker: none active for focused readability. Root aggregate verification is intentionally not run in this isolated micro-slice.
- Current work: copied Mozilla script/style cleanup fixtures now prove WordPress block output keeps the expected article paragraph/heading structure without importing raw `<script>` or `<style>` tags.
- Focused smoke: `php lanes/readability/examples/wordpress-script-style-tag-cleanup.php` reports style-tags-removal with 2 paragraph blocks, 2 heading blocks, and script/style tags imported no; remove-script-tags with 5 paragraph blocks, 0 heading blocks, and script/style tags imported no.
- Dependency closure: no new support component is needed; this reuses the existing native DOM script/style stripping, copied Mozilla fixture comparison helpers, and WordPress paragraph/heading block serializer.

## Next Slice

Map `lifehacker-working` or another remaining Kinja/comment-heavy fixture with a targeted upstream oracle, or broaden iframe HTML-block serialization for nested non-paragraph embed wrappers only after updating affected long-fixture expectations deliberately.
