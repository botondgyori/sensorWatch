<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migrate extends MY_Controller {

    /**
     * POST /api/migrate
     * Runs all pending migrations up to the latest version.
     */
    public function index()
    {
        $this->load->library('migration');

        if ($this->migration->latest() === FALSE) {
            $this->json_response(array(
                'status'  => 'error',
                'message' => $this->migration->error_string()
            ), 500);
            return;
        }

        $version = $this->db->select('version')
                         ->order_by('version', 'DESC')
                         ->limit(1)
                         ->get('migrations')
                         ->row();

        $this->json_response(array(
            'status'  => 'ok',
            'message' => 'Migrated to version ' . ($version ? $version->version : 'unknown')
        ));
    }

    /**
     * POST /api/migrate/reset
     * Rolls back all migrations to version 0, then re-runs them.
     * WARNING: This drops all tables and data!
     */
    public function reset()
    {
        $this->load->library('migration');

        // Roll back to version 0 (drops all tables)
        if ($this->migration->version(0) === FALSE) {
            $this->json_response(array(
                'status'  => 'error',
                'message' => 'Rollback failed: ' . $this->migration->error_string()
            ), 500);
            return;
        }

        // Re-run all migrations
        if ($this->migration->latest() === FALSE) {
            $this->json_response(array(
                'status'  => 'error',
                'message' => 'Migration failed: ' . $this->migration->error_string()
            ), 500);
            return;
        }

        $version = $this->db->select('version')
                         ->order_by('version', 'DESC')
                         ->limit(1)
                         ->get('migrations')
                         ->row();

        $this->json_response(array(
            'status'  => 'ok',
            'message' => 'Database reset and migrated to version ' . ($version ? $version->version : 'unknown')
        ));
    }
}
