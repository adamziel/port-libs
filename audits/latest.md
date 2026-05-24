# Independent Audit - 2026-05-24T08:04Z

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
UTC samples: 2026-05-24T07:58Z through 2026-05-24T08:04Z
sampled implementation HEAD moved during audit/commit handoff: 7fec69a94f1d -> e8bc6a1a -> 0500f313
recent commits before this audit-only commit: 0500f313 Record integration hold status; e8bc6a1a Record integration hold status; 7fec69a9 Refresh independent audit status
branch divergence after audit commit: main...origin/main [ahead 770, behind 68]
tracked dirty rows before/after audit edits: 321 -> 321
default status rows before/after audit edits: 15833 -> 15836 -> 15842
git diff --shortstat before/after audit edits: 321 files changed, 197977 insertions(+), 29281 deletions(-) -> 321 files changed, 198043 insertions(+), 29276 deletions(-) -> 321 files changed, 199149 insertions(+), 29467 deletions(-)
manifest/status JSON validation: jq reads succeeded for all 12 root lane manifests, all 12 lane-status files, porting-summary.json, and dependency-backlog.json
dependency backlog: 23 items; no active support-library port
dashboard snapshot: porting.html and porting-summary.json generated 2026-05-23 23:43:54 UTC from source 79768df0c427
root run by this audit: not started
```

Required root-run gate evidence:

```text
Initial pgrep -af '^php tools/run-tests\.php( |$)': no rows
Later pgrep -af '^php tools/run-tests\.php( |$)': no rows
```

I did not start `php tools/run-tests.php`. The duplicate-root gate was clear,
but the checkout was still moving during the audit, every lane handoff is still
pending or uncommitted, and dashboard/status/manifest data do not describe one
accepted snapshot. A root result from this surface would be another anecdote,
not acceptance evidence for the current goal.

Additional checks run by this audit:

```text
jq empty lanes/*/UPSTREAM_TEST_MANIFEST.json
jq empty lanes/*/lane-status.json porting-summary.json dependency-backlog.json
passed
```

## Findings

1. **Critical - the checkout is still not an acceptance checkpoint.**
   - Paths: `progress.md:39`, `progress.md:101`,
     `lanes/*/lane-status.json:13`, recent Git history.
   - Requirement at risk: `goal.md:29` requires small, reviewable slices with
     passing tests; `goal.md:48` requires finished agent output to be verified,
     committed, and integrated cleanly.
   - Evidence: recent history is still dominated by audit/integration-hold
     commits rather than accepted lane slices. The sampled implementation head
     moved after audit sampling from `7fec69a94f1d` through `e8bc6a1a` and
     `0500f313`, default status rows changed `15833 -> 15836 -> 15842`, and
     shortstat changed from `321 files changed, 197977 insertions(+), 29281
     deletions(-)` to `321 files changed, 199149 insertions(+), 29467
     deletions(-)`. Current lane status files still report pending/uncommitted
     handoffs, for example Difftastic, Dolt, esbuild, Gitoxide, markerPDF,
     rclone, Readability, and Syncthing all use pending or uncommitted
     latest-commit prose.

2. **Critical - there is still no acceptable root-harness result for the
   current dirty snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:39`,
     `lanes/*/lane-status.json:12`.
   - Requirement at risk: `goal.md:49` requires periodic repo-wide tests and
     static checks with failures recorded honestly.
   - Evidence: the required `pgrep -af '^php tools/run-tests\.php( |$)'` gate
     returned no active process in both audit samples, but the tree was not
     stable enough to run a meaningful no-argument root harness. Lane blockers
     continue to say root aggregate verification is pending or unassigned
     across the portfolio.

3. **Critical - `porting.html` and `porting-summary.json` are stale
   publication artifacts.**
   - Paths: `porting.html:32`, `porting.html:34`, `porting.html:35`,
     `porting.html:75`, `porting-summary.json:2`,
     `porting-summary.json:3`, `porting-summary.json:8`,
     `dependency-backlog.json:3`.
   - Requirement at risk: `goal.md:45` requires the dashboard to show current
     average progress, denominator, mapped tests, PHP pass/fail, scenarios,
     phase, audit, current work, blocker, and commit.
   - Evidence: the dashboard still reports average progress `97.7%`, generated
     `2026-05-23 23:43:54 UTC`, source snapshot `main 79768df0c427`, and `22`
     dependency items. Current `HEAD` is `7fec69a94f1d`, and
     `dependency-backlog.json` is newer (`2026-05-24 05:03:22 UTC`) with `23`
     items.

4. **High - dashboard, manifest, and lane-status counts disagree across active
   lanes.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/UPSTREAM_TEST_MANIFEST.json`, `lanes/*/lane-status.json`.
   - Requirement at risk: `goal.md:3` and `goal.md:45` require comparable
     upstream denominators, mapped tests, and PHP pass/fail status.
   - Evidence: current manifest/status values versus dashboard include:
     Difftastic `899 total / 618 mapped / 2941 PHP assertions` vs dashboard
     `735 / 374 / 374`; Dolt prose `total` / `613 mapped / 407 status pass`
     vs `inventory / 613 / 356`; esbuild `2567 / 386 / 386` vs
     `2567 / 311 / 311`; Gitoxide `2877 / 2877 / 6510` vs
     `2877 / 2751 / 5634`; libsqlite `1589 / 328 / 328` vs
     `1589 / 286 / 286`; LightningCSS `3532 / 2629 / 3807` vs
     `3532 / 1732 / 2197`; markerPDF `369 / 320 / 457` vs
     `330 / 280 / 416`; Pandoc `2276 / 1698 / 332` vs
     `2276 / 1061 / 278`; Quadrable `55 / 55 / 217` vs `55 / 55 / 190`;
     rclone `1601 / 843 / 843` vs `1601 / 698 / 698`; Readability
     `1984 / 1984 / 237` vs `1984 / 1984 / 204`; Syncthing
     `658 / 658 / 6996` vs `658 / 658 / 4579`.

