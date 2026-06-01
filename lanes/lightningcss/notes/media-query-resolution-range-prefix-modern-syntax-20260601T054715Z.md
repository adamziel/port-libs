# Media Query Resolution Range Prefix Modern Syntax

Slice: `lightningcss-media-query-range-layer-parity-20260601T054715Z`

Source truth:

- Pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/lib.rs::test_media` covers resolution prefixing for range queries such as `(resolution > 2dppx)` and `(resolution >= 300dpi)`.
- `src/media_query.rs::MediaCondition::transform_resolution` rewrites resolution range feature names and values while preserving the range operator when `MediaRangeSyntax` lowering is not active.

Behavior added:

- `TransitionPrefixer` now prefixes modern resolution range conditions without requiring the media range syntax fallback first.
- With `exclude => ['MediaRangeSyntax']`, `@media (resolution >= 2dppx)` for Safari 15 now emits `(-webkit-device-pixel-ratio>=2),(resolution>=2dppx)` instead of only the unprefixed query.
- Firefox 3.5-15 receives the equivalent `-moz-device-pixel-ratio` modern range clone when range syntax is preserved.

Red-first probe before the fix:

```text
@layer blocks{@media (resolution>=2dppx){.wp-block-query{color:#ff0}}}
@media only screen and (resolution>=124.8dpi){.foo{color:#ff0}}
```

Focused verification:

```text
php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1013 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php
passed

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 6270 assertions, 0 failures
```

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP media query parser and target prefixer.
