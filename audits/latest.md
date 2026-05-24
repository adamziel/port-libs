# Independent Audit - 2026-05-24T04:08Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every `lanes/*/UPSTREAM_TEST_MANIFEST.json`, current
`lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git history,
root-runner process state, and visible coordination processes.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T04:04Z to 2026-05-24T04:08Z
HEAD observed during audit: 078091a0 -> 7d4e7f8184a8
recent commits: 7d4e7f81 Record integration hold status; 078091a0 Refresh independent audit status; 8847b6d9 Record integration hold status
branch: main
tracked dirty rows: 306
default status rows including untracked: 13435
git diff --shortstat: 306 files changed, 157474 insertions(+), 19240 deletions(-)
tmux sessions: 189
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T04:08:12Z:
<no rows>

visible active coordination evidence includes:
PID 2222131 run-dashboard-updater-loop.sh
PID 2424048 run-evaluator-loop.sh
PID 2347911 run-team-watchdog.sh
PID 1464482 run-capacity-executor-queue.sh --loop
PID 2938141 run-capacity-controller-loop.sh
multiple run-tmux-agent.sh lane sessions for rclone, syncthing, dolt,
gitoxide, esbuild, readability, pandoc, lightningcss, markerpdf, libsqlite,
auditor, and difftastic.
```

I did not start `php tools/run-tests.php`. The required process gate was clear,
but the checkout was not stable enough for an audit-owned root run: `HEAD`
moved during review, every lane still has dirty or pending handoff state, and
high-volume writer/status/runner loops remain active.

## Findings

1. **Critical - the checkout is still a moving dirty aggregate, not an
   acceptance checkpoint.**
   - Paths: `progress.md:37`, `progress.md:39`,
     `lanes/dolt/lane-status.json:12`, `lanes/gitoxide/lane-status.json:12`,
     `lanes/rclone/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:12`, and current Git status.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`.
   - Evidence: `HEAD` moved from `078091a0` to `7d4e7f8184a8` during this
     audit; tracked dirty rows remain 306; default status rows reached 13,435;
     the shortstat is 157,474 insertions; and 189 tmux sessions plus active
     dashboard/evaluator/watchdog/capacity/lane-agent loops are visible. Lane
     statuses still report pending or uncommitted handoffs and root aggregate
     verification deferred to supervisor/integrator ownership.

2. **Critical - no coherent root-harness result exists for the current
   snapshot.**
   - Paths: `tools/run-tests.php`, `lanes/esbuild/lane-status.json:10`,
     `lanes/gitoxide/lane-status.json:10`,
     `lanes/markerpdf/lane-status.json:10`,
     `lanes/readability/lane-status.json:10`,
     `lanes/rclone/lane-status.json:10`, and
     `lanes/syncthing/lane-status.json:10`.
   - Goal requirement at risk: `goal.md:48` and `goal.md:49`.
   - Evidence: the pre-root gate was clear at 04:08:12Z, but a root run would
     only test a moving aggregate. Current status files record focused lane
     checks as green while explicitly saying the no-argument root harness was
     not assigned or remains pending. The Dolt status even preserves stale
     evidence of a prior active root PID, showing why lane-local status text is
     not a substitute for a serialized current snapshot run.

3. **Critical - `porting.html` and `porting-summary.json` are stale and do not
   satisfy the required dashboard contract for the current tree.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:43` through `porting.html:52`,
     `porting-summary.json:2` through `porting-summary.json:8`, and
     `progress.md:15`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:45`, and `goal.md:52`.
   - Evidence: the dashboard still publishes generated time
     `2026-05-23 23:43:54 UTC` and source commit `79768df0c427`, while the
     observed `HEAD` is `7d4e7f8184a8`. Its table also collapses benchmark,
     upstream denominator, mapped tests, and PHP pass/fail into broad
     "Benchmark" and "Mapped" cells instead of the explicit per-lane columns
     required by the goal.

4. **High - dashboard, manifest, and lane-status counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:11` through `porting-summary.json:120`,
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:15`,
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/lightningcss/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:7`,
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:7`, and
     `lanes/syncthing/lane-status.json:5` through
     `lanes/syncthing/lane-status.json:7`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45`.
   - Evidence: Difftastic manifest says 815 artifacts / 448 mapped while the
     dashboard says 735 / 374. LightningCSS manifest says 3,532 / 1,911 and
     2,448 PHP assertions while the dashboard says 3,532 / 1,732 and 2,197
     pass. markerPDF status says 350 / 301 with 438 PHP behavior tests while
     the dashboard says 330 / 280 and 416 pass. Rclone status says 790 PHP
     behavior tests while the dashboard says 698. Syncthing status says 6,100
     PHP assertions while the dashboard says 4,579 pass.

