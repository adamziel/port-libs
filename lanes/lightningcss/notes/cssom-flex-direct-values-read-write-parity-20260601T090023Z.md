# CSSOM Direct Flex Declaration Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T090023Z`

## Upstream Source Truth

- `parcel-bundler/lightningcss` pinned manifest commit: `22bdda3d190f1cd321d98026225cfc964af64ad9`
- `tests/test_cssom.rs` defines `DeclarationBlock::get_property_value`, `set_property`, and `remove_property` parity as read/write operations over parsed declaration values.
- `src/properties/mod.rs` routes known declaration names through typed property parsing before CSSOM serialization.
- `src/properties/flex.rs` defines modern flex longhands/shorthands and legacy 2009/2012 prefixed flex properties.
- `src/values/number.rs` serializes numeric CSS values canonically, including `.5` rather than `0.5`.

## Implemented Behavior

- Direct modern flex declarations now canonicalize on CSSOM parse/get/set/remove:
  `flex`, `flex-flow`, `flex-direction`, `flex-wrap`, `flex-grow`, `flex-shrink`, `flex-basis`, `order`, and `-webkit-*` flex aliases.
- Legacy flex declarations now canonicalize keyword case, integer values, numeric values, and basis lengths:
  `-webkit/-moz-box-*` and `-ms-flex-*` direct properties.
- Custom properties with flex-looking names remain opaque and preserve authored values.
- The WordPress flex CSSOM example now exercises direct flex parse/write behavior for block layout declarations without Node/WASM.

## Evidence

Before implementation, an ad hoc probe showed direct flex declaration values were preserved raw, for example `flex-grow:+1.00`, `flex-shrink:.500`, `flex-basis:0PX`, and `order:+001`.

Focused before count:

```text
php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 1113 assertions, 0 failures
```

Focused after count:

```text
php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php
1 test files, 1139 assertions, 0 failures
```

Full lane focused evidence:

```text
php tools/run-tests.php lanes/lightningcss/tests
13 test files, 7058 assertions, 0 failures
```

Example smoke:

```text
php lanes/lightningcss/examples/wordpress-flex-cssom.php --self-test
OK
```

PHP lint and diff hygiene were run for the changed PHP files and `lanes/lightningcss`.

## Non-Overlap

This slice does not repeat accepted flex shorthand extraction/splitting, target-prefix flex emission, animation-composition CSSOM, SVG/clip-path/mask/grid/font/container CSSOM, or media/custom-at-rule/import-graph batches. It only adds direct flex declaration value canonicalization for CSSOM read/write parity.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP `DeclarationBlock` parser and value normalizers. No Node, Rust, WASM, network, provider, or live-service runner is required for this slice.
