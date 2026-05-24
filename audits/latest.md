# Independent Audit - 2026-05-24T07:05Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, every root `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
current `lanes/*/lane-status.json`, `dependency-backlog.json`, recent Git
history, and the required PHP harness process gate.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24T07:02:46Z and 2026-05-24T07:04:12Z
HEAD: a0e3544ec667
recent commits: a0e3544e Record integration hold status; a019abce Refresh independent audit status; 9c62c3c0 Record integration hold status; ec658ab1 Record integration hold status; 64b188a9 Refresh independent audit status
branch divergence: main...origin/main [ahead 748, behind 68]
default status rows including untracked: 15020 -> 15028
git diff --shortstat: 315 files changed, 189591 insertions(+), 29021 deletions(-) -> 315 files changed, 189594 insertions(+), 28677 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; 13 candidate, 10 deferred; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T07:02:46Z: no rows
pgrep -af '^php tools/run-tests\.php( |$)' at 2026-05-24T07:04:12Z: no rows
```

I did not start `php tools/run-tests.php`. The exact process gate was clear in
both samples, but the checkout was not stable enough for an acceptance run:
status rows changed by 8, the shortstat changed, lane statuses still report
pending or uncommitted handoffs, and the dashboard is stale relative to the
current commit.

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:37` through `progress.md:39`,
     `lanes/*/lane-status.json`, recent Git history.
   - Requirement at risk: `goal.md:29`, `goal.md:48`, and `goal.md:49`
     require small reviewable slices, verification before integration, and
     honest repo-wide checks.
   - Evidence: current `HEAD` is `a0e3544ec667` and the branch is
     `main...origin/main [ahead 748, behind 68]`. During this audit, default
     status rows changed `15020 -> 15028` and shortstat changed from `315 files
     changed, 189591 insertions(+), 29021 deletions(-)` to `315 files changed,
     189594 insertions(+), 28677 deletions(-)`. Current lane statuses still
     report `pending`, `uncommitted`, or root-acceptance ownership across all
     lanes.

2. **Critical - no no-argument root-harness result can be accepted for this
   snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required exact process gate returned no rows at both audit
     samples, but the tree moved between samples and every lane still leaves
     aggregate root verification to the supervisor/integrator. Starting a root
     run from this moving aggregate would not produce acceptance evidence for a
     frozen snapshot.

3. **Critical - `porting.html` and `porting-summary.json` fail the current
   dashboard contract.**
   - Paths: `porting.html:38`, `porting.html:56` through `porting.html:67`,
     `porting-summary.json:2` through `porting-summary.json:8`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require a current
     dashboard with upstream denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still advertises average progress `97.7%`,
     generated time `2026-05-23 23:43:54 UTC`, and source snapshot
     `79768df0c427`, while current `HEAD` is `a0e3544ec667`. It cannot be used
     as a current publication source.

4. **High - dashboard, manifest, and lane-status counts disagree across every
   active lane.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `porting-summary.json`, `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:25`, `goal.md:44`, and `goal.md:45`
     require reliable denominators, mapped counts, PHP pass/fail counts,
     blockers, and current status.
   - Evidence: current manifest/status values versus dashboard include
     Difftastic `875 total / 580 mapped / 2898 pass` vs dashboard
     `735 / 374 / 374`; Dolt `613 mapped / 400 pass` vs `613 / 356`; esbuild
     `2567 / 375 / 375` vs `2567 / 311 / 311`; Gitoxide
     `2877 / 2877 / 6443` vs `2877 / 2751 / 5634`; libsqlite
     `1589 / 323 / 323` vs `1589 / 286 / 286`; LightningCSS
     `3532 / 2504 / 3499` vs `3532 / 1732 / 2197`; markerPDF
     `364 / 315 / 452` vs `330 / 280 / 416`; Pandoc
     `2276 / 1598 / 326` vs `2276 / 1061 / 278`; Quadrable `55 / 55 / 213`
     vs `55 / 55 / 190`; rclone `1601 / 835 / 835` vs `1601 / 698 / 698`;
     Readability `1984 / 1984 / 236` vs `1984 / 1984 / 204`; and Syncthing
     `658 / 658 / 6782` vs `658 / 658 / 4579`.

