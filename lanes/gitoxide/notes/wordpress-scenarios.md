# gitoxide WordPress Scenario

Git-backed WordPress content workflows, package installs, Playground snapshots, and server-side repo primitives.

## Current Native Slice

Native loose Git object storage with canonical object headers, loose-header encode/decode and upstream-shaped loose-byte body-prefix parsing, SHA-1 and SHA-256 commit IDs, commit header/signature/identity/encoding parsing with strict upstream header order, commit extra-header first/all/position lookup, commit token iteration with upstream order and partial-error behavior, raw merge-tag header access parsed as native annotated tags, annotated tag write/size/token roundtrips, gix-validate tag-name validation and sanitization preflights, commit message summary/title/body/trailer parsing with Gitoxide byte-class trimming and continuation rules, signature-stripped signed-data extraction, tree entry parsing/serialization, merge-base ancestry traversal, flat and recursive tree three-way merge decisions, exact same-object rename/delete and rename/rename conflict detection, bounded similar-blob rename/delete and rename/modify conflict detection, same-target rename content merges, bounded directory rename/modify merges with renamed plugin entry file heuristics and strict-best similar rename candidate selection, recursive blob-content merges with conflict-marker output, Git index v2 writes for ancestor/ours/theirs blob conflict stages, checkout-clean merged worktree and conflict marker-file writes for content conflicts, directory-file conflict classification, loose direct/symbolic reference parsing/storage, `gix-ref` full-name category/short-name/namespace helpers, namespace-transparent loose+packed reference-store iteration, bounded namespace-aware reference transaction update/delete guards, packed-ref transaction delete/update rewrites, packed-update modes that leave or prune loose source refs, deref update/delete split reporting where symbolic parents are reflog-only, update leaves receive object writes, and delete leaves receive the requested delete mode, object-update reflog append/delete handling, packed-ref header/reference/peeled lookup parsing, protocol v2 capability and `ls-refs` parsing, protocol v2 fetch negotiation argument building, common partial-clone fetch filter specs, sparse checkout path matching, lazy promisor object hydration, protocol v2 fetch response section and sideband parsing, protocol v1 receive-pack push request building, receive-pack advertisement parsing, generated pack handoff, REF_DELTA and OFS_DELTA pack generation, thin send-pack request building, stream-backed, git-daemon, smart HTTP auth/session/redirect/proxy plus proxy auth-method handling, native SOCKS4/SOCKS4a/SOCKS5/SOCKS5h default-requester handshakes, HTTPS-through-SOCKS TLS peer verification with a configured CA file, and SSH receive-pack transport boundaries, send-pack session orchestration, status parsing, loose+packed reference-store overlay resolution, v2 pack-index parsing/lookup, multi-pack-index parsing/lookup, MIDX-backed object database pack selection/de-duplication, pack data entry decoding, OFS_DELTA/REF_DELTA object resolution, and pack+loose+alternate+promisor object database lookup/prefix/iteration with replacement refs.

## WordPress Deploy Tree Example

`examples/wordpress-content-tree.php` parses a fixture Git tree with `.wp-env.json`, `wp-config.php`, and `wp-content` entries. This models server-side PHP code inspecting a WordPress repository snapshot in shared hosting, Playground, package install, or migration tooling without shelling out to `git`.

## WordPress Loose Object Header Example

`examples/wordpress-object-header.php` builds a block-content blob, decodes its canonical loose object header, reports SHA-1 and SHA-256 object IDs, and demonstrates the upstream `ObjectRef::from_loose()` boundary where a read-ahead buffer is parsed by advertised object size while exact storage parsing remains strict. This models WordPress import or deployment tools inspecting loose block-content blobs without invoking `git cat-file`.

## WordPress Commit Signature Example

