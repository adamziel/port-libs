# Independent Audit - 2026-05-24T07:20Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, recent Git history, targeted shell-out search,
and the required PHP harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, shell-outs, whole applications,
external converter wrappers, and hidden process launchers are treated as
non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T07:15:34Z through 2026-05-24T07:21:04Z
HEAD moved during audit: b67342d3 -> b1b7b26774f7
recent commits: b1b7b267 Record integration hold status; e0392a74 Record integration hold status; b67342d3 Refresh independent audit status; 8ac9abea Record integration hold status
branch divergence: main...origin/main [ahead 754, behind 68]
tracked dirty files: 315 -> 317
default status rows including untracked: 15220 -> 15289
git diff --shortstat: 315 files changed, 191248 insertions(+), 28681 deletions(-) -> 317 files changed, 191700 insertions(+), 28138 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; 13 candidate, 10 deferred; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
2026-05-24T07:15:34Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
2026-05-24T07:15:47Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
2026-05-24T07:16:35Z pgrep -af '^php tools/run-tests\.php( |$)':
3037652 php tools/run-tests.php
owner evidence: unavailable because PID 3037652 exited before the follow-up ps sample; treated as a blocked duplicate-run gate
2026-05-24T07:16:47Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
2026-05-24T07:16:59Z pgrep -af '^php tools/run-tests\.php( |$)': no rows
2026-05-24T07:19:35Z pgrep -af '^php tools/run-tests\.php( |$)':
3063101 php tools/run-tests.php lanes/syncthing/tests/ProtocolValidationTest.php ... lanes/syncthing/tests/ServiceDeviceIdTest.php
owner evidence: PID 3063101 user claude, parent 3062982, started Sun May 24 07:19:02 2026, elapsed 00:37 at sample, state R+
2026-05-24T07:21:04Z pgrep -af '^php tools/run-tests\.php( |$)':
3068754 php tools/run-tests.php lanes/syncthing/tests
owner evidence: PID 3068754 user claude, parent 2964082, started Sun May 24 07:19:57 2026, elapsed 01:13 at owner sample, state Rs
```

I did not start `php tools/run-tests.php`. The checkout was moving, and the
exact gate briefly matched a no-argument root harness started outside this
audit before owner sampling could capture it, then later matched an active
focused Syncthing harness. A root run from this audit would not provide
acceptance evidence for a frozen snapshot.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37` through `progress.md:39`,
     `audits/integration-status.md:1` through
     `audits/integration-status.md:41`, recent Git history.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small committed slices, verification before integration, and
     honest repo-wide checks.
   - Evidence: `HEAD` moved during this audit from `b67342d3` to
     `b1b7b26774f7`; branch divergence is `[ahead 754, behind 68]`; default
     status rows moved `15220 -> 15289`; shortstat changed by hundreds of
     insertions and deletions while sampling; tracked dirty scope moved
     `315 -> 317` files across all lanes and coordination artifacts.

2. **Critical - no root-harness result is acceptable for the current snapshot.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md:39` through
     `audits/integration-status.md:47`, `progress.md:39`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the integration hold records a capacity-owned green root run
     from dirty snapshot `caa795d044e5`; current `HEAD` is `b1b7b26774f7`.
     This audit's exact gate then briefly matched transient no-argument root
     PID `3037652` and active focused Syncthing PIDs `3063101` and `3068754`
     owned by `claude`, while the worktree continued changing. That is useful
     diagnostics, not a frozen acceptance checkpoint.

3. **Critical - `porting.html` and `porting-summary.json` fail the current
   dashboard contract.**
   - Paths: `porting.html:32` through `porting.html:38`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with denominator, mapped tests, PHP pass/fail, scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current observed `HEAD` is `b1b7b26774f7`.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json:10` through `porting-summary.json:213`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require reliable denominators, mapped counts, PHP pass/fail counts,
     blockers, and current status.
   - Evidence: current manifest/status values versus dashboard include
     Difftastic `883 total / 589 mapped / 2908 pass` vs dashboard
     `735 / 374 / 374`; Dolt `613 mapped / 402 pass` vs `613 / 356`;
     esbuild `2567 / 380 / 380` vs `2567 / 311 / 311`; Gitoxide
     `2877 / 2877 / 6454` vs `2877 / 2751 / 5634`; libsqlite
     `1589 / 324 / 324` vs `1589 / 286 / 286`; LightningCSS
     `3532 / 2505 / 3502` vs `3532 / 1732 / 2197`; markerPDF
     `365 / 316 / 452` vs `330 / 280 / 416`; Pandoc
     `2276 / 1628 / 328` vs `2276 / 1061 / 278`; Quadrable
     `55 / 55 / 215` vs `55 / 55 / 190`; rclone
     `1601 / 834 / 836` vs `1601 / 698 / 698`; Readability
     `1984 / 1984 / 236` vs `1984 / 1984 / 204`; and Syncthing
     `658 / 658 / 6860` vs `658 / 658 / 4579`.

5. **High - manifest schema is still not normalized enough for durable
   coordination.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2464`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:38`, and `goal.md:45`
     require real upstream denominators and comparable dashboard fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is still a prose
     string, while its numeric mapped count lives separately. Across manifests,
     PHP pass/fail evidence is not exposed through a consistent
     `nativeImplementation` key, forcing consumers to merge lane-status prose,
     assertion counts, behavior counts, and dashboard strings.

