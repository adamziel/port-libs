# Independent Audit - 2026-05-24T07:38Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`,
`audits/integration-status.md`, recent Git history, and the required PHP
harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T07:31:29Z through 2026-05-24T07:38:19Z; status/shortstat sample through 2026-05-24T07:36:47Z
HEAD moved during audit: 7440cb6ae5a2 -> 251ae91aa895 -> a808fccbc3f6
recent commits: a808fccb Record integration hold status; 251ae91a Record integration hold status; 7440cb6a Refresh independent audit status; a0bb34ac Record integration hold status
branch divergence: main...origin/main [ahead 758, behind 68] -> [ahead 760, behind 68]
tracked dirty rows: 316 -> 315 -> 317
default status rows including untracked: 15426 -> 15484 -> 15495
git diff --shortstat: 316 files changed, 193486 insertions(+), 28265 deletions(-) -> 315 files changed, 193519 insertions(+), 28272 deletions(-) -> 317 files changed, 193261 insertions(+), 27713 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; 13 candidate, 10 deferred; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
2026-05-24T07:31:29Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T07:33:03Z pgrep -af '^php tools/run-tests\.php( |$)':
3238866 php tools/run-tests.php lanes/syncthing/tests
3242832 php tools/run-tests.php
3244227 php tools/run-tests.php lanes/syncthing/tests/ProtocolValidationTest.php ...

owner evidence:
3238866 claude parent 3179607 started Sun May 24 07:32:26 2026 elapsed 00:40 state Rs
3242832 claude parent 3242645 started Sun May 24 07:32:38 2026 elapsed 00:28 state R+
3244227 claude parent 3244135 started Sun May 24 07:32:42 2026 elapsed 00:25 state R+

2026-05-24T07:36:47Z pgrep -af '^php tools/run-tests\.php( |$)':
no rows

2026-05-24T07:38Z final pre-commit pgrep -af '^php tools/run-tests\.php( |$)':
3324660 php tools/run-tests.php
3326283 php tools/run-tests.php lanes/syncthing/tests/ProgressEmitterTest.php ...

owner evidence:
3324660 claude parent 3324616 started Sun May 24 07:37:14 2026 elapsed 01:00 state R+
3326283 claude parent 3326192 started Sun May 24 07:37:19 2026 elapsed 00:55 state R+
```

I did not start `php tools/run-tests.php`. The exact gate matched an active
no-argument root harness owned by `claude` after the first stability sample;
the later gate briefly cleared, but the checkout had moved again, and the final
pre-commit gate matched another active no-argument root harness. An audit-owned
run would not verify a frozen source snapshot.

Additional checks run by this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json lanes/*/lane-status.json porting-summary.json dependency-backlog.json
passed

rg for process shell-outs in PHP source/tests:
tools/generate-dashboard.php: shell_exec() for git metadata
lanes/gitoxide/tests/FetchV2SessionTest.php, FetchResponseTest.php, GitUrlTest.php: proc_open() oracle/test helpers
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md`, `audits/integration-status.md`, recent Git history.
   - Requirement at risk: `goal.md` requires small, reviewable committed slices
     with verification before integration, and honest repo-wide checks.
   - Evidence: `HEAD` moved during this audit from `7440cb6ae5a2` to
     `251ae91aa895` and then `a808fccbc3f6`; branch divergence moved from
     `[ahead 758, behind 68]` to `[ahead 760, behind 68]`;
     untracked-inclusive status rows moved `15426 -> 15484 -> 15495`;
     shortstat changed while sampling. The dirty scope still spans every
     priority lane plus coordination artifacts and shared scripts.

2. **Critical - no root-harness result is acceptable for the current snapshot.**
   - Paths: `tools/run-tests.php`, `audits/latest.md`,
     `audits/integration-status.md`, `progress.md`.
   - Requirement at risk: `goal.md` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required exact pre-root gate first returned no rows, then
     matched active no-argument root PID `3242832` owned by `claude`, plus
     focused Syncthing PHP harnesses `3238866` and `3244227`; a later gate was
     clear only after the tree had moved again, and the final pre-commit gate
     matched active no-argument root PID `3324660`. Any root result from this
     interval is diagnostics only, not acceptance evidence.