5. **High - manifest/status schemas remain too free-form for reliable status
   generation.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:16`,
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:22`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/gitoxide/lane-status.json:5` through
     `lanes/gitoxide/lane-status.json:13`, and
     `porting-summary.json:11` through `porting-summary.json:120`.
   - Goal requirement at risk: `goal.md:25` and `goal.md:45`.
   - Evidence: denominators mix integers, prose strings, generated status
     slugs, source-path inventory units, behavior checks, selected tests, and
     assertions. Commit fields contain `pending`, `not com`, `uncommi`, stale
     hashes, or prose. The generator cannot reliably distinguish static
     inventory, accepted upstream runner parity, focused PHP checks, and root
     integration evidence.

6. **High - focused lane-green evidence is being treated like near-complete
   progress before supervisor acceptance.**
   - Paths: `lanes/esbuild/lane-status.json:10` through
     `lanes/esbuild/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:10` through
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/quadrable/lane-status.json:10` through
     `lanes/quadrable/lane-status.json:13`,
     `lanes/readability/lane-status.json:10` through
     `lanes/readability/lane-status.json:13`, and
     `porting.html:32`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35`, and
     `goal.md:48`.
   - Evidence: many lane statuses report focused PHP, syntax, examples, JSON,
     or `git diff --check` as green while also saying root verification and
     commit acceptance are pending. The dashboard still advertises 97.7%
     average progress and most lanes at 98-99%, which overstates accepted
     upstream parity in the absence of a frozen root-verified integration
     commit.

7. **High - markerPDF still over-credits plan-only external/runtime
   orchestration as native port progress.**
   - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9`, and
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35`.
   - Evidence: markerPDF reports 350 counted units and 301 mapped units even
     though the denominator/status includes GitHub Actions, Poetry/package
     metadata, Streamlit/FastAPI/Uvicorn planning, `chunk_convert.sh` shell
     lifecycle planning, OCR/model/runtime install readiness, benchmark ZIP
     setup, multiprocessing/model workers, and upload route planning. Those are
     blockers or preflight records unless converted to bounded native fixtures
     and PHP pass/fail evidence.

8. **High - essential optional-library coverage remains backlog-only, not
   lane-grade support-library progress.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:23`,
     `dependency-backlog.json:111` through `dependency-backlog.json:124`,
     `dependency-backlog.json:169` through `dependency-backlog.json:180`,
     `dependency-backlog.json:203` through `dependency-backlog.json:213`,
     `dependency-backlog.json:382` through `dependency-backlog.json:399`, and
     `porting.html:75` through `porting.html:78`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's support-library granularity requirement.
   - Evidence: only the 12 base lane manifests exist. Required rich-function
     support such as ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB,
     EPUB/ODF, doctemplates, PDF text, OCR/layout, table geometry, Unicode,
     source maps, protobuf, compression, glob/pathspec, and provider metadata
     normalization has no support-library manifest, activation record, upstream
     or spec denominator, mapped fixtures, PHP pass/fail evidence, or
     malformed/corrupt-case evidence. The dashboard is additionally stale: it
     still reports 22 dependency items while `dependency-backlog.json` has 23.

