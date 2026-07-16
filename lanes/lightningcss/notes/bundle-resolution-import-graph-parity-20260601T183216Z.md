# Bundle/import graph parity - repeated media imports

Slice: `lightningcss-bundle-resolution-import-graph-parity-20260601T183216Z`

Source truth:

- Upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/bundler.rs::load_file` clears stored media conditions when a repeated import later has an empty media list, and leaves an already-unconditional repeated import unconditional when a later import has media.
- `src/bundler.rs::order` preserves the last browser-evaluated `@import` position for repeated file imports.

Implemented:

- Added focused PHP coverage for repeated media-conditioned imports that become unconditional while still emitting at the last import position around external and sibling imports.
- Added coverage for the inverse order, where an unconditional first import remains unconditional after a later media-conditioned repeat.
- Kept the existing media/media merge behavior guarded with an intervening sibling import so combined conditional imports also move to the last import position.
- Added the same WordPress block-theme smoke to the bundle import-graph example.

Verification:

- `php -l lanes/lightningcss/tests/CssBundlerTest.php`
  - `No syntax errors detected in lanes/lightningcss/tests/CssBundlerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
  - `No syntax errors detected in lanes/lightningcss/examples/wordpress-bundle-import-graph.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php`
  - `1 test files, 858 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test`
  - exited 0 and printed `repeated-media-unconditional-import: merged-last`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8938 assertions, 0 failures`
- `php -r '$json = json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true); if (!is_array($json)) { fwrite(STDERR, json_last_error_msg() . "\n"); exit(1); } echo "lane-status.json valid\n";'`
  - `lane-status.json valid`
- `git diff --check -- lanes/lightningcss`
  - exited 0

Dependency closure:

- No new support component is needed. The existing native PHP bundler already has the repeated import resolver, media/supports merge, and last-position graph ordering paths; this slice adds the missing upstream-backed guard and WordPress smoke.

Non-overlap:

- Avoids accepted CSS Modules empty-from resolution, source-map offset/import-map work, duplicate supports-only imports, repeated layered imports, resolver result-shape diagnostics, external import string serialization, and source provider read parity. This slice is limited to repeated media-conditioned file imports clearing to unconditional output while preserving the final import graph position.
