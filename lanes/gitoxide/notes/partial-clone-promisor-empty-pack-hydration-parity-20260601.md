# Partial-clone promisor empty pack hydration parity

- Micro-slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T131840Z`
- Accepted base: `a93e599b8ba28b765620aaefefa98a3cad05be92`
- Source truth: upstream Gitoxide `gix-pack/src/bundle/write/mod.rs` returns no
  data, index, or keep paths when `outcome.num_objects == 0`; upstream
  `gix-pack/tests/pack/data/output/count_and_entries.rs::empty_pack_is_allowed`
  records that empty packs are valid but not written at rest because they are
  useless.
- Native delta: `ObjectDatabase::writePromisorPackBundle()` now treats an empty
  `PackBuildResult` as a no-op filtered response. It returns null pack/index/
  promisor/keep names, `materialized=false`, zero objects, and leaves promisor
  pack names, promisor object ids, packed object count, and sidecar files
  unchanged. The WordPress lazy-promisor example records the same no-op path.
- Red-first evidence: after adding the focused assertion, `php tools/run-tests.php
  lanes/gitoxide/tests/PartialCloneTest.php` failed at `1 test files, 359
  assertions, 1 failures` because the empty response still materialized
  `pack-029d08823bd8a8eab510ad6ac75c823cfd3ed31e.pack`.
- Verification after implementation:
  - `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php` passed
    `1 test files, 387 assertions, 0 failures` (`+29` focused assertions over
    the clean baseline of `358`).
  - `php -l lanes/gitoxide/src/ObjectDatabase.php` passed.
  - `php -l lanes/gitoxide/tests/PartialCloneTest.php` passed.
  - `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php` passed.
  - `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php` exited 0.
  - `git diff --check -- lanes/gitoxide` passed.
- Dependency closure: no new support component is needed; this reuses existing
  `PackBuilder::build([])` empty-pack output and the native object database
  promisor writer.
- Non-overlap: this avoids accepted promisor config boolean, external hydration
  refresh, refresh-never, pack-bundle hydration, interrupted pack keep,
  orphan-index, alternate-base, thin-delta, thin repair, cross-pack delta, and
  recursion-bound slices. It maps only the zero-object filtered pack response
  no-op boundary.
