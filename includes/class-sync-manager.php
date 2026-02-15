<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Gerenciador de Sincronização
 */
class Tiny_WooCommerce_Sync_Manager {

    private static $instance = null;
    private $logger;
    private $api;
    private $settings;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->logger = Tiny_WooCommerce_Logger::get_instance();
        $this->settings = get_option('tiny_woo_sync_settings');
        $this->api = new Tiny_WooCommerce_API();

        // Hook do cron
        add_action('tiny_woo_sync_cron', array($this, 'run_sync'));
    }

    /**
     * Executa sincronização
     */
    public function run_sync() {
        // Verifica se sincronização está habilitada
        if (empty($this->settings['sync_enabled'])) {
            return;
        }

        if (!$this->api->is_token_valid()) {
            $this->logger->error('Sincronização cancelada: token da API não configurado');
            return;
        }

        $this->logger->info('Iniciando sincronização automática');

        $sync_mode = isset($this->settings['sync_mode']) ? $this->settings['sync_mode'] : 'woocommerce';

        if ($sync_mode === 'woocommerce') {
            $this->run_sync_woocommerce_driven();
        } else {
            $this->run_sync_tiny_driven();
        }
    }

    /**
     * Sincronização orientada pelo WooCommerce - processa apenas produtos que existem na loja
     * Ideal quando o Tiny tem muito mais produtos que o WooCommerce (ex: 7300 vs 1707)
     */
    private function run_sync_woocommerce_driven() {
        $start_time = microtime(true);
        $batch_size = isset($this->settings['batch_size']) ? intval($this->settings['batch_size']) : 30;
        $delay = isset($this->settings['delay_between_requests']) ? floatval($this->settings['delay_between_requests']) : 1.5;

        $rotation_state = get_option('tiny_woo_sync_rotation_state_wc', array('offset' => 0));
        $current_offset = isset($rotation_state['offset']) ? (int) $rotation_state['offset'] : 0;

        $wc_product_ids = $this->get_woocommerce_products_with_sku($batch_size, $current_offset);
        $total_wc_with_sku = $this->count_woocommerce_products_with_sku();

        if (empty($wc_product_ids)) {
            $this->logger->info('Nenhum produto com SKU no WooCommerce para sincronizar');
            update_option('tiny_woo_sync_rotation_state_wc', array('offset' => 0));
            return;
        }

        $this->logger->info(sprintf(
            'Modo WooCommerce: %d produto(s) a processar (offset %d de %d)',
            count($wc_product_ids),
            $current_offset,
            $total_wc_with_sku
        ));

        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;

        foreach ($wc_product_ids as $wc_product_id) {
            $product = wc_get_product($wc_product_id);
            if (!$product || !$product->get_sku()) {
                $skipped_count++;
                continue;
            }

            $sku = $product->get_sku();
            $product_details = $this->api->get_product_by_sku($sku);

            if (!$product_details) {
                $this->logger->warning('Produto não encontrado no Tiny', array('sku' => $sku));
                $error_count++;
                sleep($delay);
                continue;
            }

            $result = $this->update_woocommerce_product($wc_product_id, $product_details);

            if ($result === true) {
                $updated_count++;
            } elseif ($result === false) {
                $error_count++;
            } else {
                $skipped_count++;
            }

            sleep($delay);
        }

        $next_offset = $current_offset + count($wc_product_ids);
        if ($next_offset >= $total_wc_with_sku) {
            $next_offset = 0;
        }
        update_option('tiny_woo_sync_rotation_state_wc', array('offset' => $next_offset));

        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);

        $this->logger->info('Sincronização concluída', array(
            'duration' => $duration . 's',
            'updated' => $updated_count,
            'errors' => $error_count,
            'skipped' => $skipped_count,
            'mode' => 'woocommerce',
            'next_offset' => $next_offset,
            'total' => $total_wc_with_sku
        ));
    }

    /**
     * Sincronização orientada pelo Tiny - percorre catálogo do Tiny com rotação de páginas
     */
    private function run_sync_tiny_driven() {
        $start_time = microtime(true);
        $batch_size = isset($this->settings['batch_size']) ? intval($this->settings['batch_size']) : 30;
        $delay = isset($this->settings['delay_between_requests']) ? floatval($this->settings['delay_between_requests']) : 1.5;

        $rotation_state = get_option('tiny_woo_sync_rotation_state', array('page' => 1, 'offset' => 0));
        $current_page = isset($rotation_state['page']) ? (int) $rotation_state['page'] : 1;
        $current_offset = isset($rotation_state['offset']) ? (int) $rotation_state['offset'] : 0;

        $result = $this->api->list_products_with_pagination($current_page);
        $tiny_products = $result['products'];
        $total_pages = $result['total_pages'];

        if (empty($tiny_products)) {
            $this->logger->warning('Nenhum produto encontrado no Tiny');
            return;
        }

        $tiny_products = array_slice($tiny_products, $current_offset, $batch_size);
        $processed_count = count($tiny_products);

        $this->logger->info(sprintf(
            'Modo Tiny - Página %d/%d (offset %d) - %d produto(s) a processar',
            $current_page,
            $total_pages,
            $current_offset,
            $processed_count
        ));

        $next_offset = $current_offset + $processed_count;
        $products_in_page = count($result['products']);

        if ($next_offset >= $products_in_page || $processed_count === 0) {
            $next_page = ($current_page >= $total_pages) ? 1 : $current_page + 1;
            $next_offset = 0;
        } else {
            $next_page = $current_page;
        }

        update_option('tiny_woo_sync_rotation_state', array('page' => $next_page, 'offset' => $next_offset));

        $updated_count = 0;
        $error_count = 0;
        $skipped_count = 0;

        foreach ($tiny_products as $tiny_product_data) {
            $tiny_product = $tiny_product_data['produto'];

            if (empty($tiny_product['codigo'])) {
                $skipped_count++;
                continue;
            }

            $sku = $tiny_product['codigo'];
            $wc_product_id = wc_get_product_id_by_sku($sku);

            if (!$wc_product_id) {
                $skipped_count++;
                continue;
            }

            $product_details = $this->api->get_product($tiny_product['id']);

            if (!$product_details) {
                $this->logger->error('Erro ao obter detalhes do produto', array('sku' => $sku));
                $error_count++;
                sleep($delay);
                continue;
            }

            $result = $this->update_woocommerce_product($wc_product_id, $product_details);

            if ($result === true) {
                $updated_count++;
            } elseif ($result === false) {
                $error_count++;
            } else {
                $skipped_count++;
            }

            sleep($delay);
        }

        $end_time = microtime(true);
        $duration = round($end_time - $start_time, 2);

        $this->logger->info('Sincronização concluída', array(
            'duration' => $duration . 's',
            'updated' => $updated_count,
            'errors' => $error_count,
            'skipped' => $skipped_count,
            'page' => $current_page,
            'next_page' => $next_page,
            'total_pages' => $total_pages
        ));
    }

    /**
     * Compara dois valores numéricos (considera precisão de float)
     */
    private function values_differ($old, $new) {
        if ($old === $new) {
            return false;
        }
        if (is_numeric($old) && is_numeric($new)) {
            return abs(floatval($old) - floatval($new)) > 0.0001;
        }
        return (string) $old !== (string) $new;
    }

    /**
     * Atualiza produto no WooCommerce
     * @return true se houve alterações, false em erro, 'no_changes' se nada mudou
     */
    private function update_woocommerce_product($product_id, $tiny_product_data) {
        try {
            $product = wc_get_product($product_id);

            if (!$product) {
                $this->logger->error("Produto WooCommerce não encontrado", array('id' => $product_id));
                return false;
            }

            $sku = $product->get_sku();
            $product_name = $product->get_name();
            $before_after = array();

            // Preço de venda - só atualiza se mudou
            if (isset($tiny_product_data['preco'])) {
                $old_price = $product->get_regular_price();
                $price = floatval(str_replace(',', '.', $tiny_product_data['preco']));
                if ($this->values_differ($old_price, $price)) {
                    $product->set_regular_price($price);
                    $before_after['price'] = array('before' => $old_price, 'after' => $price);
                }
            }

            // Preço promocional
            if (isset($tiny_product_data['preco_promocional']) && !empty($tiny_product_data['preco_promocional'])) {
                $sale_price = floatval(str_replace(',', '.', $tiny_product_data['preco_promocional']));
                if ($sale_price > 0) {
                    $old_sale = $product->get_sale_price();
                    if ($this->values_differ($old_sale, $sale_price)) {
                        $product->set_sale_price($sale_price);
                        $before_after['sale_price'] = array('before' => $old_sale, 'after' => $sale_price);
                    }
                }
            } else {
                $old_sale = $product->get_sale_price();
                if ($old_sale !== '' && $old_sale !== null) {
                    $product->set_sale_price('');
                    $before_after['sale_price'] = array('before' => $old_sale, 'after' => '');
                }
            }

            // Estoque
            if (isset($tiny_product_data['saldo'])) {
                $old_stock = $product->get_stock_quantity();
                $stock = floatval($tiny_product_data['saldo']);
                if ($this->values_differ($old_stock, $stock)) {
                    $product->set_stock_quantity($stock);
                    $product->set_manage_stock(true);
                    if ($stock > 0) {
                        $product->set_stock_status('instock');
                    } else {
                        $product->set_stock_status('outofstock');
                    }
                    $before_after['stock'] = array('before' => $old_stock, 'after' => $stock);
                }
            }

            // Peso
            if (isset($tiny_product_data['peso_bruto'])) {
                $old_weight = $product->get_weight();
                $weight = floatval(str_replace(',', '.', $tiny_product_data['peso_bruto']));
                if ($this->values_differ($old_weight, $weight)) {
                    $product->set_weight($weight);
                    $before_after['weight'] = array('before' => $old_weight, 'after' => $weight);
                }
            }

            // Dimensões
            $width_value = $tiny_product_data['larguraEmbalagem'] ?? $tiny_product_data['largura'] ?? null;
            if ($width_value !== null && $width_value !== '') {
                $old_width = $product->get_width();
                $width = floatval(str_replace(',', '.', $width_value));
                if ($this->values_differ($old_width, $width)) {
                    $product->set_width($width);
                    $before_after['width'] = array('before' => $old_width, 'after' => $width);
                }
            }

            $height_value = $tiny_product_data['alturaEmbalagem'] ?? $tiny_product_data['altura'] ?? null;
            if ($height_value !== null && $height_value !== '') {
                $old_height = $product->get_height();
                $height = floatval(str_replace(',', '.', $height_value));
                if ($this->values_differ($old_height, $height)) {
                    $product->set_height($height);
                    $before_after['height'] = array('before' => $old_height, 'after' => $height);
                }
            }

            $length_value = $tiny_product_data['comprimentoEmbalagem'] ?? $tiny_product_data['comprimento'] ?? null;
            if ($length_value !== null && $length_value !== '') {
                $old_length = $product->get_length();
                $length = floatval(str_replace(',', '.', $length_value));
                if ($this->values_differ($old_length, $length)) {
                    $product->set_length($length);
                    $before_after['length'] = array('before' => $old_length, 'after' => $length);
                }
            }

            // Nenhuma alteração - não salva e não registra log
            if (empty($before_after)) {
                return 'no_changes';
            }

            $product->save();

            $log_context = array(
                'product_name' => $product_name,
                'sku' => $sku,
                'wc_id' => $product_id,
                'changes' => $before_after
            );

            $this->logger->info(
                sprintf('O produto %s, SKU %s, foi atualizado com sucesso', $product_name, $sku),
                $log_context
            );

            return true;

        } catch (Exception $e) {
            $this->logger->error("Erro ao atualizar produto", array(
                'product_id' => $product_id,
                'error' => $e->getMessage()
            ));
            return false;
        }
    }

    /**
     * Sincronização manual
     */
    public function manual_sync() {
        $this->logger->info('Sincronização manual iniciada');
        $this->run_sync();
    }

    /**
     * Obtém IDs de produtos WooCommerce que possuem SKU (para modo woocommerce)
     */
    private function get_woocommerce_products_with_sku($limit, $offset) {
        global $wpdb;

        return $wpdb->get_col($wpdb->prepare(
            "SELECT p.ID FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku' AND pm.meta_value != '' AND pm.meta_value IS NOT NULL
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'
            ORDER BY p.ID ASC
            LIMIT %d OFFSET %d",
            $limit,
            $offset
        ));
    }

    /**
     * Conta total de produtos WooCommerce com SKU
     */
    private function count_woocommerce_products_with_sku() {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_sku' AND pm.meta_value != '' AND pm.meta_value IS NOT NULL
            WHERE p.post_type IN ('product', 'product_variation')
            AND p.post_status = 'publish'"
        );
    }

    /**
     * Obtém o estado atual da rotação
     * @return array ['page' => int, 'offset' => int, 'mode' => string]
     */
    public static function get_rotation_state() {
        $settings = get_option('tiny_woo_sync_settings');
        $mode = isset($settings['sync_mode']) ? $settings['sync_mode'] : 'woocommerce';

        if ($mode === 'woocommerce') {
            $state = get_option('tiny_woo_sync_rotation_state_wc', array('offset' => 0));
            $state['mode'] = 'woocommerce';
            return $state;
        }

        $state = get_option('tiny_woo_sync_rotation_state', array('page' => 1, 'offset' => 0));
        $state['mode'] = 'tiny';
        return $state;
    }

    /**
     * Reseta a rotação para o início
     */
    public static function reset_rotation() {
        update_option('tiny_woo_sync_rotation_state', array('page' => 1, 'offset' => 0));
        update_option('tiny_woo_sync_rotation_state_wc', array('offset' => 0));
    }

    /**
     * Sincroniza um produto específico por SKU
     * Útil para lojas com muitos produtos, onde a sincronização em lote pode demorar para alcançar um produto
     *
     * @param string $sku Código SKU do produto
     * @return array ['success' => bool, 'message' => string]
     */
    public function sync_product_by_sku($sku) {
        $sku = trim($sku);
        if (empty($sku)) {
            return array('success' => false, 'message' => 'Informe o SKU do produto.');
        }

        if (!$this->api->is_token_valid()) {
            return array('success' => false, 'message' => 'Token da API não configurado.');
        }

        // Verifica se produto existe no WooCommerce
        $wc_product_id = wc_get_product_id_by_sku($sku);
        if (!$wc_product_id) {
            $this->logger->warning('Produto não encontrado no WooCommerce', array('sku' => $sku));
            return array('success' => false, 'message' => 'Produto com SKU ' . esc_html($sku) . ' não encontrado no WooCommerce.');
        }

        // Busca produto no Tiny por SKU
        $product_details = $this->api->get_product_by_sku($sku);
        if (!$product_details) {
            $this->logger->error('Produto não encontrado no Tiny', array('sku' => $sku));
            return array('success' => false, 'message' => 'Produto com SKU ' . esc_html($sku) . ' não encontrado no Tiny ERP.');
        }

        $result = $this->update_woocommerce_product($wc_product_id, $product_details);

        if ($result === true) {
            $product = wc_get_product($wc_product_id);
            $name = $product ? $product->get_name() : $sku;
            $this->logger->info('Sincronização manual por SKU concluída', array('sku' => $sku, 'product_name' => $name));
            return array('success' => true, 'message' => 'Produto "' . esc_html($name) . '" atualizado com sucesso!');
        }

        if ($result === 'no_changes') {
            return array('success' => true, 'message' => 'Produto já está atualizado (nenhuma alteração necessária).');
        }

        return array('success' => false, 'message' => 'Erro ao atualizar o produto.');
    }
}
