# Media Query Case-Sensitive Range Layer Parity - 2026-06-01

## Scope

Implemented the `lightningcss-media-query-range-layer-parity-20260601T032316Z` slice for native PHP LightningCSS media query parsing and target fallback lowering.

The bounded behavior cluster is case preservation for custom and unknown media feature names in range syntax, including layered WordPress block CSS. Standard media feature names and standard media types still canonicalize to lowercase.

## Upstream Source Truth

Pinned upstream checkout:

- `/home/claude/port-libs/.upstream-cache/lightningcss`
- commit `22bdda3d190f1cd321d98026225cfc964af64ad9`

Relevant upstream evidence:

- `src/media_query.rs` parses custom `--*` media feature names as `MediaFeatureName::Custom(DashedIdent(...))`.
- `src/media_query.rs` parses otherwise unknown feature idents as `MediaFeatureName::Unknown(Ident(...))`.
- `src/media_query.rs` serializes custom and unknown feature names through `to_css_with_prefix()` with the authored ident preserved while adding lowercase `min-` / `max-` fallback prefixes.
- `src/media_query.rs` maps only `all`, `print`, and `screen` to standard media types; other media type idents remain custom strings.

Before this slice the PHP parser lowercased safe unknown idents, so layered target fallbacks changed authored custom names such as `Theme-Breakpoint` and `--WP-Breakpoint` into `theme-breakpoint` and `--wp-breakpoint`.

## Implementation

Changed `MediaQueryParser` to split identifier canonicalization by role:

- media types: lowercase only known `all`, `print`, and `screen`; preserve safe custom types such as `Speech`;
- media features: lowercase known standard and legacy feature names, including known `min-` / `max-` forms;
- custom and unknown feature names: preserve authored case for modern range syntax and legacy fallback lowering.

The touched WordPress example now covers layered block CSS that lowers:

- `(Theme-Breakpoint >= 2)` to `(min-Theme-Breakpoint:2)`;
- `(--WP-Breakpoint >= 3)` to `(min---WP-Breakpoint:3)`;
- `Speech and (--WP-Breakpoint >= 2)` without range lowering when `MediaRangeSyntax` is excluded.

## Verification

Red-first probe before the implementation:

```bash
php -r 'require "tools/bootstrap.php"; $p=new PortLibs\LightningCSS\TransitionPrefixer(); echo $p->prefixForTargets("@layer blocks { @media (--WP-Breakpoint >= 2) { .wp-block-query { color: yellow; } } @media (Theme-Breakpoint >= 2) { .wp-block-query.is-theme { color: yellow; } } }", ["firefox"=>60]), "\n";'
```

Observed before-fix output lowercased the custom names:

```text
@layer blocks{@media (min---wp-breakpoint:2){.wp-block-query{color:#ff0}}@media (min-theme-breakpoint:2){.wp-block-query.is-theme{color:#ff0}}}
```

Focused tests:

```bash
php tools/run-tests.php lanes/lightningcss/tests/MediaQueryParserTest.php lanes/lightningcss/tests/TransitionPrefixerTest.php
```

Result:

```text
2 test files, 1329 assertions, 0 failures
```

Full LightningCSS lane:

```bash
php tools/run-tests.php lanes/lightningcss/tests
```

Result:

```text
13 test files, 5769 assertions, 0 failures
```

Example smoke:

```bash
php lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php --self-test
```

Result: passed. The smoke includes `caseSensitiveCustomRangeFallback` and `caseSensitiveCustomRangeModern`.

Syntax and patch hygiene:

```bash
php -l lanes/lightningcss/src/MediaQueryParser.php
php -l lanes/lightningcss/tests/MediaQueryParserTest.php
php -l lanes/lightningcss/tests/TransitionPrefixerTest.php
php -l lanes/lightningcss/examples/wordpress-media-range-layer-prefixer.php
git diff --check -- lanes/lightningcss
```

Result: all PHP files reported no syntax errors; `git diff --check` reported no whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap And Dependency Closure

This slice avoids the stale custom-media import-tail rework note and does not touch bundle/import graph, CSS Modules, source-map, CSSOM, or custom at-rule surfaces. It also avoids existing media-query work for resolution fallbacks, env/calc ranges, negated groups, equality validation, and layer ordering. The only behavior changed here is custom/unknown media feature and custom media type case preservation for range parsing and target fallback output.

No new dependency or support component is needed. The implementation reuses the existing native PHP `MediaQueryParser`, `CssMinifier`, and `TransitionPrefixer` path.
