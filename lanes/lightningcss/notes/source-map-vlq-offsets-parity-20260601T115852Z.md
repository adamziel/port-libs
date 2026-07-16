# Source Map VLQ Offsets Parity - 2026-06-01

## Source Truth

- LightningCSS upstream manifest pin: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map behavior source truth: local `parcel_sourcemap-2.1.1` crate files:
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/lib.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/mapping_line.rs`
  - `/home/claude/.cargo/registry/src/index.crates.io-1949cf8c6b5b557f/parcel_sourcemap-2.1.1/src/vlq_utils.rs`

`SourceMap::extends()` imports the input map source/name/sourceContent tables before iterating mappings. During iteration it mutates each output mapping as it remaps it; if a later remap rejects an invalid source or name index, earlier mapping mutations remain applied, and the failing plus unvisited mappings remain in their previous compiled-map state.

## Native Delta

- `SourceMap::extendWithSourceMap()` now mutates `$this->mappings` incrementally instead of staging all remapped mappings and committing only after a successful full pass.
- Source-backed mappings validate the input source and name indexes before mutating the current mapping, so the rejecting mapping remains unchanged.
- Generated-only or missing closest input mappings are cleared immediately, matching the upstream in-place mutation behavior.
- Added a WordPress smoke showing a block-editor compiled CSS source map that preserves a previously remapped block source when a later corrupted input-map mapping rejects.

## Red First

Before the source change, the new focused test failed:

```text
php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php
Expected 'ACUEG,oBDTFF;IACAC'
Actual   'AAAAA,oBACAC;IACAC'
1 test files, 860 assertions, 1 failures
```

That proved the previous PHP implementation rolled back earlier input-map remaps when a later mapping rejected.

## Verification

```text
php -l lanes/lightningcss/src/SourceMap.php
No syntax errors detected in lanes/lightningcss/src/SourceMap.php

php -l lanes/lightningcss/tests/SourceMapTest.php
No syntax errors detected in lanes/lightningcss/tests/SourceMapTest.php

php -l lanes/lightningcss/examples/wordpress-source-map-extend-rejected-input.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-source-map-extend-rejected-input.php

php lanes/lightningcss/examples/wordpress-source-map-extend-rejected-input.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php
1 test files, 882 assertions, 0 failures

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7691 assertions, 0 failures

php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "JSON OK\n";'
JSON OK

git diff --check -- lanes/lightningcss
passed
```

Root harness status: not run - isolated micro-slice.

## Coverage And Non-Overlap

- Focused source-map coverage moved from 858 to 882 assertions.
- Full LightningCSS lane evidence is 13 files / 7691 assertions / 0 failures.
- `lane-status.json` `phpPass` moved from 7667 to 7691.
- Conservative mapped coverage remains 2374 / 3532; this deepens the already represented parcel_sourcemap SourceMap::extends/source-map VLQ cluster and does not claim a new mapped row.
- This does not repeat accepted addSourceMap rejected child merge, raw VLQ import, data URL parser, duplicate/unsorted generated-column, or first/closest lookup coverage. The bounded behavior is input-map extension partial mutation when a later source/name remap rejects.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `SourceMap` VLQ/mapping table implementation and existing TestRunner/example harnesses.
