<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Ingest Controller — POST /api/ingest/readings
 *
 * Accepts a batch of sensor readings from a tenant. Each request must
 * include an X-Api-Key header for tenant authentication.
 *
 * This endpoint is the data entry point for the entire system. External
 * sensors/gateways POST readings here, and the processor later evaluates
 * them against hysteresis rules.
 *
 * IDOR PROTECTION: Each reading's sensor_id is validated to ensure it
 * belongs to a device owned by the authenticated tenant. A tenant cannot
 * inject readings for another tenant's sensors.
 */
class Ingest extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Reading_model');
        $this->load->model('Sensor_model');
    }

    public function readings()
    {
        if ($this->input->method(TRUE) !== 'POST') {
            $this->json_response(array('status' => 'error', 'message' => 'Method not allowed'), 405);
            return;
        }

        if (!$this->require_tenant()) return;

        $input = $this->get_json_input();
        if (!$input || !isset($input['readings']) || !is_array($input['readings'])) {
            $this->json_response(array(
                'status'  => 'error',
                'message' => 'Invalid JSON body. Expected: {"readings": [...]}'
            ), 400);
            return;
        }

        $valid_readings = array();
        $errors = array();
        $index = 0;

        foreach ($input['readings'] as $r) {
            $index++;

            // Validate required fields
            if (!isset($r['sensor_id']) || !isset($r['recorded_at']) || !isset($r['value'])) {
                $errors[] = "Reading #{$index}: missing required fields (sensor_id, recorded_at, value)";
                continue;
            }

            // Validate recorded_at is a real, parseable datetime
            $recorded_at = $this->validate_datetime($r['recorded_at']);
            if ($recorded_at === false) {
                $errors[] = "Reading #{$index}: invalid recorded_at format \"{$r['recorded_at']}\", expected YYYY-MM-DD HH:MM:SS";
                continue;
            }

            $sensor_id = (int)$r['sensor_id'];

            // IDOR CHECK: verify sensor belongs to this tenant
            $sensor = $this->Sensor_model->get_for_tenant($sensor_id, $this->tenant->id);
            if (!$sensor) {
                $errors[] = "Reading #{$index}: sensor {$sensor_id} does not belong to your tenant";
                continue;
            }

            $valid_readings[] = array(
                'sensor_id'   => $sensor_id,
                'recorded_at' => $r['recorded_at'],
                'value'       => (float)$r['value']
            );
        }

        $inserted = 0;
        if (!empty($valid_readings)) {
            $inserted = $this->Reading_model->insert_batch($valid_readings);
        }

        $this->json_response(array(
            'status'   => 'ok',
            'inserted' => $inserted,
            'rejected' => count($errors),
            'errors'   => $errors
        ));
    }
}
