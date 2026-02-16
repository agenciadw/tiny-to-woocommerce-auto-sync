<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe de Logger
 */
class Tiny_WooCommerce_Logger {

    private static $instance = null;
    private $table_name;

    const LEVEL_INFO = 'INFO';
    const LEVEL_WARNING = 'WARNING';
    const LEVEL_ERROR = 'ERROR';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'tiny_woo_sync_logs';

        // Hook para limpeza automática de logs
        add_action('tiny_woo_sync_cleanup_logs', array($this, 'cleanup_old_logs'));

        // Agenda limpeza diária se não estiver agendada
        if (!wp_next_scheduled('tiny_woo_sync_cleanup_logs')) {
            wp_schedule_event(time(), 'daily', 'tiny_woo_sync_cleanup_logs');
        }
    }

    /**
     * Registra log de informação
     */
    public function info($message, $context = array()) {
        $this->log(self::LEVEL_INFO, $message, $context);
    }

    /**
     * Registra log de aviso
     */
    public function warning($message, $context = array()) {
        $this->log(self::LEVEL_WARNING, $message, $context);
    }

    /**
     * Registra log de erro
     */
    public function error($message, $context = array()) {
        $this->log(self::LEVEL_ERROR, $message, $context);
    }

    /**
     * Registra log no banco de dados
     */
    private function log($level, $message, $context = array()) {
        global $wpdb;

        $wpdb->insert(
            $this->table_name,
            array(
                'log_level' => $level,
                'message' => $message,
                'context' => json_encode($context, JSON_UNESCAPED_UNICODE),
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%s')
        );
    }

    /**
     * Obtém logs com filtros
     */
    public function get_logs($args = array()) {
        global $wpdb;

        $defaults = array(
            'level' => '',
            'search' => '',
            'limit' => 100,
            'offset' => 0,
            'order' => 'DESC'
        );

        $args = wp_parse_args($args, $defaults);

        $where = '1=1';
        $prepare_values = array();

        if (!empty($args['level'])) {
            $where .= ' AND log_level = %s';
            $prepare_values[] = $args['level'];
        }

        if (!empty($args['search'])) {
            $search_like = '%' . $wpdb->esc_like($args['search']) . '%';
            $where .= ' AND (message LIKE %s OR context LIKE %s)';
            $prepare_values[] = $search_like;
            $prepare_values[] = $search_like;
        }

        $prepare_values[] = $args['limit'];
        $prepare_values[] = $args['offset'];

        $query = "SELECT * FROM {$this->table_name} 
                  WHERE {$where} 
                  ORDER BY created_at {$args['order']} 
                  LIMIT %d OFFSET %d";

        return $wpdb->get_results($wpdb->prepare($query, $prepare_values));
    }

    /**
     * Conta total de logs
     */
    public function count_logs($level = '', $search = '') {
        global $wpdb;

        $where = '1=1';
        $prepare_values = array();

        if (!empty($level)) {
            $where .= ' AND log_level = %s';
            $prepare_values[] = $level;
        }

        if (!empty($search)) {
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $where .= ' AND (message LIKE %s OR context LIKE %s)';
            $prepare_values[] = $search_like;
            $prepare_values[] = $search_like;
        }

        $query = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}";

        if (!empty($prepare_values)) {
            return (int) $wpdb->get_var($wpdb->prepare($query, $prepare_values));
        }

        return (int) $wpdb->get_var($query);
    }

    /**
     * Limpa logs antigos
     */
    public function cleanup_old_logs() {
        global $wpdb;

        $settings = get_option('tiny_woo_sync_settings');
        $retention_days = isset($settings['log_retention_days']) ? intval($settings['log_retention_days']) : 30;

        $date_limit = wp_date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));

        $deleted = $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE created_at < %s",
                $date_limit
            )
        );

        if ($deleted > 0) {
            $this->info("Logs antigos removidos: {$deleted} registros");
        }
    }

    /**
     * Limpa todos os logs
     */
    public function clear_all_logs() {
        global $wpdb;
        return $wpdb->query("TRUNCATE TABLE {$this->table_name}");
    }

    /**
     * Obtém logs de produtos atualizados em um período
     *
     * @param string $date_from Data inicial (Y-m-d H:i:s)
     * @param string $date_to   Data final (Y-m-d H:i:s)
     * @return array Lista de logs com message e created_at
     */
    public function get_updated_products_logs($date_from, $date_to) {
        global $wpdb;

        $query = $wpdb->prepare(
            "SELECT id, message, context, created_at FROM {$this->table_name}
             WHERE log_level = %s
             AND message LIKE %s
             AND created_at >= %s
             AND created_at <= %s
             ORDER BY created_at ASC",
            self::LEVEL_INFO,
            '%foi atualizado com sucesso%',
            $date_from,
            $date_to
        );

        return $wpdb->get_results($query);
    }

    /**
     * Exclui logs por nível (INFO, WARNING, ERROR)
     * @param string $level Nível dos logs a excluir
     * @return int|false Número de linhas excluídas ou false em caso de erro
     */
    public function delete_logs_by_level($level) {
        if (empty($level) || !in_array($level, array(self::LEVEL_INFO, self::LEVEL_WARNING, self::LEVEL_ERROR), true)) {
            return false;
        }

        global $wpdb;
        return $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$this->table_name} WHERE log_level = %s",
                $level
            )
        );
    }
}
