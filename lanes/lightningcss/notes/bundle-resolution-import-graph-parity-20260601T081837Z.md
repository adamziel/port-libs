# LightningCSS bundle resolution/import graph parity - 2026-06-01T08:18:37Z

## Source truth

- Upstream pinned checkout: `/home/claude/port-libs/.upstream-cache/lightningcss` at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Relevant upstream behavior:
  - `src/bundler.rs` `load_file()` does not add a generated bundle source when a stylesheet has a `data:` input source map; remapping handles original sources instead.
  - `src/bundler.rs` `test_source_map()` has an input map with `sass/_variables.scss` in the source table, but the final bundled source map only includes sources referenced by mappings: `a.css`, `sass/_demo.scss`, and `stdin`.

## Implemented behavior

- `SourceMap::addSourceMap()` now has a default-preserving mode for existing raw source-map imports and an opt-in referenced-table mode.
- `CssBundler` uses referenced-table mode for imported inline input source maps, so unused upstream source and name table entries are not emitted in bundled source maps.
- The WordPress bundle import graph smoke now expects generated block CSS input maps to prune unused Sass partials while retaining the mapped original block source.

## Verification

- `php -l lanes/lightningcss/src/SourceMap.php` - pass
- `php -l lanes/lightningcss/src/CssBundler.php` - pass
- `php -l lanes/lightningcss/tests/CssBundlerTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-bundle-import-graph.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/CssBundlerTest.php` - 1 file, 647 assertions, 0 failures
- `php tools/run-tests.php lanes/lightningcss/tests/SourceMapTest.php` - 1 file, 691 assertions, 0 failures
- `php lanes/lightningcss/examples/wordpress-bundle-import-graph.php --self-test` - pass, including `source-map-input-unused: pruned`
- `php tools/run-tests.php lanes/lightningcss/tests` - 13 files, 6895 assertions, 0 failures
- `php -r '$path="lanes/lightningcss/lane-status.json"; json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` - pass
- `git diff --check -- lanes/lightningcss` - pass

## Status delta

- `lane-status.json` `phpPass`: `6894` -> `6895`
- Expected dashboard movement: +1 focused LightningCSS assertion; no mapped-manifest denominator change.

## Non-overlap

- Avoided the accepted reader source-provider path, import media diagnostic, layer/supports/media composition, CSS Modules dependency graph, raw VLQ offset, and target-prefix slices.
- This patch is scoped to bundle inline input source-map source/name table pruning during import graph remapping.

## Dependency closure

- No new support component is needed. The change reuses the existing native PHP source-map decoder/encoder and bundler import graph.

## Follow-up

- Next high-value source-map parity work: remapping through CSS Modules dependency imports and resolver diagnostic ordering edges.
