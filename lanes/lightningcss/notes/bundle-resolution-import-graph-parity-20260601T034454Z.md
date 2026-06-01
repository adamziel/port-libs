# Bundle Import Graph Supports Escapes - 2026-06-01T03:44Z

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T034454Z`

Source truth:

- Upstream checkout: `/home/claude/port-libs/.upstream-cache/lightningcss`
- Commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- Upstream cases: `src/bundler.rs` import-condition wrapping and `node/test/bundle.test.mjs` resolver/import graph tests. The upstream parser tokenizes import modifier preludes before graph traversal, so CSS escaped identifiers in `supports()` conditions are decoded before a bundled dependency or resolver-marked external import is emitted.

Behavior added:

- `CssBundler` now normalizes CSS identifier escapes inside `@import ... supports(...)` conditions before validation, resolver calls, bundled wrapper emission, or external import serialization.
- The scanner preserves strings and comments, decodes escaped logical operators such as escaped `and`/`not`, and decodes escaped function identifiers such as escaped `selector(...)`.
- Numeric dimensions in the same condition remain numeric tokens, so `1px` and `-1px` are not reserialized as escaped identifiers.
- Bundled imports and resolver-marked external imports now share the normalized supports condition, matching the import graph parser behavior rather than preserving raw escape text.

Red-first evidence:

- Before the patch, `@import "tokens.css" supports((display: grid) \61nd (color: red))` emitted `@supports (display:grid) \61nd(color:red){...}`.
- Before the patch, `@import "tokens.css" supports(\6e ot (display: grid))` emitted `@supports (\6e ot(display:grid)){...}`.
- Before the patch, resolver-marked external imports preserved the escaped operator in `supports((display:grid) \61nd(color:red))`.

Evidence:

- `php -l lanes/lightningcss/src/CssBundler.php`
- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 527 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 5811 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`

Dependency closure:

- No new support component is needed. This reuses `CssBundler`'s existing CSS identifier tokenizer, import supports validator, resolver/read graph, and supports-rule serializer.

Non-overlap:

- Does not edit the stale CustomMediaTransformer rework note surface from `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-lightningcss-current-rebase-20260525T053931Z-02383337.needs-lane-rework.md`.
- Does not repeat accepted import specifier decoding, escaped import layer names, malformed condition-tail validation, duplicate supports import merging, source-map import graph, or CSS Modules dependency graph slices.
- Conservative mapped coverage remains `2320 / 3532`; this strengthens the already represented bundle/import graph cluster rather than claiming a new denominator row.
