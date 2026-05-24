# Independent Audit - 2026-05-24T09:14Z

Scope reviewed: `goal.md`, `progress.md`, current worktree `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, and recent Git
history. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: through 2026-05-24T09:14Z
HEAD moved during audit: b5b2331c3c1b -> 565db9db8d14 -> a4392cb2bf56 -> e08123da0741
recent history: e08123da Record integration hold status; a4392cb2 Record dependency scout tracker updates; 0b8b01a8 Record integration hold follow-up; 565db9db Record integration hold status
branch divergence: main...origin/main [ahead 795, behind 68]
tracked dirty rows: 326 -> 325 -> 326
default status rows including untracked: 16303 -> 16302 -> 16310 -> 16312 -> 16313 -> 16314
git diff --shortstat: 326 files changed, 204768 insertions(+), 27871 deletions(-) -> 325 files changed, 204739 insertions(+), 27901 deletions(-) -> 326 files changed, 205378 insertions(+), 27901 deletions(-) -> 326 files changed, 205486 insertions(+), 27872 deletions(-) -> 326 files changed, 205624 insertions(+), 27867 deletions(-) -> 326 files changed, 205729 insertions(+), 27870 deletions(-)
manifest/status JSON validation: jq empty passed for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dashboard worktree snapshot: porting.html and porting-summary.json still generated 2026-05-23 23:43:54 UTC from source 79768df0c427; both are dirty relative to HEAD
dependency backlog: dependency-backlog.json updated 2026-05-24 09:02:27 UTC with 31 rows; dashboard/summary still show 22
root run by this audit: not started
```

Required pre-root process-gate evidence:

```text
early sample pgrep -af '^php tools/run-tests\.php( |$)':
no rows

later sample pgrep -af '^php tools/run-tests\.php( |$)':
287734 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,ppid,user,stat,etime,args -p 287734:
287734 225349 claude Rs 01:16 php tools/run-tests.php lanes/syncthing/tests

final sample pgrep -af '^php tools/run-tests\.php( |$)':
311050 php tools/run-tests.php lanes/syncthing/tests
315101 php tools/run-tests.php

ps -o pid,ppid,user,stat,etime,args -p 311050,315101:
311050 225349 claude Rs 00:59 php tools/run-tests.php lanes/syncthing/tests
315101 315064 claude R 00:26 php tools/run-tests.php

final validation pgrep -af '^php tools/run-tests\.php( |$)':
no rows
```

I did not start `php tools/run-tests.php`. The exact process gate temporarily
matched active no-argument root PID `315101` owned by `claude` plus focused
Syncthing PIDs, and the checkout was not stable enough for a trustworthy
audit-owned no-argument root result even after the gate cleared: `HEAD`, default
status rows, and shortstat all moved during this audit.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:40`, `lanes/*/lane-status.json:13`, recent Git
     history.
   - Goal requirement at risk: `goal.md` requires small, reviewable slices with
     passing tests; accepted lane output must be verified, committed, and
     integrated cleanly.
   - Evidence: `HEAD` moved from audit commit `b5b2331c3c1b` through
     integration-hold commit `565db9db8d14` and dependency-scout commit
     `a4392cb2bf56` to integration-hold commit `e08123da0741` while this audit
     was reading the tree. Default status rows moved from `16303` to `16302` to
     `16310` to `16312` to `16313` to `16314`, tracked dirty rows moved from
     `326` to `325` to `326`, and shortstat moved from `326 files changed,
     204768
     insertions(+), 27871 deletions(-)` to `325 files changed, 204739
     insertions(+), 27901 deletions(-)` to `326 files changed, 205378
     insertions(+), 27901 deletions(-)` to `326 files changed, 205486
     insertions(+), 27872 deletions(-)` to `326 files changed, 205624
     insertions(+), 27867 deletions(-)` to `326 files changed, 205729
     insertions(+), 27870 deletions(-)`. Every lane still reports `pending`,
     `uncommitted`, or shared-dirty `latestCommit` state.

2. **Critical - there is no acceptable repo-wide PHP result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:40`,
     `lanes/*/lane-status.json:12`.
   - Goal requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required exact `pgrep -af '^php tools/run-tests\.php( |$)'`
     gate was initially clear, later matched focused Syncthing PID `287734`, and
     then matched focused Syncthing PID `311050` plus no-argument root PID
     `315101` (`claude`, `php tools/run-tests.php`), and finally cleared. The
     checkout also moved during the same sampling window. Lane-local green
     results and a root run started by another owner cannot substitute for one
     audit-owned serialized no-argument root result from a frozen snapshot.

3. **Critical - `porting.html` and `porting-summary.json` remain stale and dirty
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `porting-summary.json:215`, `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md` requires the dashboard to show current
     per-lane denominator, mapped tests, PHP pass/fail, phase, audit, current
     work, blocker, and commit.
   - Evidence: the worktree dashboard still claims average progress `97.7%`,
     generated `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and
     `22` dependency rows. Current `HEAD` is `e08123da0741`, and
     `dependency-backlog.json` has `31` rows (`21 candidate`, `10 deferred`).

4. **High - dashboard, manifest, and lane-status counts still disagree across
   every active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:212`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md` requires comparable upstream
     denominators, mapped upstream tests, PHP pass/fail counts, audit status,
     and latest commit per lane.
   - Evidence:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    951 / 686 / n/a                 3019              735 / 374 / 374
