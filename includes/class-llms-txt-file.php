<?php
/**
 * Classe para gerenciar o arquivo llms.txt
 *
 * @package LLMS_Txt_Generator
 * @since 1.0.0
 * @updated 2026-01-03 - Compatibilidade PHP 8.2+ e segurança
 */

// Evitar acesso direto ao arquivo
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Classe responsável por gerenciar o arquivo llms.txt
 * 
 * @since 1.0.0
 */
class LLMS_Txt_File
{

    /**
     * Instância única da classe (padrão Singleton)
     *
     * @since 1.0.0
     * @var LLMS_Txt_File|null
     */
    private static ?LLMS_Txt_File $instance = null;

    /**
     * Caminho para o arquivo llms.txt
     *
     * @since 1.0.0
     * @var string
     */
    private string $file_path;

    /**
     * URL do arquivo llms.txt
     *
     * @since 1.0.0
     * @var string
     */
    private string $file_url;

    /**
     * Obtém a instância única da classe
     *
     * @since 1.0.0
     * @return LLMS_Txt_File
     */
    public static function get_instance(): LLMS_Txt_File
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Construtor da classe
     * Registra os hooks necessários para gerenciar o arquivo llms.txt
     *
     * @since 1.0.0
     */
    private function __construct()
    {
        // Definir caminho e URL do arquivo
        $this->file_path = ABSPATH . 'llms.txt';
        $this->file_url = home_url('/llms.txt');

        // Adicionar hooks
        add_action('init', array($this, 'maybe_serve_file'));
        add_action('llms_txt_regenerate_file', array($this, 'schedule_regeneration'));
        add_action('llms_txt_do_regenerate', array($this, 'regenerate_file'));

        // Desempenho: a verificação de existência do arquivo não roda mais em
        // TODAS as requisições do admin (admin_init). Ela passa a rodar no
        // wp_loaded apenas na página de configurações do plugin ou quando o
        // cron interno do WordPress executa a tarefa agendada.
        add_action('wp_loaded', array($this, 'maybe_check_file_on_plugin_page'));

        // Garante que a regeneração periódica aconteça sem travar o admin.
        // O resultado é memoizado por requisição e protegido por transient,
        // para não consultar o cron a cada pageview.
        add_action('init', array($this, 'ensure_regeneration_schedule'));

        // Hooks para regenerar o arquivo quando as configurações são salvas.
        // Agendamos em vez de regenerar de forma síncrona: assim a tela de
        // "Configurações" salva instantaneamente, mesmo em sites com milhares
        // de posts (a geração pesada roda em seguida, via cron/async request).
        add_action('update_option_llms_txt_settings', array($this, 'schedule_regeneration'), 10, 2);
        add_action('add_option_llms_txt_settings', array($this, 'schedule_regeneration'), 10, 2);

        // Adicionar AJAX handlers
        add_action('wp_ajax_llms_txt_get_preview', array($this, 'ajax_get_preview'));
        add_action('wp_ajax_llms_txt_regenerate_file', array($this, 'ajax_regenerate_file'));

        // Callback da request HTTP assíncrona de regeneração (admin e não-admin)
        add_action('wp_ajax_llms_txt_async_regen', array($this, 'handle_async_regeneration'));
        add_action('wp_ajax_nopriv_llms_txt_async_regen', array($this, 'handle_async_regeneration'));
    }

