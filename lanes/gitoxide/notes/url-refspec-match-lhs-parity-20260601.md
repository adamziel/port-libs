# URL/refspec match-lhs parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T112328Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/match_group/mod.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/match_group/types.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/src/match_group/util.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-refspec/tests/refspec/matching.rs`

Upstream `gix-refspec` `MatchGroup::match_lhs()` maps fetch refspec left-hand
sides against advertised remote refs. It expands partial names, supports simple
glob capture and one-sided complex wildmatch patterns, rewrites destination
refs, deduplicates identical mappings, maps object IDs without requiring an
advertised ref, and applies negative refspecs after positive mappings.

Native delta:

- Added `RefSpec::matchFetchRemoteRefs()` for bounded fetch match-lhs parity.
- Implemented partial ref expansion, full-ref matching, simple `*` capture,
  one-sided `?`/`[]`/multi-star pattern matching, destination ref
  normalization, object-id source mapping, mapping deduplication, and negative
  fetch refspec exclusion.
- Extended the WordPress URL/refspec fixture and example smoke to map
  advertised deployment refs into tracking refs while excluding a private
  branch and preserving a tag-only FETCH_HEAD-style mapping.

Evidence:

- Focused before editing: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 738 assertions, 0 failures`.
- Red-first after adding tests/example before implementation: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` failed on missing `RefSpec::matchFetchRemoteRefs()` with `1 test files, 664 assertions, 2 failures`.
- Focused after implementation: `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed `1 test files, 765 assertions, 0 failures`.
- Example smoke: `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test` exited `0`.
- PHP lint passed for changed PHP files.
- Diff whitespace: `git diff --check -- lanes/gitoxide` exited `0`.

Dependency closure:

No new support component is needed. This slice reuses the native PHP `RefSpec`
parser/writer, lane fixture, example smoke, and PHP test harness. No Git binary,
network provider, credential store, or live service is required.

Non-overlap:

This extends URL/refspec parity from parse/write/prefix behavior into fetch
match-group mapping. It does not repeat accepted URL parsing, credential
mutation, from-parts construction, alternate URL serialization, URL byte
roundtrip, argument safety, home-path/canonical path handling, short-hex prefix
classification, instruction identity, push implicit destination writing,
transport/protocol, reference transaction, object database, pack/index,
pathspec, sparse-checkout, merge-base, or tree-merge work.
