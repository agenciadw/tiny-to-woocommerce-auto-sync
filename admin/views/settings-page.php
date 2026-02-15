<?php
if (!defined('ABSPATH')) {
    exit;
}

$settings = get_option('tiny_woo_sync_settings');
$api_token = isset($settings['api_token']) ? $settings['api_token'] : '';
$sync_enabled = isset($settings['sync_enabled']) ? $settings['sync_enabled'] : false;
$sync_mode = isset($settings['sync_mode']) ? $settings['sync_mode'] : 'woocommerce';
$sync_interval = isset($settings['sync_interval']) ? $settings['sync_interval'] : 'hourly';
$batch_size = isset($settings['batch_size']) ? $settings['batch_size'] : 30;
$delay = isset($settings['delay_between_requests']) ? $settings['delay_between_requests'] : 1.5;
$log_retention = isset($settings['log_retention_days']) ? $settings['log_retention_days'] : 30;

// Próxima execução do cron (wp_date usa o timezone do WordPress)
$next_sync = wp_next_scheduled('tiny_woo_sync_cron');
$next_sync_formatted = $next_sync ? wp_date('d/m/Y H:i:s', $next_sync) : 'Não agendado';

// Estado da rotação
$rotation_state = Tiny_WooCommerce_Sync_Manager::get_rotation_state();
$rotation_page = isset($rotation_state['page']) ? (int) $rotation_state['page'] : 1;
$rotation_offset = isset($rotation_state['offset']) ? (int) $rotation_state['offset'] : 0;
$rotation_mode = isset($rotation_state['mode']) ? $rotation_state['mode'] : $sync_mode;
?>