5. **High - manifest schema remains non-normalized, with Dolt still internally
   inconsistent.**
   - Paths: `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:12`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:17`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2475`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2481`,
     `lanes/dolt/lane-status.json:5`, `lanes/dolt/lane-status.json:6`.
   - Requirement at risk: `goal.md:25` requires a real upstream benchmark
     denominator; `goal.md:3` requires durable coordination fields.
   - Evidence: Dolt's canonical `benchmarkDenominator.total` is a long
     FIND_IN_SET narrative string, while its latest slice is SOUNDEX. The
     manifest reports `nativeImplementation.phpBehaviorTests = 396`, while
     lane status reports `phpPass = 407`. Several other manifests omit a
     machine-readable PHP pass/fail field and rely on lane-status prose.

6. **High - `progress.md` active-lane handoffs are stale relative to current
   lane-status files.**
   - Paths: `progress.md:103` through `progress.md:116`,
     `lanes/gitoxide/lane-status.json:11`,
     `lanes/lightningcss/lane-status.json:11`,
     `lanes/markerpdf/lane-status.json:11`,
     `lanes/readability/lane-status.json:11`,
     `lanes/pandoc/lane-status.json:11`,
     `lanes/syncthing/lane-status.json:11`,
     `lanes/rclone/lane-status.json:11`,
     `lanes/esbuild/lane-status.json:11`.
   - Requirement at risk: `goal.md:44` requires current active lanes,
     blockers, owners/sessions, next task, and percentage estimates.
   - Evidence: the active-lanes table still names older handoffs such as
     Gitoxide SSH config-options, LightningCSS trig/math, markerPDF benchmark
     file inventory, Readability negative header cleanup, Pandoc figure/citation,
     Syncthing system log, rclone VFS Statfs/usage, and esbuild automatic JSX
     key/spread fallback. Current lane statuses now describe sparse-index mode,
     SVG stroke dasharray, PDF Form XObject `/Do`, class-only visibility, raw
     Markdown attributes, config-folder restart-only comparison, WebDAV
     copyProps/dead properties, and ES5 template lowering.

7. **High - near-complete percentages overstate accepted upstream parity.**
   - Paths: `porting.html:56` through `porting.html:67`,
     `lanes/*/lane-status.json:12`, `lanes/*/lane-status.json:13`.
   - Requirement at risk: `goal.md:35` says passing tests are not enough and
     requires meaningful upstream parity, fixture parity, edge cases, error
     behavior, docs/examples, and honest blockers.
   - Evidence: dashboard rows show `92%` to `99%`, but current lane blockers
     still record unexecuted full upstream runners for Gitoxide, Difftastic,
     markerPDF, Pandoc, Syncthing, rclone provider/mount coverage, SQLite
     all/release permutations, and esbuild release-extra coverage. Every lane
     still lacks a committed accepted root result for the current dirty tree.

8. **High - essential optional-library coverage remains backlog-only.**
   - Paths: `dependency-backlog.json:4`, `dependency-backlog.json:7`,
     `dependency-backlog.json:25`, `dependency-backlog.json:45`,
     `dependency-backlog.json:171`, `porting.html:75`.
   - Requirement at risk: this audit requires support libraries to have a
     bounded native PHP component, activation gate, dependency-specific
     upstream/spec denominator, mapped fixtures, PHP pass/fail evidence, and
     malformed/corrupt cases where relevant.
   - Evidence: the backlog has 23 gated items, all candidate/deferred rather
     than active shared ports. Rich-function gaps still need manifest-backed
     work for ZIP/package, XML/HTML5, DOCX/OpenXML, legacy DOC/CFB, EPUB, ODT,
     doctemplates, CSL/citations, math/TeX, PDF text, PDF render planning,
     OCR/layout, table geometry, Unicode repair, charset decoding, source maps,
     tree-sitter subsets, protobuf, checksums/hashes, SQL/storage codecs,
     archive/compression streams, glob/pathspecs, and provider metadata.

9. **High - rclone's dependency expansion is broad and lane-local, so it should
   not count as shared optional-library progress.**
   - Paths: `lanes/rclone/lane-status.json:8`,
     `lanes/rclone/lane-status.json:9`, `lanes/rclone/lane-status.json:11`,
     `lanes/rclone/lane-status.json:12`, `dependency-backlog.json:25`,
     `dependency-backlog.json:384`, `dependency-backlog.json:422`.
   - Requirement at risk: support-library expansion must be bounded, gated,
     tested, shared where appropriate, and backed by a dependency-specific
     denominator.
   - Evidence: rclone status includes lane-local WebDAV XML, PROPFIND,
     PROPPATCH, LOCK, If handling, COPY/MOVE, gzip compression, HTTP middleware,
     auth-proxy reads, directory templates, URL decoding, VFS, and provider
     metadata. Those may be useful rclone slices, but they are not shared
     XML/WebDAV/gzip/provider support-library progress without separate
     activation gates, manifests, malformed cases, and cross-lane ownership.

10. **High - markerPDF still mixes native evidence with external/runtime
    orchestration plans.**
    - Paths: `lanes/markerpdf/lane-status.json:5`,
      `lanes/markerpdf/lane-status.json:9`,
      `lanes/markerpdf/lane-status.json:12`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:19`,
      `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:485`.
    - Requirement at risk: `goal.md:1` and `goal.md:30` forbid counting
      wrappers, bridge calls, shell-outs, or external converter/runtime
      execution as native port progress.
    - Evidence: markerPDF has real native PDF text/font/filter work, but its
      manifest/status still carry marker_server, marker_app, chunk_convert,
      convert.py, pdftext dictionary output, Tesseract, Ghostscript, OCRMyPDF,
      Pandoc/XeLaTeX, Poetry, Streamlit, FastAPI/Uvicorn, Torch, Surya, Texify,
      Nougat, and multiprocessing lifecycle plans. Those are preflight/oracle
      metadata until bounded native PHP components own the behavior.

