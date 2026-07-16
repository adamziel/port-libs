# LightningCSS Media Query Range Layer Parity - 2026-06-01T02:11:08Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream files inspected:
  - `src/bundler.rs`: repeated imports of the same file call `entry.media.or(&rule.media)`.
  - `src/media_query.rs`: `MediaList::or()` appends only media queries not already contained in the parsed media list.

## Native PHP Delta

- `CssBundler::combineMediaOr()` now minifies each media-list member before deduplication.
- This maps parsed-equivalent media ranges such as `(min-width: 250px)` and `(width >= 250px)` to one canonical query before repeated import merging.
- The behavior is exercised through layered WordPress-style import graphs so already imported block CSS is not emitted under duplicate equivalent range conditions.

## Focused Evidence

- Focused tests added in `CssBundlerTest.php` for repeated layered imports with equivalent media range syntax and existing media-list members.
- `wordpress-media-range-layer-import-graph.php` now smokes the WordPress-facing repeated range import path.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-media-range-layer-import-graph.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => 1 file / 482 assertions / 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests` => 13 files / 5453 assertions / 0 failures.
- `php lanes/lightningcss/examples/wordpress-media-range-layer-import-graph.php` => exited 0.

## Dependency Closure

No new support component is needed. The slice reuses the existing native `MediaQueryParser` and `CssBundler` import graph machinery.

## Non-overlap

This does not repeat accepted resolution prefixing, range lowering, JSON/source-map/CSS Modules, custom at-rule, target-prefix, or property-value slices. It is limited to upstream `MediaList::or()` equivalence during repeated bundled import merging.