`examples/wordpress-commit-signature.php` parses a WordPress import/deploy commit body with author and committer actors, actor-only identity bytes, timezone offsets, `encoding`, a multiline signature header, extra-header position/count lookup, upstream-ordered commit token types, embedded release merge-tag header parsed as a native annotated tag object, commit summary/body, Signed-off-by/Co-authored-by/Acked-by/Reviewed-by/Tested-by trailers, and signature-stripped signed payload bytes. The parser now also rejects commit bytes that never reach Gitoxide's required header/message separator, preventing truncated deployment provenance from being treated as an empty-message commit. Focused trailer coverage now follows Gitoxide's vertical-tab handling for blank trailer separators, folded trailer continuations, and leading/trailing value trim, so odd imported commit metadata is parsed consistently with upstream instead of PHP locale behavior. The example now emits Gitoxide-style commit storage bytes, exact size, commit object bytes, and storage/object SHA-1 hashes for provenance roundtrips. It also preflights malformed imported commits by rejecting reordered actor headers and treating late `parent`/`encoding` lines as extra headers, matching `gix-object` instead of silently accepting ambiguous provenance. The fixture covers legacy raw actor offsets such as `+051800` and malformed `--700`, preserving the raw commit bytes while exposing Gitoxide-style parsed time access for review tools. The latest identity slice exposes `name <email>` bytes separately from timestamp parsing so migration review tools can compare importer identities without conflating timezone anomalies. This models migration and deployment tooling that needs `git log`-style provenance, signed-commit metadata, structured commit token inspection, release-tag target/kind/tagger/message provenance, importer/reviewer/tester attribution, actor identity comparison, malformed-import and timestamp preflight, and canonical commit object hashing without invoking the Git binary.

## WordPress Annotated Tag Example

`examples/wordpress-annotated-tag.php` parses, tokenizes, sizes, hashes, and roundtrips a signed WordPress release tag as native tag object bytes. It now preflights a draft release name with `GitTag::isValidName()`, sanitizes it with gix-validate tag-name rules, and proves the sanitized name is writable before constructing a release tag. This models deployment tooling that needs to reject or normalize invalid import/export labels, prepare valid release tags, verify release-tag provenance, and compare object identity without invoking `git tag`, `git cat-file`, or ad hoc tag-body regexes.

The example now uses an uppercase raw target ID in the fixture and reports both the normalized object ID and raw tag-body target bytes. This matches Gitoxide's `TagRef` boundary where callers can compare normalized object identity while still roundtripping the exact annotated-tag bytes used for release provenance hashes.

When the same parsed target is used to construct a new draft release tag, the example now shows the owned tag writer emits the normalized object ID instead of preserving the borrowed raw uppercase bytes. This matches Gitoxide's `TagRef` versus owned `Tag` write boundary and keeps generated WordPress release tags canonical while preserving imported tag provenance bytes separately.

The same example now also converts the parsed tag through `GitTag::toOwned()`, matching Gitoxide's `TagRef::into_owned()` boundary: the imported tag can still roundtrip exact signed bytes, while the owned copy writes a canonical lowercase object header for newly generated release artifacts.

## WordPress Reference Example

`examples/wordpress-references.php` writes and reads `HEAD`, `refs/heads/main`, and `refs/remotes/origin/HEAD` using native PHP loose-ref files. This models a shared-hosting deployment tool or Playground snapshot manager discovering the active WordPress branch and its commit without invoking the Git binary.

## WordPress Reference Category Example

`examples/wordpress-reference-categories.php` classifies WordPress deployment refs as local branches, remote-tracking branches, tags, pseudo refs, main-worktree refs, linked-worktree refs, and worktree-private refs. It also constructs full names from upstream categories and prefixes/strips a site namespace such as `refs/namespaces/site-a/refs/heads/main`. This models multisite or staged-deployment tooling that needs to route fetch, push, and review refs without invoking `git for-each-ref`.

The same example now uses `ReferenceName::joinPartial()` to compose plugin review branches such as `refs/heads/review/plugins/gutenberg` and `refs/remotes/origin/review/plugins/gutenberg` from partial ref components. It also uses `ReferenceName::sanitizePartial()` to turn an unsafe plugin-derived component such as `plugins/seo suite.lock` into `plugins/seo-suite` before joining it under `refs/heads/review`, and `ReferenceName::isValidBranchName()` to reject the reserved `refs/heads/HEAD` branch. This models deployment tools that build branch names from WordPress plugin paths while still rejecting or normalizing unsafe ref bytes, repeated slashes, empty components, leading-dot names, and `.lock` suffixes through upstream-shaped validation.

The example now also checks complete reference-name validity with `ReferenceName::isValid()`: slash-containing relative deployment refs such as `review/plugins/gutenberg` are valid complete names, while lowercase standalone names such as `main` are rejected unless expanded under `refs/heads/`. This follows `gix_validate::reference::name()` and helps WordPress deployment tooling distinguish valid relative ref paths from invalid pseudo refs before fetch/push planning.

