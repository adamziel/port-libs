# LightningCSS Bundle Import Prelude Ordering 2026-05-31T13:14Z

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260531T131447Z`

Source truth:

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Static source read: `src/bundler.rs` only inlines import/layer prelude rules before the first other rule.
- Local upstream binary check: late `@import` after a style rule and after `@namespace` both report `@import rules must precede all rules aside from @charset and @layer statements`; `@charset`, `@layer` statements, and comments remain valid before imports.

Implementation:

- `CssBundler` now tracks whether top-level imports are still allowed while scanning a stylesheet.
- A late top-level `@import` after a real rule throws `CssBundleException` kind `parser-error` with source file, line, and upstream-aligned column.
- `@charset` statements, `@layer` statements, ordinary comments, and license comments do not close the import prelude.
- The WordPress bundle example now smokes a valid block-theme graph with `@charset` before imports and verifies a broken late-import block-theme stylesheet is rejected.

Status delta:

- Conservative mapped coverage: `1042 / 3532` to `1045 / 3532` (+3 focused bundle/import graph checks).
- PHP evidence: `1339 pass / 0 fail` to `1349 pass / 0 fail` after full LightningCSS lane verification.
- Root harness: not run - isolated micro-slice.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` => `1 test files, 61 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 1349 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` => exits 0 and prints `late-import: rejected`.
- `php -l` passed for changed PHP files.
- `git diff --check -- lanes/lightningcss` passed.

Dependency closure:

- No new support component is needed. This reuses the existing bounded `CssBundler` top-level scanner, source-location helper, path resolver, `CssMinifier`, and `CustomMediaTransformer`; no upstream binary, browser service, filesystem crawler, or parser generator is required for native progress.

Non-overlap:

- This slice avoids the accepted license-comment bundler/minifier behavior, external-import-after-bundled-import ordering, media/layer/supports import graph wrapping, custom-media import sharing, source-map VLQ offsets, CSS Modules local/global/composes behavior, and the accepted parser/prefix/media/property CSS slices. The follow-up bundle gap is CSS Modules dependency graph bundling or source-map graph integration, not another late-import diagnostic.
