# Independent Audit - 2026-05-24T09:33Z

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
UTC samples: 2026-05-24T09:27:12Z, 2026-05-24T09:27:45Z, 2026-05-24T09:31:16Z, and 2026-05-24T09:33:04Z
HEAD moved during audit: e67ad5540e3e -> ac6ad836abc4
recent history: ac6ad836 Record integration hold status; e67ad554 Track Pandoc syntax highlighting dependency; 141600cc Refresh independent audit status; 948f5f79 Record integration hold status
tracked dirty rows: 324 -> 324 -> 326 -> 327
default status rows including untracked: 16419 -> 16419 -> 16488 -> 16489
git diff --shortstat: 324 files changed, 206952 insertions(+), 27874 deletions(-) -> 324 files changed, 206954 insertions(+), 27874 deletions(-) -> 326 files changed, 207253 insertions(+), 27992 deletions(-) -> 327 files changed, 207439 insertions(+), 27995 deletions(-)
manifest/status JSON validation: jq empty passed for 12 root lane manifests, 12 lane-status files, porting-summary.json, and dependency-backlog.json
dashboard worktree snapshot: porting.html and porting-summary.json still generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json updated 2026-05-24 09:19:25 UTC with 32 rows (22 candidate, 10 deferred)
root run by this audit: not started; another no-argument root harness became active during post-edit validation, and the final process gate still matched a focused Syncthing PHP harness
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:27:12Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:27:45Z:
no rows

post-edit pgrep -af '^php tools/run-tests\.php( |$)':
553892 php tools/run-tests.php
554985 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php lanes/syncthing/tests/BepConnectionDiagnosticsTest.php lanes/syncthing/tests/BepConnectionLifecycleTest.php lanes/syncthing/tests/BepFrameStreamTest.php lanes/syncthing/tests/BepKeepaliveTest.php lanes/syncthing/tests/BepSessionTest.php lanes/syncthing/tests/BepWireTest.php lanes/syncthing/tests/BlockDiffTest.php lanes/syncthing/tests/BlockListTest.php lanes/syncthing/tests/BlockPullReordererTest.php lanes/syncthing/tests/ClusterConfigAutoAcceptorTest.php lanes/syncthing/tests/ClusterConfigIntroducerTest.php lanes/syncthing/tests/ClusterPendingTest.php lanes/syncthing/tests/ConfigDefaultDeviceTest.php lanes/syncthing/tests/ConfigDefaultFolderTest.php lanes/syncthing/tests/ConfigDefaultIgnoresTest.php
555312 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php
555810 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests

ps -o pid,ppid,user,stat,etime,args -p 553892,554985,555312,555810 at 2026-05-24T09:31:16Z:
553892 553846 claude R+ 00:33 php tools/run-tests.php
555312 555202 claude R+ 00:28 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php ...

final pre-commit pgrep -af '^php tools/run-tests\.php( |$)':
588359 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,ppid,user,stat,etime,args -p 588359 at 2026-05-24T09:33:04Z:
588359 524761 claude Rs 00:51 php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The exact pre-root gate was clear in
early samples, then another no-argument root harness became active during
post-edit validation. I did not start a duplicate. The final process-gate
sample no longer showed that root PID, but it still matched a focused Syncthing
PHP harness. The checkout also failed the stability gate: `HEAD`, dirty counts,
and shortstat all moved while the audit was sampling.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `lanes/*/lane-status.json`, recent Git history.
   - Goal requirement at risk: `goal.md:29` requires small, reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, and integrated cleanly; `goal.md:49` requires
     periodic repo-wide verification.
   - Evidence: `HEAD` moved during this audit from `e67ad5540e3e` to
     `ac6ad836abc4`. The worktree moved from 324 tracked dirty rows and 16,419
     default status rows to 327 tracked dirty rows and 16,489 default status
     rows. The shortstat changed from `324 files changed, 206952
     insertions(+), 27874 deletions(-)` through `324 files changed, 206954
     insertions(+), 27874 deletions(-)` and `326 files changed, 207253
     insertions(+), 27992 deletions(-)` to `327 files changed, 207439
     insertions(+), 27995 deletions(-)` during this audit.
     Current lane statuses still use `pending`, `uncommitted`, or shared dirty
     worktree commit fields across active lanes.

2. **Critical - there is no acceptable repo-wide PHP result for this snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires repo-wide tests and
     static checks to be run periodically and recorded honestly.
   - Evidence: the exact process gate returned no rows in the first two
     samples, but post-edit validation matched active no-argument root PID
     `553892` owned by `claude` plus focused PHP shards. I did not start a
     duplicate; the final process-gate sample still matched focused Syncthing
     PID `588359` owned by `claude`. Focused lane-local green checks and an
     externally active root run do not establish an audit-owned serialized root
     result for `ac6ad836abc4` plus the current dirty worktree.

3. **Critical - `porting.html` and `porting-summary.json` remain stale dirty
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md:45` requires the dashboard to show the
     current per-lane denominator, mapped tests, PHP pass/fail, phase, audit,
     current work, blocker, and commit.
   - Evidence: the dashboard still claims average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and `22`
     dependency rows. Current `HEAD` is `ac6ad836abc4`, and
     `dependency-backlog.json` has `32` rows.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:59`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require comparable upstream denominators, mapped upstream tests, PHP
     pass/fail counts, audit status, and latest commit per lane.
   - Evidence:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    963 / 698 / n/a                 3031              735 / 374 / 374
