# Independent Audit - 2026-05-24T07:28Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, recent Git history, and the required PHP
harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider configs,
or auth files. Bridge code, generated fixtures, shell-outs, whole applications,
external converter wrappers, and hidden process launchers are treated as
non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T07:23:28Z through 2026-05-24T07:28:03Z
HEAD moved during audit: ef3eea374e65 -> a0bb34ac2321
recent commits: a0bb34ac Record integration hold status; 2b8a5277 Record integration hold status; ef3eea37 Refresh independent audit status; b1b7b267 Record integration hold status
branch divergence: main...origin/main [ahead 757, behind 68]
tracked dirty files: 315 -> 317
default status rows including untracked: 15434 -> 15473
git diff --shortstat: 315 files changed, 192057 insertions(+), 28066 deletions(-) -> 317 files changed, 192971 insertions(+), 28322 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; 13 candidate, 10 deferred; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
2026-05-24T07:23:28Z pgrep -af '^php tools/run-tests\.php( |$)':
3095269 php tools/run-tests.php
3096255 php tools/run-tests.php lanes/rclone/tests lanes/syncthing/tests
owner evidence for no-argument root: PID 3095269 user claude, parent 3095224, started Sun May 24 07:23:28 2026, elapsed 00:37 at owner sample, state R+
2026-05-24T07:24:16Z pgrep -af '^php tools/run-tests\.php( |$)':
3095269 php tools/run-tests.php
owner evidence: PID 3095269 user claude, parent 3095224, started Sun May 24 07:23:28 2026, elapsed 00:48 at owner sample, state R+
2026-05-24T07:24:40Z pgrep -af '^php tools/run-tests\.php( |$)':
3095269 php tools/run-tests.php
owner evidence: PID 3095269 user claude, parent 3095224, started Sun May 24 07:23:28 2026, elapsed 01:19 at owner sample, state R+
2026-05-24T07:26Z pgrep -af '^php tools/run-tests\.php( |$)':
3163644 php tools/run-tests.php lanes/quadrable/tests
owner evidence: unavailable because PID 3163644 exited before the follow-up ps sample
2026-05-24T07:28:03Z pgrep -af '^php tools/run-tests\.php( |$)':
3171998 php tools/run-tests.php lanes/syncthing/tests/BasicFilesystemWatchEventSourceTest.php ...
3172061 php tools/run-tests.php lanes/syncthing/tests/ConfigDevicesTest.php ...
3172226 php tools/run-tests.php lanes/syncthing/tests/ProtocolValidationTest.php ...
3172282 php tools/run-tests.php lanes/syncthing/tests/ServiceLanguageTest.php ...
owner evidence: PIDs 3171998, 3172061, and 3172226 owned by claude, parent PIDs 3171871/3171929/3172113, started Sun May 24 07:28:02 2026, elapsed 00:08 at owner sample, state R+
```

I did not start `php tools/run-tests.php`. The exact gate matched an active
no-argument root harness owned by `claude` earlier in the audit and later
matched focused lane harnesses while the checkout continued moving. A duplicate
audit-owned root run would violate the run gate during the root window and
would not produce acceptance evidence for a frozen snapshot after the tree
moved.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `audits/integration-status.md`, recent Git history.
   - Requirement at risk: `goal.md` requires small reviewable committed slices,
     verification before integration, and honest repo-wide checks.
   - Evidence: `HEAD` moved during this audit from `ef3eea374e65` to
     `a0bb34ac2321`; branch divergence is `[ahead 757, behind 68]`;
     untracked-inclusive status rows moved `15434 -> 15473`; shortstat moved
     from 315 to 317 changed files while sampling; dirty scope spans all lanes
     plus coordination artifacts.

2. **Critical - no root-harness result is acceptable for the current snapshot.**
   - Paths: `tools/run-tests.php`, `audits/integration-status.md`,
     `progress.md`.
   - Requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the exact pre-root gate matched active no-argument root PID
     `3095269` owned by `claude` during the audit; other samples matched
     focused rclone/syncthing, quadrable, and syncthing shard PIDs. The current
     worktree moved while that root was active and then moved again afterward,
     so even a later green result is not an acceptance result for a frozen
     source snapshot.

3. **Critical - `porting.html` and `porting-summary.json` fail the current
   dashboard contract.**
   - Paths: `porting.html`, `porting-summary.json`.
   - Requirement at risk: `goal.md` requires a current dashboard with
     denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current observed `HEAD` is `a0bb34ac2321`.

4. **High - dashboard, manifest, and lane-status counts disagree across active
   lanes.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires reliable denominators, mapped
     counts, PHP pass/fail counts, blockers, and current status.
   - Evidence: current manifest/status values versus dashboard include
     Difftastic `883 total / 598 mapped / 2918 pass` vs dashboard
     `735 / 374 / 374`; Dolt prose total / `613 mapped / 402 pass` vs
     dashboard `inventory / 613 / 356`; esbuild `2567 / 380 / 380` vs
     `2567 / 311 / 311`; Gitoxide `2877 / 2877 / 6463` vs
     `2877 / 2751 / 5634`; libsqlite `1589 / 325 / 325` vs
     `1589 / 286 / 286`; LightningCSS `3532 / 2532 / 3556` vs
     `3532 / 1732 / 2197`; markerPDF `365 / 316 / 453` vs `330 / 280 / 416`;
     Pandoc `2276 / 1643 / 329` vs `2276 / 1061 / 278`; Quadrable
     `55 / 55 / 215` vs `55 / 55 / 190`; rclone `1601 / 836 / 836` vs
     `1601 / 698 / 698`; Readability `1984 / 1984 / 237` vs
     `1984 / 1984 / 204`; and Syncthing `658 / 658 / 6937` vs
     `658 / 658 / 4579`.

5. **High - manifest schema is still not normalized enough for durable
   coordination.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `tools/generate-dashboard.php`.
   - Requirement at risk: `goal.md` requires real upstream denominators and
     comparable dashboard fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is still a long
     prose runner narrative rather than a numeric denominator. Across manifests,
     PHP pass/fail evidence is not consistently exposed under a normalized
     manifest key, so consumers have to merge manifest prose, lane-status
     fields, assertion counts, behavior counts, and stale dashboard strings.

6. **High - `progress.md` active-lane handoff labels are stale relative to
   current lane statuses.**
   - Paths: `progress.md`, `lanes/gitoxide/lane-status.json`,
     `lanes/lightningcss/lane-status.json`,
     `lanes/markerpdf/lane-status.json`, `lanes/syncthing/lane-status.json`.
   - Requirement at risk: `goal.md` requires `progress.md` to include current
     lane owners, next tasks, blockers, and percentage estimates.
   - Evidence: `progress.md` still names older handoffs such as Gitoxide SSH
     config-options, LightningCSS trig/math, markerPDF benchmark
     file-inventory, Pandoc NativeWriter figure/citation, and Syncthing system
     log. Current lane statuses now report Gitoxide split-index link writing,
     LightningCSS Lab/Oklab color-mix, markerPDF WinAnsi font decoding,
     Pandoc DOCX insertion/deletion decisions, and Syncthing debug support.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires real denominator parity, fixture
     parity, edge/error behavior, and honest blockers.
   - Evidence: most dashboard rows report `98-99%`, but current lane statuses
     still say root aggregate verification is pending, latest commits are
     pending or uncommitted, full upstream runners remain unexecuted or
     bounded, and major lanes still rely on static inventory plus focused
     slices rather than accepted suite parity.

8. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json`, `porting.html`,
     `porting-summary.json`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` has 23 items, 13 candidate and 10
     deferred, with no active support-library port. The source dashboard still
     shows 22 dependency rows and omits `pandoc-doctemplates-core`. Rich-function
     gaps remain for ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB,
     EPUB, ODT, doctemplates, citations/CSL, math/TeX, PDF text, PDF render
     planning, OCR/layout, table geometry, Unicode, charset, source maps,
     protobuf, checksum/hash, SQL/storage, archive streams, glob/pathspec, and
     provider metadata.

9. **High - rclone dependency expansion is lane-local and too broad to count as
   shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json`, `dependency-backlog.json`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone carries lane-local WebDAV XML, PROPFIND/PROPPATCH/LOCK,
     gzip, middleware, auth-proxy, directory template, URL decoding, VFS, and
     provider metadata work. These may be valid rclone slices, but they are not
     a shared support-library port without a dependency-specific denominator,
     malformed/corrupt cases, activation gate, and reusable ownership.

