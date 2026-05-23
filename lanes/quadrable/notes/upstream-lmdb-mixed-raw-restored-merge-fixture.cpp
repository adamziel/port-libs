#include <filesystem>
#include <iostream>
#include <string>
#include <string_view>

#include "quadrable.h"

namespace {

std::string hex(std::string_view bytes) {
    static constexpr char digits[] = "0123456789abcdef";

    std::string out;
    out.reserve(bytes.size() * 2);
    for (unsigned char c : bytes) {
        out.push_back(digits[c >> 4]);
        out.push_back(digits[c & 0x0f]);
    }

    return out;
}

void jsonString(std::ostream &out, std::string_view value) {
    out << '"';
    for (unsigned char c : value) {
        switch (c) {
            case '\\':
                out << "\\\\";
                break;
            case '"':
                out << "\\\"";
                break;
            case '\b':
                out << "\\b";
                break;
            case '\f':
                out << "\\f";
                break;
            case '\n':
                out << "\\n";
                break;
            case '\r':
                out << "\\r";
                break;
            case '\t':
                out << "\\t";
                break;
            default:
                if (c < 0x20) {
                    static constexpr char digits[] = "0123456789abcdef";
                    out << "\\u00" << digits[c >> 4] << digits[c & 0x0f];
                } else {
                    out << static_cast<char>(c);
                }
        }
    }
    out << '"';
}

lmdb::env createEnv(const std::string &dir) {
    std::filesystem::remove_all(dir);
    std::filesystem::create_directories(dir);

    lmdb::env env = lmdb::env::create();
    env.set_max_dbs(64);
    env.set_mapsize(1UL * 1024UL * 1024UL * 1024UL * 1024UL);
    env.open(dir.c_str(), MDB_CREATE, 0664);
    env.reader_check();

    return env;
}

lmdb::env openEnv(const std::string &dir) {
    lmdb::env env = lmdb::env::create();
    env.set_max_dbs(64);
    env.set_mapsize(1UL * 1024UL * 1024UL * 1024UL * 1024UL);
    env.open(dir.c_str(), 0, 0664);
    env.reader_check();

    return env;
}

void put(quadrable::Quadrable &db, lmdb::txn &txn, std::string_view key, std::string_view value) {
    auto changes = db.change();
    changes.put(key, value);
    changes.apply(txn);
}

void checkoutFromState(quadrable::Quadrable &db, lmdb::txn &txn, lmdb::dbi &state) {
    std::string_view v;
    if (state.get(txn, "detachedHead", v)) {
        db.checkout(lmdb::from_sv<uint64_t>(v));
    } else if (state.get(txn, "currHead", v)) {
        db.checkout(v);
    } else {
        db.checkout("master");
    }
}

void persistState(quadrable::Quadrable &db, lmdb::txn &txn, lmdb::dbi &state) {
    if (db.isDetachedHead()) {
        state.put(txn, "detachedHead", lmdb::to_sv<uint64_t>(db.getHeadNodeId(txn)));
        state.del(txn, "currHead");

        return;
    }

    state.put(txn, "currHead", db.getHead());
    state.del(txn, "detachedHead");
}

void dumpBucket(std::ostream &out, lmdb::txn &txn, const char *name) {
    auto dbi = lmdb::dbi::open(txn, name, MDB_CREATE);
    auto cursor = lmdb::cursor::open(txn, dbi);
    std::string_view key;
    std::string_view value;

    out << "      ";
    jsonString(out, name);
    out << ": [";

    bool first = true;
    for (bool found = cursor.get(key, value, MDB_FIRST); found; found = cursor.get(key, value, MDB_NEXT)) {
        if (!first) {
            out << ", ";
        }
        first = false;

        out << "{\"keyHex\": ";
        jsonString(out, hex(key));
        out << ", \"valueHex\": ";
        jsonString(out, hex(value));
        out << "}";
    }

    out << "]";
}

void dumpEntries(std::ostream &out, lmdb::txn &txn) {
    out << "    \"entries\": {\n";
    dumpBucket(out, txn, "quadrable_head");
    out << ",\n";
    dumpBucket(out, txn, "quadrable_nodesLeaf");
    out << ",\n";
    dumpBucket(out, txn, "quadrable_nodesInterior");
    out << ",\n";
    dumpBucket(out, txn, "quadrable_key");
    out << ",\n";
    dumpBucket(out, txn, "quadrable_quadb_state");
    out << "\n    }";
}

void dumpPhase(std::ostream &out, const char *name, lmdb::txn &txn, std::string_view rootHex) {
    out << "  ";
    jsonString(out, name);
    out << ": {\n";
    out << "    \"rootHex\": ";
    jsonString(out, rootHex);
    out << ",\n";
    dumpEntries(out, txn);
    out << "\n  }";
}

std::string binaryKey() {
    return std::string("wp_options:serialized-") + static_cast<char>(0xff);
}

std::string binaryValue() {
    return std::string("autoload") + '\0' + static_cast<char>(0xff)
        + std::string("serialized:site-option") + static_cast<char>(0x80);
}

std::string previewValue() {
    return std::string("preview") + '\0' + static_cast<char>(0xff)
        + std::string("post-bytes") + static_cast<char>(0x81);
}

std::string delegatedValue() {
    return std::string("delegated") + '\0' + static_cast<char>(0xff)
        + std::string("preview-update") + static_cast<char>(0x82);
}

std::string detachedValue() {
    return std::string("detached") + '\0' + static_cast<char>(0xff)
        + std::string("proof-preview") + static_cast<char>(0x83);
}

std::string detachedMergedValue() {
    return std::string("detached-merged") + '\0' + static_cast<char>(0xff)
        + std::string("proof-preview") + static_cast<char>(0x87);
}

std::string privateValue() {
    return std::string("private") + '\0' + static_cast<char>(0xff)
        + std::string("option") + static_cast<char>(0x84);
}

std::string privatePostValue() {
    return std::string("private") + '\0' + static_cast<char>(0xff)
        + std::string("post") + static_cast<char>(0x85);
}

std::string privateDelegatedValue() {
    return std::string("private-delegated") + '\0' + static_cast<char>(0xff)
        + std::string("option") + static_cast<char>(0x86);
}

std::string privateMergedValue() {
    return std::string("private-merged") + '\0' + static_cast<char>(0xff)
        + std::string("option") + static_cast<char>(0x88);
}

void printFixtureValues(std::ostream &out) {
    out << "    \"binaryKeyHex\": ";
    jsonString(out, hex(binaryKey()));
    out << ",\n";
    out << "    \"binaryValueHex\": ";
    jsonString(out, hex(binaryValue()));
    out << ",\n";
    out << "    \"previewValueHex\": ";
    jsonString(out, hex(previewValue()));
    out << ",\n";
    out << "    \"delegatedValueHex\": ";
    jsonString(out, hex(delegatedValue()));
    out << ",\n";
    out << "    \"detachedValueHex\": ";
    jsonString(out, hex(detachedValue()));
    out << ",\n";
    out << "    \"detachedMergedValueHex\": ";
    jsonString(out, hex(detachedMergedValue()));
    out << ",\n";
    out << "    \"privateValueHex\": ";
    jsonString(out, hex(privateValue()));
    out << ",\n";
    out << "    \"privatePostValueHex\": ";
    jsonString(out, hex(privatePostValue()));
    out << ",\n";
    out << "    \"privateDelegatedValueHex\": ";
    jsonString(out, hex(privateDelegatedValue()));
    out << ",\n";
    out << "    \"privateMergedValueHex\": ";
    jsonString(out, hex(privateMergedValue()));
    out << "\n";
}

void createMixedStore(const std::string &dir) {
    auto env = createEnv(dir);
    auto txn = lmdb::txn::begin(env, nullptr, 0);
    auto state = lmdb::dbi::open(txn, "quadrable_quadb_state", MDB_CREATE);

    quadrable::Quadrable tracked;
    tracked.trackKeys = true;
    tracked.init(txn);
    tracked.checkout("master");
    put(tracked, txn, "wp_options:plain", "plain");
    put(tracked, txn, binaryKey(), binaryValue());
    put(tracked, txn, "wp_posts:1", "Published post");
    put(tracked, txn, "wp_postmeta:1:_thumbnail_id", "42");

    std::string masterRoot = tracked.root(txn);
    quadrable::Proof binaryProof = tracked.exportProof(txn, {
        binaryKey(),
        "wp_posts:404",
    });

    tracked.fork(txn, "2");
    put(tracked, txn, "wp_posts:2", previewValue());
    put(tracked, txn, "wp_postmeta:2:_edit_lock", "1716400000:1");
    tracked.checkout("master");
    tracked.fork(txn, "10");
    tracked.checkout("2");
    tracked.fork(txn, "a-preview");

    quadrable::Quadrable privateFull;
    privateFull.trackKeys = false;
    privateFull.init(txn);
    privateFull.checkout("private-full");
    put(privateFull, txn, "wp_options:private", privateValue());
    put(privateFull, txn, "wp_posts:private", privatePostValue());
    std::string privateRoot = privateFull.root(txn);
    quadrable::Proof privateProof = privateFull.exportProof(txn, {
        "wp_options:private",
        "wp_posts:missing",
    });

    tracked.checkout("binary-proof");
    tracked.importProof(txn, binaryProof, masterRoot);
    put(tracked, txn, binaryKey(), delegatedValue());

    privateFull.checkout("private-proof");
    privateFull.importProof(txn, privateProof, privateRoot);
    put(privateFull, txn, "wp_options:private", privateDelegatedValue());

    tracked.checkout();
    tracked.importProof(txn, binaryProof, masterRoot);
    put(tracked, txn, binaryKey(), detachedValue());

    state.del(txn, "currHead");
    state.put(txn, "detachedHead", lmdb::to_sv<uint64_t>(tracked.getHeadNodeId(txn)));

    txn.commit();
}

quadrable::Proof createUpdatedPostProof(const std::string &dir, std::string &updatedRoot) {
    auto env = createEnv(dir);
    auto txn = lmdb::txn::begin(env, nullptr, 0);

    quadrable::Quadrable auth;
    auth.trackKeys = true;
    auth.init(txn);
    auth.checkout("master");
    put(auth, txn, "wp_options:plain", "plain");
    put(auth, txn, binaryKey(), detachedMergedValue());
    put(auth, txn, "wp_posts:1", "Published post");
    put(auth, txn, "wp_postmeta:1:_thumbnail_id", "42");

    updatedRoot = auth.root(txn);
    quadrable::Proof proof = auth.exportProof(txn, {"wp_posts:1"});

    txn.commit();

    return proof;
}

quadrable::Proof createUpdatedPrivatePostProof(const std::string &dir, std::string &updatedRoot) {
    auto env = createEnv(dir);
    auto txn = lmdb::txn::begin(env, nullptr, 0);

    quadrable::Quadrable auth;
    auth.trackKeys = false;
    auth.init(txn);
    auth.checkout("private-full");
    put(auth, txn, "wp_options:private", privateMergedValue());
    put(auth, txn, "wp_posts:private", privatePostValue());

    updatedRoot = auth.root(txn);
    quadrable::Proof proof = auth.exportProof(txn, {"wp_posts:private"});

    txn.commit();

    return proof;
}

enum class MergeMode {
    Detached,
    NamedTracked,
    NamedNoTrack,
};

void mergeDump(const std::string &restoredDir, const std::string &authDir, MergeMode mode = MergeMode::Detached) {
    std::string authoritativeUpdatedRoot;
    quadrable::Proof postProof = mode == MergeMode::NamedNoTrack
        ? createUpdatedPrivatePostProof(authDir, authoritativeUpdatedRoot)
        : createUpdatedPostProof(authDir, authoritativeUpdatedRoot);

    auto env = openEnv(restoredDir);
    auto txn = lmdb::txn::begin(env, nullptr, 0);

    quadrable::Quadrable db;
    db.trackKeys = mode != MergeMode::NamedNoTrack;
    db.init(txn);
    auto state = lmdb::dbi::open(txn, "quadrable_quadb_state", MDB_CREATE);
    checkoutFromState(db, txn, state);
    if (mode == MergeMode::NamedTracked) {
        db.checkout("binary-proof");
        persistState(db, txn, state);
    } else if (mode == MergeMode::NamedNoTrack) {
        db.checkout("private-proof");
        persistState(db, txn, state);
    }

    std::string restoredRoot = db.root(txn);

    std::cout << "{\n";
    std::cout << "  \"upstream\": {\n";
    std::cout << "    \"repo\": \"hoytech/quadrable\",\n";
    std::cout << "    \"commit\": \"4f44437dc9b951a91986ad69e2856938387be614\",\n";
    std::cout << "    \"source\": \"lanes/quadrable/notes/upstream-lmdb-mixed-raw-restored-merge-fixture.cpp\",\n";
    std::cout << "    \"rawRestore\": \"mixed tracked/noTrack/full/proof/detached database restored with mdb_dump -a and mdb_load -a before update and mergeProof\"\n";
    std::cout << "  },\n";
    if (mode == MergeMode::NamedTracked) {
        std::cout << "  \"scenario\": \"Mixed raw-restored WordPress store keeps tracked full heads, noTrack full/proof heads, an orphan detached proof head, and the active named proof head while the named proof head accepts a proven binary option update, merges a same-updated-root post proof, and leaves imported proof nodes for quadb gc\",\n";
    } else if (mode == MergeMode::NamedNoTrack) {
        std::cout << "  \"scenario\": \"Mixed raw-restored WordPress store keeps tracked full heads, noTrack full/proof heads, tracked proof heads, and an orphan detached proof head while the active noTrack proof head accepts a private option update, merges a same-updated-root private post proof, keeps new private keys out of quadrable_key, and leaves imported proof nodes for quadb gc\",\n";
    } else {
        std::cout << "  \"scenario\": \"Mixed raw-restored WordPress store keeps tracked full heads, noTrack full/proof heads, a named proof head, and the active detached proof head while the detached head accepts a proven binary option update, merges a same-updated-root post proof, and leaves imported proof nodes for quadb gc\",\n";
    }
    std::cout << "  \"fixtureValues\": {\n";
    printFixtureValues(std::cout);
    std::cout << "  },\n";
    std::cout << "  \"roots\": {\n";
    std::cout << "    \"restoredRootHex\": ";
    jsonString(std::cout, hex(restoredRoot));
    std::cout << ",\n";
    std::cout << "    \"authoritativeUpdatedRootHex\": ";
    jsonString(std::cout, hex(authoritativeUpdatedRoot));
    std::cout << "\n";
    std::cout << "  },\n";
    dumpPhase(std::cout, "beforeUpdate", txn, hex(restoredRoot));
    std::cout << ",\n";

    if (mode == MergeMode::NamedNoTrack) {
        put(db, txn, "wp_options:private", privateMergedValue());
    } else {
        put(db, txn, binaryKey(), detachedMergedValue());
    }
    persistState(db, txn, state);
    std::string updatedRoot = db.root(txn);

    std::cout << "  \"updatedRootHex\": ";
    jsonString(std::cout, hex(updatedRoot));
    std::cout << ",\n";
    dumpPhase(std::cout, "afterUpdateBeforeMerge", txn, hex(updatedRoot));
    std::cout << ",\n";

    db.mergeProof(txn, postProof);
    persistState(db, txn, state);
    std::string mergedRoot = db.root(txn);

    std::cout << "  \"mergedRootHex\": ";
    jsonString(std::cout, hex(mergedRoot));
    std::cout << ",\n";
    dumpPhase(std::cout, "afterMergeBeforeGc", txn, hex(mergedRoot));
    std::cout << ",\n";

    quadrable::Quadrable::GCStats stats;
    {
        quadrable::Quadrable::GarbageCollector gc(db);
        gc.markAllHeads(txn);
        if (db.isDetachedHead()) {
            gc.markTree(txn, db.getHeadNodeId(txn));
        }
        stats = gc.sweep(txn);
        gc.deleteNodes(txn);
    }
    persistState(db, txn, state);

    std::cout << "  \"gc\": {\"total\": " << stats.total << ", \"garbage\": " << stats.garbage << "},\n";
    dumpPhase(std::cout, "afterGc", txn, hex(db.root(txn)));
    std::cout << "\n";
    std::cout << "}\n";

    txn.commit();
}

} // namespace

int main(int argc, char **argv) {
    if (argc < 3) {
        std::cerr << "usage: upstream-lmdb-mixed-raw-restored-merge-fixture (create-source|merge-dump|merge-dump-named|merge-dump-notrack) <db-dir|restored-db-dir> [auth-db-dir]\n";
        return 2;
    }

    const std::string mode = argv[1];
    const std::string dir = argv[2];

    if (mode == "create-source") {
        createMixedStore(dir);
        return 0;
    }

    if (mode == "merge-dump" || mode == "merge-dump-named" || mode == "merge-dump-notrack") {
        if (argc != 4) {
            std::cerr << "usage: upstream-lmdb-mixed-raw-restored-merge-fixture " << mode << " <restored-db-dir> <auth-db-dir>\n";
            return 2;
        }
        MergeMode mergeMode = MergeMode::Detached;
        if (mode == "merge-dump-named") {
            mergeMode = MergeMode::NamedTracked;
        } else if (mode == "merge-dump-notrack") {
            mergeMode = MergeMode::NamedNoTrack;
        }
        mergeDump(dir, argv[3], mergeMode);
        return 0;
    }

    std::cerr << "unknown mode: " << mode << "\n";
    return 2;
}
