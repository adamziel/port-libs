# Readability nytimes-3 Verification - 2026-05-23T03:32:07Z

Scope: verify the committed Readability `nytimes-3` fixture slice at lane commit `b439cf5`.

## Fixture Slice

- Upstream fixture copied under `lanes/readability/fixtures/mozilla/nytimes-3/`:
  - `source.html`
  - `expected.html`
  - `expected-metadata.json`
- Native coverage lives in `lanes/readability/tests/ArticleExtractorTest.php` under `maps Mozilla nytimes-3 fixture with figure itemid lazy images and related-card cleanup`.
- WordPress example lives in `lanes/readability/examples/wordpress-nytimes-utility-media-import.php`.

## Evidence

- Upstream targeted oracle: `npm test -- --grep nytimes-3` in `.upstream-cache/readability` passed 15 checks, 0 failures, in 964ms. The run covered `isProbablyReaderable`, jsdom expected content/metadata checks, and upstream `JSDOMParser` expected content/metadata checks.
- Focused native PHP coverage: direct run of `lanes/readability/tests/ArticleExtractorTest.php` passed 104 tests, 984 assertions, 0 failures.
- WordPress NYT utility-media example: `php lanes/readability/examples/wordpress-nytimes-utility-media-import.php` reported 43 paragraph blocks, 7 image blocks, related card retained `no`, advertisement retained `no`.
- JSON validation: `lanes/readability/UPSTREAM_TEST_MANIFEST.json`, `lanes/readability/lane-status.json`, and `lanes/readability/fixtures/mozilla/nytimes-3/expected-metadata.json` decoded cleanly.
- Whitespace check: `git diff --check -- lanes/readability` produced no output and exited 0.
- Aggregate root suite: `php tools/run-tests.php` passed 178 test files, 17211 assertions, 0 failures.

## Notes

- No native implementation code calls Mozilla Readability.
- Root worktree contains unrelated dirty files outside the Readability owned scope; they were not edited for this verification.
