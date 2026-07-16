# Send-Pack Unpack-Only Receive-Status Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T004449Z`

## Upstream Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` keep the Gitoxide
  transport boundary where send-pack receives nested report-status packet
  lines on sideband channel 1.
- Git `send-pack.c::receive_status()` reads the unpack status and then consumes
  ref status lines until packet reading returns a non-normal packet. A receiver
  that sends `unpack ok` followed by flush leaves the requested refs without
  command status, and Git reports those refs as remote failures rather than
  treating the receive-status packet stream itself as malformed.

## Behavior Added

- `PushResponse::parseReportStatus()` now accepts `unpack ok` followed by a
  report-status flush or delimiter even when no per-ref command status line is
  present.
- `PushResponse::isSuccessful()` now requires at least one ref status, so a raw
  unpack-only response is not a false success.
- `PushResponse::forExpectedRefNames()` can now apply its existing
  `remote failed to report status` fallback to every requested ref when the
  remote reports only unpack status.
- The WordPress protocol-v1 push-response fixture/example records an
  unpack-only deployment response whose expected deployment branch and tag are
  marked as remote failures.

## Evidence

- Red-first probe before the fix:
  `php -r 'require "tools/bootstrap.php"; ... PushResponse::fromReportStatusPacketLines($packet("unpack ok\n") . "0000") ...'`
  printed `InvalidArgumentException: push response: missing command status`.

- `php -l lanes/gitoxide/src/PushResponse.php`
  reported no syntax errors.
- `php -l lanes/gitoxide/tests/PushResponseTest.php`
  reported no syntax errors.
- `php -l lanes/gitoxide/fixtures/wordpress-protocol-v1-push-response.php`
  reported no syntax errors.
- `php -l lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  reported no syntax errors.
- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`
  passed with `1 test files, 202 assertions, 0 failures`.
- `php tools/run-tests.php lanes/gitoxide/tests`
  passed with `40 test files, 6505 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
  exited 0.
- `php -r 'foreach (["lanes/gitoxide/lane-status.json", "lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR); echo $file . " OK\n"; }'`
  validated both changed JSON files.
- `git diff --check -- lanes/gitoxide`
  exited 0.

## Dependency Closure

No new support component is needed. This reuses the existing native packet-line
reader, sideband accumulator, `PushResponse`, `PushRefStatus`, and the
WordPress push-response fixture/example. It does not shell out to Git, run live
provider tests, read credentials, or require a shared support activation gate.

## Non-Overlap

This extends the accepted send-pack receive-status expected-ref fallback
without repeating report-status-v2 SHA-1/SHA-256 option parsing, proc-receive
fall-through parsing, repeated option overwrite behavior, unrequested-option
rejection, fatal sideband errors, packet-line bound guards, line-feed trimming,
response-end or delimiter terminators, smart HTTP redirect/cookie/proxy
behavior, SSH receive-pack argument safety, or receive-pack advertisement
parsing. It is bounded to the upstream unpack-only receive-status stream where
no command status lines are present before flush.
