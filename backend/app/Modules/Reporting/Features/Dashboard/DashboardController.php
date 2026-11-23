<?php
namespace App\Modules\Reporting\Features\Dashboard;

final class DashboardController extends \App\Shared\ApiController
{
    public function index()
    {
        return $this->respond(fn() => DashboardService::summary($this->request->getGet()));
    }

    public function reportMeta()
    {
        return $this->respond(fn() => ReportsService::meta());
    }

    public function report(string $type)
    {
        return $this->respond(fn() => ReportsService::get($type, $this->request->getGet()));
    }

    public function export(string $type)
    {
        try {
            $file = ReportsService::export($type, $this->request->getGet());
            return $this->response
                ->setStatusCode(200)
                ->setHeader('Content-Type', $file['contentType'])
                ->setHeader('Content-Disposition', 'attachment; filename="'.$file['filename'].'"')
                ->setHeader('Cache-Control', 'no-store')
                ->setHeader('X-Content-Type-Options', 'nosniff')
                ->setBody($file['body']);
        } catch (\Throwable $error) {
            return self::error($error);
        }
    }
}
