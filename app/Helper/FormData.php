<?php

namespace App\Helper;


class FormData
{
    public  $inputs = [];
    public  $files = [];
    private $content;
    public function __construct( string $content)
    {
        $this->content=$content;
        $this->parseContent($this->content);
    }

    private function parseContent(string $content)
    {
        $parts = $this->getParts($content);

        foreach ($parts as $part) {
            $this->processContent($part);
        }
    }

    private function getParts(string $content): array
    {
        $boundary = $this->getBoundary($content);

        if (empty($boundary)) return [];

        $parts = explode($boundary, $content);

        return array_filter($parts, function (string $part): bool {
            return mb_strlen($part) > 0 && $part !== "--\r\n";
        });
    }

    private function getBoundary(string $content): string
    {
        $firstNewLinePosition = strpos($content, "\r\n");

        return $firstNewLinePosition ? substr($content, 0, $firstNewLinePosition) : "";
    }

    private function processContent(string $content)
    {
        $content = ltrim($content, "\r\n");
        list($rawHeaders, $rawContent) = explode("\r\n\r\n", $content, 2);

        $headers = $this->parseHeaders($rawHeaders);

        if (isset($headers['content-disposition'])) {
            $this->parseContentDisposition($headers, $rawContent);
        }
    }

    private function parseHeaders(string $headers): array
    {
        $data = [];

        $headers = explode("\r\n", $headers);

        foreach ($headers as $header) {
            list($name, $value) = explode(':', $header);

            $name = strtolower($name);

            $data[$name] = ltrim($value, ' ');
        }

        return $data;
    }

    private function parseContentDisposition(array $headers, string $content)
    {
        $content = substr($content, 0, strlen($content) - 2);

        preg_match('/^form-data; *name="([^"]+)"(; *filename="([^"]+)")?/', $headers['content-disposition'], $matches);
        $fieldName = $matches[1];

        $fileName = $matches[3] ?? null;

        if (is_null($fileName)) {
            $input = $this->transformContent($fieldName, $content);

            $this->inputs = array_merge_recursive($this->inputs, $input);
        } else {
            $file = $this->storeFile($fileName, $headers['content-type'], $content);

            $file = $this->transformContent($fieldName, $file);

            $this->files = array_merge_recursive($this->files, $file);
        }
    }

    private function transformContent(string $name,  $value): array
    {
        parse_str($name, $parsedName);

        $transform = function (array $array,  $value) use (&$transform) {
            foreach ($array as &$val) {
                $val = is_array($val) ? $transform($val, $value) : $value;
            }

            return $array;
        };

        return $transform($parsedName, $value);
    }

    private function storeFile(string $name, string $type, string $content): array
    {
        $tempDirectory = sys_get_temp_dir();
        $tempName = tempnam($tempDirectory, 'ShebaTemp');

        file_put_contents($tempName, $content);

        register_shutdown_function(function () use ($tempName) {
            if (file_exists($tempName)) {
                unlink($tempName);
            }
        });

        return [
            'name' => $name,
            'type' => $type,
            'tmp_name' => $tempName,
            'error' => 0,
            'size' => filesize($tempName),
        ];
    }
}
