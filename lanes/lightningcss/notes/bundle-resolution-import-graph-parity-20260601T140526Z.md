# Bundle Resolution Import Graph Parity - Inline Source Map String Fragment Offset

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T140526Z`

Source truth:
- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Native probe: `bundleAsync()` with `sourceMap: true`, minified CSS, and a custom resolver/read pair.
- Upstream output for an entry importing `label.css` before `card.css`:
  - CSS: `.label:before{content:".card{color:green}";color:#00f}.card{color:green}.entry{color:red}`
  - map sources: `entry.css`, `blocks/label.css`, `blocks/card.scss`
  - mappings: `ACAA,sDCAA,kBFAuD`

Behavior ported:
- Inline input source-map remapping now skips generated CSS fragment matches that occur inside earlier CSS strings or comments.
- This matches upstream bundle/source-map behavior where a Sass input map for the imported `.card` rule attaches to the real generated rule, not to an identical `.card{color:green}` byte sequence inside an earlier `content:` string.

Red-first evidence:
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
- Failed before the source patch on `css bundler offsets inline source maps after earlier string fragment matches`.
- Expected generated column: `54`
- Actual generated column: `23`

Implementation:
- `src/CssBundler.php` now finds pending input-map generated fragments with a CSS-aware search that ignores string/comment offsets.
- `tests/CssBundlerTest.php` adds the quoted-fragment import graph test.
- `examples/wordpress-bundle-import-graph.php` adds `source-map-input-string-fragment: remapped` for a block-theme import graph.
- `lane-status.json` records the assertion delta.

Verification:
- `php -l lanes/lightningcss/src/CssBundler.php` - passed.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - passed.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - passed.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - `1 test files, 785 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 950 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - passed, including `source-map-input-string-fragment: remapped`.
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8167 assertions, 0 failures`.

Dependency closure:
- No new support component is needed. The slice reuses the existing PHP bundler, minifier, and source-map remapping components.

Non-overlap:
- Does not repeat the accepted null-byte import source, inline source-map pruning, malformed source-map suppression, media-query resolution x-unit, CSS Modules, or target-prefixing slices.
- Mapped upstream manifest coverage remains `2393 / 3532`; this deepens an existing bundle/source-map behavior row rather than adding a new mapped inventory unit.

Follow-up:
- If upstream evidence requires it, handle duplicate generated fragments that occur outside CSS strings/comments with a stronger source-index-aware remap strategy.
