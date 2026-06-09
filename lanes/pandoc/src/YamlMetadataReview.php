<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class YamlMetadataReview
{
    /**
     * @var array<string, string>
     */
    private const PROVENANCE_ATTRIBUTES = [
        'yamlMetadataTagProvenance' => 'tag',
        'yamlMetadataDirectiveProvenance' => 'directive',
        'yamlMetadataCommentProvenance' => 'comment',
        'yamlMetadataAnchorProvenance' => 'anchor',
        'yamlMetadataAliasProvenance' => 'alias',
        'yamlMetadataMergeProvenance' => 'merge',
        'yamlMetadataScalarProvenance' => 'scalar',
        'yamlMetadataCollectionProvenance' => 'collection',
        'yamlMetadataStreamProvenance' => 'stream',
    ];

    /**
     * @return array{
     *     meta: array<string, mixed>,
     *     summary: array<string, mixed>,
     *     provenanceByPath: array<string, list<array<string, mixed>>>,
     *     diagnosticsByPath: array<string, list<array<string, mixed>>>
     * }
     */
    public static function fromMarkdown(string $markdown, ?MarkdownReader $reader = null): array
    {
        $document = ($reader ?? new MarkdownReader())->read($markdown);

        return [
            'meta' => self::stringMap($document->attr('meta', [])),
            'summary' => self::stringMap($document->attr('yamlMetadataReviewSummary', [])),
            'provenanceByPath' => self::provenanceByPath($document),
            'diagnosticsByPath' => self::diagnosticsByPath($document),
        ];
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function provenanceByPath(AstNode $document): array
    {
        $byPath = [];
        foreach (self::PROVENANCE_ATTRIBUTES as $attribute => $category) {
            foreach (self::listOfMaps($document->attr($attribute, [])) as $entry) {
                $path = self::pathFromEntry($entry);
                $entry['category'] = $category;
                $entry['attribute'] = $attribute;
                $byPath[$path][] = $entry;
            }
        }

        return $byPath;
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public static function diagnosticsByPath(AstNode $document): array
    {
        $byPath = [];
        foreach (self::listOfMaps($document->attr('yamlMetadataDiagnostics', [])) as $entry) {
            $byPath[self::pathFromEntry($entry)][] = $entry;
        }

        return $byPath;
    }

    /**
     * @return array<string, mixed>
     */
    private static function stringMap(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return $value;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function listOfMaps(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $maps = [];
        foreach ($value as $entry) {
            if (is_array($entry)) {
                $maps[] = $entry;
            }
        }

        return $maps;
    }

    /**
     * @param array<string, mixed> $entry
     */
    private static function pathFromEntry(array $entry): string
    {
        $path = $entry['path'] ?? '';

        return is_string($path) ? $path : '';
    }
}
