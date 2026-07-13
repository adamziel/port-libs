<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

/**
 * Source-level HTML boundary scanner for Markdown's raw HTML blocks.
 *
 * This is deliberately not an HTML tree builder. It only finds a matching
 * element boundary while honoring comment, declaration, and raw-text
 * contexts, so source delimiters such as </div> inside a script string do
 * not become structural tags.
 */
final class HtmlSourceScanner
{
    /** @var list<string> */
    private const RAW_TEXT_ELEMENT_NAMES = [
        'iframe',
        'noembed',
        'noframes',
        'noscript',
        'plaintext',
        'script',
        'style',
        'textarea',
        'title',
        'xmp',
    ];

    /**
     * @return array{openStart:int,openEnd:int,closeStart:int,closeEnd:int}|null
     */
    public static function matchingElementBounds(string $source, string $name, int $offset = 0): ?array
    {
        $name = strtolower($name);
        $length = strlen($source);
        $cursor = max(0, $offset);
        $depth = 0;
        $openStart = null;
        $openEnd = null;
        $rawTextElement = null;
        $templateDepth = 0;

        while (($tagOffset = strpos($source, '<', $cursor)) !== false) {
            if ($rawTextElement !== null) {
                $closing = Html5Dom::rawHtmlClosingTagAt($source, $tagOffset);
                if ($closing === null || $closing['name'] !== $rawTextElement) {
                    $cursor = $tagOffset + 1;
                    continue;
                }

                $rawTextElement = null;
                if ($closing['name'] === $name && $depth > 0) {
                    $depth--;
                    if ($depth === 0 && $openStart !== null && $openEnd !== null) {
                        return [
                            'openStart' => $openStart,
                            'openEnd' => $openEnd,
                            'closeStart' => $tagOffset,
                            'closeEnd' => $closing['next'] - 1,
                        ];
                    }
                }

                $cursor = $closing['next'];
                continue;
            }

            if (substr_compare($source, '<!--', $tagOffset, 4) === 0) {
                $commentEnd = strpos($source, '-->', $tagOffset + 4);
                $cursor = $commentEnd === false ? $length : $commentEnd + 3;
                continue;
            }

            if (substr_compare($source, '<![CDATA[', $tagOffset, 9) === 0) {
                $cdataEnd = strpos($source, ']]>', $tagOffset + 9);
                $cursor = $cdataEnd === false ? $length : $cdataEnd + 3;
                continue;
            }

            if (($source[$tagOffset + 1] ?? '') === '!') {
                $cursor = self::declarationEnd($source, $tagOffset + 2);
                continue;
            }

            if (($source[$tagOffset + 1] ?? '') === '?') {
                $processingInstructionEnd = strpos($source, '?>', $tagOffset + 2);
                $cursor = $processingInstructionEnd === false ? $length : $processingInstructionEnd + 2;
                continue;
            }

            $closing = Html5Dom::rawHtmlClosingTagAt($source, $tagOffset);
            if ($closing !== null) {
                $insideTemplate = $templateDepth > 0;
                if ($closing['name'] === 'template' && $templateDepth > 0) {
                    if ($name === 'template' && $depth > 0) {
                        $depth--;
                        if ($depth === 0 && $openStart !== null && $openEnd !== null) {
                            return [
                                'openStart' => $openStart,
                                'openEnd' => $openEnd,
                                'closeStart' => $tagOffset,
                                'closeEnd' => $closing['next'] - 1,
                            ];
                        }
                    }
                    $templateDepth--;
                    $cursor = $closing['next'];
                    continue;
                }

                if (!$insideTemplate && $closing['name'] === $name && $depth > 0) {
                    $depth--;
                    if ($depth === 0 && $openStart !== null && $openEnd !== null) {
                        return [
                            'openStart' => $openStart,
                            'openEnd' => $openEnd,
                            'closeStart' => $tagOffset,
                            'closeEnd' => $closing['next'] - 1,
                        ];
                    }
                }

                $cursor = $closing['next'];
                continue;
            }

            $opening = Html5Dom::rawHtmlOpeningTagAt($source, $tagOffset);
            if ($opening === null) {
                $cursor = $tagOffset + 1;
                continue;
            }

            $insideTemplate = $templateDepth > 0;
            if (($name === 'template' || !$insideTemplate) && $opening['name'] === $name && !$opening['selfClosing']) {
                if ($depth === 0) {
                    $openStart = $tagOffset;
                    $openEnd = $opening['next'] - 1;
                }
                $depth++;
            }
            if ($opening['name'] === 'template' && !$opening['selfClosing']) {
                $templateDepth++;
            }
            if (!$opening['selfClosing'] && in_array($opening['name'], self::RAW_TEXT_ELEMENT_NAMES, true)) {
                if ($opening['name'] === 'plaintext') {
                    return null;
                }
                $rawTextElement = $opening['name'];
            }

            $cursor = $opening['next'];
        }

        return null;
    }

