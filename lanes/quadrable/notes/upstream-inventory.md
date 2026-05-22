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

After that, `make -r test` passed all upstream scenarios on 2026-05-22. `-r` disables GNU Make built-in rules; plain `make test` now gets past the prior missing tooling, but this shell selects the built-in C++ compile rule for `check.o` and omits the repository `-Iinclude -Iexternal -Iexternal/hoytech-cpp -Iexternal/docopt.cpp` include flags, so it fails on `#include "quadrable.h"`. With built-ins disabled, the upstream Makefile's own pattern rule is used and the upstream test binary reports `All tests OK`.

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
- `KeyTest.php` maps the `integer round-trips` scenario, most-significant-bit key addressing, prefix retention, and integer-format rejection.
- `SparseTreeTest.php` maps focused in-memory get/put/delete/update semantics from `basic put/get`, `zero-length keys`, `empty heads`, `batch insert`, `getMulti`, overwrite-before-apply, delete bubbling, missing deletes, raw integer WordPress option/post records, and an exact upstream-digest WordPress snapshot root.
- `IteratorTest.php` maps the externally visible lower-bound and upper-bound raw-key behavior from `iterators basic` plus representative `iterators full` sweeps.
- `ProofTest.php` maps `proofRoundtrip`-style compact transport encode/decode, `basic proof`, `no unnecessary empty witnesses`, `integer proofs`/`proof sizing`, and `range proofs` slices. Imported proofs build a partial tree that validates the expected root, returns authenticated values or proven absences, and throws on unauthenticated witness subtrees.
- `ProofUpdateTest.php` maps focused `update proof` subcases: mutating proven leaves, rejecting updates through opaque witness branches, updating two proven leaves at different levels, splitting a proven leaf, upgrading and mutating witness leaves, splitting a witness leaf, deletion bubbling, the upstream `can't bubble a witness node` guard, and a WordPress raw-key post update from a narrow proof.
- `ProofMergeTest.php` maps focused `mergeProof` behavior from upstream proof/sub-proof semantics: separately imported proofs with the same root expand witness branches, witness leaves can be upgraded to full leaves, different roots are rejected, and merged WordPress option/post proofs can authenticate separate partial reads.
- `SyncTest.php` maps the transport and in-memory proof-fragment boundary from upstream `sync fuzz`: `SyncRequest` encode/decode, response proof length-prefix encode/decode, bounded root and later witness proof fragments, witness-leaf expansion, imported shadow roots, matching-hash witness skips during diff, scan-time diff callbacks, WordPress scan/final diff equivalence, deterministic small randomized multi-round sync convergence, and raw-key diff reconstruction. This is not full upstream sync fuzz parity because persisted LMDB node ids, memstore scan lists, and the full randomized 500-trial node-id equivalence check remain unported.
- `NodeIdTest.php` maps focused `saves nodeId` and `re-use leafs` behavior for a native in-memory tracked tree: new leaf writes return non-zero node ids, duplicate unchanged puts return zero, changed puts return fresh ids, deletes return the removed leaf id, missing deletes return zero, reused leaves preserve their original leaf node ids across a fresh checkout, and a WordPress snapshot compact rebuild keeps unchanged-record leaf ids while producing the same authenticated root.

The native PHP hash primitive now uses a lane-local pure-PHP BLAKE2s-256 implementation matching upstream `Key::hash`, including multi-block values. Root/proof bytes for the mapped in-memory and proof surfaces are digest-compatible with upstream. The sync slice now has native scan-time diff callbacks and deterministic small sync-fuzz convergence, and the tracked tree covers leaf node-id output/reuse behavior. Full upstream-scale randomized sync fuzz parity, exact branch/interior scan/diff node-id identity, and persisted LMDB node-id behavior remain unported.
