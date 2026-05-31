# Bundle Resolution Import Graph Parity - 2026-05-31T20:53Z

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream NAPI `JsSourceProvider::resolve()` falls back to `originating_file.with_file_name(specifier)` when no custom resolver is supplied.
- Local Rust confirmation for the same `Path::with_file_name` operation:
  - `Path::new("hello/world.css").with_file_name("../bar.css")` yields `hello/../bar.css`.
  - `Path::new("/theme/blocks/card.css").with_file_name("../base.css")` yields `/theme/blocks/../base.css`.
- This makes reader-backed default resolution preserve lexical `..` identity for source-provider reads and import-graph keys instead of normalizing to the parent directory.

## Native Delta

- `CssBundler::defaultResolvedFileResult()` now preserves default resolver paths whenever resolver paths are meant to be preserved, including reader-backed source providers.
- Reader source-map expectations now assert raw lexical import reads such as `/theme/../shared/button.css`.
- Focused import graph coverage now distinguishes `/theme/base.css` from `/theme/blocks/../base.css`, matching the upstream default resolver identity.
- `wordpress-bundle-import-graph.php --self-test` includes a reader-backed block-theme import graph smoke that fails if the lexical `../` path is normalized before `read()`.

## Evidence

- Red-first probe before the source change: a reader graph with `hello/world.css` importing `../bar.css` read `bar.css` and emitted the normalized root file, proving the PHP port collapsed `hello/../bar.css`.
- `php -l lanes/lightningcss/src/CssBundler.php`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`: `1 test files, 309 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests`: `13 test files, 4264 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`: exits `0`
- `git diff --check -- lanes/lightningcss`: exits `0`

## Status Delta

- Focused `CssBundlerTest.php` moved from `307` to `309` assertions.
- Full LightningCSS PHP lane moved from `4262` to `4264` assertions.
- Conservative mapped coverage remains `2093 / 3532`; this deepens the already mapped `SourceProvider` / bundle import graph cluster instead of adding a new denominator row.

## Dependency Closure

No new support component is required. The slice reuses the native PHP `CssBundler`, reader callback, source-map, and minifier paths. No Node, Rust, WASM, filesystem watcher, or external service dependency is introduced.

## Non-Overlap

This does not repeat accepted import layer-name validation, filesystem lexical identity, resolver-returned path identity, escaped import specifier decoding, import modifier ordering, source-map remapping, CSS Modules dependency graph handling, custom media graph propagation, media-layer imports, CSSOM, visitor, or target-prefixing slices. It is limited to upstream reader-backed default resolution identity before source-provider `read()` and import graph de-duplication.