    /**
     * Return true when a closing-looking tag occurs inside script/style-like
     * source text. Passing that source through the legacy DOM bridge would
     * reinterpret the bytes as document structure, so callers can preserve
     * the original raw block instead.
     */
    public static function rawTextContainsClosingTag(string $source, string $name): bool
    {
        $name = strtolower($name);
        $length = strlen($source);
        $cursor = 0;
        $rawTextElement = null;

        while (($tagOffset = strpos($source, '<', $cursor)) !== false) {
            if ($rawTextElement !== null) {
                $closing = Html5Dom::rawHtmlClosingTagAt($source, $tagOffset);
                if ($closing !== null && $closing['name'] === $name && $closing['name'] !== $rawTextElement) {
                    return true;
                }
                if ($closing !== null && $closing['name'] === $rawTextElement) {
                    $rawTextElement = null;
                    $cursor = $closing['next'];
                    continue;
                }
                $cursor = $tagOffset + 1;
                continue;
            }

            if (substr_compare($source, '<!--', $tagOffset, 4) === 0) {
                $commentEnd = strpos($source, '-->', $tagOffset + 4);
                $cursor = $commentEnd === false ? $length : $commentEnd + 3;
                continue;
            }
            if (substr_compare($source, '<![CDATA[', $tagOffset, 9) === 0) {
                $cdataEnd = strpos($source, ']]>', $tagOffset + 9);
                $cursor = $cdataEnd === false ? $length : $cdataEnd + 3;
                continue;
            }
            if (($source[$tagOffset + 1] ?? '') === '!') {
                $cursor = self::declarationEnd($source, $tagOffset + 2);
                continue;
            }
            if (($source[$tagOffset + 1] ?? '') === '?') {
                $processingInstructionEnd = strpos($source, '?>', $tagOffset + 2);
                $cursor = $processingInstructionEnd === false ? $length : $processingInstructionEnd + 2;
                continue;
            }

            $opening = Html5Dom::rawHtmlOpeningTagAt($source, $tagOffset);
            if ($opening !== null) {
                if (!$opening['selfClosing'] && in_array($opening['name'], self::RAW_TEXT_ELEMENT_NAMES, true)) {
                    if ($opening['name'] === 'plaintext') {
                        return false;
                    }
                    $rawTextElement = $opening['name'];
                }
                $cursor = $opening['next'];
                continue;
            }

            $cursor = $tagOffset + 1;
        }

        return false;
    }

    private static function declarationEnd(string $source, int $offset): int
    {
        $length = strlen($source);
        $cursor = $offset;
        $quote = null;
        $subsetDepth = 0;

        while ($cursor < $length) {
            $char = $source[$cursor];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                $cursor++;
                continue;
            }
            if ($char === '"' || $char === "'") {
                $quote = $char;
                $cursor++;
                continue;
            }
            if ($char === '[') {
                $subsetDepth++;
                $cursor++;
                continue;
            }
            if ($char === ']' && $subsetDepth > 0) {
                $subsetDepth--;
                $cursor++;
                continue;
            }
            if ($char === '>' && $subsetDepth === 0) {
                return $cursor + 1;
            }
            $cursor++;
        }

        return $length;
    }
}
