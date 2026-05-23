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
    auto dbi = lmdb::dbi::open(txn, name);
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
    if (argc != 3) {
        std::cerr << "usage: upstream-lmdb-cursor-fixture <source-db-dir> <proof-db-dir>\n";
        return 2;
    }

    const std::string sourceDir = argv[1];
    const std::string proofDir = argv[2];

    const std::string binaryKey = std::string("wp_options:serialized-") + static_cast<char>(0xff);
    const std::string binaryValue = std::string("autoload") + '\0' + static_cast<char>(0xff)
        + std::string("serialized:site-option") + static_cast<char>(0x80);
    const std::string previewValue = std::string("preview") + '\0' + static_cast<char>(0xff)
        + std::string("post-bytes") + static_cast<char>(0x81);
    const std::string delegatedValue = std::string("delegated") + '\0' + static_cast<char>(0xff)
        + std::string("preview-update") + static_cast<char>(0x82);

    auto sourceEnv = openEnv(sourceDir);
    auto proofEnv = openEnv(proofDir);

    quadrable::Proof binaryProof;
    std::string masterRoot;
    std::string masterRootHex;
    std::string previewRootHex;
    std::string delegatedRootHex;

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

    std::cout << "{\n";
    std::cout << "  \"upstream\": {\n";
    std::cout << "    \"repo\": \"hoytech/quadrable\",\n";
    std::cout << "    \"commit\": \"4f44437dc9b951a91986ad69e2856938387be614\",\n";
    std::cout << "    \"source\": \"lanes/quadrable/notes/upstream-lmdb-cursor-fixture.cpp\"\n";
    std::cout << "  },\n";
    std::cout << "  \"scenario\": \"WordPress binary tracked key/value rows, string-sorted numeric heads, and proof-backed delegated update raw LMDB cursor bytes\",\n";
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
    std::cout << "  }\n";
    std::cout << "}\n";

    proofTxn.commit();
    sourceTxn.commit();

    return 0;
}
