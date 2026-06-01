# CSS Modules Pseudo Canonical Compose Parity

Slice: `lightningcss-css-modules-local-global-compose-parity-20260601T074254Z`

Accepted base: `0e6b89c861545d2e8159ac2fd07a33034e44e234`

## Source Truth

Pinned upstream LightningCSS commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`.

Targeted upstream evidence used pristine `git show` reads for selector pseudo-class behavior and NAPI probes against the pinned native artifact. Upstream CSS Modules serialization canonicalizes recognized no-argument pseudo-class names after local/global rewriting, including escaped or uppercase names such as `:LOCAL-LINK`, `:READ-ONLY`, `:l\6f cal-link`, and `:-WEBKIT-any-link`. It also maps legacy aliases such as `:-moz-placeholder` to `:-moz-placeholder-shown`. Unknown custom pseudo classes remain authored. Escaped pseudo names still participate in `pseudoClasses` replacement, for example `.card:h\6f ver` with `hover => is-hovered`.

## Implementation

- `CssModulesTransformer` now decodes selector pseudo-class idents before pseudo-class replacement so escaped `:hover` can map to configured replacement classes.
- Recognized standard no-argument pseudo classes are serialized in canonical lower-case form after CSS Modules local/global rewriting.
- Legacy placeholder aliases canonicalize to the upstream placeholder-shown forms.
- Unknown custom pseudo classes and functional pseudo classes are left on their existing paths.
- Added a WordPress block CSS Modules smoke for global pseudo canonicalization, local composes exports, and escaped hover replacement.

## Verification

- `php -l lanes/lightningcss/src/CssModulesTransformer.php`
- `php -l lanes/lightningcss/tests/CssModulesTransformerTest.php`
- `php -l lanes/lightningcss/examples/wordpress-css-modules-pseudo-class-canonical.php`
- `php tools/run-tests.php lanes/lightningcss/tests/CssModulesTransformerTest.php`
  - `1 test files, 470 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-css-modules-pseudo-class-canonical.php --self-test`
  - `OK`
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 6774 assertions, 0 failures`
- `git diff --check -- lanes/lightningcss`
  - clean

Assertion/status delta: `phpPass` moves from `6767` to `6774` for this isolated lane slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP CSS Modules selector parser/serializer and the existing focused lane test harness.

## Non-Overlap

This does not repeat the accepted host/slotted, language/direction pseudo, view-transition, escaped local/global pseudo syntax, double-colon local/global, or target-prefix placeholder pseudo clusters. It is specifically the CSS Modules serializer path for recognized no-argument pseudo-class canonical names and escaped replacement pseudos while preserving local/global compose exports.

Root harness: not run - isolated micro-slice.
