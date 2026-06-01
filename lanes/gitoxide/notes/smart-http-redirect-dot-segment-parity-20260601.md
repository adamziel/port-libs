# Smart HTTP Receive-Pack Dot-Segment Redirect Parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T030653Z`

Base accepted HEAD: `d8d21668f951b3baacf0bf931be2110eb082245a`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/redirect.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/reqwest/remote.rs`
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs`

The upstream HTTP clients first resolve redirect `Location` values to an effective URL, then use `redirect::base_url()` to enforce same-authority, safe-upgrade, and suffix boundaries. This slice maps the native smart HTTP receive-pack transport to that boundary for relative POST redirects that contain dot segments.

## Native Delta

- `SmartHttpReceivePackTransport::normalizeRedirectUrl()` now normalizes redirect path dot segments before reusing the effective base for receive-pack POST replay.
- `ReceivePackTransportTest.php` covers a relative permanent redirect from `/wp-content.git/git-receive-pack` through `../redirected.git/git-receive-pack` and asserts the replay URL is `https://git.example.test/redirected.git/git-receive-pack` without a literal `/../` segment.
- The WordPress smart HTTP follow-redirect fixture/example now exposes `dotSegmentPostRedirectNormalized` while preserving generated pack request bytes and redirect cookies.

## Red-First Evidence

Before the implementation, the focused test failed as expected:

`php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`

Observed failure:

- Expected replay URL: `https://git.example.test/redirected.git/git-receive-pack`
- Actual replay URL: `https://git.example.test/wp-content.git/../redirected.git/git-receive-pack`
- Result: `1 test files, 695 assertions, 1 failures`

## Verification

- `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - `1 test files, 759 assertions, 0 failures`
- `php tools/run-tests.php lanes/gitoxide/tests`
  - `40 test files, 7099 assertions, 0 failures`
- `php -l lanes/gitoxide/src/SmartHttpReceivePackTransport.php`
  - `No syntax errors detected`
- `php -l lanes/gitoxide/tests/ReceivePackTransportTest.php`
  - `No syntax errors detected`
- `php -l lanes/gitoxide/fixtures/wordpress-smart-http-follow-redirects.php`
  - `No syntax errors detected`
- `php -l lanes/gitoxide/examples/wordpress-smart-http-follow-redirects.php`
  - `No syntax errors detected`
- `php lanes/gitoxide/examples/wordpress-smart-http-follow-redirects.php`
  - exit `0`
- `git diff --check -- lanes/gitoxide`
  - exit `0`

## Status Delta

- `lane-status.json` `phpPass`: `7097 -> 7099`
- `UPSTREAM_TEST_MANIFEST.json` mapped coverage: `1714 / 2886 -> 1715 / 2886`
- Full upstream Cargo workspace: not run for this isolated micro-slice.

## Dependency Closure

No new support component is needed. The slice reuses the existing native smart HTTP requester, redirect resolver, cookie jar, and receive-pack request body plumbing. No live network, provider credential, or upstream Cargo workspace runner was used.

## Non-Overlap

This does not repeat earlier safe initial redirect, proxy credential/cookie, 304 proxy, packet-line boundary, SSH receive-pack, git-daemon, send-pack status, reference transaction, tree/pathspec, or object database slices. It is limited to effective-URL path normalization for method-preserving smart HTTP receive-pack redirects.

## Root Harness

Not run - isolated micro-slice.
