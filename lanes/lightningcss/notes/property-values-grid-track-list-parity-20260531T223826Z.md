# Property Values Grid Track List Parity

Slice: `lightningcss-property-values-color-font-grid-parity-20260531T223826Z`

Base accepted HEAD: `33a65237308053a0654b3629f3bffe8d77c73515`

Upstream source truth:

- `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `src/lib.rs::test_grid`, explicit `grid-template-columns` / `grid-template-rows` track-list minifier cases near the start of the function

Implementation/evidence:

- Added 19 focused PHP assertions for the upstream explicit grid track-list cluster:
  `repeat(4, 1fr)`, named repeat tracks, percent/fr/min-content/max-content/auto tracks,
  `minmax()`, `fit-content()`, `auto-fill`, `auto-fit`, mixed fixed/repeat tracks,
  and line-name lists for both columns and rows.
- Updated `wordpress-grid-value-minifier.php` with a block query style that compacts
  `repeat(2, [post-start] fit-content(...))`, `minmax(min-content, 1fr)`, and
  row line-name repeats without Node/WASM.
- No native support component was needed; the existing PHP grid value minifier already
  carried the behavior, and this slice makes the pinned upstream coverage explicit.

Verification:

- `php -l lanes/lightningcss/tests/CssMinifierTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-grid-value-minifier.php` - pass
- `php lanes/lightningcss/examples/wordpress-grid-value-minifier.php --self-test` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/CssMinifierTest.php` - 1 file / 1598 assertions / 0 failures
- `php tools/run-tests.php lanes/lightningcss/tests` - 13 files / 4699 assertions / 0 failures
- `php -r 'foreach (["lanes/lightningcss/lane-status.json", "lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . " OK" . PHP_EOL; }'` - pass
- `git diff --check -- lanes/lightningcss` - pass

Coverage/counting note:

- `phpPass` moves from 4680 to 4699 from the focused PHP assertions.
- Conservative mapped coverage remains `2173 / 3532` because the accepted
  `src/lib.rs::test_grid` property-value cluster already represents this area;
  this handoff deepens parity and prevents regressions rather than claiming
  additional denominator rows.

Non-overlap/dependency note:

- This does not overlap the stale custom-media rework note in the handoff directory.
- This avoids earlier accepted grid shorthand, grid longhand composition,
  grid-auto-flow placement, dynamic `var()` auto-flow, grid-template-areas override,
  aspect-ratio, vertical-align, color, and font target-prefix property-value slices.
- Dependency closure: no new support library or external runtime is required.
