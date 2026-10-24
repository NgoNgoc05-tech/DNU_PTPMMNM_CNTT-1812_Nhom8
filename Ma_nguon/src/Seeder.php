<?php

declare(strict_types=1);

/** Nạp dữ liệu mẫu từ database/seed-data.php bằng chính các lớp nghiệp vụ của ứng dụng. */
final class Seeder
{
    /** @return array{users:array<string,int>,projects:array<string,int>,versions:array<string,int>,annotations:int[]} */
    public static function run(PgSql\Connection $c, ?array $data = null, string $emailSuffix = ''): array
    {
        $data ??= require __DIR__ . '/../database/seed-data.php';
        $fixtures = __DIR__ . '/../database/fixtures/';
        return transaction($c, function () use ($c, $data, $fixtures, $emailSuffix): array {
            $users = [];
            foreach ($data['users'] as $key => $u) {
                $email = $emailSuffix === '' ? $u['email'] : str_replace('@', '+' . $emailSuffix . '@', $u['email']);
                $users[$key] = Auth::createUser($c, $email, $u['name'], $data['password']);
            }
            $projectRepo = new ProjectRepository($c);
            $screenRepo = new ScreenRepository($c);
            $projects = [];
            $versions = [];
            foreach ($data['projects'] as $pk => $p) {
                $pid = $projectRepo->create($p['name'], $p['description'], $users[$p['owner']]);
                $projects[$pk] = $pid;
                foreach ($p['members'] as $uk => $role) {
                    queryParams($c, 'INSERT INTO project_members (project_id, user_id, role, added_by) VALUES ($1, $2, $3, $4)',
                        [$pid, $users[$uk], $role, $users[$p['owner']]]);
                }
                foreach ($p['screens'] as $name => $files) {
                    $ids = $screenRepo->create($pid, $name, ImageStore::fromFile($fixtures . $files[0]), $users[$p['owner']], 'Bản đầu tiên');
                    $versions["$pk|$name|1"] = $ids['version_id'];
                    for ($i = 1; $i < count($files); $i++) {
                        $v = $screenRepo->addVersion($ids['screen_id'], ImageStore::fromFile($fixtures . $files[$i]), $users[$p['owner']], 'Sửa theo góp ý');
                        $versions["$pk|$name|" . ($i + 1)] = $v['id'];
                    }
                }
            }
            $repo = new AnnotationRepository($c);
            $annotations = [];
            foreach ($data['annotations'] as $row) {
                [$pk, $screen, $vno, $shape, $x, $y, $w, $h, $author, $body, $replies, $history] = $row;
                $m = $row[12] ?? [];
                $versionId = $versions["$pk|$screen|$vno"];
                $meta = ['priority' => $m['p'] ?? 'MEDIUM', 'category' => $m['c'] ?? 'OTHER'];
                if (isset($m['to'])) {
                    $meta['assignee_id'] = $users[$m['to']];
                }
                if (isset($m['due'])) {
                    $meta['due_date'] = date('Y-m-d', strtotime(sprintf('%+d days', $m['due'])));
                }
                $a = $repo->create($versionId, $shape, $x, $y, $w, $h, $body, $users[$author], $meta);
                foreach ($replies as [$ru, $rbody]) {
                    $repo->reply($a['id'], $rbody, $users[$ru]);
                }
                foreach ($m['react'] ?? [] as [$ru, $kind]) {
                    $repo->toggleReaction($a['comment_id'], $kind, $users[$ru]);
                }
                $lock = $a['lock_version'];
                if (isset($m['note'])) {
                    $lock = $repo->update($a['id'], ['note' => $m['note']], $lock, $users[$m['to'] ?? $author])['lock_version'];
                }
                foreach ($history as [$to, $hu, $checkedNo, $note]) {
                    $res = $repo->changeStatus($a['id'], $to, $lock, $users[$hu], $versions["$pk|$screen|$checkedNo"], $note);
                    $lock = $res['lock_version'];
                }
                $annotations[] = $a['id'];
            }
            return ['users' => $users, 'projects' => $projects, 'versions' => $versions, 'annotations' => $annotations];
        });
    }
}
