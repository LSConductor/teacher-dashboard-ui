<?php

namespace App\Hr;

use DOMDocument;
use DOMElement;
use RuntimeException;
use ZipArchive;

class XlsxBook
{
    /**
     * Sheet name to rows of cell strings. Empty cells are empty strings.
     *
     * @return array<string, array<int, array<int, string>>>
     */
    public function sheets(string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path) !== true) {
            throw new RuntimeException('Not a workbook');
        }
        $shared = $this->sharedStrings($zip);
        $targets = $this->targets($zip);
        $sheets = [];
        foreach ($targets as $name => $target) {
            $xml = $zip->getFromName($target);
            $sheets[$name] = $xml === false ? [] : $this->grid($xml, $shared);
        }
        $zip->close();

        return $sheets;
    }

    /**
     * @return array<int, string>
     */
    private function sharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $dom = $this->dom($xml);
        $strings = [];
        foreach ($dom->getElementsByTagName('si') as $item) {
            $text = '';
            foreach ($item->getElementsByTagName('t') as $node) {
                $text .= $node->textContent;
            }
            $strings[] = $text;
        }

        return $strings;
    }

    /**
     * @return array<string, string>
     */
    private function targets(ZipArchive $zip): array
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbook === false || $rels === false) {
            throw new RuntimeException('Not a workbook');
        }
        $ids = [];
        foreach ($this->dom($rels)->getElementsByTagName('Relationship') as $rel) {
            if (! $rel instanceof DOMElement) {
                continue;
            }
            $target = $rel->getAttribute('Target');
            $target = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
            $ids[$rel->getAttribute('Id')] = $target;
        }
        $sheets = [];
        foreach ($this->dom($workbook)->getElementsByTagName('sheet') as $sheet) {
            if (! $sheet instanceof DOMElement) {
                continue;
            }
            $rid = $sheet->getAttribute('r:id');
            if ($rid === '') {
                $rid = $sheet->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id');
            }
            if ($rid !== '' && isset($ids[$rid])) {
                $sheets[$sheet->getAttribute('name')] = $ids[$rid];
            }
        }

        return $sheets;
    }

    /**
     * @param  array<int, string>  $shared
     * @return array<int, array<int, string>>
     */
    private function grid(string $xml, array $shared): array
    {
        $rows = [];
        foreach ($this->dom($xml)->getElementsByTagName('row') as $row) {
            $cells = [];
            $width = 0;
            foreach ($row->getElementsByTagName('c') as $cell) {
                if (! $cell instanceof DOMElement) {
                    continue;
                }
                $index = $this->column($cell->getAttribute('r'));
                $cells[$index] = $this->value($cell, $shared);
                $width = max($width, $index + 1);
            }
            $line = array_fill(0, $width, '');
            foreach ($cells as $index => $value) {
                $line[$index] = $value;
            }
            $rows[] = $line;
        }

        return $rows;
    }

    /**
     * @param  array<int, string>  $shared
     */
    private function value(DOMElement $cell, array $shared): string
    {
        $type = $cell->getAttribute('t');
        if ($type === 'inlineStr') {
            $text = '';
            foreach ($cell->getElementsByTagName('t') as $node) {
                $text .= $node->textContent;
            }

            return $text;
        }
        $raw = '';
        foreach ($cell->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === 'v') {
                $raw = $child->textContent;
                break;
            }
        }
        if ($type === 's') {
            return $shared[(int) $raw] ?? '';
        }

        return $raw;
    }

    private function column(string $ref): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($ref)) ?: 'A';
        $number = 0;
        foreach (str_split($letters) as $letter) {
            $number = $number * 26 + (ord($letter) - 64);
        }

        return $number - 1;
    }

    private function dom(string $xml): DOMDocument
    {
        $dom = new DOMDocument;
        $dom->loadXML($xml, LIBXML_NONET);

        return $dom;
    }
}
