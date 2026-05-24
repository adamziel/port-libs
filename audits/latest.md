# Independent Audit - 2026-05-24T09:50Z

Scope reviewed: `goal.md`, `progress.md`, current worktree
`porting.html`, `porting-summary.json`, every root
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, current `lanes/*/lane-status.json`,
`dependency-backlog.json`, `audits/integration-status.md`, and recent Git
history. I did not edit lane implementation files, launch agents or tmux
sessions, push, read secrets, inspect process environments, credential stores,
provider configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T09:49:25Z, 2026-05-24T09:49:58Z, 2026-05-24T09:52:30Z, plus earlier audit sampling in this run
HEAD moved during audit: 5770237ecbc2 -> 758418e93858
recent history: 758418e9 Record integration hold status; 5770237e Refresh independent audit status; 989bdc9e Refine essential support-library tracker; 1c1cb23e Record integration hold status
tracked dirty rows: 326 -> 325 -> 325
default status rows including untracked: 16488 -> 16489 -> 16490
git diff --shortstat: 326 files changed, 209192 insertions(+), 27810 deletions(-) -> 325 files changed, 209300 insertions(+), 27817 deletions(-) -> 325 files changed, 209454 insertions(+), 27884 deletions(-)
dashboard worktree snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
dependency backlog: dependency-backlog.json has 32 rows (22 candidate, 10 deferred); dashboard still shows 22 rows
root run by this audit: not started; the checkout failed the stability gate while HEAD/status/shortstat moved and all lane handoffs remain pending or uncommitted; final validation then found an externally started no-argument root harness
```

Required pre-root process-gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:49:25Z:
no rows

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:49:58Z:
no rows

final validation pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T09:52:30Z:
844341 php tools/run-tests.php
846678 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php
848724 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,ppid,user,stat,etime,args -p 844341,846678,848724:
844341 844279 claude R+ 00:30 php tools/run-tests.php
846678 846531 claude R+ 00:23 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterSchedulerTest.php lanes/syncthing/tests/ProgressEmitterTest.php lanes/syncthing/tests/ProtocolValidationTest.php lanes/syncthing/tests/PullDbUpdaterTest.php lanes/syncthing/tests/PullFinisherTest.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php
848724 804679 claude Rs 00:14 php tools/run-tests.php lanes/syncthing/tests
```

I did not start `php tools/run-tests.php`. The exact process gate was empty
in the early recorded samples, but the tree was still moving. Final
validation found externally started no-argument root PID `844341` owned by
`claude`, plus focused Syncthing harness PIDs `846678` and `848724`, so no
duplicate was started. `audits/integration-status.md` also reports an
externally produced no-argument root result ending 2026-05-24T09:45:29Z with
204 files, 23,682 assertions, and 0 failures; that is useful telemetry, but it
is not an audit-owned serialized result from a frozen snapshot.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:41`, `audits/integration-status.md:1`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:29` requires small, reviewable slices
     with passing tests; `goal.md:48` requires finished agent work to be
     verified, committed, integrated, and cleaned up.
   - Evidence: `HEAD` moved during this audit from `5770237ecbc2` to
     `758418e93858`. Default status rows moved `16488 -> 16489 -> 16490`,
     tracked dirty rows moved `326 -> 325 -> 325`, and shortstat moved from
     `326 files changed, 209192 insertions(+), 27810 deletions(-)` to
     `325 files changed, 209454 insertions(+), 27884 deletions(-)`. Every
     lane status still records `latestCommit` as pending, uncommitted, not
     committed, or a stale commit reference; for example
     `lanes/dolt/lane-status.json:13`, `lanes/esbuild/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:13`.