    /**
     * Garante que a tarefa agendada de regeneração periódica exista.
     *
     * A verificação é barata: usa only() no cron e wp_next_scheduled(), sem
     * queries pesadas. Roda uma vez por requisição apenas para checar o hook.
     *
     * @since 2.3.3
     * @return void
     */
    public function ensure_regeneration_schedule(): void
    {
        // Desempenho: memoiza por requisição (evita repetir em re-entradas)
        // e usa um transient de 12h como "carimbo" — assim o wp_next_scheduled()
        // (que lê a tabela de opções/criada do cron) só roda de 12 em 12 horas,
        // e não em toda requisição. Sem isso, sites com muitos agendamentos
        // ficam pesados no front-end.
        if (wp_cache_get('llms_txt_schedule_checked', 'llms_txt')) {
            return;
        }

        if (get_transient('llms_txt_schedule_check')) {
            wp_cache_set('llms_txt_schedule_checked', true, 'llms_txt');
            return;
        }

        if (!wp_next_scheduled('llms_txt_do_regenerate')) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, 'hourly', 'llms_txt_do_regenerate');
        }

        set_transient('llms_txt_schedule_check', 1, 12 * HOUR_IN_SECONDS);
        wp_cache_set('llms_txt_schedule_checked', true, 'llms_txt');
    }

    /**
     * Executa a verificação leve do arquivo somente na página de
     * configurações do plugin (em vez de em todo admin_init).
     *
     * @since 2.3.3
     * @return void
     */
    public function maybe_check_file_on_plugin_page(): void
    {
        if (!is_admin()) {
            return;
        }

        $current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : '';

        if ($current_page !== 'llms-txt-generator' && strpos($current_page, 'llms-txt') === false) {
            return;
        }

        $this->check_file_exists();
    }

    /**
     * Agenda a regeneração do llms.txt de forma assíncrona.
     *
     * Substitui a regeneração síncrona (que travava o admin em sites grandes):
     * em vez de gerar o arquivo dentro da requisição atual, disparamos uma
     * requisição HTTP assíncrona ao admin-ajax.php (padrão "dispatch" do
     * WP-Cron). Se o ambiente bloqueia loopback HTTP, um fallback via cron
     * interno garante a geração no próximo tick.
     *
     * @since 2.3.3
     * @return void
     */
    public function schedule_regeneration(): void
    {
        // Anti-stampede: evita múltiplas regenerações concorrentes.
        if (get_transient('llms_txt_regen_running')) {
            return;
        }

        // Fallback garantido: roda na próxima execução do cron (até ~1 min).
        if (!wp_next_scheduled('llms_txt_do_regenerate')) {
            wp_schedule_single_event(time() + 5, 'llms_txt_do_regenerate');
        }

        // Tenta executar imediatamente de forma assíncrona (sem travar a tela).
        if (function_exists('wp_spawn_cron_request')) {
            // WP 6.1+: dispara a rotina de cron completa em background.
            wp_spawn_cron_request();
        } else {
            // Fallback compatível: request POST assíncrona ao admin-ajax.
            wp_remote_post(
                admin_url('admin-ajax.php'),
                array(
                    'method'      => 'POST',
                    'timeout'     => 0.01,
                    'redirection' => 0,
                    'blocking'    => false,
                    'body'        => array(
                        'action' => 'llms_txt_async_regen',
                        'token'  => wp_hash('llms_txt_async_regen'),
                    ),
                )
            );
        }
    }

    /**
     * Handler da request assíncrona de regeneração.
     *
     * @since 2.3.3
     * @return void
     */
    public function handle_async_regeneration(): void
    {
        $token = isset($_REQUEST['token']) ? sanitize_text_field(wp_unslash($_REQUEST['token'])) : '';

        if (!$token || !hash_equals(wp_hash('llms_txt_async_regen'), $token)) {
            wp_die('', '', array('response' => 403));
        }

        $this->regenerate_file();
        wp_die('', '', array('response' => 204));
    }

    /**
     * Verifica se o arquivo llms.txt existe e o cria se necessário
     *
     * @since 1.0.0
     * @return void
     */
    public function check_file_exists(): void
    {
        $settings = get_option('llms_txt_settings', array());

        // Verificar se o plugin está habilitado
        if (isset($settings['enabled']) && $settings['enabled'] === '1') {
            // Verificar se o arquivo existe
            if (!file_exists($this->file_path)) {
                $this->regenerate_file();
            }
        }
    }

    /**
     * Serve o arquivo llms.txt quando solicitado
     *
     * @since 1.0.0
     * @return void
     */
    public function maybe_serve_file(): void
    {
        // Verificar se estamos acessando o arquivo llms.txt - usar sanitização
        $request_uri = isset($_SERVER['REQUEST_URI']) ? sanitize_text_field(wp_unslash($_SERVER['REQUEST_URI'])) : '';

        if ($request_uri === '/llms.txt') {
            // Verificar se o arquivo existe
            if (file_exists($this->file_path)) {
                // Definir headers
                header('Content-Type: text/plain; charset=utf-8');
                header('X-Robots-Tag: noindex, follow');
                header('Cache-Control: public, max-age=3600');

                // Enviar conteúdo do arquivo
                readfile($this->file_path);
                exit;
            } else {
                // Arquivo não existe, retornar 404
                status_header(404);
                nocache_headers();
                include(get_query_template('404'));
                exit;
            }
        }
    }

    /**
     * Máximo de caracteres da descrição gerada automaticamente.
     *
     * @since 2.3.3
     * @var int
     */
    const DESCRIPTION_MAX_LENGTH = 350;

    /**
     * Regenera o arquivo llms.txt (com cache anti-carga)
     *
     * Se um arquivo válido foi gerado há menos de 5 minutos, a regeneração é
     * ignorada — isso evita que cliques repetidos em "Regenerar" ou múltiplos
     * eventos disparem gerações completas custosas em sequência.
     *
     * @since 1.0.0
     * @return bool Verdadeiro se o arquivo foi gerado com sucesso (ou já estava
     *              atualizado recentemente), falso caso contrário
     */
    public function regenerate_file()
    {
        // Cache anti-carga: arquivo recém-gerado não precisa ser refeito.
        $last_regen = (int) get_option('llms_txt_last_regen_ts', 0);
        if ($last_regen && (time() - $last_regen) < 5 * MINUTE_IN_SECONDS && file_exists($this->file_path)) {
            return true;
        }

        // Anti-stampede: se outra regeneração está em andamento (ex.: request
        // assíncrona disparada logo antes do cron rodar), não duplica o trabalho.
        if (get_transient('llms_txt_regen_running')) {
            return false;
        }
        set_transient('llms_txt_regen_running', 1, 2 * MINUTE_IN_SECONDS);

        try {
            $settings = get_option('llms_txt_settings', array());

            // Verificar se o plugin está habilitado
            if (!isset($settings['enabled']) || $settings['enabled'] !== '1') {
                // Plugin desabilitado, remover arquivo se existir
                if (file_exists($this->file_path)) {
                    @unlink($this->file_path);
                }
                return false;
            }

            // Gerar conteúdo do arquivo
            $content = $this->generate_content();

            // Garantir que o conteúdo esteja em UTF-8
            if (!mb_check_encoding($content, 'UTF-8')) {
                $content = mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content));
            }

            // Adicionar BOM (Byte Order Mark) para garantir que o arquivo seja reconhecido como UTF-8
            $content = "\xEF\xBB\xBF" . $content;

            // Verificar permissão de escrita antes de tentar gravar. Se o destino existe
            // mas não é gravável, ou o diretório não é gravável, abortar com log.
            $target_dir = dirname($this->file_path);
            $writable = (file_exists($this->file_path) && is_writable($this->file_path))
                || (!file_exists($this->file_path) && is_writable($target_dir));

            if (!$writable) {
                if (class_exists('LLMS_Txt_Logger')) {
                    LLMS_Txt_Logger::error('Destino do llms.txt não é gravável', array(
                        'path' => $this->file_path,
                        'target_dir' => $target_dir,
                    ));
                }
                return false;
            }

            // Tentar escrever o arquivo com codificação UTF-8
            $result = @file_put_contents($this->file_path, $content);

            if ($result !== false) {
                // Atualizar timestamp da última atualização.
                // Usa uma opção dedicada (autoload=false) em vez de reescrever
                // 'llms_txt_settings': assim evitamos um loop de hooks
                // (update_option → regenerate_file → update_option ...) que, em
                // sites grandes, repetia a geração pesada e travava o admin.
                update_option('llms_txt_last_regen_ts', time(), false);
                return true;
            }

            return false;
        } finally {
            // Segurança extra: nunca deixa o lock preso em caso de exceção.
            delete_transient('llms_txt_regen_running');
        }
    }

    /**
     * Gera o conteúdo do arquivo llms.txt
     *
     * @since 1.0.0
     * @return string Conteúdo do arquivo
     */
    public function generate_content()
    {
        // Título do site
        $lines = array();
        $lines[] = "# " . get_bloginfo('name');
        $lines[] = "";

        // Obter configurações
        $settings = get_option('llms_txt_settings', array());

        // Priorizar a descrição personalizada do site, se existir
        // Caso contrário, usar a descrição padrão do WordPress
        if (!empty($settings['site_description'])) {
            $lines[] = "> " . $settings['site_description'];
        } else {
            $description = get_bloginfo('description');
            if (!empty($description)) {
                $lines[] = "> " . $description;
            }
        }

        $lines[] = "";

        // Desempenho: pré-carrega em UMA única consulta os metadados de todos
        // os posts publicados (incluídos e excluídos). Antes, cada post gerava
        // várias chamadas individuais a get_post_meta(), o que em sites com
        // milhares de posts deixava o admin/geração extremamente lento.
        $this->meta_cache = $this->prime_meta_cache();

        // Adicionar seção de posts se configurado
        if (!isset($settings['include_posts']) || $settings['include_posts'] === '1') {
            $lines[] = "## Posts";
            $lines[] = "";

            $found_posts = false;

            // Paginação leve em vez de 'posts_per_page' => -1: carrega os IDs
            // de 200 em 200, mantendo o uso de memória constante e evitando
            // timeouts em bases grandes.
            foreach ($this->chunked_post_ids('post', 'date', 'DESC') as $post_id) {
                // Verificar se o post deve ser excluído do arquivo llms.txt
                if ($this->is_post_excluded($post_id)) {
                    continue; // Pular este post
                }

                $post = get_post($post_id);
                if (!$post) {
                    continue;
                }

                $found_posts = true;
                $description = $this->get_post_description_for_llms($post);

                $lines[] = "- [" . get_the_title($post_id) . "](" . get_permalink($post_id) . "): " . $description;
            }

            if (!$found_posts) {
                $lines[] = "- Nenhum post encontrado";
            }
        }

        // Adicionar seção de páginas, se configurado
        if (isset($settings['include_pages']) && $settings['include_pages'] === '1') {
            $lines[] = "";
            $lines[] = "## Páginas";
            $lines[] = "";

            $found_pages = false;

            foreach ($this->chunked_post_ids('page', 'title', 'ASC') as $post_id) {
                // Verificar se a página deve ser excluída do arquivo llms.txt
                if ($this->is_post_excluded($post_id)) {
                    continue; // Pular esta página
                }

                $post = get_post($post_id);
                if (!$post) {
                    continue;
                }

                $found_pages = true;
                $description = $this->get_post_description_for_llms($post);

                $lines[] = "- [" . get_the_title($post_id) . "](" . get_permalink($post_id) . "): " . $description;
            }

            if (!$found_pages) {
                $lines[] = "- Nenhuma página encontrada";
            }
        }

        // Adicionar seções para cada tipo de post personalizado selecionado
        if (!empty($settings['post_types']) && is_array($settings['post_types'])) {
            foreach ($settings['post_types'] as $post_type) {
                // Obter informações sobre este tipo de post
                $post_type_obj = get_post_type_object($post_type);
                if (!$post_type_obj) {
                    continue;
                }

                // Obter o nome plural para o título da seção
                $post_type_label = $post_type_obj->labels->name;
                $lines[] = "";
                $lines[] = "## " . $post_type_label;
                $lines[] = "";

                // Obter configurações de fonte de conteúdo para este CPT
                $content_source = isset($settings['cpt_content_source'][$post_type]) ?
                    $settings['cpt_content_source'][$post_type] : 'post_content';

                // Verificar se estamos usando campos personalizados
                $custom_fields = array();
                if (
                    $content_source === 'custom_fields' &&
                    isset($settings['cpt_custom_fields'][$post_type]) &&
                    !empty($settings['cpt_custom_fields'][$post_type])
                ) {
                    // Converter string de campos separados por vírgula em array
                    $custom_fields = array_map('trim', explode(',', $settings['cpt_custom_fields'][$post_type]));
                }

                // Log para depuração (apenas em modo debug)
                if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                    error_log('LLMS.txt: Processando CPT ' . $post_type . ' com fonte: ' . $content_source);
                    if (!empty($custom_fields)) {
                        error_log('LLMS.txt: Campos personalizados para ' . $post_type . ': ' . wp_json_encode($custom_fields));
                    }
                }

                $found_items = false;

                foreach ($this->chunked_post_ids($post_type, 'title', 'ASC') as $post_id) {
                    // Verificar se o post deve ser excluído do arquivo llms.txt
                    if ($this->is_post_excluded($post_id)) {
                        continue; // Pular este post
                    }

                    $post = get_post($post_id);
                    if (!$post) {
                        continue;
                    }

                    $found_items = true;

                    // Verificar se existe uma descrição técnica personalizada (meta box)
                    $custom_description = $this->get_cached_meta($post_id, '_llms_txt_description');
                    $description = '';

                    // Se já temos uma descrição personalizada, usá-la
                    if (!empty($custom_description)) {
                        $description = $custom_description;
                    } else {
                        // Caso contrário, obter conteúdo conforme configuração do CPT
                        switch ($content_source) {
                            case 'post_excerpt':
                                if (!empty($post->post_excerpt)) {
                                    $description = wp_strip_all_tags($post->post_excerpt);
                                }
                                break;

                            case 'custom_fields':
                                // Concatenar valores de todos os campos personalizados
                                $meta_values = array();
                                foreach ($custom_fields as $field) {
                                    $meta_value = get_post_meta($post_id, $field, true);
                                    if (!empty($meta_value)) {
                                        // Converter array para string se necessário
                                        if (is_array($meta_value)) {
                                            $meta_value = implode(', ', $meta_value);
                                        }
                                        $meta_values[] = wp_strip_all_tags($meta_value);
                                    }
                                }

                                if (!empty($meta_values)) {
                                    $description = implode(' | ', $meta_values);
                                }
                                break;

                            case 'post_content':
                            default:
                                // Usar o início do conteúdo como descrição
                                $description = $this->truncate_text((string) ($post->post_content ?? ''));
                                break;
                        }
                    }

                    // Se ainda não temos descrição, usar texto genérico
                    if (empty($description)) {
                        $description = __('Sem descrição disponível', 'llms-txt-generator');
                    }

                    $lines[] = "- [" . get_the_title($post_id) . "](" . get_permalink($post_id) . "): " . $description;
                }

                if (!$found_items) {
                    $lines[] = "- Nenhum item encontrado para este tipo de post";
                }
            }
        }

        // Adicionar informações personalizadas, se existirem
        if (!empty($settings['custom_content'])) {
            $lines[] = "";
            $lines[] = $settings['custom_content'];
        }

        // Adicionar rodapé
        $lines[] = "";
        $lines[] = "---";
        $lines[] = "Gerado dinamicamente | " . wp_date('Y-m-d H:i:s');

        // Limpa o cache de metas após a geração para liberar memória.
        $this->meta_cache = null;

        return implode("\n", $lines) . "\n";
    }

    /**
     * Cache de metadados carregado em bloco durante a geração do llms.txt.
     *
     * @since 2.3.3
     * @var array<int, array<string, mixed>>|null
     */
    private ?array $meta_cache = null;

    /**
     * Tamanho dos lotes de IDs consultados na geração do arquivo.
     *
     * @since 2.3.3
     * @var int
     */
    const ID_CHUNK_SIZE = 200;

    /**
     * Pré-carrega em uma única consulta os metadados relevantes de todos os
     * posts publicados (exclude + description). O resultado é armazenado no
     * cache de objetos do WordPress via wp_cache_add_multiple(), então mesmo
     * chamadas futuras a get_post_meta() evitam novas queries ao banco.
     *
     * @since 2.3.3
     * @return array<int, array<string, mixed>> Mapa post_id => [meta_key => valor]
     */
    private function prime_meta_cache(): array
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $rows = $wpdb->get_results(
            "SELECT post_id, meta_key, meta_value
             FROM {$wpdb->postmeta}
             WHERE meta_key IN ('_llms_txt_exclude', '_llms_txt_description')"
        );

        $map = array();
        $cache_group = 'llms_txt_meta';

        if (is_array($rows)) {
            foreach ($rows as $row) {
                $map[(int) $row->post_id][$row->meta_key] = maybe_unserialize($row->meta_value);
            }

            // Alimenta também o cache interno de post meta do WP (grupo 'post_meta'),
            // reduzindo queries individuais vindas de outras partes do fluxo.
            $to_cache = array();
            foreach ($map as $post_id => $metas) {
                foreach ($metas as $key => $value) {
                    $to_cache[$post_id . '_' . $key] = $value;
                }
            }
            if (!empty($to_cache)) {
                wp_cache_add_multiple($to_cache, $cache_group);
            }
        }

        return $map;
    }

    /**
     * Lê um meta do cache local da geração (sem query ao banco).
     *
     * @since 2.3.3
     * @param int    $post_id ID do post
     * @param string $key     Chave do meta
     * @return mixed Valor do meta ou string vazia
     */
    private function get_cached_meta(int $post_id, string $key)
    {
        if (is_array($this->meta_cache) && isset($this->meta_cache[$post_id][$key])) {
            return $this->meta_cache[$post_id][$key];
        }
        return '';
    }

    /**
     * Verifica se o post/página está marcado para exclusão do llms.txt.
     *
     * @since 2.3.3
     * @param int $post_id ID do post
     * @return bool
     */
    private function is_post_excluded(int $post_id): bool
    {
        return $this->get_cached_meta($post_id, '_llms_txt_exclude') === '1';
    }

    /**
     * Itera os IDs de posts publicados de um tipo, em lotes (paged query).
     *
     * Usa fields => 'ids' (sem hydratar objetos completos nem disparar
     * the_post()/setup_postdata) e limite fixo por página, mantendo o custo
     * de memória constante mesmo em sites muito grandes.
     *
     * @since 2.3.3
     * @param string $post_type Tipo de post
     * @param string $orderby   Campo de ordenação
     * @param string $order     ASC|DESC
     * @return Generator<int>
     */
    private function chunked_post_ids(string $post_type, string $orderby, string $order): Generator
    {
        $page = 1;

        do {
            $ids = get_posts(array(
                'post_type'      => $post_type,
                'post_status'    => 'publish',
                'posts_per_page' => self::ID_CHUNK_SIZE,
                'paged'          => $page,
                'orderby'        => $orderby,
                'order'          => $order,
                'fields'         => 'ids',
                'no_found_rows'  => true, // pula o SELECT FOUND_ROWS() extra
            ));

            foreach ($ids as $id) {
                yield (int) $id;
            }

            $count = count($ids);
            $page++;
        } while ($count === self::ID_CHUNK_SIZE);
    }

    /**
     * Remove tags e normaliza espaços, truncando com segurança UTF-8.
     *
     * Desempenho: o corte usa substr byte-safe antes da expansão em UTF-8,
     * evitando mb_strlen/mb_substr sobre textos muito longos.
     *
     * @since 2.3.3
     * @param string $html Conteúdo bruto (com possíveis tags/shortcodes)
     * @return string Texto limpo e truncado
     */
    private function truncate_text(string $html): string
    {
        // Corte preliminar por bytes: nada além do limite de caracteres é
        // necessário para montar a descrição (sobram folga p/ multibyte).
        if (strlen($html) > self::DESCRIPTION_MAX_LENGTH * 4) {
            $html = substr($html, 0, self::DESCRIPTION_MAX_LENGTH * 4);
        }

        $text = wp_strip_all_tags($html);
        $text = (string) preg_replace('/\s+/', ' ', $text);

        if (mb_strlen($text, 'UTF-8') > self::DESCRIPTION_MAX_LENGTH) {
            $text = mb_substr($text, 0, self::DESCRIPTION_MAX_LENGTH - 3, 'UTF-8') . '...';
        }

        return $text;
    }

    /**
     * Obtém a descrição de um post para o arquivo llms.txt
     *
     * Aceita tanto o ID quanto o objeto WP_Post já carregado pela consulta —
     * evita reconsultas a get_post() dentro do loop de geração.
     *
     * @since 1.0.0
     * @param int|WP_Post $post ID do post ou objeto do post
     * @return string Descrição do post
     */
    private function get_post_description_for_llms($post)
    {
        if (is_numeric($post)) {
            $post = get_post((int) $post);
        }

        if (!$post instanceof WP_Post) {
            return '';
        }

        $post_id = $post->ID;

        // Verificar se existe uma descrição técnica personalizada (via cache)
        $custom_description = $this->get_cached_meta($post_id, '_llms_txt_description');

        if (!empty($custom_description)) {
            return $custom_description;
        }

        // Compatibilidade com plugins SEO populares (valores lidos do meta
        // já hidratado pelo core — sem forçar carregamento extra de metas).
        foreach (array('_yoast_wpseo_metadesc', 'rank_math_description', '_aioseop_description') as $seo_key) {
            $seo_description = get_post_meta($post_id, $seo_key, true);
            if (!empty($seo_description)) {
                return $seo_description;
            }
        }

        // Se não houver descrição personalizada ou meta description, usar o resumo
        if (!empty($post->post_excerpt)) {
            return wp_strip_all_tags($post->post_excerpt);
        }

        // Usar o início do conteúdo como descrição
        return $this->truncate_text((string) ($post->post_content ?? ''));
    }

    /**
     * Obtém o conteúdo atual do arquivo llms.txt
     *
     * @since 1.0.0
     * @return string|bool Conteúdo do arquivo ou falso se o arquivo não existir
     */
    public function get_file_content()
    {
        if (file_exists($this->file_path)) {
            return file_get_contents($this->file_path);
        }

        return false;
    }

    /**
     * Verifica se o arquivo llms.txt existe
     *
     * @since 1.0.0
     * @return bool Verdadeiro se o arquivo existe, falso caso contrário
     */
    public function file_exists()
    {
        return file_exists($this->file_path);
    }

    /**
     * Obtém a URL do arquivo llms.txt
     *
     * @since 1.0.0
     * @return string URL do arquivo
     */
    public function get_file_url()
    {
        return $this->file_url;
    }

    /**
     * Obtém a data da última atualização do arquivo
     *
     * @since 1.0.0
     * @return string Data da última atualização ou string vazia se não houver data
     */
    public function get_last_updated()
    {
        // Nova fonte do timestamp (opção dedicada, sem risco de loop de hooks).
        $last_regen = (int) get_option('llms_txt_last_regen_ts', 0);

        if ($last_regen > 0) {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_regen);
        }

        // Compatibilidade com versões antigas que gravavam em settings['last_updated'].
        $settings = get_option('llms_txt_settings', array());

        if (!empty($settings['last_updated'])) {
            return date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $settings['last_updated']);
        }

        return '';
    }

    /**
     * Handler AJAX para obter a visualização do arquivo
     *
     * @since 1.0.0
     */
    public function ajax_get_preview()
    {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'llms_txt_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Erro de segurança. Por favor, recarregue a página.', 'llms-txt-generator')));
        }

        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para realizar esta ação.', 'llms-txt-generator')));
        }

        // Gerar conteúdo de visualização
        $content = $this->generate_content();

        // Retornar conteúdo
        wp_send_json_success(array('content' => $content));
    }

    /**
     * Handler AJAX para regenerar o arquivo
     *
     * @since 1.0.0
     */
    public function ajax_regenerate_file()
    {
        // Verificar nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), 'llms_txt_ajax_nonce')) {
            wp_send_json_error(array('message' => __('Erro de segurança. Por favor, recarregue a página.', 'llms-txt-generator')));
        }

        // Verificar permissões
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Você não tem permissão para realizar esta ação.', 'llms-txt-generator')));
        }

        // Força a regeneração ignorando o cache anti-carga de 5 minutos
        // (o usuário clicou explicitamente em "Regenerar").
        delete_transient('llms_txt_regen_running');
        delete_option('llms_txt_last_regen_ts');
        $result = $this->regenerate_file();

        if ($result) {
            // Retornar sucesso
            wp_send_json_success(array(
                'message' => __('O arquivo llms.txt foi regenerado com sucesso!', 'llms-txt-generator'),
                'content' => $this->get_file_content(),
                'last_updated' => $this->get_last_updated()
            ));
        } else {
            // Retornar erro
            wp_send_json_error(array('message' => __('Ocorreu um erro ao regenerar o arquivo llms.txt. Verifique as permissões de escrita.', 'llms-txt-generator')));
        }
    }
}
