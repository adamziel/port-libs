# Bundle Resolution Import Graph Parity 2026-06-01T07:01Z

## Source Truth

- Upstream: `parcel-bundler/lightningcss` at pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Behavior checked with the pinned native LightningCSS NAPI bundle from the upstream cache because the JS entrypoint has an unavailable local `detect-libc` dependency.
- Upstream `bundleAsync({ cssModules: true })` rejects a stylesheet imported under `layer(...)`, `media`, or `supports(...)` when the imported CSS Modules file contains `composes`. The thrown diagnostic is a parser-style syntax error on the imported file, not on the parent import rule.
- Observed upstream locations:
  - `.card { composes: token from "tokens.css"; color: red; }` reports `/component.css` line `1`, column `18`.
  - Multi-line `.card {\n  composes: token;\n  color: red;\n}` reports `/component.css` line `2`, column `12`.

## Native Delta

- `CssBundler` now records the first CSS Modules `composes:` declaration location while loading each stylesheet.
- Conditional import validation now raises `CssBundleException` with kind `parser-error`, source file, line, and column when media/supports/layer wrapping would place CSS Modules `composes` in a nested rule.
- The WordPress bundle import-graph smoke now checks that conditional CSS Modules composes rejection includes the upstream-style diagnostic location.

## Evidence

- Baseline focused gate before this slice: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` passed `1 test files, 583 assertions, 0 failures`.
- Focused gate after this slice: `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` passed `1 test files, 596 assertions, 0 failures`.
- Full lane gate: `php tools/run-tests.php lanes/lightningcss/tests` passed `13 test files, 6565 assertions, 0 failures`.
- Lint: `php -l lanes/lightningcss/src/CssBundler.php`, `php -l lanes/lightningcss/tests/CssBundlerTest.php`, and `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` all reported no syntax errors.
- Example: `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` exited `0`.
- Whitespace: `git diff --check -- lanes/lightningcss` exited `0`.
- Root harness: not run - isolated micro-slice.

## Status Delta

- Focused assertion count moved `583 -> 596` for `CssBundlerTest.php`.
- Full LightningCSS lane assertion count moved `6552 -> 6565`.
- `phpPass` moved `6552 -> 6565`; `phpFail` remains `0`.
- Conservative mapped coverage remains `2360 / 3532` because this deepens the already represented CSS Modules bundle/import graph diagnostics cluster.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP bundler, resolver/source-provider plumbing, CSS Modules declaration scanner, and `CssBundleException` diagnostics.

## Non-Overlap

This does not repeat accepted bundle import parsing, import ordering, source identity, file-backed SourceProvider, missing-export dependency, hidden/visible CSS Modules export, or previous invalid-composes coverage. The slice is limited to post-import-graph conditional wrapping diagnostics with structured source locations for CSS Modules `composes`.

## Follow-Up

Next high-value bundle/import graph work is another upstream-backed diagnostic surface with structured source locations, such as resolver failures after nested graph traversal or additional CSS Modules import/export error locations that are still only represented as message-only exceptions.