2. **Critical - there is still no acceptable repo-wide PHP result for this
   exact snapshot.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:34`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and static checks with honest failure recording.
   - Evidence: the required exact process gate returned no rows at
     09:49:25Z and 09:49:58Z, but the checkout failed stability while
     `HEAD`, status rows, and shortstat changed. Final validation then found
     externally started no-argument root PID `844341` owned by `claude`, plus
     focused Syncthing PIDs `846678` and `848724`; no duplicate was started.
     The external root result recorded in `audits/integration-status.md:34`
     was produced by another loop against a moving dirty tree and was
     explicitly not accepted as the integration gate. The lane statuses
     themselves keep root verification as pending, including
     `lanes/gitoxide/lane-status.json:10`, `lanes/rclone/lane-status.json:10`,
     `lanes/markerpdf/lane-status.json:10`, and
     `lanes/esbuild/lane-status.json:10`.

3. **Critical - `porting.html` and `porting-summary.json` remain stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `porting-summary.json:218`, `dependency-backlog.json:3`.
   - Goal requirement at risk: `goal.md:3` requires current coordination
     files; `goal.md:45` requires the dashboard to show current per-lane
     denominator, mapped tests, PHP pass/fail, phase, audit, work, blocker,
     and commit.
   - Evidence: the dashboard still claims average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and 22
     dependency rows. Current `HEAD` is `758418e93858`, and
     `dependency-backlog.json` was updated `2026-05-24 09:35:12 UTC` with 32
     rows.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:215`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and `goal.md:45`
     require comparable upstream denominators, mapped upstream tests, PHP
     pass/fail counts, audit status, and latest commit per lane.
   - Evidence:

```text
lane          manifest total/mapped/php       lane-status php   dashboard total/mapped/php
difftastic    969 / 709 / n/a                 3053              735 / 374 / 374
dolt          prose total / 613 / 401         417               inventory / 613 / 356
esbuild       2567 / 406 / 406                406               2567 / 311 / 311
gitoxide      2877 / 2877 / 6910              6910              2877 / 2751 / 5634
libsqlite     1589 / 336 / n/a                336               1589 / 286 / 286
LightningCSS  3535 / 2706 / n/a               3893              3532 / 1732 / 2197
markerPDF     379 / 330 / 467                 467               330 / 280 / 416
pandoc        2276 / 1778 / n/a               344               2276 / 1061 / 278
quadrable     55 / 55 / n/a                   224               55 / 55 / 190
rclone        1601 / 867 / 867                867               1601 / 698 / 698
readability   1984 / 1984 / 246               246               1984 / 1984 / 204
syncthing     658 / 658 / n/a                 7314              658 / 658 / 4579
```

5. **High - Dolt still has a non-machine-checkable denominator and stale PHP
   counts.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2499`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2505`,
     `lanes/dolt/lane-status.json:5`,
     `lanes/dolt/lane-status.json:6`.
   - Goal requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:29` requires accepted slices to be reviewable and
     passing; `goal.md:45` requires dashboard counts to be accurate.
   - Evidence: `benchmarkDenominator.total` is a prose BIN evidence paragraph
     at line 2499, not a numeric denominator. The manifest says
     `mapped = 613` and `phpBehaviorTests = 401`; lane status says
     `phpPass = 417`; dashboard still says `356 pass` and `inventory` total.

6. **High - near-complete percentages overstate accepted upstream and root
   parity.**
   - Paths: `lanes/difftastic/lane-status.json:4`,
     `lanes/dolt/lane-status.json:4`, `lanes/pandoc/lane-status.json:4`,
     `lanes/rclone/lane-status.json:4`,
     `lanes/syncthing/lane-status.json:4`, `porting.html:56` through
     `porting.html:67`.
   - Goal requirement at risk: `goal.md:35` says passing tests are not enough;
     `goal.md:37` says upstream tests are the source of truth where possible;
     `goal.md:40` requires hard gaps to be blockers or future slices.
   - Evidence: lanes continue to claim 98-99 percent while full Difftastic
     Cargo, Gitoxide Cargo workspace, Pandoc Haskell, Syncthing `go test
     ./...`, broad Dolt Go/BATS, broad rclone provider/mount parity,
     release-extra esbuild `make test-all`, and a serialized no-argument root
     PHP result from the accepted snapshot remain unexecuted or explicitly
     pending.

7. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:5`, `dependency-backlog.json:45`,
     `dependency-backlog.json:61`, `dependency-backlog.json:359`,
     `dependency-backlog.json:381`, `dependency-backlog.json:470`,
     `dependency-backlog.json:530`, `dependency-backlog.json:551`,
     `porting.html:75`.
   - Goal requirement at risk: support libraries need the same granularity as
     lanes: bounded native PHP component, activation gate,
     dependency-specific upstream/spec denominator, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases where relevant, and as much
     upstream/spec coverage as can honestly run.
   - Evidence: all 32 dependency rows are still `candidate` or `deferred`;
     none is an active support-library manifest with PHP pass/fail evidence.
     Rich-function gaps remain for WebDAV, URL percent encoding, JSON/JSON5
     documents, Source Map v3, protobuf wire, SQL expression semantics,
     archive/compression streams, ZIP/package containers, XML/HTML, Unicode,
     and charset handling. `porting.html` still publishes only 22 older rows.

