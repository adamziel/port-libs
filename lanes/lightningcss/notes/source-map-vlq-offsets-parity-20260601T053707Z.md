# Source Map VLQ Offset Parity

Slice: `lightningcss-source-map-vlq-offsets-parity-20260601T053707Z`

Base accepted HEAD: `663e16b4022673e2529b925ce20b45f0a578189e`

## Source Truth

- Upstream LightningCSS pin: `parcel-bundler/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source-map implementation dependency: `parcel_sourcemap 2.1.1`.
- Inspected upstream files: `src/lib.rs::add_sourcemap`, `src/mapping_line.rs`, and `src/vlq_utils.rs`.
- Local Rust probe against `parcel_sourcemap 2.1.1` confirmed that `add_sourcemap()` imports child sources, source contents, and names before line replacement, skips child lines whose generated line becomes negative, preserves surviving generated-only child mappings, replaces existing parent target lines, and drains the child map.

Probe result for the bounded case:

```json
{"version":3,"sourceRoot":null,"mappings":"MCGEI;Q;ADDFF;AACAC","sources":["entry.css","child-generated.css"],"sourcesContent":["",".child{}\n"],"names":["parent0","parent1","parent2","parent3","childRule"]}
```

The consumed child map serialized as:

```json
{"version":3,"sourceRoot":null,"mappings":"","sources":[],"sourcesContent":[],"names":[]}
```

## Native Delta

- Added a focused `SourceMapTest.php` case for a parent map with four source-backed lines and a child map whose generated-only line 0 is skipped by offset `-1`, source-backed line 1 replaces parent line 0, generated-only line 2 replaces parent line 1, and later parent lines remain.
- Extended `wordpress-source-map-vlq-offsets.php --self-test` with the same generated-only child offset behavior and closest-mapping check.

## Evidence

- Baseline before slice: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` passed `1 test files, 586 assertions, 0 failures`.
- Baseline before slice: `php tools/run-tests.php lanes/lightningcss/tests` passed `13 test files, 6237 assertions, 0 failures`.
- Focused after slice: `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` passed `1 test files, 599 assertions, 0 failures`.
- Full lane after slice: `php tools/run-tests.php lanes/lightningcss/tests` passed `13 test files, 6250 assertions, 0 failures`.
- Example after slice: `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test` returned `OK`.

Focused assertion delta: `+13` in `SourceMapTest.php`.

Status delta: `phpPass` moves `6237 -> 6250`; conservative mapped coverage remains `2359 / 3532` because this deepens an already represented source-map offset/VLQ cluster.

## Non-Overlap

This does not repeat the accepted raw VLQ generated-only import, separator-only raw VLQ table import, partial skipped source table, leading empty offset spans, nested unsorted offsets, duplicate generated-column offsets, direct offset-lines unsorted movement, missing/null VLQ strictness, or empty child-line merge slices. The current slice specifically covers upstream `add_sourcemap()` replacement semantics for generated-only child mapping lines that survive a negative line offset.

## Dependency Closure

No new support component is needed. The existing native PHP `SourceMap` implementation already had the merge behavior; this slice pins upstream parity with focused PHP tests and a WordPress-relevant source-map example.
