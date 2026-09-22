<?php
/**
 * Вспомогательные функции разметки для формы номера (очередь/группа обзвона):
 * выбор файла музыки (с быстрой загрузкой своего) и выбор цели маршрутизации
 * при сбоях (интерактивный поиск по сценариям и номерам).
 */

use App\Core\View;

if (!function_exists('musicPicker')) {
    function musicPicker(string $fieldName, array $musicFiles, int $selectedId): string
    {
        $selectId = 'field-' . $fieldName . '-select';
        $html = '<select name="' . View::e($fieldName) . '" id="' . $selectId . '" class="form-select mb-1 music-select">';
        $html .= '<option value="">— не выбрано —</option>';
        foreach ($musicFiles as $mf) {
            $sel = (int)$mf['id'] === $selectedId ? 'selected' : '';
            $html .= '<option value="' . (int)$mf['id'] . '" ' . $sel . '>' . View::e($mf['title']) . '</option>';
        }
        $html .= '</select>';
        $html .= '<div class="input-group input-group-sm">';
        $html .= '<input type="file" class="form-control music-quick-upload" data-target-select="' . $selectId . '" accept=".wav,.mp3,audio/wav,audio/mpeg">';
        $html .= '<span class="input-group-text music-quick-upload-status text-muted" style="display:none;"></span>';
        $html .= '</div>';
        $html .= '<div class="form-text">Выберите файл из справочника «Музыка» или загрузите свой — он тоже попадёт в справочник.</div>';
        return $html;
    }
}

if (!function_exists('failoverPicker')) {
    function failoverPicker(string $prefix, ?string $targetType, $targetId, ?string $targetLabel): string
    {
        $html = '<div class="position-relative mb-1">';
        $html .= '<input type="text" class="form-control failover-search-input" data-prefix="' . View::e($prefix) . '" autocomplete="off" placeholder="Сценарий, номер, очередь, группа обзвона, служебный номер...">';
        $html .= '<div class="list-group position-absolute w-100 shadow-sm failover-search-results" data-prefix="' . View::e($prefix) . '" style="z-index: 20; max-height: 260px; overflow-y: auto; display:none;"></div>';
        $html .= '</div>';
        $html .= '<div id="' . View::e($prefix) . '-selected" class="mb-1">';
        if ($targetLabel) {
            $html .= '<span class="badge text-bg-light text-dark border">' . View::e($targetLabel) . ' <a href="#" class="failover-clear text-danger text-decoration-none" data-prefix="' . View::e($prefix) . '">✕</a></span>';
        }
        $html .= '</div>';
        $html .= '<input type="hidden" name="' . View::e($prefix) . '_target_type" id="' . View::e($prefix) . '_target_type" value="' . View::e((string)($targetType ?? '')) . '">';
        $html .= '<input type="hidden" name="' . View::e($prefix) . '_target_id" id="' . View::e($prefix) . '_target_id" value="' . View::e((string)($targetId ?? '')) . '">';
        return $html;
    }
}
