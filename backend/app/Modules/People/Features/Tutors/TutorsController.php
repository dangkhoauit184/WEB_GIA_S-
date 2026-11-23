<?php
namespace App\Modules\People\Features\Tutors;

// Gia sư kế thừa các route hồ sơ chung từ Teachers và có cổng vận hành riêng (/api/people/tutors/me/*).
final class TutorsController extends \App\Modules\People\Features\Teachers\TeachersController
{
    protected ?string $type = 'gia_su';

    public function workspace()
    {
        return $this->respond(fn() => TutorsService::workspace());
    }

    public function session($id)
    {
        return $this->respond(fn() => TutorsService::detail((int)$id));
    }

    public function checkin($id)
    {
        return $this->respond(fn() => TutorsService::clock((int)$id, 'in', $this->input()));
    }

    public function checkout($id)
    {
        return $this->respond(fn() => TutorsService::clock((int)$id, 'out', $this->input()));
    }

    public function saveLog($id)
    {
        return $this->respond(fn() => TutorsService::saveLog((int)$id, $this->input()));
    }

    public function uploadMaterial($id)
    {
        return $this->respond(function () use ($id) {
            $contentType = strtolower((string)$this->request->getHeaderLine('Content-Type'));
            if (str_contains($contentType, 'application/json')) {
                $body = $this->input();
                TutorsValidator::fields($body, ['fileUrl', 'tieuDe', 'ghiChu']);
                return TutorsService::uploadMaterial((int)$id, null, $body);
            }
            $file = $this->request->getFile('file');
            $meta = [
                'fileUrl' => $this->request->getPost('fileUrl'),
                'tieuDe' => $this->request->getPost('tieuDe'),
                'ghiChu' => $this->request->getPost('ghiChu'),
            ];
            return TutorsService::uploadMaterial((int)$id, $file, $meta);
        });
    }

    public function removeMaterial($id)
    {
        return $this->respond(fn() => TutorsService::removeMaterial((int)$id));
    }

    public function submitRequest()
    {
        return $this->respond(fn() => TutorsService::request($this->input()), 201);
    }

    public function addTutorAvailability()
    {
        return $this->respond(fn() => TutorsService::saveAvailability($this->input()), 201);
    }

    public function editTutorAvailability($id)
    {
        return $this->respond(fn() => TutorsService::saveAvailability($this->input(), (int)$id));
    }

    public function deleteTutorAvailability($id)
    {
        return $this->respond(fn() => TutorsService::deleteAvailability((int)$id));
    }

    public function myOpportunities()
    {
        return $this->respond(fn() => TutorsService::opportunities());
    }

    public function applyOpportunity($id)
    {
        return $this->respond(fn() => TutorsService::applyOpportunity((int)$id), 201);
    }
}
