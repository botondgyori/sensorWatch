<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Controller — Base controller for the SensorWatch API.
 *
 * CI3's naming convention: a class named MY_Controller in application/core/
 * automatically extends CI_Controller. All API controllers extend this class
 * to get common JSON response helpers and tenant authentication.
 *
 * Key responsibilities:
 * 1. JSON input parsing (API accepts JSON bodies, not form data)
 * 2. JSON response formatting (consistent API response structure)
 * 3. Tenant authentication via X-Api-Key header
 */
class MY_Controller extends CI_Controller {

    protected $tenant = null;

    
    public function __construct()
    {
        parent::__construct();
    }

    protected function json_response($data, $status_code = 200)
    {
        $this->output
            ->set_status_header($status_code)
            ->set_content_type('application/json')
            ->set_output(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    protected function get_json_input()
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return null;
        }
        return json_decode($raw, true);
    }
}
