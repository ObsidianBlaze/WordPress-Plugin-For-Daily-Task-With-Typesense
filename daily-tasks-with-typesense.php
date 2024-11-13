<?php
/*
Plugin Name: Daily Tasks with Typesense
Description: A plugin to manage daily tasks with Typesense-powered search.
Version: 1.0
Author: Idada
*/

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

if (file_exists(plugin_dir_path(__FILE__) . 'vendor/autoload.php')) {
    require_once plugin_dir_path(__FILE__) . 'vendor/autoload.php';
} else {
    wp_die('Typesense library is missing. Run `composer install` to install it.');
}

// Include necessary files
include_once plugin_dir_path(__FILE__) . 'includes/typesense.php';
include_once plugin_dir_path(__FILE__) . 'includes/task-functions.php';

// Register activation and deactivation hooks
register_activation_hook(__FILE__, 'dtt_activate_plugin');
register_deactivation_hook(__FILE__, 'dtt_deactivate_plugin');

// Add an admin menu for the plugin
add_action('admin_menu', 'dtt_add_admin_menu');

add_action('admin_enqueue_scripts', 'dtt_enqueue_admin_styles');

add_action('admin_init', 'dtt_register_settings');


add_action('admin_menu', 'dtt_add_settings_menu');

add_action('admin_menu', 'dtt_register_admin_page');

//function dtt_enqueue_admin_styles($hook) {
//    if ($hook != 'toplevel_page_dtt-view-tasks') {
//        return;
//    }
//    wp_enqueue_style('dtt-admin-styles', plugin_dir_url(__FILE__) . 'css/admin-styles.css');
//}

function dtt_register_settings() {
    register_setting('dtt_settings', 'dtt_typesense_api_key');
    register_setting('dtt_settings', 'dtt_typesense_host');
    register_setting('dtt_settings', 'dtt_typesense_port');
}


function dtt_register_admin_page() {
    add_menu_page(
        'Daily Tasks',                 // Page title
        'Daily Tasks',                 // Menu title
        'manage_options',              // Capability
        'dtt-view-tasks',              // Menu slug
        'dtt_display_tasks_page',      // Callback function
        'dashicons-list-view',         // Icon
        20                             // Position
    );
}

function dtt_display_tasks_page() {
    // Display search form
    echo '<div class="wrap">';
    echo '<h1>All Daily Tasks</h1>';
    echo '<form method="get" action="">';
    echo '<input type="hidden" name="page" value="dtt-view-tasks" />';
    echo '<input type="text" name="task_search" placeholder="Search tasks..." value="' . esc_attr(isset($_GET['task_search']) ? $_GET['task_search'] : '') . '" />';
    echo '<input type="submit" class="button" value="Search" />';
    echo '</form>';

    // Table structure
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Title</th><th>Description</th><th>Due Date</th></tr></thead>';
    echo '<tbody>';

    // Fetch and display tasks
    $searchQuery = isset($_GET['task_search']) ? $_GET['task_search'] : '';  // Get search query from form input
    $tasks = dtt_get_tasks_from_typesense($searchQuery);  // Fetch tasks based on search query

    if (!empty($tasks)) {
        foreach ($tasks as $task) {
            $taskDocument = isset($task['document']) ? $task['document'] : null;
            if ($taskDocument) {
                echo '<tr>';
                echo '<td>' . esc_html($taskDocument['id']) . '</td>';
                echo '<td>' . esc_html($taskDocument['title']) . '</td>';
                echo '<td>' . esc_html($taskDocument['description']) . '</td>';
                echo '<td>' . esc_html(date('Y-m-d', $taskDocument['due_date'])) . '</td>';
                echo '</tr>';
            }
        }
    } else {
        echo '<tr><td colspan="4">No tasks found.</td></tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

function dtt_add_admin_menu() {
    add_menu_page(
        'Daily Tasks',                  // Page title
        'Daily Tasks',                  // Menu title
        'manage_options',               // Capability
        'daily-tasks',                  // Menu slug
        'dtt_display_task_page',        // Callback function
        'dashicons-list-view',          // Icon
        6                               // Position
    );
}

function dtt_enqueue_admin_styles() {
    wp_enqueue_style('dtt-admin-style', plugins_url('css/admin-style.css', __FILE__));
}


function dtt_add_settings_menu() {
    add_options_page(
        'Typesense Settings',               // Page title
        'Typesense Settings',               // Menu title
        'manage_options',                   // Capability
        'typesense-settings',               // Menu slug
        'dtt_display_settings_page'         // Callback function
    );
}

function dtt_display_settings_page() {
    ?>
    <div class="wrap">
        <h1>Typesense Settings</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('dtt_settings');
            do_settings_sections('dtt_settings');
            ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Typesense API Key</th>
                    <td>
                        <input type="text" name="dtt_typesense_api_key"
                               value="<?php echo esc_attr(get_option('dtt_typesense_api_key')); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Typesense Host</th>
                    <td>
                        <input type="text" name="dtt_typesense_host"
                               value="<?php echo esc_attr(get_option('dtt_typesense_host', 'localhost')); ?>" />
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Typesense Port</th>
                    <td>
                        <input type="text" name="dtt_typesense_port"
                               value="<?php echo esc_attr(get_option('dtt_typesense_port', '8108')); ?>" />
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

function dtt_display_task_page() {
    ?>
    <div class="wrap">
        <h1>Daily Tasks</h1>

        <!-- Task Creation Form -->
        <h2>Add a New Task</h2>
        <form method="post" action="">
            <label for="task-title">Title:</label>
            <input type="text" name="task-title" id="task-title" required>

            <label for="task-description">Description:</label>
            <textarea name="task-description" id="task-description" required></textarea>

            <label for="task-due-date">Due Date:</label>
            <input type="date" name="task-due-date" id="task-due-date" required>

            <button type="submit" name="submit-task">Add Task</button>
        </form>

        <?php
        // Handle task submission
        if (isset($_POST['submit-task'])) {
            dtt_create_task(
                sanitize_text_field($_POST['task-title']),
                sanitize_textarea_field($_POST['task-description']),
                sanitize_text_field($_POST['task-due-date'])
            );
            echo '<p>Task added successfully!</p>';
        }
        ?>

        <!-- Task Search Form -->
        <h2>Search Tasks</h2>
        <form method="get" action="">
            <label for="task-search-query">Search:</label>
            <input type="text" name="task-search-query" id="task-search-query">
            <button type="submit" name="search-task">Search</button>
        </form>

        <?php
        // Handle task search
        if (isset($_GET['search-task'])) {
            $query = sanitize_text_field($_GET['task-search-query']);
            $results = dtt_search_tasks($query);

            if (!empty($results['hits'])) {
                echo '<h3>Search Results:</h3>';
                echo '<ul>';
                foreach ($results['hits'] as $task) {
                    echo '<li><strong>' . esc_html($task['document']['title']) . '</strong>: ' . esc_html($task['document']['description']) . '</li>';
                }
                echo '</ul>';
            } else {
                echo '<p>No tasks found.</p>';
            }
        }
        ?>
    </div>
    <?php
}

function dtt_activate_plugin() {
    // Setup necessary database tables
    dtt_create_task_table();
}

function dtt_deactivate_plugin() {
    // Cleanup operations if needed
}
?>
