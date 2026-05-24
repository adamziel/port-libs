# Independent Audit - 2026-05-24T04:04Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git history,
root-runner process state, and active coordination processes.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T04:02Z to 2026-05-24T04:04Z
HEAD observed during audit: 2fb83e36 -> 8847b6d9
recent commits: 8847b6d9 Record integration hold status; 2fb83e36 Refresh independent audit status; a63636e1 Record integration hold status
branch sample: main...origin/main [ahead 685, behind 68]
tracked dirty rows: 306
default status rows including untracked: 13420
git diff --shortstat: 306 files changed, 157086 insertions(+), 19243 deletions(-)
tmux sessions: 189
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:02:58Z:
<no rows>

active coordination/worker evidence:
tmux list-sessions: 189
visible repo loops include run-dashboard-updater-loop.sh, run-evaluator-loop.sh,
run-team-watchdog.sh, run-capacity-controller-loop.sh,
run-capacity-executor-queue.sh, and many run-tmux-agent.sh lane/capacity sessions.
```

I did not start `php tools/run-tests.php`. The process gate was clear at the
sample, but the checkout was not stable enough for a meaningful audit-owned
root run: `HEAD` moved during the review, every lane handoff remains dirty or
pending, and high-volume writer/status/runner loops are active.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:37` through `progress.md:45`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`, and current Git status.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`,
     `goal.md:49`, and `goal.md:52`.
   - Evidence: `HEAD` moved during this audit from `2fb83e36` to
     `8847b6d9`; tracked dirty rows remain 306; default status rows rose to
     13,420; shortstat is 157,086 insertions; and 189 tmux sessions plus
     dashboard/evaluator/watchdog/capacity/agent loops are active. Lane status
     files still say current handoffs are pending or uncommitted and root
     aggregate verification is supervisor-owned.

2. **Critical - no coherent root-harness result exists for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/gitoxide/lane-status.json:10` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:10` through
     `lanes/markerpdf/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: the pre-root gate was clear at 04:02:58Z, but the tree was
     already unstable and moving. Current lane statuses record focused
     lane-green checks only; they explicitly leave the no-argument root harness
     pending. A root run from this dirty, moving state would not prove the
     accepted snapshot required by the goal.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   coordination artifacts.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting-summary.json:1` through `porting-summary.json:8`,
     `porting.html:75` through `porting.html:78`, and
     `dependency-backlog.json:1` through `dependency-backlog.json:5`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and
     `goal.md:52`.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 23:43:54 UTC` and source commit `79768df0c427`, while the
     observed `HEAD` is `8847b6d9` and every lane manifest/status is dirty.
     The dashboard reports 22 dependency rows, 12 candidates, and 10 deferred
     rows; `dependency-backlog.json` now has 23 rows, 13 candidates, and 10
     deferred rows after `pandoc-doctemplates-core`.

4. **High - dashboard, manifest, and lane-status counts disagree across
   active lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:80`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:7`, and
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:45`.
   - Evidence: Difftastic currently says 812 total / 446 mapped while the
     dashboard says 735 / 374. markerPDF says 350 / 301 with 438 PHP behavior
     tests while the dashboard says 330 / 280 and 416 pass. Rclone says 786
     PHP behavior tests while the dashboard says 698. Syncthing says 6,100 PHP
     assertions while the dashboard says 4,579 pass. LightningCSS says 1,911
     mapped and 2,448 PHP pass while the dashboard says 1,732 and 2,197.

5. **High - manifest/status schemas remain too free-form for reliable
   status generation.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/gitoxide/lane-status.json:5` through
     `lanes/gitoxide/lane-status.json:13`, and
     `porting-summary.json:11` through `porting-summary.json:80`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45`.
   - Evidence: denominators mix integers, prose strings, generated slugs, and
     inventory labels; PHP pass values mix behavior tests, selected files,
     assertions, and pass cases; commit fields contain `pending`,
     `uncommitted`, truncated prose, or stale hashes. The generator cannot
     reliably distinguish accepted upstream runner parity from focused local
     checks or static inventory.

6. **High - focused lane-green evidence is being recorded before supervisor
   acceptance and aggregate verification.**
   - Paths: `lanes/gitoxide/lane-status.json:10` through
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:10` through
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49`.
   - Evidence: those status files record focused PHP, syntax, examples, JSON,
     and `git diff --check` as green, while also stating no-argument root
     verification and commit acceptance are pending. That is useful intake
     evidence, but it is not accepted progress until one frozen batch is
     reviewed, root-verified, dashboard-regenerated, and committed.

7. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:20` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:80`,
     `lanes/markerpdf/lane-status.json:5`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:9`, `goal.md:30`,
     `goal.md:35`, and `goal.md:40`.
   - Evidence: markerPDF reports 98% progress, 350 counted units, and 301
     mapped semantics even though the denominator includes GitHub Actions,
     Poetry/lock/package/runtime planning, Streamlit/FastAPI/Uvicorn,
     chunk-convert shell lifecycle, OCR install readiness, model-runtime
     graphs, upload route planning, and other plan-only boundaries. These are
     blockers or preflight records, not native PDF extraction parity.

8. **High - essential optional-library coverage is still backlog-only, not
   lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:80`, `porting.html:75` through
     `porting.html:114`, and current lane manifest list.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library coverage requirement.
   - Evidence: only the 12 base lane manifests exist. Required rich-function
     support such as ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB,
     EPUB/ODF, Pandoc doctemplates, PDF text, OCR/layout, table geometry,
     Unicode, source maps, protobuf, archive/compression, glob/pathspec, and
     provider metadata normalization remains backlog text without
     dependency-specific upstream/spec denominators, mapped fixtures, PHP
     pass/fail evidence, malformed/corrupt cases, or accepted activation gates.

9. **High - archive/compression, WebDAV/XML, and provider-metadata work is
   expanding lane-locally when it should be shared or gated.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:43`, `dependency-backlog.json:382` through the
     provider/archive rows, `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: Rclone has broad VFS ZIP/WebDAV/OneDrive metadata/upload/
     permission work, while markerPDF has benchmark ZIP/package and model
     runtime metadata planning. The backlog says shared ZIP, XML/HTML5,
     archive/compression, and provider-normalization should have bounded
     dependency ports with their own denominators and corrupt-case tests.
     Current lane-local expansion risks duplicate infrastructure and inflated
     progress without shared acceptance evidence.

10. **High - shell/process boundaries are still present and must remain oracle
    or planning only.**
    - Paths: `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php:142`,
      `lanes/markerpdf/tests/ChunkConversionPlannerTest.php:49`, and
      `tools/generate-dashboard.php:197`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: Gitoxide tests still use `proc_open()` for upstream/local
      oracle checks, markerPDF records shell lifecycle metadata, and dashboard
      generation shells out for coordination metadata. Those uses may be
      acceptable as oracle or tooling boundaries, but any behavior depending on
      process launches must be excluded from native implementation progress
      until captured as fixtures or reimplemented in PHP.

11. **High - near-complete percentages overstate accepted upstream parity.**
    - Paths: `porting.html:32`, `porting.html:56` through
      `porting.html:67`, `lanes/gitoxide/lane-status.json:5` through
      `lanes/gitoxide/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:5` through
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/rclone/lane-status.json:5` through
      `lanes/rclone/lane-status.json:12`, and
      `lanes/syncthing/lane-status.json:5` through
      `lanes/syncthing/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
      `goal.md:38`, and `goal.md:40`.
    - Evidence: the dashboard advertises 97.7% average progress and most lanes
      at 98-99%, while major parity remains static-only, focused-only,
      unexecuted, disk-quota-blocked, or pending acceptance: Gitoxide full
      Cargo, markerPDF live benchmark/model runner, Rclone live providers/mount,
      Syncthing full `go test ./...`, Pandoc Haskell runner/rich formats,
      Difftastic full Cargo, esbuild release-extra `make test-all`, and SQLite
      all/release permutations.

12. **Medium - `progress.md` is stale as a current coordination surface.**
    - Paths: `progress.md:37` through `progress.md:89`,
      `lanes/lightningcss/lane-status.json:10` through
      `lanes/lightningcss/lane-status.json:14`,
      `lanes/rclone/lane-status.json:10` through
      `lanes/rclone/lane-status.json:14`, and
      `lanes/syncthing/lane-status.json:10` through
      `lanes/syncthing/lane-status.json:14`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: the current snapshot section is mostly repeated audit log
      entries, while its active-lane table still names older handoff slices
      such as LightningCSS trig/math, markerPDF benchmark planning, Rclone VFS
      Statfs, and Syncthing system-log. Current lane statuses have moved to
      newer gradient, OCR invalid-character, WebDAV If-confirmation, and folder
      errors slices. Readers still need to open lane files to determine the
      actual current handoff and blocker.

## Required Intervention

Keep the integration hold. The next best intervention has not changed:

1. Freeze writers, dashboard updater, capacity executor/controller, evaluator,
   watchdog, lane workers, and focused/broad/root runners.
2. Take two stable polls of `HEAD`, `git status --short --untracked-files=no`,
   default status count, `git diff --shortstat`, and
   `pgrep -af '^php tools/run-tests\.php( |$)'`.
3. Accept exactly one lane batch, normalize manifest/status schema fields for
   that batch, and run its focused PHP checks plus `git diff --check`.
4. If the duplicate-root gate is clear from that frozen snapshot, run exactly
   one serialized no-argument `php tools/run-tests.php`.
5. Regenerate `porting.html` and `porting-summary.json` from the accepted
   commit, then commit or reject the batch.

No `progress.md` update was made because the blocker, audit status, and next
best intervention are unchanged from the prior entry; only the evidence was
refreshed here.

## Tests

Not run. The pre-root process gate was clear at the sampled time, but the tree
was not stable enough: `HEAD` moved during review, 306 tracked dirty rows and
13,420 total status rows were present, and active writer/status/runner loops
were visible.
