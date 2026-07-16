# LightningCSS Font Face Range Minifier Parity

Micro-slice: `lightningcss-property-values-color-font-grid-parity-20260531T145441Z`

Accepted base: `a187757827b58c999a1fc6cda2f4be5e163b73e9`

Upstream source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Targeted pristine read: `git -C /home/claude/port-libs/.upstream-cache/lightningcss show 22bdda3d190f1cd321d98026225cfc964af64ad9:src/lib.rs | sed -n '14577,14743p'`.
- Count evidence: the targeted `src/lib.rs::test_font_face` range contains 38 `minify_test(` helper invocations; this slice maps the remaining 18 not covered by the accepted 20-case font-face minifier inventory.

Native PHP delta:

- `CssMinifier` now collapses equal oblique font-style angle ranges such as `oblique 14deg 14deg` to `oblique`, matching upstream @font-face minification.
- Focused tests now cover unquoted `local(...)` names, additional `format()` plus `tech()` descriptors, `font-weight` and `font-stretch` ranges, explicit/wildcard `unicode-range` lists, and Inter variable-font face descriptors.
- `wordpress-font-face-src-range-minifier.php` now self-checks variable-font and static-font `@font-face` descriptors without Node.

Evidence:

- Red-first probe on base output: `@font-face { font-style: oblique 14deg 14deg; ... }` serialized as `font-style:oblique 14deg`; upstream expects `font-style:oblique`.
- `php -l lanes/lightningcss/src/CssMinifier.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssMinifierTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` -> `1 test files, 836 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 1777 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-font-face-src-range-minifier.php` -> exits 0 and emits expected minified CSS.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, flags: JSON_THROW_ON_ERROR); echo "OK\n";'` -> `OK`.
- `git diff --check -- lanes/lightningcss` -> clean.

Status delta:

- Full LightningCSS PHP evidence: `1759 -> 1777 pass / 0 fail`.
- Conservative mapped coverage: `1232 / 3532 -> 1250 / 3532`.

Non-overlap:

- Does not repeat accepted font-family string serialization, font shorthand composition/default omission, the accepted first 20 @font-face source/unicode-range cases, font-palette/font-feature minification, grid shorthand/longhand composition, HSL/sRGB/custom-property color-mix queued work, flex target prefixing, or media-query validation work.
- Recent queued property-value handoffs inspected before editing cover HSL color-mix, custom-property color functions, grid shorthand areas, grid placement longhands, and font target fallback boundaries. This slice is only the remaining upstream `@font-face` minifier range/descriptor cluster.

Dependency closure:

- No new support component is needed. This reuses the native `CssMinifier` declaration scanner, font value normalizers, URL/string token normalizers, and unicode-range serializer.

Root harness status: not run - isolated micro-slice.
