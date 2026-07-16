# Commit gpgsig ObjectDatabase Boundary, 2026-05-31

Micro-slice: `gitoxide-commit-signature-gpgsig-parity-20260531T105541Z`

Base accepted HEAD: `1050199a8fd43430a4d0f31b8acaf48bdfe1ca42`

## Upstream Truth

- Inspected upstream cache `/home/claude/port-libs/.upstream-cache/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-object/src/commit/ref_iter.rs` exposes `CommitRefIter::signature()` as the first `gpgsig` value plus exact signed commit bytes.
- `gix-object/src/commit/mod.rs` models that pair as `SignatureRef` and `SignedData`.
- `gitoxide-core/src/repository/commit.rs` resolves the repository object before verifying or signing commit signatures.

## Native PHP Delta

- Added `ObjectDatabase::commitSignatureForVerification(string $oid): ?array`.
- The helper reads the object through the database, so replacement refs, loose/packed/alternate lookup policy, and the configured object hash are honored before commit signature extraction.
- The focused test covers replacement-backed verification, ignored replacements returning unsigned/null, uppercase object ids, SHA-256 commit ids, and non-commit rejection.
- The WordPress import example now demonstrates repository-level signature verification for a replacement ref without invoking `git`, `gpg`, or credential-bearing tools.

## Evidence

- `php tools/run-tests.php lanes/gitoxide/tests/CommitSignatureObjectTest.php`
  - `1 test files, 29 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests/CommitTest.php lanes/gitoxide/tests/CommitSignatureObjectTest.php`
  - `2 test files, 307 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4328 assertions, 0 failures`
- `php lanes/gitoxide/examples/wordpress-commit-signature.php`
  - `example exit 0`
- `php -l` over changed PHP files
  - no syntax errors
- JSON manifest/status validation
  - `json ok`

## Non-overlap

This builds on accepted raw commit gpgsig extraction, commit writer, detached-signature append, and object storage slices. It does not rename or rework parser/writer behavior; it adds the repository/object-database lookup boundary needed for verification parity.

## Dependency Closure

No new support component is needed. The slice reuses native `ObjectDatabase`, `LooseObjectStore`, `LooseReferenceStore`, and `Commit` signature extraction. Full upstream Cargo tests and real GPG verification were not run in this isolated micro-slice.
