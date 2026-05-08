<?php
/**
 * Plugin Name: Leads (GF) Manager
 * Description: Displays Gravity Forms entries for Admins and Editors with export and search.
 * Version: 1.7
 * Author: Ranked
 * Author URI: https://ranked.net.au
 * GitHub Plugin URI: https://github.com/nmskvideo-dot/gf-leads-manager
 */

if (!defined('ABSPATH')) exit;

class GF_Leads_Manager {

    private $page_slug = 'gf-leads-manager';
    private $capability = 'edit_pages';
    
    // Update system properties
    private $github_repo = 'nmskvideo-dot/gf-leads-manager';
    private $plugin_file;

    public function __construct() {
        $this->plugin_file = plugin_basename(__FILE__);
        
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'handle_csv_export']);
        
        // Update system hooks
        add_filter('site_transient_update_plugins', [$this, 'push_update']);
        add_filter('plugins_api', [$this, 'plugin_popup'], 20, 3);
        add_action('upgrader_process_complete', [$this, 'after_update'], 10, 2);
    }

    /* --- UPDATE SYSTEM --- */

    public function push_update($transient) {
        if (empty($transient->checked)) return $transient;

        $remote = wp_remote_get("https://raw.githubusercontent.com/{$this->github_repo}/main/gf-leads-manager.php", [
            'timeout' => 10,
            'headers' => ['Accept' => 'application/vnd.github.v3.raw']
        ]);

        if (!is_wp_error($remote) && wp_remote_retrieve_response_code($remote) == 200) {
            $remote_code = wp_remote_retrieve_body($remote);
            preg_match('/Version:\s*(.*)/', $remote_code, $matches);
            $remote_version = isset($matches[1]) ? trim($matches[1]) : false;

            if ($remote_version && version_compare(get_plugin_data(__FILE__)['Version'], $remote_version, '<')) {
                $obj = new stdClass();
                $obj->slug = $this->page_slug;
                $obj->plugin = $this->plugin_file;
                $obj->new_version = $remote_version;
                $obj->url = "https://github.com/{$this->github_repo}";
                $obj->package = "https://github.com/{$this->github_repo}/archive/refs/heads/main.zip";
                
                $transient->response[$this->plugin_file] = $obj;
            }
        }
        return $transient;
    }

    public function plugin_popup($result, $action, $args) {
        if ($action !== 'plugin_information' || $args->slug !== $this->page_slug) return $result;
        
        $obj = new stdClass();
        $obj->name = 'Ranked Leads (GF)';
        $obj->slug = $this->page_slug;
        $obj->version = 'See GitHub';
        $obj->author = 'Gemini';
        $obj->homepage = "https://github.com/{$this->github_repo}";
        $obj->sections = ['description' => 'Advanced Lead Management for Gravity Forms.'];
        $obj->download_link = "https://github.com/{$this->github_repo}/archive/refs/heads/main.zip";
        
        return $obj;
    }

    public function after_update($upgrader_object, $options) {
        if ($options['action'] == 'update' && $options['type'] == 'plugin') {
            delete_site_transient('update_plugins');
        }
    }

    /* --- CORE FUNCTIONALITY --- */

    public function register_admin_page() {
        add_menu_page(
            'Ranked Leads', 
            'Ranked Leads', 
            $this->capability, 
            $this->page_slug, 
            [$this, 'render_admin_page'], 
            'dashicons-list-view', 
            25
        );
    }

    public function enqueue_assets($hook) {
        if (strpos($hook, $this->page_slug) === false) return;
        wp_enqueue_script('jquery-ui-dialog');
        wp_enqueue_style('wp-jquery-ui-dialog');

        add_action('admin_footer', function() {
            ?>
            <style>
                .gf-leads-table { width: 100%; margin-top: 10px; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); table-layout: fixed; }
                .gf-leads-table th, .gf-leads-table td { text-align: left; padding: 12px; border-bottom: 1px solid #ccd0d4; vertical-align: top; }
                .gf-leads-table thead { background: #f8f9fa; }
                
                /* Message Spoiler Styles */
                .message-cell { width: 25%; position: relative; }
                .message-wrapper { max-height: 2.8em; line-height: 1.4em; overflow: hidden; position: relative; color: #555; font-size: 13px; transition: max-height 0.3s ease-out; }
                .has-more .message-wrapper { cursor: pointer; }
                .has-more .message-wrapper::after { content: ""; position: absolute; bottom: 0; right: 0; width: 100%; height: 1.2em; background: linear-gradient(to bottom, rgba(255,255,255,0), rgba(255,255,255,1)); pointer-events: none; }
                .message-wrapper.expanded { max-height: 2000px; color: #000; }
                .message-wrapper.expanded::after { display: none; }
                
                .toggle-indicator { display: none; color: #2271b1; font-size: 18px; float: right; transition: transform 0.2s; }
                .has-more .toggle-indicator { display: inline-block; }
                .message-wrapper.expanded + .toggle-indicator { transform: rotate(180deg); }

                /* Layout & UI Controls */
                .search-box-custom { margin: 15px 0; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; background: #f0f0f1; padding: 15px; border-radius: 4px; }
                .controls-left, .controls-right { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
                .modal-content-inner { max-height: 500px; overflow-y: auto; padding: 10px; }
                .entry-detail-row { display: flex; border-bottom: 1px solid #eee; padding: 8px 0; }
                .entry-label { font-weight: bold; width: 180px; flex-shrink: 0; }

                /* Mobile-Friendly Pagination */
                .tablenav .tablenav-pages a, .tablenav .tablenav-pages span.current { text-decoration: none; padding: 8px 12px; background: #fff; border: 1px solid #ccc; margin: 0 2px; border-radius: 4px; min-width: 40px; display: inline-block; text-align: center; }
                .tablenav .tablenav-pages span.current { background: #2271b1; color: #fff; border-color: #2271b1; }
                
                @media (max-width: 782px) { 
                    .gf-leads-table { table-layout: auto; display: block; overflow-x: auto; } 
                    .search-box-custom { flex-direction: column; align-items: stretch; } 
                }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    // Logic to detect if text should be truncated
                    function detectOverflow() {
                        $('.message-wrapper').each(function() {
                            if (this.scrollHeight > $(this).innerHeight() + 2) {
                                $(this).closest('td').addClass('has-more');
                            }
                        });
                    }
                    setTimeout(detectOverflow, 200);

                    // Accordion click logic
                    $('.message-cell').on('click', function() {
                        var $wrapper = $(this).find('.message-wrapper');
                        if ($(this).hasClass('has-more')) {
                            $('.message-wrapper.expanded').not($wrapper).removeClass('expanded');
                            $wrapper.toggleClass('expanded');
                        }
                    });

                    // Modal Information window
                    $('.view-info').on('click', function(e) {
                        e.stopPropagation();
                        var entryId = $(this).data('id');
                        var content = $('#entry-data-' + entryId).html();
                        $('<div title="Entry Details"><div class="modal-content-inner">' + content + '</div></div>').dialog({
                            modal: true, 
                            width: 600, 
                            resizable: false, 
                            buttons: { 
                                Close: function() { $(this).dialog("close"); } 
                            }
                        });
                    });

                    // Bulk selection logic
                    $('#select-all').on('change', function() { 
                        $('.entry-checkbox').prop('checked', $(this).prop('checked')); 
                    });
                });
            </script>
            <?php
        });
    }

    public function render_admin_page() {
        if (!class_exists('GFAPI')) return;

        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = isset($_GET['per_page']) ? (($_GET['per_page'] === 'all') ? 9999 : intval($_GET['per_page'])) : 30;

        $search_criteria = !empty($search_query) ? ['field_filters' => [['key' => '0', 'operator' => 'contains', 'value' => $search_query]]] : [];
        $entries = GFAPI::get_entries(0, $search_criteria, ['key' => 'date_created', 'direction' => 'DESC'], ['offset' => ($paged - 1) * $per_page, 'page_size' => $per_page], $total_count);
        $total_pages = ceil($total_count / $per_page);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Ranked Leads</h1>
            <hr class="wp-header-end">
            
            <form method="get">
                <input type="hidden" name="page" value="<?php echo $this->page_slug; ?>">
                <div class="search-box-custom">
                    <div class="controls-left">
                        <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Search...">
                        <input type="submit" class="button" value="Search">
                        <select name="per_page" onchange="this.form.submit()">
                            <?php 
                            $options = [30, 50, 100, 200, 500, 'all'];
                            foreach ($options as $opt) {
                                printf(
                                    '<option value="%s" %s>%s per page</option>', 
                                    $opt, 
                                    selected($per_page, ($opt === 'all' ? 9999 : $opt), false), 
                                    $opt
                                );
                            }
                            ?>
                        </select>
                    </div>
                    <div class="controls-right">
                        <button type="submit" name="action" value="export_selected" class="button">Export Selected</button>
                        <button type="submit" name="action" value="export_all" class="button button-primary">Export All to CSV</button>
                    </div>
                </div>

                <table class="gf-leads-table">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="select-all"></th>
                            <th style="width: 14%;">Form</th>
                            <th style="width: 12%;">Date</th>
                            <th style="width: 15%;">Sender</th>
                            <th class="message-cell">Message</th>
                            <th style="width: 12%;">Phone</th>
                            <th style="width: 12%;">Email</th>
                            <th style="width: 10%;">Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($entries): foreach ($entries as $entry): 
                            $form = GFAPI::get_form($entry['form_id']); 
                        ?>
                            <tr>
                                <td><input type="checkbox" name="export_ids[]" value="<?php echo $entry['id']; ?>" class="entry-checkbox"></td>
                                <td><?php echo esc_html($form['title']); ?></td>
                                <td><?php echo esc_html(date('d.m.Y H:i', strtotime($entry['date_created']))); ?></td>
                                <td><?php echo esc_html($this->get_field_val($entry, ['name', 'имя'])); ?></td>
                                <td class="message-cell">
                                    <div class="message-wrapper">
                                        <?php echo nl2br(esc_html($this->get_field_val($entry, ['message', 'text', 'сообщение']))); ?>
                                    </div>
                                    <span class="dashicons dashicons-arrow-down-alt2 toggle-indicator"></span>
                                </td>
                                <td><?php echo esc_html($this->get_field_val($entry, ['phone', 'телефон'])); ?></td>
                                <td><?php echo esc_html($this->get_field_val($entry, ['email', 'почта'])); ?></td>
                                <td>
                                    <button type="button" class="button view-info" data-id="<?php echo $entry['id']; ?>">Info</button>
                                    <div id="entry-data-<?php echo $entry['id']; ?>" style="display:none;">
                                        <?php 
                                        foreach ($form['fields'] as $field) {
                                            $val = GFFormsModel::get_lead_field_value($entry, $field);
                                            $display_val = GFCommon::get_lead_field_display($field, $val, $entry['currency']);
                                            if (!empty($display_val) && $field->type !== 'section') {
                                                echo '<div class="entry-detail-row"><span class="entry-label">' . esc_html($field->label) . ':</span><span>' . $display_val . '</span></div>';
                                            }
                                        } 
                                        ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; else: ?>
                            <tr><td colspan="8">No entries found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php echo paginate_links(['total' => $total_pages, 'current' => $paged, 'base' => add_query_arg('paged', '%#%'), 'format' => '']); ?>
                    </div>
                </div>
            </form>
        </div>
        <?php
    }

    private function get_field_val($entry, $hints) {
        $form = GFAPI::get_form($entry['form_id']);
        foreach ($form['fields'] as $field) {
            foreach ($hints as $hint) {
                if (stripos($field->label, $hint) !== false) {
                    $val = GFFormsModel::get_lead_field_value($entry, $field);
                    return is_array($val) ? implode(' ', $val) : $val;
                }
            }
        }
        return '-';
    }

    public function handle_csv_export() {
        if (!isset($_GET['action']) || !in_array($_GET['action'], ['export_all', 'export_selected'])) return;
        if (!current_user_can($this->capability)) return;

        $export_ids = isset($_GET['export_ids']) ? array_map('intval', $_GET['export_ids']) : [];
        $entries = ($_GET['action'] === 'export_all') 
            ? GFAPI::get_entries(0, [], ['key' => 'date_created', 'direction' => 'DESC'], ['offset' => 0, 'page_size' => 5000]) 
            : array_filter(array_map(['GFAPI', 'get_entry'], $export_ids));

        if (empty($entries)) return;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=ranked_leads_' . date('Y-m-d') . '.csv');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        fputcsv($output, ['ID', 'Form', 'Date', 'Name', 'Message', 'Phone', 'Email']);
        
        foreach ($entries as $entry) {
            fputcsv($output, [
                $entry['id'], 
                GFAPI::get_form($entry['form_id'])['title'], 
                $entry['date_created'], 
                $this->get_field_val($entry, ['name', 'имя']), 
                $this->get_field_val($entry, ['message', 'сообщение']), 
                $this->get_field_val($entry, ['phone', 'телефон']), 
                $this->get_field_val($entry, ['email', 'почта'])
            ]);
        }
        fclose($output); 
        exit;
    }
}
new GF_Leads_Manager();