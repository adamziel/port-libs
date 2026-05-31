# CSSOM Logical Border Declaration Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T180850Z`

## Source Truth

- Upstream: `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Files checked:
  - `src/declaration.rs`
  - `src/properties/border.rs`
  - `src/properties/mod.rs`
  - `tests/test_cssom.rs`

Upstream behavior used for this slice:

- `DeclarationBlock::get` resolves logical border longhands from compatible shorthands.
- `DeclarationBlock::set` updates compatible same-priority logical border shorthands in place and appends when a full logical axis shorthand cannot represent an asymmetric longhand update.
- `DeclarationBlock::remove` splits containing shorthands into surviving longhands when removing one logical border longhand, and removes direct included longhands when removing a logical border shorthand.

## Implementation

- Added native PHP `DeclarationBlock` support for logical border CSSOM declarations:
  - `border-block` and `border-inline`
  - `border-block-width`, `border-block-style`, `border-block-color`
  - `border-inline-width`, `border-inline-style`, `border-inline-color`
  - `border-block-start`, `border-block-end`, `border-inline-start`, `border-inline-end`
  - logical side width/style/color longhands
- Added read composition, in-place same-priority longhand updates, incompatible full-axis append behavior, and shorthand splitting on remove.
- Added a WordPress-relevant smoke example for block/theme logical border editing with CSS custom properties.

## Red Probe

Before implementation, this probe showed missing logical border parity:

```bash
php -r 'require "tools/bootstrap.php"; $b=new \PortLibs\LightningCSS\DeclarationBlock(); var_export(["getStartColor"=>$b->getProperty("border-block: 2px solid red", "border-block-start-color"), "getWidth"=>$b->getProperty("border-block-start-width: 2px; border-block-end-width: 4px", "border-block-width"), "setAxis"=>$b->setProperty("border-block-color: red green", "border-block-start-color", "blue"), "removeAxis"=>$b->removeProperty("border-inline: 1px solid red", "border-inline-start-color")]); echo "\n";'
```

Observed before edit:

```php
array (
  'getStartColor' => NULL,
  'getWidth' => NULL,
  'setAxis' => 'border-block-color: red green; border-block-start-color: blue',
  'removeAxis' => 'border-inline: 1px solid red',
)
```

Observed after edit:

```php
array (
  'getStartColor' =>
  array (
    'value' => 'red',
    'important' => false,
  ),
  'getWidth' =>
  array (
    'value' => '2px 4px',
    'important' => false,
  ),
  'setAxis' => 'border-block-color: blue green',
  'removeAxis' => 'border-inline-start-width: 1px; border-inline-end-width: 1px; border-inline-start-style: solid; border-inline-end-style: solid; border-inline-end-color: red',
)
```

## Verification

Focused baseline before this slice:

```bash
php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
```

Result before edit: `1 test files, 520 assertions, 0 failures`.

Focused gate after this slice:

```bash
php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
```

Result after edit: `1 test files, 541 assertions, 0 failures`.

Full focused LightningCSS lane:

```bash
php tools/run-tests.php lanes/lightningcss/tests
```

Result: `13 test files, 2902 assertions, 0 failures`.

Syntax checks:

```bash
php -l lanes/lightningcss/src/DeclarationBlock.php
php -l lanes/lightningcss/tests/DeclarationBlockTest.php
php -l lanes/lightningcss/examples/wordpress-logical-border-cssom.php
```

Result: no syntax errors detected.

Example smoke:

```bash
php lanes/lightningcss/examples/wordpress-logical-border-cssom.php --self-test
```

Result: `OK`.

Whitespace check:

```bash
git diff --check -- lanes/lightningcss
```

Result: passed with no output.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused `DeclarationBlockTest.php`: `520 -> 541` assertions (`+21`).
- Full LightningCSS PHP lane evidence: `2881 -> 2902` assertions (`+21`).
- `lane-status.json` `phpPass`: `2881 -> 2902`.
- Conservative upstream manifest mapped coverage remains `1637 / 3532`; this slice deepens the existing DeclarationBlock CSSOM cluster instead of adding a new inventory row.

## Non-Overlap

This slice does not repeat accepted CSSOM background, physical border, border-image, border-radius, mask, mask-border, outline, flex, grid, gap, overflow, scroll-snap, animation, transition, list-style, text-decoration, text-emphasis, caret, font, container, logical box axis, or property-location coverage. The old `CustomMediaTransformer.php` rework note in the main handoff directory is unrelated to this DeclarationBlock logical-border CSSOM cluster.

## Dependency Closure

No new support component is needed. The implementation reuses the native PHP DeclarationBlock parser, priority buckets, border value parser, and shorthand splitting helpers.

## Next

Continue CSSOM parity with a non-overlapping shorthand family, or broaden DeclarationBlock parser coverage around another upstream property group not already accepted.
