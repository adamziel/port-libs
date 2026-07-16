# Source Map VLQ Offset Parity - Empty Line Column Underflow

- Lane: `lightningcss`
- Micro-slice: `lightningcss-source-map-vlq-offsets-parity-20260601T145002Z`
- Base accepted HEAD: `0af7c1558eab56b0c7f231815cf34222c9e56c0d`
- Upstream source truth: pinned `parcel-bundler/lightningcss` manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, via `parcel_sourcemap` 2.1.1 `mapping_line.rs::offset_columns` and `lib.rs::offset_lines` / `offset_columns`.

## Behavior

`parcel_sourcemap::MappingLine::offset_columns` computes `generated_column + generated_column_offset` before checking whether the line has mappings to drain. `SourceMap::offset_lines` inserts real empty `MappingLine` spans, so underflowing negative column offsets on those generated lines are rejected even when the line has no mappings. Positive offsets, valid negative offsets that remain non-negative, and generated lines beyond the current span still no-op when there are no mappings to mutate.

The PHP `SourceMap::offsetColumns()` guard now treats an existing empty generated-line span as present for this negative-offset underflow check.

## Red-First Probe

Before this patch, the current base silently accepted an upstream-invalid offset:

```sh
php -r 'require "tools/bootstrap.php"; $m=new PortLibs\LightningCSS\SourceMap(); $s=$m->addSource("theme.css"); $m->addMapping(0,0,$s,0,0); $m->offsetLines(1,2); try { $m->offsetColumns(1,3,-4); echo "NO_EXCEPTION\n"; } catch (InvalidArgumentException $e) { echo "EXCEPTION\n"; } echo $m->writeVlq(),"\n";'
```

Output before the fix:

```text
NO_EXCEPTION
AAAA;;
```

After the fix, focused tests assert the exception and that the map remains unchanged after the rejected offsets.

## Verification

- `php -l lanes/lightningcss/src/SourceMap.php` - passed
- `php -l lanes/lightningcss/tests/SourceMapTest.php` - passed
- `php -l lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php` - passed
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - `1 test files, 968 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-source-map-vlq-offsets.php --self-test` - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 8308 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss` - passed

## Non-Overlap

This does not repeat the accepted generated-offset trailing span append, raw VLQ import/remap, duplicate generated-column boundaries, unsorted line sorting, line-local offsets, or media-query/property/CSSOM/CSS Modules clusters. It only tightens the upstream `parcel_sourcemap` negative column-offset guard for empty generated-line spans created by line offsets.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local native PHP `SourceMap` VLQ/offset implementation and existing source-map example smoke.

## Follow-Up

Full upstream Rust, Node, and WASM LightningCSS runners were not executed in this isolated micro-slice.