9. **High - shared dependency work is expanding lane-locally without a bounded
   shared gate.**
   - Paths: `dependency-backlog.json:7` through `dependency-backlog.json:40`,
     `dependency-backlog.json:421` through `dependency-backlog.json:440`,
     `lanes/rclone/lane-status.json:5` through
     `lanes/rclone/lane-status.json:12`, and
     `lanes/markerpdf/lane-status.json:5` through
     `lanes/markerpdf/lane-status.json:12`.
   - Goal requirement at risk: `goal.md:24` through `goal.md:31`,
     `goal.md:35`, and this run's dependency-expansion requirement.
   - Evidence: Rclone is growing VFS ZIP/WebDAV/OneDrive metadata/upload and
     mutation-handler surfaces; markerPDF is growing benchmark archive, PDF
     text, OCR, model-runtime, and ZIP/package planning. The backlog says ZIP,
     XML/HTML5, archive/compression, and provider normalization need bounded
     shared native components with their own evidence. Current lane-local
     expansion risks duplicated infrastructure and inflated progress.

10. **High - full upstream parity gaps remain open despite near-complete
    percentages.**
    - Paths: `porting.html:56` through `porting.html:67`,
      `lanes/esbuild/lane-status.json:12`,
      `lanes/gitoxide/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/pandoc/lane-status.json:12`,
      `lanes/rclone/lane-status.json:12`, and
      `lanes/syncthing/lane-status.json:12`.
    - Goal requirement at risk: `goal.md:35`, `goal.md:37`,
      `goal.md:38`, and `goal.md:40`.
    - Evidence: unresolved parity includes Gitoxide full Cargo workspace,
      markerPDF live benchmark/model/service execution, Pandoc Haskell runner
      and rich-format dependencies, esbuild release-extra `make test-all`,
      rclone live providers/mounts, Syncthing full `go test ./...`, Difftastic
      full Cargo/tree-sitter build, and SQLite all/release permutations. These
      must stay explicit blockers or future slices, not implied completion.

11. **Medium - `progress.md` remains stale as the current coordination
    surface.**
    - Paths: `progress.md:37` through `progress.md:45`,
      `lanes/esbuild/lane-status.json:9` through
      `lanes/esbuild/lane-status.json:14`,
      `lanes/rclone/lane-status.json:9` through
      `lanes/rclone/lane-status.json:14`, and
      `lanes/syncthing/lane-status.json:9` through
      `lanes/syncthing/lane-status.json:14`.
    - Goal requirement at risk: `goal.md:44`.
    - Evidence: the latest progress snapshot is still the 03:46 UTC audit
      entry, while `HEAD`, lane statuses, and dirty counts have moved through
      later 04:00-era handoffs. The blocker and next intervention did not
      materially change in this audit, so `progress.md` was not edited, but it
      is not a reliable current owner/session/next-task surface without opening
      lane files.

12. **Medium - shell/process boundaries are still present and must remain
    oracle or coordination tooling only.**
    - Paths: `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/markerpdf/src/ChunkConversionPlanner.php:142`,
      `lanes/markerpdf/tests/ChunkConversionPlannerTest.php:49`, and
      `tools/generate-dashboard.php:197`.
    - Goal requirement at risk: `goal.md:1` and `goal.md:30`.
    - Evidence: Gitoxide test helpers still use `proc_open()` for local oracle
      checks, markerPDF records shell lifecycle metadata, and dashboard
      generation shells out for Git metadata. Those may be acceptable as
      explicit oracle/tooling boundaries, but any runtime behavior depending on
      process launch must be excluded from native implementation progress until
      captured as fixtures or reimplemented in PHP.

## Required Intervention

Keep the integration hold. The next best intervention has not changed:

1. Freeze writers, dashboard updater, capacity executor/controller, evaluator,
   watchdog, lane workers, and focused/broad/root runners.
2. Take two stable polls of `HEAD`, tracked dirty rows, default status rows,
   and root-runner process state.
3. Accept one lane batch at a time, normalize manifest/status schema fields,
   and reject plan-only, shell-backed, generated-fixture-only, or too-broad
   dependency work as progress.
4. Run focused verification and `git diff --check` for the accepted batch.
5. If `pgrep -af '^php tools/run-tests\.php( |$)'` is clear, run exactly one
   serialized no-argument `php tools/run-tests.php` from that frozen snapshot.
6. Regenerate `porting.html` and `porting-summary.json` from the accepted
   commit, then commit or reject the batch.
