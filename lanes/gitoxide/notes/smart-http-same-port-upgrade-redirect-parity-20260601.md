# Smart HTTP Same-Port Upgrade Redirect Parity - 2026-06-01

Slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T114015Z`

Source truth:
- Upstream checkout: `/home/claude/port-libs/.upstream-cache/gitoxide`
- Pinned upstream commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`
- Behavior: `gix-transport/src/client/blocking_io/http/redirect.rs`
  `shares_authority_or_upgrades_scheme()` permits an HTTP-to-HTTPS redirect
  when the authority is preserved, including the explicit same non-default port,
  and rejects redirects that change the non-default port.
- Upstream tests referenced: `base_url_allows_same_host_scheme_upgrade` and
  `base_url_rejects_authority_changes`.

Patch summary:
- Added focused PHP receive-pack coverage for a repository at
  `http://git.example.test:8443/wp-content.git` redirecting discovery to
  `https://git.example.test:8443/redirected.git/info/refs?...`, then posting
  receive-pack to the redirected HTTPS base with request body, Git-Protocol, and
  secure cookies preserved.
- Added a rejection guard for `http://git.example.test:8080/...` redirecting to
  `https://git.example.test:8443/...`, matching upstream authority-boundary
  rejection.
- Extended the WordPress receive-pack transport fixture and example summary so
  deployment/review smoke paths expose this redirect boundary.

Verification:
- Baseline before edit:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  -> `1 test files, 1118 assertions, 0 failures`
- After edit:
  `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php`
  -> `1 test files, 1136 assertions, 0 failures`
- Focused assertion delta: `+18`
- Full upstream Cargo workspace: not run for this isolated micro-slice.

Dependency closure:
- No new support component needed. The existing injected smart-HTTP requester,
  redirect resolver, cookie scope, and receive-pack client boundaries were
  sufficient.

Non-overlap:
- This does not repeat the accepted default-port upgrade/proxy-cookie redirect
  coverage, redirect dot-segment normalization, redirect limit, cookie path,
  status classification, Git-Protocol header scoping, or SSH receive-pack
  boundary slices. It adds explicit non-default same-port scheme-upgrade parity
  and non-default port-mismatch rejection evidence.

Next task:
- Continue receive-pack transport parity with a different upstream-backed
  smart-HTTP/SSH edge, preferably one that exercises request/response boundary
  behavior not already covered by redirects, proxy selection, cookies, status
  classification, or command construction.
