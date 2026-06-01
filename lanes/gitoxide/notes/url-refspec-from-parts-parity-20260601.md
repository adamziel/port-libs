# Gitoxide URL/Refspec From-Parts Parity

Micro-slice: `gitoxide-url-refspec-parse-normalize-parity-20260601T061645Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/src/lib.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/access.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/file.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-url/tests/url/parse/ssh.rs`

Upstream `gix-url` exposes `Url::from_parts()` as a validating constructor: it
serializes the supplied fields with the same canonical/alternate writer used by
normal URLs, then parses the bytes back into a normalized `Url`. This preserves
local file path bytes in alternate form, lowercases DNS-style hosts through
normal parsing, and falls back to canonical SSH URL bytes when alternate form
cannot represent a password or port.

## Native Delta

- `GitUrl::fromParts()` now builds a URL from scheme, user, password, host,
  port, path, and alternate-form fields, then validates it by round-tripping
  through the existing parser.
- Focused tests cover HTTPS normalization, canonical and alternate file URLs,
  non-UTF-8 local-path byte preservation, SSH alternate form, SSH
  password/port canonical fallback, pathless SSH root serialization, and
  invalid constructed inputs.
- The WordPress URL/refspec fixture/example now validates deployment remotes
  built from stored HTTPS and SSH parts before fetch/push refspec handling.

## Verification

- Baseline before this slice:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 637 assertions, 0 failures`.
- Focused after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/UrlRefSpecTest.php` passed
  `1 test files, 670 assertions, 0 failures`.
- PHP lint passed for:
  `lanes/gitoxide/src/GitUrl.php`,
  `lanes/gitoxide/tests/UrlRefSpecTest.php`,
  `lanes/gitoxide/fixtures/wordpress-url-refspec-normalize.php`, and
  `lanes/gitoxide/examples/wordpress-url-refspec-normalize.php`.
- Example smoke:
  `php lanes/gitoxide/examples/wordpress-url-refspec-normalize.php --self-test`
  exited 0.
- JSON validation passed for `lanes/gitoxide/lane-status.json` and
  `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`.
- `git diff --check -- lanes/gitoxide` passed.
- Root harness and full upstream Cargo workspace were not run for this isolated
  micro-slice.

## Dependency Closure

No new support component is needed. This slice reuses the existing native PHP
URL parser/writer and refspec example plumbing; no shared support-library row,
network service, credential store, or Git binary invocation is required.

## Non-Overlap

This extends the accepted URL/refspec surface with the upstream `Url::from_parts`
construction/validation boundary. It does not repeat accepted URL byte
deserialization, credential mutation, alternate serialization, file-authority,
home-path, argument-safety, URL-length, pathless extension, forced fetch-only,
one-sided push, or short-hex refspec behavior. It also does not touch transport,
protocol v2, pack/object database, references, merge-base, sparse-checkout, or
tree/pathspec slices.
