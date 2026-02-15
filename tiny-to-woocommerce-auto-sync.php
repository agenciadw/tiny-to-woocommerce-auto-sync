<?php
/**
 * Plugin Name: DW Atualiza Produtos for Tiny ERP
 * Plugin URI: http://github.com/agenciadw/tiny-to-woocommerce-auto-sync
 * Description: Sincroniza automaticamente produtos do Tiny ERP para WooCommerce via API, atualizando preços, estoque, peso e dimensões.
 * Version: 0.2.0
 * Author: David William da Costa
 * Author URI: https://github.com/agenciadw/
 * Text Domain: tiny-woo-sync
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.5
 */

if (!defined('ABSPATH')) {
    exit;
}

// Constantes do plugin
define('TINY_WOO_SYNC_VERSION', '0.2.0');
define('TINY_WOO_SYNC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TINY_WOO_SYNC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TINY_WOO_SYNC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Declara compatibilidade com HPOS (High-Performance Order Storage) do WooCommerce
 */
add_action('before_woocommerce_init', function() {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

/**
 * Classe principal do plugin
 */
class Tiny_WooCommerce_Auto_Sync {

    private static $instance = null;

    /**
     * Singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor
     */
    private function __construct() {
        $this->includes();
        $this->init_hooks();
    }

    /**
     * Verifica requisitos do plugin (executado após plugins carregados)
     */
    private function check_requirements() {
        if (!class_exists('WooCommerce')) {
            add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
            return false;
        }
        return true;
    }

    /**
     * Aviso de WooCommerce ausente
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php _e('DW Atualiza Produtos for Tiny ERP requer o WooCommerce para funcionar.', 'tiny-woo-sync'); ?></p>
        </div>
        <?php
    }

    /**
     * Inclui arquivos necessários
     */
    private function includes() {
        require_once TINY_WOO_SYNC_PLUGIN_DIR . 'includes/class-logger.php';
        require_once TINY_WOO_SYNC_PLUGIN_DIR . 'includes/class-tiny-api.php';
        require_once TINY_WOO_SYNC_PLUGIN_DIR . 'includes/class-sync-manager.php';
        require_once TINY_WOO_SYNC_PLUGIN_DIR . 'includes/class-settings.php';
        require_once TINY_WOO_SYNC_PLUGIN_DIR . 'admin/class-admin-page.php';
    }

    /**
     * Inicializa hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'), 20);
    }

    /**
     * Inicializa o plugin (executado após WooCommerce carregar)
     */
    public function init() {
        if (!$this->check_requirements()) {
            return;
        }

        // Inicializa classes
        Tiny_WooCommerce_Logger::get_instance();
        Tiny_WooCommerce_Settings::get_instance();
        Tiny_WooCommerce_Sync_Manager::get_instance();
        Tiny_WooCommerce_Admin_Page::get_instance();

        // Carrega tradução
        load_plugin_textdomain('tiny-woo-sync', false, dirname(TINY_WOO_SYNC_PLUGIN_BASENAME) . '/languages');
    }

    /**
     * Ativação do plugin
     */
    public static function activate() {
        // Cria tabela de logs
        global $wpdb;
        $table_name = $wpdb->prefix . 'tiny_woo_sync_logs';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            log_level varchar(20) NOT NULL,
            message text NOT NULL,
            context longtext,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY log_level (log_level),
            KEY created_at (created_at)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Define opções padrão
        if (!get_option('tiny_woo_sync_settings')) {
            add_option('tiny_woo_sync_settings', array(
                'api_token' => '',
                'sync_enabled' => false,
                'sync_mode' => 'woocommerce',
                'sync_interval' => 'hourly',
                'batch_size' => 30,
                'delay_between_requests' => 1.5,
                'log_retention_days' => 30
            ));
        }

        // Agenda cron usando o intervalo salvo nas configurações (ou hourly como padrão)
        $settings = get_option('tiny_woo_sync_settings');
        $interval = (is_array($settings) && isset($settings['sync_interval'])) ? $settings['sync_interval'] : 'hourly';
        $sync_enabled = is_array($settings) && !empty($settings['sync_enabled']);
        if (!wp_next_scheduled('tiny_woo_sync_cron') && $sync_enabled) {
            wp_schedule_event(time(), $interval, 'tiny_woo_sync_cron');
        }
    }

    /**
     * Desativação do plugin
     */
    public static function deactivate() {
        // Remove cron
        $timestamp = wp_next_scheduled('tiny_woo_sync_cron');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'tiny_woo_sync_cron');
        }
    }
}

// Registra hooks de ativação/desativação (devem ser registrados no carregamento do arquivo)
register_activation_hook(__FILE__, array('Tiny_WooCommerce_Auto_Sync', 'activate'));
register_deactivation_hook(__FILE__, array('Tiny_WooCommerce_Auto_Sync', 'deactivate'));

// Inicializa o plugin após todos os plugins carregarem (prioridade 20 para garantir que WooCommerce já carregou)
add_action('plugins_loaded', function() {
    Tiny_WooCommerce_Auto_Sync::get_instance();
}, 20);

/**
 * Adiciona intervalos customizados ao WP-Cron
 */
add_filter('cron_schedules', function($schedules) {
    $schedules['every_15_minutes'] = array(
        'interval' => 900,
        'display' => __('A cada 15 minutos', 'tiny-woo-sync')
    );

    $schedules['every_30_minutes'] = array(
        'interval' => 1800,
        'display' => __('A cada 30 minutos', 'tiny-woo-sync')
    );

    return $schedules;
});
