<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Página de Administração
 */
class Tiny_WooCommerce_Admin_Page {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('wp_ajax_tiny_woo_manual_sync', array($this, 'ajax_manual_sync'));
        add_action('wp_ajax_tiny_woo_sync_product_by_sku', array($this, 'ajax_sync_product_by_sku'));
        add_action('wp_ajax_tiny_woo_reset_rotation', array($this, 'ajax_reset_rotation'));
        add_action('wp_ajax_tiny_woo_test_connection', array($this, 'ajax_test_connection'));
        add_action('wp_ajax_tiny_woo_clear_logs', array($this, 'ajax_clear_logs'));
        add_action('wp_ajax_tiny_woo_delete_logs_by_level', array($this, 'ajax_delete_logs_by_level'));
        add_action('wp_ajax_tiny_woo_send_test_report', array($this, 'ajax_send_test_report'));
    }

    /**
     * Adiciona menu no admin
     */
    public function add_admin_menu() {
        add_menu_page(
            'DW Atualiza Produtos for Tiny ERP',
            'DW Tiny ERP',
            'manage_woocommerce',
            'tiny-woo-sync',
            array($this, 'render_settings_page'),
            'dashicons-update',
            56
        );

        add_submenu_page(
            'tiny-woo-sync',
            'Configurações',
            'Configurações',
            'manage_woocommerce',
            'tiny-woo-sync',
            array($this, 'render_settings_page')
        );

        add_submenu_page(
            'tiny-woo-sync',
            'Logs',
            'Logs',
            'manage_woocommerce',
            'tiny-woo-sync-logs',
            array($this, 'render_logs_page')
        );
    }

    /**
     * Enfileira assets do admin
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'tiny-woo-sync') === false) {
            return;
        }

        wp_enqueue_style(
            'tiny-woo-sync-admin',
            TINY_WOO_SYNC_PLUGIN_URL . 'assets/css/admin-style.css',
            array(),
            TINY_WOO_SYNC_VERSION
        );
    }

    /**
     * Renderiza página de configurações
     */
    public function render_settings_page() {
        include TINY_WOO_SYNC_PLUGIN_DIR . 'admin/views/settings-page.php';
    }

    /**
     * Renderiza página de logs
     */
    public function render_logs_page() {
        include TINY_WOO_SYNC_PLUGIN_DIR . 'admin/views/logs-page.php';
    }

    /**
     * AJAX: Sincronização manual
     */
    public function ajax_manual_sync() {
        check_ajax_referer('tiny_woo_sync_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }

        $sync_manager = Tiny_WooCommerce_Sync_Manager::get_instance();
        $sync_manager->manual_sync();

        wp_send_json_success('Sincronização executada com sucesso');
    }

    /**
     * AJAX: Sincroniza produto por SKU
     */
    public function ajax_sync_product_by_sku() {
        check_ajax_referer('tiny_woo_sync_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }

        $sku = isset($_POST['sku']) ? sanitize_text_field($_POST['sku']) : '';
        if (empty($sku)) {
            wp_send_json_error('Informe o SKU do produto.');
        }

        $sync_manager = Tiny_WooCommerce_Sync_Manager::get_instance();
        $result = $sync_manager->sync_product_by_sku($sku);

        if ($result['success']) {
            wp_send_json_success($result['message']);
        }

        wp_send_json_error($result['message']);
    }

    /**
     * AJAX: Reseta rotação de páginas
     */
    public function ajax_reset_rotation() {
        check_ajax_referer('tiny_woo_sync_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }

        Tiny_WooCommerce_Sync_Manager::reset_rotation();

        wp_send_json_success('Rotação reiniciada. Próxima sincronização começará pela página 1.');
    }

    /**
     * AJAX: Testa conexão
     */
    public function ajax_test_connection() {
        check_ajax_referer('tiny_woo_sync_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }

        $api = new Tiny_WooCommerce_API();

        if (!$api->is_token_valid()) {
            wp_send_json_error('Token não configurado');
        }

        $result = $api->test_connection();

        if ($result) {
            wp_send_json_success('Conexão estabelecida com sucesso');
        } else {
            wp_send_json_error('Falha ao conectar com a API do Tiny');
        }
    }

    /**
     * AJAX: Limpa logs
     */
    public function ajax_clear_logs() {
        check_ajax_referer('tiny_woo_sync_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }

        $logger = Tiny_WooCommerce_Logger::get_instance();
        $logger->clear_all_logs();

        wp_send_json_success('Logs limpos com sucesso');
    }

    /**
     * AJAX: Exclui logs por nível
     */
    public function ajax_delete_logs_by_level() {
        check_ajax_referer('tiny_woo_sync_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }

        $level = isset($_POST['level']) ? sanitize_text_field($_POST['level']) : '';
        if (empty($level)) {
            wp_send_json_error('Nível não especificado');
        }

        $logger = Tiny_WooCommerce_Logger::get_instance();
        $deleted = $logger->delete_logs_by_level($level);

        if ($deleted === false) {
            wp_send_json_error('Nível inválido');
        }

        wp_send_json_success(array(
            'message' => sprintf('%d log(s) excluído(s) com sucesso', $deleted),
            'deleted' => $deleted
        ));
    }

    /**
     * AJAX: Envia relatório de teste
     */
    public function ajax_send_test_report() {
        check_ajax_referer('tiny_woo_sync_nonce', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Permissão negada');
        }

        try {
            $report = Tiny_WooCommerce_Sync_Report::get_instance();
            $sent = $report->send_report(true);

            if ($sent) {
                wp_send_json_success('Relatório de teste enviado. Verifique o e-mail configurado.');
            }
            wp_send_json_error('Falha ao enviar e-mail. Verifique o destinatário e a configuração de e-mail do servidor.');
        } catch (Exception $e) {
            wp_send_json_error('Erro: ' . $e->getMessage());
        }
    }
}
