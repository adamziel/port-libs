# Support Library QR Activation Review - 2026-05-24T10:51:38Z

Scope read: `goal.md`, `progress.md`, `dependency-backlog.json`,
`audits/support-library-direction-nudge-20260524T103902Z.md`,
`lanes/syncthing/lane-status.json`,
`lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`,
`lanes/syncthing/notes/wordpress-scenarios.md`, and recent
`port-syncthing` / `port-integrator` log tails only enough to assess QR
evidence and acceptance state.

No lane implementation files were edited. No lane features, staging, commit,
push, reset, revert, publish, root tests, or secret inspection were attempted.

## Decision

`qr-code-matrix-core` is `blocked`, not `active`.

Reason: the current Syncthing `/qr/` evidence is lane-local and useful, but it
has not been accepted by the integrator/evaluator from a frozen snapshot.
Support rows become active only when a base lane is accepted green enough for
the next rich-function slice or is accepted-blocked on that component. The QR
lane status instead records the QR implementation as `latestCommit: pending`,
with root/integrator verification still pending.

The row remains bounded to QR matrix/image contracts only:

- in scope: fixture-backed payload encoding, version/error-correction choice,
  mask/matrix output, SVG/PNG-or-supplied-image contracts, MIME metadata, and
  redacted WordPress summaries;
- out of scope: QR shell-outs, scanner apps, browser/mobile pairing apps, live
  pairing, camera access, and raw or secret-bearing device IDs.

## Evidence Inspected

- `progress.md`: support policy says rows activate only behind a concrete base
  lane gate; the 10:42 UTC note added `qr-code-matrix-core` as inactive; the
  current audit snapshot still says no lane implementation output was accepted
  and support-library coverage has zero active bounded ports.
- `dependency-backlog.json`: QR row had `activationGate:
  syncthing-qr-route-next`, `status: candidate`, and explicit no-shell-out,
  no-scanner-app, no-live-pairing, and no-secret exclusions.
- `audits/support-library-direction-nudge-20260524T103902Z.md`: QR was added as
  an inactive row; exact blocker wording says Syncthing QR route-body progress
  requires `qr-code-matrix-core` before support-library credit and excludes QR
  shell-outs, scanner apps, live pairing, and secret-bearing device IDs.
- `lanes/syncthing/lane-status.json`: lane-local QR reports targeted upstream
  reads, native PNG generation, sanitized WordPress output, PHP lints, focused
  QR PHP passing 1 file / 86 assertions, adjacent PHP passing 4 files / 226
  assertions, and full lane PHP passing 114 files / 7518 assertions. The same
  file records no direct upstream `/qr/` route test, no fresh Go runner for the
  QR pass, no lane-worker root harness, `latestCommit: pending`, and root
  verification pending for the supervisor/integrator.
- `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`: QR inventory records 10 direct
  files, 1 route method, 4 qart component files, 6 mapped PHP tests, 86 mapped
  assertions, and one WordPress example; it also says full upstream `go test
  ./...` remains unexecuted.
- `lanes/syncthing/notes/wordpress-scenarios.md`: QR scenario documents
  sanitized aggregate output without raw QR text, PNG bytes, device IDs, API
  keys, sessions, credentials, or secrets.
- Recent `port-integrator` logs: integration holds at 10:45 and 10:48 UTC state
  that no lane implementation output was integrated, Syncthing was skipped as
  an active lane, no no-argument root harness was accepted by those passes, and
  recent lane evidence is not accepted integration evidence while the shared
  checkout is moving.
- Recent dashboard/publisher log tail: a temp-worktree root harness passed, but
  source moved before publication; that does not accept the dirty Syncthing QR
  slice in this workspace.

## Required Blocker Wording

Integrator/evaluator should require this wording before accepting Syncthing QR
as support-library progress:

`Acceptance blocker: Syncthing QR route-body progress requires qr-code-matrix-core before support-library credit. The lane-local /qr/ implementation may be reviewed as Syncthing evidence, but qr-code-matrix-core must not be activated or credited until the Syncthing QR slice is accepted from a frozen snapshot and the QR row records a QR-specific upstream/spec denominator or bounded public vector set, mapped Syncthing /qr fixtures, PHP pass/fail evidence, malformed/error cases, and explicit exclusions for qrencode/libqrencode/zbar shell-outs, scanner apps, browser/mobile pairing apps, live pairing, camera access, and raw or secret-bearing device IDs.`

## Follow-Up

Unresolved activation blocker: accept or reject the Syncthing `/qr/` slice from
a frozen snapshot. If accepted and the next required QR work belongs in a shared
support library rather than the Syncthing lane, then activate
`qr-code-matrix-core`; otherwise keep it blocked/inactive and do not count QR
support-library progress.