dolt          prose total / 613 / 401         416               inventory / 613 / 356
esbuild       2567 / 397 / 396                397               2567 / 311 / 311
gitoxide      2877 / 2877 / n/a               6890              2877 / 2751 / 5634
libsqlite     1589 / 335 / n/a                335               1589 / 286 / 286
LightningCSS  3535 / 2697 / n/a               3883              3532 / 1732 / 2197
markerPDF     377 / 328 / 465                 465               330 / 280 / 416
pandoc        2276 / 1768 / n/a               342               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   222               55 / 55 / 190
rclone        1601 / 861 / 863                863               1601 / 698 / 698
readability   1984 / 1984 / 244               244               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7258              658 / 658 / 4579
```

5. **High - Dolt still has a non-machine-checkable and stale denominator.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2496`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2502`,
     `lanes/dolt/lane-status.json`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator, and `goal.md:29` requires small reviewable accepted slices.
   - Evidence: `benchmarkDenominator.total` is still a prose paragraph from
     the older `2026-05-24 09:05` BIN slice, while `latestSlice.timestampUtc`
     is now `2026-05-24 09:25` for REGEXP/RLIKE. The lane reports manifest
     `phpBehaviorTests = 401`, lane-status `phpPass = 416`, and dashboard `356
     pass`.

6. **High - near-complete progress percentages overstate accepted upstream and
   root parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/gitoxide/lane-status.json:4`,
     `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`,
     `porting.html:56` through `porting.html:67`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` say passing
     PHP tests are not enough, upstream tests are the source of truth where
     possible, and hard features must be marked as blockers or future slices.
   - Evidence: multiple lanes claim `98` or `99` percent while full Difftastic
     Cargo, Gitoxide Cargo workspace, Pandoc Haskell, Syncthing `go test ./...`,
     broad Dolt Go/BATS, and full rclone provider/mount parity remain
     unexecuted, outside scope, or explicitly pending. Root aggregate
     verification also remains pending for the dirty handoffs.

7. **High - essential optional-library coverage remains backlog-only despite
   rich-function gaps.**
   - Paths: `dependency-backlog.json:3`, `dependency-backlog.json:7`,
     `dependency-backlog.json:25`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `dependency-backlog.json:162`,
     `dependency-backlog.json:378`, `dependency-backlog.json:467`,
     `dependency-backlog.json:527`, `dependency-backlog.json:548`,
     `porting.html:75`.
   - Goal requirement at risk: support libraries need the same granularity as
     lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much of
     the upstream/spec suite as can actually run.
   - Evidence: `dependency-backlog.json` has 32 rows, all `candidate` or
     `deferred`; no row is an active support-library manifest with PHP pass/fail
     evidence. Rich Pandoc/markerPDF/rclone/esbuild/LightningCSS/Syncthing gaps
     still depend on bounded ports such as ZIP/package, XML/HTML, WebDAV,
     URL-percent-encoding, Pandoc syntax highlighting, Source Map v3, protobuf
     wire, SQL expression semantics, and archive/compression streams. The
     dashboard still publishes only 22 dependency rows.

8. **High - rclone's WebDAV/provider/compression expansion is too broad to
   count as shared dependency progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `dependency-backlog.json:45` through `dependency-backlog.json:55`.
   - Goal requirement at risk: dependency expansion must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators.
   - Evidence: rclone carries lane-local WebDAV behavior across PROPFIND,
     PROPPATCH, LOCK/If, COPY/MOVE, gzip, middleware, source/destination failure
     ordering, OneDrive metadata and permissions, and provider upload planning.
     That is not accepted shared WebDAV/XML/archive/provider progress until a
     bounded support library has its own manifest, gate, upstream/spec
     denominator, malformed cases, and PHP pass/fail evidence.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, shell-outs, external converter/runtime execution,
     and whole applications as native port progress.
   - Evidence: markerPDF has useful native PDF text/filter/font slices, but the
     lane status still carries plan-only or runtime boundaries for
     `marker_app`, `marker_server`, `convert.py`, `chunk_convert`, `pdftext`,
     Streamlit, FastAPI/Uvicorn, Poetry, Torch/Surya/Texify, Nougat,
     OCRMyPDF/Tesseract, Ghostscript, Pandoc/XeLaTeX, and GitHub Actions. These
     must remain preflight or supplied oracle metadata unless bounded native PHP
     components own the behavior.

10. **Medium - Gitoxide shell-outs remain acceptable only as explicit oracle
    tooling.**
    - Paths: `lanes/gitoxide/lane-status.json:12`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1573`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1574`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1576`.
    - Goal requirement at risk: `goal.md:30` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide explicitly says shell-backed filter process launch is
      not implemented or counted, but the lane still tracks shell-backed filter,
      askpass, SSH, external driver, URL, and transport surfaces. They can
      remain labeled oracle/tooling boundaries, but must not inflate native
      parity or accepted implementation progress.

## Next Best Intervention

Freeze active writers, dashboard/status publishers, focused lane harnesses, and
root loops; wait for two stable `HEAD`, dirty-count, shortstat, and process-gate
polls; accept or reject one lane batch at a time; normalize manifest/status
numeric fields, especially Dolt's denominator and commit fields; split optional
dependency work into manifest-backed bounded support-library ports only behind
real base-lane gates; run focused verification plus `git diff --check`;
regenerate `progress.md`, `porting.html`, and `porting-summary.json` from the
accepted commit; then run one serialized no-argument `php tools/run-tests.php`
only if the exact process gate remains empty and the tree stays stable.
