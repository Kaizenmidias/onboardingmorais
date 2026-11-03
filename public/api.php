<?php
declare(strict_types=1);
require dirname(__DIR__) . '/app/bootstrap.php';

if (!is_file(APP_ROOT . '/config/config.php')) json_response(['error' => 'Instalação pendente.', 'install' => true], 503);
$config = app_config();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['app']['session_name'] ?? 'onboarding_session');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
    session_start();
}

$action = $_GET['action'] ?? '';
try {
    if ($action === 'csrf') json_response(['csrf' => csrf_token()]);
    if ($action === 'session') json_response(['user' => current_user(), 'csrf' => csrf_token()]);
    if ($action === 'login') {
        require_method('POST'); check_csrf(); $input = json_input();
        $email = strtolower(trim((string)($input['email'] ?? ''))); $password = (string)($input['password'] ?? '');
        $s = db()->prepare('SELECT id,name,email,password_hash,role,status,client_id,auth_version FROM users WHERE email=? LIMIT 1'); $s->execute([$email]); $user = $s->fetch();
        if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash']) || $user['status'] !== 'active') json_response(['error' => 'E-mail ou senha inválidos.'], 422);
        session_regenerate_id(true); $_SESSION['user'] = ['id'=>(int)$user['id'],'name'=>$user['name'],'email'=>$user['email'],'role'=>$user['role'],'client_id'=>$user['client_id'] ? (int)$user['client_id'] : null,'auth_version'=>(int)$user['auth_version']];
        db()->prepare('UPDATE users SET last_login_at=?,updated_at=? WHERE id=?')->execute([now(),now(),$user['id']]); audit((int)$user['id'],'login','user',$user['id']);
        json_response(['user'=>$_SESSION['user'],'csrf'=>csrf_token()]);
    }
    if ($action === 'logout') { require_method('POST'); check_csrf(); $id = current_user()['id'] ?? null; session_destroy(); if ($id) audit((int)$id,'logout','user',$id); json_response(['ok'=>true]); }
    if ($action === 'bootstrap') {
        $user = current_user();
        $settings = db()->query('SELECT setting_key,setting_value,value_type FROM settings')->fetchAll(); $out=[];
        foreach ($settings as $row) $out[$row['setting_key']] = $row['value_type']==='json' ? json_decode($row['setting_value'],true) : $row['setting_value'];
        $courses = db()->query("SELECT id,title,slug,category,description,cover_image,landscape_image,sort_order,status,sequence_required FROM courses WHERE status <> 'archived' ORDER BY sort_order,id")->fetchAll();
        foreach ($courses as &$course) { $s=db()->prepare("SELECT m.id,m.title,m.description,m.sort_order,m.status,l.id lesson_id,l.title lesson_title,l.body,l.duration_seconds,l.media_id,l.sort_order lesson_order,l.status lesson_status FROM modules m LEFT JOIN lessons l ON l.module_id=m.id AND l.status <> 'archived' WHERE m.course_id=? AND m.status <> 'archived' ORDER BY m.sort_order,m.id,l.sort_order,l.id"); $s->execute([$course['id']]); $mods=[]; foreach($s as $r){$mid=(int)$r['id']; if(!isset($mods[$mid]))$mods[$mid]=['id'=>$mid,'title'=>$r['title'],'description'=>$r['description'],'lessons'=>[]]; if($r['lesson_id'])$mods[$mid]['lessons'][]=['id'=>(int)$r['lesson_id'],'title'=>$r['lesson_title'],'body'=>$r['body'],'duration_seconds'=>(int)$r['duration_seconds'],'media_id'=>$r['media_id'] ? (string)$r['media_id'] : null];} $course['modules']=array_values($mods); }
        json_response(['user'=>$user,'settings'=>$out,'courses'=>$courses,'csrf'=>csrf_token()]);
    }
    if ($action === 'setting') {
        $user=admin_user(); require_method('POST'); check_csrf(); $input=json_input(); $key=trim((string)($input['key']??'')); if(!preg_match('/^[a-z][a-z0-9_.-]{1,119}$/',$key))json_response(['error'=>'Chave inválida.'],422); $value=$input['value']??''; $type=is_array($value)||is_object($value)?'json':'string'; $stored=$type==='json'?json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):(string)$value; db()->prepare('INSERT INTO settings(setting_key,setting_value,value_type,updated_at,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),value_type=VALUES(value_type),updated_at=VALUES(updated_at),updated_by=VALUES(updated_by)')->execute([$key,$stored,$type,now(),$user['id']]); audit($user['id'],'update_setting','setting',$key); json_response(['ok'=>true,'key'=>$key,'value'=>$value]);
    }
    if ($action === 'course.save') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $title=trim((string)($i['title']??'')); if($title==='')json_response(['error'=>'Título obrigatório.'],422); $slug=slugify((string)($i['slug']??$title)); $status=in_array($i['status']??'draft',['draft','published','archived'],true)?$i['status']:'draft'; if($id){$s=db()->prepare('UPDATE courses SET title=?,slug=?,category=?,description=?,cover_image=?,landscape_image=?,status=?,sequence_required=?,updated_at=? WHERE id=?');$s->execute([$title,$slug,$i['category']??'',$i['description']??'',$i['cover_image']??null,$i['landscape_image']??null,$status,!empty($i['sequence_required']),now(),$id]);}else{$s=db()->prepare('INSERT INTO courses(title,slug,category,description,cover_image,landscape_image,status,sequence_required,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)');$s->execute([$title,$slug,$i['category']??'',$i['description']??'',$i['cover_image']??null,$i['landscape_image']??null,$status,!empty($i['sequence_required']),now(),now()]);$id=(int)db()->lastInsertId();} audit($user['id'],'save_course','course',$id); json_response(['ok'=>true,'id'=>$id]);
    }
    if ($action === 'module.save') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $course=(int)($i['course_id']??0); $title=trim((string)($i['title']??'')); if(!$course||$title==='')json_response(['error'=>'Curso e título são obrigatórios.'],422); if($id){db()->prepare('UPDATE modules SET title=?,description=?,status=?,updated_at=? WHERE id=? AND course_id=?')->execute([$title,$i['description']??'',$i['status']??'published',now(),$id,$course]);}else{db()->prepare('INSERT INTO modules(course_id,title,description,status,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$course,$title,$i['description']??'',$i['status']??'published',now(),now()]);$id=(int)db()->lastInsertId();} audit($user['id'],'save_module','module',$id); json_response(['ok'=>true,'id'=>$id]);
    }
    if ($action === 'lesson.save') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $module=(int)($i['module_id']??0); $title=trim((string)($i['title']??'')); if(!$module||$title==='')json_response(['error'=>'Módulo e título são obrigatórios.'],422); $duration=max(0,(int)($i['duration_seconds']??0)); if($id){db()->prepare('UPDATE lessons SET title=?,slug=?,body=?,duration_seconds=?,media_id=?,status=?,updated_at=? WHERE id=? AND module_id=?')->execute([$title,slugify((string)($i['slug']??$title)),$i['body']??'',$duration,$i['media_id']??null,$i['status']??'draft',now(),$id]);}else{db()->prepare('INSERT INTO lessons(module_id,title,slug,body,duration_seconds,media_id,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$module,$title,slugify((string)($i['slug']??$title)),$i['body']??'',$duration,$i['media_id']??null,$i['status']??'draft',now(),now()]);$id=(int)db()->lastInsertId();} audit($user['id'],'save_lesson','lesson',$id); json_response(['ok'=>true,'id'=>$id]);
    }
    if ($action === 'client.save') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $name=trim((string)($i['name']??'')); $company=trim((string)($i['company']??'')); $email=strtolower(trim((string)($i['email']??''))); if(!$name||!$company||!filter_var($email,FILTER_VALIDATE_EMAIL))json_response(['error'=>'Nome, empresa e e-mail válido são obrigatórios.'],422); db()->beginTransaction(); try{if($id){db()->prepare('UPDATE clients SET name=?,company=?,email=?,cnpj=?,phone=?,whatsapp=?,status=?,expires_at=?,updated_at=? WHERE id=?')->execute([$name,$company,$email,$i['cnpj']??null,$i['phone']??null,$i['whatsapp']??null,$i['status']??'active',$i['expires_at']??null,now(),$id]);db()->prepare('UPDATE users SET name=?,email=?,status=?,updated_at=? WHERE client_id=?')->execute([$name,$email,($i['status']??'active')==='active'?'active':'inactive',now(),$id]);}else{db()->prepare('INSERT INTO clients(name,company,email,cnpj,phone,whatsapp,status,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$name,$company,$email,$i['cnpj']??null,$i['phone']??null,$i['whatsapp']??null,$i['status']??'pending',$i['expires_at']??null,now(),now()]);$id=(int)db()->lastInsertId();db()->prepare('INSERT INTO users(client_id,name,email,role,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute([$id,$name,$email,'client','invited',now(),now()]);}db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}audit($user['id'],'save_client','client',$id);json_response(['ok'=>true,'id'=>$id]);
    }
    if ($action === 'progress.complete') {
        $user=require_user(['client','agency_admin','office_admin']); require_method('POST'); check_csrf(); $i=json_input(); $lesson=(int)($i['lesson_id']??0); if(!$lesson)json_response(['error'=>'Aula inválida.'],422); $clientId=$user['client_id']??(int)($i['client_id']??0); if(!$clientId)json_response(['error'=>'Cliente inválido.'],422); $s=db()->prepare('SELECT e.id FROM enrollments e JOIN lessons l ON l.id=? JOIN modules m ON m.id=l.module_id WHERE e.client_id=? AND e.course_id=m.course_id');$s->execute([$lesson,$clientId]);$enroll=$s->fetchColumn();if(!$enroll){$s=db()->prepare('SELECT m.course_id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.id=?');$s->execute([$lesson]);$course=$s->fetchColumn();db()->prepare('INSERT IGNORE INTO enrollments(client_id,course_id,first_access_at,last_access_at,last_lesson_id)VALUES(?,?,?,?,?)')->execute([$clientId,$course,now(),now(),$lesson]);$s=db()->prepare('SELECT id FROM enrollments WHERE client_id=? AND course_id=?');$s->execute([$clientId,$course]);$enroll=$s->fetchColumn();} $done=empty($i['completed'])?null:now(); db()->prepare('INSERT INTO lesson_progress(enrollment_id,lesson_id,completed_at,updated_at)VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE completed_at=VALUES(completed_at),updated_at=VALUES(updated_at)')->execute([$enroll,$lesson,$done,now()]);db()->prepare('UPDATE enrollments SET last_lesson_id=?,last_access_at=? WHERE id=?')->execute([$lesson,now(),$enroll]);json_response(['ok'=>true]);
    }
    json_response(['error'=>'Ação não encontrada.'],404);
} catch (Throwable $e) {
    error_log($e->getMessage()); json_response(['error'=>'Não foi possível concluir a operação.'],500);
}
