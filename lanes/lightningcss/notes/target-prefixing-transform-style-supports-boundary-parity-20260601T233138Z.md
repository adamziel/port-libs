# Target Prefixing Transform-Style Supports Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T233138Z`

Source truth:
- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/prefix_handler.rs` includes `TransformStyle` in the generated prefix handler.
- `src/prefixes.rs` maps `Feature::Perspective | Feature::PerspectiveOrigin | Feature::TransformStyle` together, with WebKit lower bounds starting at Android 3 and Safari 4, and Mozilla lower bounds starting at Firefox 10.

Before-change focused probes:
- `@supports (transform-style: preserve-3d)` with Android 2.2 emitted `@supports ((-webkit-transform-style:preserve-3d) or (transform-style:preserve-3d))` while the declaration body stayed unprefixed.
- Safari 3.1 had the same unnecessary WebKit supports prelude.
- Firefox 9 emitted an unnecessary `-moz-transform-style` supports prelude while the declaration body stayed unprefixed.

Change:
- `TransitionPrefixer::supportsDeclarationPrefixGroups()` now gates `transform-style` supports-condition prefixes with `perspectiveNeedsWebkit` / `perspectiveNeedsMoz`, matching upstream's shared perspective/3D feature row.
- Added focused lower-boundary and stale-prefix cleanup assertions for Android 2.2/3, Safari 3.1/4, and Firefox 9/10.
- Added `examples/wordpress-transform-style-supports-prefixer.php` for a WordPress flip-card block guarded by `@supports (transform-style: preserve-3d)`.

Verification:
- `php -l lanes/lightningcss/src/TransitionPrefixer.php` => no syntax errors.
- `php -l lanes/lightningcss/tests/TransitionPrefixerTest.php` => no syntax errors.
- `php -l lanes/lightningcss/examples/wordpress-transform-style-supports-prefixer.php` => no syntax errors.
- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php` => `1 test files, 1463 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-transform-style-supports-prefixer.php --self-test` => `OK`.
- `php tools/run-tests.php lanes/lightningcss/tests` => `13 test files, 9115 assertions, 0 failures`.
- `git diff --check -- lanes/lightningcss` => passed.

Status delta:
- Focused TransitionPrefixer assertions moved `1454 -> 1463` (`+9`).
- Full LightningCSS PHP lane moved `9106 -> 9115` (`+9`).
- Mapped coverage remains `2439 / 3532` because this deepens the already represented upstream transform target-prefix cluster.

Dependency closure:
- No new support component is needed. This reuses the existing native PHP target-version table, supports prelude rewriter, and declaration prefix group helper.

Non-overlap:
- This does not repeat accepted declaration-body transform prefixing, transform CSSOM, perspective-origin CSSOM, print/color-adjust, selector, mask, filter/backdrop-filter, media-range, source-map, CSS Modules, bundle/import graph, parser recovery, or custom at-rule work.
- No LightningCSS rework note existed under `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/port-lightningcss-*.needs-lane-rework.md` for this worktree.

Next:
- Continue with production-bearing current-base LightningCSS gaps only; remaining high-value follow-up is bounded Rust/Node/WASM upstream runner evidence or another non-overlapping target-prefix/parser/source-map parity cluster with focused PHP tests.