10. **High - markerPDF still mixes native progress with external/runtime
    orchestration plans.**
    - Paths: `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
    - Requirement at risk: `goal.md` forbids counting wrappers, bridge calls, or
      shell-outs to upstream binaries as native implementation progress.
    - Evidence: markerPDF has real native PDF stream/CMap/font-decoding work,
      but status text still mixes in benchmark CLI plans, `marker_server`,
      `marker_app`, Tesseract/Ghostscript/Pandoc/XeLaTeX/Poetry/Streamlit/
      FastAPI/Uvicorn/Torch/Surya/Texify/Nougat boundaries, and
      `chunk_convert.sh` / `convert.py` lifecycle plans. Those remain preflight
      or oracle evidence unless a bounded native component owns the behavior.

11. **Medium - Readability's full-fixture mapping claim still hides a known
    parity gap.**
    - Paths: `lanes/readability/lane-status.json`, `porting.html`,
      `porting-summary.json`.
    - Requirement at risk: `goal.md` requires meaningful fixture parity and
      upstream tests as the source of truth.
    - Evidence: current lane status reports one remaining copied-fixture
      normalized full-text mismatch, `firefox-nightly-blog`. The dashboard
      still presents `1984 / 1984` mapped and `99.0%` without surfacing the
      remaining parity gap.

12. **Medium - test-time and coordination shell-outs must remain explicit
    oracle/tooling, not native progress.**
    - Paths: `tools/generate-dashboard.php`,
      `lanes/gitoxide/tests/GitUrlTest.php`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php`,
      `lanes/gitoxide/tests/FetchResponseTest.php`.
    - Requirement at risk: `goal.md` requires native ports and reproducible
      generated artifacts, with bridge code counted only as temporary oracle
      tooling.
    - Evidence: targeted PHP search found `shell_exec()` in dashboard
      generation and `proc_open()` in Gitoxide tests. No lane implementation
      process shell-out was found by that search. Keep these shell-outs
      documented as coordination/oracle boundaries and out of native
      implementation credit.

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