<div class="wrap">
    <h1>DW Atualiza Produtos for Tiny ERP - Configurações</h1>

    <?php settings_errors(); ?>

    <div class="tiny-woo-sync-container">
        <div class="tiny-woo-sync-main">
            <form method="post" action="options.php">
                <?php settings_fields('tiny_woo_sync_settings_group'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="api_token">Token da API Tiny</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="api_token" 
                                   name="tiny_woo_sync_settings[api_token]" 
                                   value="<?php echo esc_attr($api_token); ?>" 
                                   class="regular-text"
                                   placeholder="Digite o token da API">
                            <p class="description">
                                Token de acesso à API do Tiny ERP. 
                                <a href="https://tiny.com.br/ajuda/api/api2-gerar-token-api" target="_blank">Como obter o token?</a>
                            </p>
                            <button type="button" 
                                    class="button" 
                                    id="test-connection">
                                Testar Conexão
                            </button>
                            <span id="connection-status"></span>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sync_enabled">Sincronização Automática</label>
                        </th>
                        <td>
                            <label>
                                <input type="checkbox" 
                                       id="sync_enabled" 
                                       name="tiny_woo_sync_settings[sync_enabled]" 
                                       value="1" 
                                       <?php checked($sync_enabled, true); ?>>
                                Ativar sincronização automática
                            </label>
                            <p class="description">
                                Quando ativado, produtos serão sincronizados automaticamente no intervalo definido.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sync_mode">Modo de Sincronização</label>
                        </th>
                        <td>
                            <select id="sync_mode" name="tiny_woo_sync_settings[sync_mode]">
                                <option value="woocommerce" <?php selected($sync_mode, 'woocommerce'); ?>>
                                    Apenas produtos do WooCommerce (recomendado)
                                </option>
                                <option value="tiny" <?php selected($sync_mode, 'tiny'); ?>>
                                    Catálogo completo do Tiny
                                </option>
                            </select>
                            <p class="description">
                                <strong>Apenas produtos do WooCommerce:</strong> Processa somente os produtos que existem na sua loja. 
                                Ideal quando o Tiny tem muito mais produtos (ex: 7300) que o WooCommerce (ex: 1707) — agiliza a sincronização e reduz carga na API.
                            </p>
                            <p class="description">
                                <strong>Catálogo completo do Tiny:</strong> Percorre todos os produtos do Tiny em rotação de páginas.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="sync_interval">Intervalo de Sincronização</label>
                        </th>
                        <td>
                            <select id="sync_interval" name="tiny_woo_sync_settings[sync_interval]">
                                <option value="every_15_minutes" <?php selected($sync_interval, 'every_15_minutes'); ?>>A cada 15 minutos</option>
                                <option value="every_30_minutes" <?php selected($sync_interval, 'every_30_minutes'); ?>>A cada 30 minutos</option>
                                <option value="hourly" <?php selected($sync_interval, 'hourly'); ?>>A cada hora</option>
                                <option value="twicedaily" <?php selected($sync_interval, 'twicedaily'); ?>>Duas vezes ao dia</option>
                                <option value="daily" <?php selected($sync_interval, 'daily'); ?>>Uma vez ao dia</option>
                            </select>
                            <p class="description">
                                Próxima sincronização: <strong><?php echo $next_sync_formatted; ?></strong>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="batch_size">Produtos por Lote</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="batch_size" 
                                   name="tiny_woo_sync_settings[batch_size]" 
                                   value="<?php echo esc_attr($batch_size); ?>" 
                                   min="20" 
                                   max="100" 
                                   step="1">
                            <p class="description">
                                Quantidade de produtos sincronizados por execução (entre 20 e 100). Lojas com muitos produtos: use "Sincronizar Produto por SKU" para atualizar um item específico rapidamente.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="delay">Delay entre Requisições (segundos)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="delay" 
                                   name="tiny_woo_sync_settings[delay_between_requests]" 
                                   value="<?php echo esc_attr($delay); ?>" 
                                   min="0.5" 
                                   max="5" 
                                   step="0.1">
                            <p class="description">
                                Tempo de espera entre cada requisição à API (previne bloqueio). Se receber "API Bloqueada", aumente para 2-3 segundos.
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="log_retention">Retenção de Logs (dias)</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="log_retention" 
                                   name="tiny_woo_sync_settings[log_retention_days]" 
                                   value="<?php echo esc_attr($log_retention); ?>" 
                                   min="1" 
                                   max="365" 
                                   step="1">
                            <p class="description">
                                Logs mais antigos que este período serão removidos automaticamente.
                            </p>
                        </td>
                    </tr>
                </table>

                <?php submit_button('Salvar Configurações'); ?>
            </form>

            <hr>

            <h2>Sincronização Manual</h2>
            <p>Execute uma sincronização imediata para testar ou atualizar produtos agora.</p>
            <p class="description" style="margin-bottom: 15px;">
                <?php if ($rotation_mode === 'woocommerce'): ?>
                    <strong>Modo WooCommerce:</strong> Processa apenas os <?php echo esc_html($batch_size); ?> produtos da loja por execução. 
                    Próxima execução: <strong>offset <?php echo esc_html($rotation_offset); ?></strong>.
                <?php else: ?>
                    <strong>Rotação de páginas (modo Tiny):</strong> A cada execução, processa uma página do Tiny (<?php echo esc_html($batch_size); ?> produtos por vez). 
                    Próxima execução: <strong>página <?php echo esc_html($rotation_page); ?></strong><?php echo $rotation_offset > 0 ? ' (offset ' . esc_html($rotation_offset) . ')' : ''; ?>.
                <?php endif; ?>
            </p>
            <p class="description" style="margin-bottom: 15px;">
                Para atualizar um produto específico rapidamente, use a opção "Sincronizar Produto por SKU" abaixo.
            </p>
            <button type="button" class="button button-secondary" id="reset-rotation" title="Reiniciar do início (página 1)">
                Reiniciar Rotação
            </button>
            <span id="reset-rotation-status"></span>
            <br><br>
            <button type="button" class="button button-primary" id="manual-sync">
                Executar Sincronização Agora
            </button>
            <span id="sync-status"></span>

            <hr style="margin: 25px 0;">

            <h2>Sincronizar Produto por SKU</h2>
            <p>Atualize um produto específico imediatamente, sem esperar a sincronização em lote.</p>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="sync-sku">SKU do Produto</label>
                    </th>
                    <td>
                        <input type="text" 
                               id="sync-sku" 
                               class="regular-text" 
                               placeholder="Ex: 17896300505520"
                               style="max-width: 250px;">
                        <button type="button" class="button" id="sync-product-by-sku">
                            Sincronizar Este Produto
                        </button>
                        <span id="sync-sku-status"></span>
                        <p class="description">
                            Digite o SKU do produto e clique para sincronizar apenas esse item do Tiny para o WooCommerce.
                        </p>
                    </td>
                </tr>
            </table>
        </div>

        <div class="tiny-woo-sync-sidebar">
            <div class="tiny-woo-sync-box">
                <h3>Dados Sincronizados</h3>
                <ul>
                    <li>✓ Preço de venda</li>
                    <li>✓ Preço promocional</li>
                    <li>✓ Quantidade em estoque</li>
                    <li>✓ Peso bruto</li>
                    <li>✓ Largura</li>
                    <li>✓ Altura</li>
                    <li>✓ Comprimento</li>
                </ul>
            </div>

            <div class="tiny-woo-sync-box">
                <h3>Como Funciona</h3>
                <ol>
                    <li>Configure o token da API</li>
                    <li>Ative a sincronização automática</li>
                    <li>Produtos serão atualizados automaticamente</li>
                    <li>Acompanhe os logs para verificar o status</li>
                </ol>
            </div>

            <div class="tiny-woo-sync-box">
                <h3>Suporte</h3>
                <p>Desenvolvido por <strong>David William da Costa</strong></p>
                <p>Versão: <?php echo TINY_WOO_SYNC_VERSION; ?></p>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Teste de conexão
    $('#test-connection').on('click', function() {
        var button = $(this);
        var status = $('#connection-status');

        button.prop('disabled', true).text('Testando...');
        status.html('');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tiny_woo_test_connection',
                nonce: '<?php echo wp_create_nonce('tiny_woo_sync_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    status.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    status.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                status.html('<span style="color: red;">✗ Erro ao testar conexão</span>');
            },
            complete: function() {
                button.prop('disabled', false).text('Testar Conexão');
            }
        });
    });

    // Reiniciar rotação de páginas
    $('#reset-rotation').on('click', function() {
        var button = $(this);
        var status = $('#reset-rotation-status');

        button.prop('disabled', true);
        status.html('<span style="color: blue;">⟳ Reiniciando...</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tiny_woo_reset_rotation',
                nonce: '<?php echo wp_create_nonce('tiny_woo_sync_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    status.html('<span style="color: green;">✓ ' + response.data + '</span>');
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    status.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                status.html('<span style="color: red;">✗ Erro ao reiniciar</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });

    // Sincronização manual
    $('#manual-sync').on('click', function() {
        var button = $(this);
        var status = $('#sync-status');

        if (!confirm('Deseja executar a sincronização agora?')) {
            return;
        }

        button.prop('disabled', true).text('Sincronizando...');
        status.html('<span style="color: blue;">⟳ Processando...</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tiny_woo_manual_sync',
                nonce: '<?php echo wp_create_nonce('tiny_woo_sync_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    status.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    status.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                status.html('<span style="color: red;">✗ Erro ao executar sincronização</span>');
            },
            complete: function() {
                button.prop('disabled', false).text('Executar Sincronização Agora');
            }
        });
    });

    // Sincronizar produto por SKU
    $('#sync-product-by-sku').on('click', function() {
        var button = $(this);
        var skuInput = $('#sync-sku');
        var status = $('#sync-sku-status');
        var sku = skuInput.val().trim();

        if (!sku) {
            status.html('<span style="color: orange;">⚠ Informe o SKU do produto.</span>');
            return;
        }

        button.prop('disabled', true);
        status.html('<span style="color: blue;">⟳ Sincronizando...</span>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tiny_woo_sync_product_by_sku',
                nonce: '<?php echo wp_create_nonce('tiny_woo_sync_nonce'); ?>',
                sku: sku
            },
            success: function(response) {
                if (response.success) {
                    status.html('<span style="color: green;">✓ ' + response.data + '</span>');
                } else {
                    status.html('<span style="color: red;">✗ ' + response.data + '</span>');
                }
            },
            error: function() {
                status.html('<span style="color: red;">✗ Erro ao sincronizar produto</span>');
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
});
</script>
