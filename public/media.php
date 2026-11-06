<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';
if (!is_file(APP_ROOT . '/config/config.php')) { http_response_code(503); exit; }
$config=app_config(); if(session_status()!==PHP_SESSION_ACTIVE){session_name($config['app']['session_name']??'onboarding_session');session_start();}
$user=require_user(['agency_admin','office_admin','client']);
ensure_audit_schema();
$id=preg_replace('/[^a-f0-9-]/','',strtolower((string)($_GET['id']??$_POST['id']??'')));
if(!$id || strlen($id)!==36){http_response_code(400);exit('Mídia inválida.');}
$s=db()->prepare('SELECT * FROM media_assets WHERE id=? AND status=?');$s->execute([$id,'ready']);$asset=$s->fetch();
if(!$asset){audit((int)$user['id'],'download_media','media',$id,'error','Arquivo não encontrado.');http_response_code(404);exit('Arquivo não encontrado.');}
if ($user['role'] === 'client') {
  $lessonId=(int)($_GET['lesson']??0);
  $check=db()->prepare("SELECT l.id FROM lessons l WHERE l.id=? AND (l.media_id=? OR EXISTS (SELECT 1 FROM lesson_materials lm WHERE lm.lesson_id=l.id AND lm.media_id=?))");
  $check->execute([$lessonId,$id,$id]);
  if (!$check->fetchColumn()) { audit((int)$user['id'],'download_media','media',$id,'error','Acesso negado ao arquivo.');http_response_code(403); exit('Acesso negado.'); }
}
$path=APP_ROOT.'/storage/uploads/'.$asset['storage_key'];
if(!is_file($path)){audit((int)$user['id'],'download_media','media',$id,'error','Arquivo indisponível no armazenamento.');http_response_code(404);exit('Arquivo indisponível.');}
audit((int)$user['id'],'download_media','media',$id);
header('Content-Type: '.($asset['mime_type']?:'application/octet-stream'));header('Content-Length: '.filesize($path));header('Content-Disposition: inline; filename="'.rawurlencode($asset['original_name']).'"');header('X-Content-Type-Options: nosniff');readfile($path);
