# Bundle Resolution Import Graph Parity 20260601T060556Z

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T060556Z`

Accepted base: `e2c270ed3a9929039fa26f779e2d74a975c61aa8`

Upstream source truth: pinned `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

## Upstream Evidence

The pinned native artifact was probed directly through `lightningcss.linux-x64-gnu.node` because the local Node package wrapper is missing `detect-libc`. The probe used `bundleAsync({ minify: true })` with in-memory files and confirmed recursive `@import` cycles that re-enter the active entry stylesheet through a layer modifier do not wrap the original entry body when recursion unwinds.

Case 1:

```css
/* /entry.css */
@import "a.css";
.entry { color: red }

/* /a.css */
@import "entry.css" layer(foo);
.a { color: green }
```

Native output:

```css
@layer foo;.a{color:green}.entry{color:red}
```

Case 2:

```css
/* /entry.css */
@import "a.css" layer(foo) screen;
.entry { color: red }

/* /a.css */
@import "entry.css" layer(bar) print;
.a { color: green }
```

Native output:

```css
@layer foo.bar;@media screen{@layer foo{.a{color:green}}}.entry{color:red}
```

This matches the upstream `src/bundler.rs::inline()` behavior: recursive entry rules have already been taken while the source is active, so the recursive layer contributes only an empty layer statement and the original entry body is not wrapped later.

## Native PHP Delta

Before the change, the PHP bundler skipped recursive imports entirely and then wrapped the original entry rules in the cycle layer. A red-first PHP probe for the first case produced `.a{color:green}@layer foo{.entry{color:red}}`, which disagreed with upstream.

`CssBundler` now emits a one-time empty layer placeholder for a recursive import cycle that re-enters an active layered stylesheet, records that the layer was consumed by the cycle, and leaves the resumed source body unwrapped. Non-layer recursive imports still collapse to an empty output as before.

Focused coverage adds two upstream-backed assertions in `CssBundlerTest.php` for direct recursive layer cycles and parent layer/media composition. The WordPress import-graph smoke now covers a block/theme recursive layer import path.

## Verification

- `php -l lanes/lightningcss/src/CssBundler.php` - no syntax errors
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - no syntax errors
- `php -r 'foreach (["lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json","lanes/lightningcss/lane-status.json"] as $f) { json_decode(file_get_contents($f), true, flags: JSON_THROW_ON_ERROR); echo "$f ok\n"; }'` - both JSON files decode
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - 1 test files, 568 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - exits 0, including `cycle-layer-import: preserved`
- `php tools/run-tests.php lanes/lightningcss/tests` - 13 test files, 6376 assertions, 0 failures
- `git diff --check -- lanes/lightningcss` - no whitespace errors

Focused assertion delta: `CssBundlerTest.php` grows from 566 to 568 assertions. Full lane PHP evidence grows from 6374 to 6376 assertions. Conservative mapped coverage remains `2359 / 3532` because this deepens an already represented bundle/import graph cluster.

## Dependency Closure

No new support component is needed. This reuses the existing PHP `CssBundler`, import parser, resolver, and example harness.

## Non-Overlap

This slice does not touch CSS Modules compose/export handling, source-map VLQ/remap logic, media-query parser semantics, target-prefixing, CSSOM declaration read/write behavior, custom at-rule visitors, or property/value minification.

Root harness: not run - isolated micro-slice.
