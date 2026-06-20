<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class TaggedTableStructureExtractor
{
    /**
     * @return array{tables: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function extract(string $pdfBytes): array
    {
        $metadata = (new PdfMetadataExtractor())->extractDocumentMetadata($pdfBytes);
        $taggedContent = (new PdfTextExtractor())->extractTaggedContent($pdfBytes);

        return $this->fromMetadata($metadata, $taggedContent);
    }

    /**
     * @param array<string, mixed> $documentMetadata
     * @param list<array<string, mixed>> $taggedContent
     * @return array{tables: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    public function fromMetadata(array $documentMetadata, array $taggedContent): array
    {
        $structureTree = $documentMetadata['structure_tree'] ?? [];
        if (!is_array($structureTree)) {
            return $this->emptyResult();
        }

        $taggedTables = $structureTree['tagged_tables'] ?? [];
        $elements = $structureTree['elements'] ?? [];
        if (!is_array($taggedTables) || !is_array($elements)) {
            return $this->emptyResult();
        }

        $elementsByObject = $this->elementsByObject($elements);
        $childrenByParent = $this->childrenByParent($elementsByObject);
        $tableSummariesByObject = $this->tableSummariesByObject($taggedTables);
        $textByObject = $this->taggedTextByStructureObject($taggedContent);
        $topLevelTableObjects = $this->integerList($taggedTables['top_level_table_objects'] ?? []);

        $tables = [];
        foreach ($topLevelTableObjects as $tableObject) {
            $html = $this->renderTableHtml(
                $tableObject,
                $tableSummariesByObject,
                $elementsByObject,
                $childrenByParent,
                $textByObject
            );
            if ($html === null) {
                continue;
            }

            $summary = $tableSummariesByObject[$tableObject] ?? [];
            $replaceTexts = $this->tableReplaceTexts($tableObject, $tableSummariesByObject, $textByObject);
            $record = [
                'source' => 'tagged_pdf_struct_tree',
                'review_only' => false,
                'visible_text_source' => true,
                'struct_object' => $tableObject,
                'html' => $html,
                'wordpress_block' => "<!-- wp:table -->\n<figure class=\"wp-block-table\">" . $html . "</figure>\n<!-- /wp:table -->",
                'replace_texts' => $replaceTexts,
                'metadata' => $summary,
                'unambiguous' => true,
            ];

            foreach (['page', 'page_number', 'page_object'] as $key) {
                if (array_key_exists($key, $summary)) {
                    $record[$key] = $summary[$key];
                }
            }

            $tables[] = $record;
        }

        return [
            'tables' => $tables,
            'metadata' => [
                'source' => 'tagged_pdf_struct_tree',
                'review_only' => true,
                'visible_text_source' => false,
                'table_count' => count($tables),
                'nested_table_count' => (int) ($taggedTables['nested_table_count'] ?? 0),
                'top_level_table_objects' => array_column($tables, 'struct_object'),
                'structure_metadata' => $taggedTables,
            ],
        ];
    }

    /**
     * @return array{tables: list<array<string, mixed>>, metadata: array<string, mixed>}
     */
    private function emptyResult(): array
    {
        return [
            'tables' => [],
            'metadata' => [
                'source' => 'tagged_pdf_struct_tree',
                'review_only' => true,
                'visible_text_source' => false,
                'table_count' => 0,
                'nested_table_count' => 0,
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $elements
     * @return array<int, array<string, mixed>>
     */
    private function elementsByObject(array $elements): array
    {
        $byObject = [];
        foreach ($elements as $element) {
            if (!is_array($element)) {
                continue;
            }

            $object = $element['object'] ?? null;
            if (is_int($object)) {
                $byObject[$object] = $element;
            }
        }

        return $byObject;
    }

    /**
     * @param array<int, array<string, mixed>> $elementsByObject
     * @return array<int, list<int>>
     */
    private function childrenByParent(array $elementsByObject): array
    {
        $children = [];
        foreach ($elementsByObject as $object => $element) {
            $parent = $element['parent_object'] ?? null;
            if (is_int($parent)) {
                $children[$parent][] = $object;
            }
        }

        return $children;
    }

    /**
     * @param array<string, mixed> $taggedTables
     * @return array<int, array<string, mixed>>
     */
    private function tableSummariesByObject(array $taggedTables): array
    {
        $summaries = [];
        $tables = $taggedTables['tables'] ?? [];
        if (!is_array($tables)) {
            return [];
        }

        foreach ($tables as $table) {
            if (!is_array($table)) {
                continue;
            }

            $object = $table['struct_object'] ?? null;
            if (is_int($object)) {
                $summaries[$object] = $table;
            }
        }

        return $summaries;
    }

    /**
     * @param list<array<string, mixed>> $taggedContent
     * @return array<int, list<string>>
     */
    private function taggedTextByStructureObject(array $taggedContent): array
    {
        $textByObject = [];
        foreach ($taggedContent as $row) {
            if (!is_array($row)) {
                continue;
            }

            $object = $row['struct_object'] ?? null;
            $text = trim((string) ($row['text'] ?? ''));
            if (is_int($object) && $text !== '') {
                $textByObject[$object][] = $text;
            }
        }

        return $textByObject;
    }

    /**
     * @param array<int, array<string, mixed>> $tableSummariesByObject
     * @param array<int, array<string, mixed>> $elementsByObject
     * @param array<int, list<int>> $childrenByParent
     * @param array<int, list<string>> $textByObject
     */
    private function renderTableHtml(
        int $tableObject,
        array $tableSummariesByObject,
        array $elementsByObject,
        array $childrenByParent,
        array $textByObject
    ): ?string {
        $summary = $tableSummariesByObject[$tableObject] ?? null;
        if (!is_array($summary) || ($summary['unambiguous'] ?? false) !== true) {
            return null;
        }

        $rows = $summary['rows'] ?? [];
        if (!is_array($rows) || $rows === []) {
            return null;
        }

        $attrs = ' data-markerpdf-source="tagged-pdf" data-markerpdf-struct-object="' . $this->esc((string) $tableObject) . '"';
        if (isset($summary['parent_cell_object'])) {
            $attrs .= ' data-markerpdf-nested-table="true"';
        }

        $htmlRows = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $cellObjects = $this->integerList($row['cell_objects'] ?? []);
            if ($cellObjects === []) {
                continue;
            }

            $cellsHtml = '';
            $allHeader = true;
            foreach ($cellObjects as $cellObject) {
                $role = $elementsByObject[$cellObject]['role'] ?? null;
                $tag = $role === 'TH' ? 'th' : 'td';
                if ($tag !== 'th') {
                    $allHeader = false;
                }
                $cellsHtml .= '<' . $tag . ' data-markerpdf-struct-object="' . $this->esc((string) $cellObject) . '">'
                    . $this->renderCellContent($cellObject, $tableSummariesByObject, $elementsByObject, $childrenByParent, $textByObject)
                    . '</' . $tag . '>';
            }

            $htmlRows[] = [
                'header' => $allHeader,
                'html' => '<tr>' . $cellsHtml . '</tr>',
            ];
        }

        if ($htmlRows === []) {
            return null;
        }

        $head = '';
        $body = '';
        foreach ($htmlRows as $index => $row) {
            if ($index === 0 && $row['header']) {
                $head .= $row['html'];
                continue;
            }

            $body .= $row['html'];
        }

        $html = '<table' . $attrs . '>';
        if ($head !== '') {
            $html .= '<thead>' . $head . '</thead>';
        }
        $html .= '<tbody>' . $body . '</tbody></table>';

        return $html;
    }

    /**
     * @param array<int, array<string, mixed>> $tableSummariesByObject
     * @param array<int, array<string, mixed>> $elementsByObject
     * @param array<int, list<int>> $childrenByParent
     * @param array<int, list<string>> $textByObject
     */
    private function renderCellContent(
        int $cellObject,
        array $tableSummariesByObject,
        array $elementsByObject,
        array $childrenByParent,
        array $textByObject
    ): string {
        $text = trim(implode(' ', $textByObject[$cellObject] ?? []));
        $nestedHtml = '';
        foreach ($childrenByParent[$cellObject] ?? [] as $childObject) {
            if (($elementsByObject[$childObject]['role'] ?? null) !== 'Table') {
                continue;
            }

            $tableHtml = $this->renderTableHtml(
                $childObject,
                $tableSummariesByObject,
                $elementsByObject,
                $childrenByParent,
                $textByObject
            );
            if ($tableHtml !== null) {
                $nestedHtml .= $tableHtml;
            }
        }

        if ($nestedHtml === '') {
            return $this->esc($text);
        }

        return ($text === '' ? '' : '<p>' . $this->esc($text) . '</p>') . $nestedHtml;
    }

    /**
     * @param array<int, array<string, mixed>> $tableSummariesByObject
     * @param array<int, list<string>> $textByObject
     * @return list<string>
     */
    private function tableReplaceTexts(int $tableObject, array $tableSummariesByObject, array $textByObject): array
    {
        $summary = $tableSummariesByObject[$tableObject] ?? [];
        $cellObjects = $this->integerList($summary['cell_objects'] ?? []);
        foreach ($this->integerList($summary['nested_table_objects'] ?? []) as $nestedTableObject) {
            $nestedSummary = $tableSummariesByObject[$nestedTableObject] ?? [];
            foreach ($this->integerList($nestedSummary['cell_objects'] ?? []) as $cellObject) {
                $cellObjects[] = $cellObject;
            }
        }

        $texts = [];
        foreach ($cellObjects as $cellObject) {
            foreach ($textByObject[$cellObject] ?? [] as $text) {
                $text = trim($text);
                if ($text !== '' && !in_array($text, $texts, true)) {
                    $texts[] = $text;
                }
            }
        }

        return $texts;
    }

    /**
     * @return list<int>
     */
    private function integerList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $integers = [];
        foreach ($value as $item) {
            if (is_int($item)) {
                $integers[] = $item;
            }
        }

        return $integers;
    }

    private function esc(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
