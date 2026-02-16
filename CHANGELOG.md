# Changelog - DW Atualiza Produtos for Tiny ERP

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

---

## [0.3.0] - 15/02/2026

### ✨ Novos Recursos

#### Relatório por E-mail
- **Adicionado** sistema de envio de relatório de sincronização por e-mail
- **Relatório** lista todos os produtos atualizados no período (nome, SKU, data/hora)
- **Template** usa a estrutura e estilos do WooCommerce (header, footer, tabelas)
- **Agendamento** diário, semanal ou mensal via WP-Cron
- **Configurações** na página do plugin: ativar, e-mail(s) do destinatário, frequência
- **Botão** "Enviar relatório de teste" para verificar o envio imediatamente

### 🔧 Melhorias

- **Adicionado** intervalos `weekly` e `monthly` ao cron do WordPress
- **Adicionado** método `get_updated_products_logs()` no Logger para consulta por período

---

## [0.2.0] - 15/02/2026

### ✨ Novos Recursos

#### Modo de Sincronização
- **Adicionado** modo "Apenas produtos do WooCommerce" - processa somente produtos que existem na loja
- **Adicionado** modo "Catálogo completo do Tiny" - percorre todos os produtos do Tiny
- **Ideal** quando o Tiny tem muito mais produtos (ex: 7300) que o WooCommerce (ex: 1707)
- **Reduz** carga na API e no servidor ao pular produtos não vinculados

#### Rotação de Páginas (modo Tiny)
- **Implementado** rotação de páginas - a cada execução processa uma página diferente
- **Corrigido** problema de processar apenas os primeiros 30-50 produtos (sempre página 1)
- **Adicionado** estado persistente (página e offset) para próxima execução
- **Adicionado** ciclo completo - ao chegar na última página, reinicia da primeira

#### Sincronizar Produto por SKU
- **Adicionado** campo e botão para sincronizar um produto específico por SKU
- **Útil** para atualizar imediatamente um produto alterado no Tiny, sem esperar o lote
- **Implementado** endpoint AJAX `tiny_woo_sync_product_by_sku`

#### Interface
- **Adicionado** botão "Reiniciar Rotação" para voltar ao início (página 1 ou offset 0)
- **Adicionado** exibição do estado da rotação (próxima página/offset)
- **Adicionado** seletor de modo de sincronização nas configurações

### 🔧 Melhorias

#### Configurações
- **Aumentado** limite de produtos por lote de 50 para 100
- **Adicionado** opção `sync_mode` nas configurações
- **Adicionado** opção padrão `sync_mode: woocommerce` para novas instalações

#### Sincronização
- **Unificado** lógica da sincronização manual e agendada (ambas usam `run_sync()`)
- **Adicionado** método `get_woocommerce_products_with_sku()` para modo WooCommerce
- **Adicionado** método `count_woocommerce_products_with_sku()` para total de produtos
- **Adicionado** estado de rotação separado para modo WooCommerce (`tiny_woo_sync_rotation_state_wc`)

#### Logs
- **Adicionado** informação de página/offset no log de sincronização concluída
- **Adicionado** suporte a exibição do modo (WooCommerce) e próxima execução
- **Removido** log de aviso para cada produto não encontrado no WooCommerce (modo Tiny) - evitava spam com milhares de produtos

#### API Tiny
- **Adicionado** método `list_products_with_pagination()` retornando produtos + página + total_pages
- **Refatorado** `list_products()` para usar o novo método (compatibilidade mantida)

### 📁 Arquivos Modificados

- `tiny-to-woocommerce-auto-sync.php` - Opção padrão sync_mode
- `includes/class-sync-manager.php` - Modos de sync, rotação, sync por SKU, helpers WooCommerce
- `includes/class-tiny-api.php` - list_products_with_pagination
- `includes/class-settings.php` - sync_mode, batch_size até 100
- `admin/class-admin-page.php` - AJAX sync_product_by_sku, reset_rotation
- `admin/views/settings-page.php` - Modo de sync, sync por SKU, reiniciar rotação
- `admin/views/logs-page.php` - Formato para modo WooCommerce e página

---

## [0.1.0] - 15/02/2026

### ✨ Novos Recursos

#### Sistema de Logs Aprimorado
- **Adicionado** log apenas para produtos que sofreram alteração real (não registra quando valores são iguais)
- **Adicionado** sistema de busca por nome do produto ou SKU nos logs
- **Adicionado** exclusão de logs por nível (Info, Avisos ou Erros) - botão aparece ao filtrar
- **Adicionado** comparação antes/depois para detectar alterações antes de salvar