The latest namespace prefix slice uses `ReferenceName::intoNamespacedPrefix()` to build a namespaced ref-iteration prefix such as `refs/namespaces/site-a/refs/heads/review/` while preserving the trailing slash. This follows `gix_ref::namespace::Namespace::into_namespaced_prefix()` and models multisite deployment tooling that iterates only a tenant's review branches without invoking `git for-each-ref`.

## WordPress Namespaced Reference Store Example

`examples/wordpress-namespaced-reference-store.php` writes multisite review refs under `refs/namespaces/site-a/refs/...`, combines loose refs with a packed remote-tracking ref, and opens a namespace-aware `ReferenceStore`. Store iteration returns tenant-relative names such as `refs/heads/review/plugin-a`, loose iteration uses the same transparent namespace boundary, packed refs are included through the namespace filter, symbolic targets inside the namespace are stripped to tenant-relative targets, and redundant namespaced lookups intentionally miss. This follows the focused upstream `gix-ref` namespaced store-iteration tests and models WordPress multisite deployment tooling that scopes review refs without invoking `git for-each-ref`.

## WordPress Packed Reference Example

`examples/wordpress-packed-refs.php` parses a compacted `packed-refs` buffer with a WordPress deployment branch, remote-tracking branch, and peeled release tag. This models a PHP deployment or package manager inspecting compacted repository state on shared hosting without invoking `git show-ref` or `git for-each-ref`.

## WordPress Reference Store Example

`examples/wordpress-reference-store.php` combines loose `HEAD` with packed branch and release-tag refs. This models a shared-hosting deployment tool resolving the active WordPress branch from loose refs while reading compacted branch/tag state from `packed-refs`.

## WordPress Protocol V2 ls-refs Example

`examples/wordpress-protocol-v2-ls-refs.php` parses a deterministic protocol v2 capability advertisement, builds an `ls-refs` request with de-duplicated `ref-prefix` arguments and `unborn` support, then parses the remote response into active branch, peeled release tag, and unborn staging branch refs. This models a PHP deployment or package manager discovering remote WordPress repo state before deciding what to fetch.

## WordPress Protocol V2 Fetch Example

`examples/wordpress-protocol-v2-fetch.php` parses a deterministic protocol v2 capability advertisement and builds a shallow blobless `fetch` request using `want-ref refs/heads/main`, `deepen 1`, `filter blob:none`, and local `have` negotiation state. This models a PHP deployment or package manager requesting only the WordPress branch metadata and reachable pack data it needs before object database reads.

## WordPress Protocol V2 Fetch Response Example

`examples/wordpress-protocol-v2-fetch-response.php` parses deterministic protocol v2 fetch response packet lines, including `acknowledgments`, `shallow-info`, `wanted-refs`, and `packfile` sections. It extracts the wanted WordPress branch object, shallow boundary update, sideband progress text, and sideband pack bytes that can be passed to the native pack/object database layer.

## WordPress Protocol V1 Push Example

`examples/wordpress-protocol-v1-push.php` builds a deterministic receive-pack request for updating `refs/heads/main`, creating `refs/tags/wp-release`, requesting `report-status-v2`, `side-band-64k`, `object-format=sha1`, `atomic`, and `push-options`, then packet-lines the ref updates before placeholder pack bytes. This models a PHP deployment tool preparing a WordPress branch/tag push without invoking `git push`.

## WordPress Protocol V1 Push Pack Example

`examples/wordpress-protocol-v1-push-pack.php` builds native pack bytes for a deterministic WordPress commit/tree/blob set, verifies the generated pack and index through the native pack reader, and appends those bytes to a receive-pack branch/tag request. This models a PHP deployment tool preparing the object payload for `git-receive-pack` without invoking `git pack-objects`.

## WordPress Protocol V1 Push Response Example

`examples/wordpress-protocol-v1-push-response.php` parses a deterministic sidebanded receive-pack response for a WordPress deployment push. It extracts progress messages, `unpack ok`, and accepted branch/tag ref statuses from nested report-status packet lines, so a PHP deployment tool can determine whether the remote accepted the push without invoking `git push`.

## WordPress Send-Pack Session Example

