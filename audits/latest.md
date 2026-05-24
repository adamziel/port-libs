# Independent Audit - 2026-05-24T18:50Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `08647706 Record repeated Readability handoff rejection`.
I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files.

Bridge code, generated fixtures, shell-outs, whole applications, external
converter wrappers, and hidden process launchers are treated as non-progress
unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 18:42-18:50
observed HEAD movement during audit: 0cffb030e29f -> 4d13f1d71057 -> 086477060014
current HEAD: 086477060014
recent history: 08647706 Record repeated Readability handoff rejection; 4d13f1d7 Record Readability handoff rejection; 0cffb030 Refresh independent audit status; e3de8087 Record Quadrable handoff rejection
branch sample: main...origin/main [ahead 983, behind 68]
tracked dirty rows moved: 331 -> 330 -> 332
default status rows including untracked moved: 21217 -> 21216 -> 21243 -> 21256 -> 21383
dirty shortstat moved: 331 files changed, 271665 insertions(+), 32261 deletions(-) -> 330 files changed, 271593 insertions(+), 32261 deletions(-) -> 330 files changed, 272024 insertions(+), 32258 deletions(-) -> 332 files changed, 273858 insertions(+), 33567 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows (0 active, 25 candidate, 1 blocked, 11 deferred)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started; final gate was occupied by another no-argument root run
```

Required exact pre-root process gate:

```text
2026-05-24T18:42:15Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T18:42:28Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T18:43:30Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T18:48:08Z pgrep -af '^php tools/run-tests\.php$': no rows
2026-05-24T18:50:23Z pgrep -af '^php tools/run-tests\.php$': 934477 php tools/run-tests.php
owner evidence: 934477 claude 934421 R+ 00:53 php tools/run-tests.php
```

I did not start `php tools/run-tests.php`. The no-argument root harness gate
was clear in the earlier audit samples but the checkout was not stable enough
for an audit-owned root acceptance run; by final validation the exact gate was
occupied by PID `934477`, so I did not start a duplicate. `HEAD`, dirty
counts, and shortstat moved during sampling, and no owner-free lane batch had
been accepted.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          latest sampled manifest/status
difftastic    manifest 1058/1213; status 3642 assertions
dolt          manifest 613/613; manifest native PHP ledger 442; status 450 PASS cases
esbuild       manifest/status 470/2567
gitoxide      manifest 2877/2877; status 7594 assertions
libsqlite     manifest/status 376/1589; status 6049 assertions
LightningCSS  manifest 2980/3548; status 4305 assertions
markerPDF     manifest 378/427; status 516 behavior tests
pandoc        manifest 2278/2276; status 393 behavior tests
quadrable     manifest 55/55; status 254 behavior tests
rclone        manifest/status 973/1601
readability   manifest 1984/1984; status 3839 assertions / 287 behavior tests
syncthing     manifest 658/658; status 8941 assertions
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:15`, `progress.md:49-51`,
     `lanes/difftastic/lane-status.json:13`,
     `lanes/dolt/lane-status.json:13`,
     `lanes/esbuild/lane-status.json:13`,
     `lanes/gitoxide/lane-status.json:13`,
     `lanes/lightningcss/lane-status.json:13`,
     `lanes/markerpdf/lane-status.json:13`,
     `lanes/readability/lane-status.json:13`,
     `lanes/syncthing/lane-status.json:13`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:48`, and
     `goal.md:52` require small reviewable committed slices, verified
     handoffs, and visible stable progress for every lane.
   - Evidence: `HEAD` moved during this audit from `0cffb030e29f` through
     `4d13f1d71057` to `086477060014`. Default status rows including
     untracked moved `21217 -> 21216 -> 21243 -> 21256 -> 21383`; tracked
     shortstat moved from
     `331 files changed, 271665 insertions(+), 32261 deletions(-)` to
     `332 files changed, 273858 insertions(+), 33567 deletions(-)`.
     Current lane statuses continue to describe work as `pending`,
     `uncommitted`, or supervisor/integrator-owned rather than accepted lane
     commits.

2. **Critical - no trustworthy no-argument root acceptance result exists for the current tree.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording; this audit run also required the exact
     duplicate-root gate before any root run.
   - Evidence: the exact root gate returned no rows in earlier audit samples,
     then final validation matched active no-argument root PID
     `934477 php tools/run-tests.php` with owner evidence
     `934477 claude 934421 R+ 00:53 php tools/run-tests.php`. I did not start
     a duplicate. I also did not run the root harness while the gate was clear
     because the tree failed the frozen-snapshot gate while `HEAD`, dirty
     counts, and shortstat moved, and recent integration status shows current
     lane handoffs are being rejected/deferred rather than accepted. A root
     result from this moving aggregate would not prove the goal-required
     accepted baseline.

3. **Critical - `porting.html` and `porting-summary.json` are stale against current lane metadata.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:2-8`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track denominator, mapped tests, PHP pass/fail, phase,
     audit, current work, blocker, and commit.
   - Evidence: the dashboard still publishes source `89260857cc71`, generated
     `2026-05-24 12:29:46 UTC`, while sampled `HEAD` is `086477060014`.
     Current lane files report Difftastic `1058/1213` and `3642` assertions
     while the dashboard says `851/1077` and `3245`; Dolt `450` PASS cases
     while the dashboard says `425`; esbuild `470` while the dashboard says
     `429`; Gitoxide `7594` assertions while the dashboard says `7152`;
     libsqlite `376/1589` and `6049` assertions while the dashboard says
     `349/1589` and `348`; LightningCSS `2980/3548` and `4305` assertions
     while the dashboard says `2765/3548` and `4065`; markerPDF `378/427`
     and `516` tests while the dashboard says `347/396` and `484`; Pandoc
     manifest `2278/2276` and status `393` tests while the dashboard says
     `1891/2276` and `362`; rclone `973` while the dashboard says `906`;
     Readability `3839` assertions while the dashboard says `3545`; and
     Syncthing `8941` assertions while the dashboard says `7902`.

