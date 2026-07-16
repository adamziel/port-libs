#include <filesystem>
#include <iostream>
#include <string>
#include <string_view>
#include <vector>

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

lmdb::env openEnv(const std::string &dir) {
    std::filesystem::remove_all(dir);
    std::filesystem::create_directories(dir);

    lmdb::env env = lmdb::env::create();
    env.set_max_dbs(64);
    env.set_mapsize(1UL * 1024UL * 1024UL * 1024UL * 1024UL);
    env.open(dir.c_str(), MDB_CREATE, 0664);
    env.reader_check();

    return env;
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

} // namespace

int main(int argc, char **argv) {
    if (argc != 7) {
        std::cerr << "usage: upstream-lmdb-cursor-fixture <source-db-dir> <proof-db-dir> <detached-proof-db-dir> <merge-gc-db-dir> <notrack-db-dir> <notrack-proof-db-dir>\n";
        return 2;
    }

    const std::string sourceDir = argv[1];
    const std::string proofDir = argv[2];
    const std::string detachedProofDir = argv[3];
    const std::string mergeGcDir = argv[4];
    const std::string noTrackDir = argv[5];
    const std::string noTrackProofDir = argv[6];

    const std::string binaryKey = std::string("wp_options:serialized-") + static_cast<char>(0xff);
    const std::string binaryValue = std::string("autoload") + '\0' + static_cast<char>(0xff)
        + std::string("serialized:site-option") + static_cast<char>(0x80);
    const std::string previewValue = std::string("preview") + '\0' + static_cast<char>(0xff)
        + std::string("post-bytes") + static_cast<char>(0x81);
    const std::string delegatedValue = std::string("delegated") + '\0' + static_cast<char>(0xff)
        + std::string("preview-update") + static_cast<char>(0x82);
    const std::string detachedValue = std::string("detached") + '\0' + static_cast<char>(0xff)
        + std::string("proof-preview") + static_cast<char>(0x83);
    const std::string noTrackDelegatedValue = std::string("notrack") + '\0' + static_cast<char>(0xff)
        + std::string("delegated-update") + static_cast<char>(0x84);

    auto sourceEnv = openEnv(sourceDir);
    auto proofEnv = openEnv(proofDir);
    auto detachedProofEnv = openEnv(detachedProofDir);
    auto mergeGcEnv = openEnv(mergeGcDir);
    auto noTrackEnv = openEnv(noTrackDir);
    auto noTrackProofEnv = openEnv(noTrackProofDir);

    quadrable::Proof binaryProof;
    quadrable::Proof plainProof;
    std::string masterRoot;
    std::string masterRootHex;
    std::string previewRootHex;
    std::string delegatedRootHex;
    std::string detachedRootHex;
    std::string mergeGcRootHex;
    std::string noTrackMasterRoot;
    std::string noTrackMasterRootHex;
    std::string noTrackPreviewRootHex;
    std::string noTrackDelegatedRootHex;
    quadrable::Quadrable::GCStats mergeGcStats;

    auto sourceTxn = lmdb::txn::begin(sourceEnv, nullptr, 0);
    quadrable::Quadrable source;
    source.trackKeys = true;
    source.init(sourceTxn);
    auto sourceState = lmdb::dbi::open(sourceTxn, "quadrable_quadb_state", MDB_CREATE);

    source.checkout("master");
    put(source, sourceTxn, "wp_options:plain", "plain");
    put(source, sourceTxn, binaryKey, binaryValue);
    masterRoot = source.root(sourceTxn);
    masterRootHex = hex(masterRoot);
    binaryProof = source.exportProof(sourceTxn, {binaryKey, "wp_posts:404"});
    plainProof = source.exportProof(sourceTxn, {"wp_options:plain"});

    source.fork(sourceTxn, "2");
    put(source, sourceTxn, "wp_posts:2", previewValue);
    source.checkout("master");
    source.fork(sourceTxn, "10");
    source.checkout("2");
    source.fork(sourceTxn, "a-preview");
    previewRootHex = hex(source.root(sourceTxn));
    sourceState.put(sourceTxn, "currHead", std::string("a-preview"));

    auto proofTxn = lmdb::txn::begin(proofEnv, nullptr, 0);
    quadrable::Quadrable proofDb;
    proofDb.trackKeys = true;
    proofDb.init(proofTxn);
    auto proofState = lmdb::dbi::open(proofTxn, "quadrable_quadb_state", MDB_CREATE);

    proofDb.checkout("binary-proof");
    proofDb.importProof(proofTxn, binaryProof, masterRoot);
    put(proofDb, proofTxn, binaryKey, delegatedValue);
    delegatedRootHex = hex(proofDb.root(proofTxn));
    proofState.put(proofTxn, "currHead", std::string("binary-proof"));

    auto detachedProofTxn = lmdb::txn::begin(detachedProofEnv, nullptr, 0);
    quadrable::Quadrable detachedProofDb;
    detachedProofDb.trackKeys = true;
    detachedProofDb.init(detachedProofTxn);
    auto detachedProofState = lmdb::dbi::open(detachedProofTxn, "quadrable_quadb_state", MDB_CREATE);

    detachedProofDb.checkout();
    detachedProofDb.importProof(detachedProofTxn, binaryProof, masterRoot);
    put(detachedProofDb, detachedProofTxn, binaryKey, detachedValue);
    detachedRootHex = hex(detachedProofDb.root(detachedProofTxn));
    detachedProofState.put(detachedProofTxn, "detachedHead", lmdb::to_sv<uint64_t>(detachedProofDb.getHeadNodeId(detachedProofTxn)));

    auto mergeGcTxn = lmdb::txn::begin(mergeGcEnv, nullptr, 0);
    quadrable::Quadrable mergeGcDb;
    mergeGcDb.trackKeys = true;
    mergeGcDb.init(mergeGcTxn);
    auto mergeGcState = lmdb::dbi::open(mergeGcTxn, "quadrable_quadb_state", MDB_CREATE);

    mergeGcDb.checkout("merge-gc-proof");
    mergeGcDb.importProof(mergeGcTxn, binaryProof, masterRoot);
    mergeGcDb.mergeProof(mergeGcTxn, plainProof);
    mergeGcRootHex = hex(mergeGcDb.root(mergeGcTxn));
    mergeGcState.put(mergeGcTxn, "currHead", std::string("merge-gc-proof"));

    quadrable::Proof noTrackProof;

    auto noTrackTxn = lmdb::txn::begin(noTrackEnv, nullptr, 0);
    quadrable::Quadrable noTrackDb;
    noTrackDb.trackKeys = false;
    noTrackDb.init(noTrackTxn);
    auto noTrackState = lmdb::dbi::open(noTrackTxn, "quadrable_quadb_state", MDB_CREATE);

    noTrackDb.checkout("master");
    put(noTrackDb, noTrackTxn, "wp_options:plain", "plain");
    put(noTrackDb, noTrackTxn, binaryKey, binaryValue);
    noTrackMasterRoot = noTrackDb.root(noTrackTxn);
    noTrackMasterRootHex = hex(noTrackMasterRoot);
    noTrackProof = noTrackDb.exportProof(noTrackTxn, {binaryKey, "wp_posts:404"});

    noTrackDb.fork(noTrackTxn, "2");
    put(noTrackDb, noTrackTxn, "wp_posts:2", previewValue);
    noTrackDb.checkout("master");
    noTrackDb.fork(noTrackTxn, "10");
    noTrackDb.checkout("2");
    noTrackDb.fork(noTrackTxn, "a-preview");
    noTrackPreviewRootHex = hex(noTrackDb.root(noTrackTxn));
    noTrackState.put(noTrackTxn, "currHead", std::string("a-preview"));

    auto noTrackProofTxn = lmdb::txn::begin(noTrackProofEnv, nullptr, 0);
    quadrable::Quadrable noTrackProofDb;
    noTrackProofDb.trackKeys = false;
    noTrackProofDb.init(noTrackProofTxn);
    auto noTrackProofState = lmdb::dbi::open(noTrackProofTxn, "quadrable_quadb_state", MDB_CREATE);

    noTrackProofDb.checkout("private-proof");
    noTrackProofDb.importProof(noTrackProofTxn, noTrackProof, noTrackMasterRoot);
    put(noTrackProofDb, noTrackProofTxn, binaryKey, noTrackDelegatedValue);
    noTrackDelegatedRootHex = hex(noTrackProofDb.root(noTrackProofTxn));
    noTrackProofState.put(noTrackProofTxn, "currHead", std::string("private-proof"));

    std::cout << "{\n";
    std::cout << "  \"upstream\": {\n";
    std::cout << "    \"repo\": \"hoytech/quadrable\",\n";
    std::cout << "    \"commit\": \"4f44437dc9b951a91986ad69e2856938387be614\",\n";
    std::cout << "    \"source\": \"lanes/quadrable/notes/upstream-lmdb-cursor-fixture.cpp\"\n";
    std::cout << "  },\n";
    std::cout << "  \"scenario\": \"WordPress binary tracked key/value rows, string-sorted numeric heads, proof-backed delegated and detached updates, noTrackKeys empty key buckets, and mergeProof plus quadb gc raw LMDB cursor bytes\",\n";
    std::cout << "  \"binaryFixture\": {\n";
    std::cout << "    \"keyHex\": ";
    jsonString(std::cout, hex(binaryKey));
    std::cout << ",\n";
    std::cout << "    \"valueHex\": ";
    jsonString(std::cout, hex(binaryValue));
    std::cout << ",\n";
    std::cout << "    \"previewValueHex\": ";
    jsonString(std::cout, hex(previewValue));
    std::cout << ",\n";
    std::cout << "    \"delegatedValueHex\": ";
    jsonString(std::cout, hex(delegatedValue));
    std::cout << ",\n";
    std::cout << "    \"detachedValueHex\": ";
    jsonString(std::cout, hex(detachedValue));
    std::cout << ",\n";
    std::cout << "    \"noTrackDelegatedValueHex\": ";
    jsonString(std::cout, hex(noTrackDelegatedValue));
    std::cout << "\n";
    std::cout << "  },\n";
    std::cout << "  \"fullHead\": {\n";
    std::cout << "    \"rootHex\": ";
    jsonString(std::cout, previewRootHex);
    std::cout << ",\n";
    dumpEntries(std::cout, sourceTxn);
    std::cout << "\n";
    std::cout << "  },\n";
    std::cout << "  \"proofHead\": {\n";
    std::cout << "    \"sourceRootHex\": ";
    jsonString(std::cout, masterRootHex);
    std::cout << ",\n";
    std::cout << "    \"rootHex\": ";
    jsonString(std::cout, delegatedRootHex);
    std::cout << ",\n";
    dumpEntries(std::cout, proofTxn);
    std::cout << "\n";
    std::cout << "  },\n";
    std::cout << "  \"detachedProofHead\": {\n";
    std::cout << "    \"sourceRootHex\": ";
    jsonString(std::cout, masterRootHex);
    std::cout << ",\n";
    std::cout << "    \"rootHex\": ";
    jsonString(std::cout, detachedRootHex);
    std::cout << ",\n";
    dumpEntries(std::cout, detachedProofTxn);
    std::cout << "\n";
    std::cout << "  },\n";
    std::cout << "  \"mergeGcProofHead\": {\n";
    std::cout << "    \"sourceRootHex\": ";
    jsonString(std::cout, masterRootHex);
    std::cout << ",\n";
    std::cout << "    \"rootHex\": ";
    jsonString(std::cout, mergeGcRootHex);
    std::cout << ",\n";
    std::cout << "    \"beforeGc\": {\n";
    dumpEntries(std::cout, mergeGcTxn);
    std::cout << "\n";
    std::cout << "    },\n";

    {
        quadrable::Quadrable::GarbageCollector gc(mergeGcDb);
        gc.markAllHeads(mergeGcTxn);
        if (mergeGcDb.isDetachedHead()) gc.markTree(mergeGcTxn, mergeGcDb.getHeadNodeId(mergeGcTxn));
        mergeGcStats = gc.sweep(mergeGcTxn);
        gc.deleteNodes(mergeGcTxn);
    }

    std::cout << "    \"gc\": {\"total\": " << mergeGcStats.total
        << ", \"garbage\": " << mergeGcStats.garbage << "},\n";
    std::cout << "    \"afterGc\": {\n";
    dumpEntries(std::cout, mergeGcTxn);
    std::cout << "\n";
    std::cout << "    }\n";
    std::cout << "  }\n";
    std::cout << "  ,\n";
    std::cout << "  \"noTrackHead\": {\n";
    std::cout << "    \"rootHex\": ";
    jsonString(std::cout, noTrackPreviewRootHex);
    std::cout << ",\n";
    dumpEntries(std::cout, noTrackTxn);
    std::cout << "\n";
    std::cout << "  },\n";
    std::cout << "  \"noTrackProofHead\": {\n";
    std::cout << "    \"sourceRootHex\": ";
    jsonString(std::cout, noTrackMasterRootHex);
    std::cout << ",\n";
    std::cout << "    \"rootHex\": ";
    jsonString(std::cout, noTrackDelegatedRootHex);
    std::cout << ",\n";
    dumpEntries(std::cout, noTrackProofTxn);
    std::cout << "\n";
    std::cout << "  }\n";
    std::cout << "}\n";

    noTrackProofTxn.commit();
    noTrackTxn.commit();
    mergeGcTxn.commit();
    detachedProofTxn.commit();
    proofTxn.commit();
    sourceTxn.commit();

    return 0;
}
