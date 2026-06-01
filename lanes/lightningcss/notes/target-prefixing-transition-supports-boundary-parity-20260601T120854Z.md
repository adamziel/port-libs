# Transition Supports Target Prefix Boundary Parity

Micro-slice: `lightningcss-target-prefixing-browser-boundary-parity-20260601T120854Z`

## Source Truth

- Pinned upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Pristine upstream reads:
  - `src/rules/supports.rs`: `SupportsCondition::set_prefixes_for_targets()` calls `PropertyId::set_prefixes_for_targets()` for unprefixed declaration conditions.
  - `src/properties/mod.rs`: `transition`, `transition-property`, `transition-duration`, `transition-delay`, and `transition-timing-function` are prefixed property ids.
  - `src/prefixes.rs`: `Feature::Transition*` maps WebKit for Chrome 4-25, Android 2.1-4.2, iOS Safari 3.2-6, and Safari 3.1-6; Mozilla for Firefox 4-15; Opera for Opera 10-12.

## Red-First Gap

Before the change, the PHP prefixer prefixed the transition declarations inside `@supports`, but left the support condition unprefixed:

```text
@supports (transition:opacity 200ms){.foo{-webkit-transition:opacity .2s;transition:opacity .2s}}
```

The upstream behavior expands the guard as well, for example Chrome 25 needs:

```text
@supports ((-webkit-transition:opacity 200ms) or (transition:opacity 200ms)){...}
```

## Implementation

- Added the transition family to `TransitionPrefixer::supportsDeclarationPrefixGroups()`.
- Covered `transition`, `transition-property`, `transition-duration`, `transition-delay`, and `transition-timing-function`.
- Existing direct declaration prefixing remains unchanged; this only aligns `@supports` declaration guards with the same target-prefix browser boundaries.
- Added `wordpress-transition-supports-prefixer.php` for block cover and navigation transition guards without Node/WASM.

## Verification

```text
php -l lanes/lightningcss/src/TransitionPrefixer.php
No syntax errors detected in lanes/lightningcss/src/TransitionPrefixer.php

php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
No syntax errors detected in lanes/lightningcss/tests/TransitionPrefixerTest.php

php -l lanes/lightningcss/examples/wordpress-transition-supports-prefixer.php
No syntax errors detected in lanes/lightningcss/examples/wordpress-transition-supports-prefixer.php

php tools/run-tests.php lanes/lightningcss/tests/TransitionPrefixerTest.php
1 test files, 1274 assertions, 0 failures

php lanes/lightningcss/examples/wordpress-transition-supports-prefixer.php --self-test
OK

php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7703 assertions, 0 failures
```

## Status Delta

- `lane-status.json` `phpPass` moves from `7694` to `7703`.
- Conservative mapped coverage remains `2374 / 3532`; this deepens the already represented target-prefix/supports declaration behavior rather than claiming a new denominator row.

## Dependency Closure

No new support component is needed. This reuses the native PHP supports-condition scanner, existing declaration-prefix group rewriter, transition target-version flags, and lane-local example harness.

## Non-Overlap

This slice does not repeat accepted direct transition declaration prefixing, backdrop/filter supports conditions, generic supports declaration target-prefix boundaries, animation target prefixing, background-clip target prefixing, import media range tails, CSSOM, CSS Modules, source-map, bundle/import graph, media-query, property-value, or custom-at-rule work. It is limited to transition-family `@supports` declaration guards.

## Next Task

Continue non-overlapping target-prefix support-condition parity, especially animation or background-clip guards, or pivot to the current higher-priority LightningCSS surfaces if another worker has already covered those.
