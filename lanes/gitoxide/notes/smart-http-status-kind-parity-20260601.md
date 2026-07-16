# Smart HTTP Status Kind Parity

Micro-slice: `gitoxide-receive-pack-transport-boundary-parity-20260601T102631Z`

## Source Truth

- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/traits.rs` documents that HTTP `401` must become `std::io::ErrorKind::PermissionDenied`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/curl/remote.rs` maps `401` to `PermissionDenied`, `5xx` statuses to `ConnectionAborted`, and other non-success statuses to `Other`.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/client/blocking_io/http/reqwest/remote.rs` applies the same status-kind split for the reqwest transport.
- `/home/claude/port-libs/.upstream-cache/gitoxide/gix-transport/src/lib.rs` marks `ConnectionAborted` as spurious/retryable and marks `PermissionDenied` and `Other` as non-spurious.

## Native Movement

- Added `SmartHttpStatusException` so smart HTTP receive-pack failures expose the HTTP status, gix-style kind, and retryability while preserving `RuntimeException` compatibility.
- Added `SmartHttpReceivePackTransport::classifyHttpStatus()` with `401 -> permission_denied`, `5xx -> connection_aborted`, and all other non-success statuses as `other`.
- Updated receive-pack discovery and response status checks to throw the typed status exception.
- Extended the WordPress receive-pack fixture/example with `401`, `404`, `500`, and post-advertisement `503` status boundaries.

## Verification

- Baseline before patch: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` -> `1 test files, 1039 assertions, 0 failures`.
- Focused after patch: `php tools/run-tests.php lanes/gitoxide/tests/ReceivePackTransportTest.php` -> `1 test files, 1069 assertions, 0 failures`.
- Full lane attempt: `php tools/run-tests.php lanes/gitoxide/tests` -> blocked by `/tmp` inode exhaustion after `40 test files, 7426 assertions, 135 failures`; warnings were `No space left on device` creating `/tmp/port-libs-*` temp directories/files in unrelated reference/reflog tests.

## Non-overlap

This slice does not repeat the accepted Git-Protocol header, packet-line, proxy/cookie/noProxy/redirect, content-type, SSH, git-daemon, or send-pack receive-status clusters. It is limited to smart HTTP non-success status classification and retryability at the receive-pack transport boundary.

## Dependency Closure

No new support component is needed. The slice reuses the existing injected smart HTTP requester, native stream transport, and focused PHP test harness. The upstream Cargo workspace remains unexecuted.

## Next Task

Retry the full Gitoxide PHP lane in a clean `/tmp` environment, then continue with non-overlapping receive-pack live HTTP auth/retry propagation or SSH/auth status variants.
