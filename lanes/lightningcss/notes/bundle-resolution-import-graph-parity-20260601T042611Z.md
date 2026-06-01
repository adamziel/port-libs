# Bundle Resolution Import Graph Parity - 2026-06-01T04:26Z

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T042611Z`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream parser source: `src/parser.rs` parses import preludes with `expect_url_or_string()` and does not reject an empty string token before bundler resolution.
- Upstream bundler source: `src/bundler.rs` forwards parsed `@import` URLs to the `SourceProvider`, so resolver/source-provider behavior decides whether an empty import source is usable.

Behavior added:

- `CssBundler` now accepts empty quoted import strings and empty `url()` import sources as valid parsed sources.
- Empty import sources are passed to the resolver callback with the originating file, preserving the import graph boundary instead of raising a parser diagnostic.
- Existing malformed source tokens, bad quoted `url()` boundaries, unescaped whitespace, and newline errors remain parser errors before resolver reads.

Red-first evidence:

- Before the change, a direct `CssBundler` probe for `@import "";` threw `CssBundleException('parser-error', 'Invalid @import source')` at `/entry.css:1:1` before invoking the resolver.

Evidence:

- `php -l lanes/lightningcss/src/CssBundler.php`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 544 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5949 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
- `git diff --check -- lanes/lightningcss`

Dependency closure:

- No new support component is needed. This reuses the existing `CssBundler` parser, resolver callback, reader, and in-memory source provider paths.

Non-overlap:

- Does not repeat accepted CSS Modules forgiving selector, CSSOM SVG paint/rendering, custom at-rule block token, or target-prefix appearance boundary slices.
- Does not edit stale custom-media rework areas from the current-rebase note.
- Conservative mapped coverage remains `2336 / 3532`; this strengthens the already represented bundle/import graph cluster and adds `+4` focused PHP assertions.
