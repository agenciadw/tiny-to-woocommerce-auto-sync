<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gerenciador do Relatório de Sincronização por E-mail
 */
class Tiny_WooCommerce_Sync_Report {

    private static $instance = null;
    private $logger;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->logger = Tiny_WooCommerce_Logger::get_instance();

        add_action('tiny_woo_sync_report_cron', array($this, 'send_report'));
    }

    /**
     * Envia o relatório de sincronização
     *
     * @param bool $force_test Se true, envia mesmo com relatório desativado (para teste)
     */
    public function send_report($force_test = false) {
        $settings = get_option('tiny_woo_sync_settings');
        if (!$force_test && empty($settings['report_email_enabled'])) {
            return;
        }

        $schedule = isset($settings['report_schedule']) ? $settings['report_schedule'] : 'daily';
        $period = $this->get_period_for_schedule($schedule);

        $logs = $this->logger->get_updated_products_logs($period['from'], $period['to']);
        $products = $this->parse_logs_to_products($logs);

        $report_data = array(
            'products'     => $products,
            'period_start' => wp_date('d/m/Y H:i', strtotime($period['from'])),
            'period_end'   => wp_date('d/m/Y H:i', strtotime($period['to'])),
            'total'        => count($products)
        );

        $recipient = $this->get_report_recipient();
        if (empty($recipient)) {
            return false;
        }

        $subject = sprintf(
            __('[%s] Relatório de Sincronização de Produtos', 'tiny-woo-sync'),
            get_bloginfo('name')
        );

        return $this->send_report_via_wp_mail($recipient, $subject, $report_data);
    }

    /**
     * Retorna o período (from/to) conforme o agendamento
     */
    private function get_period_for_schedule($schedule) {
        $now = current_time('mysql');

        switch ($schedule) {
            case 'weekly':
                $from = date('Y-m-d H:i:s', strtotime('-7 days', strtotime($now)));
                break;
            case 'monthly':
                $from = date('Y-m-d H:i:s', strtotime('-1 month', strtotime($now)));
                break;
            case 'daily':
            default:
                $from = date('Y-m-d H:i:s', strtotime('-1 day', strtotime($now)));
                break;
        }

        return array(
            'from' => $from,
            'to'   => $now
        );
    }

    /**
     * Converte logs em lista de produtos para o relatório
     */
    private function parse_logs_to_products($logs) {
        $products = array();

        foreach ($logs as $log) {
            $name = '';
            $sku = '';
            $changes = array();
            $date = wp_date('d/m/Y H:i', strtotime($log->created_at));

            if (!empty($log->context)) {
                $context = json_decode($log->context, true);
                if (is_array($context)) {
                    $name = isset($context['product_name']) ? $context['product_name'] : '';
                    $sku = isset($context['sku']) ? $context['sku'] : '';
                    if (!empty($context['changes'])) {
                        $changes = $this->format_changes_for_report($context['changes']);
                    }
                }
            }

            if (empty($name) || empty($sku)) {
                if (preg_match('/O produto (.+), SKU ([^\s,]+), foi atualizado/', $log->message, $m)) {
                    $name = $m[1];
                    $sku = $m[2];
                }
            }

            if (!empty($name) && !empty($sku)) {
                $products[] = array(
                    'name'    => $name,
                    'sku'     => $sku,
                    'date'    => $date,
                    'changes' => $changes
                );
            }
        }

        return $products;
    }

    /**
     * Formata as alterações para exibição no relatório
     */
    private function format_changes_for_report($changes) {
        $labels = array(
            'regular_price' => __('Preço', 'tiny-woo-sync'),
            'sale_price'    => __('Preço promocional', 'tiny-woo-sync'),
            'stock'         => __('Estoque', 'tiny-woo-sync'),
            'weight'        => __('Peso', 'tiny-woo-sync'),
            'width'         => __('Largura', 'tiny-woo-sync'),
            'height'        => __('Altura', 'tiny-woo-sync'),
            'length'        => __('Comprimento', 'tiny-woo-sync')
        );

        $formatted = array();
        foreach ($changes as $key => $data) {
            if (!isset($data['before'], $data['after'])) {
                continue;
            }
            $label = isset($labels[$key]) ? $labels[$key] : $key;
            $before = $this->format_change_value($key, $data['before']);
            $after = $this->format_change_value($key, $data['after']);
            $formatted[] = $label . ': ' . $before . ' → ' . $after;
        }

        return $formatted;
    }

    /**
     * Formata valor para exibição (preço, peso, medidas)
     */
    private function format_change_value($key, $value) {
        if ($value === '' || $value === null) {
            return '—';
        }

        switch ($key) {
            case 'regular_price':
            case 'sale_price':
                return function_exists('wc_price') ? strip_tags(wc_price($value)) : 'R$ ' . number_format((float) $value, 2, ',', '.');
            case 'weight':
                return number_format((float) $value, 3, ',', '.') . ' kg';
            case 'width':
            case 'height':
            case 'length':
                return number_format((float) $value, 2, ',', '.') . ' cm';
            case 'stock':
                return number_format((float) $value, 0, ',', '.');
            default:
                return esc_html((string) $value);
        }
    }

    /**
     * Obtém o destinatário do relatório
     */
    private function get_report_recipient() {
        $settings = get_option('tiny_woo_sync_settings');
        $recipient = !empty($settings['report_email']) ? trim($settings['report_email']) : get_option('admin_email');
        $recipients = array_map('trim', explode(',', $recipient));
        $recipients = array_filter($recipients, 'is_email');
        return implode(', ', $recipients);
    }

    /**
     * Envia relatório via wp_mail (fallback quando WC_Email falha)
     */
    private function send_report_via_wp_mail($recipient, $subject, $report_data) {
        $products = isset($report_data['products']) ? $report_data['products'] : array();
        $period_start = isset($report_data['period_start']) ? $report_data['period_start'] : '';
        $period_end = isset($report_data['period_end']) ? $report_data['period_end'] : '';
        $total = isset($report_data['total']) ? (int) $report_data['total'] : 0;

        $message = '<p>' . sprintf(__('Relatório de sincronização Tiny ERP para o período de %s a %s.', 'tiny-woo-sync'), $period_start, $period_end) . '</p>';

        if ($total > 0) {
            $message .= '<p><strong>' . sprintf(_n('%d produto foi atualizado.', '%d produtos foram atualizados.', $total, 'tiny-woo-sync'), $total) . '</strong></p>';
            $message .= '<table border="1" cellpadding="8" cellspacing="0" style="border-collapse: collapse; width: 100%;">';
            $message .= '<tr><th style="text-align: left;">' . esc_html__('Produto', 'tiny-woo-sync') . '</th><th style="text-align: left;">' . esc_html__('SKU', 'tiny-woo-sync') . '</th><th style="text-align: left;">' . esc_html__('Alterações', 'tiny-woo-sync') . '</th><th style="text-align: left;">' . esc_html__('Data/Hora', 'tiny-woo-sync') . '</th></tr>';
            foreach ($products as $item) {
                $changes_html = !empty($item['changes']) ? implode('<br>', array_map('esc_html', $item['changes'])) : '—';
                $message .= '<tr><td>' . esc_html($item['name']) . '</td><td>' . esc_html($item['sku']) . '</td><td>' . $changes_html . '</td><td>' . esc_html($item['date']) . '</td></tr>';
            }
            $message .= '</table>';
        } else {
            $message .= '<p>' . __('Nenhum produto foi atualizado neste período.', 'tiny-woo-sync') . '</p>';
        }

        $message .= '<p><em>' . __('Este é um relatório automático do plugin DW Atualiza Produtos for Tiny ERP.', 'tiny-woo-sync') . '</em></p>';

        $headers = array('Content-Type: text/html; charset=UTF-8');

        return wp_mail($recipient, $subject, $message, $headers);
    }

    /**
     * Reagenda o cron do relatório conforme as configurações
     *
     * @param array|null $settings Configurações (opcional; se não informado, usa get_option)
     */
    public static function reschedule_report_cron($settings = null) {
        wp_clear_scheduled_hook('tiny_woo_sync_report_cron');

        if ($settings === null) {
            $settings = get_option('tiny_woo_sync_settings');
        }
        if (empty($settings['report_email_enabled'])) {
            return;
        }

        $schedule = isset($settings['report_schedule']) ? $settings['report_schedule'] : 'daily';
        $interval = in_array($schedule, array('weekly', 'monthly'), true) ? $schedule : 'daily';

        wp_schedule_event(time(), $interval, 'tiny_woo_sync_report_cron');
    }
}