6. **High - `progress.md` active-lane handoff labels are stale relative to
   current lane statuses.**
   - Paths: `progress.md:98` through `progress.md:111`,
     `lanes/gitoxide/lane-status.json:9` through
     `lanes/gitoxide/lane-status.json:12`,
     `lanes/lightningcss/lane-status.json:9` through
     `lanes/lightningcss/lane-status.json:12`,
     `lanes/markerpdf/lane-status.json:9` through
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/syncthing/lane-status.json:9` through
     `lanes/syncthing/lane-status.json:12`.
   - Requirement at risk: `goal.md:44` requires `progress.md` to include
     current lane owners, next tasks, blockers, and percentage estimates.
   - Evidence: `progress.md` still names older handoffs such as Gitoxide SSH
     config-options, LightningCSS trig/math, markerPDF benchmark
     file-inventory, Pandoc NativeWriter figure/citation, and Syncthing system
     log. Current lane statuses now report Gitoxide split-index link writing,
     LightningCSS color-mix fallback, markerPDF ToUnicode CMap decoding,
     Pandoc DOCX track-change decisions, and Syncthing debug-file routing.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:35` through `goal.md:40` require real
     denominator parity, fixture parity, edge/error behavior, and honest
     blockers.
   - Evidence: most dashboard rows report `98-99%`, but current lane statuses
     still say root aggregate verification is pending, latest commits are
     pending or uncommitted, full upstream runners remain unexecuted or
     bounded, and major lanes still rely on static inventory plus focused
     slices rather than accepted suite parity.

8. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:7` through
     `dependency-backlog.json:124`, `dependency-backlog.json:169` through
     `dependency-backlog.json:216`, `dependency-backlog.json:382` through
     `dependency-backlog.json:440`, `porting.html:72` through
     `porting.html:78`, `porting-summary.json:215` through
     `porting-summary.json:249`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` has 23 items, 13 candidate and 10
     deferred, with no active support-library port. The dashboard still shows
     22 dependency rows and omits `pandoc-doctemplates-core`. Rich-function
     gaps remain for ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB,
     EPUB, ODT, doctemplates, citations/CSL, math/TeX, PDF text, PDF render
     planning, OCR/layout, table geometry, Unicode, charset, source maps,
     protobuf, checksum/hash, SQL/storage, archive streams, glob/pathspec, and
     provider metadata.

9. **High - rclone dependency expansion is lane-local and too broad to count
   as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:9` through
     `lanes/rclone/lane-status.json:12`,
     `dependency-backlog.json:25` through `dependency-backlog.json:42`,
     `dependency-backlog.json:382` through `dependency-backlog.json:440`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone carries lane-local WebDAV XML, PROPFIND/PROPPATCH/LOCK,
     gzip, middleware, auth-proxy, directory template, URL decoding, VFS, and
     provider metadata work. These may be valid rclone slices, but they are not
     a shared support-library port without a dependency-specific denominator,
     malformed/corrupt cases, activation gate, and reusable ownership.

10. **High - markerPDF still mixes native progress with external/runtime
    orchestration plans.**
    - Paths: `lanes/markerpdf/lane-status.json:9` through
      `lanes/markerpdf/lane-status.json:12`,
      `dependency-backlog.json:169` through `dependency-backlog.json:216`.
    - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
      wrappers, bridge calls, or shell-outs to upstream binaries as native
      implementation progress.
    - Evidence: markerPDF has real native PDF stream/CMap work, but the
      phase/blocker text still mixes in benchmark CLI plans, `marker_server`
      and `marker_app` runtime plans, Tesseract/Ghostscript/Pandoc/XeLaTeX/
      Poetry/Streamlit/FastAPI/Uvicorn/Torch/Surya/Texify/Nougat boundaries,
      and `chunk_convert.sh` / `convert.py` shell lifecycle plans. Those are
      preflight or oracle evidence unless a bounded native component owns the
      behavior directly.

11. **Medium - Readability's full-fixture mapping claim still hides known
    parity gaps.**
    - Paths: `lanes/readability/lane-status.json:5`,
      `lanes/readability/lane-status.json:12`, `porting.html:66`,
      `porting-summary.json:181` through `porting-summary.json:195`.
    - Requirement at risk: `goal.md:35` and `goal.md:37` require meaningful
      fixture parity and upstream tests as the source of truth.
    - Evidence: current lane status reports two remaining copied-fixture
      normalized full-text mismatches: `firefox-nightly-blog` and
      `wikipedia-2`. The dashboard still presents `1984 / 1984` mapped and
      `99.0%` without surfacing the remaining parity gap.

12. **Medium - test-time and coordination shell-outs must remain explicit
    oracle/tooling, not native progress.**
    - Paths: `tools/generate-dashboard.php:197`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/GitUrlTest.php:104`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39`
      require native ports and reproducible generated artifacts, with bridge
      code counted only as temporary oracle tooling.
    - Evidence: the targeted PHP search found `shell_exec()` in dashboard
      generation and `proc_open()` in Gitoxide tests. No lane implementation
      process shell-out was found by that search, apart from unrelated
      `PDO::exec()` SQL calls in Syncthing. Keep these shell-outs documented as
      coordination/oracle boundaries and out of native implementation credit.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop or wait out
active writers/status publishers and PHP shards; take two stable polls of
`HEAD`, status row counts, shortstat, exact PHP runner state, focused runner
state, Dolt runner state, capacity/disk state, dashboard publication state, and
relevant log mtimes; accept or reject one lane-scoped batch; normalize schema
and count fields for that batch; run focused verification plus
`git diff --check`; run exactly one serialized no-argument
`php tools/run-tests.php` from that same frozen snapshot only if the exact
process gate is empty; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the accepted commit; then commit
or reject.
