<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Email de Relatório de Sincronização - estende WC_Email
 * Usa a estrutura e template do WooCommerce
 */
class Tiny_WooCommerce_Email_Sync_Report extends WC_Email {

    /**
     * Dados do relatório
     */
    public $report_data = array();

    public function __construct() {
        $this->id             = 'tiny_woo_sync_report';
        $this->title          = __('Relatório de Sincronização Tiny', 'tiny-woo-sync');
        $this->description    = __('Relatório por e-mail com os produtos atualizados na sincronização Tiny ERP.', 'tiny-woo-sync');
        $this->template_html  = 'emails/tiny-woo-sync-report.php';
        $this->template_plain = 'emails/plain/tiny-woo-sync-report.php';
        $this->template_base  = TINY_WOO_SYNC_PLUGIN_DIR . 'templates/';
        $this->customer_email = false;

        parent::__construct();

        $this->email_type = 'html';
    }

    /**
     * Assunto padrão
     */
    public function get_default_subject() {
        return sprintf(
            __('[%s] Relatório de Sincronização de Produtos', 'tiny-woo-sync'),
            '{site_title}'
        );
    }

    /**
     * Cabeçalho padrão
     */
    public function get_default_heading() {
        return __('Relatório de Sincronização Tiny ERP', 'tiny-woo-sync');
    }

    /**
     * Campos do formulário (vazio - usamos configurações do plugin)
     */
    public function init_form_fields() {
        $this->form_fields = array();
    }

    /**
     * Verifica se o e-mail está ativo (usa configurações do plugin)
     */
    public function is_enabled() {
        $settings = get_option('tiny_woo_sync_settings');
        return !empty($settings['report_email_enabled']);
    }

    /**
     * Obtém destinatário (usa configurações do plugin)
     */
    public function get_recipient() {
        $settings = get_option('tiny_woo_sync_settings');
        $recipient = !empty($settings['report_email']) ? trim($settings['report_email']) : get_option('admin_email');
        $recipients = array_map('trim', explode(',', $recipient));
        $recipients = array_filter($recipients, 'is_email');
        return implode(', ', $recipients);
    }

    /**
     * Dispara o envio do e-mail
     *
     * @param array $report_data Dados do relatório: products, period_start, period_end, total
     * @param bool  $force      Se true, envia mesmo com relatório desativado (para teste)
     */
    public function trigger($report_data = array(), $force = false) {
        $this->report_data = wp_parse_args($report_data, array(
            'products'     => array(),
            'period_start' => '',
            'period_end'   => '',
            'total'       => 0
        ));

        $this->object = $this->report_data;

        $can_send = $force ? $this->get_recipient() : ($this->is_enabled() && $this->get_recipient());
        if ($can_send) {
            return $this->send($this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments());
        }

        return false;
    }

    /**
     * Conteúdo HTML
     */
    public function get_content_html() {
        return wc_get_template_html(
            $this->template_html,
            array(
                'report_data'   => $this->report_data,
                'email_heading' => $this->get_heading(),
                'email'         => $this
            ),
            '',
            $this->template_base
        );
    }

    /**
     * Conteúdo texto puro
     */
    public function get_content_plain() {
        return wc_get_template_html(
            $this->template_plain,
            array(
                'report_data'   => $this->report_data,
                'email_heading' => $this->get_heading(),
                'email'         => $this
            ),
            '',
            $this->template_base
        );
    }

}
