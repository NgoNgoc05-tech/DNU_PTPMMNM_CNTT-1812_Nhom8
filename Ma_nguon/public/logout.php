<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
if (isPost()) {
    checkCsrf($_POST);
    Auth::logout();
}
redirect('/login.php');
