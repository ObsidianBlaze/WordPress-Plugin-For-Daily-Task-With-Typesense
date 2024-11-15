<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

use Typesense\Client;

/**
 * Get the Typesense client instance.
 */
function dtt_get_typesense_client() {
    // Retrieve API configuration from settings
    $api_key = get_option('dtt_typesense_api_key');
    $host = get_option('dtt_typesense_host', 'localhost');
    $port = get_option('dtt_typesense_port', '8108');

    // Check if the API key is set
    if (!$api_key) {
        wp_die('Typesense API key is missing. Please set it in the Typesense settings.');
    }

    return new Client([
        'nodes' => [
            [
                'host' => $host,
                'port' => $port,
                'protocol' => 'http'
            ]
        ],
        'api_key' => $api_key,
        'connection_timeout_seconds' => 2
    ]);
}

/**
 * Add a task to the Typesense collection.
 */

/**
 * Create the Typesense collection for tasks if it doesn't exist.
 */
function dtt_create_typesense_collection() {
    $client = dtt_get_typesense_client();

    // Define the schema with `due_date` as an int32 for sorting
    $collection_schema = [
        'name' => 'tasks',
        'fields' => [
            ['name' => 'title', 'type' => 'string'],
            ['name' => 'description', 'type' => 'string'],
            ['name' => 'due_date', 'type' => 'int32', 'optional' => false]  // int32 for sorting
        ],
        'default_sorting_field' => 'due_date'
    ];

    try {
        $client->collections->create($collection_schema);
    } catch (Exception $e) {
        error_log('Error creating Typesense collection: ' . $e->getMessage());
    }}

/**
 * Search tasks in the Typesense collection.
 */

function dtt_get_tasks_from_typesense($searchQuery = '') {
    $client = dtt_get_typesense_client();
    $tasks = [];

    try {
        // Adjust search query based on input
        $searchParams = [
            'q' => $searchQuery ?: ' ',   // Use the search query or a single space to match all tasks
            'query_by' => 'title',        // Search by 'title' field
            'sort_by' => 'due_date:asc'   // Sort by due date in ascending order
        ];

        $response = $client->collections['tasks']->documents->search($searchParams);
        $tasks = isset($response['hits']) ? $response['hits'] : []; // Retrieve tasks from response
    } catch (Exception $e) {
        error_log('Error fetching tasks from Typesense: ' . $e->getMessage());
    }

    return $tasks;
}

