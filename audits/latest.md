# Independent Audit - 2026-05-24T04:23Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git history, and
root-runner process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T04:21:24Z to 2026-05-24T04:23:40Z
HEAD observed during audit: 7b44ca427aff -> f5ebfb5c2d7e
recent commits: f5ebfb5c Record integration hold status; 7b44ca42 Refresh independent audit status; aa2e1632 Record integration hold status
branch: main
branch divergence: ahead 694, behind 68 relative to origin/main
tracked dirty rows: 306
default status rows including untracked: 13460
git diff --shortstat: 306 files changed, 157278 insertions(+), 17572 deletions(-)
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:21:24Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:23:40Z:
<no rows>
```

I did not start `php tools/run-tests.php`. The duplicate-root gate was clear,
but the checkout was not stable enough for a meaningful no-argument root run:
`HEAD` moved during the audit, the worktree remains a 306-file tracked dirty
aggregate, and every primary lane still has dirty or pending handoff state.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:37` through `progress.md:90`, current Git status, and
     recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verified finished work, and periodic
     repo-wide tests/static checks.
   - Evidence: `HEAD` moved from `7b44ca427aff` to `f5ebfb5c2d7e` during this
     audit; `main...origin/main` is now ahead 694 and behind 68; tracked dirty
     rows are 306; total status rows are 13,460; and the shortstat is 157,278
     insertions. Recent history is dominated by audit and integration-hold
     commits while broad lane output remains unaccepted.

2. **Critical - no coherent root-harness result exists for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:13`, and
     `lanes/markerpdf/lane-status.json:10` through
     `lanes/markerpdf/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:49` requires repo-wide tests/static
     checks with honest failure recording.
   - Evidence: the two exact `pgrep -af '^php tools/run-tests\.php( |$)'`
     samples had no rows, but lane statuses still say no-argument root
     aggregate verification is pending or not assigned. Focused lane-green
     checks are not a serialized root result from a frozen accepted snapshot.

3. **Critical - `porting.html` and `porting-summary.json` are stale and still
   fail the dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting.html:43` through `porting.html:52`, and
     `porting-summary.json:1` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require a current
     generated dashboard with benchmark source, upstream denominator, mapped
     tests, PHP pass/fail, WordPress scenarios, phase, audit, current work,
     blocker, and commit.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 23:43:54 UTC` and source commit `79768df0c427`, while the
     observed `HEAD` is `f5ebfb5c2d7e`. Its table also collapses benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail into broad
     `Benchmark` and `Mapped` cells instead of the required separate columns.

4. **High - manifest, lane-status, and dashboard counts disagree across active
   lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:14` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:12`, and
     `porting-summary.json:11` through `porting-summary.json:213`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45` require mapped
     upstream denominators and accurate dashboard pass/fail counts.
   - Evidence: Difftastic manifest now says 819 total / 454 mapped while the
     dashboard says 735 / 374. Gitoxide manifest says 2,877 / 2,877 while the
     dashboard says 2,751 / 2,877. LightningCSS manifest says 3,532 / 1,912
     while the dashboard says 1,732 mapped. markerPDF manifest/status says
     351 / 302 and 439 PHP behavior tests while the dashboard says 330 / 280
     and 416 pass. Rclone lane status says 794 PHP behavior tests while the
     dashboard says 698. Syncthing lane status says 6,179 PHP assertions while
     the dashboard says 4,579 pass.

