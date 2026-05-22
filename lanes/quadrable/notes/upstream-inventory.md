# Quadrable Upstream Inventory

Upstream checkout: `hoytech/quadrable` at `4f44437dc9b951a91986ad69e2856938387be614`.

## Cloned Source Inventory

The local upstream cache is a checkout, not a seed placeholder. A targeted `git ls-tree -r --name-only HEAD` inventory counts 55 tracked upstream paths:

- 3 C++ entrypoints: `check.cpp`, `quadb.cpp`, `syncBench.cpp`.
- 22 Quadrable public/internal headers under `include/quadrable*`.
- 20 documentation assets under `docs/`.
- 3 external submodule gitlinks: `external/docopt.cpp`, `external/hoytech-cpp`, `external/lmdbxx`.
- 7 root metadata or support files: `.gitignore`, `.gitmodules`, `LICENSE`, `Makefile`, `README.md`, `TODO`, and the aggregate `include/quadrable.h`.

## Runner Status

The upstream test runner is `make test`, which applies AddressSanitizer flags, cleans build outputs, builds `check.cpp`, creates `testdb/`, and runs the resulting `./check` binary.

Initial 2026-05-22 evidence found missing runner dependencies. This lane then installed the directly required OS packages:

- `sudo -n dnf install -y gcc-c++ lmdb-devel libb2-devel libasan`
- `git submodule update --init --depth 1 external/hoytech-cpp external/lmdbxx external/docopt.cpp`

After that, `make -r test` passed all upstream scenarios on 2026-05-22, and was reconfirmed after the tracked fork/branch node-id slice, after the bounded tracked diff node-id slice, after the native memStore-range tracked-node slice, after the named-head memStore fork guard slice, after the tracked diff reconstruction slice, after the imported sync shadow node-id slice, after the bounded upstream-MT19937 sync-fuzz slice, after the composite integer/hash key slice, after the PHP-native integer range guard slice, after the native upstream-shaped `SyncFuzzer` helper slice, after the persisted tracked node-store snapshot slice, and after the cached full proof-tree/full native fuzzer slice. `-r` disables GNU Make built-in rules; plain `make test` now gets past the prior missing tooling, but this shell selects the built-in C++ compile rule for `check.o` and omits the repository `-Iinclude -Iexternal -Iexternal/hoytech-cpp -Iexternal/docopt.cpp` include flags, so it fails on `#include "quadrable.h"`. With built-ins disabled, the upstream Makefile's own pattern rule is used and the upstream test binary reports `All tests OK`.

The explicit heavy native parity probe `SyncFuzzer::run(500, 0)` now passes after caching immutable full proof trees during sync-fragment export. The 2026-05-22 run completed 500 upstream-shaped trials in 167.914s, with max 9 round trips, 25,537 requests, and 22,591 responses. The probe is intentionally not part of the fast root suite.

Static denominator from `check.cpp`:

- 34 top-level `test("...")` scenarios.
- 29 `equivHeads("...")` equivalence subcases.
- 136 `verify(...)` checks.
- 20 `verifyThrow(...)` error checks.
- 10 proof/range scenarios touch proof export/import/sizing.
- 5 scenarios touch iterator behavior (`back up start of iterator window`, `re-use leafs`, `integer proofs`, `iterators basic`, `iterators full`).
- 1 scenario covers sync fuzz.

Top-level scenarios:

1. basic put/get
2. zero-length keys
3. overwriting updates before apply
4. saves nodeId
5. integer round-trips
6. empty heads
7. batch insert
8. getMulti
9. del
10. del bubble
11. mix del and put
12. del non-existent
13. leaf splitting while deleting/updating split leaf
14. bunch of strings
15. large mixed update/del
16. back up start of iterator window
17. fork
18. re-use leafs
19. basic proof
20. use same empty node for multiple keys
21. more proofs
22. big proof test
23. sub-proof test
24. no unnecessary empty witnesses
25. update proof
26. integer proofs
27. proof sizing
28. iterators basic
29. iterators full
30. range proofs
31. memStore basic
32. memStore forking from lmdb
33. memStore-only env
34. sync fuzz

Current PHP mapping:

