<?php
require __DIR__.'/bootstrap.php';
rate_limit('crawl');
$data=input();
$page=fetch_page((string)($data['url']??''));
echo json_encode(['technologies'=>technologies($page['html'])]);