5. **High - manifest denominator schema remains non-normalized, and Dolt's
   canonical denominator field is stale slice prose.**
   - Paths: `lanes/difftastic/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2461`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`.
   - Requirement at risk: `goal.md:24`, `goal.md:25`, `goal.md:38`, and
     `goal.md:45` require real upstream denominators and comparable dashboard
     fields.
   - Evidence: Difftastic and Pandoc still put prose strings in
     `benchmarkDenominator.total`. Dolt's `benchmarkDenominator.total` still
     contains the older 06:05 FIND_IN_SET slice narrative even though
     `lanes/dolt/lane-status.json:5` now reports the 06:58 MAKE_SET slice and
     `400` PHP pass cases. The primary denominator field is not machine-safe.

6. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `lanes/*/lane-status.json`, `porting.html:56` through
     `porting.html:67`.
   - Requirement at risk: `goal.md:33` through `goal.md:40` require real
     denominator parity, fixture parity, edge/error behavior, and honest
     blockers.
   - Evidence: most lane statuses report `98-99%`, but the same files still
     say root aggregate verification is pending, latest commits are pending or
     uncommitted, and full upstream runners remain unexecuted, bounded, or
     static-only for major lanes. Focused lane tests are useful evidence; they
     are not accepted completion.

7. **High - essential optional-library coverage remains backlog-only, not
   accepted support-library ports.**
   - Paths: `dependency-backlog.json:3` through
     `dependency-backlog.json:436`, `porting.html:72` through
     `porting.html:114`.
   - Requirement at risk: this audit run requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: `dependency-backlog.json` has 23 items, 13 candidate and 10
     deferred, with no active support-library port. The dashboard still shows
     22 dependency rows because `pandoc-doctemplates-core` is absent from
     `porting.html`. Rich-function gaps remain for ZIP/package, XML/HTML5,
     DOCX/OpenXML, legacy DOC/CFB, EPUB, ODT, doctemplates, citations/CSL,
     math/TeX, PDF text, PDF render planning, OCR/layout, table geometry,
     Unicode, charset, source maps, protobuf, checksum/hash, storage codecs,
     archive streams, glob/pathspec, and provider metadata.

8. **High - rclone dependency expansion is lane-local and too broad to count
   as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:5`,
     `lanes/rclone/lane-status.json:8` through
     `lanes/rclone/lane-status.json:14`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1329` through
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:1344`.
   - Requirement at risk: this audit run requires dependency expansion to be
     bounded, gated, tested, and shared where appropriate.
   - Evidence: rclone carries lane-local WebDAV XML/PROPFIND/PROPPATCH/LOCK,
     gzip, middleware, auth-proxy, directory template, URL decoding, provider
     metadata, VFS, response, and reader helper work. These can be valid rclone
     slices, but they are not a shared support-library port without a
     dependency-specific denominator, malformed/corrupt cases, activation gate,
     and reusable ownership.

9. **High - markerPDF still mixes native progress with external/runtime
   orchestration plans.**
   - Paths: `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:9` through
     `lanes/markerpdf/lane-status.json:12`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:475`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1000` through
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:1038`.
   - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
     wrappers, bridge calls, or shell-outs to upstream binaries as native
     implementation progress.
   - Evidence: markerPDF has real native PDF stream/filter/text work, including
     the current ToUnicode CMap slice, but the manifest/status still mix in
     pdftext/pypdfium/Surya/Texify model stacks, Tesseract/Ghostscript/Pandoc/
     XeLaTeX/Poetry/Streamlit/FastAPI/Uvicorn plans, chunk-convert shell
     lifecycle plans, server startup/lifespan slots, and helper-script command
     plans. Those are preflight/oracle evidence unless a bounded native
     component owns the behavior directly.

10. **Medium - Readability's full-fixture claim still hides known parity gaps.**
    - Paths: `lanes/readability/lane-status.json:5`,
      `lanes/readability/lane-status.json:10`,
      `lanes/readability/lane-status.json:12`,
      `lanes/readability/UPSTREAM_TEST_MANIFEST.json:930`,
      `porting.html:66`.
    - Requirement at risk: `goal.md:35` and `goal.md:37` require meaningful
      fixture parity and upstream tests as source of truth.
    - Evidence: current lane status records three remaining copied-fixture
      normalized text mismatches: `firefox-nightly-blog`, `wikipedia-2`, and
      `wikipedia-3`. The dashboard still presents `1984 / 1984` mapped and
      `99.0%` without surfacing that parity gap.

11. **Medium - test-time shell-outs must remain oracle tooling, not native
    progress.**
    - Paths: `lanes/gitoxide/tests/FetchResponseTest.php:16`,
      `lanes/gitoxide/tests/FetchResponseTest.php:18`,
      `lanes/gitoxide/tests/GitUrlTest.php:69`,
      `lanes/gitoxide/tests/GitUrlTest.php:70`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:11`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:13`,
      `tools/generate-dashboard.php:197`.
    - Requirement at risk: `goal.md:1`, `goal.md:30`, and `goal.md:39`
      require native ports and reproducible generated artifacts, with bridge
      code counted only as temporary oracle tooling.
    - Evidence: Gitoxide tests invoke Git through `proc_open`, and the
      dashboard generator shells out for Git metadata. That can be acceptable
      as explicit fixture/oracle or coordination tooling only; it must not
      become implementation progress.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop or wait out
active writers/status publishers and PHP shards; take two stable polls of
`HEAD`, status row counts, shortstat, exact PHP runner state, capacity/disk
state, dashboard publication state, and relevant log mtimes; accept or reject
one lane-scoped batch; normalize schema/count fields for that batch; run
focused verification plus `git diff --check`; run exactly one serialized
no-argument `php tools/run-tests.php` from that same frozen snapshot if the
exact process gate remains empty; regenerate `progress.md`, `porting.html`,
`porting-summary.json`, and lane statuses from the accepted commit; then commit
or reject.
