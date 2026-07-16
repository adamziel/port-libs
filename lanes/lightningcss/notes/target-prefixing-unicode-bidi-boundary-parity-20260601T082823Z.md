# Unicode-Bidi Target Prefixing Boundary Parity

Source truth: upstream `parcel-bundler/lightningcss` pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9`, `src/prefixes.rs` `Feature::Isolate`, `Feature::Plaintext`, and `Feature::IsolateOverride`, plus `src/properties/text.rs` / `src/properties/mod.rs` `unicode-bidi` property mapping.

Behavior ported:
- `unicode-bidi: isolate` emits `-webkit-isolate` for Chrome 16-47, iOS Safari 6-10.3, Opera 15-34, and Safari 6-10.1, and `-moz-isolate` for Firefox 10-49.
- `unicode-bidi: plaintext` emits `-webkit-plaintext` for iOS Safari 6-10.3 and Safari 6-10.1, and `-moz-plaintext` for Firefox 10-49.
- `unicode-bidi: isolate-override` emits `-webkit-isolate-override` for iOS Safari 7-10.3 and Safari 7-10.1, and `-moz-isolate-override` for Firefox 17-49.
- Stale prefixed `unicode-bidi` values are removed when an unprefixed declaration is present and the current targets no longer need those prefixes.

Focused evidence:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` passed.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-unicode-bidi-prefixer.php` passed.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` passed with 1 test file, 1123 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests` passed with 13 test files, 6956 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-unicode-bidi-prefixer.php --self-test` passed.

WordPress smoke:
- `wordpress-unicode-bidi-prefixer.php` models navigation, post title, and citation CSS where legacy editor targets need unicode-bidi value prefixes and modern frontend targets drop stale prefixed values.

Dependency closure: no new support component is needed; this reuses `TransitionPrefixer` target-version routing, declaration scanning, serializer output, and lane-local WordPress example coverage.

Non-overlap: avoids accepted writing-mode, object-fit, cursor, text-decoration, scroll-snap, logical-size/spacing/inset, selector, supports-declaration, clip-path, CSSOM, source-map, CSS Modules, and bundle/import graph slices. This slice is limited to `unicode-bidi` value prefix browser-boundary parity.
