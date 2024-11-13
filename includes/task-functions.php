<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * Create the task table in the database (if it doesn't already exist).
 */
function dtt_create_task_table() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'dtt_tasks';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        due_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

/**
 * Insert a task into the database and add it to Typesense.
 */
function dtt_create_task($title, $description, $due_date) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dtt_tasks';

    // Insert the task into the database
    $wpdb->insert(
        $table_name,
        [
            'title' => $title,
            'description' => $description,
            'due_date' => $due_date
        ],
        [
            '%s',
            '%s',
            '%s'
        ]
    );

    // Get the ID of the inserted task
    $task_id = $wpdb->insert_id;

    // If the task was successfully added, add it to Typesense
    if ($task_id) {
        dtt_create_task_in_typesense($task_id, $title, $description, $due_date);
    }
}

/**
 * Create a task document in Typesense.
 */
function dtt_create_task_in_typesense($task_id, $title, $description, $due_date) {
    $client = dtt_get_typesense_client();

    // Convert `due_date` to timestamp for Typesense sorting
    $due_date_timestamp = strtotime($due_date);

    $task_data = [
        'id' => (string)$task_id,
        'title' => $title,
        'description' => $description,
        'due_date' => $due_date_timestamp  // Store as integer
    ];

    // Add task document to Typesense
    try {
        $client->collections['tasks']->documents->create($task_data);
    } catch (Exception $e) {
        error_log('Error adding task to Typesense: ' . $e->getMessage());
    }
}

/**
 * Retrieve all tasks from the database.
 */
function dtt_get_all_tasks() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'dtt_tasks';

    $results = $wpdb->get_results("SELECT * FROM $table_name ORDER BY due_date ASC", ARRAY_A);

    return $results;
}

/**
 * Search tasks using Typesense.
 */
function dtt_search_tasks($query) {
    $client = dtt_get_typesense_client();

    try {
        $results = $client->collections['tasks']->documents->search([
            'q' => $query,
            'query_by' => 'title,description'
        ]);
        return $results;
    } catch (Exception $e) {
        error_log('Error searching tasks in Typesense: ' . $e->getMessage());
        return [];
    }
}
