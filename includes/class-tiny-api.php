<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe para comunicação com API do Tiny
 */
class Tiny_WooCommerce_API {

    private $api_token;
    private $base_url = 'https://api.tiny.com.br/api2/';
    private $logger;

    public function __construct($api_token = '') {
        $this->logger = Tiny_WooCommerce_Logger::get_instance();

        if (empty($api_token)) {
            $settings = get_option('tiny_woo_sync_settings');
            $api_token = isset($settings['api_token']) ? $settings['api_token'] : '';
        }

        $this->api_token = $api_token;
    }

    /**
     * Valida se o token está configurado
     */
    public function is_token_valid() {
        return !empty($this->api_token);
    }

    /**
     * Faz requisição para API do Tiny
     */
    private function request($endpoint, $params = array()) {
        if (!$this->is_token_valid()) {
            $this->logger->error('Token da API do Tiny não configurado');
            return false;
        }

        $params['token'] = $this->api_token;
        $params['formato'] = 'json';

        $url = $this->base_url . $endpoint;

        $response = wp_remote_post($url, array(
            'timeout' => 30,
            'body' => $params
        ));

        if (is_wp_error($response)) {
            $this->logger->error('Erro na requisição à API do Tiny', array(
                'endpoint' => $endpoint,
                'error' => $response->get_error_message()
            ));
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Verifica se houve erro na resposta
        if (isset($data['retorno']['status']) && $data['retorno']['status'] === 'Erro') {
            $error_message = isset($data['retorno']['erros'][0]['erro']) 
                ? $data['retorno']['erros'][0]['erro'] 
                : 'Erro desconhecido';

            // Inclui a mensagem de erro no log principal para visibilidade (não só no contexto)
            $this->logger->error('Erro retornado pela API do Tiny: ' . $error_message, array(
                'endpoint' => $endpoint,
                'error' => $error_message
            ));

            return false;
        }

        return $data;
    }

    /**
     * Lista produtos do Tiny (compatibilidade - retorna apenas produtos)
     */
    public function list_products($page = 1) {
        $result = $this->list_products_with_pagination($page);
        return $result['products'];
    }

    /**
     * Lista produtos do Tiny com informações de paginação
     * @param int $page Número da página
     * @return array ['products' => array, 'page' => int, 'total_pages' => int]
     */
    public function list_products_with_pagination($page = 1) {
        $params = array(
            'pagina' => max(1, intval($page)),
            'situacao' => 'A' // Apenas produtos ativos
        );

        $response = $this->request('produtos.pesquisa.php', $params);

        if (!$response || !isset($response['retorno']['produtos'])) {
            return array(
                'products' => array(),
                'page' => 1,
                'total_pages' => 1
            );
        }

        return array(
            'products' => $response['retorno']['produtos'],
            'page' => isset($response['retorno']['pagina']) ? (int) $response['retorno']['pagina'] : 1,
            'total_pages' => isset($response['retorno']['numero_paginas']) ? (int) $response['retorno']['numero_paginas'] : 1
        );
    }

    /**
     * Obtém detalhes de um produto específico
     */
    public function get_product($product_id) {
        $params = array(
            'id' => $product_id
        );

        $response = $this->request('produto.obter.php', $params);

        if (!$response || !isset($response['retorno']['produto'])) {
            return null;
        }

        return $response['retorno']['produto'];
    }

    /**
     * Obtém produto por SKU
     */
    public function get_product_by_sku($sku) {
        $params = array(
            'pesquisa' => $sku
        );

        $response = $this->request('produtos.pesquisa.php', $params);

        if (!$response || !isset($response['retorno']['produtos'])) {
            return null;
        }

        $products = $response['retorno']['produtos'];

        // Procura produto com SKU exato
        foreach ($products as $product_data) {
            $product = $product_data['produto'];
            if (isset($product['codigo']) && $product['codigo'] === $sku) {
                return $this->get_product($product['id']);
            }
        }

        return null;
    }

    /**
     * Testa conexão com a API
     */
    public function test_connection() {
        $response = $this->request('info.php');

        if ($response && isset($response['retorno']['status'])) {
            return $response['retorno']['status'] === 'OK';
        }

        return false;
    }
}
