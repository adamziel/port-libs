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

The upstream test runner is `make test`, which applies AddressSanitizer flags, cleans build outputs, builds `check.cpp`, creates `testdb/`, and runs the resulting `./check` binary. It was attempted on 2026-05-22 and failed before compiling `check.cpp`:

- `make test` exits 2 at `g++ -std=c++17 ... -c -o check.o check.cpp` with `make: g++: No such file or directory`.
- `make` and `cc` are now present, but `c++`, `g++`, and `clang++` are not on `PATH`.
- The three upstream submodules are uninitialized gitlinks.
- LMDB and BLAKE2 headers/libs were not found under `/usr/include`.

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

- `HashTreeTest.php` maps sparse empty root, leaf hash domain separation, and path bit ordering.
- `KeyTest.php` maps the `integer round-trips` scenario, most-significant-bit key addressing, prefix retention, and integer-format rejection.
- `SparseTreeTest.php` maps focused in-memory get/put/delete/update semantics from `basic put/get`, `zero-length keys`, `empty heads`, `batch insert`, `getMulti`, overwrite-before-apply, delete bubbling, missing deletes, and raw integer WordPress option/post records.
- `IteratorTest.php` maps the externally visible lower-bound and upper-bound raw-key behavior from `iterators basic` plus representative `iterators full` sweeps.
- `ProofTest.php` maps `proofRoundtrip`-style compact transport encode/decode, `basic proof`, `no unnecessary empty witnesses`, `integer proofs`/`proof sizing`, and `range proofs` slices. Imported proofs build a partial tree that validates the expected root, returns authenticated values or proven absences, and throws on unauthenticated witness subtrees.

The native PHP hash primitive currently uses the lane's SHA-256 `HashTree` surrogate because this PHP build does not expose BLAKE2s. The proof command, strand, range, and partial-tree semantics are mapped, but root/proof bytes are not digest-compatible with upstream until a native BLAKE2s implementation or acceptable PHP extension path is added.
