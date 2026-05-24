# Independent Audit - 2026-05-24T05:28Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, all 12 `lanes/*/UPSTREAM_TEST_MANIFEST.json` files,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and root-runner process state.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC sample: 2026-05-24T05:28Z
HEAD observed: a843795f1f4c
recent commits: a843795f Record integration hold status; 97198950 Refresh independent audit status; 254c4116 Record integration hold status
branch divergence: main...origin/main [ahead 710, behind 68]
tracked dirty rows: 307
default status rows including untracked: 13527
git diff --shortstat: 307 files changed, 167253 insertions(+), 21926 deletions(-)
recent port logs touched in last five minutes: 24
manifest/status JSON validation: jq empty passed for all lane manifests, lane-status files, porting-summary.json, and dependency-backlog.json
root run by this audit: not started
```

Required root-run gate evidence:

```text
Initial pgrep -af '^php tools/run-tests\.php( |$)':
<no rows>

Later pgrep -af '^php tools/run-tests\.php( |$)':
987713 php tools/run-tests.php lanes/syncthing/tests

ps -o pid,user,etime,args -p 987713:
PID USER   ELAPSED COMMAND
987713 claude 00:20 php tools/run-tests.php lanes/syncthing/tests

Post-edit pgrep -af '^php tools/run-tests\.php( |$)':
<no rows>

