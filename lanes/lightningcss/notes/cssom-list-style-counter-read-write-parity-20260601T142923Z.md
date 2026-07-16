# CSSOM List Style Counter Read/Write Parity

Micro-slice: `lightningcss-cssom-declaration-read-write-parity-20260601T142923Z`.

Source truth:

- Pinned upstream `parcel-bundler/lightningcss` commit `22bdda3d190f1cd321d98026225cfc964af64ad9`.
- Upstream CSSOM entry point: `tests/test_cssom.rs` `DeclarationBlock` parse/get/set/remove behavior.
- Upstream list serialization: `src/properties/list.rs` `ListStyleType`, `CounterStyle`, and `ListStyle::ToCss`.

Native upstream oracle evidence from the cached addon:

- `.a{list-style-type:Upper-Roman}` serializes as `.a{list-style-type:upper-roman}`.
- `.a{list-style:Inside URL("marker.svg") Square}` serializes as `.a{list-style:inside url(marker.svg) square}`.
- `.a{list-style-type:Symbols(Symbolic "A" "B")}` serializes as `.a{list-style-type:symbols("A" "B")}`.
- `.a{list-style-type:wp\2d marker}` serializes as `.a{list-style-type:wp-marker}`.
- `.a{list-style:disc}` serializes as `.a{list-style:outside}`.

Implemented behavior:

- `DeclarationBlock` now canonicalizes upstream predefined counter-style keywords in `list-style-type` and `list-style`.
- `symbols(...)` list-style values now lowercase the function/system, omit the default `symbolic` system, and normalize symbol tokens.
- Escaped custom counter-style identifiers now decode and serialize through the existing CSS identifier helpers.
- The WordPress list marker CSSOM smoke now covers upper-roman marker edits, `symbols()` marker fallbacks, and escaped custom counter identifiers.

Focused evidence:

- `php tools/run-tests.php lanes/lightningcss/tests/DeclarationBlockTest.php`: 1 test file, 1260 assertions, 0 failures.
- `php tools/run-tests.php lanes/lightningcss/tests`: 13 test files, 8237 assertions, 0 failures.
- `php lanes/lightningcss/examples/wordpress-list-style-cssom.php --self-test`: OK.
- `php -l lanes/lightningcss/src/DeclarationBlock.php`: pass.
- `php -l lanes/lightningcss/tests/DeclarationBlockTest.php`: pass.
- `php -l lanes/lightningcss/examples/wordpress-list-style-cssom.php`: pass.
- `git diff --check -- lanes/lightningcss`: pass.

Status delta:

- `phpPass` moves 8231 -> 8237.
- Mapped coverage remains 2393 / 3532 because this deepens the already represented upstream DeclarationBlock CSSOM cluster.

Dependency closure: no new support component is needed. This reuses the existing bounded `DeclarationBlock` parser/serializer, CSS string/url normalizers, CSS identifier escape reader, shorthand component parser, and lane-local WordPress list-style CSSOM smoke.

Non-overlap: this does not repeat the accepted list-style shorthand split/get/set/remove cluster. It only ports counter-style value canonicalization for predefined keywords, `symbols()`, and escaped custom counter identifiers.
