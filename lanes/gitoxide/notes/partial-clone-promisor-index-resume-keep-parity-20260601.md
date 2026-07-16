# Partial-clone promisor index-resume keep-sidecar parity

- Worker slice: `gitoxide-partial-clone-promisor-hydration-parity-20260601T182619Z`
- Base accepted HEAD: `95f3bf329230b45b13b590174fa3414e2f5a9eab`
- Upstream source truth:
  - `gix-pack/src/bundle/write/mod.rs::inner_write()` writes a `.keep` sidecar only when the final `pack-<hash>.pack` path is not already present before persisting the pack data.
  - The same path persists a missing `pack-<hash>.idx` after that check, so a resume where pack data already exists but the index is missing must rebuild the index without creating a keep sidecar.
  - `gix/src/remote/connection/fetch/receive_pack.rs` documents keep sidecars as protection for newly written packs during fetch ref updates.

## Native delta

- `ObjectDatabase::writePromisorPackBundle()` now separates pack/index materialization from keep-sidecar creation.
- A keep sidecar is created and returned only when the `.pack` data path is newly materialized.
- If the `.pack` data already exists and the `.idx`/`.promisor` sidecars are being rebuilt, the writer completes those sidecars without leaving or reporting a `.keep`.
- The WordPress lazy promisor fetch example now records a null `resumedPromisorKeep` value for an existing-pack/missing-index resume.

## Verification

- `php -l lanes/gitoxide/src/ObjectDatabase.php`
  - `No syntax errors detected in lanes/gitoxide/src/ObjectDatabase.php`
- `php -l lanes/gitoxide/tests/PartialCloneTest.php`
  - `No syntax errors detected in lanes/gitoxide/tests/PartialCloneTest.php`
- `php -l lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - `No syntax errors detected in lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PartialCloneTest.php`
  - `1 test files, 448 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests/ObjectDatabaseTest.php`
  - `1 test files, 396 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-lazy-promisor-fetch.php`
  - exited `0`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 10435 assertions, 0 failures`
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `php -r 'json_decode(file_get_contents("lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "manifest json ok\n";'`
  - `manifest json ok`
- `git diff --check -- lanes/gitoxide`
  - passed

Root harness and full upstream Cargo workspace were not run for this isolated micro-slice.

## Non-overlap

This slice does not repeat accepted promisor pack bundle writing, empty pack handling, refresh-never behavior, alternate-base thin-pack hydration, resolver-repaired thin packs, orphan index hydration, stale MIDX recovery, kept-orphan index skipping, or deep delta recursion bounds. It only fixes the upstream keep-sidecar outcome when pack data already exists and the missing promisor index is rebuilt.

## Dependency closure

No new support component is needed. The patch reuses the existing native PHP object database, promisor pack-bundle writer, focused partial-clone tests, and lazy promisor WordPress example.
