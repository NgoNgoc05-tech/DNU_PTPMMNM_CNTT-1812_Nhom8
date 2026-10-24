<?php

declare(strict_types=1);

final class ScreenRepository
{
    public function __construct(private PgSql\Connection $c)
    {
    }

    /** Tạo màn hình và phiên bản v1 trong cùng một giao dịch. */
    public function create(int $projectId, string $name, array $image, int $userId, string $note = ''): array
    {
        $name = cleanName($name);
        return transaction($this->c, function () use ($projectId, $name, $image, $userId, $note): array {
            $screen = fetchOne($this->c,
                'INSERT INTO screens (project_id, name, created_by) VALUES ($1, $2, $3) RETURNING id',
                [$projectId, $name, $userId]);
            $screenId = (int) $screen['id'];
            $versionId = $this->insertVersion($screenId, $image, $userId, $note);
            return ['screen_id' => $screenId, 'version_id' => $versionId];
        });
    }

    /**
     * Xoá một phiên bản ảnh tải nhầm: xoá hàng image_versions (CASCADE góp ý, nhận xét, lịch sử của phiên bản đó)
     * rồi xoá tệp ảnh. Không cho xoá phiên bản duy nhất (khi đó xoá cả màn hình).
     * @return array{screen_id:int,version_no:int}
     */
    public function deleteVersion(int $versionId): array
    {
        $v = $this->version($versionId);
        transaction($this->c, function () use ($v): void {
            fetchOne($this->c, 'SELECT id FROM screens WHERE id = $1 FOR UPDATE', [$v['screen_id']]);
            $count = (int) fetchOne($this->c, 'SELECT COUNT(*) AS n FROM image_versions WHERE screen_id = $1', [$v['screen_id']])['n'];
            if ($count <= 1) {
                throw new AppError('LAST_VERSION', 422);
            }
            queryParams($this->c, 'DELETE FROM image_versions WHERE id = $1', [$v['id']]);
        });
        @unlink(ImageStore::path($v['stored_name']));
        return ['screen_id' => $v['screen_id'], 'version_no' => $v['version_no']];
    }

    /** Xoá màn hình cùng mọi phiên bản, góp ý và tệp ảnh của nó. @return int project_id */
    public function deleteScreen(int $screenId): int
    {
        $screen = $this->find($screenId);
        $files = array_column(fetchAll($this->c, 'SELECT stored_name FROM image_versions WHERE screen_id = $1', [$screenId]), 'stored_name');
        queryParams($this->c, 'DELETE FROM screens WHERE id = $1', [$screenId]);
        foreach ($files as $f) {
            @unlink(ImageStore::path($f));
        }
        return (int) $screen['project_id'];
    }

    public function rename(int $screenId, string $name): void
    {
        queryParams($this->c, 'UPDATE screens SET name = $2 WHERE id = $1', [$screenId, cleanName($name)]);
    }

    /**
     * YÊU CẦU KỸ THUẬT 2 — tải bản mới KHÔNG đụng tới phiên bản cũ hay góp ý cũ:
     * chỉ thêm một hàng image_versions mới với version_no = max + 1.
     * Khoá hàng screens (FOR UPDATE) để hai người tải cùng lúc không trùng số phiên bản.
     */
    public function addVersion(int $screenId, array $image, int $userId, string $note = ''): array
    {
        return transaction($this->c, function () use ($screenId, $image, $userId, $note): array {
            if (fetchOne($this->c, 'SELECT id FROM screens WHERE id = $1 FOR UPDATE', [$screenId]) === null) {
                throw new AppError('NOT_FOUND', 404);
            }
            $versionId = $this->insertVersion($screenId, $image, $userId, $note);
            return $this->version($versionId);
        });
    }

    private function insertVersion(int $screenId, array $image, int $userId, string $note): int
    {
        $row = fetchOne($this->c,
            'INSERT INTO image_versions
                (screen_id, version_no, stored_name, original_name, mime_type, width, height, byte_size, sha256, note, uploaded_by)
             VALUES ($1, (SELECT COALESCE(MAX(version_no), 0) + 1 FROM image_versions WHERE screen_id = $1),
                     $2, $3, $4, $5, $6, $7, $8, $9, $10)
             RETURNING id',
            [$screenId, $image['stored_name'], $image['original_name'], $image['mime'], $image['width'],
             $image['height'], $image['byte_size'], $image['sha256'], mb_substr(trim($note), 0, 255), $userId]);
        return (int) $row['id'];
    }

    public function listForProject(int $projectId): array
    {
        return fetchAll($this->c,
            "SELECT s.id, s.name,
                    lv.id AS latest_version_id, lv.version_no AS latest_version_no, lv.width, lv.height,
                    (SELECT COUNT(*) FROM image_versions v WHERE v.screen_id = s.id) AS versions,
                    (SELECT COUNT(*) FROM annotations a JOIN image_versions v ON v.id = a.image_version_id
                      WHERE v.screen_id = s.id AND a.status = 'OPEN') AS open_items,
                    (SELECT COUNT(*) FROM annotations a JOIN image_versions v ON v.id = a.image_version_id
                      WHERE v.screen_id = s.id) AS total_items,
                    lv.created_at AS updated_at
               FROM screens s
               JOIN LATERAL (SELECT id, version_no, width, height, created_at FROM image_versions
                              WHERE screen_id = s.id ORDER BY version_no DESC LIMIT 1) lv ON true
              WHERE s.project_id = $1
              ORDER BY s.id", [$projectId]);
    }

    public function find(int $screenId): array
    {
        $row = fetchOne($this->c, 'SELECT id, project_id, name FROM screens WHERE id = $1', [$screenId]);
        if ($row === null) {
            throw new AppError('NOT_FOUND', 404);
        }
        return $row;
    }

    public function versions(int $screenId): array
    {
        return fetchAll($this->c,
            "SELECT v.id, v.version_no, v.width, v.height, v.original_name, v.note, v.created_at, u.name AS uploader,
                    (SELECT COUNT(*) FROM annotations a WHERE a.image_version_id = v.id) AS annotations,
                    (SELECT COUNT(*) FROM annotations a WHERE a.image_version_id = v.id AND a.status = 'OPEN') AS open_items
               FROM image_versions v JOIN users u ON u.id = v.uploaded_by
              WHERE v.screen_id = $1 ORDER BY v.version_no", [$screenId]);
    }

    public function version(int $versionId): array
    {
        $row = fetchOne($this->c,
            'SELECT v.id, v.screen_id, v.version_no, v.stored_name, v.mime_type, v.width, v.height, v.original_name,
                    v.note, v.created_at, s.project_id, s.name AS screen_name
               FROM image_versions v JOIN screens s ON s.id = v.screen_id WHERE v.id = $1', [$versionId]);
        if ($row === null) {
            throw new AppError('NOT_FOUND', 404);
        }
        foreach (['id', 'screen_id', 'version_no', 'width', 'height', 'project_id'] as $k) {
            $row[$k] = (int) $row[$k];
        }
        return $row;
    }
}
