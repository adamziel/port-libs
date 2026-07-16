# LightningCSS Bundle Unknown Statement Import Parity

Micro-slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T112014Z`

Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

## Upstream Evidence

Native probe used `/home/claude/port-libs/.upstream-cache/lightningcss/lightningcss.linux-x64-gnu.node` directly because the lane's pinned Node wrapper lacks its `detect-libc` dependency in this isolated worktree.

- `@wp-bundle meta; @import "dep.css" screen; .entry { color: red }` resolves and reads `/dep.css`, but output preserves `@import "dep.css" screen` after the statement-form unknown at-rule instead of inlining the dependency.
- `@wp-bundle meta; @import "pkg:card\2e css" layer(theme.blocks) supports(display: grid) screen; .entry { color: red }` calls the resolver with decoded `pkg:card.css`, reads `/blocks/card.css`, and outputs `@import "pkg:card.css" layer(theme.blocks) supports(display:grid) screen`.
- `@wp-bundle { color: red } @import "dep.css"; .entry { color: red }` rejects with `@import rules must precede all rules aside from @charset and @layer statements` at line 1 column 34 and does not read the dependency.

## Implementation

- `CssBundler::topLevelItems()` now treats statement-form unknown at-rules as import-transparent for parsing, matching the upstream parser state for unknown no-block at-rules.
- `CssBundler::inline()` now preserves imports encountered after body output has started. It still consumes the resolved dependency slot so resolver/read diagnostics happen, but it serializes the decoded original import prelude instead of inlining the resolved file.
- `@custom-media` remains a body barrier in the PHP bundler so this slice does not alter the lane's accepted custom-media transform semantics.
- `wordpress-bundle-import-graph.php` now smokes a block-theme unknown at-rule import that resolves, reads, and remains preserved.

## Verification

- Red check before fix: ad hoc PHP `CssBundler::bundleWithReader()` for `@wp-bundle meta; @import "dep.css"; ...` failed with `CssBundleException: @import rules must precede all rules aside from @charset and @layer statements`.
- `php -l lanes/lightningcss/src/CssBundler.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 720 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php` -> exited 0 and printed `unknown-statement-import: preserved-after-resolution`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 7535 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Status Delta

- Focused `CssBundlerTest.php` assertions moved `711 -> 720`.
- Full LightningCSS lane assertions moved `7526 -> 7535`.
- Conservative mapped upstream coverage remains `2369 / 3532`; this deepens the already represented bundle/import graph cluster.

## Dependency Closure

No new support component is needed. This reuses the native PHP bundle graph scanner, resolver/read callback boundary, CSS import serializer, existing PHP test harness, and WordPress bundle smoke. No Node, Rust, WASM, browser service, package manager, or credentialed provider is required.

## Non-Overlap

This slice does not repeat accepted escaped import source parsing, URL token boundary parsing, CRLF escape terminators, resolver object-shape diagnostics, conditional CSS Modules `composes` validation, source-map import remapping, media/supports/layer condition composition, CSSOM, target-prefixing, custom-at-rule visitors, or property-value clusters. It only covers statement-form unknown at-rules before imports and the directly coupled import preservation/inlining boundary.

## Next Task

Continue bundle/import graph work on source-map resolver evidence, CSS Modules dependency/source-map boundaries, or additional resolver/read diagnostics that are distinct from statement-form unknown at-rule import preservation.
