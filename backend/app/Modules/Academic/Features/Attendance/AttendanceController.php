<?php
namespace App\Modules\Academic\Features\Attendance;

final class AttendanceController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>AttendanceService::workspace()); }
    public function show($id) { return $this->respond(fn()=>AttendanceService::detail((int)$id)); }
    public function checkin($id) { return $this->respond(fn()=>AttendanceService::clock((int)$id, 'in', $this->input())); }
    public function checkout($id) { return $this->respond(fn()=>AttendanceService::clock((int)$id, 'out', $this->input())); }
    public function save($id) { return $this->respond(fn()=>AttendanceService::save((int)$id, $this->input())); }
    public function request() { return $this->respond(fn()=>AttendanceService::request($this->input()), 201); }
}
