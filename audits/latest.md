# Independent Audit - 2026-05-24T05:43Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, recent Git history, and root-runner process
state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, shell-outs, whole applications,
external converter wrappers, and hidden process launchers are treated as
non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T05:42:54Z, 2026-05-24T05:43:31Z, and post-edit validation through 2026-05-24T05:48:49Z
HEAD observed during audit: eb9b88d7edc2 -> c9abf0c4afb5 -> 99eb12fe9428
recent commits: 99eb12fe Record integration hold status; c9abf0c4 Record integration hold status; eb9b88d7 Refresh independent audit status
branch divergence: main...origin/main [ahead 723, behind 68]
tracked dirty rows: 312 -> 312 -> 314
default status rows including untracked: 13685 -> 13685 -> 13748
git diff --shortstat during audit: 312 files changed, 172448 insertions(+), 22382 deletions(-) -> 312 files changed, 172464 insertions(+), 22382 deletions(-) -> 314 files changed, 173056 insertions(+), 22492 deletions(-)
manifest/status JSON validation: jq empty passed for all lane manifests, lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' before root-run decision:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:42:54Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:43:31Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' during pre-commit validation:
1572207 php tools/run-tests.php lanes/dolt/tests

owner sample for PID 1572207:
<process exited before owner sampling; ps returned no process row>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T05:47:09Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at final pre-commit validation:
1584339 php tools/run-tests.php
1584812 php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php lanes/syncthing/tests/SentDownloadStateTest.php lanes/syncthing/tests/ServiceDeviceIdTest.php lanes/syncthing/tests/ServiceLanguageTest.php lanes/syncthing/tests/ServiceMapTest.php lanes/syncthing/tests/ServiceRandomStringTest.php
1585380 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests

owner sample:
1584339 claude 1584295 00:29 R+ php tools/run-tests.php
1584812 claude 1584721 00:26 R+ php tools/run-tests.php lanes/syncthing/tests/PullItemUpdaterTest.php lanes/syncthing/tests/PullJobQueueTest.php lanes/syncthing/tests/PullScannerTest.php lanes/syncthing/tests/PullTemporaryFileTest.php lanes/syncthing/tests/PullWorkPlanTest.php lanes/syncthing/tests/ReceiveEncryptedBepConnectionTest.php lanes/syncthing/tests/ReceiveEncryptedBepModelTest.php lanes/syncthing/tests/ReceiveEncryptedTest.php lanes/syncthing/tests/RemoteDownloadProgressTrackerTest.php lanes/syncthing/tests/RequestExchangeTest.php lanes/syncthing/tests/RequestServerTest.php lanes/syncthing/tests/SentDownloadStateTest.php lanes/syncthing/tests/ServiceDeviceIdTest.php lanes/syncthing/tests/ServiceLanguageTest.php lanes/syncthing/tests/ServiceMapTest.php lanes/syncthing/tests/ServiceRandomStringTest.php
1585380 exited before owner sampling

post-commit handoff check:
1584339 php tools/run-tests.php
1597046 php tools/run-tests.php lanes/syncthing/tests
1600997 php tools/run-tests.php lanes/quadrable/tests

post-commit owner sample:
1597046 claude 1529298 00:34 Rs php tools/run-tests.php lanes/syncthing/tests
1584339 and 1600997 exited before owner sampling
```

I did not start `php tools/run-tests.php`. The exact process gate was clear, but
the checkout was not stable enough: `HEAD` moved during review and shortstat
changed across the stability samples. Pre-commit validation then briefly found a
focused Dolt PHP harness, which exited before owner sampling. Final pre-commit
validation found active no-argument root PID `1584339` and focused Syncthing PID
`1584812`, both owned by `claude`; focused rclone/Syncthing PID `1585380`
exited before owner sampling. A post-commit handoff check then found focused
Syncthing PID `1597046` owned by `claude`, while no-argument root PID `1584339`
and focused Quadrable PID `1600997` had exited before owner sampling. The latest
integration-status commits also record a separate capacity-helper dirty-root run
from 2026-05-24T05:36:51Z that exited 1 after 359 test files and 49073
assertions with one rclone missing example include, plus a later moving-tree
sample where exact no-argument root PID `1548832` and focused Syncthing PID
`1549090` were active. Those results are diagnostic only because they did not
run from an accepted frozen snapshot.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `audits/latest.md`, `audits/integration-status.md`,
     current Git status, and `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires small reviewable slices, verified
     commits, and a stable visible baseline for every lane.
   - Evidence: `HEAD` moved during this audit from `eb9b88d7edc2` through
     `c9abf0c4afb5` to `99eb12fe9428`; recent history is still dominated by
     audit/status refresh commits; the branch is `ahead 723, behind 68`;
     tracked dirty rows moved `312 -> 312 -> 314`; default status rows moved
     `13685 -> 13685 -> 13748`; and shortstat moved to `314 files changed,
     173056 insertions(+), 22492 deletions(-)`. That is active shared output,
     not an accepted lane handoff.

