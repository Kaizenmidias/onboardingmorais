<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';
if (!is_file(APP_ROOT . '/config/config.php')) { http_response_code(503); exit; }
$config=app_config(); if(session_status()!==PHP_SESSION_ACTIVE){session_name($config['app']['session_name']??'onboarding_session');session_start();}
$user=require_user(['agency_admin','office_admin','client']);
$id=preg_replace('/[^a-f0-9-]/','',strtolower((string)($_GET['id']??$_POST['id']??'')));
if(!$id || strlen($id)!==36){http_response_code(400);exit('Mídia inválida.');}
$s=db()->prepare('SELECT * FROM media_assets WHERE id=? AND status=?');$s->execute([$id,'ready']);$asset=$s->fetch();
if(!$asset){http_response_code(404);exit('Arquivo não encontrado.');}
if ($user['role'] === 'client') {
  $lessonId=(int)($_GET['lesson']??0);
  $check=db()->prepare("SELECT l.id FROM lessons l JOIN modules m ON m.id=l.module_id JOIN courses c ON c.id=m.course_id WHERE l.id=? AND l.media_id=? AND l.status='published' AND m.status='published' AND c.status='published'");
  $check->execute([$lessonId,$id]);
  if (!$check->fetchColumn()) { http_response_code(403); exit('Acesso negado.'); }
}
$path=rtrim($config['app']['upload_dir'],'/\\').DIRECTORY_SEPARATOR.$asset['storage_key'];
if(!is_file($path)){http_response_code(404);exit('Arquivo indisponível.');}
header('Content-Type: '.($asset['mime_type']?:'application/octet-stream'));header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="'.rawurlencode($asset['original_name']).'"');header('X-Content-Type-Options: nosniff');readfile($path);
