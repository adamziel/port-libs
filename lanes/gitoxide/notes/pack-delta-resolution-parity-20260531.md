# Gitoxide Pack Delta Resolution Parity - 2026-05-31

## Upstream Source Truth

- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/delta.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/file/decode/entry.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/malformed.rs`.
- Focused upstream behavior: `truncated_delta_header_ignores_zero_filled_remainder`, `complete_delta_with_mismatched_declared_size_is_rejected`, `plain_object_with_mismatched_declared_size_is_rejected`, `in_pack_delta_base_with_mismatched_declared_size_is_rejected`, and `short_delta_application_is_reported_without_panicking`.

## Native PHP Delta

- `PackData::entryAtOffset()` now inflates pack entry payloads with a one-byte overrun guard and suppresses PHP inflater warnings for corrupt attacker-controlled pack bytes.
- Plain objects, REF_DELTA entries, and OFS_DELTA bases now reject streams that inflate to fewer or more bytes than the entry header declared before delta header parsing or object trust.
- `PackDataTest.php` adds focused malformed declared-size checks for external REF_DELTA entries, short REF_DELTA entries, and in-pack OFS_DELTA bases.
- `examples/wordpress-pack-data.php` now reports `strictDeclaredSizeGuard` to show a WordPress pack reader refuses malformed compacted content before trusting it.

## Red-First Evidence

- Before the `PackData` fix, `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` failed the new declared-size test with captured warning `zlib_decode(): insufficient memory`.
- After the fix, the same focused test passes without captured PHP warnings.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` - passed, `1 test files, 74 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PackBuilderTest.php` - passed, `1 test files, 146 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-pack-data.php` - passed.
- `php tools/run-tests.php lanes/gitoxide/tests` - passed, `32 test files, 2970 assertions, 0 failures`.

## Non-Overlap

This slice does not repeat the accepted 2026-05-31 batch for object database commit writes, recursive tree merge multiple-base fixture shape, pack/MIDX validation, packed-reference peeled transactions, smart HTTP chained redirect cookie recomputation, or SSH protocol-v2/auth boundary handling. It is bounded to malformed pack entry inflation and delta-resolution declared-size parity.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib, existing `PackData`, `PackIndex`, `GitObject`, and WordPress pack fixtures. Full cargo workspace parity remains excluded for this isolated worker because it would hydrate/build the large feature-heavy upstream workspace; the upstream source files and focused PHP lane tests provide the evidence for this bounded behavior.
