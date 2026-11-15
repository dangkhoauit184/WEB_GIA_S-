<?php
namespace App\Modules\Communication\Features\Notifications;

final class NotificationsController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>NotificationsService::index()); }
    public function markRead($id) { return $this->respond(fn()=>NotificationsService::markRead((int)$id)); }
    public function readAll() { return $this->respond(fn()=>NotificationsService::readAll()); }
    public function create() { return $this->respond(fn()=>NotificationsService::create($this->input()), 201); }
}
