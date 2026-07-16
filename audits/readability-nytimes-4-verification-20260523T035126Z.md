# Readability nytimes-4 Verification - 2026-05-23T03:51:26Z

Scope: map and verify the Readability `nytimes-4` fixture slice from Mozilla Readability commit `08be6b4bdb204dd333c9b7a0cfbc0e730b257252`.

## Fixture Slice

- Upstream fixture copied from `.upstream-cache/readability/test/test-pages/nytimes-4/` to `lanes/readability/fixtures/mozilla/nytimes-4/`:
  - `source.html`
  - `expected.html`
  - `expected-metadata.json`
- Fixture provenance:
  - Upstream `source.html` hash: `a14721bdc64eb917b4099d666a0ea6b8b218997cac06cbf008b3df25f2ffeb4e`; lane copy hash after trailing-line-space normalization for `git diff --check`: `48f53a007f3622e5e1221d6cd95d98ed1d8448ce360d258a69ba3a6f4e996de9`.
  - `expected.html` matches upstream hash: `d94802c57f2b6e6ee15bb70d4449ea74a9aca646800e81264c1d3fa47dcf470d`.
  - `expected-metadata.json` matches upstream hash: `8583d3b101e6c8dc0db2acaa71924ec8ae9bb9a55d1d08bcdac6743699f74604`.
- Native coverage lives in `lanes/readability/tests/ArticleExtractorTest.php` under `maps Mozilla nytimes-4 fixture with debt article graphics and related-link cleanup`.
- WordPress example lives in `lanes/readability/examples/wordpress-nytimes-debt-article-import.php`.

## Evidence

- Upstream targeted oracle: `npm test -- --grep nytimes-4` in `.upstream-cache/readability` passed 15 checks, 0 failures, in 580ms. The run covered `isProbablyReaderable`, jsdom expected content/metadata checks, and upstream `JSDOMParser` expected content/metadata checks.
- Focused native PHP coverage: the targeted `TestRunner` invocation for `lanes/readability/tests/ArticleExtractorTest.php` passed 105 tests, 1007 assertions, 0 failures.
- WordPress NYT debt article example: `php lanes/readability/examples/wordpress-nytimes-debt-article-import.php` reported 47 paragraph blocks, 1 image block, debt chart chrome retained `no`, related link cards retained `no`, share tools retained `no`.
- JSON validation: `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, and `lanes/readability/fixtures/mozilla/nytimes-4/expected-metadata.json` decoded cleanly.
- Whitespace check: `git diff --check -- lanes/readability` produced no output and exited 0.
- Aggregate root suite: `php tools/run-tests.php` passed 182 test files, 17632 assertions, 0 failures.

## Decisions

- The native slice promotes a selected NYT `section[itemprop=articleBody]` back to the surrounding `article#story` only when that wrapper has a header, preserving the upstream oracle wrapper without broadening generic section promotion.
- The cleanup removes NYT `data-testid="share-tools"`, combined `interactive-embedded custom-graphic-container` debt chart chrome, and `module=relatedlinks` anchors while retaining the lead image, caption, article paragraphs, and upstream-retained related-topic label boundary.

## Blockers

- No readability-local, upstream targeted, or aggregate root blocker is active.
