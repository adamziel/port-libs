# Bundle/import graph parity - backslash import paths

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T231929Z`

Source truth:

- Upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/bundler.rs::FileProvider::resolve()` resolves relative imports with `originating_file.with_file_name(specifier)`.
- The upstream test provider in `src/bundler.rs` uses the same `with_file_name(specifier)` path construction.
- A Rust path probe on this Unix worker confirmed `Path::new("/entry.css").with_file_name("blocks\\card.css")` displays as `/blocks\card.css`, so a decoded backslash is a literal file-name byte rather than a path separator.

Red-first evidence:

- Before this patch, the PHP in-memory source provider resolved `@import "blocks\\\\card.css"` to the slash-path sibling when both `/blocks\card.css` and `/blocks/card.css` existed.
- The same import through the reader-backed provider preserved `/blocks\card.css`, matching upstream lexical path identity.

Implemented:

- `CssBundler` now keeps a backslash-preserved lookup key for in-memory file paths that contain decoded backslashes.
- The slash-normalized fallback for a backslash-named in-memory file is only filled when no slash-path source exists, so import graph identity is stable regardless of file array order.
- Default and custom resolver results that contain a backslash now preserve the resolved path when loading the imported stylesheet instead of normalizing it through `/`.
- Added focused coverage that keeps `/blocks\editor.css`, `/blocks/editor.css`, `/tokens\pkg.css`, and `/tokens/pkg.css` distinct across default resolution, custom resolver paths, source-map generation, and slash/backslash source ordering.
- Added a WordPress block-theme import-graph smoke for literal backslash block stylesheet paths with a slash-path collision guard.

Verification:

- `php -l lanes/lightningcss/src/CssBundler.php`
  - `No syntax errors detected in lanes/lightningcss/src/CssBundler.php`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 865 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - exited 0 and printed `backslash-import-path: preserved`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 9109 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - exited 0

Status delta:

- `phpPass` moves `9102 -> 9109` from the full lane-scoped run.
- Mapped upstream inventory remains `2439 / 3532`; this is a behavior parity patch rather than a manifest-denominator expansion.
- Root harness was not run for this isolated micro-slice.
- Rust/Node/WASM upstream runners were not executed.

Dependency closure:

- No new support component is needed. The existing PHP bundler resolver and source-provider path model is reused; the missing behavior was preserving decoded backslash path identity in the in-memory provider and resolver result handoff without collapsing slash-path siblings.

Non-overlap:

- Avoids accepted external import ordering, repeated import graph ordering, source-map VLQ offset parity, URL delimiter escaping, layer/supports/media import composition, source provider reader parity, and CSS Modules dependency graph behavior. This slice is limited to upstream lexical path identity for decoded backslashes in bundle import resolution.
