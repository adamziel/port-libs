# Send-Pack Receive-Status Multi-Rewrite No-Refname Parity

Micro-slice: `gitoxide-send-pack-receive-status-parity-20260601T085813Z`

## Source Truth

- Upstream Gitoxide cache commit: `87433ed33eee9ba974111d20b854f6acb07cd4a6`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/tests/client/git.rs::push_v1_simulated`
  and `gix-transport/tests/fixtures/v1/push.response` define the Gitoxide
  transport boundary where sideband channel 1 carries nested receive-status
  packet lines.
- Git `send-pack.c::receive_status()` at
  `2be606a3bd1c916fcc14435556a807c6f5b5ce14` appends a new
  `ref_push_report` for each repeated successful status with report-status-v2
  options, while `option refname` is optional.
- Git upstream test `t/t5411/test-0036-report-multi-rewrite-for-one-ref.sh`
  covers multiple proc-receive rewrites for one requested ref where one report
  omits `option refname` and therefore keeps the requested ref as its effective
  report target.

## Behavior Verified

- `PushResponse::forExpectedRefNames()` now has focused coverage for three
  proc-receive reports attached to the same requested pseudo-ref:
  one report without `option refname`, followed by two explicit
  `refs/changes/...` rewrites.
- The first report keeps `refs/for/main/topic` as its effective ref while
  preserving `old-oid` and `new-oid`; the later reports preserve their explicit
  rewrite refs, object IDs, and per-report `forced-update` flag.
- The same case is covered through both direct report-status packet lines and
  sideband channel-1 receive-status bytes with progress on channel 2.
- The WordPress protocol-v1 push-response fixture/example now records the
  multi-report deployment review flow so a PHP deployment tool can surface all
  remote-created review refs without shelling out to `git push`.

## Evidence

- `php -l lanes/gitoxide/tests/PushResponseTest.php`
- `php -l lanes/gitoxide/fixtures/wordpress-protocol-v1-push-response.php`
- `php -l lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`
- `php tools/run-tests.php lanes/gitoxide/tests/PushResponseTest.php`: `1 test files, 303 assertions, 0 failures`.
- `php lanes/gitoxide/examples/wordpress-protocol-v1-push-response.php`: exited `0`.
- `php tools/run-tests.php lanes/gitoxide/tests`: `40 test files, 8378 assertions, 0 failures`.

## Dependency Closure

No new support component is needed. This reuses the existing native
packet-line reader, sideband accumulator, `PushResponse`, `PushRefStatus`, and
WordPress push-response fixture/example. It does not shell out to Git, run live
provider tests, read credentials, or require a shared support-library
activation gate.

## Non-Overlap

This verifies a distinct proc-receive multi-report shape without repeating the
accepted optional OK text, bare NG fallback, fall-through parsing, missing
expected refs, unpack-only fallback, unrequested option rejection, valueless
options, object-id diagnostic suffixes, malformed object-option tolerance,
response-end/delimiter terminators, packet-line bounds, fatal sideband errors,
smart HTTP redirect/cookie/proxy behavior, SSH receive-pack boundaries,
protocol-v2 fetch sideband parsing, pack/index behavior, reference
transactions, or object database integrity checks.
