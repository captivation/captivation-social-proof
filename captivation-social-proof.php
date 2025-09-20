<?php
/**
 * Plugin Name: Social Proof Overlays
 * Description: Display animated social proof overlays with schema markup to build trust and engagement
 * Version: 1.0.14
 * Author: Captivation Agency
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class SocialProofOverlays {
    
    private $option_name = 'spo_settings';
    private $cached_settings = null;
    
    public function __construct() {
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('admin_init', array($this, 'admin_init'));
        add_action('wp_footer', array($this, 'display_overlays'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('wp_ajax_spo_save_content', array($this, 'save_content'));
        add_action('wp_ajax_spo_delete_content', array($this, 'delete_content'));
        add_action('admin_notices', array($this, 'show_debug_notices'));
        
        // Meta box handling
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_data'), 10, 1);
        add_action('wp_ajax_spo_meta_save', array($this, 'ajax_meta_save'));
    }

    private function get_settings() {
        if ($this->cached_settings === null) {
            $this->cached_settings = get_option($this->option_name);
        }
        return $this->cached_settings;
    }

    private function clear_settings_cache() {
        $this->cached_settings = null;
    }

    private function debug_log($message) {
        $log_file = WP_CONTENT_DIR . '/spo-debug.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
    }

    public function show_debug_notices() {
        $log_file = WP_CONTENT_DIR . '/spo-debug.log';
        if (file_exists($log_file) && current_user_can('manage_options')) {
            $log_content = file_get_contents($log_file);
            $recent_logs = array_slice(explode("\n", $log_content), -10); // Last 10 lines
            
            /*if (!empty(array_filter($recent_logs))) {
                echo '<div class="notice notice-info"><p><strong>SPO Debug Log (Last 10 entries):</strong></p>';
                echo '<pre style="background: #f1f1f1; padding: 10px; font-size: 11px;">';
                echo esc_html(implode("\n", $recent_logs));
                echo '</pre>';
                echo '<p><a href="' . admin_url('options-general.php?page=social-proof-overlays&clear_debug=1') . '">Clear Debug Log</a></p>';
                echo '</div>';
            }*/
        }
    }
    
    public function init() {
        // Initialize default settings
        if (false === get_option($this->option_name)) {
            $defaults = array(
                'content_items' => array(),
                'display_groups' => array(),
                'delay' => 3000,
                'duration' => 5000,
                'interval' => 8000,
                'position' => 'bottom-left',
                'animation' => 'slide',
                'theme' => 'modern',
                'custom_bg_color' => '#667eea',
    'custom_text_color' => '#ffffff',
    'custom_border_color' => '#ffffff',
    'custom_border_width' => '1',
                'enabled' => true
            );
            add_option($this->option_name, $defaults);
        }
    }
    
    public function admin_menu() {
        add_options_page(
            'Social Proof Overlays',
            'Social Proof',
            'manage_options',
            'social-proof-overlays',
            array($this, 'admin_page')
        );
    }
    
    public function admin_init() {
        register_setting('spo_settings_group', $this->option_name);
    }
    
    public function admin_enqueue_scripts($hook) {
        if ('settings_page_social-proof-overlays' !== $hook) {
            return;
        }
        wp_enqueue_editor();
        wp_enqueue_script('jquery-ui-sortable');
    }
    
    public function enqueue_scripts() {
        $settings = $this->get_settings();
        
        // Improved null checking
        if (!$settings || !isset($settings['enabled']) || !$settings['enabled']) {
            return;
        }
        
        // Check if we have active content items
        $active_items = array();
        if (!empty($settings['content_items'])) {
            $active_items = array_filter($settings['content_items'], function($item) {
                return !empty($item['active']) && !empty($item['content']);
            });
        }
        
        if (empty($active_items)) {
            return;
        }
        
        // Enqueue jQuery
        wp_enqueue_script('jquery');
    }
    
    public function admin_page() {
        if (isset($_GET['clear_debug'])) {
            $log_file = WP_CONTENT_DIR . '/spo-debug.log';
            if (file_exists($log_file)) {
                unlink($log_file);
            }
            echo '<div class="notice notice-success"><p>Debug log cleared.</p></div>';
        }
        
        $settings = $this->get_settings();
        ?>
        <div class="wrap">
            <h1>Social Proof Overlays</h1>
            
            <div class="spo-admin-container">
                <div class="spo-tabs">
                    <button class="spo-tab-button active" data-tab="content">Content</button>
                    <button class="spo-tab-button" data-tab="groups">Display Groups</button>
                    <button class="spo-tab-button" data-tab="design">Design</button>
                    <button class="spo-tab-button" data-tab="display">Display</button>
                </div>
                
                <form method="post" action="options.php">
                    <?php settings_fields('spo_settings_group'); ?>
                    
                    <!-- Content Tab -->
                    <div id="content-tab" class="spo-tab-content active">
                        <h2>Content Items</h2>
                        <div id="content-repeater">
                            <?php $this->render_content_items($settings['content_items']); ?>
                        </div>
                        <button type="button" id="add-content-item" class="button button-primary">Add New Item</button>
                    </div>
                    
                    <!-- Display Groups Tab -->
                    <div id="groups-tab" class="spo-tab-content">
                        <h2>Display Groups</h2>
                        <p>Create groups to organize your content items and control where they appear.</p>
                        
                        <div id="groups-repeater">
                            <?php $this->render_display_groups($settings['display_groups']); ?>
                        </div>
                        <button type="button" id="add-display-group" class="button button-primary">Add New Group</button>
                    </div>
                    
                    <!-- Design Tab -->
                    <div id="design-tab" class="spo-tab-content">
                        <h2>Design Settings</h2>
                        <table class="form-table">
                            <tr>
                                <th>Position</th>
                                <td>
                                    <select name="<?php echo $this->option_name; ?>[position]">
                                        <option value="top-left" <?php selected($settings['position'], 'top-left'); ?>>Top Left</option>
                                        <option value="top-right" <?php selected($settings['position'], 'top-right'); ?>>Top Right</option>
                                        <option value="bottom-left" <?php selected($settings['position'], 'bottom-left'); ?>>Bottom Left</option>
                                        <option value="bottom-right" <?php selected($settings['position'], 'bottom-right'); ?>>Bottom Right</option>
                                        <option value="center" <?php selected($settings['position'], 'center'); ?>>Center</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th>Animation</th>
                                <td>
                                    <select name="<?php echo $this->option_name; ?>[animation]">
                                        <option value="slide" <?php selected($settings['animation'], 'slide'); ?>>Slide In</option>
                                        <option value="fade" <?php selected($settings['animation'], 'fade'); ?>>Fade In</option>
                                        <option value="bounce" <?php selected($settings['animation'], 'bounce'); ?>>Bounce In</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
    <th>Theme</th>
    <td>
        <select name="<?php echo $this->option_name; ?>[theme]">
            <option value="modern" <?php selected($settings['theme'], 'modern'); ?>>Modern</option>
            <option value="minimal" <?php selected($settings['theme'], 'minimal'); ?>>Minimal</option>
            <option value="bold" <?php selected($settings['theme'], 'bold'); ?>>Bold</option>
            <option value="elegant" <?php selected($settings['theme'], 'elegant'); ?>>Elegant</option>
            <option value="custom" <?php selected($settings['theme'], 'custom'); ?>>Custom</option>
        </select>
    </td>
</tr>
<tr id="custom-colors-row" style="<?php echo ($settings['theme'] === 'custom') ? '' : 'display: none;'; ?>">
    <th>Custom Colors</th>
    <td>
        <table style="width: 100%;">
            <tr>
                <td style="width: 50%; padding-right: 10px;">
                    <label><strong>Background Color:</strong></label><br>
                    <input type="color" name="<?php echo $this->option_name; ?>[custom_bg_color]" value="<?php echo isset($settings['custom_bg_color']) ? $settings['custom_bg_color'] : '#667eea'; ?>" style="width: 100%;" />
                </td>
                <td style="width: 50%;">
                    <label><strong>Text Color:</strong></label><br>
                    <input type="color" name="<?php echo $this->option_name; ?>[custom_text_color]" value="<?php echo isset($settings['custom_text_color']) ? $settings['custom_text_color'] : '#ffffff'; ?>" style="width: 100%;" />
                </td>
            </tr>
            <tr>
                <td style="padding-right: 10px; padding-top: 10px;">
                    <label><strong>Border Color:</strong></label><br>
                    <input type="color" name="<?php echo $this->option_name; ?>[custom_border_color]" value="<?php echo isset($settings['custom_border_color']) ? $settings['custom_border_color'] : '#ffffff'; ?>" style="width: 100%;" />
                </td>
                <td style="padding-top: 10px;">
                    <label><strong>Border Width (px):</strong></label><br>
                    <input type="number" name="<?php echo $this->option_name; ?>[custom_border_width]" value="<?php echo isset($settings['custom_border_width']) ? $settings['custom_border_width'] : '1'; ?>" min="0" max="10" style="width: 100%;" />
                </td>
            </tr>
        </table>
    </td>
</tr>
                        </table>
                    </div>
                    
                    <!-- Display Tab -->
                    <div id="display-tab" class="spo-tab-content">
                        <h2>Display Settings</h2>
                        <table class="form-table">
                            <tr>
                                <th>Enable Overlays</th>
                                <td>
                                    <input type="checkbox" name="<?php echo $this->option_name; ?>[enabled]" value="1" <?php checked($settings['enabled'], 1); ?> />
                                    <label>Show social proof overlays on site</label>
                                </td>
                            </tr>
                            <tr>
                                <th>Initial Delay (ms)</th>
                                <td>
                                    <input type="number" name="<?php echo $this->option_name; ?>[delay]" value="<?php echo $settings['delay']; ?>" min="0" step="500" />
                                    <p class="description">Time before first overlay appears</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Display Duration (ms)</th>
                                <td>
                                    <input type="number" name="<?php echo $this->option_name; ?>[duration]" value="<?php echo $settings['duration']; ?>" min="1000" step="500" />
                                    <p class="description">How long each overlay stays visible</p>
                                </td>
                            </tr>
                            <tr>
                                <th>Interval Between (ms)</th>
                                <td>
                                    <input type="number" name="<?php echo $this->option_name; ?>[interval]" value="<?php echo $settings['interval']; ?>" min="2000" step="500" />
                                    <p class="description">Time between each overlay</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                    
                    <?php submit_button(); ?>
                </form>
            </div>
        </div>
        
        <style>
        .spo-admin-container { max-width: 800px; }
        .spo-tabs { border-bottom: 1px solid #ccc; margin-bottom: 20px; }
        .spo-tab-button { background: none; border: none; padding: 10px 20px; cursor: pointer; border-bottom: 2px solid transparent; }
        .spo-tab-button.active { border-bottom-color: #0073aa; color: #0073aa; }
        .spo-tab-content { display: none; }
        .spo-tab-content.active { display: block; }
        .content-item { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; background: #f9f9f9; }
        .content-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .content-item-title { font-weight: bold; }
        .delete-item { color: #a00; cursor: pointer; }
        .form-table th { width: 150px; }
        .group-item { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; background: #f0f8ff; }
        .group-item-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
        .group-item-title { font-weight: bold; }
        .delete-group { color: #a00; cursor: pointer; }
        .spo-groups-checkboxes label { margin-bottom: 5px; }
        .spo-overlay-item:focus {
            outline: 2px solid #005fcc;
            outline-offset: 2px;
        }
        </style>
        
        <script>
        jQuery(document).ready(function($) {
            // Tab switching
            $('.spo-tab-button').click(function() {
                var tab = $(this).data('tab');
                $('.spo-tab-button, .spo-tab-content').removeClass('active');
                $(this).addClass('active');
                $('#' + tab + '-tab').addClass('active');
            });

            // Show/hide custom color options
$('select[name="<?php echo $this->option_name; ?>[theme]"]').change(function() {
    if ($(this).val() === 'custom') {
        $('#custom-colors-row').show();
    } else {
        $('#custom-colors-row').hide();
    }
});
            
            // Add content item
            $('#add-content-item').click(function() {
                var index = $('.content-item').length;
                var html = getContentItemHtml(index);
                $('#content-repeater').append(html);
                initializeEditor(index);
                populateGroupsForNewItem(index);
            });

            // Add display group
            $('#add-display-group').click(function() {
                var index = $('.group-item').length;
                var html = getGroupItemHtml(index);
                $('#groups-repeater').append(html);
                
                // Update all existing content items with new groups
                $('.content-item').each(function(itemIndex) {
                    populateGroupsForNewItem(itemIndex);
                });
            });

            // Delete display group
            $(document).on('click', '.delete-group', function() {
                if (confirm('Delete this group? This will remove it from all content items and pages.')) {
                    $(this).closest('.group-item').remove();
                    updateGroupsCheckboxes();
                }
            });

            function getGroupItemHtml(index) {
                var optionName = '<?php echo $this->option_name; ?>';
                return `
                <div class="group-item">
                    <div class="group-item-header">
                        <span class="group-item-title">New Group</span>
                        <span class="delete-group" data-index="${index}">Delete</span>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th>Group Name</th>
                            <td>
                                <input type="text" name="${optionName}[display_groups][${index}][name]" style="width:100%;" required placeholder="e.g. Homepage Testimonials" />
                            </td>
                        </tr>
                        <tr>
                            <th>Description</th>
                            <td>
                                <textarea name="${optionName}[display_groups][${index}][description]" rows="2" style="width:100%;" placeholder="Optional description"></textarea>
                            </td>
                        </tr>
                    </table>
                </div>`;
            }

            function updateGroupsCheckboxes() {
                // Update group checkboxes in content items when groups change
                $('.spo-groups-checkboxes[data-index]').each(function() {
                    var index = $(this).data('index');
                    var html = '<p><em>Save changes to see updated groups</em></p>';
                    $(this).html(html);
                });
            }

            function populateGroupsForNewItem(index) {
                // Get current groups from the groups tab
                var groups = [];
                $('.group-item').each(function(groupIndex) {
                    var groupName = $(this).find('input[name*="[name]"]').val();
                    if (groupName) {
                        groups.push({
                            index: groupIndex,
                            name: groupName
                        });
                    }
                });
                
                // Build checkboxes HTML
                var html = '';
                if (groups.length === 0) {
                    html = '<p><em>No display groups created yet. <a href="#" onclick="jQuery(\'.spo-tab-button[data-tab=groups]\').click(); return false;">Create a group</a> first.</em></p>';
                } else {
                    html = '<div class="spo-groups-checkboxes">';
                    var optionName = '<?php echo $this->option_name; ?>';
                    groups.forEach(function(group) {
                        html += '<label style="display: block; margin-bottom: 5px;">';
                        html += '<input type="checkbox" name="' + optionName + '[content_items][' + index + '][groups][]" value="' + group.index + '" /> ';
                        html += group.name;
                        html += '</label>';
                    });
                    html += '</div>';
                }
                
                // Update the specific item
                $('.spo-groups-checkboxes[data-index="' + index + '"]').html(html);
            }
            
            // Delete content item
            $(document).on('click', '.delete-item', function() {
                if (confirm('Delete this item?')) {
                    $(this).closest('.content-item').remove();
                }
            });
            
            function getContentItemHtml(index) {
                var optionName = '<?php echo $this->option_name; ?>';
                return `
                <div class="content-item">
                    <div class="content-item-header">
                        <span class="content-item-title">Content Item ${index + 1}</span>
                        <span class="delete-item">Delete</span>
                    </div>
                    <table class="form-table">
                        <tr>
                            <th>Content Type</th>
                            <td>
                                <select name="${optionName}[content_items][${index}][type]">
                                    <option value="review">Customer Review</option>
                                    <option value="nugget">Business Nugget</option>
                                    <option value="faq">FAQ/Tip</option>
                                    <option value="stat">Statistic</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th>Content</th>
                            <td>
                                <textarea name="${optionName}[content_items][${index}][content]" id="content_${index}" rows="4" style="width:100%;"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th>Author/Source</th>
                            <td>
                                <input type="text" name="${optionName}[content_items][${index}][author]" style="width:100%;" placeholder="Author name or source" />
                            </td>
                        </tr>
                        <tr>
                            <th>Link URL</th>
                            <td>
                                <input type="url" name="${optionName}[content_items][${index}][url]" style="width:100%;" placeholder="https://example.com (optional)" />
                                <p class="description">Optional: URL to redirect when overlay is clicked</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Link Target</th>
                            <td>
                                <select name="${optionName}[content_items][${index}][target]">
                                    <option value="_blank">New Window</option>
                                    <option value="_self">Same Window</option>
                                </select>
                                <p class="description">Where to open the link</p>
                            </td>
                        </tr>
                        <tr>
                            <th>CTA Message</th>
                            <td>
                                <input type="text" name="${optionName}[content_items][${index}][cta]" style="width:100%;" placeholder="Learn More, Read Story, etc. (optional)" maxlength="20" />
                                <p class="description">Optional: Short call-to-action text (20 chars max)</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Display Groups</th>
                            <td>
                                <div class="spo-groups-checkboxes" data-index="${index}">
                                    <!-- Groups will be populated by JavaScript -->
                                </div>
                                <p class="description">Select which groups this content belongs to</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Active</th>
                            <td>
                                <input type="checkbox" name="${optionName}[content_items][${index}][active]" value="1" checked />
                            </td>
                        </tr>
                    </table>
                </div>`;
            }
            
            function initializeEditor(index) {
                wp.editor.initialize('content_' + index, {
                    tinymce: {
                        wpautop: true,
                        plugins: 'charmap colorpicker hr lists paste tabfocus textcolor fullscreen wordpress wpautoresize wpeditimage wpemoji wpgallery wplink wptextpattern',
                        toolbar1: 'bold italic underline | bullist numlist | link unlink | undo redo'
                    },
                    quicktags: true
                });
            }
        });
        </script>
        <?php
    }
    
    private function render_content_items($items) {
        if (empty($items)) {
            return;
        }
        
        foreach ($items as $index => $item) {
            ?>
            <div class="content-item">
                <div class="content-item-header">
                    <span class="content-item-title">Content Item <?php echo $index + 1; ?></span>
                    <span class="delete-item">Delete</span>
                </div>
                <table class="form-table">
                    <tr>
                        <th>Content Type</th>
                        <td>
                            <select name="<?php echo $this->option_name; ?>[content_items][<?php echo $index; ?>][type]">
                                <option value="review" <?php selected($item['type'], 'review'); ?>>Customer Review</option>
                                <option value="nugget" <?php selected($item['type'], 'nugget'); ?>>Business Nugget</option>
                                <option value="faq" <?php selected($item['type'], 'faq'); ?>>FAQ/Tip</option>
                                <option value="stat" <?php selected($item['type'], 'stat'); ?>>Statistic</option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th>Content</th>
                        <td>
                            <?php
                            wp_editor(
                                $item['content'],
                                'content_' . $index,
                                array(
                                    'textarea_name' => $this->option_name . '[content_items][' . $index . '][content]',
                                    'textarea_rows' => 4,
                                    'media_buttons' => false,
                                    'teeny' => true
                                )
                            );
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Author/Source</th>
                        <td>
                            <input type="text" name="<?php echo $this->option_name; ?>[content_items][<?php echo $index; ?>][author]" value="<?php echo esc_attr($item['author']); ?>" style="width:100%;" placeholder="Author name or source" />
                        </td>
                    </tr>
                    <tr>
                        <th>Link URL</th>
                        <td>
                            <input type="url" name="<?php echo $this->option_name; ?>[content_items][<?php echo $index; ?>][url]" value="<?php echo esc_attr(isset($item['url']) ? $item['url'] : ''); ?>" style="width:100%;" placeholder="https://example.com (optional)" />
                            <p class="description">Optional: URL to redirect when overlay is clicked</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Link Target</th>
                        <td>
                            <select name="<?php echo $this->option_name; ?>[content_items][<?php echo $index; ?>][target]">
                                <option value="_blank" <?php selected(isset($item['target']) ? $item['target'] : '_blank', '_blank'); ?>>New Window</option>
                                <option value="_self" <?php selected(isset($item['target']) ? $item['target'] : '_blank', '_self'); ?>>Same Window</option>
                            </select>
                            <p class="description">Where to open the link</p>
                        </td>
                    </tr>   
                    <tr>
                        <th>CTA Message</th>
                        <td>
                            <input type="text" name="<?php echo $this->option_name; ?>[content_items][<?php echo $index; ?>][cta]" value="<?php echo esc_attr(isset($item['cta']) ? $item['cta'] : ''); ?>" style="width:100%;" placeholder="Learn More, Read Story, etc. (optional)" maxlength="20" />
                            <p class="description">Optional: Short call-to-action text (20 chars max)</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Display Groups</th>
                        <td>
                            <?php $this->render_groups_checkboxes($index, isset($item['groups']) ? $item['groups'] : array()); ?>
                            <p class="description">Select which groups this content belongs to</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Active</th>
                        <td>
                            <input type="checkbox" name="<?php echo $this->option_name; ?>[content_items][<?php echo $index; ?>][active]" value="1" <?php checked($item['active'], 1); ?> />
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        }
    }
    
    public function display_overlays() {
        $settings = $this->get_settings();
        
        if (!$settings || !isset($settings['enabled']) || !$settings['enabled'] || empty($settings['content_items'])) {
            return;
        }
        
        // DIAGNOSTIC CODE - ADD THIS TEMPORARILY
        global $post;
        $current_post_id = is_object($post) ? $post->ID : get_queried_object_id();
        $selected_group = get_post_meta($current_post_id, '_spo_display_group', true);
        
        // Debug output (remove after testing)
        if (current_user_can('manage_options')) {
            echo "<!-- SPO Debug: Post ID: $current_post_id, Selected Group: '$selected_group', Type: " . gettype($selected_group) . " -->";
            
            // Show all available groups
            $groups = isset($settings['display_groups']) ? $settings['display_groups'] : array();
            echo "<!-- Available Groups: " . print_r($groups, true) . " -->";
            
            // Show content items and their groups
            foreach ($settings['content_items'] as $index => $item) {
                if (!empty($item['groups'])) {
                    echo "<!-- Content Item $index Groups: " . print_r($item['groups'], true) . " -->";
                }
            }
        }

        // Get groups and ensure consistent indexing
        $groups = isset($settings['display_groups']) ? $settings['display_groups'] : array();
        if (!empty($groups)) {
            $groups = array_values($groups);
            $settings['display_groups'] = $groups;
        }

        // Convert to string for consistent comparison
$selected_group = strval($selected_group);

if ($selected_group === '') {  
    return; // No group selected = no overlays
}

        // Filter items by selected group
        $active_items = array_filter($settings['content_items'], function($item) use ($selected_group) {
            $has_groups = !empty($item['groups']);
            $is_in_group = $has_groups && in_array($selected_group, $item['groups']);
            
            return !empty($item['active']) && !empty($item['content']) && $is_in_group;
        });
        
        if (empty($active_items)) {
            return;
        }
        
        ?>
        <!-- Social Proof Overlays CSS -->
        <style>
        #spo-overlay-container {
            position: fixed;
            z-index: 999999;
            pointer-events: none;
        }
        
        .spo-overlay-item {
            display: none;
            position: relative;
            max-width: 300px;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
            line-height: 1.4;
            pointer-events: auto;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 60px;
        }
        
        .spo-overlay-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 25px rgba(0,0,0,0.2);
        }

        .spo-clickable {
            text-decoration: none !important;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .spo-clickable:hover {
            text-decoration: none !important;
            border: 2px solid rgba(255, 255, 255, 0.6);
            transform: translateY(-3px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.25);
        }

        .spo-clickable:visited {
            text-decoration: none !important;
        }

        .spo-clickable:focus {
            outline: none;
            border: 2px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
            text-decoration: none !important;
        }

.spo-clickable * {
            text-decoration: none !important;
        }

        .spo-clickable:hover * {
            text-decoration: none !important;
        }

        /* Specific theme color overrides for clickable links */
        .spo-clickable.spo-theme-modern {
            color: white !important;
        }

        .spo-clickable.spo-theme-modern * {
            color: white !important;
        }

        .spo-clickable.spo-theme-minimal {
            color: #333 !important;
        }

        .spo-clickable.spo-theme-minimal * {
            color: #333 !important;
        }

        .spo-clickable.spo-theme-bold {
            color: white !important;
        }

        .spo-clickable.spo-theme-bold * {
            color: white !important;
        }

        .spo-clickable.spo-theme-elegant {
            color: #ecf0f1 !important;
        }

        .spo-clickable.spo-theme-elegant * {
            color: #ecf0f1 !important;
        }
        
        /* Themes */
.spo-theme-custom {
    background: <?php echo isset($settings['custom_bg_color']) ? $settings['custom_bg_color'] : '#667eea'; ?>;
    color: <?php echo isset($settings['custom_text_color']) ? $settings['custom_text_color'] : '#ffffff'; ?>;
    border: <?php echo isset($settings['custom_border_width']) ? $settings['custom_border_width'] : '1'; ?>px solid <?php echo isset($settings['custom_border_color']) ? $settings['custom_border_color'] : '#ffffff'; ?>;
}

/* Custom theme clickable overrides */
.spo-clickable.spo-theme-custom {
    color: <?php echo isset($settings['custom_text_color']) ? $settings['custom_text_color'] : '#ffffff'; ?> !important;
}

.spo-clickable.spo-theme-custom * {
    color: <?php echo isset($settings['custom_text_color']) ? $settings['custom_text_color'] : '#ffffff'; ?> !important;
}

/* Custom theme CTA colors */
.spo-theme-custom .spo-cta-message {
    color: <?php echo isset($settings['custom_text_color']) ? $settings['custom_text_color'] : 'rgba(255, 255, 255, 0.9)'; ?>;
}

/* Custom theme countdown colors */
.spo-theme-custom .spo-countdown-bg {
    stroke: <?php echo isset($settings['custom_text_color']) ? $settings['custom_text_color'] : '#ffffff'; ?>;
    stroke-opacity: 0.2;
}

.spo-theme-custom .spo-countdown-progress {
    stroke: <?php echo isset($settings['custom_text_color']) ? $settings['custom_text_color'] : '#ffffff'; ?>;
    stroke-opacity: 0.9;
}

        .spo-theme-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .spo-theme-minimal {
            background: white;
            color: #333;
            border: 1px solid #e1e1e1;
        }
        
        .spo-theme-bold {
            background: #ff6b6b;
            color: white;
            font-weight: 600;
        }
        
        .spo-theme-elegant {
            background: #2c3e50;
            color: #ecf0f1;
            font-style: italic;
        }
        
        .spo-content p {
            margin: 0 0 8px 0;
        }
        
        .spo-content p:last-child {
            margin-bottom: 0;
        }
        
        .spo-author {
            font-size: 12px;
            opacity: 0.8;
            margin-top: 8px;
            font-style: italic;
        }

        /* Countdown Timer Styles */
        .spo-countdown-timer {
            position: absolute;
            bottom: 8px;
            right: 8px;
            width: 18px;
            height: 18px;
            z-index: 1;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .spo-overlay-item.spo-timer-ready .spo-countdown-timer {
            opacity: 1;
        }

        /* CTA Message Styles */
        .spo-cta-message {
            position: absolute;
            bottom: 8px;
            left: 30px;
            font-size: 11px;
            opacity: 0;
            transition: opacity 0.3s ease;
            font-weight: 500;
            letter-spacing: 0.5px;
        }

        .spo-overlay-item.spo-timer-ready .spo-cta-message {
            opacity: 0.7;
        }

        .spo-overlay-item:hover .spo-cta-message {
            opacity: 1;
        }

        /* Theme-specific CTA colors */
        .spo-theme-modern .spo-cta-message {
            color: rgba(255, 255, 255, 0.9);
        }

        .spo-theme-minimal .spo-cta-message {
            color: rgba(0, 0, 0, 0.6);
        }

        .spo-theme-bold .spo-cta-message {
            color: rgba(255, 255, 255, 0.9);
        }

        .spo-theme-elegant .spo-cta-message {
            color: rgba(236, 240, 241, 0.8);
        }

        .spo-countdown-svg {
            width: 100%;
            height: 100%;
            transform: rotate(-90deg);
        }

        .spo-countdown-bg {
            fill: none;
            stroke: rgba(255, 255, 255, 0.2);
            stroke-width: 2;
        }

        .spo-countdown-progress {
            fill: none;
            stroke: rgba(255, 255, 255, 0.8);
            stroke-width: 2;
            stroke-linecap: round;
            stroke-dasharray: 100, 100;
            stroke-dashoffset: 0;
        }

        /* Theme-specific countdown colors */
        .spo-theme-modern .spo-countdown-bg {
            stroke: rgba(255, 255, 255, 0.2);
        }

        .spo-theme-modern .spo-countdown-progress {
            stroke: rgba(255, 255, 255, 0.9);
        }

        .spo-theme-minimal .spo-countdown-bg {
            stroke: rgba(0, 0, 0, 0.1);
        }

        .spo-theme-minimal .spo-countdown-progress {
            stroke: rgba(0, 0, 0, 0.6);
        }

        .spo-theme-bold .spo-countdown-bg {
            stroke: rgba(255, 255, 255, 0.2);
        }

        .spo-theme-bold .spo-countdown-progress {
            stroke: rgba(255, 255, 255, 0.9);
        }

        .spo-theme-elegant .spo-countdown-bg {
            stroke: rgba(255, 255, 255, 0.15);
        }

        .spo-theme-elegant .spo-countdown-progress {
            stroke: rgba(236, 240, 241, 0.8);
        }

        /* Ensure content doesn't overlap with countdown/CTA */
        .spo-content {
            padding-bottom: 30px;
            padding-left: 8px;
            padding-right: 8px;
        }
        
        /* Positions */
        .spo-position-top-left {
            top: 20px;
            left: 20px;
        }
        
        .spo-position-top-right {
            top: 20px;
            right: 20px;
        }
        
        .spo-position-bottom-left {
            bottom: 20px;
            left: 20px;
        }
        
        .spo-position-bottom-right {
            bottom: 20px;
            right: 20px;
        }
        
        .spo-position-center {
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        
        /* Animations */
        @keyframes slideInLeft {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideInUp {
            from { transform: translateY(100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes slideInDown {
            from { transform: translateY(-100%); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: scale(0.9); }
            to { opacity: 1; transform: scale(1); }
        }
        
        @keyframes bounceIn {
            0% { opacity: 0; transform: scale(0.3); }
            50% { opacity: 1; transform: scale(1.05); }
            70% { transform: scale(0.9); }
            100% { opacity: 1; transform: scale(1); }
        }
        
        .spo-animation-slide.spo-position-top-left,
        .spo-animation-slide.spo-position-bottom-left {
            animation: slideInLeft 0.5s ease-out;
        }
        
        .spo-animation-slide.spo-position-top-right,
        .spo-animation-slide.spo-position-bottom-right {
            animation: slideInRight 0.5s ease-out;
        }
        
        .spo-animation-slide.spo-position-center {
            animation: slideInDown 0.5s ease-out;
        }
        
        .spo-animation-fade {
            animation: fadeIn 0.5s ease-out;
        }
        
        .spo-animation-bounce {
            animation: bounceIn 0.6s ease-out;
        }
        
        @media (max-width: 768px) {
            .spo-overlay-item {
                max-width: 280px;
                padding: 12px;
                font-size: 13px;
            }
            
            .spo-position-top-left,
            .spo-position-top-right,
            .spo-position-bottom-left,
            .spo-position-bottom-right {
                left: 10px;
                right: 10px;
                max-width: calc(100vw - 20px);
            }
            
            .spo-position-bottom-left,
            .spo-position-bottom-right {
                bottom: 10px;
            }
            
            .spo-position-top-left,
            .spo-position-top-right {
                top: 10px;
            }
        }
        </style>
        
        <div id="spo-overlay-container" 
             data-delay="<?php echo esc_attr($settings['delay']); ?>"
             data-duration="<?php echo esc_attr($settings['duration']); ?>"
             data-interval="<?php echo esc_attr($settings['interval']); ?>"
             data-position="<?php echo esc_attr($settings['position']); ?>"
             data-animation="<?php echo esc_attr($settings['animation']); ?>"
             data-theme="<?php echo esc_attr($settings['theme']); ?>">
            <?php foreach ($active_items as $index => $item): ?>
                <?php if (!empty($item['url'])): ?>
                    <?php 
                    $target = isset($item['target']) ? $item['target'] : '_blank';
                    $rel = ($target === '_blank') ? 'rel="noopener"' : '';
                    ?>
                    <a href="<?php echo esc_url($item['url']); ?>" class="spo-overlay-item spo-clickable" data-index="<?php echo esc_attr($index); ?>" target="<?php echo esc_attr($target); ?>" <?php echo $rel; ?>>
                <?php else: ?>
                    <div class="spo-overlay-item" data-index="<?php echo esc_attr($index); ?>">
                <?php endif; ?>
                    <?php echo $this->generate_schema_markup($item); ?>
                    <div class="spo-countdown-timer">
                        <svg class="spo-countdown-svg" viewBox="0 0 36 36">
                            <path class="spo-countdown-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                            <path class="spo-countdown-progress" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        </svg>
                    </div>
                    <div class="spo-content">
                        <?php echo wpautop(wp_kses_post($item['content'])); ?>
                        <?php if (!empty($item['author'])): ?>
                            <div class="spo-author">— <?php echo esc_html($item['author']); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($item['url']) && !empty($item['cta'])): ?>
                        <div class="spo-cta-message">
                            <?php echo esc_html($item['cta']); ?> →
                        </div>
                    <?php endif; ?>
                <?php if (!empty($item['url'])): ?>
                    </a>
                <?php else: ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <!-- Social Proof Overlays JavaScript -->
        <script>
        jQuery(document).ready(function($) {
            var container = $("#spo-overlay-container");
            if (!container.length) return;
            
            var settings = {
                delay: parseInt(container.data("delay")) || 3000,
                duration: parseInt(container.data("duration")) || 5000,
                interval: parseInt(container.data("interval")) || 8000,
                position: container.data("position") || "bottom-left",
                animation: container.data("animation") || "slide",
                theme: container.data("theme") || "modern"
            };
            
            var items = container.find(".spo-overlay-item");
            var currentIndex = 0;
            var isPlaying = false;
            
            // Apply theme and position classes
            container.addClass("spo-position-" + settings.position);
            items.addClass("spo-theme-" + settings.theme + " spo-animation-" + settings.animation);
            
            function showOverlay() {
                if (items.length === 0 || isPlaying) return;
                
                isPlaying = true;
                var currentItem = items.eq(currentIndex);
                var progressCircle = currentItem.find('.spo-countdown-progress');
                var countdownInterval;
                
                // Reset countdown to start position before showing
                progressCircle.css('stroke-dashoffset', 0);

                currentItem.fadeIn(300, function() {
                    // Show countdown timer and start animation
                    currentItem.addClass('spo-timer-ready');
                    var startTime = Date.now();
                    var pausedTime = 0;
                    var isPaused = false;
                    var pauseStartTime = 0;
                    var overlayTimeout;
                    
                    // Function to start/restart the main overlay timeout
                    function startOverlayTimeout() {
                        var remainingTime = settings.duration - (Date.now() - startTime - pausedTime);
                        if (remainingTime <= 0) {
                            fadeOutOverlay();
                            return;
                        }
                        
                        overlayTimeout = setTimeout(function() {
                            fadeOutOverlay();
                        }, remainingTime);
                    }
                    
                    // Function to fade out overlay
                    function fadeOutOverlay() {
                        if (countdownInterval) {
                            clearInterval(countdownInterval);
                        }
                        if (overlayTimeout) {
                            clearTimeout(overlayTimeout);
                        }
                        currentItem.fadeOut(300, function() {
                            // Reset countdown for next use and remove hover handlers
                            currentItem.removeClass('spo-timer-ready').off('mouseenter mouseleave');
                            progressCircle.css({'stroke-dashoffset': 0, 'opacity': '1'});
                            isPlaying = false;
                            currentIndex = (currentIndex + 1) % items.length;
                            
                            setTimeout(showOverlay, settings.interval);
                        });
                    }
                    
                    // Start initial timeout
                    startOverlayTimeout();
                    
                    // Animate countdown
                    countdownInterval = setInterval(function() {
                        if (isPaused) return;
                        
                        var elapsed = Date.now() - startTime - pausedTime;
                        var progress = elapsed / settings.duration;
                        var dashOffset = 100 * progress;
                        
                        if (progress >= 1) {
                            clearInterval(countdownInterval);
                            dashOffset = 100;
                        }
                        
                        progressCircle.css('stroke-dashoffset', dashOffset);
                    }, 50);
                    
                    // Hover pause/resume functionality
                    currentItem.off('mouseenter mouseleave').on('mouseenter', function() {
                        if (!isPaused) {
                            isPaused = true;
                            pauseStartTime = Date.now();
                            progressCircle.css('opacity', '0.5');
                            
                            // Clear the main timeout
                            if (overlayTimeout) {
                                clearTimeout(overlayTimeout);
                            }
                        }
                    }).on('mouseleave', function() {
                        if (isPaused) {
                            isPaused = false;
                            pausedTime += Date.now() - pauseStartTime;
                            progressCircle.css('opacity', '1');
                            
                            // Restart the main timeout with remaining time
                            startOverlayTimeout();
                        }
                    });
                    
                    // Store references for click handler
                    currentItem.data('overlayTimeout', overlayTimeout);
                    currentItem.data('fadeOutOverlay', fadeOutOverlay);
                });
            }
            
            // Click behavior - only dismiss non-clickable items
            items.on("click", function(e) {
                if (!$(this).hasClass("spo-clickable")) {
                    e.preventDefault();
                    var fadeOutOverlay = $(this).data('fadeOutOverlay');
                    
                    if (fadeOutOverlay) {
                        fadeOutOverlay();
                    }
                }
                // Clickable items will follow their href naturally
            });
            
            // Start the cycle
            setTimeout(showOverlay, settings.delay);
        });
        </script>
        <?php
    }
    
    private function generate_schema_markup($item) {
        $schema = '';
        
        switch ($item['type']) {
            case 'review':
                $schema_data = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'Review',
                    'reviewBody' => wp_strip_all_tags($item['content']),
                    'author' => array(
                        '@type' => 'Person',
                        'name' => $item['author']
                    ),
                    'itemReviewed' => array(
                        '@type' => 'Organization',
                        'name' => get_bloginfo('name')
                    )
                );
                
                // Add URL if provided
                if (!empty($item['url'])) {
                    $schema_data['url'] = $item['url'];
                }
                
                $schema = sprintf('<script type="application/ld+json">%s</script>', json_encode($schema_data));
                break;
                
            case 'faq':
                $schema_data = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'Question',
                    'name' => wp_strip_all_tags($item['content']),
                    'acceptedAnswer' => array(
                        '@type' => 'Answer',
                        'text' => wp_strip_all_tags($item['content'])
                    )
                );
                
                // Add URL if provided
                if (!empty($item['url'])) {
                    $schema_data['url'] = $item['url'];
                }
                
                $schema = sprintf('<script type="application/ld+json">%s</script>', json_encode($schema_data));
                break;
                
            case 'nugget':
            case 'stat':
                $schema_data = array(
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'name' => get_bloginfo('name'),
                    'description' => wp_strip_all_tags($item['content'])
                );
                
                // Add URL if provided
                if (!empty($item['url'])) {
                    $schema_data['url'] = $item['url'];
                }
                
                $schema = sprintf('<script type="application/ld+json">%s</script>', json_encode($schema_data));
                break;
        }
        
        return $schema;
    }
    
    private function render_display_groups($groups) {
        if (empty($groups)) {
            return;
        }
        
        foreach ($groups as $index => $group) {
            ?>
            <div class="group-item">
                <div class="group-item-header">
                    <span class="group-item-title"><?php echo esc_html($group['name']); ?></span>
                    <span class="delete-group" data-index="<?php echo $index; ?>">Delete</span>
                </div>
                <table class="form-table">
                    <tr>
                        <th>Group Name</th>
                        <td>
                            <input type="text" name="<?php echo $this->option_name; ?>[display_groups][<?php echo $index; ?>][name]" value="<?php echo esc_attr($group['name']); ?>" style="width:100%;" required />
                        </td>
                    </tr>
                    <tr>
                        <th>Description</th>
                        <td>
                            <textarea name="<?php echo $this->option_name; ?>[display_groups][<?php echo $index; ?>][description]" rows="2" style="width:100%;" placeholder="Optional description"><?php echo esc_textarea($group['description']); ?></textarea>
                        </td>
                    </tr>
                </table>
            </div>
            <?php
        }
    }
    
    private function render_groups_checkboxes($item_index, $selected_groups = array()) {
        $settings = $this->get_settings();
        $groups = isset($settings['display_groups']) ? $settings['display_groups'] : array();
        
        if (empty($groups)) {
            echo '<p><em>No display groups created yet. <a href="#" onclick="jQuery(\'.spo-tab-button[data-tab=groups]\').click(); return false;">Create a group</a> first.</em></p>';
            return;
        }
        
        echo '<div class="spo-groups-checkboxes">';
        foreach ($groups as $group_index => $group) {
            $checked = in_array($group_index, $selected_groups) ? 'checked' : '';
            echo '<label style="display: block; margin-bottom: 5px;">';
            echo '<input type="checkbox" name="' . $this->option_name . '[content_items][' . $item_index . '][groups][]" value="' . $group_index . '" ' . $checked . ' /> ';
            echo esc_html($group['name']);
            echo '</label>';
        }
        echo '</div>';
    }
    
    // SIMPLIFIED META BOX SYSTEM
    public function add_meta_boxes() {
        $post_types = get_post_types(array('public' => true), 'names');
        
        foreach ($post_types as $post_type) {
            if ($post_type === 'attachment') continue;
            
            add_meta_box(
                'spo_display_group',
                'Social Proof Overlays',
                array($this, 'render_meta_box'),
                $post_type,
                'side',
                'default'
            );
        }
    }
    
    public function render_meta_box($post) {
        $selected_group = get_post_meta($post->ID, '_spo_display_group', true);
        $settings = $this->get_settings();
        $groups = isset($settings['display_groups']) ? array_values($settings['display_groups']) : array();
        
        wp_nonce_field('spo_meta_save', 'spo_meta_nonce');
        ?>
        <p>
            <label for="spo_display_group"><strong>Display Group:</strong></label><br>
            <select name="spo_display_group" id="spo_display_group" style="width: 100%;">
                <option value="">None (no overlays)</option>
                <?php foreach ($groups as $index => $group): ?>
                    <option value="<?php echo esc_attr($index); ?>" <?php selected($selected_group, strval($index)); ?>>
                        <?php echo esc_html($group['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        
        <?php if (!empty($groups) && !empty($selected_group) && isset($groups[intval($selected_group)])): ?>
            <p><small><strong>Description:</strong> <?php echo esc_html($groups[intval($selected_group)]['description']); ?></small></p>
        <?php endif; ?>
        
        <script>
        jQuery(document).ready(function($) {
            // Double-save approach for maximum compatibility
            $('form#post').on('submit', function() {
                var postId = $('#post_ID').val();
                var groupValue = $('#spo_display_group').val();
                var nonce = $('input[name="spo_meta_nonce"]').val();
                
                // AJAX save as backup
                $.post(ajaxurl, {
                    action: 'spo_meta_save',
                    post_id: postId,
                    group_value: groupValue,
                    nonce: nonce
                });
            });
        });
        </script>
        <?php
    }
    
    // Primary save method
    public function save_meta_data($post_id) {
    $this->debug_log("=== SAVE START for post $post_id ===");
    
    // Skip if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        $this->debug_log("Skipped: AUTOSAVE");
        return;
    }
    
    // Skip if this is a revision
    if (wp_is_post_revision($post_id)) {
        $this->debug_log("Skipped: REVISION");
        return;
    }
    
    // Check if our nonce is set
    if (!isset($_POST['spo_meta_nonce'])) {
        $this->debug_log("Skipped: NO NONCE - Available POST keys: " . implode(', ', array_keys($_POST)));
        return;
    }
    
    // Verify nonce
    if (!wp_verify_nonce($_POST['spo_meta_nonce'], 'spo_meta_save')) {
        $this->debug_log("Skipped: NONCE FAILED");
        return;
    }
    
    // Check user permissions
    if (!current_user_can('edit_post', $post_id)) {
        $this->debug_log("Skipped: NO PERMISSION");
        return;
    }
    
    // Check if field exists
    if (!isset($_POST['spo_display_group'])) {
        $this->debug_log("Field not found in POST data");
        return;
    }
    
    $group = sanitize_text_field($_POST['spo_display_group']);
    $this->debug_log("Attempting to save group: '$group'");
    
    // Get current value before saving
    $current_value = get_post_meta($post_id, '_spo_display_group', true);
    $this->debug_log("Current value in DB: '$current_value'");
    
    // FIXED: Check for empty string, not empty() which treats '0' as empty
    if ($group === '') {
        $result = delete_post_meta($post_id, '_spo_display_group');
        $this->debug_log("Deleted meta, result: " . ($result ? 'SUCCESS' : 'FAILED'));
    } else {
        $result = update_post_meta($post_id, '_spo_display_group', $group);
        $this->debug_log("Updated meta, result: " . ($result ? 'SUCCESS' : 'NO CHANGE'));
    }
    
    // Verify what's actually saved
    $saved_value = get_post_meta($post_id, '_spo_display_group', true);
    $this->debug_log("Final verification - value in DB: '$saved_value'");
    $this->debug_log("=== SAVE END ===");
}
    
    // AJAX backup save method
    public function ajax_meta_save() {
    $this->debug_log("AJAX save called");
    
    if (!current_user_can('edit_posts')) {
        $this->debug_log("AJAX - User cannot edit posts");
        wp_die('Unauthorized');
    }
    
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'spo_meta_save')) {
        $this->debug_log("AJAX - Nonce failed");
        wp_die('Nonce failed');
    }
    
    $post_id = intval($_POST['post_id']);
    $group_value = sanitize_text_field($_POST['group_value']);
    
    $this->debug_log("AJAX - Saving group '$group_value' for post $post_id");
    
    // FIXED: Check for empty string, not empty()
    if ($group_value === '') {
        delete_post_meta($post_id, '_spo_display_group');
    } else {
        update_post_meta($post_id, '_spo_display_group', $group_value);
    }
    
    // Verify save
    $saved = get_post_meta($post_id, '_spo_display_group', true);
    $this->debug_log("AJAX - Verified saved value: '$saved'");
    
    wp_die("AJAX Save complete - Post: $post_id, Group: '$group_value', Verified: '$saved'");
}
}

// Initialize the plugin
new SocialProofOverlays();
?>