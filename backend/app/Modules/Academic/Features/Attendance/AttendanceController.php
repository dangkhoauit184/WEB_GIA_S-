<?php
namespace App\Modules\Academic\Features\Attendance;

final class AttendanceController extends \App\Shared\ApiController
{
    public function index() { return $this->respond(fn()=>AttendanceService::workspace()); }
    public function show($id) { return $this->respond(fn()=>AttendanceService::detail((int)$id)); }
    public function checkin($id) { return $this->respond(fn()=>AttendanceService::clock((int)$id, 'in', $this->input())); }
    public function checkout($id) { return $this->respond(fn()=>AttendanceService::clock((int)$id, 'out', $this->input())); }
    public function save($id) { return $this->respond(fn()=>AttendanceService::save((int)$id, $this->input())); }
    public function uploadMaterial($id)
    {
        return $this->respond(function () use ($id) {
            $contentType = strtolower((string)$this->request->getHeaderLine('Content-Type'));
            if (str_contains($contentType, 'application/json')) {
                $body = $this->input();
                AttendanceValidator::fields($body, ['fileUrl', 'tieuDe', 'ghiChu']);
                return AttendanceService::uploadMaterial((int)$id, null, $body);
            }
            $file = $this->request->getFile('file');
            $meta = [
                'fileUrl' => $this->request->getPost('fileUrl'),
                'tieuDe' => $this->request->getPost('tieuDe'),
                'ghiChu' => $this->request->getPost('ghiChu'),
            ];
            return AttendanceService::uploadMaterial((int)$id, $file, $meta);
        });
    }
    public function removeMaterial($id) { return $this->respond(fn()=>AttendanceService::removeMaterial((int)$id)); }
    public function downloadMaterial($id)
    {
        try {
            $info = AttendanceService::downloadMaterial((int)$id);
            $bytes = @file_get_contents($info['path']);
            if ($bytes === false) {
                throw new \App\Shared\ApiException(404, 'Không đọc được tệp tài liệu.');
            }
            $safeName = str_replace(['"', "\r", "\n"], '', $info['originalName']);
            $encodedName = rawurlencode($info['originalName']);
            return $this->response
                ->setStatusCode(200)
                ->setHeader('Content-Type', $info['mimeType'])
                ->setHeader('Content-Disposition', $info['disposition'] . '; filename="' . $safeName . '"; filename*=UTF-8\'\'' . $encodedName)
                ->setHeader('Content-Length', (string)strlen($bytes))
                ->setBody($bytes);
        } catch (\Throwable $error) {
            return self::error($error);
        }
    }
    public function request() { return $this->respond(fn()=>AttendanceService::request($this->input()), 201); }
}