- `HashTreeTest.php` maps upstream BLAKE2s-256 key/value hash vectors, sparse empty root, leaf hash domain separation, branch hashing, and path bit ordering.
- `KeyTest.php` maps the `integer round-trips` scenario, PHP-native overflow rejection for upstream's `int range exceeded` boundary, most-significant-bit key addressing, prefix retention, integer-format rejection, and the upstream `Key::fromIntegerAndHash` 23-31 byte truncated hash overlay used for composite WordPress post-meta keys.
- `SparseTreeTest.php` maps focused in-memory get/put/delete/update semantics from `basic put/get`, `zero-length keys`, `empty heads`, `batch insert`, `getMulti`, overwrite-before-apply, delete bubbling, missing deletes, cached full proof-tree invalidation after later updates, raw integer WordPress option/post records, and an exact upstream-digest WordPress snapshot root.
- `IteratorTest.php` maps the externally visible lower-bound and upper-bound raw-key behavior from `iterators basic` plus representative `iterators full` sweeps.
- `ProofTest.php` maps `proofRoundtrip`-style compact transport encode/decode, `basic proof`, `no unnecessary empty witnesses`, `integer proofs`/`proof sizing`, and `range proofs` slices. Imported proofs build a partial tree that validates the expected root, returns authenticated values or proven absences, and throws on unauthenticated witness subtrees.
- `ProofUpdateTest.php` maps focused `update proof` subcases: mutating proven leaves, rejecting updates through opaque witness branches, updating two proven leaves at different levels, splitting a proven leaf, upgrading and mutating witness leaves, splitting a witness leaf, deletion bubbling, the upstream `can't bubble a witness node` guard, and a WordPress raw-key post update from a narrow proof.
- `ProofMergeTest.php` maps focused `mergeProof` behavior from upstream proof/sub-proof semantics: separately imported proofs with the same root expand witness branches, witness leaves can be upgraded to full leaves, different roots are rejected, and merged WordPress option/post proofs can authenticate separate partial reads.
- `SyncTest.php` maps the transport and in-memory proof-fragment boundary from upstream `sync fuzz`: `SyncRequest` encode/decode, response proof length-prefix encode/decode, bounded root and later witness proof fragments, witness-leaf expansion, imported shadow roots, imported proof-fragment leaf node ids in the memStore range, upstream path-order/same-path fragment request rejection, matching-hash witness skips during diff, scan-time diff callbacks, WordPress scan/final diff equivalence, deterministic small randomized multi-round sync convergence with scan/final node-id parity, raw-key diff reconstruction, a pure-PHP `std::mt19937`-compatible seed-zero generator, the first two upstream-shaped sync-fuzz trials using upstream RNG dimensions and request/response byte budgets, a reusable native `SyncFuzzer` helper parameterized for the upstream 500-trial shape, a fast four-trial CI mapping of upstream RNG dimensions and budgeted request/response counts, and path-projected diffs over partial shadow witness branches. A full native `SyncFuzzer::run(500, 0)` probe now passes outside the fast suite; remaining sync-fuzz parity work is to exercise persisted tracked-node snapshots and branch node-id continuity inside that loop.
- `SyncTest.php` also now exposes the upstream-shaped `nodeIdShadow` checkpoint for native PHP sync sessions: imported partial roots report memStore-range root node ids, every imported shadow node id stays in the memStore range, and expanding later proof fragments produces fresh shadow root node ids while preserving the authenticated remote root.
- `NodeIdTest.php` maps focused `saves nodeId`, `fork`, `re-use leafs`, `memStore basic`, `memStore forking from lmdb`, `memStore-only env`, and `Sync::diff` leaf node-id behavior for a native in-memory tracked tree: new leaf writes return non-zero node ids, duplicate unchanged puts return zero, changed puts return fresh ids, deletes return the removed leaf id, missing deletes return zero, saved branch head ids can be checked out to restore old and new trees, named heads persist head node ids, tracked node-store snapshots restore named heads and branch node ids across JSON persistence, copy-on-write updates reuse unchanged branch child ids after reload, reused leaves preserve their original leaf node ids across a fresh checkout, memStore-mode writes allocate from upstream's `firstMemStoreNodeId` range, detached overlays can add volatile records while the base tree remains unchanged, named heads reject memStore node persistence until an explicit fork detaches the head, changed/added diffs report the target leaf node id, deleted diffs report the source leaf node id, bounded scan callbacks match final diff node ids, tracked diffs can be applied to reconstruct changed randomized forks, and WordPress snapshot compact rebuild/fork/diff/reconstruct/memStore overlay/named-head/persisted-reload examples keep authenticated old and updated roots addressable by node id.

The native PHP hash primitive now uses a lane-local pure-PHP BLAKE2s-256 implementation matching upstream `Key::hash`, including multi-block values, and the key primitive now includes upstream-compatible `fromIntegerAndHash` composite keys for integer prefixes plus 23-31 byte hash suffixes. Integer keys fail closed with `int range exceeded` before PHP signed-integer overflow can corrupt a sparse key. Root/proof bytes for the mapped in-memory and proof surfaces are digest-compatible with upstream. The sync slice now has native scan-time diff callbacks, deterministic small and full upstream-shaped sync-fuzz convergence, imported proof-fragment shadow leaf node ids that match scan-time and final diffs, exposed memStore-range sync shadow root node ids, upstream-compatible same-path fragment request rejection, an upstream-compatible MT19937 RNG stream for bounded sync-fuzz dimensions, cached full proof-tree reuse for repeated proof-fragment export, and partial-shadow diff recursion that can skip hash-equal witness branches while diffing changed branch/leaf paths. The tracked tree covers leaf node-id output/reuse, saved branch-head checkout, named head checkout/persistence, persisted tracked node-store snapshot restore, copy-on-write unchanged branch reuse after reload, tracked leaf diff node ids, bounded scan/final diff node-id equality, randomized tracked diff application/reconstruction, memStore-range allocation for volatile detached writes, and the named-head LMDB/MemStore guard from upstream `memStore forking from lmdb`. Full persisted tracked-node sync-fuzz parity remains unported.
