# Target Prefixing Browser Boundary Parity

Date: 2026-06-01 16:53 UTC
Base accepted HEAD: `a9137cb7b139366b80725c530ab651ef1f979b87`
Slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T164657Z`

## Scope

This slice ports one bounded upstream target-prefix behavior: `border-image`
declaration conditions inside `@supports` now receive the same WebKit,
Mozilla, and Opera prefixed alternatives as direct `border-image`
declarations, and stale prefixed alternatives are pruned for modern targets.

Before the patch, a legacy Chrome/Firefox/Opera target prefixed the
declarations inside the block but left the prelude as:

```css
@supports (border-image:url(border.png) 30 fill/10px/4px round)
```

After the patch, the same target emits:

```css
@supports ((-webkit-border-image:url(border.png) 30 fill/10px/4px round) or (-moz-border-image:url(border.png) 30 fill/10px/4px round) or (-o-border-image:url(border.png) 30 fill/10px/4px round) or (border-image:url(border.png) 30 fill/10px/4px round))
```

## Source Truth

Pinned upstream: `parcel-bundler/lightningcss`
`22bdda3d190f1cd321d98026225cfc964af64ad9`.

- `src/rules/supports.rs` lines 153-165 call
  `property_id.set_prefixes_for_targets(*targets)` for unprefixed supports
  declarations.
- `src/properties/mod.rs` line 1299 defines `border-image` with
  `WebKit`, `Moz`, and `O` vendor prefixes.
- `src/prefixes.rs` lines 878-909 define the `Feature::BorderImage`
  browser ranges: Android 2.1-4.2, Chrome 4-14, Firefox 3.5-14,
  iOS Safari 3.2-5, Opera 11-12.1, and Safari 3.1-5.1.

## Verification

- `php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php`
  - `1 test files, 1400 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-border-image-prefixer.php`
  - passed, printing legacy and modern border-image outputs
- `php tools/run-tests.php lanes/lightningcss/tests`
  - `13 test files, 8802 assertions, 0 failures`

Full upstream Rust/Node/WASM runners were not executed for this isolated
micro-slice.

## Dependency Closure

No new support component is needed. The patch reuses the existing PHP
`TransitionPrefixer` target-option matrix, declaration-prefix group rewriter,
and `@supports` declaration-prefix prelude logic.

## Non-Overlap

This does not duplicate the already accepted direct `border-image` declaration
boundary coverage or CSSOM border-image behavior. The new behavior is limited
to the `@supports` prelude boundary that LightningCSS applies from the same
upstream property-prefix metadata.

## Next

Continue auditing prefixed shorthand properties whose declarations already
have PHP target-boundary coverage but whose `@supports` declaration conditions
may not yet share the same prefix matrix.
