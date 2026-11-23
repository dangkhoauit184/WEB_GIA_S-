<?php
namespace App\Modules\Academic\Features\Attendance;

use App\Shared\{ApiException, Store};
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Handles pre-session material file upload, authorized class-scoped download,
 * metadata lookup, and previous-session homework grading validation.
 */
final class SessionMaterialService
{
    public const MAX_FILE_BYTES = 10485760; // 10 MB

    /** @var array<string, list<string>> */
    private const ALLOWED_MIME_BY_EXT = [
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword', 'application/x-ole-storage', 'application/cdfv2', 'application/vnd.ms-office'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip', 'application/x-zip-compressed'],
        'ppt'  => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/cdfv2', 'application/vnd.ms-office'],
        'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation', 'application/zip', 'application/x-zip-compressed'],
        'xls'  => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/cdfv2', 'application/vnd.ms-office'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip', 'application/x-zip-compressed'],
        'txt'  => ['text/plain'],
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
    ];

    /** @var array<string, string> */
    private const CANONICAL_MIME_BY_EXT = [
        'pdf'  => 'application/pdf',
        'doc'  => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'ppt'  => 'application/vnd.ms-powerpoint',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'xls'  => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'txt'  => 'text/plain; charset=UTF-8',
        'png'  => 'image/png',
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
    ];

    public static function storageDir(): string
    {
        $base = rtrim(WRITEPATH, DIRECTORY_SEPARATOR . '/') . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'session-materials';
        if (!is_dir($base)) {
            if (!@mkdir($base, 0777, true) && !is_dir($base)) {
                throw new ApiException(500, 'Không thể khởi tạo thư mục lưu trữ tài liệu trên máy chủ.');
            }
            @chmod($base, 0777);
        }
        return $base;
    }

    public static function downloadPath(int $sessionId): string
    {
        return '/api/academic/attendance/sessions/' . $sessionId . '/material/download';
    }

    private static function metaPath(int $sessionId): string
    {
        return self::storageDir() . DIRECTORY_SEPARATOR . 'session_' . $sessionId . '.meta.json';
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function readMeta(int $sessionId): ?array
    {
        $path = self::metaPath($sessionId);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    private static function deleteLocalFile(int $sessionId): void
    {
        $meta = self::readMeta($sessionId);
        if ($meta && !empty($meta['storedName']) && is_string($meta['storedName'])) {
            $stored = basename($meta['storedName']);
            $full = self::storageDir() . DIRECTORY_SEPARATOR . $stored;
            if (is_file($full)) {
                @unlink($full);
            }
        }
        $metaFile = self::metaPath($sessionId);
        if (is_file($metaFile)) {
            @unlink($metaFile);
        }
    }

    /**
     * Describe session's pre-session material cleanly for Teacher/Tutor/Assistant/Parent responses.
     *
     * @param array<string, mixed> $session
     * @param array<string, mixed>|null $homework
     * @return array<string, mixed>
     */
    public static function describe(array $session, ?array $homework = null): array
    {
        $sessionId = (int)($session['id'] ?? $session['buoi_hoc_id'] ?? 0);
        $url = $session['file_bai_giang_url'] ?? ($homework['file_de_bai_url'] ?? null);
        $downloadPath = $sessionId > 0 ? self::downloadPath($sessionId) : '';
        $meta = ($sessionId > 0 && $url === $downloadPath) ? self::readMeta($sessionId) : null;
        $isUploaded = $meta !== null && !empty($meta['storedName'])
            && is_file(self::storageDir() . DIRECTORY_SEPARATOR . basename((string)$meta['storedName']));

        if ($url === $downloadPath && !$isUploaded) {
            $url = null;
        }

        return [
            'co_tai_lieu' => $url !== null && $url !== '',
            'url' => $url,
            'la_tep_tai_len' => $isUploaded,
            'ten_tep' => $isUploaded ? ($meta['originalName'] ?? null) : null,
            'loai_tep' => $isUploaded ? ($meta['mimeType'] ?? null) : null,
            'kich_thuoc' => $isUploaded ? (int)($meta['sizeBytes'] ?? 0) : null,
            'tai_len_luc' => $isUploaded ? ($meta['uploadedAt'] ?? null) : null,
            'tieu_de' => $homework['tieu_de'] ?? ($meta['title'] ?? null),
            'ghi_chu' => $homework['noi_dung'] ?? ($meta['note'] ?? null),
            'bai_tap_id' => isset($homework['id']) ? (int)$homework['id'] : null,
        ];
    }

    /**
     * Validate an uploaded file's size, extension, real magic MIME type, and Office ZIP structure.
     *
     * @return array{ext:string, mime:string, originalName:string, size:int}
     */
    public static function validateUploadedFile(UploadedFile $file): array
    {
        $error = $file->getError();
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            throw new ApiException(413, 'Tệp tài liệu vượt quá dung lượng tối đa 10 MB.', 'PAYLOAD_TOO_LARGE');
        }
        if ($error !== UPLOAD_ERR_OK || !$file->isValid()) {
            throw new ApiException(400, 'Tải tệp không thành công. Vui lòng chọn lại tệp hợp lệ.');
        }

        $size = (int)$file->getSize();
        if ($size <= 0) {
            throw new ApiException(400, 'Tệp tài liệu trống, không thể tải lên.');
        }
        if ($size > self::MAX_FILE_BYTES) {
            throw new ApiException(413, 'Tệp tài liệu vượt quá dung lượng tối đa 10 MB.', 'PAYLOAD_TOO_LARGE');
        }

        $rawName = trim((string)$file->getClientName());
        $cleanName = preg_replace('/[\x00-\x1F\x7F\/\\\\]+/', '_', basename($rawName)) ?? 'tai-lieu';
        $cleanName = mb_substr($cleanName, 0, 180) ?: 'tai-lieu';
        $ext = strtolower((string)pathinfo($cleanName, PATHINFO_EXTENSION));

        if ($ext === '' || !isset(self::ALLOWED_MIME_BY_EXT[$ext])) {
            throw new ApiException(400, 'Định dạng tệp không được hỗ trợ. Chỉ chấp nhận PDF, Word, PowerPoint, Excel, TXT, PNG hoặc JPG.');
        }

        $tmpPath = $file->getTempName();
        if (!$tmpPath || !is_file($tmpPath)) {
            throw new ApiException(400, 'Không đọc được tệp tạm vừa tải lên.');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $detectedMime = strtolower((string)($finfo->file($tmpPath) ?: ''));

        if (!in_array($detectedMime, self::ALLOWED_MIME_BY_EXT[$ext], true)) {
            throw new ApiException(400, 'Nội dung tệp không khớp với định dạng .' . $ext . ' cho phép.');
        }

        // Verify OOXML packages actually contain [Content_Types].xml instead of arbitrary ZIP archives.
        if (in_array($ext, ['docx', 'pptx', 'xlsx'], true) && class_exists(\ZipArchive::class)) {
            $zip = new \ZipArchive();
            if ($zip->open($tmpPath) !== true) {
                throw new ApiException(400, 'Tệp tài liệu Office không hợp lệ hoặc bị hỏng.');
            }
            $hasContentTypes = $zip->locateName('[Content_Types].xml') !== false;
            $zip->close();
            if (!$hasContentTypes) {
                throw new ApiException(400, 'Tệp .' . $ext . ' không đúng cấu trúc tài liệu Office.');
            }
        }

        return [
            'ext' => $ext,
            'mime' => self::CANONICAL_MIME_BY_EXT[$ext],
            'originalName' => $cleanName,
            'size' => $size,
        ];
    }

    /**
     * Save pre-session material (uploaded file from device OR preview URL) for a Teacher or Tutor.
     * Works BEFORE check-in so students/parents can prepare ahead of the session.
     *
     * @param callable(int, int):array<string, mixed> $lockFn
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function saveMaterial(
        int $sessionId,
        array $actor,
        callable $lockFn,
        ?UploadedFile $file,
        array $meta
    ): array {
        $title = AttendanceValidator::text($meta['tieuDe'] ?? null, 255, false);
        $note = AttendanceValidator::text($meta['ghiChu'] ?? null, 15000, false);
        $externalUrl = AttendanceValidator::url($meta['fileUrl'] ?? null);

        $validatedFile = null;
        if ($file !== null && $file->getError() !== UPLOAD_ERR_NO_FILE) {
            $validatedFile = self::validateUploadedFile($file);
        }

        $movedFullPath = null;
        $storedName = null;
        $pendingMetaPayload = null;
        $oldStoredToRemove = null;
        $deleteLocalAfterCommit = false;

        try {
            Store::transaction(function () use (
                $sessionId,
                $actor,
                $lockFn,
                $file,
                $validatedFile,
                $externalUrl,
                $title,
                $note,
                &$movedFullPath,
                &$storedName,
                &$pendingMetaPayload,
                &$oldStoredToRemove,
                &$deleteLocalAfterCommit
            ) {
                $session = $lockFn($sessionId, (int)$actor['userId']);
                if (($session['vai_tro'] ?? '') !== 'day_chinh') {
                    throw new ApiException(403, 'Chỉ người dạy chính của ca học mới được tải lên hoặc cập nhật tài liệu.', 'FORBIDDEN');
                }
                if (in_array($session['trang_thai'] ?? '', ['huy', 'doi'], true)) {
                    throw new ApiException(409, 'Ca học đã hủy hoặc đổi lịch, không thể cập nhật tài liệu.');
                }

                $beforeUrl = $session['file_bai_giang_url'] ?? null;
                $existingHw = Store::one('SELECT id, tieu_de, noi_dung, file_de_bai_url FROM bai_tap WHERE buoi_hoc_id=? ORDER BY id DESC LIMIT 1', [$sessionId]);

                if ($validatedFile !== null && $file !== null) {
                    $dir = self::storageDir();
                    $storedName = 'session_' . $sessionId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $validatedFile['ext'];
                    $file->move($dir, $storedName, true);
                    $movedFullPath = $dir . DIRECTORY_SEPARATOR . $storedName;

                    $oldMeta = self::readMeta($sessionId);
                    if ($oldMeta && !empty($oldMeta['storedName']) && $oldMeta['storedName'] !== $storedName) {
                        $oldStoredToRemove = $dir . DIRECTORY_SEPARATOR . basename((string)$oldMeta['storedName']);
                    }

                    $finalUrl = self::downloadPath($sessionId);
                    $finalTitle = $title ?: pathinfo($validatedFile['originalName'], PATHINFO_FILENAME) ?: ('Tài liệu buổi học ' . $session['ngay']);
                    $pendingMetaPayload = [
                        'sessionId' => $sessionId,
                        'storedName' => $storedName,
                        'originalName' => $validatedFile['originalName'],
                        'mimeType' => $validatedFile['mime'],
                        'sizeBytes' => $validatedFile['size'],
                        'title' => $finalTitle,
                        'note' => $note,
                        'uploadedBy' => (int)$actor['userId'],
                        'uploadedAt' => Store::one('SELECT NOW() AS now')['now'],
                    ];
                } elseif ($externalUrl !== null) {
                    if ($externalUrl !== self::downloadPath($sessionId)) {
                        $deleteLocalAfterCommit = true;
                    }
                    $finalUrl = $externalUrl;
                    $finalTitle = $title ?: ($existingHw['tieu_de'] ?? ($session['chu_de_bai_hoc'] ?: 'Tài liệu & bài tập chuẩn bị buổi ' . $session['ngay']));
                } elseif ($beforeUrl !== null && $beforeUrl !== '' && ($title !== null || $note !== null)) {
                    $finalUrl = $beforeUrl;
                    $finalTitle = $title ?: ($existingHw['tieu_de'] ?? 'Tài liệu buổi học ' . $session['ngay']);
                    $existingMeta = self::readMeta($sessionId);
                    if ($existingMeta !== null && $beforeUrl === self::downloadPath($sessionId)) {
                        $existingMeta['title'] = $finalTitle;
                        $existingMeta['note'] = $note;
                        $pendingMetaPayload = $existingMeta;
                    }
                } else {
                    throw new ApiException(400, 'Vui lòng chọn tệp từ thiết bị hoặc nhập liên kết tài liệu xem trước.');
                }

                Store::execute('UPDATE buoi_hoc SET file_bai_giang_url=? WHERE id=?', [$finalUrl, $sessionId]);

                // Sync pre-session bai_tap record so students can view it ahead of class AND the next session can grade it.
                if ($existingHw) {
                    Store::execute(
                        'UPDATE bai_tap SET tieu_de=?, noi_dung=?, file_de_bai_url=? WHERE id=?',
                        [$finalTitle, $note, $finalUrl, (int)$existingHw['id']]
                    );
                } else {
                    Store::insert('bai_tap', [
                        'buoi_hoc_id' => $sessionId,
                        'tieu_de' => $finalTitle,
                        'noi_dung' => $note,
                        'file_de_bai_url' => $finalUrl,
                        'han_nop' => null,
                        'nguoi_tao_id' => (int)$actor['userId'],
                    ]);
                }

                Store::recordAudit(
                    (int)$actor['userId'],
                    'sua',
                    'buoi_hoc',
                    $sessionId,
                    ['file_bai_giang_url' => $beforeUrl],
                    [
                        'thao_tac' => 'tai_tai_lieu_truoc_buoi_hoc',
                        'file_bai_giang_url' => $finalUrl,
                        'tieu_de' => $finalTitle,
                        'ten_tep' => $validatedFile['originalName'] ?? null,
                    ]
                );
            });

            // Commit succeeded: now persist metadata sidecar & clean superseded file cleanly.
            if ($deleteLocalAfterCommit) {
                self::deleteLocalFile($sessionId);
            } elseif ($pendingMetaPayload !== null) {
                $metaFile = self::metaPath($sessionId);
                if (@file_put_contents($metaFile, json_encode($pendingMetaPayload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) === false) {
                    throw new ApiException(500, 'Không thể lưu thông tin tài liệu đã tải lên.');
                }
                @chmod($metaFile, 0666);
                if ($movedFullPath !== null) {
                    @chmod($movedFullPath, 0666);
                }
                if ($oldStoredToRemove !== null && is_file($oldStoredToRemove)) {
                    @unlink($oldStoredToRemove);
                }
            }
        } catch (\Throwable $e) {
            if ($movedFullPath !== null && is_file($movedFullPath)) {
                @unlink($movedFullPath);
            }
            throw $e;
        }

        $fresh = $lockFn($sessionId, (int)$actor['userId']);
        $hw = Store::one('SELECT id, tieu_de, noi_dung, file_de_bai_url, han_nop, updated_at FROM bai_tap WHERE buoi_hoc_id=? ORDER BY id DESC LIMIT 1', [$sessionId]);
        return self::describe($fresh, $hw);
    }

    /**
     * Remove pre-session material attached to a session.
     *
     * @param callable(int, int):array<string, mixed> $lockFn
     * @param array<string, mixed> $actor
     * @return array<string, mixed>
     */
    public static function removeMaterial(int $sessionId, array $actor, callable $lockFn): array
    {
        Store::transaction(function () use ($sessionId, $actor, $lockFn) {
            $session = $lockFn($sessionId, (int)$actor['userId']);
            if (($session['vai_tro'] ?? '') !== 'day_chinh') {
                throw new ApiException(403, 'Chỉ người dạy chính của ca học mới được gỡ tài liệu.', 'FORBIDDEN');
            }
            if (in_array($session['trang_thai'] ?? '', ['huy', 'doi'], true)) {
                throw new ApiException(409, 'Ca học đã hủy hoặc đổi lịch.');
            }

            $beforeUrl = $session['file_bai_giang_url'] ?? null;
            self::deleteLocalFile($sessionId);
            Store::execute('UPDATE buoi_hoc SET file_bai_giang_url=NULL WHERE id=?', [$sessionId]);

            $existingHw = Store::one('SELECT id FROM bai_tap WHERE buoi_hoc_id=? ORDER BY id DESC LIMIT 1', [$sessionId]);
            if ($existingHw) {
                $graded = Store::one('SELECT id FROM so_theo_doi_buoi_hoc WHERE bai_tap_id=? LIMIT 1', [(int)$existingHw['id']]);
                if ($graded) {
                    Store::execute('UPDATE bai_tap SET file_de_bai_url=NULL WHERE id=?', [(int)$existingHw['id']]);
                } else {
                    Store::execute('DELETE FROM bai_tap WHERE id=?', [(int)$existingHw['id']]);
                }
            }

            Store::recordAudit(
                (int)$actor['userId'],
                'sua',
                'buoi_hoc',
                $sessionId,
                ['file_bai_giang_url' => $beforeUrl],
                ['thao_tac' => 'go_tai_lieu_buoi_hoc']
            );
        });

        return [
            'co_tai_lieu' => false,
            'url' => null,
            'la_tep_tai_len' => false,
            'ten_tep' => null,
            'loai_tep' => null,
            'kich_thuoc' => null,
            'tai_len_luc' => null,
            'tieu_de' => null,
            'ghi_chu' => null,
            'bai_tap_id' => null,
        ];
    }

    /**
     * Authorize and resolve an uploaded session material file for download.
     * Only enrolled students' parents, assigned teaching staff (teacher/tutor/TA), branch staff, and Admin may access.
     *
     * @param array<string, mixed> $user
     * @return array{path:string, originalName:string, mimeType:string, disposition:string}
     */
    public static function authorizeDownload(int $sessionId, array $user): array
    {
        $session = Store::one(
            "SELECT b.id, b.lop_id, b.loai, b.pham_vi_bu, b.file_bai_giang_url,
                    COALESCE(b.co_so_id, l.co_so_id) AS co_so_id, l.nguoi_day_chinh_id
             FROM buoi_hoc b
             JOIN lop_hoc l ON l.id=b.lop_id
             WHERE b.id=? AND l.deleted_at IS NULL",
            [$sessionId]
        );
        if (!$session) {
            throw new ApiException(404, 'Không tìm thấy buổi học.');
        }

        $role = (string)($user['role'] ?? '');
        $userId = (int)($user['userId'] ?? 0);
        $allowed = false;

        if ($role === 'ADMIN') {
            $allowed = true;
        } elseif ($role === 'GIAO_VU') {
            $allowed = empty($user['coSoId']) || (int)$user['coSoId'] === (int)$session['co_so_id'];
        } elseif (in_array($role, ['GIAO_VIEN', 'GIA_SU', 'TRO_GIANG'], true)) {
            $assigned = Store::one(
                "SELECT id FROM phan_cong_buoi_hoc WHERE buoi_hoc_id=? AND user_id=? AND trang_thai<>'da_go' LIMIT 1",
                [$sessionId, $userId]
            );
            $isMainTeacher = Store::one(
                "SELECT id FROM nhan_su_chuyen_mon WHERE user_id=? AND id=? AND deleted_at IS NULL LIMIT 1",
                [$userId, (int)($session['nguoi_day_chinh_id'] ?? 0)]
            );
            $allowed = $assigned !== null || $isMainTeacher !== null;
        } elseif ($role === 'PHU_HUYNH') {
            $enrolled = Store::one(
                "SELECT lhv.id
                 FROM lop_hoc_vien lhv
                 JOIN hoc_vien hv ON hv.id=lhv.hoc_vien_id AND hv.deleted_at IS NULL
                 WHERE hv.phu_huynh_user_id=?
                   AND lhv.lop_id=?
                   AND lhv.trang_thai IN ('dang_hoc','hoc_thu','cho_xep_lop')
                   AND (?<>'bu' OR ?<>'ca_nhan' OR EXISTS (
                       SELECT 1 FROM hoc_bu_hoc_vien hb WHERE hb.buoi_bu_id=? AND hb.hoc_vien_id=hv.id
                   ))
                 LIMIT 1",
                [$userId, (int)$session['lop_id'], $session['loai'], $session['pham_vi_bu'], $sessionId]
            );
            $allowed = $enrolled !== null;
        }

        if (!$allowed) {
            throw new ApiException(403, 'Bạn không thuộc lớp học này nên không có quyền truy cập tài liệu.', 'FORBIDDEN');
        }

        $meta = self::readMeta($sessionId);
        if (!$meta || empty($meta['storedName'])) {
            throw new ApiException(404, 'Buổi học chưa có tệp tài liệu tải lên.');
        }

        $dir = realpath(self::storageDir());
        $target = realpath(self::storageDir() . DIRECTORY_SEPARATOR . basename((string)$meta['storedName']));
        if ($dir === false || $target === false || !str_starts_with($target, $dir . DIRECTORY_SEPARATOR) || !is_file($target)) {
            throw new ApiException(404, 'Tệp tài liệu không tồn tại trên máy chủ.');
        }

        $mime = (string)($meta['mimeType'] ?? 'application/octet-stream');
        $inline = str_starts_with($mime, 'application/pdf') || str_starts_with($mime, 'image/') || str_starts_with($mime, 'text/plain');

        return [
            'path' => $target,
            'originalName' => (string)($meta['originalName'] ?? basename($target)),
            'mimeType' => $mime,
            'disposition' => $inline ? 'inline' : 'attachment',
        ];
    }

    /**
     * List homeworks / materials from PREVIOUS sessions of the same class (ordered newest first)
     * so the session logbook grades strictly previous sessions' homework.
     *
     * @param array<string, mixed> $session
     * @return array{latest:?array<string,mixed>, items:list<array<string,mixed>>}
     */
    public static function previousHomeworks(array $session): array
    {
        $items = Store::rows(
            "SELECT bt.id, bt.buoi_hoc_id, bt.tieu_de, bt.noi_dung, bt.file_de_bai_url, bt.han_nop, bt.updated_at,
                    b.ngay AS ngay_buoi_hoc, b.gio_bat_dau AS gio_buoi_hoc, b.chu_de_bai_hoc AS chu_de_buoi_hoc
             FROM bai_tap bt
             JOIN buoi_hoc b ON b.id=bt.buoi_hoc_id
             WHERE b.lop_id=?
               AND b.id<>?
               AND b.trang_thai NOT IN ('huy','doi')
               AND (b.ngay < ? OR (b.ngay = ? AND b.gio_bat_dau < ?))
             ORDER BY b.ngay DESC, b.gio_bat_dau DESC, bt.id DESC",
            [
                (int)$session['lop_id'],
                (int)$session['id'],
                $session['ngay'],
                $session['ngay'],
                $session['gio_bat_dau'],
            ]
        );

        return [
            'latest' => $items[0] ?? null,
            'items' => $items,
        ];
    }

    /**
     * Validate and resolve the previous-session bai_tap_id for grading a student in the session logbook.
     * Prevents creating new homework inside the logbook and prevents duplicate grading of the same
     * (hoc_vien_id, bai_tap_id) across different sessions.
     *
     * @param array<string, mixed> $session
     * @param array<string, mixed>|null $existingRow
     */
    public static function resolveGradingHomeworkId(
        array $session,
        ?int $requestedHomeworkId,
        ?array $existingRow,
        int $studentId,
        ?float $score,
        ?string $homeworkComment
    ): ?int {
        $existingBtId = isset($existingRow['bai_tap_id']) && $existingRow['bai_tap_id'] !== null
            ? (int)$existingRow['bai_tap_id']
            : null;

        // If neither score nor homework comment is entered, keep null (or clear when ungrading).
        if ($score === null && ($homeworkComment === null || $homeworkComment === '')) {
            return null;
        }

        $prev = self::previousHomeworks($session);
        $prevIds = array_map(static fn(array $row): int => (int)$row['id'], $prev['items']);

        $targetId = $requestedHomeworkId ?? $existingBtId ?? (isset($prev['latest']['id']) ? (int)$prev['latest']['id'] : null);
        if ($targetId === null) {
            throw new ApiException(400, 'Chưa có bài tập về nhà từ buổi học trước để chấm điểm.');
        }

        // Allow updating an already-saved historical bai_tap_id on the exact same row,
        // otherwise strictly require a homework belonging to a PREVIOUS session of the same class.
        $isHistoricalSelf = $existingBtId !== null && $targetId === $existingBtId;
        if (!$isHistoricalSelf && !in_array($targetId, $prevIds, true)) {
            throw new ApiException(400, 'Chỉ được chấm bài tập về nhà đã chuẩn bị ở buổi học trước của cùng lớp.');
        }

        // Anti-duplicate: reject grading the same (student, homework) in a DIFFERENT session.
        if ($score !== null) {
            $duplicate = Store::one(
                "SELECT s.id, b.ngay
                 FROM so_theo_doi_buoi_hoc s
                 JOIN buoi_hoc b ON b.id=s.buoi_hoc_id
                 WHERE s.hoc_vien_id=? AND s.bai_tap_id=? AND s.buoi_hoc_id<>? AND s.diem_bai_tap_giay IS NOT NULL
                 LIMIT 1",
                [$studentId, $targetId, (int)$session['id']]
            );
            if ($duplicate !== null) {
                throw new ApiException(409, 'Học viên này đã được chấm điểm cho bài tập buổi trước ở buổi học ngày ' . $duplicate['ngay'] . '. Không thể chấm trùng.');
            }
        }

        return $targetId;
    }
}
