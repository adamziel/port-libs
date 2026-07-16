# Pack Index and MIDX Prefix Parity

Slice: `gitoxide-pack-index-midx-prefix-parity-20260531T131819Z`

## Upstream Source Truth

- `gix-pack/src/index/access.rs::lookup_prefix()` is the shared prefix lookup implementation for pack indexes. It returns missing/found/ambiguous and, when callers request candidates, writes the contiguous matching entry range. Missing prefixes reset the candidate range to `0..0`; unique matches set `index..index + 1`; ambiguous prefixes set the whole matching range.
- `gix-pack/src/multi_index/access.rs::lookup_prefix()` delegates to the same `gix-pack/src/index/access.rs::lookup_prefix()` helper, so MIDX prefix behavior must match pack-index behavior for SHA-1 and SHA-256 object IDs.
- `gix-odb/src/store_impls/dynamic/prefix.rs::disambiguate_prefix()` grows a candidate prefix one hex nibble at a time until the lookup is unique, returns `None` when the prefix is missing, and checks exact existence once the full object ID length is reached.

## Native PHP Delta

- `PackIndex::lookupPrefix()` and `MultiPackIndex::lookupPrefix()` now include upstream-style `candidateRange` metadata for missing, found, and ambiguous results while preserving the existing status, entry, and matches keys.
- `PackIndex::disambiguatePrefix()` and `MultiPackIndex::disambiguatePrefix()` now expose shortest unique-prefix growth with the same minimum prefix length and full-length existence boundary as Gitoxide.
- The WordPress pack-index and multi-pack-index examples now report candidate ranges and shortest content/template prefixes for compacted repository objects without invoking `git`.

## Verification

- Before focused change: `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php` -> `2 test files, 77 assertions, 0 failures`.
- After focused change: `php tools/run-tests.php lanes/gitoxide/tests/PackIndexTest.php lanes/gitoxide/tests/MultiPackIndexTest.php` -> `2 test files, 106 assertions, 0 failures`.
- Full Gitoxide lane: `php tools/run-tests.php $(find lanes/gitoxide/tests -name '*Test.php' | sort)` -> `39 test files, 4659 assertions, 0 failures`.
- PHP lint: `php -l` passed for changed source, test, fixture, and example PHP files.
- Example smokes: `php lanes/gitoxide/examples/wordpress-pack-index.php` and `php lanes/gitoxide/examples/wordpress-multi-pack-index.php` both exited 0.
- Diff check: `git diff --check -- lanes/gitoxide` exited 0.

## Non-Overlap

This slice does not repeat accepted pack/MIDX parsing, checksum, large-offset, object-database de-duplication, loose-object allocation-limit, or pack-delta behavior. It adds the remaining prefix candidate-range and disambiguation surface shared by upstream pack indexes and multi-pack indexes.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP pack-index, MIDX, object-id, and WordPress fixture helpers. Full upstream Cargo workspace parity remains excluded for this isolated worker because it would hydrate/build the large feature-heavy workspace beyond the current micro-slice.
