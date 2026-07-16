# Source Map Generated-Offset Span Parity - 2026-06-01

Lane: `lightningcss`
Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T142603Z`
Base accepted HEAD: `a5614704e60ea0cab87726a10629a257ac3e49fd`

## Source Truth

Pinned LightningCSS source remains `22bdda3d190f1cd321d98026225cfc964af64ad9`. The behavior is taken from `parcel_sourcemap-2.1.1` `SourceMap::add_sourcemap()` and `MappingLine`: appending a child source map preserves whole generated mapping lines, including empty trailing generated-line spans. This slice applies that rule to the PHP port's generated-offset append path used by bundled inline input source maps.

## Native Delta

- `SourceMap::appendSourceMapWithGeneratedOffset()` now groups child mappings by generated line and iterates the child map's full generated-line count.
- Empty child lines, including trailing empty lines after the last mapping, advance the parent generated-line span after the generated offset.
- Same-line child mappings are validated and remapped before they are inserted for that line; the child map is still drained after append.
- Added a WordPress-relevant smoke for a block-generated input map appended after theme CSS.

Manual red-first check before the change produced `AAAAA;;ICAAC` for child VLQ `AAAAA;;` appended at generated offset line `2`, column `4`. The patched behavior preserves the child trailing spans and writes `AAAAA;;ICAAC;;`.

## Verification

- `php -l lanes/lightningcss/src/SourceMap.php` -> no syntax errors
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-source-map-generated-offset-spans.php` -> no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 965 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-generated-offset-spans.php --self-test` -> `OK`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 778 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8199 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'` -> `JSON OK`
- `git diff --check -- lanes/lightningcss` -> passed

Focused SourceMap coverage moved from `950` to `965` assertions. Full LightningCSS lane evidence moved from `8184` to `8199` assertions. Conservative mapped coverage remains `2393 / 3532`.

## Non-Overlap

No `port-lightningcss-*.needs-lane-rework.md` note existed for this lane. This does not repeat earlier source-map offset, positive empty child add-source-map, duplicate generated-column, raw VLQ import, or line-local offset coverage. The bounded behavior is specifically generated-offset append preservation of trailing empty child generated-line spans.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP `SourceMap` VLQ, offset, remap, buffer, and closest-mapping paths.

Root harness status: not run - isolated micro-slice.
