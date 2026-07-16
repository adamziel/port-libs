# Media Query Range/Layer Empty List Parity

Slice: `lightningcss-media-query-range-layer-parity-20260601T035839Z`

Source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted source: `src/media_query.rs::MediaList::parse`, which parses each list member with `parse_until_before(Delimiter::Comma, ...)` and treats `EndOfInput` before a member as the trailing-comma stop condition.
- Targeted native binding probes at the pinned commit:
  - `@media screen, { .a { color: red } }` serializes as `@media screen{.a{color:red}}`.
  - `@media (width >= 1px), { .a { color: red } }` serializes as `@media (width>=1px){.a{color:red}}`.
  - `@media ,screen { .a { color: red } }`, `@media screen,,print { .a { color: red } }`, `@media screen, ,print { .a { color: red } }`, and `@layer blocks { @media (width >= 1px),,(hover) { .a { color: red } } }` reject with `Unexpected token Comma`.

Native PHP delta:

- `MediaQueryParser::minifyList()` now preserves top-level empty comma-separated members while splitting media lists.
- A single trailing empty member after a valid media query is accepted and omitted from output.
- Leading and middle empty members throw `InvalidArgumentException`, so `CssMinifier` rejects invalid plain and `@layer`-wrapped range media queries before target fallback serialization.
- The WordPress media range/layer example now covers the accepted trailing-comma range query and the rejected double-comma guard.

Verification:

- `php -l lanes/lightningcss/src/MediaQueryParser.php`
- `php -l lanes/lightningcss/tests/MediaQueryParserTest.php`
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php`
- `php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `2 test files, 1365 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5865 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test`
  - passed; includes `trailingCommaRangeList` and `emptyMediaListGuard`
- `git diff --check -- lanes/lightningcss`
  - passed

Dependency closure:

- No new support component is needed. This reuses the existing native PHP `MediaQueryParser`, `CssMinifier`, and target-prefixing path.

Non-overlap:

- Does not touch the stale custom-media import-tail rework note.
- Does not repeat accepted resolution prefixing, range target fallbacks, case-sensitive custom media, CSSOM, bundle/import, CSS Modules, or custom at-rule visitor clusters.

Next task:

- Continue with a distinct media-query parser or target-prefix behavior, such as unrepresented condition parser recovery or feature-specific target fallback boundaries, rather than another empty comma-list variant.
