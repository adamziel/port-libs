# CSSOM `all` Keyword Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260531T215647Z`

Source truth:

- Upstream `parcel-bundler/lightningcss` pinned manifest commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- `src/properties/mod.rs` parses `PropertyId::All` as `Property::All(CSSWideKeyword::parse(input)?)`.
- `src/declaration.rs` keeps `all` in the direct declaration read/write/remove path because it is not modeled as a shorthand.

Implemented behavior:

- `DeclarationBlock` now canonicalizes recognized CSS-wide keyword values for the direct `all` property during parse and `setProperty()` writes.
- Normalization applies after escaped property names are decoded, so `a\6c l: InHeRiT` reads as `all: inherit`.
- Unparsed values such as `var(--wp--custom--reset)` remain preserved for CSSOM fallback parity.
- Existing exact-property priority movement and removal behavior is unchanged.

Verification:

- `php -l lanes/lightningcss/src/DeclarationBlock.php` - pass.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php` - pass.
- `php -l lanes/lightningcss/examples/wordpress-all-cssom.php` - pass.
- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php` - pass, `1 test files, 778 assertions, 0 failures`.
- `php tools/run-tests.php lanes/lightningcss/tests` - pass, `13 test files, 4522 assertions, 0 failures`.
- `php lanes/lightningcss/examples/wordpress-all-cssom.php --self-test` - pass.

Dashboard/countability:

- Focused DeclarationBlock coverage increases from 770 to 778 assertions.
- Full LightningCSS lane-focused coverage increases from 4514 to 4522 assertions.
- Mapped denominator remains `2152 / 3532`; this is additional CSSOM behavior inside the already represented declaration read/write surface.

Dependency closure:

- No new support component is needed. The slice reuses the existing native PHP CSS declaration parser and CSSOM mutation path.

Exclusions:

- Full upstream Rust/Node/WASM runners were not executed in this isolated micro-slice.
- The stale May 25 `CustomMediaTransformer.php` rework note is unrelated to this DeclarationBlock CSSOM cluster.
