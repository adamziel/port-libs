# Independent Audit - 2026-05-24T19:47Z

Scope reviewed: `goal.md`, `progress.md`, `porting.html`,
`porting-summary.json`, `dependency-backlog.json`, every
`lanes/*/UPSTREAM_TEST_MANIFEST.json`, every `lanes/*/lane-status.json`, and
recent Git history through `116ccf10 Refresh independent audit status`.

I did not edit lane implementation files, launch agents or tmux sessions, push,
read secrets, inspect process environments, credential stores, provider
configs, or auth files. Bridge code, generated fixtures, shell-outs, whole
applications, external converter wrappers, and hidden process launchers are
treated as non-progress unless they are explicitly temporary oracle tooling.

## Current Snapshot

```text
UTC samples: 2026-05-24 19:43-19:47
HEAD: 116ccf106c9b
main divergence sample: behind 68, ahead 999
recent history: 116ccf10 Refresh independent audit status; 57e13cae Record Readability handoff rejection; 64cf436d Record rclone handoff rejection; 303bf14e Refresh independent audit status; ca5c7111 Record markerPDF handoff rejection; 4af74d41 Refresh independent audit status; 49b5a511 Record Quadrable handoff rejection; eebc7e29 Refresh independent audit status
default status rows including untracked moved during this audit: 22141 -> 22155 -> 22251
dirty shortstat moved during this audit: 241 files changed, 210096 insertions(+), 26405 deletions(-) -> 241 files changed, 210106 insertions(+), 26405 deletions(-) -> 243 files changed, 210966 insertions(+), 26594 deletions(-)
dashboard snapshot: porting.html and porting-summary.json still publish source 89260857cc71 generated 2026-05-24 12:29:46 UTC
dependency backlog: 37 rows, 0 active (blocked 1, candidate 25, deferred 11)
json validation by this audit: jq empty passed for all 12 lane manifests, all 12 lane-status files, dependency-backlog.json, and porting-summary.json
root run by this audit: not started
```

Required exact pre-root process gate:

```text
2026-05-24T19:43:31Z pgrep -af '^php tools/run-tests\.php$': 1353157 php tools/run-tests.php
2026-05-24T19:43:31Z owner evidence: 1353157 claude 1338946 Rs 00:28 php tools/run-tests.php
2026-05-24T19:44:00Z pgrep -af '^php tools/run-tests\.php$': 1353157 php tools/run-tests.php
2026-05-24T19:44:00Z owner evidence: 1353157 claude 1338946 Rs 00:55 php tools/run-tests.php
2026-05-24T19:47:30Z pgrep -af '^php tools/run-tests\.php$': no rows
```

I did not start `php tools/run-tests.php`. The exact no-argument root harness
gate was occupied by PID `1353157` in the first samples. It cleared by
`19:47:30Z`, but the worktree still failed the stability gate because status
rows and shortstat continued moving.

Latest sampled manifest/status counts. These are samples from a moving
worktree, not an acceptance ledger:

```text
lane          manifest mapped/total     status phpPass/phpFail
difftastic    1106/1246                 3714/0
dolt          613/613                   456/0
esbuild       479/2567                  479/0
gitoxide      1454/2877                 7547/0
libsqlite     214/1589                  213/0
LightningCSS  3002/3548                 3958/0
markerPDF     163/78                    268/0
pandoc        2276/2276                 398/0
quadrable     55/55                     258/0
rclone        462/1601                  462/0
Readability   1984/1984                 3881/0
syncthing     658/658                   9068/0
```

## Findings

