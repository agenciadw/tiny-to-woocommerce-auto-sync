<?php
/**
 * Template do relatório de sincronização - HTML
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
?>

<p><?php echo esc_html(sprintf(__('Relatório de sincronização Tiny ERP para o período de %s a %s.', 'tiny-woo-sync'), $period_start, $period_end)); ?></p>

<?php if ($total > 0): ?>
    <p><strong><?php echo esc_html(sprintf(_n('%d produto foi atualizado.', '%d produtos foram atualizados.', $total, 'tiny-woo-sync'), $total)); ?></strong></p>

    <table class="td" cellspacing="0" cellpadding="6" style="width: 100%; font-family: 'Helvetica Neue', Helvetica, Roboto, Arial, sans-serif; margin: 16px 0;" border="1">
        <thead>
            <tr>
                <th class="td" scope="col" style="text-align: left; color: #636363; border: 1px solid #e5e5e5; padding: 12px;"><?php esc_html_e('Produto', 'tiny-woo-sync'); ?></th>
                <th class="td" scope="col" style="text-align: left; color: #636363; border: 1px solid #e5e5e5; padding: 12px;"><?php esc_html_e('SKU', 'tiny-woo-sync'); ?></th>
                <th class="td" scope="col" style="text-align: left; color: #636363; border: 1px solid #e5e5e5; padding: 12px;"><?php esc_html_e('Data/Hora', 'tiny-woo-sync'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($products as $item): ?>
                <tr>
                    <td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo esc_html($item['name']); ?></td>
                    <td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo esc_html($item['sku']); ?></td>
                    <td class="td" style="text-align: left; border: 1px solid #e5e5e5; padding: 12px;"><?php echo esc_html($item['date']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p><?php esc_html_e('Nenhum produto foi atualizado neste período.', 'tiny-woo-sync'); ?></p>
<?php endif; ?>

<p><?php esc_html_e('Este é um relatório automático do plugin DW Atualiza Produtos for Tiny ERP.', 'tiny-woo-sync'); ?></p>
