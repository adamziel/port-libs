# CSSOM Declaration Set Delimiter Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T022407Z`

Base accepted HEAD: `28ec15ab9aa5188bc23d7c22caf22b5083cf6e4e`

## Upstream source truth

- Pinned LightningCSS upstream: `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/declaration.rs::DeclarationBlock::set` takes a parsed `Property` plus an explicit `important` boolean, so caller priority is separate from the property value text.
- `src/properties/mod.rs::Property::parse_string` parses a property value through the parser entrypoint without requiring the whole raw string to be exhausted afterward.
- `src/properties/custom.rs::CustomProperty::parse` uses `parse_until_before(Delimiter::Bang | Delimiter::Semicolon, ...)`, so top-level `!` and `;` delimit custom property values instead of becoming serialized content.

## Port behavior

- `DeclarationBlock::setProperty()` now trims CSSOM write values at the first top-level `;` or `!`.
- Delimiters inside quoted strings, functions, square brackets, curly blocks, or CSS escapes are preserved.
- The explicit `$important` argument remains the only way for `setProperty()` to write an important declaration, matching upstream's parsed-property plus explicit-priority model.
- Empty values produced by a leading top-level delimiter still throw `InvalidArgumentException`.

## Red-first evidence

Before the change, the native CSSOM writer accepted injected declaration tails:

```text
array (
  0 => 'color: green; background: blue',
  1 => 'color: green !important',
  2 => '--Block-Accent: blue; color: red !important',
)
```

After the change, the focused test asserts the upstream-style boundary for ordinary values, explicit priority, custom properties, nested WordPress custom-property blocks, escaped delimiters, and leading-delimiter rejection.

## Verification

- `php -l lanes/lightningcss/src/DeclarationBlock.php`: no syntax errors
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`: no syntax errors
- `php -l lanes/lightningcss/examples/wordpress-custom-property-block-cssom.php`: no syntax errors
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`: 1 test files / 894 assertions / 0 failures
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 test files / 5459 assertions / 0 failures
- `php lanes/lightningcss/examples/wordpress-custom-property-block-cssom.php --self-test`: OK

## Coverage and non-overlap

- Focused PHP assertion delta: +8 in `DeclarationBlockTest.php`.
- Conservative mapped coverage remains `2297 / 3532`; this deepens the existing DeclarationBlock CSSOM denominator cluster rather than adding a new upstream inventory row.
- Avoided the stale CustomMedia rework note and did not touch source maps, CSS Modules, bundle/import graph, media-query, custom at-rule, target-prefixing, color, or property-value clusters.

## Dependency closure

No new support component is needed. The slice reuses the native PHP DeclarationBlock scanner/parser helpers already present in `lanes/lightningcss/src`.
