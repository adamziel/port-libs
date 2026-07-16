# Credential Helper Local File Context Parity - 2026-06-01

Slice: `gitoxide-credential-helper-context-parity-20260601T120738Z`
Base accepted HEAD: `104a9f5fce0ab0f0e77688b3f9277242f2f9e31c`

## Source Truth

- Upstream `gix-credentials/src/protocol/context/mod.rs`
  `Context::destructure_url_in_place()` delegates the `url` byte string to
  `gix_url::parse()`, then stores parsed protocol, host, username, password,
  and slash-trimmed path back into the helper context.
- Upstream `gix-credentials/src/helper/cascade.rs` destructures
  `Action::get_for_url()` before invoking helpers and sends helpers a context
  payload without the original `url` field.
- Upstream `gix-url/tests/url/parse/file.rs` treats local paths without a URL
  protocol as `file` URLs, preserves whitespace and `~` path bytes without
  username expansion, and treats root-like `file://../` paths as file URLs
  with authority but no credential path after slash trimming.

## PHP Delta

- Added focused credential-context coverage for local relative paths,
  whitespace-bearing absolute paths, tilde-prefixed local paths, and root
  `file://../` authority paths.
- Added credential-cascade coverage for `Action::get_for_url()` style local
  file paths: helpers receive `protocol=file` plus the path, with stale
  network host/user context and the original `url` removed before invocation.
- Extended the WordPress credential-context fixture/example so deployment
  diagnostics include local mirror paths that should not shell out, expand
  usernames, read credential stores, or leak stale network context.
- No production source change was needed; the current native PHP
  `CredentialContext`, `CredentialCascade`, and `GitUrl` behavior already
  matched the pinned upstream behavior.

## Verification

- Baseline focused credential family before the patch:
  `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`
  passed `4 test files, 403 assertions, 0 failures`.
- Focused credential family after the patch:
  `php tools/run-tests.php lanes/gitoxide/tests/CredentialContextTest.php lanes/gitoxide/tests/CredentialCascadeTest.php lanes/gitoxide/tests/CredentialHelperExchangeTest.php lanes/gitoxide/tests/CredentialProgramTest.php`
  passed `4 test files, 437 assertions, 0 failures`.
- Full Gitoxide lane:
  `php tools/run-tests.php lanes/gitoxide/tests` passed
  `40 test files, 9246 assertions, 0 failures`.
- Changed PHP lint passed for
  `lanes/gitoxide/tests/CredentialContextTest.php`,
  `lanes/gitoxide/tests/CredentialCascadeTest.php`,
  `lanes/gitoxide/fixtures/wordpress-credential-context.php`, and
  `lanes/gitoxide/examples/wordpress-credential-context.php`.
- `php lanes/gitoxide/examples/wordpress-credential-context.php` exited `0`.
- JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide` exited `0`.

## Dependency Closure

No new support component is needed. This slice reuses the lane-local native
credential context, cascade, URL parser, fixture, and PHP test harness. It
does not read credential stores, provider configs, OAuth/browser state,
process environments, live remotes, external Git binaries, or helper
processes.

## Non-Overlap

This deepens the represented credential helper context cluster without
repeating accepted signed-integer/boolean parsing, UTF-8 string-field
validation, CR-byte line parsing, HTTP root-path clearing, HTTP path-disabled
preservation, password-only HTTP userinfo handling, raw next-action payload
preservation, cascade quit ordering, prompt fallback, platform helper
selection, smart HTTP proxy credentials, SSH receive-pack credentials,
URL/refspec writer/display behavior, transport/status behavior, pack/index,
object database, reference transactions, sparse-checkout, pathspec,
merge-base, or tree-merge work.