dolt          prose total / 613 / 401         415               inventory / 613 / 356
esbuild       2567 / 396 / 396                396               2567 / 311 / 311
gitoxide      2877 / 2877 / n/a               6871              2877 / 2751 / 5634
libsqlite     1589 / 334 / n/a                334               1589 / 286 / 286
LightningCSS  3535 / 2694 / n/a               3880              3532 / 1732 / 2197
markerPDF     376 / 327 / 464                 463               330 / 280 / 416
pandoc        2276 / 1754 / n/a               340               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   221               55 / 55 / 190
rclone        1601 / 859 / 859                859               1601 / 698 / 698
readability   1984 / 1984 / 243               242               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7229              658 / 658 / 4579
```

5. **High - Dolt still has a non-machine-checkable denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2489`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2495`,
     `lanes/dolt/lane-status.json:6`, `lanes/dolt/lane-status.json:13`.
   - Goal requirement at risk: `goal.md` requires a real upstream benchmark
     denominator and durable coordination fields.
   - Evidence: Dolt's later `benchmarkDenominator.total` is a long prose
     evidence paragraph, not a numeric denominator. Dolt also records manifest
     `phpBehaviorTests = 401`, lane status `phpPass = 415`, and dashboard
     `356 pass`.

6. **High - near-complete progress percentages overstate accepted upstream and
   root parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/dolt/lane-status.json:4`, `lanes/gitoxide/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`, `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`, `porting.html:32`.
   - Goal requirement at risk: `goal.md` says passing PHP tests are not enough,
     upstream tests are the source of truth where possible, and hard features
     must be marked as blockers or future slices.
   - Evidence: most active lanes claim `98` or `99` percent while full
     Difftastic Cargo, Gitoxide Cargo workspace, Pandoc Haskell, Syncthing
     `go test ./...`, broad Dolt Go/BATS, and full rclone provider/mount parity
     remain unexecuted or explicitly outside current evidence. Root aggregate
     verification is pending for all handoffs.

7. **High - essential optional-library coverage remains backlog-only, and the
   backlog is expanding faster than accepted support ports.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:5`,
     `dependency-backlog.json:7`, `dependency-backlog.json:25`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `porting-summary.json:215`, `porting.html:75`.
   - Goal requirement at risk: support libraries need the same granularity as
     lanes: a bounded native PHP component, an activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much of
     the upstream/spec suite as can actually run.
   - Evidence: `dependency-backlog.json` now has `31` rows, including rich gaps
     for ZIP/package, XML/HTML5 DOM, WebDAV, URL percent-encoding, DOCX/ODT/EPUB,
     PDF text/render/OCR/layout, JSON/JSON5, tree-sitter grammar subsets,
     sequence diff/merge, protobuf wire format, and SQL expression semantics.
     All rows remain `candidate` or `deferred`; none is an active support-library
     manifest with PHP pass/fail evidence. The dashboard still publishes only
     `22` rows.

8. **High - rclone's WebDAV/provider/compression expansion is too broad to
   count as shared dependency progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:9`, `lanes/rclone/lane-status.json:13`,
     `dependency-backlog.json:45`.
   - Goal requirement at risk: dependency expansion must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators.
   - Evidence: rclone now carries a broad WebDAV surface: PROPFIND/PROPPATCH,
     LOCK/If, COPY/MOVE, gzip, serve middleware, auth-proxy, directory
     templates, URL decoding, held locks, partial failure behavior, and local
     x/net runner evidence. It is useful lane-local evidence, but it is not
     accepted shared WebDAV/XML/archive progress until a bounded support-library
     component has its own manifest, gate, spec/upstream denominator, malformed
     cases, and PHP pass/fail evidence.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md` forbids counting wrappers, bridge
     calls, shell-outs, external converter/runtime execution, and whole
     applications as native port progress.
   - Evidence: markerPDF has real native PDF text/filter/font slices, but status
     still carries marker_app, marker_server, convert.py, chunk_convert,
     pdftext execution, Streamlit, FastAPI/Uvicorn, Poetry, Torch/Surya/Texify,
     Nougat, OCRMyPDF/Tesseract, Ghostscript, Pandoc/XeLaTeX, and GitHub
     Actions/publishing boundaries. These must remain preflight or supplied
     oracle metadata unless bounded native PHP components own the behavior.

10. **Medium - manifests retain stale internal PHP evidence against current
    lane-status files.**
    - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2495`,
      `lanes/dolt/lane-status.json:6`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/markerpdf/lane-status.json:6`.
    - Goal requirement at risk: `goal.md` requires durable coordination files
      with honest mapped tests and PHP pass/fail evidence.
    - Evidence: Dolt manifest PHP count is `401` while lane status is `415`;
      markerPDF manifest is `464` while lane status is `463`.

11. **Medium - Gitoxide shell-outs remain acceptable only as explicit oracle
    tooling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/gitoxide/tests/FetchResponseTest.php`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php`,
      `lanes/gitoxide/tests/GitUrlTest.php`.
    - Goal requirement at risk: `goal.md` says generated fixtures, bridge calls,
      and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide still records caller-supplied shell-backed filter,
      askpass, transport, and Git diagnostic boundaries. Those can remain
      labeled oracle tooling, but they must not inflate native parity or accepted
      implementation progress.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops; wait for two stable `HEAD`, dirty-count, and process-gate polls;
accept or reject one lane batch at a time; normalize manifest/status numeric
fields and commit fields; run focused verification plus `git diff --check`;
regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit; then run one serialized no-argument `php tools/run-tests.php`
only if the exact process gate remains empty and the tree stays stable.
