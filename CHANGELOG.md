# Changelog - DW Atualiza Produtos for Tiny ERP

Todas as mudanças notáveis neste projeto serão documentadas neste arquivo.

O formato é baseado em [Keep a Changelog](https://keepachangelog.com/pt-BR/1.0.0/),
e este projeto adere ao [Semantic Versioning](https://semver.org/lang/pt-BR/).

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
