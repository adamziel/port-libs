# Credential Helper Context Diagnostic Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T155444Z`
Base accepted HEAD: `57d8e6e255e0f04075a11bb6231bd0b9bffc3ac4`

## Source Truth

- Upstream cache: `/home/claude/port-libs/.upstream-cache/gitoxide` at `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `gix-credentials/src/protocol/context/serde.rs` writes credential context fields in order and errors when a key or value contains a NUL byte or newline.
- `gix-credentials/src/protocol/mod.rs` formats missing-identity diagnostics with a best-effort `context.write_to(&mut buf).ok()`, so invalid later fields do not replace the high-level missing-identity error with a lower-level context-encoding error.
- `gix-credentials/src/helper/cascade.rs` maps helper get outcomes through the same missing-identity path.

Focused upstream check:

```text
cd /home/claude/port-libs/.upstream-cache/gitoxide && cargo test -p gix-credentials helper::invoke_outcome_to_helper_result --quiet
result: 2 passed, 0 failed
```

## PHP Delta

- Added `CredentialContext::diagnosticBytes()` as a best-effort companion to strict `storageBytes()`. It preserves upstream field order and stops at the first invalid key/value while returning the bytes accumulated so far.
- Switched `CredentialHelperOutcome` and `CredentialCascade` missing-identity messages to use redacted diagnostic bytes.
- Added focused coverage for invalid newline-bearing context paths and invalid secret fields. Strict storage encoding still rejects invalid context bytes; missing-identity diagnostics now stay on the missing-identity error path and keep secrets redacted.
- Extended the WordPress credential-context fixture/example to cover malformed helper-context diagnostics for deployment-token safety.

## Red-First Evidence

Before the patch, invalid context bytes replaced missing-identity errors:

```text
CredentialHelperOutcome::requireIdentity(null, invalid path context)
InvalidArgumentException: Credential context keys and values must not contain NUL bytes or newlines

(new CredentialCascade([]))->get(invalid path context)
InvalidArgumentException: Credential context keys and values must not contain NUL bytes or newlines
```

After the patch, both paths surface missing identity with best-effort redacted context bytes.

## Verification

```text
php -l lanes/gitoxide/src/CredentialContext.php && php -l lanes/gitoxide/src/CredentialCascade.php && php -l lanes/gitoxide/src/CredentialHelperOutcome.php && php -l lanes/gitoxide/tests/CredentialContextTest.php && php -l lanes/gitoxide/tests/CredentialCascadeTest.php && php -l lanes/gitoxide/tests/CredentialHelperExchangeTest.php && php -l lanes/gitoxide/fixtures/wordpress-credential-context.php && php -l lanes/gitoxide/examples/wordpress-credential-context.php
result: no syntax errors detected

php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialProgramTest.php
result: 4 test files, 497 assertions, 0 failures

php lanes/gitoxide/examples/wordpress-credential-context.php >/tmp/gitoxide-credential-context-example.out && printf 'example ok\n' && wc -c /tmp/gitoxide-credential-context-example.out
result: example ok; 0 /tmp/gitoxide-credential-context-example.out

php tools/run-tests.php lanes/gitoxide/tests
result: 40 test files, 10069 assertions, 0 failures
```

## Dependency Closure

No new support component is needed. The slice reuses native PHP credential context and cascade helpers, avoids live credential stores, avoids environment/provider configuration reads, and does not invoke external Git credential helpers or network remotes.

## Non-Overlap

This does not repeat accepted credential URL destructuring, local/file URL handling, CR-byte preservation, UTF-8 validation, raw next-action parsing, cascade quit/prompt handling, smart HTTP/SSH, refs, pack/index, object database, or pathspec work. It only deepens the represented credential helper error-path cluster for upstream missing-identity diagnostic parity.