stability evidence:
earlier sample: HEAD 97198950bc29; 308 tracked dirty rows; 13524 total status rows; 308 files changed, 167213 insertions(+), 21966 deletions(-)
next sample:    HEAD a843795f1f4c; 307 tracked dirty rows; 13525 total status rows; 307 files changed, 167252 insertions(+), 21926 deletions(-)
final sample:   HEAD a843795f1f4c; 307 tracked dirty rows; 13527 total status rows; 307 files changed, 167253 insertions(+), 21926 deletions(-)
```

I did not start `php tools/run-tests.php`. The exact gate later matched focused
Syncthing PID `987713` owned by `claude`; a post-edit check was clear only
after the checkout had already moved from `97198950bc29` to `a843795f1f4c` and
the dirty aggregate remained active. A no-argument root run from this state
would not be a coherent acceptance checkpoint.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `progress.md:77`, `lanes/*/lane-status.json`,
     and current Git status.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:49` require small reviewable slices, finished-agent
     verification, commits, and honest repo-wide checks.
   - Evidence: `HEAD` advanced during this audit; current branch divergence is
     `ahead 710, behind 68`; the tree still has 307 tracked dirty rows, 13,527
     status rows including untracked files, and 24 recently touched port logs.
     Every primary lane remains a dirty/pending handoff, not an integrated
     slice.

2. **Critical - no coherent root-harness result exists for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/dolt/lane-status.json`, `lanes/esbuild/lane-status.json`,
     `lanes/gitoxide/lane-status.json`, `lanes/libsqlite/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, `lanes/readability/lane-status.json`,
     and `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording.
   - Evidence: initial `pgrep -af '^php tools/run-tests\.php( |$)'` returned
     no rows, but later gating matched active focused Syncthing PID `987713`
     owned by `claude`; a post-edit check cleared only after the tree had
     already changed between samples. Lane statuses cite focused lane-green
     checks while aggregate root verification remains explicitly pending.

3. **Critical - `porting.html` and `porting-summary.json` are stale and do
   not satisfy the dashboard contract.**
   - Paths: `porting.html:32`, `porting.html:34`,
     `porting.html:35`, `porting.html:43` through `porting.html:52`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require current
     dashboard rows with benchmark source, upstream denominator, mapped tests,
     PHP pass/fail, WordPress scenarios, phase, audit, current work, blocker,
     and commit.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 23:43:54 UTC` and snapshot `main 79768df0c427`, while the
     reviewed HEAD is `a843795f1f4c`. The HTML still has only `Benchmark` and
     `Mapped` columns instead of separate benchmark-source, upstream
     denominator, mapped-tests, and PHP pass/fail columns, and commit cells
     still contain truncated prose states such as `pending`, `uncommi`,
     `not com`, and `HEAD 8d`.

4. **High - manifest, lane-status, and dashboard counts disagree across
   active lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:76`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/esbuild/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json`, and
     `lanes/syncthing/UPSTREAM_TEST_MANIFEST.json`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45`.
   - Evidence: current manifests do not match the published dashboard:
     Difftastic is `834` total / `472` mapped while the dashboard says
     `735` / `374`; esbuild maps `349` while the dashboard says `311`;
     Gitoxide maps `2877` while the dashboard says `2751`; markerPDF is
     `355` / `306` / `442` native behavior tests while the dashboard says
     `330` / `280` / `416`; Pandoc maps `1459` while the dashboard says
     `1061`; rclone maps `806` while the dashboard says `698`; Syncthing lane
     status reports 6,332 PHP assertions while the dashboard says 4,579 pass.

5. **High - manifest/status schemas are still too free-form for reliable
   acceptance.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/quadrable/UPSTREAM_TEST_MANIFEST.json`,
     and `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:25`, `goal.md:38`, and
     `goal.md:45`.
   - Evidence: `benchmarkDenominator.total` is numeric in some manifests and
     a long narrative string in others. Native evidence uses lane-specific
     fields such as `currentSlice`, `latestSlice`, `phpBehaviorTests`, and
     prose audit blobs. The dashboard generator cannot safely separate
     upstream denominator units, mapped upstream units, behavior-test counts,
     assertion counts, runner status, blocker state, and accepted commit state.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:32`, `porting.html:56` through `porting.html:67`,
     `lanes/difftastic/lane-status.json`,
     `lanes/gitoxide/lane-status.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/pandoc/lane-status.json`,
     `lanes/rclone/lane-status.json`, and
     `lanes/syncthing/lane-status.json`.
   - Goal requirement at risk: `goal.md:35` through `goal.md:40` and
     `goal.md:49`.
   - Evidence: the dashboard advertises 97.7% average progress and 98-99% for
     most lanes while current lane records still say root verification, full
     Cargo/Go/BATS/Haskell/release-extra suites, live provider paths, and
     upstream parity remain unrun or explicitly blocked.

7. **High - markerPDF still over-credits external/runtime orchestration as
   mapped native progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/markerpdf/lane-status.json`,
     `lanes/markerpdf/src/ChunkConversionPlanner.php`, and
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: the markerPDF status still includes benchmark CLI planning,
     marker server startup/lifespan/remote polling plans, chunk-convert shell
     lifecycle plans, OCR install readiness, Poetry/package metadata, model
     runtime graphs, Texify/Nougat planning, and CI workflow/publish plans.
     Those are preflight or oracle boundaries, not native PDF conversion
     progress.

8. **High - essential optional-library coverage remains backlog-only rather
   than lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:1` through `dependency-backlog.json:23`,
     `dependency-backlog.json:25` through `dependency-backlog.json:43`,
     `porting.html:75` through `porting.html:78`, and
     `progress.md:17` through `progress.md:24`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library requirement.
   - Evidence: `dependency-backlog.json` now has 23 items with
     `candidate: 13` and `deferred: 10`, but `porting.html` still says 22
     items with `candidate: 12`. There are no corresponding support-library
     manifests, lane-status files, PHP pass/fail evidence, or
     dependency-specific upstream/spec denominators for the rich-function gaps:
     Pandoc package/OpenXML/ODT/doctemplates/citation/math, markerPDF PDF
     text/OCR/layout/table/Unicode, rclone archive/XML/provider metadata, and
     shared charset/hash/glob/compression surfaces.

9. **High - dependency expansion is happening lane-locally instead of through
   bounded shared gates.**
   - Paths: `lanes/rclone/src/VfsZipArchive.php`,
     `lanes/rclone/src/VfsWebDavProppatchXml.php`,
     `lanes/rclone/src/VfsWebDavReadResponse.php`,
     `lanes/markerpdf/src/BenchmarkArchiveInspector.php`, and
     `dependency-backlog.json`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: rclone carries lane-local ZIP writing, WebDAV XML parsing, and
     gzip/WebDAV response modeling, while markerPDF inspects benchmark ZIP
     archives inside the markerPDF lane. These may be useful local slices, but
     they should not count as support-library progress until split or
     explicitly justified behind bounded shared gates with dependency-specific
     upstream/spec denominators, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases.

10. **Medium - `progress.md` Active Lanes lag current lane handoffs.**
    - Paths: `progress.md:81` through `progress.md:94` and current
      `lanes/*/lane-status.json`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: the Active Lanes table still lists older handoffs such as
      Gitoxide SSH config-options, LightningCSS trig/math, markerPDF benchmark
      file-inventory planning, Readability negative header cleanup, Syncthing
      system-log route, Difftastic Ada/Apex, rclone VFS Statfs/usage, and
      esbuild automatic JSX fallback. Current lane statuses describe later
      UNTR, relative color, ASCII85Decode, WebDAV gzip, scored sibling,
      events-bridge, and class decorator work.

11. **Medium - shell-backed oracle tests and adapters must stay quarantined
    from native progress credit.**
    - Paths: `lanes/gitoxide/tests/*`, `lanes/gitoxide/src/GitFilterDriver.php`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php`, and
      `lanes/rclone/src/*`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: several lanes correctly use subprocesses or external command
      shapes as oracle/preflight evidence. That evidence must remain labeled as
      fixture generation, runner evidence, or adapter planning, never as native
      implementation progress.

## Next Intervention

Maintain the hard writer/runner/status freeze requirement. The next acceptable
move is still: two stable polls of `HEAD`, tracked status count,
untracked-inclusive status count, shortstat, PHP runner state, and relevant log
mtimes; then accept one lane-scoped batch only, normalize schema/count fields
for that batch, run focused verification plus `git diff --check`, run exactly
one serialized no-argument `php tools/run-tests.php` from that same frozen
snapshot if `pgrep -af '^php tools/run-tests\.php( |$)'` is empty, regenerate
dashboard artifacts from the accepted commit, and then commit or reject.
