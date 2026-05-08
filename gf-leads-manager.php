<?php
/**
 * Plugin Name: Leads (GF) Manager
 * Description: Displays Gravity Forms entries for Admins and Editors with export and search.
 * Version: 1.1
 * Author: Ranked
 * Author URI: https://ranked.net.au
 */

if (!defined('ABSPATH')) exit;

class GF_Leads_Manager {

    private $page_slug = 'gf-leads-manager';
    // Use 'edit_pages' to allow both Administrators and Editors
    private $capability = 'edit_pages'; 

    public function __construct() {
        add_action('admin_menu', [$this, 'register_admin_page']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('admin_init', [$this, 'handle_csv_export']);
    }

    public function register_admin_page() {
        add_menu_page(
            'LEADS (GF)',
            'LEADS (GF)',
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
                .gf-leads-table { width: 100%; margin-top: 20px; border-collapse: collapse; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
                .gf-leads-table th, .gf-leads-table td { text-align: left; padding: 12px; border-bottom: 1px solid #ccd0d4; }
                .gf-leads-table thead { background: #f8f9fa; }
                .gf-leads-table tr:hover { background: #f0f0f1; }
                .search-box-custom { margin: 15px 0; display: flex; justify-content: space-between; align-items: center; }
                .modal-content-inner { max-height: 500px; overflow-y: auto; padding: 10px; }
                .entry-detail-row { display: flex; border-bottom: 1px solid #eee; padding: 8px 0; }
                .entry-label { font-weight: bold; width: 180px; flex-shrink: 0; color: #222; }
                .tablenav { margin-top: 20px; }
            </style>
            <script>
                jQuery(document).ready(function($) {
                    $('.view-info').on('click', function() {
                        var entryId = $(this).data('id');
                        var content = $('#entry-data-' + entryId).html();
                        $('<div title="Entry Details"><div class="modal-content-inner">' + content + '</div></div>').dialog({
                            modal: true,
                            width: 600,
                            resizable: false,
                            buttons: { Close: function() { $(this).dialog("close"); } }
                        });
                    });

                    $('#select-all').on('change', function() {
                        $('.entry-checkbox').prop('checked', $(this).prop('checked'));
                    });
                });
            </script>
            <?php
        });
    }

    public function render_admin_page() {
        if (!class_exists('GFAPI')) {
            echo '<div class="notice notice-error"><p>Gravity Forms is not active.</p></div>';
            return;
        }

        $search_query = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 30;

        $search_criteria = [];
        if (!empty($search_query)) {
            $search_criteria['field_filters'][] = ['key' => '0', 'operator' => 'contains', 'value' => $search_query];
        }

        $sorting = ['key' => 'date_created', 'direction' => 'DESC'];
        $paging = ['offset' => ($paged - 1) * $per_page, 'page_size' => $per_page];
        
        $entries = GFAPI::get_entries(0, $search_criteria, $sorting, $paging, $total_count);
        $total_pages = ceil($total_count / $per_page);

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">LEADS (GF)</h1>
            <hr class="wp-header-end">

            <form method="get">
                <input type="hidden" name="page" value="<?php echo $this->page_slug; ?>">
                <div class="search-box-custom">
                    <div>
                        <input type="search" name="s" value="<?php echo esc_attr($search_query); ?>" placeholder="Search entries...">
                        <input type="submit" class="button" value="Search">
                    </div>
                    <div>
                        <button type="submit" name="action" value="export_all" class="button button-primary">Export All to CSV</button>
                    </div>
                </div>

                <table class="gf-leads-table">
                    <thead>
                        <tr>
                            <th style="width: 30px;"><input type="checkbox" id="select-all"></th>
                            <th>Form Title</th>
                            <th>Date</th>
                            <th>Sender Name</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($entries): foreach ($entries as $entry): 
                            $form = GFAPI::get_form($entry['form_id']);
                            $sender_name = $this->get_field_val($entry, ['name', 'first name', 'last name', 'full name', 'имя', 'фио']);
                            $phone = $this->get_field_val($entry, ['phone', 'tel', 'mobile', 'телефон', 'номер']);
                            $email = $this->get_field_val($entry, ['email', 'e-mail', 'почта']);
                        ?>
                            <tr>
                                <td><input type="checkbox" name="export_ids[]" value="<?php echo $entry['id']; ?>" class="entry-checkbox"></td>
                                <td><?php echo esc_html($form['title']); ?></td>
                                <td><?php echo esc_html(date('d.m.Y H:i', strtotime($entry['date_created']))); ?></td>
                                <td><?php echo esc_html($sender_name); ?></td>
                                <td><?php echo esc_html($phone); ?></td>
                                <td><?php echo esc_html($email); ?></td>
                                <td>
                                    <button type="button" class="button view-info" data-id="<?php echo $entry['id']; ?>">Information</button>
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
                            <tr><td colspan="7">No entries found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="tablenav bottom">
                    <div class="alignleft actions">
                        <button type="submit" name="action" value="export_selected" class="button">Export Selected to CSV</button>
                    </div>
                    <div class="tablenav-pages">
                        <?php
                        echo paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => __('&laquo;'),
                            'next_text' => __('&raquo;'),
                            'total' => $total_pages,
                            'current' => $paged
                        ]);
                        ?>
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
                    if (is_array($val)) return implode(' ', $val); // Handles multi-input fields like Name
                    return $val;
                }
            }
        }
        return '-';
    }

    public function handle_csv_export() {
        if (!isset($_GET['action']) || !in_array($_GET['action'], ['export_all', 'export_selected'])) return;
        if (!current_user_can($this->capability)) return;

        $export_ids = isset($_GET['export_ids']) ? array_map('intval', $_GET['export_ids']) : [];
        $entries = [];

        if ($_GET['action'] === 'export_all') {
            $entries = GFAPI::get_entries(0, [], ['key' => 'date_created', 'direction' => 'DESC'], ['offset' => 0, 'page_size' => 2000]);
        } elseif (!empty($export_ids)) {
            foreach ($export_ids as $id) {
                $entries[] = GFAPI::get_entry($id);
            }
        }

        if (empty($entries)) return;

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=gf_leads_' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');
        // Add BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ID', 'Form', 'Date', 'Name', 'Phone', 'Email', 'Full Data']);

        foreach ($entries as $entry) {
            $form = GFAPI::get_form($entry['form_id']);
            $all_fields = [];
            foreach ($form['fields'] as $field) {
                $val = GFFormsModel::get_lead_field_value($entry, $field);
                $display_val = GFCommon::get_lead_field_display($field, $val, $entry['currency']);
                if (!empty($display_val) && $field->type !== 'section') {
                    $all_fields[] = $field->label . ": " . strip_tags($display_val);
                }
            }

            fputcsv($output, [
                $entry['id'],
                $form['title'],
                $entry['date_created'],
                $this->get_field_val($entry, ['name', 'имя']),
                $this->get_field_val($entry, ['phone', 'телефон']),
                $this->get_field_val($entry, ['email', 'почта']),
                implode(' | ', $all_fields)
            ]);
        }
        fclose($output);
        exit;
    }
}

new GF_Leads_Manager();