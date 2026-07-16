# Gitoxide URL/Refspec HTTP Userinfo Path Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T073014Z`

Accepted base: `91cc2906391fe8c2f88dd6a676bfd20c8abba8bd`

## Upstream Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/http.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/invalid.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/simple_url.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/lib.rs`

Pinned `gix-url` keeps an empty HTTP username when a password is present, so
`http://:password@example.com/repo` round-trips with password redaction in
display output. It also treats `?` and `#` as path bytes for Git remote URLs
instead of separating query/fragment components, while rejecting raw whitespace
in URL-form userinfo and path bytes before parsing.

## Native Delta

- Added focused `UrlRefSpecTest.php` assertions for HTTP password-only
  userinfo, password redaction, lossless roundtrip, query/fragment path
  delimiters, and raw whitespace rejection in username, password, and path
  positions.
- Extended the WordPress URL/refspec fixture/example with a password-only
  deployment-token remote whose query/fragment bytes remain part of the
  repository path and whose display URL redacts the token.
- No parser source change was needed; the existing native PHP URL parser already
  matched this focused upstream behavior on the current base.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 670 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 700 assertions, 0 failures`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.

Additional lint, JSON validation, and diff-check evidence is expected in the
handoff final report.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP URL parser,
writer, display-redaction helper, and WordPress fixture plumbing; no shared
dependency row, live network service, credential store, Git binary, or upstream
Cargo runner is required.

## Non-Overlap

This extends URL/refspec parity with the upstream HTTP userinfo/path delimiter
boundary. It does not repeat accepted URL byte deserialization, credential
mutation, alternate serialization, file authority, SCP IPv6, home-path,
argument-safety/root-path, URL-length, empty SSH port, pathless extension,
from-parts construction, forced fetch-only, one-sided push, or short-hex
refspec behavior. It also does not touch transport, protocol v2, pack/object
database, references, merge-base, sparse-checkout, tree/pathspec, credentials,
or partial-clone slices.
