# LightningCSS Target Prefixing Scroll Snap Boundary Parity

Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T052703Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned at `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Source file: `/home/claude/port-libs/.upstream-cache/lightningcss/src/prefixes.rs`.
- Upstream feature rows: `Feature::ScrollSnapType`, `Feature::ScrollSnapCoordinate`, `Feature::ScrollSnapDestination`, `Feature::ScrollSnapPointsX`, and `Feature::ScrollSnapPointsY`.
- Upstream browser ranges:
  - `-ms-`: IE >= 10, Edge 12 through 18.
  - `-webkit-`: Safari 9 through 10.1, iOS Safari 9 through 10.3.

## Implementation

- Added target option flags for the upstream scroll-snap WebKit/MS ranges.
- Added `TransitionPrefixer::rewriteScrollSnapPrefixEntries()` using the existing vendor-prefixed declaration group helper.
- Covered target emission and stale-prefix cleanup for:
  - `scroll-snap-type`
  - `scroll-snap-coordinate`
  - `scroll-snap-destination`
  - `scroll-snap-points-x`
  - `scroll-snap-points-y`
- Added `examples/wordpress-scroll-snap-prefixer.php` to model carousel/card strip scroll snap CSS for Safari/IE/Edge boundary targets.

## Verification

- `php -l lanes/lightningcss/src/TransitionPrefixer.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `No syntax errors detected`
- `php -l lanes/lightningcss/examples/wordpress-scroll-snap-prefixer.php`
  - `No syntax errors detected`
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1011 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-scroll-snap-prefixer.php`
  - exited `0`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6209 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - exited `0`
- JSON validity check for `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json`
  - both decoded successfully

## Non-Overlap

This does not repeat accepted CSSOM scroll-snap read/write/remove behavior, mask browser-boundary prefixing, supports declaration target-prefixing, overflow shorthand fallback lowering, background-clip text prefixing, legacy text/sticky prefixing, display flex/flex longhand prefixing, animation timeline target boundaries, or media-query range/comment layers. The new behavior is limited to scroll-snap target-prefix declaration insertion/removal from the upstream prefix table.

The existing main-repo rework note for `CustomMediaTransformer.php` is stale to this micro-slice and does not name the current target-prefix scroll-snap behavior.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP target parsing, CSS minifier declaration preservation, and `TransitionPrefixer` vendor declaration group machinery.