1. **Critical - the repository is still a live dirty aggregate, not an acceptance baseline.**
   - Paths: `progress.md:49-52`, `audits/integration-status.md:1-151`,
     `lanes/*/lane-status.json`, `tools/run-tests.php`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35-40`,
     `goal.md:48`, and `goal.md:49` require small reviewable slices, honest
     blockers, verified handoff cleanup, and repo-wide verification.
   - Evidence: `HEAD` is stable only for this short sample at `116ccf106c9b`,
     but the checkout is huge and still moving: default status rows changed
     `22141 -> 22155 -> 22251` and dirty shortstat changed from `241 files
     changed, 210096 insertions(+), 26405 deletions(-)` through `241 files
     changed, 210106 insertions(+), 26405 deletions(-)` to `243 files changed,
     210966 insertions(+), 26594 deletions(-)`. Recent accepted history is still
     rejection-led: Readability, rclone, markerPDF, Quadrable, libsqlite,
     Gitoxide, and LightningCSS handoffs were rejected/deferred in the recent
     audit/integration sequence. This state cannot support claims of accepted
     native progress across the dirty lane piles.

2. **Critical - no accepted serialized root-harness result exists for the current snapshot.**
   - Paths: `tools/run-tests.php`, `progress.md:51`,
     `audits/integration-status.md:1-229`, `lanes/*/lane-status.json`.
   - Goal requirement at risk: `goal.md:49` requires periodic repo-wide tests
     and honest failure recording.
   - Evidence: the exact pre-root gate was occupied by active no-argument root
     PID `1353157 php tools/run-tests.php`, owned by `claude`, in early
     samples, so this audit correctly did not start a duplicate. The gate
     cleared by `19:47:30Z`, but the checkout was still moving and still not
     root-run eligible. The last integration-owned root result recorded in
     `audits/integration-status.md` failed with `378` test files, `58733`
     assertions, and `1` failure while `HEAD` moved. Focused lane shards are
     useful but do not replace one accepted no-argument root result from a
     frozen snapshot.

3. **Critical - `porting.html` and `porting-summary.json` are materially stale.**
   - Paths: `porting.html:32-38`, `porting.html:56-67`,
     `porting-summary.json:1-120`.
   - Goal requirement at risk: `goal.md:3` and `goal.md:45` require the
     dashboard to track current denominator, mapped tests, PHP pass/fail,
     WordPress scenarios, phase, audit, current work, blocker, and commit.
   - Evidence: both dashboard artifacts still publish source snapshot
     `89260857cc71`, generated `2026-05-24 12:29:46 UTC`, while current `HEAD`
     is `116ccf106c9b`. The table disagrees with current lane metadata:
     Difftastic is now `1106/1246` and `3714` pass units versus dashboard
     `851/1077` and `3245`; Gitoxide is `1454/2877` versus dashboard
     `2877/2877`; markerPDF is `163/78` and `268` versus dashboard `347/396`
     and `484`; rclone is `462/1601` versus dashboard `906/1601`; Pandoc is
     `398` pass units versus dashboard `362`; Syncthing is `9068` versus
     dashboard `7902`. The visible `98.3%` average is therefore not an honest
     current status signal.

4. **High - support-library coverage is visible, but still not first-class accepted work.**
   - Paths: `dependency-backlog.json:1-4`, `dependency-backlog.json:7-94`,
     `dependency-backlog.json:129-190`, `dependency-backlog.json:214-267`,
     `dependency-backlog.json:272-426`, `dependency-backlog.json:429-646`,
     `dependency-backlog.json:649-687`, `porting.html:72-129`,
     `progress.md:17-36`.
   - Goal requirement at risk: `goal.md:35-40` require real denominators,
     meaningful fixture parity, edge-case coverage, and hard-feature blockers.
     The latest support-library directive requires bounded native components,
     activation gates, dependency-specific upstream/spec denominators, mapped
     fixtures, PHP pass/fail evidence, malformed/corrupt cases where relevant,
     and bounded install-attempt notes before missing packages become final
     blockers.
   - Evidence: the backlog has rows for all important base-tool rich functions,
     including Pandoc DOC, DOCX/OpenXML, PDF input/output handoff, EPUB,
     ODT/OpenDocument, templates, citations, math, tables, package containers,
     XML/HTML, Unicode/charset, JSON/YAML metadata, syntax highlighting, and
     archive/compression. It also covers rclone WebDAV/provider metadata,
     Gitoxide wire/path/hash support, esbuild/LightningCSS source maps,
     package resolution and browser target data, Difftastic tree-sitter and
     sequence diff, Syncthing protobuf/QR/archive/path support, Dolt/MySQL/SQL
     semantics/storage, libsqlite JSON/UTF-16/SQL semantics, markerPDF PDF/OCR
     and table helpers, and Quadrable proof/transport/hash support. But all
     37 rows remain inactive (`candidate`, `deferred`, or one `blocked` QR
     row). There are still no accepted support manifests, no dependency-specific
     PHP pass/fail ledgers, no malformed/corrupt evidence records, no accepted
     activation records, and no bounded install-attempt notes. Lane-local helper
     work must continue to be treated as lane-local until an accepted base lane
     opens or blocks on the exact bounded support component.

5. **High - Pandoc's status still overstates readiness for the original rich conversion-kernel goal.**
   - Paths: `goal.md:12`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json:12-16`,
     `lanes/pandoc/lane-status.json`, `dependency-backlog.json:81-94`,
     `dependency-backlog.json:129-190`, `dependency-backlog.json:214-267`,
     `dependency-backlog.json:272-426`, `dependency-backlog.json:629-646`.
   - Goal requirement at risk: `goal.md:12` requires a document conversion
     kernel with a shared AST plus readers/writers for Markdown, HTML, WXR,
     EPUB/PDF-oriented intermediate forms, and WordPress block output.
   - Evidence: Pandoc reports `2276/2276` over a static inventory and `398`
     focused PHP behavior tests, but full Haskell runner parity remains
     unexecuted. The current lane status explicitly leaves live fetch/openURL,
     broader HTML parser parity, rich MathML/PlainMath/TeX conversion,
     citation/CSL, templates, PDF, package parsers, ZIP/XML/HTML support,
     syntax highlighting, and richer document formats behind inactive support
     rows. Those are central to the requested conversion kernel; they are not
     optional polish.

6. **High - markerPDF's manifest denominator is internally invalid and still mixes native evidence with runtime/application planning.**
   - Paths: `goal.md:9`, `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/markerpdf/lane-status.json`, `dependency-backlog.json:272-337`.
   - Goal requirement at risk: `goal.md:1`, `goal.md:30`, and
     `goal.md:35-40` say wrappers, shell-outs, whole applications, runtime
     launchers, and plan-only behavior must not count as native implementation
     progress.
   - Evidence: the current manifest reports `mapped: 163` against `total: 78`
     repository paths, so its numerator exceeds the stated denominator. Its
     source text still includes Streamlit, FastAPI/Uvicorn, pypdfium/PDF
     rendering, Surya/OCR/Texify/Torch/Nougat, benchmark/archive scripts,
     Poetry/build/publish tooling, and top-level conversion runners. Native
     `PdfTextExtractor` slices can count only against bounded PDF text evidence
     and supplied-result handoffs, not as progress on those external runtime
     systems or inactive PDF/OCR/table support rows.

7. **High - the newest Readability and rclone rejections show handoff scope still does not match evidence.**
   - Paths: `audits/integration-status.md:1-151`,
     `lanes/readability/UPSTREAM_TEST_MANIFEST.json:12-18`,
     `lanes/readability/lane-status.json`,
     `lanes/rclone/UPSTREAM_TEST_MANIFEST.json:12-20`,
     `lanes/rclone/lane-status.json`.
   - Goal requirement at risk: `goal.md:29`, `goal.md:35-40`, and
     `goal.md:48` require focused, reviewable slices whose dirty files match
     their evidence.
   - Evidence: the latest Readability intake rejected/deferred a narrow
     boolean-option truthiness claim because the actual dirty state includes a
     broad accumulated `ArticleExtractor.php`, test, fixture, serializer, URL,
     JSON-LD, table/sibling, and media rewrite. The latest rclone intake
     rejected/deferred a WebDAV DELETE partial `RemoveAll` claim because the
     tracked diff was still an older OneDrive permission-planner batch while
     the advertised WebDAV files sat inside a broad untracked pile. Neither
     handoff can count as accepted progress, and neither should activate
     support-library rows.

8. **High - Gitoxide's current reduced manifest invalidates the dashboard's full-mapped claim.**
   - Paths: `goal.md:7`, `lanes/gitoxide/UPSTREAM_TEST_MANIFEST.json:12-22`,
     `lanes/gitoxide/lane-status.json`, `porting.html:59`,
     `porting-summary.json:61-77`.
   - Goal requirement at risk: `goal.md:7`, `goal.md:25`, and
     `goal.md:35-40` require a Git implementation with packfiles, refs,
     commits, object database, protocol v2, sparse/partial clone, push, merge,
     server-oriented primitives, and a real upstream denominator.
   - Evidence: the current manifest is explicitly reduced for reviewability
     and claims `1454/2877` mapped for a bounded discovery/HEAD validation
     handoff. The dashboard still claims `2877/2877` mapped and a `98%` row,
     hiding the current reduced scope and the remaining original Git surface.

9. **Medium - manifest, status, and dashboard ledgers still mix count units and percentages.**
   - Paths: `lanes/*/UPSTREAM_TEST_MANIFEST.json`,
     `lanes/*/lane-status.json`, `porting.html:56-67`,
     `porting-summary.json:9-213`.
   - Goal requirement at risk: `goal.md:3`, `goal.md:25`, and
     `goal.md:44-45` require durable coordination by upstream denominator,
     mapped tests, PHP pass/fail, current work, blocker, and commit.
   - Evidence: markerPDF counts repository paths as a denominator but focused
     behaviors as mapped units. Difftastic maps behavior artifacts while status
     reports assertion units. Dolt reports PASS cases while the manifest
     denominator is executable upstream files. Pandoc maps static artifacts but
     reports only `398` focused behavior tests. Most rows display `95-99%`
     despite `latestCommit` being pending/uncommitted and root acceptance being
     absent. These are useful local ledgers, but not comparable dashboard
     percentages until normalized.

## Required Next Intervention

Freeze writers/runners/status publishers long enough for two stable polls of
`HEAD`, dirty status rows, shortstat, active root PIDs, and relevant
handoff/log mtimes. Do not add lane breadth while handoffs are still
accumulated. Triage the latest failed root result, then accept or reject one
owner-free reduced lane batch whose dirty files exactly match its evidence,
normalize manifest/status count units for that lane, regenerate `porting.html`
and `porting-summary.json` from the accepted commit, and run exactly one
serialized no-argument `php tools/run-tests.php` only if
`pgrep -af '^php tools/run-tests\.php$'` stays empty on that frozen snapshot.
Keep every support-library row inactive until a base lane is accepted-ready or
accepted-blocked on the exact bounded component with its own denominator,
mapped fixtures, malformed/corrupt cases, PHP pass/fail ledger, and bounded
install-attempt notes where missing packages matter.