3. **Critical - the dashboard contract is currently broken.**
   - Paths: `porting.html`, `porting-summary.json`.
   - Requirement at risk: `goal.md` requires a current dashboard with
     denominator, mapped tests, PHP pass/fail, WordPress scenarios, phase,
     audit, current work, blocker, and commit.
   - Evidence: both files still advertise generated time
     `2026-05-23 23:43:54 UTC`, source snapshot `79768df0c427`, and average
     progress `97.7%`, while current observed `HEAD` is `a808fccbc3f6`. The
     dashboard also reports 22 dependency rows, while `dependency-backlog.json`
     now has 23.

4. **High - dashboard, manifest, and lane-status counts disagree across active
   lanes.**
   - Paths: `porting.html`, `porting-summary.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires reliable upstream denominators,
     mapped counts, PHP pass/fail counts, blockers, and current status.
   - Evidence: current manifest/status values versus dashboard include:
     Difftastic `888 total / 607 mapped / 2923 pass` vs dashboard
     `735 / 374 / 374`; Dolt prose total / `613 mapped / 403 pass` vs
     `inventory / 613 / 356`; esbuild `2567 / 382 / 382 pass in status` vs
     `2567 / 311 / 311`; Gitoxide manifest `2877 mapped / 6349 pass` and
     status `6490 pass` vs `2751 / 5634`; libsqlite `1589 / 325 / 326` vs
     `1589 / 286 / 286`; LightningCSS `3532 / 2595 / 3556` vs
     `3532 / 1732 / 2197`; markerPDF `366 / 317 / 454` vs `330 / 280 / 416`;
     Pandoc `2276 / 1643 / 329` vs `2276 / 1061 / 278`; Quadrable
     `55 / 55 / 216` vs `55 / 55 / 190`; rclone `1601 / 838 / 838` vs
     `1601 / 698 / 698`; Readability `1984 / 1984 / 237` vs
     `1984 / 1984 / 204`; Syncthing `658 / 658 / 6937` vs `658 / 658 / 4579`.

5. **High - manifest schema is still not normalized enough for durable
   coordination.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `tools/generate-dashboard.php`.
   - Requirement at risk: `goal.md` requires real upstream denominators and
     comparable dashboard fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is still a long
     prose runner narrative rather than a numeric denominator. Several
     manifests expose PHP evidence through different keys or prose fields, so
     consumers must merge manifest prose, lane-status strings, assertion counts,
     behavior counts, and stale dashboard values.

6. **High - `progress.md` remains stale as a coordination surface even after
   repeated audit refreshes.**
   - Paths: `progress.md`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md` requires `progress.md` to include current
     lane owners, next tasks, blockers, and percentage estimates.
   - Evidence: the top audit log is current, but the active-lanes table still
     names old handoffs such as Gitoxide SSH config-options, LightningCSS
     trig/math, markerPDF benchmark file-inventory, Pandoc NativeWriter
     figure/citation, Syncthing system log, and rclone VFS Statfs/usage. Current
     lane statuses instead report Gitoxide v4 path-compressed index writing,
     LightningCSS Lab/Oklab color-mix, markerPDF PDF font Differences decoding,
     Pandoc ODT multi-header tables, Syncthing debug support/profile, and
     rclone WebDAV COPY/MOVE normalized ordering.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html`, `lanes/*/lane-status.json`, `progress.md`.
   - Requirement at risk: `goal.md` says passing tests are not enough; each
     lane needs meaningful upstream parity, fixture parity, error behavior, and
     honest blockers.
   - Evidence: dashboard rows report `92-99%`, but every current lane-status
     handoff still says root aggregate verification is pending, latest work is
     pending/uncommitted or lane-local, and full upstream runners remain
     unexecuted, bounded, unavailable, or blocked for major lanes.

8. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json`, `porting.html`,
     `porting-summary.json`.
   - Requirement at risk: this audit requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` has 23 items, 13 candidate and 10
     deferred, with no active support-library port. The source dashboard still
     shows 22 rows and omits `pandoc-doctemplates-core`. Rich-function gaps
     remain for ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB, EPUB,
     ODT, doctemplates, citations/CSL, math/TeX, PDF text, PDF render planning,
     OCR/layout, table geometry, Unicode, charset, source maps, protobuf,
     checksum/hash, SQL/storage, archive streams, glob/pathspec, and provider
     metadata.

9. **High - rclone dependency expansion is lane-local and too broad to count as
   shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json`, `dependency-backlog.json`.
   - Requirement at risk: this audit requires dependency expansion to be
     bounded, gated, tested, shared where appropriate, and backed by a
     dependency-specific denominator.
   - Evidence: rclone carries lane-local WebDAV XML, PROPFIND/PROPPATCH/LOCK,
     gzip, middleware, auth-proxy, directory templates, URL decoding, VFS, and
     provider metadata work. These may be valid rclone slices, but they are not
     accepted support-library ports without separate denominators,
     malformed/corrupt cases, activation gates, and reusable ownership.

10. **High - markerPDF still mixes native evidence with external/runtime
    orchestration plans.**
    - Paths: `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json`,
      `lanes/markerpdf/lane-status.json`, `dependency-backlog.json`.
    - Requirement at risk: `goal.md` forbids counting wrappers, bridge calls,
      shell-outs, or external converter/runtime execution as native port
      progress.
    - Evidence: markerPDF has real native PDF stream/filter/CMap/font-decoding
      work, but the status and manifest still carry benchmark CLI plans,
      `marker_server`, `marker_app`, Tesseract/Ghostscript/Pandoc/XeLaTeX/
      Poetry/Streamlit/FastAPI/Uvicorn/Torch/Surya/Texify/Nougat boundaries,
      and `chunk_convert.sh` / `convert.py` lifecycle plans. Those remain
      preflight/oracle evidence unless a bounded native PHP component owns the
      behavior.

11. **Medium - Readability's full-mapping claim still hides a known parity
    gap.**
    - Paths: `lanes/readability/lane-status.json`, `porting.html`,
      `porting-summary.json`.
    - Requirement at risk: `goal.md` requires meaningful fixture parity and
      upstream tests as the source of truth.
    - Evidence: current lane status still reports one known copied-fixture
      normalized full-text mismatch, `firefox-nightly-blog`. The dashboard
      presents `1984 / 1984` mapped and `99.0%` without surfacing that gap.

12. **Medium - test-time and coordination shell-outs must remain explicit
    oracle/tooling, not native progress.**
    - Paths: `tools/generate-dashboard.php`,
      `lanes/gitoxide/tests/GitUrlTest.php`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php`,
      `lanes/gitoxide/tests/FetchResponseTest.php`.
    - Requirement at risk: `goal.md` requires native ports and reproducible
      generated artifacts, with bridge code counted only as temporary oracle
      tooling.
    - Evidence: targeted PHP search found `shell_exec()` in dashboard metadata
      generation and `proc_open()` in Gitoxide tests. No lane implementation
      process shell-out was found by that search. Keep these documented as
      coordination/oracle boundaries and out of native implementation credit.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop or wait out
active writers/status publishers and PHP shards; take two stable polls of
`HEAD`, tracked/default status rows, shortstat, exact PHP runner state, focused
runner state, Dolt runner state, capacity/disk state, dashboard publication
state, and relevant log mtimes; accept or reject one lane-scoped batch; normalize
schema and count fields for that batch; run focused verification plus
`git diff --check`; run exactly one serialized no-argument
`php tools/run-tests.php` from that same frozen snapshot only if the exact
process gate is empty; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the accepted commit; then commit
or reject.
