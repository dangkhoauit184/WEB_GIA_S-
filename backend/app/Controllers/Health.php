<?php
namespace App\Controllers;

use App\Shared\{ApiController,Store};

final class Health extends ApiController
{
    public function index()
    {
        try {
            $db=Store::one('SELECT @@session.time_zone AS timeZone,@@session.transaction_isolation AS isolationLevel');
            return $this->response->setJSON(['success'=>true,'message'=>'EduCenter ERP API PHP & MySQL đang hoạt động bình thường.',
                'db'=>$db,'timestamp'=>gmdate('c')]);
        } catch (\Throwable $error) { return self::error($error); }
    }
    public function manifest()
    {
        return $this->response->setJSON(['success'=>true,'name'=>'EduCenter ERP','backend'=>'PHP / CodeIgniter 4',
            'modules'=>json_decode(file_get_contents(ROOTPATH.'../shared/modules.json'),true)]);
    }
    public function notFound()
    {
        return $this->response->setStatusCode(404)->setJSON([
            'success'=>false,'code'=>'NOT_FOUND','message'=>'Không tìm thấy đường dẫn.',
        ]);
    }
}
