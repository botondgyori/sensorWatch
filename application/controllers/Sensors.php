<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Sensors Controller — GET /api/sensors/{id}/state
 *
 * Returns the current hysteresis state for a specific sensor.
 * Requires X-Api-Key header for tenant authentication.
 * Enforces tenant isolation: a tenant can only view sensors
 * attached to their own devices.
 */
class Sensors extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Sensor_model');
        $this->load->model('Sensor_state_model');
    }

    public function state($sensor_id = null)
    {
        if ($this->input->method(TRUE) !== 'GET') {
            $this->json_response(array('status' => 'error', 'message' => 'Method not allowed'), 405);
            return;
        }

        if (!$this->require_tenant()) return;

        if (!$sensor_id || !is_numeric($sensor_id)) {
            $this->json_response(array('status' => 'error', 'message' => 'Sensor ID is required'), 400);
            return;
        }

        $sensor_id = (int) $sensor_id;

        // IDOR CHECK: verify sensor belongs to the authenticated tenant
        $sensor = $this->Sensor_model->get_for_tenant($sensor_id, $this->tenant->id);
        if (!$sensor) {
            $this->json_response(array(
                'status'  => 'error',
                'message' => 'Sensor not found or does not belong to your tenant'
            ), 403);
            return;
        }

        $sensor_full = $this->Sensor_model->get_with_type($sensor_id);

        $properties = $this->Sensor_model->get_effective_properties($sensor_id);

        $state = $this->Sensor_state_model->get_by_sensor($sensor_id);

        $this->json_response(array(
            'status' => 'ok',
            'sensor' => array(
                'id'           => (int) $sensor_full->id,
                'display_name' => $sensor_full->display_name,
                'type_key'     => $sensor_full->type_key,
                'value_kind'   => $sensor_full->value_kind,
                'unit'         => $sensor_full->unit
            ),
            'state' => $state ? array(
                'current_state'      => $state->current_state,
                'pending_state'      => $state->pending_state,
                'pending_since'      => $state->pending_since,
                'last_transition_at' => $state->last_transition_at,
                'last_notified_at'   => $state->last_notified_at,
                'updated_at'         => $state->updated_at
            ) : null,
            'effective_properties' => $properties
        ));
    }
}
