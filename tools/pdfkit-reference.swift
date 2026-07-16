#!/usr/bin/env swift

import Foundation
import PDFKit

struct ReferenceLine: Codable {
    let text: String
    let x: Double
    let y: Double
    let width: Double
    let height: Double
}

struct ReferencePage: Codable {
    let number: Int
    let width: Double
    let height: Double
    let rotation: Int
    let text: String
    let lines: [ReferenceLine]
}

struct ReferenceDocument: Codable {
    let extractor: String
    let pageCount: Int
    let pages: [ReferencePage]
}

func normalizedText(_ value: String?) -> String {
    let text = value ?? ""
    return text.replacingOccurrences(of: "\u{00a0}", with: " ")
        .trimmingCharacters(in: .whitespacesAndNewlines)
}

func rounded(_ value: CGFloat) -> Double {
    return (Double(value) * 1000.0).rounded() / 1000.0
}

guard CommandLine.arguments.count == 2 else {
    FileHandle.standardError.write(Data("Usage: pdfkit-reference.swift <input.pdf>\n".utf8))
    exit(2)
}

let input = URL(fileURLWithPath: CommandLine.arguments[1])
guard let document = PDFDocument(url: input) else {
    FileHandle.standardError.write(Data("PDFKit could not open the input document.\n".utf8))
    exit(1)
}

var pages: [ReferencePage] = []
for index in 0..<document.pageCount {
    guard let page = document.page(at: index) else {
        continue
    }

    let bounds = page.bounds(for: .mediaBox)
    let selection = page.selection(for: bounds)
    let lines = (selection?.selectionsByLine() ?? []).compactMap { lineSelection -> ReferenceLine? in
        let text = normalizedText(lineSelection.string)
        guard !text.isEmpty else {
            return nil
        }
        let lineBounds = lineSelection.bounds(for: page)
        return ReferenceLine(
            text: text,
            x: rounded(lineBounds.minX),
            y: rounded(lineBounds.minY),
            width: rounded(lineBounds.width),
            height: rounded(lineBounds.height)
        )
    }

    pages.append(ReferencePage(
        number: index + 1,
        width: rounded(bounds.width),
        height: rounded(bounds.height),
        rotation: page.rotation,
        text: normalizedText(page.string),
        lines: lines
    ))
}

let reference = ReferenceDocument(
    extractor: "macOS PDFKit",
    pageCount: document.pageCount,
    pages: pages
)
let encoder = JSONEncoder()
encoder.outputFormatting = [.prettyPrinted, .sortedKeys, .withoutEscapingSlashes]
do {
    let data = try encoder.encode(reference)
    FileHandle.standardOutput.write(data)
} catch {
    FileHandle.standardError.write(Data("Could not encode PDFKit reference JSON: \(error)\n".utf8))
    exit(1)
}
