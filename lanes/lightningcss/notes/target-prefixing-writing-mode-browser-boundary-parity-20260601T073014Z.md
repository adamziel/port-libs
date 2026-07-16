# Writing-Mode Target Prefixing Boundary Parity

Source truth: upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/prefixes.rs` `Feature::WritingMode`.

Behavior ported:
- `-webkit-writing-mode` for Android 3 through 4.4.3, Chrome 8 through 47, iOS Safari 5 through 10.3, Opera 15 through 34, Safari 5.1 through 10.1, and Samsung Internet through 4.
- `-ms-writing-mode` for IE 5.5 and later.
- Legacy IE value mapping for `horizontal-tb` to `lr-tb`, `vertical-rl` to `tb-rl`, and `vertical-lr` to `tb-lr`.
- Stale prefixed writing-mode cleanup for modern targets when the unprefixed declaration is present.

Focused evidence:
- Red-first: `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` failed with 1 test file, 1065 assertions, 1 failure before implementation.
- After implementation: `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed with 1 test file, 1093 assertions, 0 failures.
- Full lane: `php tools/run-tests.php lanes/lightningcss/tests` passed with 13 test files, 6751 assertions, 0 failures.

WordPress smoke:
- `php lanes/lightningcss/examples/wordpress-writing-mode-prefixer.php --self-test` passes and models vertical navigation/post-title writing-mode CSS for Chrome and IE target boundaries.

Dependency closure: no new support component is needed; this reuses `TransitionPrefixer` target-version routing, declaration scanning, and lane-local WordPress example coverage.

Non-overlap: avoids accepted object-fit, keyframes, selector pseudo, text-emphasis, mask, supports-declaration, font fallback, bundle/import graph, source-map, CSS Modules, CSSOM, and custom at-rule slices. This slice is limited to `writing-mode` generated target-prefix table parity.
