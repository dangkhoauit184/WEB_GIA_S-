<?php
namespace App\Modules\Academic\Features\AcademicAffairs;

final class AcademicAffairsController extends \App\Shared\ApiController
{
    public function dashboard() { return $this->respond(fn()=>AcademicAffairsModel::map(AcademicAffairsService::summary())); }
    public function rooms() { return $this->respond(fn()=>\App\Modules\Administration\Features\Catalog\RoomCatalog::forOperations()); }
    public function makeups() { return $this->respond(fn()=>AcademicAffairsModel::map(AcademicAffairsRepository::makeups(AcademicAffairsFilter::check()))); }
    public function people() { return $this->respond(fn()=>AcademicAffairsModel::map(AcademicAffairsService::people())); }
    public function courses() { return $this->respond(fn()=>AcademicAffairsModel::map(AcademicAffairsService::courses())); }
    public function registrations($id) { return $this->respond(fn()=>AcademicAffairsModel::map(AcademicAffairsService::registrations((int)$id))); }
    public function register($id) { return $this->respond(fn()=>AcademicAffairsModel::map(AcademicAffairsService::register((int)$id, $this->input())), 201); }
    public function createStudent() { return $this->respond(fn()=>AcademicAffairsService::student($this->input()), 201); }
    public function notifications() { return $this->respond(fn()=>AcademicAffairsModel::map(AcademicAffairsService::notifications())); }
    public function readNotification($id) { return $this->respond(fn()=>AcademicAffairsService::readNotification((int)$id, $this->input())); }
}
