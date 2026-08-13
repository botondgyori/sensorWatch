<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Notifications extends MY_Controller {

    public function __construct()
    {
        parent::__construct();
        $this->load->model('Notification_model');
    }

    public function index()
    {
        if ($this->input->method(TRUE) !== 'GET') {
            $this->json_response(array('status' => 'error', 'message' => 'Method not allowed'), 405);
            return;
        }

        if (!$this->require_tenant()) return;

        $user_id = $this->input->get('userId');
        if (!$user_id || !is_numeric($user_id)) {
            $this->json_response(array(
                'status'  => 'error',
                'message' => 'userId query parameter is required'
            ), 400);
            return;
        }

        $user_id = (int) $user_id;

        if (!$this->Notification_model->user_belongs_to_tenant($user_id, $this->tenant->id)) {
            $this->json_response(array(
                'status'  => 'error',
                'message' => 'User does not belong to your tenant'
            ), 403);
            return;
        }

        $limit  = $this->input->get('limit')  ? (int) $this->input->get('limit')  : 50;
        $offset = $this->input->get('offset') ? (int) $this->input->get('offset') : 0;

        $notifications = $this->Notification_model->get_by_user($user_id, $limit, $offset);

        $this->json_response(array(
            'status'        => 'ok',
            'user_id'       => $user_id,
            'count'         => count($notifications),
            'notifications' => $notifications
        ));
    }
}
