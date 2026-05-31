# Commit gpgsig object/storage parity - 2026-05-31

Micro-slice: `gitoxide-commit-signature-gpgsig-parity-20260531T102211Z`

Base accepted HEAD: `abe349fe4c5a6f978b53aa40c7bbfdcb020ef0a8`

## Upstream source truth

- Re-read `gix/src/object/commit.rs::signature()`: repository commit objects
  delegate to `gix_object::CommitRefIter::signature(&self.data,
  self.id.kind())`.
- Re-read `gix-object/src/commit/ref_iter.rs::signature()` and
  `gix-object/src/commit/mod.rs::SignedData`: the returned signed data is the
  original commit object body with the first `gpgsig` header byte range
  removed.
- Re-read the focused upstream fixture cases in
  `gix-object/tests/object/commit/iter.rs` and `from_bytes.rs`:
  `signed-singleline.txt`, `message-with-footer.txt`,
  `signed-whitespace.txt`, `signed-with-encoding.txt`, and
  `two-multiline-headers.txt`.

## Native behavior added

- `Commit::signatureForVerificationFromObject()` mirrors the object-level
  Gitoxide API for already-decoded loose commit objects, rejecting non-commit
  objects before attempting `gpgsig` parsing.
- `Commit::signatureForVerificationFromStorageBytes()` decodes canonical
  loose-object storage bytes and then applies the same commit signature
  extraction path.
- The focused tests cover single-line signatures, message-footer signed data,
  prior multiline `mergetag` preservation, unsigned commits, non-commit
  rejection, and caller-selected SHA-256 object-format validation.
- The WordPress commit-signature example now proves loose commit object storage
  verification without invoking `git verify-commit`, `gpg`, or any external
  Git binary.

## Evidence

- Red-first baseline after adding the focused object test:
  `php tools/run-tests.php lanes/gitoxide/tests/CommitSignatureObjectTest.php`
  - failed with missing `Commit::signatureForVerificationFromObject()`.
- After implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/CommitSignatureObjectTest.php`
  - `1 test files, 20 assertions, 0 failures`
- Focused commit signature set:
  `php tools/run-tests.php lanes/gitoxide/tests/CommitTest.php lanes/gitoxide/tests/CommitSignatureObjectTest.php`
  - `2 test files, 294 assertions, 0 failures`
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests`
  - `39 test files, 4155 assertions, 0 failures`
- WordPress example smoke:
  `php lanes/gitoxide/examples/wordpress-commit-signature.php`
  - exited `0`
- Syntax/diff checks:
  - `php -l` passed for changed PHP files.
  - `git diff --check -- lanes/gitoxide` exited `0`.
  - JSON validation passed for `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`
    and `lanes/gitoxide/lane-status.json`.

## Non-overlap

This is additive to the accepted raw `gpgsig` extraction and writer parity
slices. It does not change multiline header parsing or detached signature
writing; it adds the object/storage boundary that Gitoxide exposes from
`gix::object::Commit::signature()`.

## Dependency closure

No new support component is needed. The slice reuses native loose object
decoding, commit object validation, and signed-data extraction. External
`gpg`, live remotes, credential stores, and Git binaries are not executed or
counted.