5. **High - manifest/status schemas remain too free-form for reliable
   generation or acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json:20`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:16`,
     and `porting-summary.json:28` through `porting-summary.json:178`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is sometimes a number, sometimes a
     prose paragraph, and sometimes duplicated as `totalCount`. Commit fields
     in the generated summary still contain truncated prose such as `not com`,
     `uncommi`, `HEAD 8d`, and `pending`. Those shapes prevent a generator or
     auditor from distinguishing full upstream runner parity, static inventory,
     focused runner evidence, PHP behavior tests, and root integration proof.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:18` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:21`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:93` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:97`,
     `lanes/rclone/lane-status.json:10` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:10` through
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes while full Cargo/Haskell/Go/provider/root parity remains
     unexecuted or blocked in the same lane records. Passing focused PHP checks
     are being presented too close to full-port completion.

7. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:13`, and
     `lanes/markerpdf/notes/upstream-test-inventory.md:340` through
     `lanes/markerpdf/notes/upstream-test-inventory.md:430`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:35`.
   - Evidence: markerPDF counts Streamlit/FastAPI/Uvicorn plans, Poetry/package
     metadata, chunk-convert shell lifecycle planning, OCR/Tesseract/
     Ghostscript readiness, model-loader/runtime graphs, Texify/model token
     planning, benchmark archive setup, and supplied-callback boundaries inside
     the mapped denominator. Those are preflight/blocker/oracle notes unless
     reduced to bounded native PHP behavior with fixture parity and pass/fail
     evidence.

8. **High - essential optional-library coverage remains backlog-only, not
   lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `dependency-backlog.json:111` through `dependency-backlog.json:123`,
     `porting.html:75` through `porting.html:78`, and the manifest list from
     `rg --files -g 'UPSTREAM_TEST_MANIFEST.json'`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: the repo has exactly the 12 lane manifests and no
     support-library manifests. `dependency-backlog.json` has 23 gated items
     with requirements for ZIP/package, XML/HTML5, DOCX/OpenXML, legacy
     DOC/CFB, doctemplates, PDF text, OCR/layout, table geometry, Unicode,
     source maps, protobuf, compression, glob/pathspec, and provider metadata
     normalization, but none has its own bounded native component, activation
     gate enforcement, dependency-specific denominator, mapped fixtures, PHP
     pass/fail evidence, or malformed/corrupt coverage. The stale dashboard
     still reports 22 backlog items.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:43`,
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: rclone is expanding VFS ZIP/WebDAV/OneDrive/provider metadata
     surfaces and markerPDF is expanding benchmark archive, PDF text, OCR,
     model-runtime, and package planning while the backlog says shared ZIP and
     XML/HTML5 cores should be gated and reusable. This risks duplicate
     infrastructure and inflated lane progress.

10. **Medium - `progress.md` Active Lanes still lag current lane handoffs.**
    - Paths: `progress.md:71` through `progress.md:90`,
      `lanes/rclone/lane-status.json:10` through
      `lanes/rclone/lane-status.json:14`,
      `lanes/syncthing/lane-status.json:10` through
      `lanes/syncthing/lane-status.json:14`, and
      `lanes/markerpdf/lane-status.json:10` through
      `lanes/markerpdf/lane-status.json:14`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists rclone as VFS Statfs/usage,
      Syncthing as system-log route, and markerPDF as benchmark file-inventory
      planning, while current lane-status files report WebDAV COPY/MOVE,
      folder versions, and reading-order rescale zero-dimension guards. The
      human coordination file is not the current owner/session/next-task source.

11. **Medium - process/shell boundaries need stricter non-progress labeling.**
    - Paths: `tools/generate-dashboard.php:197`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:670` through
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:673`, and
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:680` through
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:684`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: dashboard generation shells out for Git metadata, and
      markerPDF records shell-script lifecycle and OCR installer command plans.
      These may be acceptable as coordination/preflight metadata, but they must
      remain explicitly excluded from native implementation progress.

## Required Intervention

Keep the integration hold. Freeze writers, lane agents, status/dashboard
publishers, and root/focused runners long enough for two stable polls of
`HEAD`, `git status`, `git diff --shortstat`, and
`pgrep -af '^php tools/run-tests\.php( |$)'`. Accept one lane batch at a time
only after schema/count normalization, focused lane verification, and
`git diff --check`. If the root process gate is empty on that frozen snapshot,
run exactly one no-argument `php tools/run-tests.php`, regenerate
`porting.html`/`porting-summary.json` from the accepted commit, then commit or
reject. Do not credit support-library work until a bounded shared component has
its own manifest, activation gate, denominator, fixtures, PHP evidence, and
malformed/corrupt coverage.
