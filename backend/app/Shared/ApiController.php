<?php
namespace App\Shared;

use CodeIgniter\HTTP\ResponseInterface;

abstract class ApiController extends \CodeIgniter\Controller
{
    protected function respond(callable $work, int $status = 200): ResponseInterface
    {
        try {
            return $this->response->setStatusCode($status)->setJSON([
                'success' => true, 'data' => $work(),
            ]);
        } catch (\Throwable $error) {
            return self::error($error);
        }
    }

    protected function input(): array
    {
        try {
            $body = json_decode($this->request->getBody(), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ApiException(400, 'JSON không hợp lệ.');
        }
        if (!is_array($body) || array_is_list($body) && $body !== []) {
            throw new ApiException(400, 'Nội dung phải là một đối tượng JSON.');
        }
        return $body;
    }

    public static function error(\Throwable $error): ResponseInterface
    {
        $status = 500; $code = 'INTERNAL_SERVER_ERROR'; $message = 'Máy chủ gặp lỗi. Vui lòng thử lại.';
        if ($error instanceof ApiException) {
            $status = $error->status; $code = $error->errorCode; $message = $error->getMessage();
        } else {
            $cause = $error;
            while ($cause->getPrevious()) $cause = $cause->getPrevious();
            $number = (int) $cause->getCode();
            if ($number === 1644) {
                $status = 400; $code = 'BUSINESS_RULE_VIOLATION'; $message = $cause->getMessage();
            } elseif ($number === 1062) {
                $status = 409; $code = 'DUPLICATE_ENTRY'; $message = 'Dữ liệu đã tồn tại.';
            } elseif (in_array($number, [1451, 1452, 3819, 1265, 1048, 1406, 1292], true)) {
                $status = 400; $code = 'DATA_CONSTRAINT_ERROR'; $message = 'Dữ liệu không thỏa mãn ràng buộc.';
            }
            log_message('error', '{type}: {message}', ['type' => get_class($error), 'message' => $error->getMessage()]);
        }
        return service('response')->setStatusCode($status)->setJSON(['success' => false, 'code' => $code, 'message' => $message]);
    }
}
