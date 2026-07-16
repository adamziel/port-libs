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

void put(quadrable::Quadrable &db, lmdb::txn &txn, std::string_view key, std::string_view value) {
    auto changes = db.change();
    changes.put(key, value);
    changes.apply(txn);
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

std::string proofHeadName(bool trackKeys) {
    return trackKeys ? "wp-delegated-raw" : "private-delegated-raw";
}

void createSource(const std::string &dir, bool detachedProofHead, bool trackKeys = true) {
    auto env = createEnv(dir);
    auto txn = lmdb::txn::begin(env, nullptr, 0);
    auto state = lmdb::dbi::open(txn, "quadrable_quadb_state", MDB_CREATE);

    quadrable::Quadrable source;
    source.trackKeys = trackKeys;
    source.init(txn);
    source.checkout("master");
    put(source, txn, "wp_options:siteurl", "https://example.test");
    put(source, txn, "wp_options:home", "https://example.test");
    put(source, txn, "wp_posts:1", "Published post");

    std::string root = source.root(txn);
    quadrable::Proof siteUrlProof = source.exportProof(txn, {"wp_options:siteurl"});

    quadrable::Quadrable proofDb;
    proofDb.trackKeys = trackKeys;
    proofDb.init(txn);
    if (detachedProofHead) {
        proofDb.checkout();
    } else {
        proofDb.checkout(proofHeadName(trackKeys));
    }
    proofDb.importProof(txn, siteUrlProof, root);
    persistState(proofDb, txn, state);

    txn.commit();
}

quadrable::Proof createUpdatedHomeProof(const std::string &dir, std::string &updatedRoot, bool trackKeys = true) {
    auto env = createEnv(dir);
    auto txn = lmdb::txn::begin(env, nullptr, 0);

    quadrable::Quadrable auth;
    auth.trackKeys = trackKeys;
    auth.init(txn);
    auth.checkout("master");
    put(auth, txn, "wp_options:siteurl", "https://preview.example.test");
    put(auth, txn, "wp_options:home", "https://example.test");
    put(auth, txn, "wp_posts:1", "Published post");

    updatedRoot = auth.root(txn);
    quadrable::Proof proof = auth.exportProof(txn, {"wp_options:home"});

    txn.commit();

    return proof;
}

struct SequentialProofs {
    std::string updatedRoot;
    quadrable::Proof homeProof;
    quadrable::Proof postProof;
};

SequentialProofs createUpdatedSequentialProofs(const std::string &dir) {
    auto env = createEnv(dir);
    auto txn = lmdb::txn::begin(env, nullptr, 0);

    quadrable::Quadrable auth;
    auth.trackKeys = true;
    auth.init(txn);
    auth.checkout("master");
    put(auth, txn, "wp_options:siteurl", "https://preview.example.test");
    put(auth, txn, "wp_options:home", "https://example.test");
    put(auth, txn, "wp_posts:1", "Published post");

    SequentialProofs proofs;
    proofs.updatedRoot = auth.root(txn);
    proofs.homeProof = auth.exportProof(txn, {"wp_options:home"});
    proofs.postProof = auth.exportProof(txn, {"wp_posts:1"});

    txn.commit();

    return proofs;
}

void mergeDump(const std::string &restoredDir, const std::string &authDir, bool trackKeys = true) {
    std::string authoritativeUpdatedRoot;
    quadrable::Proof homeProof = createUpdatedHomeProof(authDir, authoritativeUpdatedRoot, trackKeys);

    auto env = openEnv(restoredDir);
    auto txn = lmdb::txn::begin(env, nullptr, 0);

    quadrable::Quadrable db;
    db.trackKeys = trackKeys;
    db.init(txn);
    auto state = lmdb::dbi::open(txn, "quadrable_quadb_state", MDB_CREATE);
    checkoutFromState(db, txn, state);
    bool detached = db.isDetachedHead();

    std::string restoredRoot = db.root(txn);

    std::cout << "{\n";
    std::cout << "  \"upstream\": {\n";
    std::cout << "    \"repo\": \"hoytech/quadrable\",\n";
    std::cout << "    \"commit\": \"4f44437dc9b951a91986ad69e2856938387be614\",\n";
    std::cout << "    \"source\": \"lanes/quadrable/notes/upstream-lmdb-raw-restored-merge-fixture.cpp\",\n";
    std::cout << "    \"rawRestore\": \"source proof-head database restored with mdb_dump -a and mdb_load -a before update and mergeProof\"\n";
    std::cout << "  },\n";
    if (detached) {
        std::cout << "  \"scenario\": \"Detached raw-restored WordPress delegated proof head accepts a proven siteurl update, merges a same-updated-root home proof, and leaves imported proof nodes for quadb gc\",\n";
    } else if (!trackKeys) {
        std::cout << "  \"scenario\": \"noTrackKeys raw-restored WordPress delegated proof head accepts a proven siteurl update, merges a same-updated-root home proof, and keeps the key bucket empty through quadb gc\",\n";
    } else {
        std::cout << "  \"scenario\": \"Raw-restored WordPress delegated proof head accepts a proven siteurl update, merges a same-updated-root home proof, and leaves imported proof nodes for quadb gc\",\n";
    }
    std::cout << "  \"fixtureValues\": {\n";
    std::cout << "    \"siteUrlKey\": \"wp_options:siteurl\",\n";
    std::cout << "    \"homeKey\": \"wp_options:home\",\n";
    std::cout << "    \"postKey\": \"wp_posts:1\",\n";
    std::cout << "    \"originalUrl\": \"https://example.test\",\n";
    std::cout << "    \"updatedUrl\": \"https://preview.example.test\",\n";
    std::cout << "    \"postValue\": \"Published post\",\n";
    std::cout << "    \"noTrackKeys\": " << (trackKeys ? "false" : "true") << ",\n";
    std::cout << "    \"detached\": " << (detached ? "true" : "false") << ",\n";
    std::cout << "    \"head\": ";
    if (detached) {
        std::cout << "null\n";
    } else {
        jsonString(std::cout, proofHeadName(trackKeys));
        std::cout << "\n";
    }
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

    put(db, txn, "wp_options:siteurl", "https://preview.example.test");
    persistState(db, txn, state);
    std::string updatedRoot = db.root(txn);

    std::cout << "  \"updatedRootHex\": ";
    jsonString(std::cout, hex(updatedRoot));
    std::cout << ",\n";
    dumpPhase(std::cout, "afterUpdateBeforeMerge", txn, hex(updatedRoot));
    std::cout << ",\n";

    db.mergeProof(txn, homeProof);
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

void mergeDumpSequential(const std::string &restoredDir, const std::string &authDir) {
    SequentialProofs proofs = createUpdatedSequentialProofs(authDir);

    auto env = openEnv(restoredDir);
    auto txn = lmdb::txn::begin(env, nullptr, 0);

    quadrable::Quadrable db;
    db.trackKeys = true;
    db.init(txn);
    auto state = lmdb::dbi::open(txn, "quadrable_quadb_state", MDB_CREATE);
    checkoutFromState(db, txn, state);

    std::string restoredRoot = db.root(txn);

    std::cout << "{\n";
    std::cout << "  \"upstream\": {\n";
    std::cout << "    \"repo\": \"hoytech/quadrable\",\n";
    std::cout << "    \"commit\": \"4f44437dc9b951a91986ad69e2856938387be614\",\n";
    std::cout << "    \"source\": \"lanes/quadrable/notes/upstream-lmdb-raw-restored-merge-fixture.cpp\",\n";
    std::cout << "    \"rawRestore\": \"source proof-head database restored with mdb_dump -a and mdb_load -a before update and two sequential mergeProof calls\"\n";
    std::cout << "  },\n";
    std::cout << "  \"scenario\": \"Raw-restored WordPress delegated proof head accepts a proven siteurl update, then sequentially merges same-updated-root home and post proofs before quadb gc\",\n";
    std::cout << "  \"fixtureValues\": {\n";
    std::cout << "    \"siteUrlKey\": \"wp_options:siteurl\",\n";
    std::cout << "    \"homeKey\": \"wp_options:home\",\n";
    std::cout << "    \"postKey\": \"wp_posts:1\",\n";
    std::cout << "    \"originalUrl\": \"https://example.test\",\n";
    std::cout << "    \"updatedUrl\": \"https://preview.example.test\",\n";
    std::cout << "    \"postValue\": \"Published post\",\n";
    std::cout << "    \"head\": ";
    jsonString(std::cout, proofHeadName(true));
    std::cout << "\n";
    std::cout << "  },\n";
    std::cout << "  \"roots\": {\n";
    std::cout << "    \"restoredRootHex\": ";
    jsonString(std::cout, hex(restoredRoot));
    std::cout << ",\n";
    std::cout << "    \"authoritativeUpdatedRootHex\": ";
    jsonString(std::cout, hex(proofs.updatedRoot));
    std::cout << "\n";
    std::cout << "  },\n";
    dumpPhase(std::cout, "beforeUpdate", txn, hex(restoredRoot));
    std::cout << ",\n";

    put(db, txn, "wp_options:siteurl", "https://preview.example.test");
    persistState(db, txn, state);
    std::string updatedRoot = db.root(txn);

    std::cout << "  \"updatedRootHex\": ";
    jsonString(std::cout, hex(updatedRoot));
    std::cout << ",\n";
    dumpPhase(std::cout, "afterUpdateBeforeFirstMerge", txn, hex(updatedRoot));
    std::cout << ",\n";

    db.mergeProof(txn, proofs.homeProof);
    persistState(db, txn, state);
    std::string firstMergedRoot = db.root(txn);

    std::cout << "  \"firstMergedRootHex\": ";
    jsonString(std::cout, hex(firstMergedRoot));
    std::cout << ",\n";
    dumpPhase(std::cout, "afterFirstMergeBeforeSecond", txn, hex(firstMergedRoot));
    std::cout << ",\n";

    db.mergeProof(txn, proofs.postProof);
    persistState(db, txn, state);
    std::string secondMergedRoot = db.root(txn);

    std::cout << "  \"secondMergedRootHex\": ";
    jsonString(std::cout, hex(secondMergedRoot));
    std::cout << ",\n";
    dumpPhase(std::cout, "afterSecondMergeBeforeGc", txn, hex(secondMergedRoot));
    std::cout << ",\n";

    quadrable::Quadrable::GCStats stats;
    {
        quadrable::Quadrable::GarbageCollector gc(db);
        gc.markAllHeads(txn);
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
        std::cerr << "usage: upstream-lmdb-raw-restored-merge-fixture (create-source|create-source-detached|create-source-notrack|merge-dump|merge-dump-detached|merge-dump-notrack|merge-dump-sequential) <db-dir|restored-db-dir> [auth-db-dir]\n";
        return 2;
    }

    const std::string mode = argv[1];
    const std::string dir = argv[2];

    if (mode == "create-source") {
        createSource(dir, false);
        return 0;
    }

    if (mode == "create-source-detached") {
        createSource(dir, true);
        return 0;
    }

    if (mode == "create-source-notrack") {
        createSource(dir, false, false);
        return 0;
    }

    if (mode == "merge-dump" || mode == "merge-dump-detached" || mode == "merge-dump-notrack") {
        if (argc != 4) {
            std::cerr << "usage: upstream-lmdb-raw-restored-merge-fixture " << mode << " <restored-db-dir> <auth-db-dir>\n";
            return 2;
        }
        mergeDump(dir, argv[3], mode != "merge-dump-notrack");
        return 0;
    }

    if (mode == "merge-dump-sequential") {
        if (argc != 4) {
            std::cerr << "usage: upstream-lmdb-raw-restored-merge-fixture " << mode << " <restored-db-dir> <auth-db-dir>\n";
            return 2;
        }
        mergeDumpSequential(dir, argv[3]);
        return 0;
    }

    std::cerr << "unknown mode: " << mode << "\n";
    return 2;
}
