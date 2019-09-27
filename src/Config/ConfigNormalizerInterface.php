<?php

namespace Rikudou\MimeTypeDetector\Config;

interface ConfigNormalizerInterface
{
    /**
     * Normalizes a given file with configs
     *
     * @param string $configFile
     * @return array
     * @see normalizerArray() for return format example
     */
    public function normalizeFile(string $configFile): array;

    /**
     * Normalizes an array. All the values specified in example below must be returned even if empty.
     *
     * The parameter 'binary' accepts either null or a boolean.
     * The parameter 'content' accepts either null or a string.
     *
     * @param array $config
     * @return array [
     *      'mimeType' => [
     *          0 => [
     *              'length' => 1,
     *              'offset' => 0,
     *              'binary' => null,
     *              'archive' => false,
     *              'files' => [
     *                  0 => [
     *                      'name' => 'path/to/file/in/archive'
     *                      'dir' => false,
     *                      'pattern' => false,
     *                      'binary' => null,
     *                      'content' => null
     *                  ]
     *              ],
     *              'bytes' => [
     *                  0 => 'ff',
     *              ]
     *          ]
     *      ]
     * ]
     */
    public function normalizeArray(array $config): array;
}
