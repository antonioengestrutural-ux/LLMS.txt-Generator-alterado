<p align="center">
  <img src="https://img.shields.io/badge/WordPress-Plugin-blue.svg" alt="WordPress Plugin">
  <img src="https://img.shields.io/badge/Versão-2.4.0-green.svg" alt="Versão">
  <img src="https://img.shields.io/badge/PHP-8.2+-purple.svg" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/WordPress-6.5+-21759b.svg" alt="WordPress 6.5+">
  <img src="https://img.shields.io/badge/Licença-GPL%20v2%2B-orange.svg" alt="Licença">
  <img src="https://img.shields.io/badge/i18n-pt__BR%20%7C%20en__US-yellow.svg" alt="i18n">
</p>

# LLMS.txt Generator (manutenção por antonio.eng.br)

<p align="center">
  <b>Plugin WordPress para gerar, gerenciar e otimizar o arquivo <code>llms.txt</code> do seu site</b><br>
  Controle, com precisão, como ChatGPT, Claude, Gemini e demais sistemas de IA acessam, leem e representam o conteúdo do seu WordPress.
</p>

---

## ⚠️ Sobre esta versão (fork de manutenção)

Esta é uma **manutenção derivada** do plugin original **LLMS.txt Generator**, criado por **Tosta / Dante Testa** (<https://tosta.com.br>, repositório original: <https://github.com/tosta/LLMS.txt-Generator>).

**As alterações a partir da versão 2.4.0 foram feitas por [antonio.eng.br](https://github.com/antonio-eng-br)** e incluem:

| Alteração | Detalhe |
|---|---|
| 📌 Versionamento | Plugin atualizado para a série **2.4.x** |
| 🐘 PHP | Linha base consolidada em **PHP 8.2+** (compatível até 8.4) |
| 🧱 WordPress | Requisito mínimo elevado para **WordPress 6.5+** (`Requires at least` e checagem na ativação) |
| 📄 Documentação | README reescrito e arquivo de créditos legais separado ([LEGAL.md](LEGAL.md)) |

O código continua licenciado sob **GPL-2.0+**. Os créditos ao autor original são preservados **exatamente onde a licença obriga**: nos cabeçalhos de copyright do código e no aviso de licença. Detalhes completos em **[LEGAL.md](LEGAL.md)**.

> Dúvidas sobre o projeto original, suporte upstream e contato com o criador: <https://tosta.com.br> / <https://github.com/tosta/LLMS.txt-Generator>.

---

## 🔎 O que é o arquivo `llms.txt`?

O **`llms.txt`** é um padrão aberto e emergente, proposto para fazer pelo conteúdo dos sites o que o `robots.txt` faz pelos buscadores: dar aos sistemas de IA (ChatGPT, Claude, Gemini, Perplexity, etc.) instruções claras sobre **o que** está no seu site, **como** ele deve ser interpretado e **quais partes** podem ou não ser usadas para treinamento e geração de respostas.

Diferente do `robots.txt`, o `llms.txt` não é apenas uma lista de permissões — ele é um **mapa estruturado em Markdown** com:

- Título e descrição do site
- Listas categorizadas de páginas e posts mais importantes
- Descrições técnicas curtas para cada item
- Regras e contexto adicional para os modelos

Quando bem escrito, ele melhora dramaticamente como os LLMs entendem e citam o seu conteúdo.

### Por que isso importa?

- 🎯 **Citações mais precisas**: respostas geradas por IA tendem a referenciar seu site com a descrição que **você** escreveu, em vez de uma síntese aleatória.
- 🧭 **Controle de acesso**: você define quais posts/páginas/CPTs entram e quais ficam de fora.
- 🚀 **AEO/GEO ready**: o arquivo serve como base sólida para estratégias de *Answer Engine Optimization* e *Generative Engine Optimization*.
- ⏱️ **Atualização automática**: tudo que você publicar entra no arquivo sem intervenção manual.

---

## ✨ Funcionalidades

### 📄 Gerenciamento do arquivo `llms.txt`

- **Geração automática** servida em `https://seu-site.com/llms.txt`
- **Atualização incremental** sempre que um post for publicado, atualizado ou excluído
- **Pré-visualização** do arquivo direto no painel antes de publicar
- **Inclusão/exclusão por tipo**: posts, páginas, produtos WooCommerce, CPTs personalizados
- **Exclusão individual** por post via meta box
- **Conteúdo personalizado**: adicione blocos em Markdown ao topo/rodapé do arquivo
- **Cabeçalho UTF-8 com BOM** para compatibilidade máxima
- **Pré-checagem de escrita** (`is_writable()`) com log claro de falha

### 🤖 Descrições técnicas com IA

Três provedores integrados, todos plugáveis lado a lado:

| Provedor | Modelo padrão | Custo | Velocidade |
|---|---|---|---|
| **OpenAI** | `gpt-4o-mini` | $$ (pago) | ⚡ Muito rápido |
| **DeepSeek (via OpenRouter)** | `deepseek/deepseek-chat-v3-0324:free` | 🆓 Gratuito | ⚡ Rápido |
| **Google Gemini** | `gemini-2.0-flash` | 🆓 Gratuito | ⚡⚡ Ultra rápido |

- **Geração individual** via meta box no editor de cada post
- **Geração em massa** via *bulk action* no admin de posts
- **Validação prévia** das chaves API antes do uso
- **Edição manual** das descrições geradas a qualquer momento
- **Limite de 350 caracteres** otimizado para AEO
- **Prompt estruturado** focado em informação técnica, sem linguagem promocional

### 🎨 Interface administrativa

- Design moderno com **Tailwind CSS**
- Totalmente **responsivo** (desktop, tablet, mobile)
- **Toasts e indicadores de status** em tempo real
- **Admin Columns** com botões de geração direta na listagem
- **Meta box dedicada** abaixo do editor (Gutenberg e Clássico)
- **Contador de caracteres** em tempo real

### 🔧 Configuração granular para CPTs

Cada *Custom Post Type* pode ter sua própria fonte de conteúdo:

- `post_content` — corpo do post
- `post_excerpt` — resumo manual
- `custom_fields` — concatenação de meta fields específicos (ex.: `_descricao`, `_info`, `_atributos`)

Útil para WooCommerce, *plugins de portfólio*, *learning management systems* e qualquer estrutura que armazene dados em meta.

### 🌐 Internacionalização

- 🇧🇷 **Português Brasileiro** (pt_BR) — tradução nativa completa
- 🇺🇸 **English (en_US)** — tradução completa
- Arquitetura preparada para novos idiomas (`.po`, `.mo`, `.l10n.php`)
- Localização dos scripts JS para feedback em tempo real

### 🔐 Segurança

- **Criptografia AES-256-CBC** das chaves de API armazenadas em `wp_options`
- **Backward compatibility**: chaves antigas em texto plano continuam funcionando e são re-criptografadas no primeiro salvamento
- **Inputs `type="password"`** sem `value` renderizado para evitar vazamento via DOM/extensões
- **Nonces e capabilities** em todos os handlers AJAX e bulk actions
- **`sslverify => true`** em todas as chamadas HTTP externas
- **Escape contextual** (`esc_html`, `esc_attr`, `esc_url`) em todas as saídas
- **Sanitização** (`sanitize_text_field` + `wp_unslash`) em todas as entradas
- Sem `eval`, `shell_exec`, `unserialize` de dados externos, `include` dinâmico
- **Sistema de logs dedicado** (`LLMS_Txt_Logger`) com sanitização automática de segredos (`[REDACTED]`) e rotação de arquivo

---

## 📦 Requisitos

| Item | Mínimo | Recomendado |
|---|---|---|
| **WordPress** | 6.5 | 6.7+ |
| **PHP** | 8.2 | 8.3 ou 8.4 |
| **Extensões PHP** | `openssl`, `mbstring`, `json` | mesmas |
| **Permissão de escrita** | em `ABSPATH` (raiz do WP) | — |
| **Chave de API** (opcional) | OpenAI, OpenRouter ou Gemini | — |

> ⚠️ Sem `openssl`, a criptografia das chaves de API não funciona — o plugin ainda roda, mas perde a camada de hardening.

---

## 💾 Instalação

### Método 1 — Upload via admin (recomendado)

1. Baixe/compile o ZIP desta manutenção
2. No WP Admin, vá em **Plugins → Adicionar novo → Enviar plugin**
3. Selecione o ZIP e clique em **Instalar agora**
4. Clique em **Ativar plugin**

### Método 2 — FTP/SSH

```bash
cd wp-content/plugins/
unzip llms-txt-generator-2.4.0.zip
# A pasta deve se chamar exatamente "llms-txt-generator"
```

Em seguida, ative em **Plugins** no WP Admin.

### Método 3 — WP-CLI

```bash
wp plugin install path/to/llms-txt-generator-2.4.0.zip --activate
```

---

## ⚙️ Configuração rápida

### 1. Configurações gerais

Acesse **Configurações → LLMS.txt Generator** e:

- ✅ Marque **Habilitar arquivo llms.txt**
- ✏️ Preencha a **descrição do site** (1–3 frases sobre o seu projeto)
- ☑️ Selecione os **tipos de post** que devem ser incluídos
- 📝 (Opcional) adicione **conteúdo personalizado** em Markdown

### 2. Integração com IA

Na aba **Integração com IA**:

1. Escolha o provedor: **OpenAI**, **DeepSeek (via OpenRouter)** ou **Gemini**
2. Cole a chave de API no campo correspondente
3. Clique em **Validar chave** — a chave é testada no provedor antes de salvar
4. Salve as configurações

> 💡 **Onde obter cada chave:**
> - OpenAI: <https://platform.openai.com/api-keys>
> - OpenRouter (DeepSeek grátis): <https://openrouter.ai/keys>
> - Google Gemini: <https://aistudio.google.com/app/apikey>

### 3. Verifique o arquivo gerado

Acesse `https://seu-site.com/llms.txt` no navegador. O conteúdo deve aparecer estruturado, em Markdown, listando o conteúdo conforme suas configurações.

---

## 📝 Uso no dia a dia

### Controle de um post específico

1. Edite o post → role até a meta box **LLMS.txt — Descrição LLMS**
2. Para **excluir** o post do arquivo, marque a caixa correspondente
3. Para escrever **manualmente** a descrição, basta digitar (máx. 350 caracteres)
4. Para **gerar com IA**, clique no botão **Gerar automaticamente**
5. Atualize o post

### Geração em massa

1. Vá em **Posts → Todos os posts** (ou Páginas, ou seu CPT)
2. Selecione os posts desejados
3. No menu **Ações em massa**, escolha:
   - **Gerar descrições LLMS (apenas novos)** — pula posts que já têm descrição
   - **Gerar descrições LLMS (forçar todos)** — sobrescreve descrições existentes
4. Clique em **Aplicar**
5. O JavaScript gerencia a fila, mostrando progresso em tempo real

### Regenerar o arquivo manualmente

Em **Configurações → LLMS.txt Generator**, clique em **Regenerar arquivo**.

---

## 🪝 Hooks para desenvolvedores

O plugin expõe filtros que permitem customização sem editar o core.

### `llms_txt_generator_bulk_post_types`

Modifica os tipos de post elegíveis para *bulk action*.

```php
add_filter('llms_txt_generator_bulk_post_types', function ($post_types) {
    // Adicionar suporte a um CPT customizado
    $post_types[] = 'meu_cpt';
    return $post_types;
});
```

### `llms_txt_pre_generate_technical_description`

Permite injetar uma descrição customizada antes da chamada à IA. Se retornar valor não vazio, a IA não é chamada e os tokens são economizados.

```php
add_filter('llms_txt_pre_generate_technical_description', function ($description, $post) {
    if (get_post_meta($post->ID, 'minha_descricao_pronta', true)) {
        return get_post_meta($post->ID, 'minha_descricao_pronta', true);
    }
    return $description; // string vazia → segue para a IA
}, 10, 2);
```

### `llms_txt_generated_technical_description`

Pós-processamento da descrição gerada pela IA. Útil para padronizar formatação, adicionar prefixos, etc.

```php
add_filter('llms_txt_generated_technical_description', function ($description, $post) {
    return mb_strtoupper(mb_substr($description, 0, 1)) . mb_substr($description, 1);
}, 10, 2);
```

---

## 🧪 Troubleshooting

<details>
<summary><b>O arquivo /llms.txt retorna 404</b></summary>

- Verifique se **Habilitar arquivo llms.txt** está marcado
- Vá em **Configurações → Links permanentes** e clique em **Salvar** (sem alterar nada) para forçar reescrita das regras
- Confirme se o WordPress consegue escrever em `ABSPATH` (raiz do site). Em caso de falha, o log do plugin (Configurações → LLMS.txt Logs) terá a linha de destino não gravável
</details>

<details>
<summary><b>Erro "Chave da API OpenAI não configurada"</b></summary>

O plugin lê chaves criptografadas. Se o erro aparece **após** salvar a chave, é provável que a extensão `openssl` do PHP não esteja disponível.

Execute no servidor: `php -m | grep openssl`. Se nada aparecer, peça à sua hospedagem para habilitar a extensão.

Chaves salvas em versões anteriores continuam funcionando — o erro só aparece se você re-salvar sem `openssl` disponível.
</details>

<details>
<summary><b>A geração em massa para no meio</b></summary>

- Verifique a aba do navegador: o processamento é feito client-side, fechar a aba interrompe a fila
- Em servidores compartilhados, ajuste o intervalo entre chamadas se atingir rate limit da API
- Confirme o saldo da sua conta OpenAI / créditos OpenRouter
</details>

<details>
<summary><b>Como faço para resetar tudo?</b></summary>

```sql
DELETE FROM wp_options WHERE option_name LIKE 'llms_txt_%';
DELETE FROM wp_postmeta WHERE meta_key LIKE '_llms_txt_%';
```

Em seguida, desative e reative o plugin.
</details>

<details>
<summary><b>Posso usar com cache de página (WP Rocket, LiteSpeed, etc.)?</b></summary>

Sim. O arquivo `llms.txt` é estático, servido direto do disco. O cache de plugins não interfere.

Se o cache impedir a regeneração ao publicar, exclua a rota `/llms.txt` da lista de URLs cacheadas.
</details>

---

## 🛠️ Para contribuidores

```bash
git clone <repo desta manutenção>
cd llms-txt-generator

# Linter do WordPress (opcional, mas recomendado)
composer require --dev wp-coding-standards/wpcs dealerdirect/phpcodesniffer-composer-installer
vendor/bin/phpcs --standard=WordPress --extensions=php .
```

Pull requests bem-vindos. Por favor, mantenha:

- PHP 8.2+ compatível
- WordPress 6.5+ como linha base
- Sanitização e escape consistentes com o resto do código
- Strings traduzíveis com `__()` / `_e()` / `esc_html__()` no text domain `llms-txt-generator`
- Os avisos de copyright/licença do trabalho original intactos (ver [LEGAL.md](LEGAL.md))

---

## 📝 Changelog

### 2.4.0 (Outubro 2026) — Manutenção antonio.eng.br
- 👷 **Fork de manutenção**: alterações a partir desta versão feitas por **antonio.eng.br** sobre o código original de Tosta (GPL-2.0+)
- 🐘 **PHP 8.2+** consolidado como linha base (`Requires PHP: 8.2`)
- 🧱 **WordPress 6.5+** agora é requisito mínimo (`Requires at least` e checagem na ativação atualizados)
- 📄 README reescrito para refletir a manutenção derivada
- ⚖️ Novo arquivo [LEGAL.md](LEGAL.md) com créditos ao autor original dentro do escopo obrigatório da GPL
- ♻️ Constantes de versão (`LLMS_TXT_GENERATOR_VERSION` / `LLMS_TXT_VERSION`) sincronizadas em 2.4.0

### Histórico upstream (resumo)

As versões anteriores foram desenvolvidas por Tosta / Dante Testa no repositório original. Principais marcos:

- **2.3.2** — Sistema de logs dedicado (`LLMS_Txt_Logger`), página admin de logs, fix de i18n em WP 6.7+
- **2.3.1** — Release de segurança: criptografia AES-256-CBC das chaves de API, modelo `gpt-4o-mini`, remoção de `.bak` vazado, pre-check de escrita
- **2.3.0** — Hardening de segurança completo (nonces, capabilities, sslverify, escapes), PHP 8.2+ obrigatório, null safety
- **2.2.0** — Integração com Google Gemini (Flash 2.0)
- **2.1.0** — Correções de compatibilidade PHP 8.2+
- **2.0.4** — Tradução en_US, fixes de CPT/bulk generator, sistema i18n
- **2.0.0** — DeepSeek, Tailwind CSS, geração em massa
- **1.0.0** — Lançamento inicial

Detalhes completos do changelog upstream: [repositório original](https://github.com/tosta/LLMS.txt-Generator).

---

## 🔐 Licença e Créditos

Este plugin é distribuído sob a [GPL v2 ou posterior](http://www.gnu.org/licenses/gpl-2.0.html), a mesma licença do trabalho original.

- **Autor original:** Tosta / Dante Testa — © 2025, [tosta.com.br](https://tosta.com.br)
- **Manutenção e alterações (v2.4.0+):** [antonio.eng.br](https://github.com/antonio-eng-br)

Os créditos exigidos pela licença estão preservados no código e documentados em **[LEGAL.md](LEGAL.md)**.
