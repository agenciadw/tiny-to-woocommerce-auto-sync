<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Formata número para padrão brasileiro (vírgula decimal)
 */
function tiny_woo_sync_format_br_number($value) {
    if ($value === '' || $value === null) {
        return '-';
    }
    if (is_numeric($value)) {
        return number_format(floatval($value), 2, ',', '.');
    }
    return esc_html($value);
}

/**
 * Formata preço para padrão brasileiro (R$)
 */
function tiny_woo_sync_format_br_price($value) {
    if ($value === '' || $value === null) {
        return '-';
    }
    if (is_numeric($value)) {
        return 'R$ ' . number_format(floatval($value), 2, ',', '.');
    }
    return esc_html($value);
}

/**
 * Formata o contexto do log para exibição legível
 */
function tiny_woo_sync_format_log_context($message, $context) {
    if (empty($context) || !is_array($context)) {
        return '';
    }

    $lines = array();

    // Log de sincronização concluída
    if (strpos($message, 'Sincronização concluída') !== false) {
        if (isset($context['duration'])) {
            $lines[] = 'Duração: ' . esc_html($context['duration']);
        }
        if (isset($context['updated'])) {
            $lines[] = 'Total de produtos atualizados: ' . intval($context['updated']);
        }
        if (isset($context['errors'])) {
            $lines[] = 'Erros: ' . intval($context['errors']);
        }
        if (isset($context['skipped'])) {
            $lines[] = 'Não atualizados: ' . intval($context['skipped']);
        }
        if (isset($context['mode']) && $context['mode'] === 'woocommerce') {
            if (isset($context['next_offset'], $context['total'])) {
                $lines[] = 'Modo: Apenas produtos WooCommerce';
                $lines[] = 'Próxima execução: offset ' . intval($context['next_offset']) . ' de ' . intval($context['total']);
            }
        } else {
            if (isset($context['page'], $context['total_pages'])) {
                $lines[] = 'Página processada: ' . intval($context['page']) . ' de ' . intval($context['total_pages']);
            }
            if (isset($context['next_page'])) {
                $lines[] = 'Próxima execução: página ' . intval($context['next_page']);
            }
        }
        return implode("\n", $lines);
    }

    // Log de produto atualizado (novo formato com nome na mensagem)
    if (strpos($message, 'foi atualizado com sucesso') !== false) {
        if (!empty($context['product_name'])) {
            $lines[] = 'Nome do produto: ' . esc_html($context['product_name']);
        }
        if (!empty($context['sku'])) {
            $lines[] = 'SKU: ' . esc_html($context['sku']);
        }
        if (!empty($context['wc_id'])) {
            $lines[] = 'ID do produto: ' . intval($context['wc_id']);
        }

        $labels = array(
            'price' => 'Preço',
            'sale_price' => 'Preço promocional',
            'stock' => 'Estoque',
            'weight' => 'Peso',
            'width' => 'Largura',
            'height' => 'Altura',
            'length' => 'Comprimento'
        );

        if (!empty($context['changes']) && is_array($context['changes'])) {
            $dimension_fields = array('width', 'height', 'length');
            $has_dimensions = count(array_intersect(array_keys($context['changes']), $dimension_fields)) > 0;

            if ($has_dimensions) {
                $before_parts = array();
                $after_parts = array();
                foreach (array('length', 'width', 'height') as $dim) {
                    if (isset($context['changes'][$dim]['before'], $context['changes'][$dim]['after'])) {
                        $before_parts[] = tiny_woo_sync_format_br_number($context['changes'][$dim]['before']);
                        $after_parts[] = tiny_woo_sync_format_br_number($context['changes'][$dim]['after']);
                    }
                }
                if (!empty($before_parts)) {
                    $lines[] = 'Medidas: ' . implode(' x ', $before_parts) . ' / ' . implode(' x ', $after_parts) . ' cm';
                }
            }

            foreach ($context['changes'] as $field => $values) {
                if (in_array($field, $dimension_fields)) {
                    continue;
                }
                if (isset($values['before'], $values['after'])) {
                    $label = isset($labels[$field]) ? $labels[$field] : $field;
                    if ($field === 'price' || $field === 'sale_price') {
                        $before = tiny_woo_sync_format_br_price($values['before']);
                        $after = tiny_woo_sync_format_br_price($values['after']);
                    } else {
                        $before = tiny_woo_sync_format_br_number($values['before']);
                        $after = tiny_woo_sync_format_br_number($values['after']);
                    }
                    $lines[] = $label . ': ' . $before . ' / ' . $after;
                }
            }
        }

        return implode("\n", $lines);
    }

    // Log de produto atualizado (formato antigo - apenas SKU e ID)
    if (strpos($message, 'Produto atualizado com sucesso') !== false) {
        if (!empty($context['sku'])) {
            $lines[] = 'SKU: ' . esc_html($context['sku']);
        }
        if (!empty($context['wc_id'])) {
            $lines[] = 'ID do produto: ' . intval($context['wc_id']);
        }
        if (!empty($context['fields'])) {
            $lines[] = 'Campos alterados: ' . esc_html($context['fields']);
        }
        return implode("\n", $lines);
    }

    // Fallback: outros logs
    foreach ($context as $key => $value) {
        if (is_array($value) || is_object($value)) {
            $lines[] = esc_html($key) . ': ' . esc_html(wp_json_encode($value, JSON_UNESCAPED_UNICODE));
        } else {
            $lines[] = esc_html($key) . ': ' . esc_html($value);
        }
    }
    return implode("\n", $lines);
}

