# Gitoxide Pack Delta Resolution Parity - 2026-05-31

## Upstream Source Truth

- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/delta.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/entry/decode.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/src/data/file/decode/entry.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/malformed.rs`.
- Inspected `/home/claude/port-libs/.upstream-cache/gitoxide/gix-pack/tests/pack/data/fuzzed.rs`.
- Focused upstream behavior: `truncated_delta_header_ignores_zero_filled_remainder`, `complete_delta_with_mismatched_declared_size_is_rejected`, `plain_object_with_mismatched_declared_size_is_rejected`, `in_pack_delta_base_with_mismatched_declared_size_is_rejected`, `short_delta_application_is_reported_without_panicking`, and `oversized_delta_result_is_rejected_without_panicking`.
- Current micro-slice focus: `gix-pack/src/data/delta.rs::apply()` writes into a fixed target buffer. Copy/insert commands that would overrun the declared result size return write errors instead of growing an attacker-controlled output buffer, and short delta applications are rejected when the promised result bytes were not produced.
- Additive metadata focus: `gix-pack/src/data/entry/decode.rs::parse_header_info()` rejects non-canonical pack entry size encodings and overflow while decoding, and `parse_leb64()` rejects OFS_DELTA base-distance overflow before delta-base traversal. This maps the upstream fuzz/regression boundaries behind `oversized_pack_entry_header_is_reported_without_panicking`, `invalid_ofs_delta_base_distance_is_reported_without_panicking`, and the entry-header canonical-size invariant.

## Native PHP Delta

- `PackData::entryAtOffset()` now inflates pack entry payloads with a one-byte overrun guard and suppresses PHP inflater warnings for corrupt attacker-controlled pack bytes.
- Plain objects, REF_DELTA entries, and OFS_DELTA bases now reject streams that inflate to fewer or more bytes than the entry header declared before delta header parsing or object trust.
- Delta base/result size headers now reject values that exceed PHP's integer range before left-shift wraparound can turn an attacker-controlled Git varint into a negative size.
- `PackDataTest.php` adds focused malformed declared-size checks for external REF_DELTA entries, short REF_DELTA entries, and in-pack OFS_DELTA bases.
- `PackDataTest.php` adds focused oversized base/result delta-header checks for REF_DELTA entries with external bases.
- `PackData::applyDelta()` now rejects delta copy and insert instructions before appending bytes beyond the declared result size, and it reports short delta applications with the upstream-shaped "fewer bytes than promised" boundary.
- `PackDataTest.php` adds focused result-buffer overrun checks for copy commands, insert commands, and short applications.
- `PackData::entryAtOffset()` now rejects non-canonical pack entry size headers, pack entry size-header integer overflow, and OFS_DELTA base-distance overflow before resolving the malformed entry.
- `PackDataTest.php` adds focused entry-metadata checks for the three malformed pack metadata cases above.
- `examples/wordpress-pack-data.php` now reports `strictDeclaredSizeGuard`, `oversizedDeltaHeaderGuard`, `deltaResultBufferGuard`, and `packEntryMetadataGuard` to show a WordPress pack reader refuses malformed compacted content before trusting it.

## Red-First Evidence

- Before the `PackData` fix, `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` failed the new declared-size test with captured warning `zlib_decode(): insufficient memory`.
- Before the oversized-header fix, `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` failed the new oversized result-header case with `Actual: 'Delta result size mismatch: expected -9223372036854775808, got 0'`.
- Before the current result-buffer fix, `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` failed the new overrun test with `Actual: 'Delta result size mismatch: expected 1, got 2'`.
- Before the current metadata fix, `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` failed `rejects malformed delta entry metadata before size wraparound` with `Expected InvalidArgumentException was not thrown`, and the WordPress fixture summary failed because `packEntryMetadataGuard` was not present.
- After the fix, the same focused test passes without captured PHP warnings.
- After the fix, the oversized base/result header cases report `Delta header size exceeds platform integer range` before delta application.
- After the current fix, copy and insert overrun cases report `Delta copy exceeds declared result size` / `Delta insert exceeds declared result size`, and the short-output case reports `Delta instructions produced fewer bytes than promised`.
- After the metadata fix, non-canonical entry-size headers report `Pack entry size header uses a non-canonical encoding`, overflowing entry-size headers report `Pack entry size header overflowed while decoding`, and overflowing OFS_DELTA base-distance metadata reports `Ofs-delta base distance overflowed while decoding`.

## Verification

- `php -l lanes/gitoxide/src/PackData.php` - passed.
- `php -l lanes/gitoxide/tests/PackDataTest.php` - passed.
- `php -l lanes/gitoxide/examples/wordpress-pack-data.php` - passed.
- `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php` - passed, `1 test files, 85 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PackBuilderTest.php` - passed, `1 test files, 146 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests/PackDataTest.php lanes/gitoxide/tests/PackBuilderTest.php` - passed, `2 test files, 231 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-pack-data.php` - passed.
- `php tools/run-tests.php lanes/gitoxide/tests` - passed, `38 test files, 4032 assertions, 0 failures`.
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'` - passed.
- `git diff --check -- lanes/gitoxide` - passed.

## Non-Overlap

This slice does not repeat the accepted 2026-05-31 batch for object database commit writes, recursive tree merge multiple-base fixture shape, pack/MIDX validation, packed-reference peeled transactions, smart HTTP chained redirect cookie recomputation, SSH protocol-v2/auth boundary handling, protocol v2 ls-refs advertisements, sparse checkout pathspecs, config include/includeIf, attributes pathspecs, loose object integrity, packed/loose reference peeling, commit gpgsig stripping, reflog parsing, index/cache-tree sparse metadata, tree pathspec walking, pack delta declared-size guards, pack delta oversized-header guards, or pack delta result-buffer guards. It is bounded to pack-entry metadata safety before delta-base resolution: canonical size-header parsing and OFS_DELTA base-distance overflow rejection.

## Dependency Closure

No new support component is needed. The slice reuses PHP zlib, existing `PackData`, `PackIndex`, `GitObject`, and WordPress pack fixtures. Full cargo workspace parity remains excluded for this isolated worker because it would hydrate/build the large feature-heavy upstream workspace; the upstream source files and focused PHP lane tests provide the evidence for this bounded behavior.