2. **Critical - there is no coherent root-harness result for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md`,
     `progress.md`, and `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires periodic repo-wide tests/static
     checks and honest failure recording.
   - Evidence: this audit's exact duplicate-run gate returned no rows, but the
     source snapshot moved, so an audit-owned root run would not represent a
     frozen acceptance point. Pre-commit validation briefly matched focused
     Dolt PID `1572207` (`php tools/run-tests.php lanes/dolt/tests`), which
     exited before owner sampling, and final pre-commit validation matched
     active no-argument root PID `1584339` plus focused Syncthing PID `1584812`,
     both owned by `claude`. `audits/integration-status.md` records a separate
     dirty moving-tree root run at 2026-05-24T05:36:51Z that exited 1 with one
     rclone include failure after 359 files and 49073 assertions, and a later
     moving-tree sample with active no-argument root PID `1548832`.
     Focused lane-green claims, active in-flight helper output, and dirty-root
     diagnostics cannot substitute for one completed serialized no-argument
     root result from a frozen source snapshot.

3. **Critical - `porting.html` and `porting-summary.json` remain stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:56` through `porting.html:67`, and
     `porting-summary.json`.
   - Requirement at risk: `goal.md` requires current per-lane benchmark source,
     upstream denominator, mapped tests, PHP pass/fail, WordPress scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: dashboard artifacts still publish generated time
     `2026-05-23 23:43:54 UTC` and source snapshot `main 79768df0c427`, while
     current `HEAD` is `99eb12fe9428`. Commit cells still contain fragments
     such as `pending`, `not com`, `uncommi`, and `HEAD 8d`, not accepted
     commit identifiers.

4. **High - manifest, lane-status, and dashboard counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires real upstream denominators and
     reliable coordination status.
   - Evidence: current manifests/statuses report values that no longer match
     the dashboard, including Difftastic `852 / 503 / 2789 pass` versus
     dashboard `735 / 374 / 374`; esbuild `353 / 353` versus `311 / 311`;
     Gitoxide `2877 mapped` and `6349 status pass` versus `2751 / 5634`;
     libsqlite `314` versus `286`; LightningCSS `2141 mapped` and `2884 pass`
     versus `1732 / 2197`; markerPDF `357 / 308 / 445` versus `330 / 280 /
     416`; Pandoc `1492 mapped` and `317 pass` versus `1061 / 278`;
     Quadrable `207 pass` versus `190`; rclone `815 / 815` versus `698 / 698`;
     Readability `234 pass` versus `204`; and Syncthing `6398 pass` versus
     `4579`.

5. **High - manifest/status schemas remain too free-form for acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires a real upstream benchmark
     denominator, mapped upstream tests, PHP passing/failing counts, blockers,
     and latest commit.
   - Evidence: `benchmarkDenominator.total` is numeric in some manifests and a
     long narrative string in others; `mapped` mixes files, test cases,
     assertions, behavior checks, and supplied-boundary semantics; `phpPass`
     mixes behavior tests and assertion counts; and `latestCommit` fields
     contain prose such as `pending in shared dirty worktree`, `not committed`,
     and `uncommitted lane batch`.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     and `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` says passing tests are not enough and each
     lane needs meaningful upstream denominator, fixture parity, edge-case
     coverage, error behavior, docs/examples, and honest blockers.
   - Evidence: the dashboard advertises `97.7%` average progress and `98-99%`
     for most lanes while blockers still record unrun or partial Cargo, Go,
     BATS, Haskell, release-extra, live-provider, model/runtime, and root
     aggregate parity. Focused green slices are useful evidence, but they are
     not accepted full-port parity.

7. **High - markerPDF still over-credits external/runtime orchestration as
   mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:461`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:687` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:718`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:971` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:985`, and
     `lanes/markerpdf/src/ChunkConversionPlanner.php:17`.
   - Requirement at risk: `goal.md` forbids counting wrappers around
     JS/Rust/Go/C binaries and says bridge code may only exist as temporary
     fixture-generation or oracle tooling.
   - Evidence: markerPDF has valuable native PDF stream-filter work, but the
     manifest/status also count marker server/app/runtime planning,
     pyproject/Poetry/package metadata, model runtime graphs, OCR install
     plans, Texify/Nougat boundaries, benchmark archive planning, and
     `chunk_convert.sh` shell lifecycle metadata. Those are preflight/oracle
     boundaries unless separated from native port progress.

8. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json`, `progress.md:17` through
     `progress.md:24`, `porting.html:71` through `porting.html:78`,
     `porting-summary.json`, and the absence of any support-library
     `UPSTREAM_TEST_MANIFEST.json` outside `lanes/*`.
   - Requirement at risk: this audit prompt requires support libraries to have
     a bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` now has 23 rows (`candidate: 13`,
     `deferred: 10`), including `pandoc-doctemplates-core`; local
     `porting.html` and `porting-summary.json` still publish 22 rows
     (`candidate: 12`, `deferred: 10`). The only manifest files are the 12 lane
     manifests, so rich-function gaps such as ZIP/OpenXML/ODT/doctemplates,
     PDF text/OCR/layout/table/Unicode, WebDAV XML/archive/provider metadata,
     charset/hash/glob/compression, and source maps have no dependency-specific
     PHP pass/fail evidence.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/src/VfsZipArchive.php`,
     `lanes/rclone/src/VfsServeZipResponse.php`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php`,
     `lanes/rclone/src/VfsWebDavPropfindResponse.php`,
     `lanes/rclone/src/VfsWebDavCompression.php`,
     `lanes/rclone/src/MetadataMapper.php`,
     `lanes/rclone/src/OneDrivePermissionPlanner.php`, and
     `dependency-backlog.json`.
   - Requirement at risk: this audit prompt says dependency expansion must be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone carries lane-local ZIP, WebDAV XML, WebDAV compression,
     provider metadata, and OneDrive permission/metadata planning components,
     while markerPDF carries benchmark archive and supplied-document archive
     evidence. These may be justified lane slices, but they should not count as
     support-library progress until split or gated with dependency-specific
     denominators, mapped fixtures, PHP pass/fail evidence, and malformed or
     corrupt cases.

10. **Medium - `progress.md` Active Lanes still lags current handoffs.**
    - Paths: `progress.md:82` through `progress.md:101` and
      `lanes/*/lane-status.json`.
    - Requirement at risk: `goal.md` requires current owner/session, next task
      per lane, audit status, current work, blocker, and latest commit.
    - Evidence: the Active Lanes table still lists older handoffs such as
      Gitoxide SSH config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Readability negative header cleanup, Syncthing
      system-log route, Difftastic Ada/Apex, rclone VFS Statfs, and esbuild
      automatic JSX fallback. Current lane statuses describe later index,
      relative-color, PDF stream-filter, BBC live-update cleanup,
      TestIgnoreDeleteUnignore, PHP/Hack declaration, WebDAV middleware, and
      TypeScript private-field work.

## Next Intervention

Keep the hard writer/runner/status freeze. The next acceptable move is still:
two stable polls of `HEAD`, tracked status count, untracked-inclusive status
count, shortstat, exact PHP runner state, Dolt runner state, capacity/disk state,
and relevant log mtimes; accept one lane-scoped batch only; normalize
schema/count fields for that batch; run focused verification plus
`git diff --check`; run exactly one serialized no-argument
`php tools/run-tests.php` from that frozen snapshot if the exact process gate is
empty; regenerate `porting.html`/`porting-summary.json` from the accepted
commit; then commit or reject. Do not count support-library work until it has a
bounded component, activation gate, dependency-specific denominator, mapped
fixtures, PHP pass/fail evidence, and malformed/corrupt cases where relevant.
