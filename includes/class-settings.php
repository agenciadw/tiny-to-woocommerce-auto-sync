<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gerenciador de Configurações
 */
class Tiny_WooCommerce_Settings {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_init', array($this, 'maybe_fix_cron_schedule'), 5);
    }

    /**
     * Corrige o cron se o intervalo salvo não for o mesmo que está agendado
     */
    public function maybe_fix_cron_schedule() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'tiny-woo-sync') {
            return;
        }

        $settings = get_option('tiny_woo_sync_settings');
        if (empty($settings['sync_enabled']) || empty($settings['sync_interval'])) {
            return;
        }

        $next = wp_next_scheduled('tiny_woo_sync_cron');
        if (!$next) {
            $this->reschedule_cron($settings['sync_interval']);
            return;
        }

        // Verifica se o próximo agendamento está dentro do intervalo esperado
        $schedules = wp_get_schedules();
        $interval_seconds = isset($schedules[$settings['sync_interval']]['interval']) 
            ? $schedules[$settings['sync_interval']]['interval'] 
            : 3600;
        $max_next = time() + $interval_seconds + 60; // 1 min de tolerância

        if ($next > $max_next) {
            $this->reschedule_cron($settings['sync_interval']);
        }
    }

    /**
     * Registra configurações
     */
    public function register_settings() {
        register_setting(
            'tiny_woo_sync_settings_group',
            'tiny_woo_sync_settings',
            array($this, 'sanitize_settings')
        );
    }

    /**
     * Sanitiza configurações
     */
    public function sanitize_settings($input) {
        $sanitized = array();

        $sanitized['api_token'] = isset($input['api_token']) ? sanitize_text_field($input['api_token']) : '';
        $sanitized['sync_enabled'] = isset($input['sync_enabled']) ? (bool) $input['sync_enabled'] : false;
        $sanitized['sync_mode'] = (isset($input['sync_mode']) && $input['sync_mode'] === 'woocommerce') ? 'woocommerce' : 'tiny';
        $sanitized['sync_interval'] = isset($input['sync_interval']) ? sanitize_text_field($input['sync_interval']) : 'hourly';
        $sanitized['batch_size'] = isset($input['batch_size']) ? intval($input['batch_size']) : 30;
        $sanitized['delay_between_requests'] = isset($input['delay_between_requests']) ? floatval($input['delay_between_requests']) : 1.5;
        $sanitized['log_retention_days'] = isset($input['log_retention_days']) ? intval($input['log_retention_days']) : 30;
        $sanitized['report_email_enabled'] = isset($input['report_email_enabled']) ? (bool) $input['report_email_enabled'] : false;
        $sanitized['report_email'] = isset($input['report_email']) ? sanitize_text_field($input['report_email']) : '';
        $sanitized['report_schedule'] = isset($input['report_schedule']) ? sanitize_text_field($input['report_schedule']) : 'daily';

        if (!in_array($sanitized['report_schedule'], array('daily', 'weekly', 'monthly'), true)) {
            $sanitized['report_schedule'] = 'daily';
        }

        // Reagenda relatório por e-mail (usa valores já sanitizados)
        Tiny_WooCommerce_Sync_Report::reschedule_report_cron($sanitized);

        // Valida batch_size
        if ($sanitized['batch_size'] < 20) {
            $sanitized['batch_size'] = 20;
        } elseif ($sanitized['batch_size'] > 100) {
            $sanitized['batch_size'] = 100;
        }

        // Reagenda cron quando sync está ativo (garante que use o intervalo correto)
        if (!empty($sanitized['sync_enabled'])) {
            $this->reschedule_cron($sanitized['sync_interval']);
        }

        return $sanitized;
    }

    /**
     * Reagenda cron
     */
    private function reschedule_cron($interval) {
        // Remove todos os eventos agendados do cron
        wp_clear_scheduled_hook('tiny_woo_sync_cron');

        // Agenda novo evento com o intervalo correto
        wp_schedule_event(time(), $interval, 'tiny_woo_sync_cron');
    }

    /**
     * Obtém configuração específica
     */
    public static function get($key, $default = '') {
        $settings = get_option('tiny_woo_sync_settings');
        return isset($settings[$key]) ? $settings[$key] : $default;
    }
}
