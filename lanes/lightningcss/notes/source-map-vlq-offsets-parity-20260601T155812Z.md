# Source Map VLQ Offsets Parity - Generated-Only Input Rows

## Source Truth

- Upstream LightningCSS source truth is `parcel-bundler/lightningcss` at manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/printer.rs::Printer::add_mapping` remaps through an input source map with `find_closest_mapping(loc.line, loc.column - 1)`.
- The printer returns before adding an output mapping when the closest input-map row is generated-only (`mapping.original` is absent). That means generated-only input-map rows do not create output source-map rows or trailing generated-line spans during LightningCSS input-map pruning.
- `parcel_sourcemap 2.1.1` still preserves generated-only and empty spans for direct map composition/offset APIs; the pruning rule only applies to LightningCSS input-map remaps that skip rows without original source locations.

## Native Delta

- `SourceMap::appendSourceMapWithGeneratedOffset(..., false)` now extends `generatedLineCount` only for pruned child lines that produce at least one source-backed mapping.
- Preserve mode remains unchanged: `appendSourceMapWithGeneratedOffset(..., true)` and raw add_sourcemap-style merges still keep generated-only child segments, sparse generated-line spans, unused tables, and empty-line replacement behavior.
- `CssBundler` inherits the parity fix for inline input maps: an imported CSS file whose inline map contains only generated-only rows no longer leaves a bare semicolon source-map span in the bundle output.

## Red-First Evidence

- Before the fix, a pruned child map with one source-backed row plus generated-only/trailing spans serialized as `AAAAA;;GCECC;;;`; after the fix it serializes as `AAAAA;;GCECC`.
- Before the fix, an inline source map with raw mappings `;K` produced bundled source-map mappings `;`; after the fix it produces an empty mapping string and does not import the generated-only source/name tables.

## Verification

- `php -l lanes/lightningcss/src/SourceMap.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/SourceMapTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` -> no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-source-map-input-remap-source-backed.php` -> no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` -> `1 test files, 1018 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` -> `1 test files, 826 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-source-map-input-remap-source-backed.php --self-test` -> `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` -> `13 test files, 8647 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/lightningcss/lane-status.json")); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg() . PHP_EOL); exit(1); } echo "lane-status.json OK\n";'` -> `lane-status.json OK`.
- `git diff --check -- lanes/lightningcss` -> passed.

## Delta And Non-Overlap

- Full lane focused PHP assertions move `8550 -> 8647`.
- This slice is bounded to LightningCSS input-map pruning of generated-only rows and trailing generated-line spans.
- It does not repeat direct add_sourcemap generated-offset span preservation, raw VLQ import, duplicate-column offset boundaries, data URL parser trivia, empty-line add_sourcemap replacement, CSS Modules, CSSOM, custom-at-rule, or target-prefixing clusters.

## Dependency Closure

- No new support component is needed.
- Existing native PHP `SourceMap`, `CssBundler`, and inline data URL source-map import support are reused.
- Full upstream Rust, Node, and WASM LightningCSS runners remain out of scope for this isolated micro-slice.
