# Credential Helper Exchange Action Boundary Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T132418Z`
Base accepted HEAD: `3fbf3e52f7c6e6a72c8a17054cab01a393183925`

## Source Truth

- Upstream `gix-credentials/src/program/main.rs` converts only the first
  helper argument into an action, accepting `fill|get`, `approve|store`, and
  `reject|erase`.
- The same entrypoint rejects missing or invalid actions before invoking the
  credential callback.
- Upstream store and erase actions must not return a context. Returning one is
  treated as a helper implementation bug rather than serialized output.

## PHP Delta

- `CredentialHelperExchangeTest` now covers missing action rejection, invalid
  action rejection, first-action-only parsing with ignored trailing args, and
  store/erase callback context-return rejection.
- The WordPress credential-context fixture/example now records the same
  deployment-helper action boundary without invoking `git credential`, reading
  credential stores, or inspecting provider/OAuth state.
- No production source change was needed; the current native PHP helper
  exchange already matched the pinned upstream action boundary.

## Verification

- Baseline credential family before the patch:
  `php tools/run-tests.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`
  passed `4 test files, 437 assertions, 0 failures`.
- Focused credential family after the patch:
  `php tools/run-tests.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`
  passed `4 test files, 454 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 9481 assertions, 0 failures`.
- Changed PHP lint passed for
  `lanes/gitoxide/tests/CredentialHelperExchangeTest.php`,
  `lanes/gitoxide/tests/CredentialContextTest.php`,
  `lanes/gitoxide/fixtures/wordpress-credential-context.php`, and
  `lanes/gitoxide/examples/wordpress-credential-context.php`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php` exited `0`.
- `git diff --check -- lanes/gitoxide` exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local native
credential context parser and callback-based helper exchange boundary. It does
not read credential stores, process environments, provider config,
OAuth/browser state, live remotes, external Git binaries, or helper processes.

## Non-Overlap

This deepens the represented credential helper entrypoint cluster without
repeating accepted URL destructuring, HTTP root-path clearing,
HTTP-path-disabled preservation, signed integer/boolean parsing, CR-byte line
parsing, constructor string-field validation, raw next-action preservation,
cascade quit ordering, prompt fallback, platform helper selection, smart HTTP
proxy credentials, SSH receive-pack credentials, receive-pack transport,
pack/index, object database, reference transactions, sparse-checkout,
pathspec, merge-base, URL/refspec, partial-clone, protocol, or tree-merge
work.
