# Quadrable Upstream Inventory

Upstream checkout: `hoytech/quadrable` at `4f44437dc9b951a91986ad69e2856938387be614`.

The upstream test runner is `make test`, which applies AddressSanitizer flags, cleans build outputs, builds `check.cpp`, creates `testdb/`, and runs the resulting `./check` binary. This VM has not executed the C++ runner because the current porting lane only needs a static denominator and the upstream build depends on LMDB, BLAKE2, and initialized submodules.

Static denominator from `check.cpp`:

- 34 top-level `test("...")` scenarios.
- 29 `equivHeads("...")` equivalence subcases.
- 136 `verify(...)` checks.
- 20 `verifyThrow(...)` error checks.

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

