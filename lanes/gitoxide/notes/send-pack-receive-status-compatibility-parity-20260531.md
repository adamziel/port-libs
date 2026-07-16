# Send-Pack Receive-Status Compatibility Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260531T151811Z`

Upstream source truth:

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` define the Gitoxide
  send-pack boundary where sideband channel 1 carries nested receive-status
  packet lines.
- Git `send-pack.c::receive_status()` at upstream Git source commit
  `2be606a3bd1c916fcc14435556a807c6f5b5ce14` accepts remote status text after
  `ok <ref>`, defaults a bare `ng <ref>` status to `failed`, ignores unknown
  report-status-v2 options after a matched status, and lets repeated
  `refname`, `old-oid`, `new-oid`, and `forced-update` option data overwrite
  the current report.
- Git `gitprotocol-pack` keeps the baseline grammar for `unpack`, `ok`, `ng`,
  and report-status-v2 option lines.

Behavior added:

- `PushResponse` now splits `ok <ref> <remote-status>` and `ng <ref>
  <reason>` at the first post-ref space, so a successful status can preserve
  optional remote status text without treating it as part of the refname.
- Bare `ng <ref>` status lines now default the rejection reason to `failed`,
  matching upstream send-pack fallback behavior.
- `PushRefStatus` now keeps optional OK status text while applying
  report-status-v2 options, ignores unknown extension options after a matched
  successful status, and treats repeated `refname`, `old-oid`, `new-oid`, and
  `forced-update` options as last-wins data.
- The prior lane-local `option fall-through` guard remains strict: duplicate
  or value-bearing fall-through options still fail, and options after rejected
  refs still fail.
- The WordPress protocol-v1 push-response fixture/example now covers a
  proc-receive-like accepted pseudo-ref with optional status text, duplicate
  refname/old-oid overwrite behavior, an ignored future option, a value-bearing
  forced-update option, and a bare protected-branch rejection defaulting to
  `failed`.

Verification evidence:

- Red-first focused probe before implementation:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines(...) ...'`
  failed with `InvalidArgumentException: Reference name contains an invalid byte`
  for an `ok refs/heads/main already up-to-date` receive-status line.
- Focused check after implementation:
  `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php` passed
  with `1 test files, 102 assertions, 0 failures`.

Dependency closure:

- No new support component is needed. This reuses the existing native
  packet-line receive-status parser, `PushResponse`, `PushRefStatus`, and the
  WordPress push-response fixture/example. No live service, credential store,
  shell-out to Git, or shared dependency activation is needed.

Non-overlap:

- This does not repeat accepted fatal receive-status parsing, proc-receive
  fall-through status parsing, packet-line maximum or empty-line guards,
  line-feed-only trimming, report-status-v2 SHA-1/SHA-256 object option
  parsing, send-pack status packet bounds, protocol-v2 fetch sideband parsing,
  smart HTTP redirect/cookie behavior, or receive-pack transport advertisement
  ERR parsing. It is bounded to send-pack receive-status compatibility for
  optional OK status text, bare NG fallback text, ignored future options, and
  repeated report-status-v2 option overwrite behavior.
