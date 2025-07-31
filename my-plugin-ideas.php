<?php
/**
 * Plugin Name: My Plugin Ideas Dashboard Widget
 * Plugin URI: https://virtualmarketadvantage.com
 * Description: A dashboard widget for managing plugin ideas and concepts
 * Version: 1.0.0
 * Author: Virtual Market Advantage, INC
 * License: GPL v2 or later
 * Text Domain: my-plugin-ideas
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class IdeaToRealityPlugin {
    
    public function __construct() {
        add_action('init', array($this, 'register_post_type'), 0);
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('save_post', array($this, 'save_meta_boxes'));
        add_action('wp_ajax_create_idea', array($this, 'ajax_create_idea'));
        add_action('wp_ajax_enhance_idea', array($this, 'ajax_enhance_idea'));
        add_action('add_meta_boxes', array($this, 'remove_default_meta_boxes'), 999);
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('admin_post_export_idea_pdf', array($this, 'export_idea_pdf'));
    }
    
    public function register_post_type() {
        // Check if post type already exists
        if (post_type_exists('idea_note')) {
            return;
        }
        
        $args = array(
            'label' => 'Ideas',
            'labels' => array(
                'name' => 'Ideas',
                'singular_name' => 'Idea',
                'menu_name' => 'Ideas',
                'add_new' => 'Add New Idea',
                'add_new_item' => 'Add New Idea',
                'edit_item' => 'Edit Idea',
                'new_item' => 'New Idea',
                'view_item' => 'View Idea',
                'search_items' => 'Search Ideas',
                'not_found' => 'No ideas found',
                'not_found_in_trash' => 'No ideas found in trash'
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_admin_bar' => false,
            'show_in_nav_menus' => false,
            'can_export' => true,
            'has_archive' => false,
            'exclude_from_search' => true,
            'publicly_queryable' => false,
            'supports' => array('title', 'comments', 'author'),
            'capability_type' => 'post'
        );
        
        register_post_type('idea_note', $args);
        
        // Flush rewrite rules only on activation
        if (get_option('idea_plugin_flush_rewrite') !== 'done') {
            flush_rewrite_rules();
            update_option('idea_plugin_flush_rewrite', 'done');
        }
    }
    
    public function add_dashboard_widget() {
        if (current_user_can('edit_posts')) {
            wp_add_dashboard_widget(
                'idea_to_reality_widget',
                'My Plugin Ideas',
                array($this, 'dashboard_widget_content')
            );
        }
    }
    
    public function dashboard_widget_content() {
        // Get recent ideas
        $recent_ideas = get_posts(array(
            'post_type' => 'idea_note',
            'numberposts' => 5,
            'post_status' => 'any',
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        ?>
        <div id="idea-widget-container">
            <div class="idea-header">
                <div class="idea-input-group">
                    <input type="text" id="new-idea-title" placeholder="Enter a new plugin idea..." class="idea-input">
                    <button type="button" id="create-idea-btn" class="button button-primary">Add Idea</button>
                </div>
                <div class="idea-actions">
                    <a href="<?php echo admin_url('edit.php?post_type=idea_note'); ?>" class="idea-action-btn" title="View All Ideas">
                        <span class="dashicons dashicons-list-view"></span>
                    </a>
                    <a href="<?php echo admin_url('admin.php?page=my-plugin-ideas-settings'); ?>" class="idea-action-btn" title="Settings">
                        <span class="dashicons dashicons-admin-generic"></span>
                    </a>
                </div>
            </div>
            
            <div id="idea-status" class="idea-status" style="display: none;"></div>
            
            <?php if (!empty($recent_ideas)): ?>
                <h4>Recent Ideas</h4>
                <div class="idea-table-container">
                    <table class="idea-table">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th>GitHub</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_ideas as $idea): 
                                $status = get_post_meta($idea->ID, 'idea_status', true) ?: 'concept';
                                $github_url = get_post_meta($idea->ID, 'idea_github', true);
                                $status_labels = array(
                                    'concept' => 'Concept',
                                    'planning' => 'Planning',
                                    'development' => 'Development',
                                    'testing' => 'Testing',
                                    'completed' => 'Completed',
                                    'abandoned' => 'Abandoned'
                                );
                                $status_label = $status_labels[$status] ?? 'Concept';
                                $status_class = 'status-' . $status;
                            ?>
                                <tr class="idea-row">
                                    <td class="idea-title">
                                        <a href="<?php echo get_edit_post_link($idea->ID); ?>" class="idea-link">
                                            <?php echo esc_html($idea->post_title); ?>
                                        </a>
                                    </td>
                                    <td class="idea-date">
                                        <?php echo get_the_date('M j, Y', $idea->ID); ?>
                                    </td>
                                    <td class="idea-status">
                                        <span class="status-badge <?php echo $status_class; ?>">
                                            <?php echo $status_label; ?>
                                        </span>
                                    </td>
                                    <td class="idea-github">
                                        <?php if (!empty($github_url)): ?>
                                            <a href="<?php echo esc_url($github_url); ?>" target="_blank" rel="noopener noreferrer" class="github-link" title="View on GitHub">
                                                <span class="dashicons dashicons-external"></span>
                                            </a>
                                        <?php else: ?>
                                            <span class="no-github">—</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <p class="idea-footer">
                    <a href="<?php echo admin_url('edit.php?post_type=idea_note'); ?>" class="button button-secondary">View All Ideas</a>
                </p>
            <?php else: ?>
                <p class="idea-empty">No ideas yet. Add your first plugin idea above!</p>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#create-idea-btn').on('click', function() {
                var title = $('#new-idea-title').val().trim();
                if (!title) {
                    alert('Please enter an idea title');
                    return;
                }
                
                var $btn = $(this);
                var $status = $('#idea-status');
                
                $btn.prop('disabled', true).text('Creating...');
                $status.html('Creating idea...').removeClass('notice-success notice-error').addClass('notice-info').show();
                
                $.post(ajaxurl, {
                    action: 'create_idea',
                    title: title,
                    nonce: '<?php echo wp_create_nonce('create_idea'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        $status.html('✅ Idea created successfully!').removeClass('notice-info notice-error').addClass('notice-success');
                        $('#new-idea-title').val('');
                        // Reload the page to show the new idea
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        $status.html('❌ ' + response.data).removeClass('notice-info notice-success').addClass('notice-error');
                    }
                })
                .fail(function() {
                    $status.html('❌ Network error occurred').removeClass('notice-info notice-success').addClass('notice-error');
                })
                .always(function() {
                    $btn.prop('disabled', false).text('Add Idea');
                });
            });
            
            // Allow Enter key to submit
            $('#new-idea-title').on('keypress', function(e) {
                if (e.which === 13) {
                    $('#create-idea-btn').click();
                }
            });
        });
        </script>
        <?php
    }
    
    public function ajax_create_idea() {
        check_ajax_referer('create_idea', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $title = sanitize_text_field($_POST['title'] ?? '');
        if (empty($title)) {
            wp_send_json_error('Title is required');
        }
        
        $post_data = array(
            'post_title' => $title,
            'post_type' => 'idea_note',
            'post_status' => 'publish',
            'post_author' => get_current_user_id()
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id) {
            wp_send_json_success('Idea created successfully');
        } else {
            wp_send_json_error('Failed to create idea');
        }
    }
    
    public function ajax_enhance_idea() {
        check_ajax_referer('enhance_idea', 'nonce');
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('Permission denied');
        }
        
        $idea_text = sanitize_textarea_field($_POST['idea_text'] ?? '');
        $post_id = intval($_POST['post_id'] ?? 0);
        
        if (empty($idea_text)) {
            wp_send_json_error('Idea text is required');
        }
        
        if (!$post_id || get_post_type($post_id) !== 'idea_note') {
            wp_send_json_error('Invalid post ID');
        }
        
        // Check if OpenAI is enabled and configured
        $openai_enabled = get_option('idea_openai_enabled', '0');
        $api_key = get_option('idea_openai_api_key', '');
        
        if (!$openai_enabled || empty($api_key)) {
            wp_send_json_error('OpenAI integration is not configured');
        }
        
        // Create the enhancement prompt
        $prompt = $this->create_enhancement_prompt($idea_text);
        
        // Call OpenAI API
        $enhanced_text = $this->call_openai_api($api_key, $prompt);
        
        if ($enhanced_text) {
            // Update the post meta with the enhanced text
            update_post_meta($post_id, 'idea_basic', $enhanced_text);
            wp_send_json_success($enhanced_text);
        } else {
            wp_send_json_error('Failed to enhance idea. Please check your OpenAI API key and try again.');
        }
    }
    
    private function create_enhancement_prompt($idea_text) {
        return "You are a WordPress plugin development expert. Please enhance and rewrite the following plugin idea description using proper WordPress terminology and best practices. Make it more detailed, professional, and comprehensive while maintaining the core concept.

Original idea: {$idea_text}

Please rewrite this as a detailed plugin description that includes:
- Clear problem statement and solution
- Target audience (WordPress users, developers, etc.)
- Key features and functionality
- Technical considerations (hooks, filters, custom post types, etc.)
- WordPress integration points
- User experience improvements

Use proper WordPress terminology like 'hooks', 'filters', 'custom post types', 'meta boxes', 'admin pages', 'shortcodes', 'widgets', 'REST API', 'AJAX', etc.

Keep the response focused and professional, suitable for a plugin development brief.";
    }
    
    private function call_openai_api($api_key, $prompt) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => 'You are a WordPress plugin development expert who writes clear, professional plugin descriptions using proper WordPress terminology.'
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'max_tokens' => 1000,
            'temperature' => 0.7
        );
        
        $response = wp_remote_post($url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ),
            'body' => json_encode($body),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (isset($data['choices'][0]['message']['content'])) {
            return trim($data['choices'][0]['message']['content']);
        }
        
        return false;
    }
    
    public function remove_default_meta_boxes() {
        // Only remove meta boxes for our custom post type
        if (get_post_type() === 'idea_note') {
            remove_meta_box('commentstatusdiv', 'idea_note', 'normal'); // Discussion
            remove_meta_box('slugdiv', 'idea_note', 'normal'); // Slug
            remove_meta_box('authordiv', 'idea_note', 'normal'); // Author
        }
    }
    
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=idea_note',
            'Plugin Ideas Settings',
            'Settings',
            'manage_options',
            'my-plugin-ideas-settings',
            array($this, 'settings_page')
        );
    }
    
    public function enqueue_admin_scripts($hook) {
        // Only load on dashboard and our settings page
        if ($hook === 'index.php' || strpos($hook, 'my-plugin-ideas-settings') !== false) {
            wp_enqueue_style('dashicons');
            wp_add_inline_style('dashicons', $this->get_custom_css());
        }
    }
    
    public function get_custom_css() {
        return '
        .idea-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .idea-input-group {
            display: flex;
            flex: 1;
            margin-right: 10px;
        }
        
        .idea-input {
            flex: 1;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
        }
        
        .idea-actions {
            display: flex;
            gap: 5px;
        }
        
        .idea-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #fff;
            color: #666;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .idea-action-btn:hover {
            background: #f0f0f0;
            color: #333;
            border-color: #999;
        }
        
        .idea-status {
            margin: 10px 0;
            padding: 10px;
            border-radius: 4px;
        }
        
        .idea-table-container {
            margin: 10px 0;
            overflow-x: auto;
        }
        
        .idea-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            background: #fff;
            border: 1px solid #e1e1e1;
            border-radius: 4px;
            overflow: hidden;
        }
        
        .idea-table thead {
            background: #f9f9f9;
            border-bottom: 2px solid #e1e1e1;
        }
        
        .idea-table th {
            padding: 8px 12px;
            text-align: left;
            font-weight: 600;
            color: #333;
            border-right: 1px solid #e1e1e1;
        }
        
        .idea-table th:last-child {
            border-right: none;
        }
        
        .idea-table td {
            padding: 8px 12px;
            border-bottom: 1px solid #f0f0f0;
            border-right: 1px solid #f0f0f0;
            vertical-align: middle;
        }
        
        .idea-table td:last-child {
            border-right: none;
        }
        
        .idea-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .idea-table tbody tr:hover {
            background-color: #f9f9f9;
        }
        
        .idea-title {
            font-weight: 500;
        }
        
        .idea-link {
            text-decoration: none;
            color: #0073aa;
            font-weight: 500;
        }
        
        .idea-link:hover {
            color: #005177;
            text-decoration: underline;
        }
        
        .idea-date {
            color: #666;
            font-size: 12px;
        }
        
        .idea-status {
            text-align: center;
        }
        
        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-concept {
            background-color: #e3f2fd;
            color: #1976d2;
        }
        
        .status-planning {
            background-color: #fff3e0;
            color: #f57c00;
        }
        
        .status-development {
            background-color: #e8f5e8;
            color: #388e3c;
        }
        
        .status-testing {
            background-color: #fff8e1;
            color: #fbc02d;
        }
        
        .status-completed {
            background-color: #e8f5e8;
            color: #2e7d32;
        }
        
        .status-abandoned {
            background-color: #ffebee;
            color: #d32f2f;
        }
        
        .idea-github {
            text-align: center;
        }
        
        .github-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 4px;
            background-color: #24292e;
            color: #fff;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .github-link:hover {
            background-color: #0366d6;
            color: #fff;
            transform: scale(1.1);
        }
        
        .no-github {
            color: #ccc;
            font-style: italic;
        }
        
        .idea-footer {
            margin-top: 15px;
        }
        
        .idea-empty {
            color: #666;
            font-style: italic;
        }
        
        .notice-success { 
            background-color: #d4edda; 
            border: 1px solid #c3e6cb; 
            color: #155724; 
        }
        .notice-error { 
            background-color: #f8d7da; 
            border: 1px solid #f5c6cb; 
            color: #721c24; 
        }
        .notice-info { 
            background-color: #d1ecf1; 
            border: 1px solid #bee5eb; 
            color: #0c5460; 
        }
        ';
    }
    
    public function settings_page() {
        // Save settings if form is submitted
        if (isset($_POST['submit']) && wp_verify_nonce($_POST['idea_settings_nonce'], 'idea_settings')) {
            update_option('idea_openai_api_key', sanitize_text_field($_POST['openai_api_key'] ?? ''));
            update_option('idea_openai_enabled', isset($_POST['openai_enabled']) ? '1' : '0');
            echo '<div class="notice notice-success"><p>Settings saved successfully!</p></div>';
        }
        
        $api_key = get_option('idea_openai_api_key', '');
        $enabled = get_option('idea_openai_enabled', '0');
        ?>
        <div class="wrap">
            <h1>Plugin Ideas Settings</h1>
            
            <form method="post" action="">
                <?php wp_nonce_field('idea_settings', 'idea_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="openai_enabled">Enable OpenAI Integration</label>
                        </th>
                        <td>
                            <input type="checkbox" id="openai_enabled" name="openai_enabled" value="1" <?php checked($enabled, '1'); ?>>
                            <label for="openai_enabled">Enable AI-powered idea suggestions and analysis</label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="openai_api_key">OpenAI API Key</label>
                        </th>
                        <td>
                            <input type="password" id="openai_api_key" name="openai_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text">
                            <p class="description">Enter your OpenAI API key to enable AI features. Get one at <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a></p>
                        </td>
                    </tr>
                </table>
                
                <p class="submit">
                    <input type="submit" name="submit" id="submit" class="button button-primary" value="Save Settings">
                </p>
            </form>
            
            <?php if ($enabled && !empty($api_key)): ?>
                <div class="card">
                    <h2>AI Features Available</h2>
                    <p>With OpenAI integration enabled, you can:</p>
                    <ul>
                        <li>Get AI-powered suggestions for plugin ideas</li>
                        <li>Analyze existing ideas for market potential</li>
                        <li>Generate detailed descriptions for your ideas</li>
                        <li>Get technical implementation suggestions</li>
                    </ul>
                </div>
            <?php endif; ?>
            

        </div>
        <?php
    }
    
    public function add_meta_boxes() {
        add_meta_box(
            'idea_basic_meta',
            'Idea Description',
            array($this, 'render_basic_idea_meta_box'),
            'idea_note',
            'normal',
            'high'
        );
        
        add_meta_box(
            'idea_status_meta',
            'Idea Status',
            array($this, 'render_status_meta_box'),
            'idea_note',
            'side',
            'default'
        );
        
        add_meta_box(
            'idea_github_meta',
            'GitHub Repository',
            array($this, 'render_github_meta_box'),
            'idea_note',
            'side',
            'default'
        );
        
        add_meta_box(
            'idea_export_meta',
            'Export Options',
            array($this, 'render_export_meta_box'),
            'idea_note',
            'side',
            'low'
        );
    }
    
    public function render_basic_idea_meta_box($post) {
        wp_nonce_field('idea_basic_meta_nonce', 'idea_basic_meta_nonce_field');
        $basic_idea = get_post_meta($post->ID, 'idea_basic', true);
        $openai_enabled = get_option('idea_openai_enabled', '0');
        $api_key = get_option('idea_openai_api_key', '');
        ?>
        <div class="idea-basic-meta-container">
            <label for="idea_basic">Describe your plugin idea:</label>
            <textarea 
                name="idea_basic" 
                id="idea_basic" 
                rows="8" 
                style="width:100%; padding: 12px; border: 1px solid #ddd; border-radius: 4px;" 
                placeholder="Describe the basic idea for this plugin, what problem it solves, target audience, etc..."
            ><?php echo esc_textarea($basic_idea); ?></textarea>
            
            <?php if ($openai_enabled && !empty($api_key) && !empty($basic_idea)): ?>
                <div style="margin-top: 10px;">
                    <button type="button" id="enhance-idea-btn" class="button button-secondary">
                        <span class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></span>
                        Enhance with AI
                    </button>
                    <span id="enhance-status" style="margin-left: 10px; display: none;"></span>
                </div>
            <?php elseif (empty($basic_idea)): ?>
                <p style="color: #666; font-style: italic; margin-top: 10px;">Add a description above to enable AI enhancement</p>
            <?php elseif (!$openai_enabled || empty($api_key)): ?>
                <p style="color: #666; font-style: italic; margin-top: 10px;">
                    <a href="<?php echo admin_url('admin.php?page=my-plugin-ideas-settings'); ?>">Configure OpenAI</a> to enable AI enhancement
                </p>
            <?php endif; ?>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#enhance-idea-btn').on('click', function() {
                var $btn = $(this);
                var $status = $('#enhance-status');
                var $textarea = $('#idea_basic');
                var originalText = $textarea.val();
                
                if (!originalText.trim()) {
                    alert('Please add a description first');
                    return;
                }
                
                $btn.prop('disabled', true).html('<span class="dashicons dashicons-update" style="margin-right: 5px; animation: spin 1s linear infinite;"></span>Enhancing...');
                $status.html('Enhancing your idea with AI...').show();
                
                $.post(ajaxurl, {
                    action: 'enhance_idea',
                    idea_text: originalText,
                    post_id: <?php echo $post->ID; ?>,
                    nonce: '<?php echo wp_create_nonce('enhance_idea'); ?>'
                })
                .done(function(response) {
                    if (response.success) {
                        $textarea.val(response.data);
                        $status.html('✅ Idea enhanced successfully!').css('color', 'green');
                        setTimeout(function() {
                            $status.fadeOut();
                        }, 3000);
                    } else {
                        $status.html('❌ ' + response.data).css('color', 'red');
                    }
                })
                .fail(function() {
                    $status.html('❌ Network error occurred').css('color', 'red');
                })
                .always(function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="margin-right: 5px;"></span>Enhance with AI');
                });
            });
        });
        </script>
        
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        <?php
    }
    
    public function render_status_meta_box($post) {
        wp_nonce_field('idea_status_meta_nonce', 'idea_status_meta_nonce_field');
        $status = get_post_meta($post->ID, 'idea_status', true) ?: 'concept';
        ?>
        <div class="idea-status-meta-container">
            <label for="idea_status">Current Status:</label>
            <select name="idea_status" id="idea_status" style="width: 100%; margin-top: 5px;">
                <option value="concept" <?php selected($status, 'concept'); ?>>Concept</option>
                <option value="planning" <?php selected($status, 'planning'); ?>>Planning</option>
                <option value="development" <?php selected($status, 'development'); ?>>In Development</option>
                <option value="testing" <?php selected($status, 'testing'); ?>>Testing</option>
                <option value="completed" <?php selected($status, 'completed'); ?>>Completed</option>
                <option value="abandoned" <?php selected($status, 'abandoned'); ?>>Abandoned</option>
            </select>
        </div>
        <?php
    }
    
    public function render_github_meta_box($post) {
        wp_nonce_field('idea_github_meta_nonce', 'idea_github_meta_nonce_field');
        $github_url = get_post_meta($post->ID, 'idea_github', true);
        ?>
        <div class="idea-github-meta-container">
            <label for="idea_github">GitHub Repository URL:</label>
            <input 
                type="url" 
                name="idea_github" 
                id="idea_github" 
                value="<?php echo esc_attr($github_url); ?>" 
                placeholder="https://github.com/username/repository"
                style="width: 100%; margin-top: 5px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
            >
            <p class="description" style="margin-top: 5px; font-size: 12px; color: #666;">
                Enter the GitHub repository URL for this plugin idea
            </p>
            
            <?php if (!empty($github_url)): ?>
                <div style="margin-top: 10px;">
                    <a href="<?php echo esc_url($github_url); ?>" target="_blank" rel="noopener noreferrer" class="button button-secondary" style="width: 100%; text-align: center;">
                        <span class="dashicons dashicons-external" style="margin-right: 5px;"></span>
                        View on GitHub
                    </a>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    public function render_export_meta_box($post) {
        ?>
        <div class="idea-export-meta-container">
            <p style="margin-bottom: 15px; color: #666; font-size: 12px;">
                Export this idea as a PDF for sharing or documentation.
            </p>
            
            <a href="<?php echo admin_url('admin-post.php?action=export_idea_pdf&idea_id=' . $post->ID . '&_wpnonce=' . wp_create_nonce('export_idea_pdf')); ?>" 
               class="button button-primary" 
               target="_blank"
               rel="noopener noreferrer"
               style="width: 100%; text-align: center; display: block; text-decoration: none; margin-bottom: 10px;">
                <span class="dashicons dashicons-pdf" style="margin-right: 5px;"></span>
                Export as PDF
            </a>
            
            <p style="font-size: 11px; color: #999; margin: 0;">
                The PDF will include the title, description, status, and GitHub link.
            </p>
        </div>
        <?php
    }
    
    public function save_meta_boxes($post_id) {
        // If this is an autosave, our form has not been submitted, so we don't want to do anything
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        
        // Check the user's permissions
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
        
        // Check if this is our post type
        if (get_post_type($post_id) !== 'idea_note') {
            return;
        }
        
        // Save basic idea
        if (isset($_POST['idea_basic_meta_nonce_field']) && wp_verify_nonce($_POST['idea_basic_meta_nonce_field'], 'idea_basic_meta_nonce')) {
            if (isset($_POST['idea_basic'])) {
                $basic_idea = sanitize_textarea_field($_POST['idea_basic']);
                update_post_meta($post_id, 'idea_basic', $basic_idea);
            }
        }
        
        // Save status
        if (isset($_POST['idea_status_meta_nonce_field']) && wp_verify_nonce($_POST['idea_status_meta_nonce_field'], 'idea_status_meta_nonce')) {
            if (isset($_POST['idea_status'])) {
                $status = sanitize_text_field($_POST['idea_status']);
                update_post_meta($post_id, 'idea_status', $status);
            }
        }
        
        // Save GitHub link
        if (isset($_POST['idea_github_meta_nonce_field']) && wp_verify_nonce($_POST['idea_github_meta_nonce_field'], 'idea_github_meta_nonce')) {
            if (isset($_POST['idea_github'])) {
                $github_url = esc_url_raw($_POST['idea_github']);
                update_post_meta($post_id, 'idea_github', $github_url);
            }
        }
    }
    
    public function export_idea_pdf() {
        // Check permissions
        if (!current_user_can('edit_posts')) {
            wp_die('Permission denied');
        }
        
        // Verify nonce
        if (!wp_verify_nonce($_GET['_wpnonce'], 'export_idea_pdf')) {
            wp_die('Security check failed');
        }
        
        // Get idea ID
        $idea_id = intval($_GET['idea_id'] ?? 0);
        if (!$idea_id || get_post_type($idea_id) !== 'idea_note') {
            wp_die('Invalid idea ID');
        }
        
        // Get the idea
        $idea = get_post($idea_id);
        if (!$idea) {
            wp_die('Idea not found');
        }
        
        // Generate HTML content
        $html_content = $this->generate_single_idea_html($idea);
        
        // Set headers for HTML display in browser (printable as PDF)
        $filename = sanitize_file_name($idea->post_title) . '-idea-' . date('Y-m-d') . '.html';
        header('Content-Type: text/html; charset=UTF-8');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // Output HTML content
        echo $html_content;
        exit;
    }
    
    private function generate_single_idea_html($idea) {
        $status = get_post_meta($idea->ID, 'idea_status', true) ?: 'concept';
        $description = get_post_meta($idea->ID, 'idea_basic', true);
        $github_url = get_post_meta($idea->ID, 'idea_github', true);
        
        $status_labels = array(
            'concept' => 'Concept',
            'planning' => 'Planning',
            'development' => 'Development',
            'testing' => 'Testing',
            'completed' => 'Completed',
            'abandoned' => 'Abandoned'
        );
        $status_label = $status_labels[$status] ?? 'Concept';
        
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>' . esc_html($idea->post_title) . ' - Plugin Idea</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 40px; 
            line-height: 1.6; 
            color: #333;
        }
        .header { 
            text-align: center; 
            border-bottom: 3px solid #0073aa; 
            padding-bottom: 30px; 
            margin-bottom: 40px; 
        }
        .header h1 { 
            color: #0073aa; 
            margin: 0 0 15px 0; 
            font-size: 32px;
            font-weight: bold;
        }
        .header .subtitle { 
            color: #666; 
            margin: 10px 0 0 0; 
            font-size: 16px;
        }
        .idea-info {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 30px;
            border-left: 5px solid #0073aa;
        }
        .idea-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e1e1e1;
        }
        .meta-item {
            text-align: center;
        }
        .meta-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 5px;
        }
        .meta-value {
            font-size: 16px;
            font-weight: bold;
            color: #333;
        }
        .idea-status { 
            display: inline-block; 
            padding: 8px 16px; 
            border-radius: 20px; 
            font-size: 12px; 
            font-weight: bold; 
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .status-concept { background: #e3f2fd; color: #1976d2; }
        .status-planning { background: #fff3e0; color: #f57c00; }
        .status-development { background: #e8f5e8; color: #388e3c; }
        .status-testing { background: #fff8e1; color: #fbc02d; }
        .status-completed { background: #e8f5e8; color: #2e7d32; }
        .status-abandoned { background: #ffebee; color: #d32f2f; }
        .idea-description { 
            background: #fff; 
            padding: 25px; 
            border-radius: 8px;
            border: 1px solid #e1e1e1;
            line-height: 1.8;
            font-size: 15px;
        }
        .github-section {
            margin-top: 30px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 8px;
            text-align: center;
        }
        .github-link { 
            color: #0366d6; 
            text-decoration: none; 
            font-weight: bold;
            font-size: 16px;
        }
        .github-link:hover { 
            text-decoration: underline; 
        }
        .footer {
            margin-top: 50px;
            padding-top: 20px;
            border-top: 1px solid #e1e1e1;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        @media print {
            body { 
                margin: 15px; 
                font-size: 12px;
            }
            .header { 
                margin-bottom: 15px; 
                padding-bottom: 10px;
                border-bottom: 2px solid #0073aa;
            }
            .header h1 { 
                font-size: 20px; 
                margin-bottom: 8px;
                margin-top: 0;
            }
            .header .subtitle { 
                font-size: 12px; 
                margin: 3px 0;
            }
            .idea-info {
                padding: 12px;
                margin-bottom: 15px;
                border-left: 3px solid #0073aa;
            }
            .idea-meta {
                margin-bottom: 12px;
                padding-bottom: 8px;
            }
            .idea-description { 
                padding: 12px;
                font-size: 12px;
                line-height: 1.5;
            }
            .github-section {
                margin-top: 12px;
                padding: 12px;
            }
            .footer {
                margin-top: 15px;
                padding-top: 8px;
                font-size: 10px;
            }
            .print-controls { display: none; }
        }
        .print-controls {
            position: fixed;
            top: 20px;
            right: 20px;
            background: #0073aa;
            color: white;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            z-index: 1000;
        }
        .print-controls button {
            background: white;
            color: #0073aa;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            margin: 0 5px;
            font-weight: bold;
        }
        .print-controls button:hover {
            background: #f0f0f0;
        }
    </style>
</head>
<body>
    <div class="print-controls">
        <button onclick="window.print()">🖨️ Print</button>
        <button onclick="window.close()">❌ Close</button>
    </div>
    
    <div class="header">
        <h1>' . esc_html($idea->post_title) . '</h1>
        <p class="subtitle">Plugin Idea Documentation</p>
        <p class="subtitle">Generated on ' . date('F j, Y \a\t g:i A') . '</p>
    </div>
    
    <div class="idea-info">
        <div class="idea-meta">
            <div class="meta-item">
                <div class="meta-label">Created</div>
                <div class="meta-value">' . get_the_date('F j, Y', $idea->ID) . '</div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Status</div>
                <div class="meta-value">
                    <span class="idea-status status-' . $status . '">' . $status_label . '</span>
                </div>
            </div>
            <div class="meta-item">
                <div class="meta-label">Author</div>
                <div class="meta-value">' . get_the_author_meta('display_name', $idea->post_author) . '</div>
            </div>
        </div>';
        
        if (!empty($description)) {
            $html .= '<div class="idea-description">' . nl2br(esc_html($description)) . '</div>';
        } else {
            $html .= '<div class="idea-description" style="color: #999; font-style: italic;">No description provided for this idea.</div>';
        }
        
        if (!empty($github_url)) {
            $html .= '<div class="github-section">
                <strong>GitHub Repository:</strong><br>
                <a href="' . esc_url($github_url) . '" class="github-link">' . esc_url($github_url) . '</a>
            </div>';
        }
        
        $html .= '</div>
    
    <div class="footer">
        <p>This document was generated by the My Plugin Ideas Dashboard Widget</p>
        <p>WordPress Plugin Management System</p>
    </div>
</body>
</html>';
        
        return $html;
    }
}

// Initialize the plugin
new IdeaToRealityPlugin();

// Activation hook
register_activation_hook(__FILE__, function() {
    // Clear the flush rewrite flag so it runs on next load
    delete_option('idea_plugin_flush_rewrite');
});

// Deactivation hook
register_deactivation_hook(__FILE__, function() {
    flush_rewrite_rules();
    delete_option('idea_plugin_flush_rewrite');
}); 