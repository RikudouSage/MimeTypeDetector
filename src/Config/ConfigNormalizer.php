<?php

namespace Rikudou\MimeTypeDetector\Config;

use Rikudou\MimeTypeDetector\MimeTypeException;
use Symfony\Component\Yaml\Yaml;

final class ConfigNormalizer implements ConfigNormalizerInterface
{
    /**
     * @var array
     */
    private $disabledMimeTypes;

    /**
     * @var bool
     */
    private $advancedDetection;

    public function __construct(bool $advancedDetection = true, array $disabledMimeTypes = [])
    {
        $this->advancedDetection = $advancedDetection;
        $this->disabledMimeTypes = $disabledMimeTypes;
    }

    public function normalizeFile(string $configFile): array
    {
        return $this->normalizeArray(Yaml::parseFile($configFile));
    }

    public function normalizeArray(array $config): array
    {
        if (!isset($config['mime_types'])) {
            throw new MimeTypeException("The config array must contain a 'mime_types' root key");
        }
        $result = $config['mime_types'];

        foreach ($config['mime_types'] as $mimeType => $configurations) {
            if (in_array($mimeType, $this->disabledMimeTypes, true)) {
                unset($result[$mimeType]);
                continue;
            }

            if (isset($configurations['length']) || isset($configurations['parent'])) {
                $configurations = [$configurations];
            }

            $result[$mimeType] = $configurations;

            foreach ($configurations as $index => $configuration) {
                if (!$this->advancedDetection && isset($configuration['archive']) && $configuration['archive']) {
                    unset($result[$mimeType][$index]);
                    continue;
                }

                if (isset($configuration['parent'])) {
                    $configuration = $this->mergeWithParent($configuration, $config);
                    $result[$mimeType][$index] = $configuration;
                } else {
                    $result[$mimeType][$index]['parent'] = null;
                }

                if (!isset($configuration['offset'])) {
                    $result[$mimeType][$index]['offset'] = 0;
                }

                if (!isset($configuration['length']) || !$configuration['length']) {
                    throw new MimeTypeException("The configuration for '{$mimeType}' does not contain a length key");
                }

                $byteSequences = $configuration['bytes'];
                if (!is_array($byteSequences)) {
                    $byteSequences = [$byteSequences];
                    $result[$mimeType][$index]['bytes'] = $byteSequences;
                }

                foreach ($byteSequences as $byteSequenceIndex => $byteSequence) {
                    $result[$mimeType][$index]['bytes'][$byteSequenceIndex] = strtolower($byteSequence);
                }

                if (!isset($configuration['binary'])) {
                    $result[$mimeType][$index]['binary'] = null;
                }

                if (!isset($configuration['archive'])) {
                    $result[$mimeType][$index]['archive'] = false;
                }

                if (isset($configuration['archive']) && !isset($configuration['files'])) {
                    throw new MimeTypeException("The mime '{$mimeType}' is marked as archive but does not contain files array");
                }

                if (!isset($configuration['files'])) {
                    $result[$mimeType][$index]['files'] = [];
                }

                if (isset($configuration['files'])) {
                    $files = $configuration['files'];
                    foreach ($files as $fileIndex => $file) {
                        if (!is_array($file)) {
                            $file = [
                                'name' => $file,
                            ];
                        }
                        if (!isset($file['dir'])) {
                            $file['dir'] = false;
                        }
                        if (!isset($file['pattern'])) {
                            $file['pattern'] = false;
                        }
                        if (!isset($file['binary'])) {
                            $file['binary'] = null;
                        }
                        if (!isset($file['content'])) {
                            $file['content'] = null;
                        }
                        $result[$mimeType][$index]['files'][$fileIndex] = $file;
                    }
                }
            }
        }

        return array_filter($result);
    }

    private function mergeWithParent(array $item, array $fullConfig): array
    {
        $fullConfig = $fullConfig['mime_types'];
        $parent = $item['parent'];
        if (!isset($fullConfig[$parent])) {
            throw new MimeTypeException("Invalid parent: '{$parent}'");
        }
        $parent = $fullConfig[$parent];
        if (!isset($item['length']) && isset($parent['length'])) {
            $item['length'] = $parent['length'];
        }
        if (!isset($item['offset']) && isset($parent['offset'])) {
            $item['offset'] = $parent['offset'];
        }

        $childBytes = $item['bytes'] ?? [];
        if (!is_array($childBytes)) {
            $childBytes = [$childBytes];
        }

        $parentBytes = $parent['bytes'] ?? [];
        if (!is_array($parentBytes)) {
            $parentBytes = [$parentBytes];
        }

        $item['bytes'] = array_merge($parentBytes, $childBytes);

        return $item;
    }
}
