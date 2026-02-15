# DW Atualiza Produtos for Tiny ERP

Plugin WordPress para sincronização automática de produtos entre Tiny ERP e WooCommerce.

![Version](https://img.shields.io/badge/version-0.1.0-blue.svg)
![WordPress](https://img.shields.io/badge/WordPress-5.8%2B-blue.svg)
![WooCommerce](https://img.shields.io/badge/WooCommerce-5.0%2B-purple.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)

---

## 📋 Índice

- [Sobre](#-sobre)
- [Recursos](#-recursos)
- [Requisitos](#-requisitos)
- [Instalação](#-instalação)
- [Configuração](#-configuração)
- [Funcionalidades](#-funcionalidades)
- [Logs](#-logs)
- [Perguntas Frequentes](#-perguntas-frequentes)
- [Changelog](#-changelog)

---

## 🎯 Sobre

O **DW Atualiza Produtos for Tiny ERP** mantém seu catálogo WooCommerce sincronizado com o Tiny ERP, oferecendo:

- ⚡ **Sincronização Automática** via cron do WordPress
- 🔄 **Sincronização Manual** sob demanda
- 📊 **Logs Detalhados** com formato legível
- 🔍 **Busca e Filtros** nos logs
- 🧹 **Limpeza Seletiva** de logs por nível

---

## ✨ Recursos

### Dados Sincronizados

- ✅ **Preço de venda** - Mantém preços sempre atualizados
- ✅ **Preço promocional** - Sincroniza preços em promoção
- ✅ **Estoque** - Quantidade e status (em estoque/fora de estoque)
- ✅ **Peso** - Peso bruto do produto
- ✅ **Dimensões** - Largura, altura e comprimento

### Modos de Operação

#### 1. Sincronização Automática
```
Cron do WordPress → Busca produtos no Tiny → Atualiza WooCommerce
⏱️ Intervalos: 15 min, 30 min, 1h, 2x/dia, 1x/dia
```

#### 2. Sincronização Manual
```
Você clica → Plugin sincroniza → Atualizado!
⏱️ Tempo: Sob demanda
```

### Sistema de Logs

- 📝 **Logs inteligentes** - Registra apenas produtos que foram realmente atualizados
- 🔎 **Busca** - Encontre logs por nome do produto ou SKU
- 🏷️ **Filtro por nível** - Info, Avisos ou Erros
- 🗑️ **Exclusão seletiva** - Exclua apenas logs de um nível específico
- 📐 **Formato brasileiro** - Valores com R$, vírgula decimal e cm

---

## 📦 Requisitos

### Sistema

- **WordPress:** 5.8 ou superior
- **WooCommerce:** 5.0 ou superior
- **PHP:** 7.4 ou superior
- **MySQL:** 5.6 ou superior

### Tiny ERP

- ✅ Conta ativa no Tiny ERP
- ✅ API habilitada
- ✅ Token de API gerado

### Servidor

- ✅ Permissão para executar requisições HTTP externas
- ✅ Cron do WordPress funcionando

---

## 🚀 Instalação

### Método 1: Upload pelo WordPress

1. Baixe o arquivo ZIP do plugin
2. Acesse: `WordPress Admin > Plugins > Adicionar Novo`
3. Clique em: **"Enviar Plugin"**
4. Escolha o arquivo ZIP
5. Clique em: **"Instalar Agora"**
6. Clique em: **"Ativar"**

### Método 2: Upload via FTP

1. Extraia o arquivo ZIP
2. Faça upload da pasta do plugin para `/wp-content/plugins/`
3. Acesse: `WordPress Admin > Plugins`
4. Encontre: **"DW Atualiza Produtos for Tiny ERP"**
5. Clique em: **"Ativar"**

---

## ⚙️ Configuração

### 1. Token da API Tiny

1. **Obtenha o Token:**
   - Acesse o Tiny ERP
   - Vá em: `Configurações > E-commerce > Integrações`
   - Copie o **Token da API**

2. **Configure no Plugin:**
   - Acesse: `WordPress Admin > DW Tiny ERP > Configurações`
   - Cole o token no campo **"Token da API Tiny"**
   - Clique em: **"Testar Conexão"**
   - Clique em: **"Salvar Configurações"**
   - ✅ Deve aparecer: "Conexão estabelecida com sucesso"

### 2. Sincronização Automática

1. Marque: ☑️ **"Ativar sincronização automática"**
2. Escolha o intervalo:
   - **A cada 15 minutos** - Alta frequência
   - **A cada 30 minutos** - Recomendado
   - **A cada hora** - Padrão
   - **Duas vezes ao dia** - Baixa frequência
   - **Uma vez ao dia** - Mínima frequência

3. Configure:
   - **Produtos por Lote:** 20 a 50 (padrão: 30)
   - **Delay entre Requisições:** 0,5 a 5 segundos (previne bloqueio da API)
   - **Retenção de Logs:** Dias para manter logs (padrão: 30)

4. Clique em: **"Salvar Configurações"**

---

## 🎯 Funcionalidades

### Sincronização Manual

- Botão **"Executar Sincronização Agora"** na página de configurações
- Sincroniza imediatamente os produtos do Tiny para o WooCommerce
- Processa até o limite de produtos por lote configurado

### Comportamento Inteligente

- **Apenas produtos alterados** - O plugin compara valores antes de atualizar
- **Sem logs desnecessários** - Registra apenas quando há alteração real
- **Identificação por SKU** - Produtos são encontrados pelo código SKU

---

## 📊 Logs

Acesse: `DW Tiny ERP > Logs`

### Recursos

| Recurso | Descrição |
|---------|-----------|
| **Busca** | Busque por nome do produto ou SKU |
| **Filtro por nível** | Info, Avisos ou Erros |
| **Excluir por nível** | Ao filtrar, exclua apenas logs daquele tipo |
| **Limpar todos** | Remove todos os logs |
| **Detalhes** | Modal com informações formatadas |

### Formato dos Logs

**Log de sincronização:**
```
Duração: 43,75s
Total de produtos atualizados: 13
Erros: 0
Não atualizados: 17
```

**Log de produto atualizado:**
```
Nome do produto: Produto Exemplo
SKU: ABC123
ID do produto: 2292
Preço: R$ 9,49 / R$ 10,99
Peso: 0,98 / 1,20
Medidas: 11,00 x 11,00 x 25,00 / 12,00 x 12,00 x 26,00 cm
```

### Níveis de Log

| Nível | Descrição |
|-------|------------|
| ℹ️ Info | Sincronização concluída, produto atualizado |
| ⚠️ Avisos | Produto não encontrado no WooCommerce |
| ❌ Erros | Falha na API, produto não encontrado |

---

## ❓ Perguntas Frequentes

### 1. O plugin cria produtos novos?

**R:** NÃO. O plugin apenas **atualiza produtos existentes** no WooCommerce. Os produtos devem ter o mesmo SKU no Tiny e no WooCommerce.

### 2. Por que alguns produtos não são atualizados?

**R:** Produtos são ignorados quando:
- Não possuem SKU
- Não existem no WooCommerce com o mesmo SKU
- Não houve alteração nos dados (valores já estão iguais)

### 3. O que significa "Não atualizados" no log?

**R:** São produtos que foram verificados mas não precisaram de alteração (dados já estavam corretos).

### 4. Como obter o token da API do Tiny?

**R:** Acesse o Tiny ERP > Configurações > E-commerce > Integrações. O Tiny oferece documentação em: [Como obter o token](https://tiny.com.br/ajuda/api/api2-gerar-token-api)

### 5. Recebi "API Bloqueada", o que fazer?

**R:** Aumente o **Delay entre Requisições** nas configurações para 2 ou 3 segundos. Isso evita que a API do Tiny bloqueie por excesso de requisições.

### 6. Os logs ocupam muito espaço?

**R:** Não. Configure a **Retenção de Logs** (ex: 30 dias) para remoção automática. Você também pode excluir logs por nível ou limpar todos manualmente.

---

## 📝 Changelog

Para o changelog completo, veja [CHANGELOG.md](CHANGELOG.md)

### Versão 0.1.0 (15/02/2026)

#### Novos Recursos
- 📝 Logs apenas para produtos realmente atualizados
- 🔍 Sistema de busca por nome ou SKU
- 🗑️ Exclusão de logs por nível (Info, Avisos, Erros)
- 📐 Formatação brasileira (R$, vírgula decimal, cm)

#### Melhorias
- 📋 Mensagem do log com nome e SKU do produto visíveis
- 📊 Descrição dos logs em formato legível (não técnico)
- 🎨 Modal de detalhes com apenas campos alterados

#### Alterações
- 🏷️ Nome do plugin alterado para "DW Atualiza Produtos for Tiny ERP"

---

## 📜 Licença

Este plugin é proprietário e de uso exclusivo.

**Direitos Reservados © 2026 DW Digital**

---

## 🙏 Agradecimentos

Desenvolvido por **David William da Costa - DW Digital**

- Tiny ERP pela API
- WooCommerce pela plataforma
- Comunidade WordPress

---

**Versão:** 0.1.0  
**Última Atualização:** 15 de Fevereiro de 2026  
**Autor:** David William da Costa - DW Digital  
**Requer:** WordPress 5.8+, WooCommerce 5.0+, PHP 7.4+
