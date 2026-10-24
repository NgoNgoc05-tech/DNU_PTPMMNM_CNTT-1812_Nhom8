<?php

declare(strict_types=1);

require __DIR__ . '/../src/web.php';
redirect(isset($_SESSION['user_id']) ? '/projects.php' : '/login.php');
