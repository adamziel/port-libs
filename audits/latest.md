# Independent Audit - 2026-05-24T04:30Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every current `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and root-runner process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T04:29:51Z to 2026-05-24T04:30:02Z
HEAD observed during audit: 5a63ac38dad0
recent commits: 5a63ac38 Record integration hold status; eda8e7ff Refresh independent audit status; f5ebfb5c Record integration hold status
branch: main
branch divergence: ahead 696, behind 68 relative to origin/main
tracked dirty rows: 306
default status rows including untracked: 13470
git diff --shortstat changed during two samples: 306 files changed, 157756 insertions(+), 17557 deletions(-) -> 306 files changed, 157765 insertions(+), 17557 deletions(-)
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:29:51Z:
<no rows>

pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:30:02Z:
<no rows>
```

I did not start `php tools/run-tests.php`. The duplicate-root gate was clear,
but the checkout was not stable enough for a meaningful no-argument root run:
the tracked dirty row count stayed at 306 while the diff itself changed within
11 seconds, the default status has 13,470 rows, and every primary lane still
has dirty or pending handoff state.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:39`, current Git status, and recent Git history.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verified finished work, and honest
     repo-wide checks.
   - Evidence: `HEAD` is `5a63ac38dad0`, `main...origin/main` is ahead 696
     and behind 68, tracked dirty rows remain 306, default status rows are
     13,470, and the shortstat changed from 157,756 to 157,765 insertions
     during two audit samples 11 seconds apart. That is not a frozen tree.

2. **Critical - no coherent root-harness result exists for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/rclone/lane-status.json:10`,
     `lanes/syncthing/lane-status.json:10`, and
     `lanes/markerpdf/lane-status.json:10`.
   - Goal requirement at risk: `goal.md:49` requires repo-wide tests/static
     checks with failures recorded honestly.
   - Evidence: the exact pre-root process gate had no rows at both audit
     samples, but the tree was changing, so an audit-owned root run would not
     prove the current dirty aggregate. Lane-status files continue to record
     focused lane-green evidence while explicitly leaving root aggregate
     verification to the supervisor/integrator.

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
     observed `HEAD` is `5a63ac38dad0`. Its table still collapses benchmark
     source, upstream denominator, mapped tests, and PHP pass/fail into broad
     `Benchmark` and `Mapped` cells instead of the required separate columns.

4. **High - manifest, lane-status, and dashboard counts disagree across active
   lanes.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:13`, and
     `porting-summary.json:11` through `porting-summary.json:213`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45` require mapped
     upstream denominators and accurate dashboard pass/fail counts.
   - Evidence: current manifests/statuses say Difftastic is 819 total / 455
     mapped while the dashboard says 735 / 374; esbuild is 346 mapped while
     the dashboard says 311; LightningCSS is 1,922 mapped and 2,461 PHP
     assertions while the dashboard says 1,732 and 2,197; markerPDF status
     says 352 / 303 and 440 PHP behavior tests while the dashboard says
     330 / 280 and 416; rclone status says 797 PHP behavior tests while the
     dashboard says 698; Syncthing status says 6,265 PHP assertions while the
     dashboard says 4,579 pass.

5. **High - manifest/status schemas remain too free-form for reliable
   generation or acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2430`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:16`, and
     `porting-summary.json:28` through `porting-summary.json:195`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is sometimes a number, sometimes a
     long prose paragraph, and sometimes paired with `totalCount`. Dolt places
     `mapped` near the top and a narrative `total` much later. Generated
     commit fields still contain truncated states such as `not com`, `uncommi`,
     and `HEAD 8d`, which are not auditable commit identifiers.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/gitoxide/lane-status.json:5` through
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`, and
     `lanes/syncthing/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes while full Cargo/Haskell/Go/provider/root parity remains
     unexecuted or blocked in the same lane records. Focused PHP checks are
     useful evidence, but they are being presented too close to full-port
     completion.

7. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:670` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:690`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:35`.
   - Evidence: the mapped denominator includes `chunk_convert.sh` shell
     lifecycle planning, Streamlit/FastAPI/Uvicorn runtime plans, OCR installer
     plans, Poetry/package metadata, model-runtime dependency graphs, Texify
     tokenizer/model boundaries, and benchmark/Nougat/Pandoc/XeLaTeX planning.
     Those are preflight/blocker/oracle notes unless reduced to bounded native
     PHP behavior with fixture parity and pass/fail evidence.

8. **High - essential optional-library coverage remains backlog-only, not
   lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:23`, `dependency-backlog.json:25` through
     `dependency-backlog.json:43`, `dependency-backlog.json:111` through
     `dependency-backlog.json:124`, `dependency-backlog.json:169` through
     `dependency-backlog.json:213`, and `porting.html:72` through
     `porting.html:78`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: the current lane tree has only the 12 primary
     `lanes/*/UPSTREAM_TEST_MANIFEST.json` files. The 23 support-library
     candidates define useful gates for ZIP/package, XML/HTML5, OpenXML,
     legacy DOC/CFB, doctemplates, PDF text, OCR/layout, table geometry,
     Unicode, source maps, protobuf, compression, glob/pathspec, and provider
     metadata, but none has a bounded component directory with its own
     manifest, activation gate enforcement, dependency-specific denominator,
     mapped fixtures, PHP pass/fail evidence, or malformed/corrupt coverage.
     The stale dashboard still reports 22 backlog items.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `dependency-backlog.json:15` through
     `dependency-backlog.json:20`, `dependency-backlog.json:35` through
     `dependency-backlog.json:40`, `lanes/rclone/src/VfsZipArchive.php:13`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php:31`,
     `lanes/rclone/lane-status.json:8` through
     `lanes/rclone/lane-status.json:12`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:9`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php:36`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: rclone now has lane-local ZIP and WebDAV XML surfaces, while
     markerPDF uses archive inspection and PDF/OCR/model planning inside the
     lane. The backlog says ZIP/package and XML/HTML5 should be shared,
     dependency-specific support cores. Keeping those pieces lane-local risks
     duplicate infrastructure and inflated lane progress.

10. **Medium - `progress.md` Active Lanes still lag current lane handoffs.**
    - Paths: `progress.md:72` through `progress.md:89`,
      `lanes/rclone/lane-status.json:11`, `lanes/syncthing/lane-status.json:11`,
      and `lanes/markerpdf/lane-status.json:11`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: `progress.md` still lists older handoffs such as rclone VFS
      Statfs/usage, Syncthing system-log route, and markerPDF benchmark
      file-inventory planning. Current lane-status files now describe WebDAV
      OPTIONS, event streams, and equation tokenizer gating.

11. **Medium - process/shell boundaries need stricter non-progress labeling.**
    - Paths: `tools/generate-dashboard.php:197`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:670` through
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:684`, and
      `lanes/markerpdf/src/ChunkConversionPlanner.php:142`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: dashboard generation shells out for Git metadata; several
      Gitoxide tests use subprocesses as oracles; markerPDF records shell
      lifecycle and installer command plans. Some of this is acceptable
      coordination or oracle tooling, but it must remain excluded from native
      implementation progress and from lane completion percentages.

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