$logger = Tiny_WooCommerce_Logger::get_instance();

// Paginação
$per_page = 50;
$current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
$offset = ($current_page - 1) * $per_page;

// Filtro de nível
$level_filter = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';

// Busca por nome ou SKU
$search_filter = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

// Obtém logs
$logs = $logger->get_logs(array(
    'level' => $level_filter,
    'search' => $search_filter,
    'limit' => $per_page,
    'offset' => $offset
));

// Total de logs (com filtros aplicados)
$total_logs = $logger->count_logs($level_filter, $search_filter);
$total_pages = $total_logs > 0 ? ceil($total_logs / $per_page) : 1;

// Contadores por nível (sem busca para mostrar totais gerais)
$info_count = $logger->count_logs('INFO');
$warning_count = $logger->count_logs('WARNING');
$error_count = $logger->count_logs('ERROR');
?>

<div class="wrap">
    <h1>DW Atualiza Produtos for Tiny ERP - Logs</h1>

    <div class="tiny-woo-sync-logs-header">
        <div class="tiny-woo-sync-stats">
            <div class="stat-box">
                <span class="stat-label">Total</span>
                <span class="stat-value"><?php echo number_format($total_logs, 0, ',', '.'); ?></span>
            </div>
            <div class="stat-box stat-info">
                <span class="stat-label">Info</span>
                <span class="stat-value"><?php echo number_format($info_count, 0, ',', '.'); ?></span>
            </div>
            <div class="stat-box stat-warning">
                <span class="stat-label">Avisos</span>
                <span class="stat-value"><?php echo number_format($warning_count, 0, ',', '.'); ?></span>
            </div>
            <div class="stat-box stat-error">
                <span class="stat-label">Erros</span>
                <span class="stat-value"><?php echo number_format($error_count, 0, ',', '.'); ?></span>
            </div>
        </div>

        <div class="tiny-woo-sync-filters">
            <form method="get" class="tiny-woo-sync-filters-form">
                <input type="hidden" name="page" value="tiny-woo-sync-logs">
                <input type="search" 
                       name="search" 
                       value="<?php echo esc_attr($search_filter); ?>" 
                       placeholder="Buscar por nome ou SKU..." 
                       class="tiny-woo-sync-search-input">
                <select name="level" onchange="this.form.submit()">
                    <option value="">Todos os níveis</option>
                    <option value="INFO" <?php selected($level_filter, 'INFO'); ?>>Info</option>
                    <option value="WARNING" <?php selected($level_filter, 'WARNING'); ?>>Avisos</option>
                    <option value="ERROR" <?php selected($level_filter, 'ERROR'); ?>>Erros</option>
                </select>
                <button type="submit" class="button">Buscar</button>
            </form>

            <?php
            $level_labels = array(
                'INFO' => 'Info',
                'WARNING' => 'Avisos',
                'ERROR' => 'Erros'
            );
            if (!empty($level_filter) && isset($level_labels[$level_filter])):
                $count_to_delete = $logger->count_logs($level_filter);
            ?>
                <button type="button" 
                        class="button button-secondary" 
                        id="delete-logs-by-level" 
                        data-level="<?php echo esc_attr($level_filter); ?>"
                        data-level-label="<?php echo esc_attr($level_labels[$level_filter]); ?>"
                        data-count="<?php echo intval($count_to_delete); ?>"
                        data-original-text="Excluir logs de <?php echo esc_attr($level_labels[$level_filter]); ?> (<?php echo number_format($count_to_delete, 0, ',', '.'); ?>)">
                    Excluir logs de <?php echo esc_html($level_labels[$level_filter]); ?> (<?php echo number_format($count_to_delete, 0, ',', '.'); ?>)
                </button>
            <?php endif; ?>

            <button type="button" class="button" id="clear-logs">Limpar Todos os Logs</button>
        </div>
    </div>

    <?php if (empty($logs)): ?>
        <div class="notice notice-info">
            <p>Nenhum log encontrado.</p>
        </div>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 120px;">Data/Hora</th>
                    <th style="width: 80px;">Nível</th>
                    <th>Mensagem</th>
                    <th style="width: 100px;">Detalhes</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                    <?php
                    $level_class = strtolower($log->log_level);
                    $context = json_decode($log->context, true);
                    $log_dt = \DateTime::createFromFormat('Y-m-d H:i:s', $log->created_at, wp_timezone());
                    $log_timestamp = $log_dt ? $log_dt->getTimestamp() : strtotime($log->created_at);
                    ?>
                    <tr>
                        <td><?php echo wp_date('d/m/Y H:i:s', $log_timestamp); ?></td>
                        <td>
                            <span class="log-level log-level-<?php echo $level_class; ?>">
                                <?php echo esc_html($log->log_level); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($log->message); ?></td>
                        <td>
                            <?php if (!empty($context)): ?>
                                <?php
                                $formatted_context = tiny_woo_sync_format_log_context($log->message, $context);
                                ?>
                                <button type="button" 
                                        class="button button-small view-context" 
                                        data-context="<?php echo esc_attr($formatted_context); ?>">
                                    Ver Detalhes
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $base_url = add_query_arg(array(
                        'page' => 'tiny-woo-sync-logs',
                        'level' => $level_filter,
                        'search' => $search_filter,
                        'paged' => '%#%'
                    ), admin_url('admin.php'));
                    echo paginate_links(array(
                        'base' => $base_url,
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $current_page
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal para detalhes -->
<div id="context-modal" style="display: none;">
    <div class="context-modal-overlay"></div>
    <div class="context-modal-content">
        <div class="context-modal-header">
            <h3>Detalhes do Log</h3>
            <button type="button" class="context-modal-close">&times;</button>
        </div>
        <div class="context-modal-body">
            <pre id="context-data"></pre>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Limpar todos os logs
    $('#clear-logs').on('click', function() {
        if (!confirm('Tem certeza que deseja limpar todos os logs? Esta ação não pode ser desfeita.')) {
            return;
        }

        var button = $(this);
        button.prop('disabled', true).text('Limpando...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tiny_woo_clear_logs',
                nonce: '<?php echo wp_create_nonce('tiny_woo_sync_nonce'); ?>'
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erro ao limpar logs: ' + response.data);
                    button.prop('disabled', false).text('Limpar Todos os Logs');
                }
            },
            error: function() {
                alert('Erro ao limpar logs');
                button.prop('disabled', false).text('Limpar Todos os Logs');
            }
        });
    });

    // Excluir logs por nível (Info, Avisos ou Erros)
    $(document).on('click', '#delete-logs-by-level', function() {
        var button = $(this);
        var level = button.data('level');
        var levelLabel = button.data('level-label');
        var count = button.data('count');

        if (!confirm('Tem certeza que deseja excluir os ' + count + ' log(s) de ' + levelLabel + '? Esta ação não pode ser desfeita.')) {
            return;
        }

        button.prop('disabled', true).text('Excluindo...');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'tiny_woo_delete_logs_by_level',
                nonce: '<?php echo wp_create_nonce('tiny_woo_sync_nonce'); ?>',
                level: level
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Erro ao excluir logs: ' + (response.data || 'Erro desconhecido'));
                    button.prop('disabled', false).text(button.data('original-text') || 'Excluir logs');
                }
            },
            error: function() {
                alert('Erro ao excluir logs');
                button.prop('disabled', false).text(button.data('original-text') || 'Excluir logs');
            }
        });
    });

    // Ver detalhes do log (contexto já formatado em texto legível)
    $(document).on('click', '.view-context', function() {
        var context = $(this).attr('data-context');
        $('#context-data').text(context || '');
        $('#context-modal').fadeIn();
    });

    // Fechar modal
    $('.context-modal-close, .context-modal-overlay').on('click', function() {
        $('#context-modal').fadeOut();
    });
});
</script>