11. **Medium - Gitoxide shell-outs in tests must remain oracle tooling, not
    progress evidence.**
    - Paths: `lanes/gitoxide/tests/GitUrlTest.php:68`,
      `lanes/gitoxide/tests/GitUrlTest.php:101`,
      `lanes/gitoxide/tests/FetchV2SessionTest.php:10`,
      `lanes/gitoxide/tests/FetchResponseTest.php:15`,
      `lanes/gitoxide/tests/CredentialProgramTest.php:24`.
    - Requirement at risk: `goal.md:1` and `goal.md:30` forbid shell-outs as
      native implementation progress except temporary oracle/fixture tooling.
    - Evidence: Gitoxide tests invoke `git` via `proc_open()` to read upstream
      fixture bytes or diagnostics, and credential tests preserve shell command
      boundaries. That can be acceptable as explicit oracle coverage, but it
      must not inflate native implementation progress or be hidden inside
      pass-count summaries.

12. **Medium - focused green lane anecdotes still dominate runner evidence.**
    - Paths: `lanes/gitoxide/lane-status.json:10`,
      `lanes/lightningcss/lane-status.json:12`,
      `lanes/markerpdf/lane-status.json:10`,
      `lanes/readability/lane-status.json:10`,
      `lanes/syncthing/lane-status.json:10`.
    - Requirement at risk: `goal.md:37` requires upstream tests as the source
      of truth whenever possible, and `goal.md:49` requires repo-wide checks.
    - Evidence: focused lane runs report green assertions, but none is an
      accepted aggregate result from a frozen dirty snapshot. Until the
      supervisor freezes writers, accepts one lane batch, runs focused checks
      and one serialized root harness from the same source, then regenerates
      the dashboard from that accepted commit, these results remain handoff
      evidence only.

## Next Intervention

Keep the hard writer/runner/status freeze as the next gate. Stop or wait out
active writers/status publishers and PHP shards; take two stable polls of
`HEAD`, tracked/default status rows, shortstat, exact PHP runner state, focused
runner state, upstream-runner state, dashboard state, and relevant log mtimes;
accept or reject one lane-scoped batch; normalize schema and count fields for
that batch; run focused verification plus `git diff --check`; run exactly one
serialized no-argument `php tools/run-tests.php` from that same frozen snapshot
if the process gate is empty; regenerate `porting.html` and
`porting-summary.json` from the accepted commit; then commit or reject.
