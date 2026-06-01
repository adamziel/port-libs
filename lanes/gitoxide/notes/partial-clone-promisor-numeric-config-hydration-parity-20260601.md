Partial Clone Promisor Numeric Config Hydration Parity

- Worker slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T055415Z`
- Accepted base: `7db0bee1b6d6b17fcc1ae3a0e1b10ac7a87ade2d`
- Upstream source truth:
  `/home/claude/port-libs/.upstream-cache/gitoxide/gix-config-value/src/boolean.rs`
  parses config booleans with `yes/on/true` as true, `no/off/false` and empty
  values as false, and numeric values as nonzero/zero booleans. Upstream
  `/home/claude/port-libs/.upstream-cache/gitoxide/src/plumbing/progress.rs`
  names `remote.<name>.promisor` and `remote.<name>.partialCloneFilter` as
  partial-clone config required for Gitoxide plumbing parity.

Native PHP delta:

- `ObjectDatabase::configBooleanIsTrue()` now accepts numeric config booleans
  for promisor remotes: nonzero decimal values enable promisor behavior, zero
  disables it.
- `PartialCloneTest.php` covers `promisor = 2` as a promised-missing object
  that hydrates through `PromisorObjectResolver`, and `promisor = 0` as an
  ordinary missing object with no promisor remotes.
- `wordpress-lazy-promisor-fetch.php` now uses `promisor = 2` so the local
  smoke proves a WordPress deployment fixture can rely on numeric promisor
  config accepted by upstream `gix-config-value`.

Red-first evidence:

- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
- Result before implementation: `1 test files, 186 assertions, 2 failures`
  because numeric `remote.origin.promisor` values were not recognized.

Verification:

- `php -l lanes/gitoxide/src/ObjectDatabase.php` -> no syntax errors.
- `php -l lanes/gitoxide/tests/PartialCloneTest.php` -> no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php` -> no
  syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` -> `1
  test files, 241 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests` -> `40 test files, 7682
  assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php` -> exit 0.
- `git diff --check -- lanes/gitoxide` -> passed.

Dependency closure:

- No new support component is needed. This reuses existing config parsing,
  object database, and `PromisorObjectResolver` support.

Non-overlap:

- This does not repeat accepted promisor hydration refresh, promisor pack
  bundle hydration, cross-pack delta hydration, refresh-never behavior, or
  direct promisor inventory refresh. It narrows to the upstream config boolean
  parsing boundary that controls whether the existing hydration path is
  activated.

Next task:

- Continue with non-overlapping Gitoxide fetch sideband, send-pack,
  receive-pack, merge-base, URL/refspec, tree/pathspec, sparse-checkout,
  loose-object, config/include, object database, reference transaction,
  transport, protocol, and pack-index behavior with focused PHP gates.
