<?php
/**
 * Template do relatório de sincronização - texto puro
 *
 * @package Tiny_WooCommerce_Sync
 * @var array $report_data Dados do relatório
 * @var string $email_heading Cabeçalho do e-mail
 * @var WC_Email $email Objeto do e-mail
 */

if (!defined('ABSPATH')) {
    exit;
}

$products = isset($report_data['products']) ? $report_data['products'] : array();
$period_start = isset($report_data['period_start']) ? $report_data['period_start'] : '';
$period_end = isset($report_data['period_end']) ? $report_data['period_end'] : '';
$total = isset($report_data['total']) ? (int) $report_data['total'] : 0;

echo sprintf(__('Relatório de sincronização Tiny ERP para o período de %s a %s.', 'tiny-woo-sync'), $period_start, $period_end) . "\n\n";

if ($total > 0) {
    echo sprintf(_n('%d produto foi atualizado.', '%d produtos foram atualizados.', $total, 'tiny-woo-sync'), $total) . "\n\n";
    echo "----------------------------------------\n";
    echo esc_html__('Produto', 'tiny-woo-sync') . " | " . esc_html__('SKU', 'tiny-woo-sync') . " | " . esc_html__('Data/Hora', 'tiny-woo-sync') . "\n";
    echo "----------------------------------------\n";

    foreach ($products as $item) {
        echo $item['name'] . " | " . $item['sku'] . " | " . $item['date'] . "\n";
    }

    echo "----------------------------------------\n\n";
} else {
    echo __("Nenhum produto foi atualizado neste período.", 'tiny-woo-sync') . "\n\n";
}

echo __("Este é um relatório automático do plugin DW Atualiza Produtos for Tiny ERP.", 'tiny-woo-sync') . "\n";