4. **High - manifest/status ledgers remain non-atomic and internally inconsistent.**
   - Paths: `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:391`,
     `lanes/pandoc/lane-status.json:5`,
     `lanes/dolt/UPSTREAM_TEST_MANIFEST.json:2593`,
     `lanes/dolt/lane-status.json:6`,
     `lanes/lightningcss/lane-status.json:13`,
     `porting-summary.json:28-42`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by denominator, mapped
     tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: Pandoc now records `total: 2276` and `mapped: 2278`, an
     impossible mapped-over-denominator ledger, while lane status and warning
     prose still say `2,276` focused checks. Dolt status reports `450` PASS
     cases but the manifest native PHP ledger still says
     `phpBehaviorTests: 442`.
     LightningCSS status still says `HEAD 314f357474f7 at status update`
     while current sampled `HEAD` is `086477060014`. `porting-summary.json`
     is older still.

5. **High - current handoff rejections confirm the lane batches are too broad for reviewable integration.**
   - Paths: `audits/integration-status.md:3`,
     `audits/integration-status.md:30`,
     `audits/integration-status.md:53`,
     `audits/integration-status.md:102`,
     `audits/integration-status.md:133`,
     `audits/integration-status.md:177`,
     `audits/integration-status.md:208`,
     `audits/integration-status.md:261`,
     `audits/integration-status.md:294`,
     `audits/integration-status.md:342`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:36`, and
     `goal.md:48` require small correct slices, passing tests, and supervisor
     verification before committing/integrating output.
   - Evidence: recent history is dominated by audit/integration rejection
     commits, including a repeated Readability rejection after the first
     Readability rejection. Readability, Quadrable, Pandoc, Difftastic, and
     LightningCSS handoffs were rejected/deferred because the named focused
     slice was mixed with older broad dirty changes, large untracked
     fixture/example sets, or accumulated unrelated lane work. That means
     their latest green focused tests cannot be treated as accepted progress
     for the whole dirty lane.

6. **High - support-library coverage is still backlog-only, not first-class lane-granular work.**
   - Paths: `dependency-backlog.json:7-21`,
     `dependency-backlog.json:81-93`,
     `dependency-backlog.json:129-175`,
     `dependency-backlog.json:179-209`,
     `dependency-backlog.json:214-267`,
     `dependency-backlog.json:272-336`,
     `dependency-backlog.json:340-409`,
     `dependency-backlog.json:413-425`,
     `dependency-backlog.json:629-645`, `porting.html:72-78`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers. The latest support-library directive requires bounded
     native support components with activation gates, dependency-specific
     upstream/spec denominators, mapped fixtures, PHP pass/fail evidence,
     malformed/corrupt cases where relevant, and bounded install-attempt
     notes where tooling is missing.
   - Evidence: the backlog does visibly route Pandoc DOC, DOCX/OpenXML, PDF
     input/output handoff, EPUB, ODT/OpenDocument, templates, citations, math,
     tables, package containers, XML/HTML, Unicode/charset, JSON/YAML
     metadata, syntax highlighting, and archive/compression, plus shared rows
     for WebDAV, URL handling, source maps, protobuf, hashes, SQL, and
     archive streams. It still has `0` active support rows. The root lane
     manifest set contains only the 12 base `lanes/*/UPSTREAM_TEST_MANIFEST.json`
     files; there are no accepted dependency-specific support manifests, PHP
     pass/fail ledgers, malformed/corrupt evidence records, accepted
     activation records, or bounded `sudo -n` install-attempt/ruled-out notes.

7. **High - Pandoc remains far short of the original rich conversion-kernel goal despite 99 percent status language.**
   - Paths: `goal.md:12`, `lanes/pandoc/lane-status.json:5`,
     `lanes/pandoc/lane-status.json:11-14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:14`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:391`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:393`,
     `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:1402-1403`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc reports `99%`, but full upstream Haskell runner parity
     remains unexecuted and the manifest now overcounts mapped checks
     (`2278/2276`). Current slices explicitly do not invoke upstream
     Pandoc, network fetches, browser tooling, converter shell-outs, PDF
     processing, ZIP/package parsing, citation/CSL engines,
     PlainMath/MathML conversion, TeX math/ref conversion, or broader
     XML/HTML/syntax-highlighting support. DOC/DOCX/OpenXML, PDF input/output
     handoff, EPUB, ODT/OpenDocument, citations, math, templates, tables,
     JSON/YAML metadata, archive/compression, XML/HTML, Unicode, and charset
     remain inactive support rows rather than accepted conversion-kernel
     coverage.

8. **High - markerPDF still mixes useful native PDF extraction with plan-only runtime/application evidence.**
   - Paths: `goal.md:9`, `lanes/markerpdf/lane-status.json:5`,
     `lanes/markerpdf/lane-status.json:10-14`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:10`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:13`,
     `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:843-868`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, and plan-only application
     behavior must not count as native implementation progress.
   - Evidence: the native PDF positioned word-gap/text-advance work is useful and
     explicitly avoids Python/pdftext/pypdfium/Poppler/Ghostscript, but the
     denominator still carries benchmark archive planning, Streamlit app
     flow, FastAPI/Uvicorn server shape, Poetry/package metadata, OCR/model
     install planning, Nougat subprocess planning, chunk-convert shell
     lifecycle planning, model runtime dependency graphs, and other plan-only
     runtime evidence. Richer searchable PDF, OCR/layout, and table work
     should be credited only through accepted bounded rows such as
     `pdf-text-dictionary-core`, `layout-ocr-result-core`, and
     `table-geometry-core`.

9. **Medium - the 98-99 percent progress claims remain misleading.**
   - Paths: `porting.html:32`, `porting.html:56-67`,
     `porting-summary.json:8`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:35-40` require meaningful fixture
     parity, edge-case coverage, upstream tests as source of truth, and
     explicit blockers for hard features.
   - Evidence: the published average is `98.3%` and most lanes report
     `98-99%`, but the dashboard is stale, the current lane work is mostly
     pending or uncommitted, root acceptance is absent for the current tree,
     manifest/status ledgers still disagree, several upstream full-suite
     runners remain static-only or bounded, and no support-library row is
     active.

## Next Intervention

Freeze or wait out active lane workers, root/focused runners, status
publishers, dashboard publishers, evaluator/auditor loops, capacity jobs, and
integrator loops. Then require two stable dirty-count/HEAD polls with
`pgrep -af '^php tools/run-tests\.php$'` clear before any audit-owned root run.
The best next intake is still a single owner-free reduced lane batch from a
frozen snapshot: normalize the Pandoc mapped-count overflow and Dolt
manifest/status PHP-count drift, enforce atomic manifest/status writes, run
focused verification plus `git diff --check`, run exactly one serialized
no-argument `php tools/run-tests.php` only from that frozen snapshot,
regenerate `porting.html` and `porting-summary.json` from the accepted commit,
and keep support-library rows inactive until a base-lane rich slice is
accepted or accepted-blocked on one bounded component.
