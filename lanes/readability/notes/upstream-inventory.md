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
- Mozilla `test-pages/lazy-image-1` copied into the lane and mapped for metadata description precedence over shorter OpenGraph/Twitter snippets, readerable classification, lazy image `data-old-src` promotion, exact expected article image source row retention, Medium-style out-of-band full-width figure wrapper removal, and post-article recommendation/signup chrome removal.
- Mozilla `test-pages/lazy-image-2` copied into the lane and mapped for HTML entity-decoded excerpt metadata, readerable classification, Kinja in-article ad wrapper removal, exact whitespace-normalized article text parity, and 56 responsive image rows with `data-srcset`/`srcset` parity.
- Mozilla `test-pages/lazy-image-3` copied into the lane and mapped for full-fixture `data-src` jpg/png image promotion, expected title/null metadata, and false readerable classification.
- Mozilla default video whitelist cleanup semantics: generic `iframe`, `embed`, and `object` nodes are removed while allowed video hosts are retained.
- Focused Mozilla lazy-image semantics for noscript fallback promotion, `data-old-src` placeholder preservation, and `data-srcset` promotion.
- Focused Mozilla short non-SVG base64 data URI placeholders are removed before `data-srcset` promotion so responsive candidates survive JavaScript-free extraction.
- Mozilla title/header cleanup semantics from `_headerDuplicatesTitle` and `_prepArticle`: the first content `h1`/`h2` that closely duplicates the extracted title is removed, and remaining `h1` elements are demoted to `h2` because the title is emitted separately.
- Layout-only full-width figure wrapper cleanup: wrappers whose only payload is a single image figure with a short caption are removed when surrounded by paragraph-rich article siblings, while in-column editorial figures are retained for WordPress block image output.

## Current Lane Status

- Phase: cloned static inventory plus upstream npm runner evidence and Mozilla fixture/JSON-LD/video/lazy media/ad wrapper/title-heading/out-of-band figure mappings.
- Native PHP lane tests: 22 passing, 0 failing, 155 assertions.
- Current root verification: `php tools/run-tests.php` passes 69 test files, 3840 assertions, 0 failures.
- Upstream runner verification: `npm test` passes 1984 Mozilla Mocha tests, 0 failures.
- Blocker: no readability-local execution blocker remains. Exact structural HTML parity is still incomplete for `lazy-image-1` wrappers/classes and broader copied lazy-image fixtures.
- Current work: native extraction now removes duplicate title headers from content, demotes body `h1` headings to `h2`, preserves lazy media/video fixtures, removes layout-only full-width figure wrappers, cleans platform chrome, and emits WordPress block output.

## Next Slice

Broaden exact structural HTML parity for copied lazy-image fixtures, starting with wrapper/class cleanup around `lazy-image-1` figures and then expanding to other image-heavy upstream pages.