8. **High - dependency expansion is happening inside lanes instead of bounded
   shared support ports.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1363` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1366`,
     `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/esbuild/lane-status.json:11`,
     `dependency-backlog.json:45`, `dependency-backlog.json:61`,
     `dependency-backlog.json:381`, `dependency-backlog.json:551`.
   - Goal requirement at risk: optional-library work must be bounded, gated,
     tested, shared where appropriate, and backed by dependency-specific
     denominators.
   - Evidence: rclone carries lane-local WebDAV behavior across x/net COPY
     ordering, filename/XML escaping, PROPFIND/PROPPATCH/LOCK/If, gzip, VFS
     ZIP/serve surfaces, and provider metadata. Esbuild now has a lane-local
     Source Map v3 parser and explicitly notes future reuse opportunity. These
     should not count as shared dependency progress until separate support
     manifests with activation gates, upstream/spec denominators, malformed
     cases, and PHP pass/fail evidence exist.

9. **High - markerPDF still mixes native PDF evidence with external/runtime
   application boundaries.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:504`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:505`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:940` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1075`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, shell-outs, external converter/runtime execution,
     and whole applications as native port progress.
   - Evidence: markerPDF has useful native PDF text/filter/font slices, but
     the manifest/status still enumerate plan-only or supplied boundaries for
     Streamlit, FastAPI/Uvicorn, multiprocessing, chunk_convert shell
     lifecycle, pdftext, pypdfium/PIL, Torch/Surya/Texify/Nougat, OCRMyPDF,
     Tesseract, Ghostscript, Pandoc/XeLaTeX, Poetry, and GitHub workflow
     surfaces. Those must remain preflight/oracle metadata unless a bounded
     native PHP component owns and tests the behavior.

10. **Medium - Gitoxide shell-outs remain acceptable only as explicit oracle or
    caller-supplied tooling.**
    - Paths: `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1570`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1587`,
      `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:1588`,
      `lanes/gitoxide/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:30` says generated fixtures, bridge
      calls, and shell-outs to upstream binaries must not count as native
      implementation progress.
    - Evidence: Gitoxide correctly marks shell-backed filter execution as not
      implemented or counted, and some Git probes are described as bounded
      oracle tooling. Keep that distinction explicit; shell-backed filters,
      askpass, SSH, external merge drivers, live network adapters, and
      credential state must not inflate native parity.

## Next Best Intervention

Freeze active writers, status/dashboard publishers, focused lane harnesses,
root loops, and Dolt runner shards. Require two stable polls of `HEAD`, tracked
dirty rows, untracked-inclusive status rows, shortstat, exact PHP process
gate, dependency/dashboard counts, and relevant log mtimes. Accept or reject
one lane batch at a time, normalizing manifest/status/dashboard numeric fields
before publication. Split optional dependency work into manifest-backed
bounded support-library ports only behind real base-lane gates. Run focused
verification plus `git diff --check`; regenerate `progress.md`,
`porting.html`, and `porting-summary.json` from the accepted commit; then run
one serialized no-argument `php tools/run-tests.php` only if the exact process
gate stays empty on that frozen snapshot.
