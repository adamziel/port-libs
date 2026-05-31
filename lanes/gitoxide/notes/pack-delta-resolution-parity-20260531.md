# Gitoxide Pack Delta Resolution Parity - 2026-05-31

## Upstream Source Truth

- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/delta.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/file/decode/entry.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/malformed.rs`.
- Focused upstream behavior: `truncated_delta_header_ignores_zero_filled_remainder`, `complete_delta_with_mismatched_declared_size_is_rejected`, `plain_object_with_mismatched_declared_size_is_rejected`, `in_pack_delta_base_with_mismatched_declared_size_is_rejected`, `short_delta_application_is_reported_without_panicking`, and `oversized_delta_result_is_rejected_without_panicking`.

## Native PHP Delta

- `PackData::entryAtOffset()` now inflates pack entry payloads with a one-byte overrun guard and suppresses PHP inflater warnings for corrupt attacker-controlled pack bytes.
- Plain objects, REF_DELTA entries, and OFS_DELTA bases now reject streams that inflate to fewer or more bytes than the entry header declared before delta header parsing or object trust.
- Delta base/result size headers now reject values that exceed PHP's integer range before left-shift wraparound can turn an attacker-controlled Git varint into a negative size.
- `PackDataTest.php` adds focused malformed declared-size checks for external REF_DELTA entries, short REF_DELTA entries, and in-pack OFS_DELTA bases.
- `PackDataTest.php` adds focused oversized base/result delta-header checks for REF_DELTA entries with external bases.
- `examples/wordpress-pack-data.php` now reports `strictDeclaredSizeGuard` and `oversizedDeltaHeaderGuard` to show a WordPress pack reader refuses malformed compacted content before trusting it.

## Red-First Evidence

- Before the `PackData` fix, `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` failed the new declared-size test with captured warning `zlib_decode(): insufficient memory`.
- Before the oversized-header fix, `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` failed the new oversized result-header case with `Actual: 'Delta result size mismatch: expected -9223372036854775808, got 0'`.
- After the fix, the same focused test passes without captured PHP warnings.
- After the fix, the oversized base/result header cases report `Delta header size exceeds platform integer range` before delta application.

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` - passed, `1 test files, 77 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PackBuilderTest.php` - passed, `1 test files, 146 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-pack-data.php` - passed.
- `php tools/run-tests.php lanes/gitoxide/tests` - passed, `37 test files, 3327 assertions, 0 failures`.

## Non-Overlap

This slice does not repeat the accepted 2026-05-31 batch for object database commit writes, recursive tree merge multiple-base fixture shape, pack/MIDX validation, packed-reference peeled transactions, smart HTTP chained redirect cookie recomputation, SSH protocol-v2/auth boundary handling, protocol v2 ls-refs advertisements, sparse checkout pathspecs, config include/includeIf, attributes pathspecs, loose object integrity, packed/loose reference peeling, commit gpgsig stripping, reflog parsing, index/cache-tree sparse metadata, or tree pathspec walking. It is bounded to malformed pack entry inflation and delta-resolution declared-size/oversized-header parity.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib, existing `PackData`, `PackIndex`, `GitObject`, and WordPress pack fixtures. Full cargo workspace parity remains excluded for this isolated worker because it would hydrate/build the large feature-heavy upstream workspace; the upstream source files and focused PHP lane tests provide the evidence for this bounded behavior.
