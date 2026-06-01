# LightningCSS Bundle Resolution Import Graph Parity - 2026-06-01 07:55Z

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Pinned upstream commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream probe used the native Node addon at `lightningcss.linux-x64-gnu.node`.
- In upstream source-provider mode with only `read`, `@import "https://cdn.example/reset.css"` from `/entry.css` requests `/https://cdn.example/reset.css` from the reader. It is not auto-marked external unless a resolver explicitly returns `{ external }`.
- Upstream `FileProvider` resolves imports with `originating_file.with_file_name(specifier)`, so file-backed bundles also treat URL-shaped specifiers as source-provider file identities.
- Upstream `TestProvider` still keeps its special `https:` external behavior for in-memory test fixtures, so the PHP in-memory `bundle()` path remains unchanged.

## Implemented Behavior

- `CssBundler::resolveImport()` now gates automatic `http(s):` externalization to non-source-provider paths.
- `bundleWithReader()` and `bundleFile()` preserve source-provider path identities for absolute URL-shaped specifiers when no resolver is supplied.
- Resolver-returned external imports still serialize as external imports in reader-backed bundles.

## Focused Evidence

- Red-first PHP probe before the patch:
  - Output kept `@import "https://cdn.example/reset.css" screen`.
  - Reader calls were only `['/entry.css', '/blocks/card.css']`.
- After the patch:
  - `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - Result: `1 test files, 634 assertions, 0 failures`.
  - New focused case: `css bundler resolves reader absolute url imports as source provider paths like upstream`.
- Example smoke:
  - `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - Result included `reader-absolute-url-imports: resolved`.
- Full focused lane:
  - `php tools/run-tests.php lanes/lightningcss/tests`
  - Result: `13 test files, 6814 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP reader, resolver, and filesystem source-provider plumbing.

## Non-Overlap

This does not overlap the recently accepted media query math/grid auto-flow work or earlier bundle slices for malformed import source parsing, EOF import diagnostics, import prelude ordering, escaped import tokens, external import ordering, reader lexical path preservation, CSS Modules dependency source maps, or conditional CSS Modules compose diagnostics.

## Follow-Up

Remaining high-value bundle/import graph work is source-map remapping through CSS Modules dependency imports and resolver diagnostic ordering edges. Full upstream Rust/Node/WASM suites remain unexecuted in this isolated slice.
