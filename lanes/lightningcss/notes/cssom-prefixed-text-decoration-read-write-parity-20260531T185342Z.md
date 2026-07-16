# CSSOM Prefixed Text Decoration Read/Write Parity

Status: ready for supervisor handoff on accepted HEAD `0c0eec061390da3a2185ec8623476b5865dd4a49`.

## Source Truth

Upstream `parcel-bundler/lightningcss` at pinned commit `22bdda3d190f1cd321d98026225cfc964af64ad9` defines `-webkit-` and `-moz-` text-decoration aliases in `src/properties/mod.rs`, and `src/properties/text.rs` models `TextDecoration(VendorPrefix)` as prefixed `line`, unprefixed `thickness`, prefixed `style`, and prefixed `color`. `src/declaration.rs::DeclarationBlock::{get,set,remove}` is the CSSOM source truth for composing shorthands, updating compatible shorthands, and splitting compatible shorthands during removal.

Important parity detail: prefixed `text-decoration` shorthands compose with unprefixed `text-decoration-thickness`, but splitting a prefixed shorthand into longhands does not emit a prefixed thickness longhand because upstream has no `-webkit-text-decoration-thickness` or `-moz-text-decoration-thickness` declaration id.

## Implementation

- `DeclarationBlock` now recognizes `-webkit-text-decoration`, `-moz-text-decoration`, and their prefixed `line`, `style`, and `color` longhands.
- CSSOM reads compose prefixed shorthands from matching prefixed line/style/color declarations plus unprefixed `text-decoration-thickness`.
- CSSOM writes update matching prefixed shorthands in place, while unprefixed `text-decoration-thickness` only updates unprefixed `text-decoration`.
- CSSOM removal splits prefixed shorthands with upstream-compatible longhand output and shorthand removal drops matching prefixed longhands plus unprefixed thickness.
- The WordPress text-decoration CSSOM example now includes legacy `-webkit-text-decoration` color update/removal coverage.

## Red Probe

Before the patch, the focused probe returned `NULL` for a prefixed color read, appended a separate prefixed color longhand instead of updating the shorthand, and did not split the prefixed shorthand on removal:

```sh
php -r 'require "tools/bootstrap.php"; $b = new PortLibs\LightningCSS\DeclarationBlock(); var_export([$b->getProperty("-webkit-text-decoration: underline wavy red 2px", "-webkit-text-decoration-color"), $b->setProperty("-webkit-text-decoration: underline wavy red", "-webkit-text-decoration-color", "blue"), $b->removeProperty("-webkit-text-decoration: underline wavy red", "-webkit-text-decoration-color")]);'
```

Observed before patch:

```php
array (
  0 => NULL,
  1 => '-webkit-text-decoration: underline wavy red; -webkit-text-decoration-color: blue',
  2 => '-webkit-text-decoration: underline wavy red',
)
```

## Verification

- `php -l lanes/lightningcss/src/DeclarationBlock.php` - pass
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` - pass
- `php -l lanes/lightningcss/examples/wordpress-text-decoration-cssom.php` - pass
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` - `1 test files, 579 assertions, 0 failures`
- `php tools/run-tests.php lanes/lightningcss/tests` - `13 test files, 3161 assertions, 0 failures`
- `php lanes/lightningcss/examples/wordpress-text-decoration-cssom.php --self-test` - `OK`

## Status Delta

Focused assertion delta: `+20` in `DeclarationBlockTest.php`.

Mapped coverage remains `1696 / 3532` because this deepens the already represented DeclarationBlock CSSOM helper cluster rather than claiming a new upstream denominator row.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP `DeclarationBlock` parser/serializer and focused PHP test harness.

## Non-Overlap

This does not repeat the earlier unprefixed text-decoration CSSOM slice or the accepted target-prefixing `text-decoration-thickness` browser-boundary work. It is limited to CSSOM read/write/remove parity for prefixed `text-decoration` declaration-block behavior.

## Next

Continue with other unmapped or weakly mapped CSSOM shorthand families, source-map edge cases, CSS Modules graph behavior, or visitor/custom at-rule parity without expanding this prefixed text-decoration cluster.
