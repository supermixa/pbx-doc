<?php

namespace App\Core\Export;

require_once __DIR__ . '/../../../lib/tfpdf/tfpdf.php';
require_once __DIR__ . '/../../../lib/tfpdf/font/unifont/ttfonts.php';

/**
 * Генерация PDF со списком (таблицами) на основе tFPDF — UTF-8 и кириллица
 * через встроенный шрифт DejaVu Sans. Альбомная A4, заголовок таблицы
 * повторяется на каждой новой странице, длинный текст в ячейках переносится.
 *
 * Формат секций — см. Exporter::send().
 */
final class PdfWriter extends \tFPDF
{
    private const FONT = 'DejaVu';
    private const LINE_H = 4.6;     // высота строки текста в ячейке, мм
    private const PAD = 1.2;        // внутренний отступ ячейки, мм
    private const FONT_SIZE = 8.5;

    private string $footerText = '';

    public static function build(string $title, string $subtitle, array $sections): string
    {
        $pdf = new self('L', 'mm', 'A4');
        $pdf->SetTitle($title, true);
        $pdf->SetCreator('PBX Doc', true);
        $pdf->AddFont(self::FONT, '', 'DejaVuSans.ttf', true);
        $pdf->AddFont(self::FONT, 'B', 'DejaVuSans-Bold.ttf', true);
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 14);
        $pdf->AliasNbPages();
        $pdf->footerText = 'PBX Doc · сформировано ' . date('d.m.Y H:i');
        $pdf->AddPage();

        $pdf->SetFont(self::FONT, 'B', 14);
        $pdf->MultiCell(0, 7, $title, 0, 'L');
        if ($subtitle !== '') {
            $pdf->SetFont(self::FONT, '', 9);
            $pdf->SetTextColor(110, 110, 110);
            $pdf->MultiCell(0, 5, $subtitle, 0, 'L');
            $pdf->SetTextColor(0, 0, 0);
        }

        foreach ($sections as $section) {
            $pdf->Ln(3);
            $pdf->section($section);
        }

        return $pdf->Output('S');
    }

    public function Footer(): void
    {
        $this->SetY(-10);
        $this->SetFont(self::FONT, '', 7.5);
        $this->SetTextColor(120, 120, 120);
        $this->Cell(0, 5, $this->footerText, 0, 0, 'L');
        $this->Cell(0, 5, 'Страница ' . $this->PageNo() . ' из {nb}', 0, 0, 'R');
        $this->SetTextColor(0, 0, 0);
    }

    private function section(array $section): void
    {
        $headers = array_values($section['headers']);
        $rows = array_map('array_values', $section['rows']);
        $widths = $this->columnWidths($headers, $rows);

        if (!empty($section['title'])) {
            // Заголовок секции не должен остаться один внизу страницы
            if ($this->GetY() + 20 > $this->PageBreakTrigger) {
                $this->AddPage();
            }
            $this->SetFont(self::FONT, 'B', 10.5);
            $this->Cell(0, 7, $section['title'], 0, 1, 'L');
        }

        $this->tableRow($headers, $widths, true);
        if (empty($rows)) {
            $this->SetFont(self::FONT, '', self::FONT_SIZE);
            $this->Cell(array_sum($widths), 6, 'Записей нет', 1, 1, 'L');
            return;
        }
        foreach ($rows as $row) {
            $height = $this->rowHeight($row, $widths, false);
            if ($this->GetY() + $height > $this->PageBreakTrigger) {
                $this->AddPage();
                $this->tableRow($headers, $widths, true);
            }
            $this->tableRow($row, $widths, false);
        }
    }

    /** Ширины колонок по содержимому, растянутые/сжатые до ширины страницы */
    private function columnWidths(array $headers, array $rows): array
    {
        $available = $this->GetPageWidth() - $this->lMargin - $this->rMargin;
        $natural = [];
        foreach ($headers as $i => $h) {
            $this->SetFont(self::FONT, 'B', self::FONT_SIZE);
            $w = $this->GetStringWidth($h);
            $this->SetFont(self::FONT, '', self::FONT_SIZE);
            foreach ($rows as $r) {
                foreach (explode("\n", (string)($r[$i] ?? '')) as $line) {
                    $w = max($w, $this->GetStringWidth($line));
                }
            }
            // одна колонка не забирает больше 35% ширины — длинное переносится
            $natural[$i] = min($w + 2 * self::PAD + 1, $available * 0.35);
        }
        $total = array_sum($natural) ?: 1;
        return array_map(fn($w) => $w * $available / $total, $natural);
    }

    private function rowHeight(array $cells, array $widths, bool $isHeader): float
    {
        $this->SetFont(self::FONT, $isHeader ? 'B' : '', self::FONT_SIZE);
        $lines = 1;
        foreach ($widths as $i => $w) {
            $lines = max($lines, count($this->wrap((string)($cells[$i] ?? ''), $w - 2 * self::PAD)));
        }
        return $lines * self::LINE_H + 2 * self::PAD;
    }

    private function tableRow(array $cells, array $widths, bool $isHeader): void
    {
        $height = $this->rowHeight($cells, $widths, $isHeader);
        $this->SetFont(self::FONT, $isHeader ? 'B' : '', self::FONT_SIZE);
        $this->SetDrawColor(190, 190, 190);
        if ($isHeader) {
            $this->SetFillColor(233, 236, 239);
        }
        $x = $this->lMargin;
        $y = $this->GetY();
        foreach ($widths as $i => $w) {
            $this->Rect($x, $y, $w, $height, $isHeader ? 'DF' : 'D');
            $text = (string)($cells[$i] ?? '');
            if (!$isHeader && $text === '') {
                $text = '—';
            }
            $lineY = $y + self::PAD;
            foreach ($this->wrap($text, $w - 2 * self::PAD) as $line) {
                $this->SetXY($x + self::PAD, $lineY);
                $this->Cell($w - 2 * self::PAD, self::LINE_H, $line, 0, 0, 'L');
                $lineY += self::LINE_H;
            }
            $x += $w;
        }
        $this->SetXY($this->lMargin, $y + $height);
    }

    /** Перенос текста по словам под ширину $width (длинные слова режутся по символам) */
    private function wrap(string $text, float $width): array
    {
        $result = [];
        foreach (explode("\n", str_replace("\r", '', $text)) as $paragraph) {
            $line = '';
            foreach (preg_split('/ +/u', $paragraph) as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($this->GetStringWidth($candidate) <= $width) {
                    $line = $candidate;
                    continue;
                }
                if ($line !== '') {
                    $result[] = $line;
                    $line = '';
                }
                // слово длиннее ширины колонки — режем по символам
                while ($this->GetStringWidth($word) > $width && mb_strlen($word) > 1) {
                    $cut = mb_strlen($word) - 1;
                    while ($cut > 1 && $this->GetStringWidth(mb_substr($word, 0, $cut)) > $width) {
                        $cut--;
                    }
                    $result[] = mb_substr($word, 0, $cut);
                    $word = mb_substr($word, $cut);
                }
                $line = $word;
            }
            $result[] = $line;
        }
        return $result ?: [''];
    }
}
