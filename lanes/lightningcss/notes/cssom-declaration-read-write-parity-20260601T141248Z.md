# CSSOM declaration read/write parity - escaped container names

Source truth: pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.

- `src/properties/contain.rs` parses `container-name` as a `ContainerNameList` and serializes the `container` shorthand from the normalized name plus `container-type`.
- `src/rules/container.rs` defines each container name as `ContainerName(CustomIdent)`, so CSS identifier escapes are decoded by the parser and emitted with normal identifier escaping.
- `src/values/ident.rs` shows `CustomIdent::to_css()` delegates to the printer's identifier serializer.

Behavior added:

- `DeclarationBlock` now canonicalizes valid escaped `container-name` identifiers for direct longhands and `container` shorthands.
- CSSOM read/write/remove paths now return `wp-query-card is-wide` for `wp-\71 uery-card is-\77 ide`, and preserve necessary escaping for numeric-leading identifiers such as `\31 wp-card`.
- The existing `wordpress-container-cssom.php` smoke now covers escaped block query container names.

Verification:

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` passed: `1 test files, 1261 assertions, 0 failures`.
- `php -l lanes/lightningcss/src/DeclarationBlock.php` passed.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` passed.
- `php -l lanes/lightningcss/examples/wordpress-container-cssom.php` passed.
- `php lanes/lightningcss/examples/wordpress-container-cssom.php --self-test` passed.
- `git diff --check -- lanes/lightningcss` passed.

Status delta:

- Focused DeclarationBlock coverage moved `1254 -> 1261` assertions.
- `lane-status.json` `phpPass` expectation moves `8173 -> 8180` by the focused assertion delta.
- Mapped coverage remains `2393 / 3532`; this deepens the already represented CSSOM declaration block cluster.

Dependency closure:

- No new support component is needed. The slice reuses the native PHP declaration parser, CSS escape decoder, and identifier serializer already present in `DeclarationBlock`.

Non-overlap and follow-up:

- This does not repeat accepted CSSOM font, direct enum, text/writing, background, border, mask, flex, grid, gap, overflow, animation, transition, list-style, text-decoration, text-emphasis, caret, property-location, or original container shorthand/longhand composition slices.
- Follow-up CSSOM work should target a different property/value cluster or stricter invalid-value recovery only if backed by an upstream parser/error behavior case.
