<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Admin extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
    }

    public function process_readings()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            $this->json_response(array('status' => 'error', 'message' => 'Method not allowed'), 405);
            return;
        }

        $this->load->library('ReadingProcessor');
        $results = $this->readingprocessor->process_all();

        $this->json_response(array(
            'status'  => 'ok',
            'results' => $results
        ));
    }
}
