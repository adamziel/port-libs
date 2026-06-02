<?php

declare(strict_types=1);

namespace PortLibs\MarkerPDF;

final class PdfAcroFormExtractor
{
    private const COMMON_FIELD_FLAGS = [
        1 => 'read_only',
        2 => 'required',
        3 => 'no_export',
    ];

    private const FIELD_TYPE_FLAGS = [
        'Tx' => [
            13 => 'multiline',
            14 => 'password',
            21 => 'file_select',
            23 => 'do_not_spell_check',
            24 => 'do_not_scroll',
            25 => 'comb',
            26 => 'rich_text',
        ],
        'Btn' => [
            15 => 'no_toggle_to_off',
            16 => 'radio',
            17 => 'push_button',
            26 => 'radios_in_unison',
        ],
        'Ch' => [
            18 => 'combo',
            19 => 'edit',
            20 => 'sort',
            22 => 'multi_select',
            23 => 'do_not_spell_check',
            27 => 'commit_on_sel_change',
        ],
    ];

    /**
     * Native boundary for PDF AcroForm field dictionaries.
     *
     * @return array{need_appearances: bool, permissions: array<string, mixed>, fields: list<array<string, mixed>>}
     */
    public function extractForm(string $pdfBytes): array
    {
        $objects = $this->pdfObjects($pdfBytes);
        $catalog = $this->catalogObjectBody($objects);
        $permissions = $catalog === null ? $this->emptyPermissions() : $this->documentPermissions($catalog, $objects);
        $acroForm = $catalog === null ? null : $this->acroFormDictionaryBody($catalog, $objects);
        if ($acroForm === null) {
            return [
                'need_appearances' => false,
                'permissions' => $permissions,
                'fields' => [],
            ];
        }

        $pageObjectNumbers = $this->orderedPageObjectNumbers($objects);
        $pageIndexes = array_flip($pageObjectNumbers);
        $pageWidgets = $this->pageWidgetMap($objects, $pageObjectNumbers);
        $formDefaults = $this->acroFormDefaults($acroForm);
        $fields = [];
        $fieldRefs = $this->fieldReferencesFromAcroForm($acroForm);

        foreach ($fieldRefs as $fieldRef) {
            foreach ($this->fieldsFromObject(
                $fieldRef,
                $objects,
                $formDefaults,
                [],
                [],
                $pageIndexes,
                $pageWidgets
            ) as $field) {
                $fields[] = $field;
            }
        }

        $fields = $this->markCertifyingSignatureFields($fields, $permissions);

        return [
            'need_appearances' => $this->boolValueAfterName($acroForm, 'NeedAppearances') === true,
            'permissions' => $permissions,
            'fields' => $fields,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function extractFields(string $pdfBytes): array
    {
        return $this->extractForm($pdfBytes)['fields'];
    }

    /**
     * @return list<array<string, mixed>>
     * @param array<int, string> $objects
     * @param array<string, array{value: string, source: string, source_object: int|null}> $inherited
     * @param list<string> $nameParts
     * @param array<int, true> $seen
     * @param array<int, int> $pageIndexes
     * @param array<int, array{page_index: int, page_object: int}> $pageWidgets
     */
    private function fieldsFromObject(
        int $objectNumber,
        array $objects,
        array $inherited,
        array $nameParts,
        array $seen,
        array $pageIndexes,
        array $pageWidgets
    ): array {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $this->dictionaryObjectBody($objects[$objectNumber]) ?? trim($objects[$objectNumber]);
        $effective = $this->mergeFieldAttributes($body, $inherited, $objectNumber);
        $partialName = $this->pdfStringValueAfterName($body, 'T', $objects);
        $mappingName = $this->pdfStringValueAfterName($body, 'TM', $objects);
        $currentNameParts = $nameParts;
        if ($partialName !== null && $partialName !== '') {
            $currentNameParts[] = $partialName;
        }

        $kidRefs = $this->kidReferences($body);
        $childFieldRefs = [];
        $widgetRefs = [];
        foreach ($kidRefs as $kidRef) {
            if (!isset($objects[$kidRef])) {
                continue;
            }

            $kidBody = $this->dictionaryObjectBody($objects[$kidRef]) ?? trim($objects[$kidRef]);
            if ($this->isPureWidget($kidBody)) {
                $widgetRefs[] = $kidRef;
                continue;
            }

            $childFieldRefs[] = $kidRef;
        }

        if ($childFieldRefs !== []) {
            $fields = [];
            foreach ($childFieldRefs as $childRef) {
                foreach ($this->fieldsFromObject(
                    $childRef,
                    $objects,
                    $effective,
                    $currentNameParts,
                    $seen,
                    $pageIndexes,
                    $pageWidgets
                ) as $field) {
                    $fields[] = $field;
                }
            }

            return $fields;
        }

        if ($this->isWidget($body)) {
            array_unshift($widgetRefs, $objectNumber);
        }

        $fieldType = $this->fieldType($effective);
        if ($fieldType === null && $partialName === null && $mappingName === null) {
            return [];
        }

        $flags = $this->integerFromEffective($effective, 'Ff', 0);
        $defaultAppearance = $this->defaultAppearanceFromEffective($effective);
        $password = $fieldType === 'Tx' && $this->hasFlagBit($flags, 14);

        $name = $currentNameParts === [] ? '#' . $objectNumber : implode('.', $currentNameParts);
        $field = [
            'object' => $objectNumber,
            'name' => $name,
            'partial_name' => $partialName,
            'mapping_name' => $mappingName ?? $name,
            'field_type' => $fieldType,
            'field_type_label' => $this->fieldTypeLabel($fieldType),
            'flags' => $flags,
            'flag_names' => $this->flagNames($flags, $fieldType),
            'value' => $password ? null : $this->valueFromEffective($effective, 'V', $objects),
            'value_redacted' => $password,
            'default_value' => $password ? null : $this->valueFromEffective($effective, 'DV', $objects),
            'default_appearance' => $defaultAppearance,
            'widgets' => $this->widgetsForField($widgetRefs, $objects, $defaultAppearance, $pageIndexes, $pageWidgets),
        ];

        if ($fieldType === 'Ch') {
            $field['options'] = $this->optionsFromEffective($effective, $objects);
        }
        if ($fieldType === 'Sig') {
            $field['signature'] = isset($effective['V'])
                ? $this->signatureMetadataFromValue($effective['V']['value'], $objects)
                : null;
            $field['certifying_signature'] = false;
        }

        return [$field];
    }

    /**
     * @return array{doc_mdp: array<string, mixed>|null}
     */
    private function emptyPermissions(): array
    {
        return ['doc_mdp' => null];
    }

    /**
     * @param array<int, string> $objects
     * @return array{doc_mdp: array<string, mixed>|null}
     */
    private function documentPermissions(string $catalog, array $objects): array
    {
        $permsValue = $this->valueAfterName($catalog, 'Perms');
        $perms = $permsValue === null ? null : $this->resolvedDictionaryFromValue($permsValue, $objects);
        if ($perms === null) {
            return $this->emptyPermissions();
        }

        $docMdpValue = $this->valueAfterName($perms['body'], 'DocMDP');
        $signature = $docMdpValue === null ? null : $this->signatureMetadataFromValue($docMdpValue, $objects);
        if ($signature === null) {
            return $this->emptyPermissions();
        }

        $docMdpTransform = $this->docMdpTransformFromSignatureMetadata($signature);

        return [
            'doc_mdp' => [
                'signature_object' => $signature['object'],
                'signature_name' => $signature['name'],
                'signed_at' => $signature['signed_at'],
                'permission_level' => $docMdpTransform['permission_level'] ?? null,
                'permission_label' => $docMdpTransform['permission_label'] ?? 'unknown',
                'allowed_changes' => $docMdpTransform['allowed_changes'] ?? [],
                'transform_params_version' => $docMdpTransform['transform_params_version'] ?? null,
                'source' => 'catalog_perms_doc_mdp',
            ],
        ];
    }

    /**
     * @param list<array<string, mixed>> $fields
     * @param array<string, mixed> $permissions
     * @return list<array<string, mixed>>
     */
    private function markCertifyingSignatureFields(array $fields, array $permissions): array
    {
        $docMdpObject = is_array($permissions['doc_mdp'] ?? null)
            ? ($permissions['doc_mdp']['signature_object'] ?? null)
            : null;
        if (!is_int($docMdpObject)) {
            return $fields;
        }

        foreach ($fields as $index => $field) {
            $signature = $field['signature'] ?? null;
            if (!is_array($signature) || ($signature['object'] ?? null) !== $docMdpObject) {
                continue;
            }

            $fields[$index]['certifying_signature'] = true;
            $fields[$index]['signature']['certifying_signature'] = true;
        }

        return $fields;
    }

    /**
     * @param array<string, mixed> $signature
     * @return array<string, mixed>|null
     */
    private function docMdpTransformFromSignatureMetadata(array $signature): ?array
    {
        foreach ($signature['reference_transforms'] ?? [] as $transform) {
            if (is_array($transform) && ($transform['transform_method'] ?? null) === 'DocMDP') {
                return $transform;
            }
        }

        return null;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function signatureMetadataFromValue(string $value, array $objects): ?array
    {
        $signature = $this->resolvedDictionaryFromValue($value, $objects);
        if ($signature === null) {
            return null;
        }

        $body = $signature['body'];
        $contentsValue = $this->valueAfterName($body, 'Contents');
        $metadata = [
            'object' => $signature['object'],
            'filter' => $this->pdfNameValueAfterName($body, 'Filter'),
            'subfilter' => $this->pdfNameValueAfterName($body, 'SubFilter'),
            'name' => $this->pdfStringValueAfterName($body, 'Name', $objects),
            'reason' => $this->pdfStringValueAfterName($body, 'Reason', $objects),
            'location' => $this->pdfStringValueAfterName($body, 'Location', $objects),
            'contact_info' => $this->pdfStringValueAfterName($body, 'ContactInfo', $objects),
            'signed_at' => $this->pdfStringValueAfterName($body, 'M', $objects),
            'byte_range' => $this->integerArrayValueAfterName($body, 'ByteRange'),
            'contents_present' => $contentsValue !== null,
            'contents_length_bytes' => $contentsValue === null ? null : $this->signatureContentsLength($contentsValue, $objects),
            'reference_transforms' => $this->signatureReferenceTransforms($body, $objects),
            'certifying_signature' => false,
        ];

        $docMdp = $this->docMdpTransformFromSignatureMetadata($metadata);
        if ($docMdp !== null) {
            $metadata['doc_mdp'] = $docMdp;
        }

        return $metadata;
    }

    /**
     * @param array<int, string> $objects
     * @return list<array<string, mixed>>
     */
    private function signatureReferenceTransforms(string $signatureBody, array $objects): array
    {
        $reference = $this->valueAfterName($signatureBody, 'Reference');
        if ($reference === null || !str_starts_with(trim($reference), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($reference);
        if ($body === null) {
            return [];
        }

        $transforms = [];
        foreach ($this->dictionaryValuesFromArrayBody($body, $objects) as $dictionary) {
            $transformBody = $dictionary['body'];
            $method = $this->pdfNameValueAfterName($transformBody, 'TransformMethod');
            if ($method === null) {
                continue;
            }

            $transform = [
                'transform_method' => $method,
                'data_object' => $this->objectReferenceValueAfterName($transformBody, 'Data'),
                'digest_method' => $this->pdfNameValueAfterName($transformBody, 'DigestMethod'),
            ];

            $params = $this->docMdpTransformParams($transformBody, $objects, $method);
            if ($params !== null) {
                $transform += $params;
            }

            $transforms[] = $transform;
        }

        return $transforms;
    }

    /**
     * @param array<int, string> $objects
     * @return array<string, mixed>|null
     */
    private function docMdpTransformParams(string $referenceBody, array $objects, string $method): ?array
    {
        $paramsValue = $this->valueAfterName($referenceBody, 'TransformParams');
        $params = $paramsValue === null ? null : $this->resolvedDictionaryFromValue($paramsValue, $objects);
        if ($method !== 'DocMDP' && $params === null) {
            return null;
        }

        $paramsBody = $params['body'] ?? '';
        $level = $paramsBody === '' ? null : $this->numberValueAfterName($paramsBody, 'P');
        if ($method === 'DocMDP' && $level === null) {
            $level = 2;
        }

        return [
            'transform_params_object' => $params['object'] ?? null,
            'transform_params_type' => $paramsBody === '' ? null : $this->pdfNameValueAfterName($paramsBody, 'Type'),
            'transform_params_version' => $paramsBody === '' ? null : $this->pdfNameValueAfterName($paramsBody, 'V'),
            'permission_level' => $level,
            'permission_valid' => in_array($level, [1, 2, 3], true),
            'permission_label' => $this->docMdpPermissionLabel($level),
            'allowed_changes' => $this->docMdpAllowedChanges($level),
        ];
    }

    private function docMdpPermissionLabel(?int $level): string
    {
        return match ($level) {
            1 => 'no_changes',
            2 => 'form_fill_templates_signatures',
            3 => 'form_fill_templates_signatures_annotations',
            default => 'unknown',
        };
    }

    /**
     * @return list<string>
     */
    private function docMdpAllowedChanges(?int $level): array
    {
        return match ($level) {
            1 => [],
            2 => ['fill_forms', 'instantiate_page_templates', 'sign'],
            3 => ['fill_forms', 'instantiate_page_templates', 'sign', 'create_modify_delete_annotations'],
            default => [],
        };
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $inherited
     * @return array<string, array{value: string, source: string, source_object: int|null}>
     */
    private function mergeFieldAttributes(string $body, array $inherited, int $objectNumber): array
    {
        $effective = $inherited;
        foreach (['FT', 'Ff', 'V', 'DV', 'DA', 'Q', 'Opt'] as $name) {
            $value = $this->valueAfterName($body, $name);
            if ($value === null) {
                continue;
            }

            $effective[$name] = [
                'value' => $value,
                'source' => 'field',
                'source_object' => $objectNumber,
            ];
        }

        return $effective;
    }

    /**
     * @return array<string, array{value: string, source: string, source_object: int|null}>
     */
    private function acroFormDefaults(string $acroForm): array
    {
        $defaults = [];
        foreach (['DA', 'Q'] as $name) {
            $value = $this->valueAfterName($acroForm, $name);
            if ($value !== null) {
                $defaults[$name] = [
                    'value' => $value,
                    'source' => 'acroform',
                    'source_object' => null,
                ];
            }
        }

        return $defaults;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function fieldType(array $effective): ?string
    {
        if (!isset($effective['FT'])) {
            return null;
        }

        $value = trim($effective['FT']['value']);
        if (!str_starts_with($value, '/')) {
            return null;
        }

        return $this->decodePdfName($value);
    }

    private function fieldTypeLabel(?string $fieldType): string
    {
        return match ($fieldType) {
            'Tx' => 'text',
            'Ch' => 'choice',
            'Btn' => 'button',
            'Sig' => 'signature',
            default => 'unknown',
        };
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function integerFromEffective(array $effective, string $name, int $default): int
    {
        if (!isset($effective[$name])) {
            return $default;
        }

        $value = trim($effective[$name]['value']);
        return preg_match('/^[+-]?\d+/', $value, $match) === 1 ? (int) $match[0] : $default;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function valueFromEffective(array $effective, string $name, array $objects): mixed
    {
        if (!isset($effective[$name])) {
            return null;
        }

        return $this->pdfValueToPhpValue($effective[$name]['value'], $objects);
    }

    /**
     * @return list<string>
     */
    private function flagNames(int $flags, ?string $fieldType): array
    {
        $names = [];
        foreach (self::COMMON_FIELD_FLAGS as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        foreach (self::FIELD_TYPE_FLAGS[$fieldType ?? ''] ?? [] as $bit => $name) {
            if ($this->hasFlagBit($flags, $bit)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function hasFlagBit(int $flags, int $oneBasedBit): bool
    {
        return ($flags & (1 << ($oneBasedBit - 1))) !== 0;
    }

    /**
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     * @return array<string, mixed>|null
     */
    private function defaultAppearanceFromEffective(array $effective): ?array
    {
        if (!isset($effective['DA'])) {
            return null;
        }

        $raw = $this->pdfValueToString($effective['DA']['value'], []);
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $appearance = $this->parseDefaultAppearance($raw);
        $appearance['raw'] = $raw;
        $appearance['source'] = $effective['DA']['source'];
        $appearance['source_object'] = $effective['DA']['source_object'];

        return $appearance;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDefaultAppearance(string $appearance): array
    {
        $tokens = $this->appearanceTokens($appearance);
        $parsed = [
            'font_resource' => null,
            'font_size' => null,
            'text_color' => null,
        ];

        foreach ($tokens as $index => $token) {
            if ($token === 'Tf' && isset($tokens[$index - 2], $tokens[$index - 1])) {
                $font = $tokens[$index - 2];
                $size = $tokens[$index - 1];
                if (is_string($font) && str_starts_with($font, '/') && is_numeric($size)) {
                    $parsed['font_resource'] = $this->decodePdfName($font);
                    $parsed['font_size'] = (float) $size;
                }
                continue;
            }

            if ($token === 'g' && isset($tokens[$index - 1]) && is_numeric($tokens[$index - 1])) {
                $parsed['text_color'] = [
                    'space' => 'DeviceGray',
                    'components' => [(float) $tokens[$index - 1]],
                ];
                continue;
            }

            if ($token === 'rg' && isset($tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1])) {
                $components = [$tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1]];
                if ($this->allNumeric($components)) {
                    $parsed['text_color'] = [
                        'space' => 'DeviceRGB',
                        'components' => array_map('floatval', $components),
                    ];
                }
                continue;
            }

            if ($token === 'k' && isset($tokens[$index - 4], $tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1])) {
                $components = [$tokens[$index - 4], $tokens[$index - 3], $tokens[$index - 2], $tokens[$index - 1]];
                if ($this->allNumeric($components)) {
                    $parsed['text_color'] = [
                        'space' => 'DeviceCMYK',
                        'components' => array_map('floatval', $components),
                    ];
                }
            }
        }

        return $parsed;
    }

    /**
     * @return list<string>
     */
    private function appearanceTokens(string $appearance): array
    {
        if (preg_match_all('/\/(?:#[0-9A-Fa-f]{2}|[^\s\[\]\(\)<>{}\/%])+|[+-]?(?:\d+(?:\.\d*)?|\.\d+)|[A-Za-z][A-Za-z0-9*]*/', $appearance, $matches) === false) {
            return [];
        }

        return $matches[0];
    }

    /**
     * @param list<mixed> $values
     */
    private function allNumeric(array $values): bool
    {
        foreach ($values as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<int> $widgetRefs
     * @param array<int, string> $objects
     * @param array<string, mixed>|null $fieldDefaultAppearance
     * @param array<int, int> $pageIndexes
     * @param array<int, array{page_index: int, page_object: int}> $pageWidgets
     * @return list<array<string, mixed>>
     */
    private function widgetsForField(
        array $widgetRefs,
        array $objects,
        ?array $fieldDefaultAppearance,
        array $pageIndexes,
        array $pageWidgets
    ): array {
        $widgets = [];
        $seen = [];
        foreach ($widgetRefs as $widgetRef) {
            if (isset($seen[$widgetRef]) || !isset($objects[$widgetRef])) {
                continue;
            }
            $seen[$widgetRef] = true;

            $body = $this->dictionaryObjectBody($objects[$widgetRef]) ?? trim($objects[$widgetRef]);
            $pageObject = $this->objectReferenceValueAfterName($body, 'P') ?? ($pageWidgets[$widgetRef]['page_object'] ?? null);
            $pageIndex = $pageObject !== null && isset($pageIndexes[$pageObject])
                ? $pageIndexes[$pageObject]
                : ($pageWidgets[$widgetRef]['page_index'] ?? null);
            $annotationFlags = $this->numberValueAfterName($body, 'F');
            $widgetAppearance = $this->widgetDefaultAppearance($body, $fieldDefaultAppearance);

            $widgets[] = [
                'object' => $widgetRef,
                'page_index' => $pageIndex,
                'page_object' => $pageObject,
                'rect' => $this->rectFromAnnotation($body),
                'annotation_flags' => $annotationFlags,
                'hidden' => $annotationFlags !== null && (($annotationFlags & 3) !== 0 || ($annotationFlags & 32) !== 0),
                'appearance_state' => $this->pdfNameValueAfterName($body, 'AS'),
                'appearance_states' => $this->normalAppearanceStates($body),
                'default_appearance' => $widgetAppearance,
            ];
        }

        return $widgets;
    }

    /**
     * @param array<string, mixed>|null $fieldDefaultAppearance
     * @return array<string, mixed>|null
     */
    private function widgetDefaultAppearance(string $widgetBody, ?array $fieldDefaultAppearance): ?array
    {
        $raw = $this->pdfStringValueAfterName($widgetBody, 'DA', []);
        if ($raw === null || $raw === '') {
            return $fieldDefaultAppearance;
        }

        $appearance = $this->parseDefaultAppearance($raw);
        $appearance['raw'] = $raw;
        $appearance['source'] = 'widget';
        $appearance['source_object'] = null;

        return $appearance;
    }

    /**
     * @return list<array{export: string, label: string}>
     * @param array<string, array{value: string, source: string, source_object: int|null}> $effective
     */
    private function optionsFromEffective(array $effective, array $objects): array
    {
        if (!isset($effective['Opt'])) {
            return [];
        }

        $value = trim($effective['Opt']['value']);
        if (!str_starts_with($value, '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($value);
        if ($body === null) {
            return [];
        }

        $options = [];
        $offset = 0;
        while ($offset < strlen($body)) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= strlen($body)) {
                break;
            }

            if ($body[$offset] === '[') {
                $endOffset = null;
                $nested = $this->readPdfArrayAt($body, $offset, $endOffset);
                if ($nested === null || $endOffset === null) {
                    break;
                }
                $parts = $this->scalarValuesFromArrayBody($nested, $objects);
                if (count($parts) >= 2) {
                    $options[] = ['export' => $parts[0], 'label' => $parts[1]];
                }
                $offset = $endOffset;
                continue;
            }

            $item = $this->readScalarAt($body, $offset, $objects);
            if ($item !== null) {
                $options[] = ['export' => $item['value'], 'label' => $item['value']];
                $offset = $item['end'];
                continue;
            }

            $offset++;
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    private function scalarValuesFromArrayBody(string $body, array $objects): array
    {
        $values = [];
        $offset = 0;
        while ($offset < strlen($body)) {
            $this->skipWhitespace($body, $offset);
            $item = $this->readScalarAt($body, $offset, $objects);
            if ($item === null) {
                $offset++;
                continue;
            }
            $values[] = $item['value'];
            $offset = $item['end'];
        }

        return $values;
    }

    /**
     * @return array{value: string, end: int}|null
     */
    private function readScalarAt(string $body, int $offset, array $objects): ?array
    {
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if ($body[$offset] === '(') {
            $end = $this->skipLiteralString($body, $offset);
            $value = $this->decodePdfStringBytes($this->decodeLiteralString(substr($body, $offset + 1, $end - $offset - 2)));
            return ['value' => $value, 'end' => $end];
        }

        if ($body[$offset] === '<' && substr($body, $offset, 2) !== '<<') {
            $end = $this->skipHexString($body, $offset);
            $hex = preg_replace('/\s+/', '', substr($body, $offset + 1, $end - $offset - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = $hex === '' ? '' : hex2bin($hex);
            if ($bytes === false) {
                return null;
            }

            return ['value' => $this->decodePdfStringBytes($bytes), 'end' => $end];
        }

        if ($body[$offset] === '/') {
            $end = $this->skipPdfName($body, $offset);
            return ['value' => $this->decodePdfName(substr($body, $offset, $end - $offset)), 'end' => $end];
        }

        if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
            $ref = (int) $match[1];
            if (!isset($objects[$ref])) {
                return null;
            }
            $resolved = $this->pdfValueToString(trim($objects[$ref]), $objects);
            return $resolved === null ? null : ['value' => $resolved, 'end' => $offset + strlen($match[0])];
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function normalAppearanceStates(string $widgetBody): array
    {
        $ap = $this->valueAfterName($widgetBody, 'AP');
        if ($ap === null || !str_starts_with(trim($ap), '<<')) {
            return [];
        }

        $normal = $this->valueAfterName($ap, 'N');
        if ($normal === null || !str_starts_with(trim($normal), '<<')) {
            return [];
        }

        $states = [];
        if (preg_match_all('/\/((?:#[0-9A-Fa-f]{2}|[^\s\[\]\(\)<>{}\/%])+)\b/', $normal, $matches)) {
            foreach ($matches[1] as $name) {
                $decoded = $this->decodePdfName('/' . $name);
                if (!in_array($decoded, $states, true)) {
                    $states[] = $decoded;
                }
            }
        }

        return $states;
    }

    /**
     * @return list<int>
     */
    private function fieldReferencesFromAcroForm(string $acroForm): array
    {
        $fields = $this->valueAfterName($acroForm, 'Fields');
        if ($fields === null || !str_starts_with(trim($fields), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($fields);
        return $body === null ? [] : $this->objectReferences($body);
    }

    /**
     * @return list<int>
     */
    private function kidReferences(string $body): array
    {
        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $body = $this->arrayBodyFromValue($kids);
        return $body === null ? [] : $this->objectReferences($body);
    }

    private function isPureWidget(string $body): bool
    {
        if (!$this->isWidget($body)) {
            return false;
        }

        return $this->valueAfterName($body, 'T') === null
            && $this->valueAfterName($body, 'TM') === null
            && $this->valueAfterName($body, 'FT') === null
            && $this->valueAfterName($body, 'Kids') === null;
    }

    private function isWidget(string $body): bool
    {
        return preg_match('/\/Subtype\s*\/Widget\b/', $body) === 1;
    }

    /**
     * @return array<int, array{page_index: int, page_object: int}>
     * @param array<int, string> $objects
     * @param list<int> $pageObjectNumbers
     */
    private function pageWidgetMap(array $objects, array $pageObjectNumbers): array
    {
        $widgets = [];
        foreach ($pageObjectNumbers as $pageIndex => $pageObjectNumber) {
            if (!isset($objects[$pageObjectNumber])) {
                continue;
            }

            $annots = $this->valueAfterName($objects[$pageObjectNumber], 'Annots');
            if ($annots === null) {
                continue;
            }

            $annotationRefs = $this->annotationObjectReferences($annots, $objects);
            foreach ($annotationRefs as $annotationRef) {
                $annotationBody = $this->dictionaryObjectBody($objects[$annotationRef] ?? '') ?? '';
                if ($annotationBody === '' || !$this->isWidget($annotationBody)) {
                    continue;
                }

                $widgets[$annotationRef] = [
                    'page_index' => $pageIndex,
                    'page_object' => $pageObjectNumber,
                ];
            }
        }

        return $widgets;
    }

    /**
     * @return list<int>
     */
    private function annotationObjectReferences(string $annots, array $objects): array
    {
        $annots = trim($annots);
        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $annots, $match) === 1) {
            $objectNumber = (int) $match[1];
            if (!isset($objects[$objectNumber])) {
                return [];
            }

            $objectBody = trim($objects[$objectNumber]);
            if (str_starts_with($objectBody, '[')) {
                $arrayBody = $this->arrayBodyFromValue($objectBody);
                return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
            }

            return [$objectNumber];
        }

        if (!str_starts_with($annots, '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($annots);
        return $arrayBody === null ? [] : $this->objectReferences($arrayBody);
    }

    private function acroFormDictionaryBody(string $catalog, array $objects): ?string
    {
        $value = $this->valueAfterName($catalog, 'AcroForm');
        if ($value === null) {
            return null;
        }

        $value = trim($value);
        if (str_starts_with($value, '<<')) {
            return $this->readPdfDictionaryAt($value, 0);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1) {
            return $this->dictionaryObjectBody($objects[(int) $match[1]] ?? '');
        }

        return null;
    }

    /**
     * @return list<float>|null
     */
    private function rectFromAnnotation(string $annotationBody): ?array
    {
        $value = $this->valueAfterName($annotationBody, 'Rect');
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null) {
            return null;
        }

        $numbers = $this->numbersFromPdfArray($arrayBody);
        if (count($numbers) < 4) {
            return null;
        }

        return $this->normalizeRect(array_slice($numbers, 0, 4));
    }

    /**
     * @param list<float> $rect
     * @return list<float>
     */
    private function normalizeRect(array $rect): array
    {
        return [
            min($rect[0], $rect[2]),
            min($rect[1], $rect[3]),
            max($rect[0], $rect[2]),
            max($rect[1], $rect[3]),
        ];
    }

    private function pdfValueToPhpValue(string $value, array $objects): mixed
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '[')) {
            $body = $this->arrayBodyFromValue($value);
            return $body === null ? [] : $this->scalarValuesFromArrayBody($body, $objects);
        }

        return $this->pdfValueToString($value, $objects);
    }

    private function pdfValueToString(string $value, array $objects): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return $this->decodePdfStringBytes($this->decodeLiteralString(substr($value, 1, $end - 2)));
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            if (strlen($hex) % 2 === 1) {
                $hex .= '0';
            }
            $bytes = $hex === '' ? '' : hex2bin($hex);
            return $bytes === false ? null : $this->decodePdfStringBytes($bytes);
        }

        if ($value[0] === '/') {
            return $this->decodePdfName($value);
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 && isset($objects[(int) $match[1]])) {
            return $this->pdfValueToString(trim($objects[(int) $match[1]]), $objects);
        }

        if ($value === 'null') {
            return null;
        }

        if ($value === 'true' || $value === 'false' || is_numeric($value)) {
            return $value;
        }

        return null;
    }

    private function pdfStringValueAfterName(string $body, string $name, array $objects): ?string
    {
        $value = $this->valueAfterName($body, $name);
        return $value === null ? null : $this->pdfValueToString($value, $objects);
    }

    /**
     * @return list<int>|null
     */
    private function integerArrayValueAfterName(string $body, string $name): ?array
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '[')) {
            return null;
        }

        $arrayBody = $this->arrayBodyFromValue($value);
        if ($arrayBody === null || preg_match_all('/[+-]?\d+/', $arrayBody, $matches) === false) {
            return [];
        }

        return array_map('intval', $matches[0]);
    }

    private function pdfNameValueAfterName(string $body, string $name): ?string
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || !str_starts_with(trim($value), '/')) {
            return null;
        }

        return $this->decodePdfName($value);
    }

    private function numberValueAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^[+-]?\d+/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[0];
    }

    private function objectReferenceValueAfterName(string $body, string $name): ?int
    {
        $value = $this->valueAfterName($body, $name);
        if ($value === null || preg_match('/^(\d+)\s+\d+\s+R\b/', trim($value), $match) !== 1) {
            return null;
        }

        return (int) $match[1];
    }

    private function boolValueAfterName(string $body, string $name): ?bool
    {
        $value = $this->valueAfterName($body, $name);
        return match (trim((string) $value)) {
            'true' => true,
            'false' => false,
            default => null,
        };
    }

    private function valueAfterName(string $body, string $name): ?string
    {
        if (preg_match('/\/' . preg_quote($name, '/') . '\b/s', $body, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return null;
        }

        $offset = $match[0][1] + strlen($match[0][0]);
        $this->skipWhitespace($body, $offset);
        if ($offset >= strlen($body)) {
            return null;
        }

        if (preg_match('/\G\d+\s+\d+\s+R\b/s', $body, $ref, 0, $offset) === 1) {
            return $ref[0];
        }

        if ($body[$offset] === '[') {
            $endOffset = null;
            $this->readPdfArrayAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if (substr($body, $offset, 2) === '<<') {
            $endOffset = null;
            $this->readPdfDictionaryAt($body, $offset, $endOffset);
            return $endOffset === null ? null : substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '(') {
            $endOffset = $this->skipLiteralString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '<') {
            $endOffset = $this->skipHexString($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        if ($body[$offset] === '/') {
            $endOffset = $this->skipPdfName($body, $offset);
            return substr($body, $offset, $endOffset - $offset);
        }

        $end = $offset;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return substr($body, $offset, max(0, $end - $offset));
    }

    /**
     * @param array<int, string> $objects
     * @return array{body: string, object: int|null}|null
     */
    private function resolvedDictionaryFromValue(string $value, array $objects): ?array
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if (str_starts_with($value, '<<')) {
            $body = $this->readPdfDictionaryAt($value, 0);
            return $body === null ? null : ['body' => $body, 'object' => null];
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) !== 1) {
            return null;
        }

        $objectNumber = (int) $match[1];
        $body = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
        return $body === null ? null : ['body' => $body, 'object' => $objectNumber];
    }

    /**
     * @param array<int, string> $objects
     * @return list<array{body: string, object: int|null}>
     */
    private function dictionaryValuesFromArrayBody(string $body, array $objects): array
    {
        $dictionaries = [];
        $offset = 0;
        $length = strlen($body);
        while ($offset < $length) {
            $this->skipWhitespace($body, $offset);
            if ($offset >= $length) {
                break;
            }

            if (substr($body, $offset, 2) === '<<') {
                $endOffset = null;
                $dictionaryBody = $this->readPdfDictionaryAt($body, $offset, $endOffset);
                if ($dictionaryBody === null || $endOffset === null) {
                    $offset++;
                    continue;
                }

                $dictionaries[] = ['body' => $dictionaryBody, 'object' => null];
                $offset = $endOffset;
                continue;
            }

            if (preg_match('/\G(\d+)\s+\d+\s+R\b/s', $body, $match, 0, $offset) === 1) {
                $objectNumber = (int) $match[1];
                $dictionaryBody = $this->dictionaryObjectBody($objects[$objectNumber] ?? '');
                if ($dictionaryBody !== null) {
                    $dictionaries[] = ['body' => $dictionaryBody, 'object' => $objectNumber];
                }
                $offset += strlen($match[0]);
                continue;
            }

            $offset++;
        }

        return $dictionaries;
    }

    private function signatureContentsLength(string $value, array $objects): ?int
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        if ($value[0] === '<' && substr($value, 0, 2) !== '<<') {
            $end = $this->skipHexString($value, 0);
            $hex = preg_replace('/\s+/', '', substr($value, 1, $end - 2)) ?? '';
            return intdiv(strlen($hex) + 1, 2);
        }

        if ($value[0] === '(') {
            $end = $this->skipLiteralString($value, 0);
            return strlen($this->decodeLiteralString(substr($value, 1, $end - 2)));
        }

        if (preg_match('/^(\d+)\s+\d+\s+R\b/', $value, $match) === 1 && isset($objects[(int) $match[1]])) {
            return $this->signatureContentsLength(trim($objects[(int) $match[1]]), $objects);
        }

        return null;
    }

    private function skipWhitespace(string $body, int &$offset): void
    {
        while ($offset < strlen($body) && ctype_space($body[$offset])) {
            $offset++;
        }
    }

    private function skipPdfName(string $body, int $offset): int
    {
        $end = $offset + 1;
        while ($end < strlen($body) && !ctype_space($body[$end]) && !str_contains('[]()<>{}/%', $body[$end])) {
            $end++;
        }

        return $end;
    }

    /**
     * @return array<int, string>
     */
    private function pdfObjects(string $pdfBytes): array
    {
        $objects = [];
        if (!preg_match_all('/(\d+)\s+\d+\s+obj\b(.*?)\bendobj/s', $pdfBytes, $matches, PREG_SET_ORDER)) {
            return $objects;
        }

        foreach ($matches as $match) {
            $objects[(int) $match[1]] = $match[2];
        }

        return $objects;
    }

    /**
     * @param array<int, string> $objects
     */
    private function catalogObjectBody(array $objects): ?string
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) === 1) {
                return $this->dictionaryObjectBody($body);
            }
        }

        return null;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     */
    private function orderedPageObjectNumbers(array $objects): array
    {
        foreach ($objects as $body) {
            if (preg_match('/\/Type\s*\/Catalog\b/', $body) !== 1 || preg_match('/\/Pages\s+(\d+)\s+\d+\s+R\b/s', $body, $match) !== 1) {
                continue;
            }

            $pages = $this->pageObjectNumbersFromTree((int) $match[1], $objects);
            if ($pages !== []) {
                return $pages;
            }
        }

        $pages = [];
        foreach ($objects as $objectNumber => $body) {
            if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
                $pages[] = $objectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     * @param array<int, string> $objects
     * @param array<int, true> $seen
     */
    private function pageObjectNumbersFromTree(int $objectNumber, array $objects, array $seen = []): array
    {
        if (isset($seen[$objectNumber]) || !isset($objects[$objectNumber])) {
            return [];
        }

        $seen[$objectNumber] = true;
        $body = $objects[$objectNumber];
        if (preg_match('/\/Type\s*\/Page\b/', $body) === 1) {
            return [$objectNumber];
        }

        $kids = $this->valueAfterName($body, 'Kids');
        if ($kids === null || !str_starts_with(trim($kids), '[')) {
            return [];
        }

        $arrayBody = $this->arrayBodyFromValue($kids);
        if ($arrayBody === null) {
            return [];
        }

        $pages = [];
        foreach ($this->objectReferences($arrayBody) as $childObjectNumber) {
            foreach ($this->pageObjectNumbersFromTree($childObjectNumber, $objects, $seen) as $pageObjectNumber) {
                $pages[] = $pageObjectNumber;
            }
        }

        return $pages;
    }

    /**
     * @return list<int>
     */
    private function objectReferences(string $value): array
    {
        if (!preg_match_all('/(\d+)\s+\d+\s+R\b/', $value, $matches)) {
            return [];
        }

        return array_map('intval', $matches[1]);
    }

    private function dictionaryObjectBody(string $objectBody): ?string
    {
        $offset = strpos($objectBody, '<<');
        return $offset === false ? null : $this->readPdfDictionaryAt($objectBody, $offset);
    }

    private function arrayBodyFromValue(string $value): ?string
    {
        $offset = strpos($value, '[');
        return $offset === false ? null : $this->readPdfArrayAt($value, $offset);
    }

    private function readPdfDictionaryAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (substr($value, $offset, 2) !== '<<') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 2;
        for ($index = $offset, $length = strlen($value); $index < $length - 1; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) !== '<<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }

            $pair = substr($value, $index, 2);
            if ($pair === '<<') {
                $depth++;
                $index++;
                continue;
            }

            if ($pair !== '>>') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 2;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
            $index++;
        }

        return null;
    }

    private function readPdfArrayAt(string $value, int $offset, ?int &$endOffset = null): ?string
    {
        if (($value[$offset] ?? '') !== '[') {
            return null;
        }

        $depth = 0;
        $bodyStart = $offset + 1;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '(') {
                $index = $this->skipLiteralString($value, $index) - 1;
                continue;
            }
            if ($char === '<' && substr($value, $index, 2) === '<<') {
                $endDictionary = null;
                $this->readPdfDictionaryAt($value, $index, $endDictionary);
                if ($endDictionary !== null) {
                    $index = $endDictionary - 1;
                    continue;
                }
            }
            if ($char === '<') {
                $index = $this->skipHexString($value, $index) - 1;
                continue;
            }
            if ($char === '[') {
                $depth++;
                continue;
            }
            if ($char !== ']') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                $endOffset = $index + 1;
                return substr($value, $bodyStart, $index - $bodyStart);
            }
        }

        return null;
    }

    private function skipLiteralString(string $value, int $offset): int
    {
        $depth = 0;
        for ($index = $offset, $length = strlen($value); $index < $length; $index++) {
            $char = $value[$index];
            if ($char === '\\') {
                $index++;
                continue;
            }
            if ($char === '(') {
                $depth++;
                continue;
            }
            if ($char !== ')') {
                continue;
            }

            $depth--;
            if ($depth === 0) {
                return $index + 1;
            }
        }

        return strlen($value);
    }

    private function skipHexString(string $value, int $offset): int
    {
        $end = strpos($value, '>', $offset + 1);
        return $end === false ? strlen($value) : $end + 1;
    }

    /**
     * @return list<float>
     */
    private function numbersFromPdfArray(string $arrayBody): array
    {
        if (!preg_match_all('/[+-]?(?:\d+(?:\.\d*)?|\.\d+)/', $arrayBody, $matches)) {
            return [];
        }

        return array_map('floatval', $matches[0]);
    }

    private function decodePdfName(string $name): string
    {
        $name = ltrim($name, '/');

        return preg_replace_callback('/#([0-9A-Fa-f]{2})/', static fn (array $match): string => chr(hexdec($match[1])), $name) ?? $name;
    }

    private function decodePdfStringBytes(string $bytes): string
    {
        $prefix = strtolower(bin2hex(substr($bytes, 0, 2)));
        if ($prefix === 'feff') {
            $decoded = iconv('UTF-16BE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }
        if ($prefix === 'fffe') {
            $decoded = iconv('UTF-16LE', 'UTF-8//IGNORE', substr($bytes, 2));
            return $decoded === false ? '' : $decoded;
        }

        return $bytes;
    }

    private function decodeLiteralString(string $value): string
    {
        $value = preg_replace("/\\\\\r\n|\\\\\n|\\\\\r/s", '', $value) ?? $value;

        return preg_replace_callback('/\\\\([0-7]{1,3}|.)/s', static function (array $match): string {
            return match ($match[1]) {
                'n' => "\n",
                'r' => "\r",
                't' => "\t",
                'b' => "\x08",
                'f' => "\x0c",
                '(' => '(',
                ')' => ')',
                '\\' => '\\',
                default => preg_match('/^[0-7]+$/', $match[1]) === 1 ? chr(octdec($match[1]) & 0xff) : $match[1],
            };
        }, $value) ?? $value;
    }
}