`examples/wordpress-send-pack-session.php` parses advertised receive-pack refs and capabilities, plans a WordPress branch update plus release-tag creation, generates native pack bytes for the commit/tree/blob payload, builds the receive-pack request, and parses the status response. This models the local orchestration around a WordPress deployment push before actual transport I/O is ported.

## WordPress Thin Send-Pack Example

`examples/wordpress-send-pack-thin.php` builds a thin receive-pack request for a WordPress content update. It uses remote base objects to encode changed content as REF_DELTA entries, omits those bases from the transit pack, and preserves the update command envelope without invoking `git pack-objects` or `git push`.

## WordPress OFS Delta Pack Example

`examples/wordpress-send-pack-ofs-delta.php` builds a compact non-thin pack containing two related `wp_posts` export blobs. The second blob is written as an OFS_DELTA entry against the earlier in-pack base, then read back through the native pack/index resolver. This models receive-pack payload generation for WordPress deployment tools that want compact pack bytes without relying on `git pack-objects`.

## WordPress Receive-Pack Transport Example

`examples/wordpress-receive-pack-transport.php` runs a deterministic receive-pack handshake, request write, and status response read over native PHP stream resources. The transport tests now also cover git-daemon `git-receive-pack` service requests, smart HTTP discovery/POST exchanges with auth headers, Set-Cookie session propagation, safe initial redirects with effective-base reuse, cleartext URL credential refusal before network I/O, proxy/no-proxy, Basic proxy credential-helper boundaries, proxy auth-method normalization, native default-requester SOCKS5h and SOCKS4a receive-pack discovery through local proxy handshakes, and SSH `git-receive-pack` command setup over injected streams, modeling concrete URL adapter boundaries a shared-hosting deployment tool needs before actual SSH authentication/channel integration and broader HTTPS-through-SOCKS coverage are added.

`examples/wordpress-smart-http-cleartext-credentials.php` documents the URL-credential guard for WordPress deployment tools: `http://deploy:token@...` is rejected before discovery I/O, so an HTTP-to-HTTPS redirect cannot leak Basic credentials from the first cleartext request.

## WordPress Smart HTTP SOCKS/TLS Example

`examples/wordpress-smart-http-socks-tls.php` documents an HTTPS receive-pack discovery flow through a SOCKS5h proxy using a configured CA file and normal peer verification. The focused transport test exercises the same boundary against a local TLS server: PHP opens the SOCKS tunnel, CONNECTs to `git.example.test:443`, negotiates TLS for the origin host, sends an origin-form smart HTTP request, and keeps proxy credentials out of origin headers.

## WordPress Reference Transaction Example

`examples/wordpress-reference-transaction.php` promotes a tenant-scoped production branch, deletes a reviewed plugin branch, and updates `HEAD` inside `refs/namespaces/site-a/` while returning store-relative ref names and symbolic targets. This models the bounded `git update-ref` behavior a multisite deployment tool needs to publish or prune review refs without invoking the Git binary.

## WordPress Deref Reference Transaction Example

`examples/wordpress-deref-reference-transaction.php` updates a production branch through symbolic `HEAD` with `deref=true`. The transaction report keeps `HEAD` as a reflog-only edit, applies the object update to `refs/heads/production`, preserves the symbolic `HEAD` file, and writes matching audit reflogs for both names. It now also runs a delete-side reflog-only transaction through the same symbolic `HEAD`: the report keeps both `HEAD` and `refs/heads/production` as reflog-only delete edits, removes both reflogs, and leaves the symbolic parent plus production branch intact. This models deployment tooling that publishes through the active branch and later prunes audit logs while matching Gitoxide's symbolic-ref split behavior without invoking `git update-ref`.

## WordPress Packed Reference Transaction Example

`examples/wordpress-packed-reference-transaction.php` starts from compacted `packed-refs`, promotes a packed production branch, prunes a reviewed plugin branch, removes the loose production source ref in explicit packed-update mode, rewrites the sorted packed-ref file, and records a deployment reflog line. This models shared-hosting or Playground deployment tools that need pack-refs/update-ref-style branch publication and audit trails without invoking `git update-ref`, `git pack-refs`, or shell commands.

## WordPress Tree Merge Example

`examples/wordpress-tree-merge.php` merges flat root-tree changes where one side updates `wp-content` and the other adds `.wp-env.json`, then reports a structured `theme.json` modify/modify conflict. This models the first PHP-native merge decision layer for WordPress deployment snapshots before recursive tree traversal and blob conflict-marker merges are added.

