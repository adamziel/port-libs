# Pandoc Status-File Conflict Reducer

Snapshot: 2026-06-13T02:46:06Z on `origin/main` `4487923aa203`.

Scope: status-file conflict reduction only. No parser behavior was inspected or
changed.

## Queue Findings

`gt mq list port_libs --ready --json` reported 32 ready MRs. Every ready MR
that touched Pandoc status artifacts also conflicted under:

```bash
git merge-tree --write-tree origin/main origin/<branch>
```

The shared hot spots are:

- `progress.md`
- `PANDOC_STATUS.md`
- `lanes/pandoc/lane-status.json`
- `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`

Two ready MRs are status-only replay candidates:

| Issue | MR | Branch | Status files | Recommendation |
| --- | --- | --- | --- | --- |
| `plib-gljei` | `plib-wisp-hd7` | `polecat/699/plib-gljei@mqbn7eyv` | `progress.md`, `PANDOC_STATUS.md`, `lanes/pandoc/lane-status.json`, `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` | Replay after the implementation queue drains, or close as superseded if the only surviving value is stale aggregate counters/prose. |
| `plib-pg7cz` | `plib-wisp-epf` | `polecat/723/plib-pg7cz@mqbpyi91` | `progress.md`, `PANDOC_STATUS.md` | Replay after the implementation queue drains, or close as superseded if the status prose is already represented by current main. |

The remaining 30 ready MRs are implementation branches with stale status deltas.
For these, merge or rebase the implementation code and any unique
`lanes/pandoc/notes/*.md` evidence first, then regenerate or hand-edit the four
aggregate status artifacts from current main. Do not mechanically add old
`phpPass`, mapped-case, or prose deltas from branch snapshots whose
`pre_verified_base` predates current `origin/main`.

## Non-Status Merge Risks

Most simulated conflicts were limited to the four status artifacts. Two ready
implementation branches also showed code/test conflicts:

| Issue | Branch | Non-status conflict |
| --- | --- | --- |
| `plib-yaih5` | `polecat/704/plib-yaih5@mqbnw0rp` | `lanes/pandoc/tests/MarkdownReaderTest.php` |
| `plib-j5tip` | `polecat/707/plib-j5tip@mqbo89aq` | `lanes/pandoc/src/PandocFormatRegistry.php` |

Handle those with normal implementation conflict review before touching the
status files.

## Recommended Replay Order

1. Land implementation branches whose non-status files merge cleanly, keeping
   their unique evidence notes.
2. Resolve `plib-yaih5` and `plib-j5tip` as code/test conflicts before applying
   their status deltas.
3. Defer `plib-gljei` and `plib-pg7cz` until after the implementation queue has
   landed; then create one fresh status reconciliation instead of merging their
   stale status-only snapshots.
4. For the final status refresh, validate with `jq empty` for the two JSON
   status files and `git diff --check` for touched Markdown/JSON. Run only the
   focused Pandoc PHP tests needed by the implementation changes, plus the full
   `lanes/pandoc/tests` gate when producing a new aggregate reconciliation.

No Pandoc binary, browser, Node, office suite, TeX/Typst engine, online service,
or external validator was used for this snapshot.
