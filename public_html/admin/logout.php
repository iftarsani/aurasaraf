<?php
require __DIR__ . '/../../aurasaraf_app/bootstrap.php';
if (is_post()) { csrf_check(); admin_logout(); }
redirect('admin/login.php');