## WordPress Blob Merge Example

`examples/wordpress-blob-merge.php` line-merges independent WordPress metadata edits and emits diff3 conflict markers for overlapping `theme.json` changes. This models the content-merge layer needed before tree conflicts can be resolved into merged blobs.

## WordPress Recursive Tree Merge Example

`examples/wordpress-recursive-tree-merge.php` walks nested WordPress trees, merges independent `post.meta` edits into a new blob, records a full-path `theme.json` content conflict with diff3 marker output, writes the ancestor/ours/theirs blob stages into a Git index v2 file, and checks out a merged worktree containing both clean metadata and the marker-file `theme.json`. The checkout removes a stale plugin file while preserving `.git/config`. The underlying recursive merge also classifies nested directory-file collisions such as a cache directory on one side and a cache file on the other; those tree stages can now expand into file-level index entries. The merge engine detects exact same-object rename/delete and rename/rename conflicts plus bounded similar-blob rename/delete and rename/modify conflicts for renamed plugin entry files, and same-target renamed plugin entry edits now merge or conflict at the renamed path. Bounded plugin directory renames are detected by descendant similarity, including unique renamed internal leaves, so edits left under the old directory on the other side merge or conflict at the new directory path even when the renamed side also renames the main plugin file. When a plugin split creates multiple similar candidate directories, the merge chooses the strictly best match and keeps equal-score ambiguity on the ordinary add/delete path.

## WordPress Partial Clone Example

`examples/wordpress-partial-clone.php` writes a deterministic WordPress pack/index pair with a `.promisor` sidecar, builds a blobless `FetchFilterSpec`, and stores a tree that references both packed content and an omitted media blob. This models a PHP deployment or package manager distinguishing local packed WordPress content from media bytes promised by a partial clone without invoking `git cat-file`.

## WordPress Lazy Promisor Fetch Example

`examples/wordpress-lazy-promisor-fetch.php` starts with a blobless promisor pack and an omitted media object, then resolves that object through a native `PromisorObjectResolver`. The object database verifies the returned object ID, persists the blob into loose storage, and reports the media object as present for a fresh database instance.

## WordPress Sparse Checkout Example

`examples/wordpress-sparse-checkout.php` combines a blobless fetch filter with a cone-mode sparse checkout rooted at `wp-content/plugins/gutenberg`. It filters deterministic WordPress tree entries so root files, ancestor-directory files, and Gutenberg plugin files are materialized while unrelated plugin/admin paths remain skipped.

## WordPress Pack Index Example

`examples/wordpress-pack-index.php` parses a deterministic v2 pack index fixture for a WordPress repository and locates compacted object offsets, including a large 64-bit media object offset. This models a PHP object database finding packed content objects on shared hosting without invoking `git`.

## WordPress Multi-Pack Index Example

`examples/wordpress-multi-pack-index.php` parses a deterministic multi-pack-index fixture that maps content, template, and large media objects to the pack index names and offsets that contain them. This models a PHP object database using one compact MIDX fanout table to select the right WordPress content or media pack before reading pack data.

## WordPress Pack Data Example

`examples/wordpress-pack-data.php` pairs a deterministic pack index with pack data, then reads a packed commit, blob, and OFS_DELTA-reconstructed blob by object ID. This models a PHP object database reading compacted WordPress content on shared hosting without invoking `git cat-file`.

## WordPress Object Database Example

`examples/wordpress-object-database.php` writes the deterministic WordPress pack fixture into a temporary `.git/objects/pack` directory, adds a loose draft object, links an alternate shared package object cache through `objects/info/alternates`, maps a draft object through `refs/replace`, then reads every source through one object database. This models package managers, Playground snapshot tools, and shared-hosting deployment code traversing packed, loose, shared-cache, and replacement repository content without invoking the Git binary.

## WordPress Multi-Pack Object Database Example

`examples/wordpress-object-database-multi-pack.php` writes two deterministic pack/index pairs plus a `multi-pack-index`, including a package object duplicated across both packs. The object database uses the MIDX to count three indexed objects from four raw pack-index entries, read content/media/shared objects by pack selection, and keep prefix and pack-offset iteration aligned with the MIDX.

## Next Task

Run another controlled `gix-object` message/tag integration subset, broaden proxy credential persistence, or map another focused `gix-merge` fixture.
