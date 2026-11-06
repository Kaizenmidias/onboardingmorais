<?php
declare(strict_types=1);
require __DIR__ . '/app/bootstrap.php';

if (!is_file(APP_ROOT . '/config/config.php')) json_response(['error' => 'Instalação pendente.', 'install' => true], 503);
$config = app_config();
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name($config['app']['session_name'] ?? 'onboarding_session');
    session_set_cookie_params(['httponly' => true, 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'samesite' => 'Lax']);
    session_start();
}
ensure_audit_schema();
// Garante tabelas opcionais em instalações já existentes sem exigir acesso SSH.
try { db()->exec("CREATE TABLE IF NOT EXISTS lesson_notes (user_id BIGINT UNSIGNED NOT NULL, lesson_id BIGINT UNSIGNED NOT NULL, note_text LONGTEXT NOT NULL, updated_at DATETIME NOT NULL, PRIMARY KEY(user_id,lesson_id), CONSTRAINT fk_notes_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_notes_lesson FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); db()->exec("CREATE TABLE IF NOT EXISTS lesson_note_entries (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, lesson_id BIGINT UNSIGNED NOT NULL, note_text LONGTEXT NOT NULL, created_at DATETIME NOT NULL, KEY idx_note_entries_user_lesson (user_id,lesson_id), CONSTRAINT fk_note_entries_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_note_entries_lesson FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); db()->exec("CREATE TABLE IF NOT EXISTS saved_lessons (user_id BIGINT UNSIGNED NOT NULL, lesson_id BIGINT UNSIGNED NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(user_id,lesson_id), CONSTRAINT fk_saved_user FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE, CONSTRAINT fk_saved_lesson FOREIGN KEY(lesson_id) REFERENCES lessons(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $schemaError) { error_log($schemaError->getMessage()); }

$action = $_GET['action'] ?? '';
try { db()->exec("CREATE TABLE IF NOT EXISTS lesson_materials (lesson_id BIGINT UNSIGNED NOT NULL, media_id CHAR(36) NOT NULL, created_at DATETIME NOT NULL, PRIMARY KEY(lesson_id,media_id), KEY idx_lm_lesson (lesson_id), KEY idx_lm_media (media_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"); } catch (Throwable $schemaError) { error_log($schemaError->getMessage()); }
// Migra a instalação inicial que usava o identificador "kaizen" para os
// acessos administrativos definitivos. A rotina é idempotente e só altera a
// conta legada quando ainda não existe um administrador com e-mail válido.
try {
    $admins = db()->query("SELECT id,email FROM users WHERE role IN ('agency_admin','office_admin') ORDER BY id")->fetchAll();
    $hasValidAdmin = false;
    foreach ($admins as $admin) {
        if (filter_var((string)$admin['email'], FILTER_VALIDATE_EMAIL)) { $hasValidAdmin = true; break; }
    }
    if (!$hasValidAdmin && $admins) {
        db()->prepare('UPDATE users SET email=?,updated_at=? WHERE id=?')->execute(['agenciakaizendesign@gmail.com', now(), (int)$admins[0]['id']]);
    }
    $checkSecond = db()->prepare("SELECT id FROM users WHERE email=? LIMIT 1");
    $checkSecond->execute(['gildasiomorais@moraiscontabilidade.com']);
    $secondId = $checkSecond->fetchColumn();
    if ($secondId) {
        db()->prepare('UPDATE users SET name=?,password_hash=?,role=?,status=?,updated_at=? WHERE id=?')->execute([
            'Gildasio Morais', password_hash('+Godem2023', PASSWORD_DEFAULT), 'office_admin', 'active', now(), (int)$secondId
        ]);
    } else {
        db()->prepare('INSERT INTO users(name,email,password_hash,role,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute([
            'Gildasio Morais', 'gildasiomorais@moraiscontabilidade.com', password_hash('+Godem2023', PASSWORD_DEFAULT),
            'office_admin', 'active', now(), now()
        ]);
    }
} catch (Throwable $migrationError) { error_log($migrationError->getMessage()); }
try {
    if ($action === 'csrf') json_response(['csrf' => csrf_token()]);
    if ($action === 'session') json_response(['user' => current_user(), 'csrf' => csrf_token()]);
    if ($action === 'login') {
        require_method('POST'); check_csrf(); $input = json_input();
        $email = strtolower(trim((string)($input['email'] ?? ''))); $password = (string)($input['password'] ?? '');
        $s = db()->prepare("SELECT u.id,u.name,u.email,c.company,u.password_hash,u.role,u.status,u.client_id,u.auth_version FROM users u LEFT JOIN clients c ON c.id=u.client_id WHERE u.email=? AND u.status='active' AND (u.role<>'client' OR (((c.status='active') OR (c.status='pending' AND u.password_hash IS NOT NULL AND u.password_hash<>'')) AND (c.expires_at IS NULL OR c.expires_at>=CURDATE()))) LIMIT 1"); $s->execute([$email]); $user = $s->fetch();
        if (!$user || !$user['password_hash'] || !password_verify($password, $user['password_hash']) || $user['status'] !== 'active') { audit(null,'login','user',null,'error','Credenciais inválidas.'); json_response(['error' => 'E-mail ou senha inválidos.'], 422); }
        session_regenerate_id(true); $_SESSION['user'] = ['id'=>(int)$user['id'],'name'=>$user['name'],'email'=>$user['email'],'company'=>$user['company'] ?? null,'role'=>$user['role'],'client_id'=>$user['client_id'] ? (int)$user['client_id'] : null,'auth_version'=>(int)$user['auth_version']];
        db()->prepare('UPDATE users SET last_login_at=?,updated_at=? WHERE id=?')->execute([now(),now(),$user['id']]); audit((int)$user['id'],'login','user',$user['id']);
        json_response(['user'=>$_SESSION['user'],'csrf'=>csrf_token()]);
    }
    if ($action === 'logout') { require_method('POST'); check_csrf(); $id = current_user()['id'] ?? null; session_destroy(); if ($id) audit((int)$id,'logout','user',$id); json_response(['ok'=>true]); }
    if ($action === 'bootstrap') {
        // Contas com senha já definida estão prontas para acesso. Corrige
        // cadastros antigos que permaneceram como convite pendente.
        try { db()->exec("UPDATE clients c INNER JOIN users u ON u.client_id=c.id SET c.status='active', u.status='active' WHERE c.status='pending' AND u.password_hash IS NOT NULL AND u.password_hash<>''"); } catch (Throwable $activationError) { error_log($activationError->getMessage()); }
        $user = current_user();
        // Corrige acessos de cliente criados diretamente na tabela users sem o
        // respectivo registro em clients. Isso garante que contas existentes
        // (como Ricardo) apareçam no painel e continuem vinculadas ao acesso.
        if ($user && in_array($user['role'], ['agency_admin','office_admin'], true)) {
            $orphans = db()->query("SELECT id,name,email FROM users WHERE role='client' AND client_id IS NULL")->fetchAll();
            if ($orphans) {
                $insertClient = db()->prepare('INSERT INTO clients(name,company,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?)');
                $linkUser = db()->prepare('UPDATE users SET client_id=? WHERE id=?');
                foreach ($orphans as $orphan) {
                    $insertClient->execute([$orphan['name'], $orphan['name'], $orphan['email'], 'active', now(), now()]);
                    $linkUser->execute([(int)db()->lastInsertId(), (int)$orphan['id']]);
                }
            }
        }
        $settings = db()->query('SELECT setting_key,setting_value,value_type FROM settings')->fetchAll(); $out=[];
        foreach ($settings as $row) $out[$row['setting_key']] = $row['value_type']==='json' ? json_decode($row['setting_value'],true) : $row['setting_value'];
        $courses = db()->query("SELECT id,title,slug,category,description,cover_image,landscape_image,sort_order,status,sequence_required FROM courses WHERE status <> 'archived' ORDER BY sort_order,id")->fetchAll();
        foreach ($courses as &$course) { $s=db()->prepare("SELECT m.id,m.title,m.description,m.sort_order,m.status,l.id lesson_id,l.title lesson_title,l.body,l.duration_seconds,l.media_id,l.sort_order lesson_order,l.status lesson_status FROM modules m LEFT JOIN lessons l ON l.module_id=m.id AND l.status <> 'archived' WHERE m.course_id=? AND m.status <> 'archived' ORDER BY m.sort_order,m.id,l.sort_order,l.id"); $s->execute([$course['id']]); $mods=[]; foreach($s as $r){$mid=(int)$r['id']; if(!isset($mods[$mid]))$mods[$mid]=['id'=>$mid,'title'=>$r['title'],'description'=>$r['description'],'lessons'=>[]]; if($r['lesson_id']){$lesson=['id'=>(int)$r['lesson_id'],'title'=>$r['lesson_title'],'body'=>$r['body'],'duration_seconds'=>(int)$r['duration_seconds'],'media_id'=>$r['media_id'] ? (string)$r['media_id'] : null,'materials'=>[]];try{$ms=db()->prepare('SELECT media_id FROM lesson_materials WHERE lesson_id=?');$ms->execute([(int)$r['lesson_id']]);$lesson['materials']=array_map('strval',$ms->fetchAll(PDO::FETCH_COLUMN));}catch(Throwable $ignored){}$mods[$mid]['lessons'][]=$lesson;}} $course['modules']=array_values($mods); }
        $media = db()->query("SELECT id,original_name,mime_type,file_size,status,created_at FROM media_assets WHERE status='ready' ORDER BY created_at DESC,id DESC")->fetchAll();
        $clients = [];
        if ($user && in_array($user['role'], ['agency_admin','office_admin'], true)) {
            $clients = db()->query("SELECT id,name,company,email,cnpj,phone,whatsapp,status,expires_at,NULL AS last_access_at FROM clients ORDER BY id DESC")->fetchAll();
        }
        $notes = [];
        if ($user) { try { $ns = db()->prepare('SELECT id,lesson_id,note_text,created_at FROM lesson_note_entries WHERE user_id=? ORDER BY created_at,id'); $ns->execute([(int)$user['id']]); foreach ($ns as $note) { $key=(string)$note['lesson_id']; $notes[$key] ??= []; $notes[$key][]=['id'=>(int)$note['id'],'text'=>$note['note_text'],'created_at'=>$note['created_at']]; } $legacy = db()->prepare('SELECT lesson_id,note_text,updated_at FROM lesson_notes WHERE user_id=?'); $legacy->execute([(int)$user['id']]); foreach ($legacy as $note) { $key=(string)$note['lesson_id']; if (empty($notes[$key])) $notes[$key]=[['id'=>'legacy','text'=>$note['note_text'],'created_at'=>$note['updated_at']]]; } } catch (Throwable $ignored) {} }
        json_response(['user'=>$user,'settings'=>$out,'courses'=>$courses,'clients'=>$clients,'media'=>$media,'notes'=>$notes,'csrf'=>csrf_token()]);
    }
    if ($action === 'setting') {
        $user=admin_user(); require_method('POST'); check_csrf(); $input=json_input(); $key=trim((string)($input['key']??'')); if(!preg_match('/^[a-z][a-z0-9_.-]{1,119}$/',$key))json_response(['error'=>'Chave inválida.'],422); $value=$input['value']??''; $type=is_array($value)||is_object($value)?'json':'string'; $stored=$type==='json'?json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):(string)$value; db()->prepare('INSERT INTO settings(setting_key,setting_value,value_type,updated_at,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),value_type=VALUES(value_type),updated_at=VALUES(updated_at),updated_by=VALUES(updated_by)')->execute([$key,$stored,$type,now(),$user['id']]); audit($user['id'],'update_setting','setting',$key); json_response(['ok'=>true,'key'=>$key,'value'=>$value]);
    }
    if ($action === 'course.save') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $title=trim((string)($i['title']??'')); if($title==='')json_response(['error'=>'Título obrigatório.'],422); $slug=slugify((string)($i['slug']??$title)); $status=in_array($i['status']??'draft',['draft','published','archived'],true)?$i['status']:'draft'; $sequence=(int)!empty($i['sequence_required']); if($id){$s=db()->prepare('UPDATE courses SET title=?,slug=?,category=?,description=?,cover_image=?,landscape_image=?,status=?,sequence_required=?,updated_at=? WHERE id=?');$s->execute([$title,$slug,$i['category']??'',$i['description']??'',$i['cover_image']??null,$i['landscape_image']??null,$status,$sequence,now(),$id]);}else{$s=db()->prepare('INSERT INTO courses(title,slug,category,description,cover_image,landscape_image,status,sequence_required,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)');$s->execute([$title,$slug,$i['category']??'',$i['description']??'',$i['cover_image']??null,$i['landscape_image']??null,$status,$sequence,now(),now()]);$id=(int)db()->lastInsertId();} audit($user['id'],'save_course','course',$id); $count=(int)db()->query("SELECT COUNT(*) FROM courses WHERE status <> 'archived'")->fetchColumn(); json_response(['ok'=>true,'id'=>$id,'count'=>$count]);
    }
    if ($action === 'module.save') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $course=(int)($i['course_id']??0); $title=trim((string)($i['title']??'')); if(!$course||$title==='')json_response(['error'=>'Curso e título são obrigatórios.'],422); if($id){db()->prepare('UPDATE modules SET title=?,description=?,status=?,updated_at=? WHERE id=? AND course_id=?')->execute([$title,$i['description']??'',$i['status']??'published',now(),$id,$course]);}else{db()->prepare('INSERT INTO modules(course_id,title,description,status,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([$course,$title,$i['description']??'',$i['status']??'published',now(),now()]);$id=(int)db()->lastInsertId();} audit($user['id'],'save_module','module',$id); json_response(['ok'=>true,'id'=>$id]);
    }
    if ($action === 'lesson.save') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $module=(int)($i['module_id']??0); $title=trim((string)($i['title']??'')); if(!$module||$title==='')json_response(['error'=>'Módulo e título são obrigatórios.'],422); $duration=max(0,(int)($i['duration_seconds']??0)); if($id){db()->prepare('UPDATE lessons SET title=?,slug=?,body=?,duration_seconds=?,media_id=?,status=?,updated_at=? WHERE id=? AND module_id=?')->execute([$title,slugify((string)($i['slug']??$title)),$i['body']??'',$duration,$i['media_id']??null,$i['status']??'draft',now(),$id]);}else{$existing=db()->prepare('SELECT id FROM lessons WHERE module_id=? AND title=? AND status<>"archived" ORDER BY id DESC LIMIT 1');$existing->execute([$module,$title]);$id=(int)($existing->fetchColumn()?:0);if($id){db()->prepare('UPDATE lessons SET slug=?,body=?,duration_seconds=?,media_id=?,status=?,updated_at=? WHERE id=?')->execute([slugify((string)($i['slug']??$title)),$i['body']??'',$duration,$i['media_id']??null,$i['status']??'draft',now(),$id]);}else{db()->prepare('INSERT INTO lessons(module_id,title,slug,body,duration_seconds,media_id,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([$module,$title,slugify((string)($i['slug']??$title)),$i['body']??'',$duration,$i['media_id']??null,$i['status']??'draft',now(),now()]);$id=(int)db()->lastInsertId();}} db()->prepare('DELETE FROM lesson_materials WHERE lesson_id=?')->execute([$id]); foreach((array)($i['materials']??[]) as $mid){$mid=trim((string)$mid);if($mid!=='')db()->prepare('INSERT IGNORE INTO lesson_materials(lesson_id,media_id,created_at) VALUES(?,?,?)')->execute([$id,$mid,now()]);} audit($user['id'],'save_lesson','lesson',$id); json_response(['ok'=>true,'id'=>$id]);
    }
    if ($action === 'content.delete') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $type=(string)($i['type']??''); $id=(int)($i['id']??0);
        if (!$id || !in_array($type,['course','module','lesson'],true)) json_response(['error'=>'Conteúdo inválido.'],422);
        $table=['course'=>'courses','module'=>'modules','lesson'=>'lessons'][$type];
        $s=db()->prepare("UPDATE {$table} SET status='archived',updated_at=? WHERE id=?"); $s->execute([now(),$id]); audit($user['id'],'archive_'.$type,$type,$id); json_response(['ok'=>true]);
    }
    if ($action === 'media.delete') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=trim((string)($i['id']??''));
        if ($id==='') json_response(['error'=>'Mídia inválida.'],422);
        $s=db()->prepare("SELECT storage_key FROM media_assets WHERE id=? AND status<>'deleted'"); $s->execute([$id]); $asset=$s->fetch(); if(!$asset)json_response(['error'=>'Mídia não encontrada.'],404); $path=APP_ROOT.'/storage/uploads/'.$asset['storage_key']; if(is_file($path))@unlink($path); $s=db()->prepare("UPDATE media_assets SET status='deleted',updated_at=? WHERE id=?"); $s->execute([now(),$id]); audit($user['id'],'delete_media','media',$id); json_response(['ok'=>true]);
    }
    if ($action === 'client.save') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $name=trim((string)($i['name']??'')); $company=trim((string)($i['company']??'')); $email=strtolower(trim((string)($i['email']??''))); $password=(string)($i['password']??''); if(!$name||!$company||!filter_var($email,FILTER_VALIDATE_EMAIL))json_response(['error'=>'Nome, empresa e e-mail válido são obrigatórios.'],422); if(!$id && strlen($password)<8)json_response(['error'=>'A senha deve ter pelo menos 8 caracteres.'],422); db()->beginTransaction(); try{if($id){db()->prepare('UPDATE clients SET name=?,company=?,email=?,cnpj=?,phone=?,whatsapp=?,status=?,expires_at=?,updated_at=? WHERE id=?')->execute([$name,$company,$email,$i['cnpj']??null,$i['phone']??null,$i['whatsapp']??null,$i['status']??'active',$i['expires_at']??null,now(),$id]); if($password!=='') db()->prepare('UPDATE users SET name=?,email=?,password_hash=?,status=?,updated_at=? WHERE client_id=?')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),($i['status']??'active')==='active'?'active':'inactive',now(),$id]); else db()->prepare('UPDATE users SET name=?,email=?,status=?,updated_at=? WHERE client_id=?')->execute([$name,$email,($i['status']??'active')==='active'?'active':'inactive',now(),$id]);}else{db()->prepare('INSERT INTO clients(name,company,email,cnpj,phone,whatsapp,status,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$name,$company,$email,$i['cnpj']??null,$i['phone']??null,$i['whatsapp']??null,$i['status']??'pending',$i['expires_at']??null,now(),now()]);$id=(int)db()->lastInsertId();db()->prepare('INSERT INTO users(client_id,name,email,password_hash,role,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$name,$email,password_hash($password,PASSWORD_DEFAULT),'client','active',now(),now()]);}db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}audit($user['id'],'save_client','client',$id);json_response(['ok'=>true,'id'=>$id]);
    }
    if ($action === 'client.delete') {
        $user=admin_user(); require_method('POST'); check_csrf(); $id=(int)(json_input()['id']??0); if(!$id)json_response(['error'=>'Cliente inválido.'],422); db()->prepare('DELETE FROM clients WHERE id=?')->execute([$id]); audit($user['id'],'delete_client','client',$id); json_response(['ok'=>true]);
    }
    if ($action === 'notification.send') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input();
        $subject=trim((string)($i['subject']??'')); $body=trim((string)($i['body']??'')); $clientId=(int)($i['client_id']??0);
        if ($subject==='' || $body==='') json_response(['error'=>'Assunto e mensagem são obrigatórios.'],422);
        if ($clientId) { $s=db()->prepare("SELECT email FROM clients WHERE id=? AND status='active'"); $s->execute([$clientId]); $recipients=array_filter([(string)$s->fetchColumn()]); }
        else { $recipients=array_column(db()->query("SELECT email FROM clients WHERE status='active' AND email<>''")->fetchAll(),'email'); }
        if (!$recipients) json_response(['error'=>'Nenhum cliente ativo encontrado.'],422);
        $from='gildasiomorais@moraiscontabilidade.com'; $headers="From: {$from}\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $sent=0; foreach($recipients as $to) if (filter_var($to,FILTER_VALIDATE_EMAIL) && @mail($to,$subject,$body,$headers)) $sent++;
        audit($user['id'],'send_notification','notification',$clientId ?: 'all',$sent===count($recipients)?'success':'warning',$sent.' de '.count($recipients).' e-mail(s) enviado(s).'); json_response(['ok'=>true,'sent'=>$sent,'total'=>count($recipients)]);
    }
    if ($action === 'invite.send') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $clientId=(int)($i['client_id']??0); if(!$clientId)json_response(['error'=>'Cliente inválido.'],422);
        $s=db()->prepare('SELECT u.id,u.email,u.name FROM users u WHERE u.client_id=?'); $s->execute([$clientId]); $target=$s->fetch(); if(!$target)json_response(['error'=>'Acesso do cliente não encontrado.'],404);
        $raw=bin2hex(random_bytes(32)); db()->prepare("INSERT INTO auth_tokens(user_id,purpose,token_hash,expires_at,created_at) VALUES(?,?,?,?,?)")->execute([$target['id'],'invite',hash('sha256',$raw),gmdate('Y-m-d H:i:s',time()+86400),now()]);
        $url=((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').'#/convite?token='.$raw; $from='gildasiomorais@moraiscontabilidade.com'; $headers="From: {$from}\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n"; $ok=@mail($target['email'],'Seu acesso ao portal de onboarding',"Olá, {$target['name']}!\n\nDefina sua senha acessando:\n{$url}\n\nO link expira em 24 horas.",$headers); if(!$ok){audit($user['id'],'send_invite','client',$clientId,'error','A hospedagem não aceitou o envio do e-mail.');json_response(['error'=>'A hospedagem não aceitou o envio do e-mail.'],502);} audit($user['id'],'send_invite','client',$clientId); json_response(['ok'=>true]);
    }
    if ($action === 'password.reset.request') {
        require_method('POST'); check_csrf(); $i=json_input(); $email=strtolower(trim((string)($i['email']??''))); $s=db()->prepare('SELECT id,name,email FROM users WHERE email=? AND status="active"'); $s->execute([$email]); $target=$s->fetch();
        if($target){$raw=bin2hex(random_bytes(32)); db()->prepare("INSERT INTO auth_tokens(user_id,purpose,token_hash,expires_at,created_at) VALUES(?,?,?,?,?)")->execute([$target['id'],'reset',hash('sha256',$raw),gmdate('Y-m-d H:i:s',time()+3600),now()]); $url=((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').'#/redefinir?token='.$raw; $from='gildasiomorais@moraiscontabilidade.com'; @mail($target['email'],'Recuperação de acesso',"Olá, {$target['name']}!\n\nRedefina sua senha em:\n{$url}\n\nO link expira em 1 hora.","From: {$from}\r\nReply-To: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n");}
        json_response(['ok'=>true]);
    }
    if ($action === 'password.reset.complete') {
        require_method('POST'); check_csrf(); $i=json_input(); $token=(string)($i['token']??''); $password=(string)($i['password']??''); if(strlen($password)<8||$token==='')json_response(['error'=>'Token ou senha inválidos.'],422); $s=db()->prepare("SELECT id,user_id FROM auth_tokens WHERE purpose='reset' AND token_hash=? AND used_at IS NULL AND revoked_at IS NULL AND expires_at>=?"); $s->execute([hash('sha256',$token),now()]); $row=$s->fetch(); if(!$row)json_response(['error'=>'Link expirado ou inválido.'],422); db()->prepare('UPDATE users SET password_hash=?,updated_at=? WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),now(),$row['user_id']]); db()->prepare('UPDATE auth_tokens SET used_at=? WHERE id=?')->execute([now(),$row['id']]); json_response(['ok'=>true]);
    }
    if ($action === 'reports.summary') {
        $user=admin_user(); $clients=(int)db()->query("SELECT COUNT(*) FROM clients")->fetchColumn(); $active=(int)db()->query("SELECT COUNT(*) FROM clients WHERE status='active'")->fetchColumn(); $courses=(int)db()->query("SELECT COUNT(*) FROM courses WHERE status='published'")->fetchColumn(); $lessons=(int)db()->query("SELECT COUNT(*) FROM lessons WHERE status='published'")->fetchColumn(); $media=(int)db()->query("SELECT COUNT(*) FROM media_assets WHERE status='ready'")->fetchColumn(); $completed=(int)db()->query("SELECT COUNT(*) FROM lesson_progress WHERE completed_at IS NOT NULL")->fetchColumn(); json_response(['clients'=>$clients,'active_clients'=>$active,'published_courses'=>$courses,'published_lessons'=>$lessons,'media_assets'=>$media,'completed_lessons'=>$completed]);
    }
    if ($action === 'audit.list') {
        admin_user();
        $page=max(1,(int)($_GET['page']??1)); $perPage=min(100,max(10,(int)($_GET['per_page']??25))); $offset=($page-1)*$perPage;
        $where=[]; $params=[]; $q=trim((string)($_GET['q']??'')); $result=trim((string)($_GET['result']??'')); $type=trim((string)($_GET['type']??''));
        if($q!==''){ $where[]='(ae.action_name LIKE ? OR ae.entity_type LIKE ? OR ae.entity_id LIKE ? OR ae.message LIKE ? OR u.name LIKE ? OR u.email LIKE ?)'; array_push($params,...array_fill(0,6,'%'.$q.'%')); }
        if(in_array($result,['success','warning','error'],true)){ $where[]='ae.result=?'; $params[]=$result; }
        if($type!==''){ $where[]='ae.entity_type=?'; $params[]=$type; }
        $sqlWhere=$where?' WHERE '.implode(' AND ',$where):'';
        $count=db()->prepare("SELECT COUNT(*) FROM audit_events ae LEFT JOIN users u ON u.id=ae.user_id{$sqlWhere}"); $count->execute($params); $total=(int)$count->fetchColumn();
        $rows=db()->prepare("SELECT ae.id,ae.action_name,ae.entity_type,ae.entity_id,ae.result,ae.message,ae.ip_address,ae.user_agent,ae.request_id,ae.created_at,u.name user_name,u.email user_email FROM audit_events ae LEFT JOIN users u ON u.id=ae.user_id{$sqlWhere} ORDER BY ae.created_at DESC,ae.id DESC LIMIT {$perPage} OFFSET {$offset}"); $rows->execute($params);
        json_response(['events'=>$rows->fetchAll(),'page'=>$page,'per_page'=>$perPage,'total'=>$total,'pages'=>max(1,(int)ceil($total/$perPage))]);
    }
    if ($action === 'progress.complete') {
        $user=require_user(['client','agency_admin','office_admin']); require_method('POST'); check_csrf(); $i=json_input(); $lesson=(int)($i['lesson_id']??0); if(!$lesson)json_response(['error'=>'Aula inválida.'],422); $clientId=$user['client_id']??(int)($i['client_id']??0); if(!$clientId)json_response(['error'=>'Cliente inválido.'],422); $s=db()->prepare('SELECT e.id FROM enrollments e JOIN lessons l ON l.id=? JOIN modules m ON m.id=l.module_id WHERE e.client_id=? AND e.course_id=m.course_id');$s->execute([$lesson,$clientId]);$enroll=$s->fetchColumn();if(!$enroll){$s=db()->prepare('SELECT m.course_id FROM lessons l JOIN modules m ON m.id=l.module_id WHERE l.id=?');$s->execute([$lesson]);$course=$s->fetchColumn();db()->prepare('INSERT IGNORE INTO enrollments(client_id,course_id,first_access_at,last_access_at,last_lesson_id)VALUES(?,?,?,?,?)')->execute([$clientId,$course,now(),now(),$lesson]);$s=db()->prepare('SELECT id FROM enrollments WHERE client_id=? AND course_id=?');$s->execute([$clientId,$course]);$enroll=$s->fetchColumn();} $done=empty($i['completed'])?null:now(); db()->prepare('INSERT INTO lesson_progress(enrollment_id,lesson_id,completed_at,updated_at)VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE completed_at=VALUES(completed_at),updated_at=VALUES(updated_at)')->execute([$enroll,$lesson,$done,now()]);db()->prepare('UPDATE enrollments SET last_lesson_id=?,last_access_at=? WHERE id=?')->execute([$lesson,now(),$enroll]);audit((int)$user['id'],'complete_lesson','lesson',$lesson);json_response(['ok'=>true]);
    }
    if ($action === 'lesson.note') {
        $user=require_user(['client','agency_admin','office_admin']); require_method('POST'); check_csrf(); $i=json_input(); $lesson=(int)($i['lesson_id']??0); $note=trim((string)($i['note']??'')); if(!$lesson||$note==='')json_response(['error'=>'A anotação não pode ficar vazia.'],422); db()->prepare('INSERT INTO lesson_note_entries(user_id,lesson_id,note_text,created_at) VALUES(?,?,?,?,?)')->execute([$user['id'],$lesson,$note,now()]); db()->prepare('INSERT INTO lesson_notes(user_id,lesson_id,note_text,updated_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE note_text=VALUES(note_text),updated_at=VALUES(updated_at)')->execute([$user['id'],$lesson,$note,now()]); audit((int)$user['id'],'save_note','lesson',$lesson); json_response(['ok'=>true]);
    }
    if ($action === 'lesson.save_toggle') {
        $user=require_user(['client','agency_admin','office_admin']); require_method('POST'); check_csrf(); $i=json_input(); $lesson=(int)($i['lesson_id']??0); $saved=!empty($i['saved']); if(!$lesson)json_response(['error'=>'Aula inválida.'],422); if($saved) db()->prepare('INSERT IGNORE INTO saved_lessons(user_id,lesson_id,created_at) VALUES(?,?,?)')->execute([$user['id'],$lesson,now()]); else db()->prepare('DELETE FROM saved_lessons WHERE user_id=? AND lesson_id=?')->execute([$user['id'],$lesson]); audit((int)$user['id'],$saved?'save_lesson':'unsave_lesson','lesson',$lesson); json_response(['ok'=>true]);
    }
    json_response(['error'=>'Ação não encontrada.'],404);
} catch (Throwable $e) {
    error_log($e->getMessage()); $current=current_user(); audit_failure($current ? (int)$current['id'] : null, $action ?: 'unknown', 'request', null, $e); json_response(['error'=>'Não foi possível concluir a operação.'],500);
}
