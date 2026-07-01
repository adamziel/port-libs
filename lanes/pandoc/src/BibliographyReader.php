<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class BibliographyReader
{
    /**
     * @param array<string, mixed> $options
     */
    public function __construct(
        private readonly string $format,
        private readonly array $options = [],
    ) {
    }

    public function read(string $bytes): AstNode
    {
        $items = $this->items($bytes);
        $ids = $this->itemIds($items);
        $processor = CitationCslProcessor::fromItems($items);
        $bibliography = $processor->bibliographyDefinitionList($ids);
        $attrs = [
            'sourceFormat' => $this->format,
            'bibliography' => [
                'format' => $this->format,
                'reader' => self::class,
                'parser' => $this->parserName(),
                'itemCount' => count($items),
                'itemIds' => $ids,
                'sourceBytes' => strlen($bytes),
                'sourceSha256' => hash('sha256', $bytes),
                'payloadExposurePolicy' => 'source-bytes-omitted',
            ],
            'cslItemCount' => count($items),
            'cslItemIds' => $ids,
            'cslItems' => $items,
        ];

        return new AstNode('document', $attrs, $bibliography->children === [] ? [] : [$bibliography]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(string $bytes): array
    {
        return match ($this->format) {
            'bibtex', 'biblatex' => CitationCslProcessor::bibtexItems($bytes),
            'csljson' => $this->cslJsonItems($bytes),
            'endnotexml' => CitationCslProcessor::endnoteXmlItems($bytes),
            'ris' => CitationCslProcessor::risItems($bytes),
            default => throw new \InvalidArgumentException("Unsupported bibliography input format '{$this->format}'."),
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function cslJsonItems(string $json): array
    {
        return CitationCslProcessor::cslJsonItems($json);
    }

    /**
     * @param list<array<string, mixed>> $items
     * @return list<string>
     */
    private function itemIds(array $items): array
    {
        $ids = [];
        foreach ($items as $index => $item) {
            $id = $item['id'] ?? null;
            if (!is_string($id) && !is_int($id)) {
                throw new \InvalidArgumentException('CSL item at index ' . $index . ' is missing string id');
            }

            $id = trim((string) $id);
            if ($id === '') {
                throw new \InvalidArgumentException('CSL item at index ' . $index . ' has an empty id');
            }

            $ids[] = $id;
        }

        return $ids;
    }

    private function parserName(): string
    {
        return match ($this->format) {
            'bibtex', 'biblatex' => BibtexCslParser::class,
            'csljson' => 'CSL JSON',
            'endnotexml' => 'EndNote XML',
            'ris' => 'RIS',
            default => 'unknown',
        };
    }
}