#### Formatação e Visibilidade
- **Adicionado** mensagem do log com nome e SKU visíveis: "O produto NOME, SKU X, foi atualizado com sucesso"
- **Adicionado** formato legível para descrição dos logs (substitui JSON técnico)
- **Adicionado** formatação brasileira: R$ para preços, vírgula decimal, cm para medidas
- **Adicionado** exibição apenas dos campos que foram alterados no modal de detalhes

### 🔧 Melhorias

#### Log de Sincronização
- **Alterado** formato de exibição:
  - Antes: `{"duration": "43.75s", "updated": 13, "errors": 0, "skipped": 17}`
  - Depois: Duração, Total de produtos atualizados, Erros, Não atualizados

#### Log de Produto
- **Alterado** formato de exibição no modal:
  - Nome do produto, SKU, ID do produto
  - Preço: Antes / Agora (com R$)
  - Peso: Antes / Agora
  - Medidas: Antes / Agora (com cm)
  - Apenas campos que tiveram alteração

#### Logger
- **Adicionado** parâmetro `search` em `get_logs()` para busca em message e context
- **Adicionado** parâmetro `search` em `count_logs()` para paginação com busca
- **Adicionado** método `delete_logs_by_level()` para exclusão por nível

### 🏷️ Alterações

#### Nome do Plugin
- **Alterado** nome de "Tiny to WooCommerce Auto Sync" para "DW Atualiza Produtos for Tiny ERP"
- **Alterado** item do menu para "DW Tiny ERP"
- **Alterado** títulos das páginas (Configurações e Logs)
- **Alterado** mensagem de dependência do WooCommerce

### 📁 Arquivos Modificados

- `tiny-to-woocommerce-auto-sync.php` - Nome do plugin
- `includes/class-sync-manager.php` - Comparação de valores, log condicional, retorno 'no_changes'
- `includes/class-logger.php` - Busca e delete_logs_by_level
- `admin/class-admin-page.php` - AJAX delete_logs_by_level, nome do menu
- `admin/views/logs-page.php` - Busca, exclusão por nível, formatação BR, helper de contexto
- `admin/views/settings-page.php` - Título da página
- `assets/css/admin-style.css` - Estilos do campo de busca

---

## [0.0.1] - 2026

### 🚀 Implementação Inicial

#### Sincronização
- **Implementado** sincronização automática de produtos do Tiny ERP para WooCommerce
- **Implementado** sincronização manual sob demanda
- **Implementado** integração via API do Tiny ERP
- **Implementado** identificação de produtos por SKU

#### Dados Sincronizados
- **Implementado** atualização de preço de venda
- **Implementado** atualização de preço promocional
- **Implementado** atualização de estoque e status
- **Implementado** atualização de peso bruto
- **Implementado** atualização de dimensões (largura, altura, comprimento)

#### Configurações
- **Implementado** campo para token da API Tiny
- **Implementado** teste de conexão com a API
- **Implementado** ativação/desativação da sincronização automática
- **Implementado** intervalo de sincronização (15 min, 30 min, 1h, 2x/dia, 1x/dia)
- **Implementado** tamanho do lote (20-50 produtos)
- **Implementado** delay entre requisições (previne bloqueio da API)
- **Implementado** retenção de logs (dias)

#### Sistema de Logs
- **Implementado** registro de logs em banco de dados
- **Implementado** níveis: INFO, WARNING, ERROR
- **Implementado** filtro por nível
- **Implementado** paginação
- **Implementado** limpeza automática de logs antigos
- **Implementado** botão para limpar todos os logs
- **Implementado** modal para visualizar contexto do log

#### Infraestrutura
- **Implementado** compatibilidade com HPOS do WooCommerce
- **Implementado** agendamento via WP-Cron
- **Implementado** intervalos customizados (15 e 30 minutos)
- **Implementado** tabela de logs no banco de dados

---

## Formato

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

### Tipos de Mudanças
- `Added` (Adicionado) para novas funcionalidades
- `Changed` (Modificado) para mudanças em funcionalidades existentes
- `Deprecated` (Descontinuado) para funcionalidades que serão removidas
- `Removed` (Removido) para funcionalidades removidas
- `Fixed` (Corrigido) para correção de bugs
- `Security` (Segurança) para vulnerabilidades
