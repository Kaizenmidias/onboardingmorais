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
ensure_groups_schema();
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

    // Garante o acesso administrativo da agência nesta instalação. A conta só
    // é criada quando ainda não existe, preservando qualquer senha alterada
    // posteriormente pelo administrador.
    $agencyEmail = 'agenciakaizendesign@gmail.com';
    $checkAgency = db()->prepare('SELECT id FROM users WHERE email=? LIMIT 1');
    $checkAgency->execute([$agencyEmail]);
    if (!$checkAgency->fetchColumn()) {
        db()->prepare('INSERT INTO users(name,email,password_hash,role,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?)')->execute([
            'Agência Kaizen Design', $agencyEmail, password_hash('Kaizen@@2026', PASSWORD_DEFAULT),
            'agency_admin', 'active', now(), now()
        ]);
    }

    // Recupera o acesso de teste do cliente Ricardo caso a instalação tenha
    // sido publicada sem os dados iniciais. O cadastro é idempotente.
    $ricardoEmail = 'ricardorpc11@gmail.com';
    $checkRicardo = db()->prepare('SELECT id,client_id FROM users WHERE email=? LIMIT 1');
    $checkRicardo->execute([$ricardoEmail]);
    $ricardo = $checkRicardo->fetch();
    if (!$ricardo) {
        db()->prepare('INSERT INTO clients(name,company,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([
            'Ricardo Pereira Cardoso', 'Cliente de teste', $ricardoEmail, 'active', now(), now()
        ]);
        $clientId = (int)db()->lastInsertId();
        db()->prepare('INSERT INTO users(name,email,password_hash,role,status,client_id,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)')->execute([
            'Ricardo Pereira Cardoso', $ricardoEmail, password_hash('Kaizen@@2026', PASSWORD_DEFAULT),
            'client', 'active', $clientId, now(), now()
        ]);
    } elseif (!$ricardo['client_id']) {
        // O bootstrap já vincula órfãos, mas cria o vínculo imediatamente para
        // que o login funcione mesmo antes de uma chamada de bootstrap.
        db()->prepare('INSERT INTO clients(name,company,email,status,created_at,updated_at) VALUES(?,?,?,?,?,?)')->execute([
            'Ricardo Pereira Cardoso', 'Cliente de teste', $ricardoEmail, 'active', now(), now()
        ]);
        $clientId = (int)db()->lastInsertId();
        db()->prepare('UPDATE users SET client_id=?,status=?,updated_at=? WHERE id=?')->execute([$clientId, 'active', now(), (int)$ricardo['id']]);
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
        foreach ($settings as $row) { if ($row['setting_key'] === 'mail.pass') continue; $out[$row['setting_key']] = $row['value_type']==='json' ? json_decode($row['setting_value'],true) : $row['setting_value']; }
        $courses = db()->query("SELECT id,title,slug,category,description,cover_image,landscape_image,sort_order,status,sequence_required FROM courses WHERE status <> 'archived' ORDER BY sort_order,id")->fetchAll();
        foreach ($courses as &$course) { $s=db()->prepare("SELECT m.id,m.title,m.description,m.sort_order,m.status,l.id lesson_id,l.title lesson_title,l.body,l.duration_seconds,l.media_id,l.sort_order lesson_order,l.status lesson_status FROM modules m LEFT JOIN lessons l ON l.module_id=m.id AND l.status <> 'archived' WHERE m.course_id=? AND m.status <> 'archived' ORDER BY m.sort_order,m.id,l.sort_order,l.id"); $s->execute([$course['id']]); $mods=[]; foreach($s as $r){$mid=(int)$r['id']; if(!isset($mods[$mid]))$mods[$mid]=['id'=>$mid,'title'=>$r['title'],'description'=>$r['description'],'lessons'=>[]]; if($r['lesson_id']){$lesson=['id'=>(int)$r['lesson_id'],'title'=>$r['lesson_title'],'body'=>$r['body'],'duration_seconds'=>(int)$r['duration_seconds'],'media_id'=>$r['media_id'] ? (string)$r['media_id'] : null,'materials'=>[]];try{$ms=db()->prepare('SELECT media_id FROM lesson_materials WHERE lesson_id=?');$ms->execute([(int)$r['lesson_id']]);$lesson['materials']=array_map('strval',$ms->fetchAll(PDO::FETCH_COLUMN));}catch(Throwable $ignored){}$mods[$mid]['lessons'][]=$lesson;}} $course['modules']=array_values($mods); }
        $media = db()->query("SELECT id,original_name,mime_type,file_size,status,created_at FROM media_assets WHERE status='ready' ORDER BY created_at DESC,id DESC")->fetchAll();
        $clients = [];
        $groups = [];
        if ($user && in_array($user['role'], ['agency_admin','office_admin'], true)) {
            $clients = db()->query("SELECT c.id,c.name,c.company,c.email,c.cnpj,c.phone,c.whatsapp,c.status,c.expires_at,NULL AS last_access_at,GROUP_CONCAT(DISTINCT cgm.group_id) AS group_ids FROM clients c LEFT JOIN client_group_members cgm ON cgm.client_id=c.id GROUP BY c.id ORDER BY c.id DESC")->fetchAll();
            $groups = db()->query("SELECT g.id,g.name,g.description,COUNT(cgm.client_id) AS member_count FROM client_groups g LEFT JOIN client_group_members cgm ON cgm.group_id=g.id WHERE g.status='active' GROUP BY g.id ORDER BY g.name")->fetchAll();
        }
        $notes = [];
        if ($user) { try { $legacy = db()->prepare('SELECT lesson_id,note_text,updated_at FROM lesson_notes WHERE user_id=?'); $legacy->execute([(int)$user['id']]); foreach ($legacy as $note) { $key=(string)$note['lesson_id']; $decoded=json_decode((string)$note['note_text'],true); if (is_array($decoded) && array_is_list($decoded)) $notes[$key]=array_values(array_filter($decoded,fn($entry)=>is_array($entry)&&!empty($entry['text']))); else $notes[$key]=[['id'=>'legacy','text'=>$note['note_text'],'created_at'=>$note['updated_at']]]; } } catch (Throwable $ignored) {} }
        // Expose only non-secret, effective SMTP values so the admin form can
        // start populated from config.php even before any DB override exists.
        $effectiveMail = portal_mail_config();
        unset($effectiveMail['pass']);
        $out['mail'] = array_merge($effectiveMail, is_array($out['mail'] ?? null) ? $out['mail'] : []);
        json_response(['user'=>$user,'settings'=>$out,'courses'=>$courses,'clients'=>$clients,'groups'=>$groups,'media'=>$media,'notes'=>$notes,'csrf'=>csrf_token()]);
    }
    if ($action === 'mail.settings') {
        $user = admin_user(); require_method('POST'); check_csrf(); $i = json_input();
        $host = trim((string)($i['host'] ?? '')); $port = (int)($i['port'] ?? 465); $encryption = strtolower(trim((string)($i['encryption'] ?? 'ssl'))); $mailUser = strtolower(trim((string)($i['user'] ?? ''))); $pass = (string)($i['pass'] ?? '');
        if ($host === '' || $port < 1 || $port > 65535 || !in_array($encryption, ['ssl','tls','none'], true) || !filter_var($mailUser, FILTER_VALIDATE_EMAIL)) json_response(['error' => 'Informe servidor, porta, criptografia e usuário SMTP válidos.'], 422);
        $save = static function (string $key, string $value) use ($user): void { db()->prepare('INSERT INTO settings(setting_key,setting_value,value_type,updated_at,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),value_type=VALUES(value_type),updated_at=VALUES(updated_at),updated_by=VALUES(updated_by)')->execute([$key,$value,'string',now(),$user['id']]); };
        $save('mail.host', $host); $save('mail.port', (string)$port); $save('mail.encryption', $encryption); $save('mail.user', $mailUser); if ($pass !== '') $save('mail.pass', $pass);
        audit($user['id'], 'update_mail_settings', 'setting', 'mail'); json_response(['ok' => true, 'mail' => ['host'=>$host,'port'=>$port,'encryption'=>$encryption,'user'=>$mailUser]]);
    }
    if ($action === 'mail.test') {
        $user = admin_user(); require_method('POST'); check_csrf(); $i = json_input(); $mail = portal_mail_config();
        foreach (['host','port','encryption','user','pass'] as $field) if (array_key_exists($field, $i) && (string)$i[$field] !== '') $mail[$field] = $field === 'port' ? (int)$i[$field] : trim((string)$i[$field]);
        $to = strtolower(trim((string)($i['to'] ?? ''))); $diagnostic = portal_mail_diagnostic($mail, $to);
        if ($diagnostic['ok']) {
            // The successful test confirms these are the credentials the
            // portal must use for recovery, invitations and notifications.
            $save = static function (string $key, string $value) use ($user): void { db()->prepare('INSERT INTO settings(setting_key,setting_value,value_type,updated_at,updated_by) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),value_type=VALUES(value_type),updated_at=VALUES(updated_at),updated_by=VALUES(updated_by)')->execute([$key,$value,'string',now(),$user['id']]); };
            foreach (['host'=>(string)$mail['host'],'port'=>(string)$mail['port'],'encryption'=>(string)$mail['encryption'],'user'=>(string)$mail['user'],'pass'=>(string)$mail['pass']] as $key=>$value) $save('mail.'.$key,$value);
            $diagnostic['saved'] = true;
        }
        $result = $diagnostic['ok'] ? 'success' : 'error'; audit($user['id'], 'test_mail', 'mail', $to, $result, (string)($diagnostic['message'] ?? '')); json_response($diagnostic, $diagnostic['ok'] ? 200 : 422);
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
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $name=trim((string)($i['name']??'')); $company=trim((string)($i['company']??'')); $email=strtolower(trim((string)($i['email']??''))); $password=(string)($i['password']??''); if(!$name||!$company||!filter_var($email,FILTER_VALIDATE_EMAIL))json_response(['error'=>'Nome, empresa e e-mail válido são obrigatórios.'],422); if(!$id && strlen($password)<8)json_response(['error'=>'A senha deve ter pelo menos 8 caracteres.'],422); db()->beginTransaction(); try{if($id){db()->prepare('UPDATE clients SET name=?,company=?,email=?,cnpj=?,phone=?,whatsapp=?,status=?,expires_at=?,updated_at=? WHERE id=?')->execute([$name,$company,$email,$i['cnpj']??null,$i['phone']??null,$i['whatsapp']??null,$i['status']??'active',$i['expires_at']??null,now(),$id]); if($password!=='') db()->prepare('UPDATE users SET name=?,email=?,password_hash=?,status=?,updated_at=? WHERE client_id=?')->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),($i['status']??'active')==='active'?'active':'inactive',now(),$id]); else db()->prepare('UPDATE users SET name=?,email=?,status=?,updated_at=? WHERE client_id=?')->execute([$name,$email,($i['status']??'active')==='active'?'active':'inactive',now(),$id]);}else{db()->prepare('INSERT INTO clients(name,company,email,cnpj,phone,whatsapp,status,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?)')->execute([$name,$company,$email,$i['cnpj']??null,$i['phone']??null,$i['whatsapp']??null,$i['status']??'pending',$i['expires_at']??null,now(),now()]);$id=(int)db()->lastInsertId();db()->prepare('INSERT INTO users(client_id,name,email,password_hash,role,status,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)')->execute([$id,$name,$email,password_hash($password,PASSWORD_DEFAULT),'client','active',now(),now()]);}db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;} $mailSent=true; if(!$id || $password==='') $mailSent=false; else { $portalUrl=((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost'); $mailBody="Olá, {$name}!\n\nSua conta na área do cliente foi criada.\n\nLink de acesso: {$portalUrl}/#/login\nE-mail: {$email}\nSenha: {$password}\n\nPor segurança, recomendamos alterar sua senha após o primeiro acesso.\n\nAtenciosamente,\nMorais Contabilidade"; $mailSent=portal_mail($email,'Sua conta na área do cliente foi criada',$mailBody,'gildasiomorais@moraiscontabilidade.com'); } audit($user['id'],'save_client','client',$id); json_response(['ok'=>true,'id'=>$id,'email_sent'=>$mailSent,'warning'=>$mailSent?null:'Conta criada, mas não foi possível enviar o e-mail de acesso. Verifique o SMTP.']);
    }
    if ($action === 'group.save') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $id=(int)($i['id']??0); $name=trim((string)($i['name']??'')); $description=trim((string)($i['description']??''));
        if ($name==='') json_response(['error'=>'Nome do grupo é obrigatório.'],422);
        if ($id) { db()->prepare("UPDATE client_groups SET name=?,description=?,updated_at=? WHERE id=? AND status='active'")->execute([$name,$description,now(),$id]); }
        else { db()->prepare("INSERT INTO client_groups(name,description,status,created_at,updated_at) VALUES(?,?, 'active',?,?)")->execute([$name,$description,now(),now()]); $id=(int)db()->lastInsertId(); }
        $ids=array_values(array_unique(array_filter(array_map('intval',(array)($i['client_ids']??[])),static fn(int $v):bool=>$v>0)));
        db()->prepare('DELETE FROM client_group_members WHERE group_id=?')->execute([$id]); $ins=db()->prepare('INSERT IGNORE INTO client_group_members(group_id,client_id,created_at) VALUES(?,?,?)'); foreach($ids as $clientId) $ins->execute([$id,$clientId,now()]);
        audit($user['id'],'save_group','client_group',$id); json_response(['ok'=>true,'id'=>$id]);
    }
    if ($action === 'group.delete') {
        $user=admin_user(); require_method('POST'); check_csrf(); $id=(int)(json_input()['id']??0); if(!$id) json_response(['error'=>'Grupo inválido.'],422); db()->prepare("UPDATE client_groups SET status='archived',updated_at=? WHERE id=?")->execute([now(),$id]); audit($user['id'],'archive_group','client_group',$id); json_response(['ok'=>true]);
    }
    if ($action === 'client.delete') {
        $user=admin_user(); require_method('POST'); check_csrf(); $id=(int)(json_input()['id']??0); if(!$id)json_response(['error'=>'Cliente inválido.'],422); db()->prepare('DELETE FROM clients WHERE id=?')->execute([$id]); audit($user['id'],'delete_client','client',$id); json_response(['ok'=>true]);
    }
    if ($action === 'notification.send') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input();
        $template=trim((string)($i['template']??'custom')); $subject=trim((string)($i['subject']??'')); $body=trim((string)($i['body']??'')); $clientId=(int)($i['client_id']??0); $groupId=(int)($i['group_id']??0); $selectedIds=array_values(array_unique(array_filter(array_map('intval',is_array($i['client_ids']??null)?$i['client_ids']:[]),static fn(int $id): bool=>$id>0)));
        if ($template==='new_lesson') {
            $subject='Uma nova aula foi adicionada';
            $body=trim($body) !== '' ? $body : "Uma nova aula foi adicionada à sua área do cliente.\n\nAcesse o portal para conferir os novos conteúdos:\n{portal_url}\n\nAtenciosamente,\nMorais Contabilidade";
        }
        if ($subject==='' || $body==='') json_response(['error'=>'Assunto e mensagem são obrigatórios.'],422);
        if ($selectedIds) { $placeholders=implode(',',array_fill(0,count($selectedIds),'?')); $s=db()->prepare("SELECT name,email FROM clients WHERE id IN ($placeholders) AND status='active' AND email<>'' ORDER BY name"); $s->execute($selectedIds); $recipients=$s->fetchAll(); $clientId=0; }
        elseif ($groupId) { $s=db()->prepare("SELECT c.name,c.email FROM clients c JOIN client_group_members cgm ON cgm.client_id=c.id WHERE cgm.group_id=? AND c.status='active' AND c.email<>'' ORDER BY c.name"); $s->execute([$groupId]); $recipients=$s->fetchAll(); }
        elseif ($clientId) { $s=db()->prepare("SELECT name,email FROM clients WHERE id=? AND status='active' AND email<>''"); $s->execute([$clientId]); $recipients=$s->fetchAll(); }
        else { $recipients=db()->query("SELECT name,email FROM clients WHERE status='active' AND email<>'' ORDER BY name")->fetchAll(); }
        if (!$recipients) json_response(['error'=>'Nenhum cliente ativo encontrado.'],422);
        $from='gildasiomorais@moraiscontabilidade.com'; $portalUrl=((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost');
        $sent=0; foreach($recipients as $recipient) { $to=(string)$recipient['email']; $personalBody=str_replace(['{nome}','{portal_url}'],[(string)$recipient['name'],$portalUrl],$body); if (filter_var($to,FILTER_VALIDATE_EMAIL) && portal_mail($to,$subject,$personalBody,$from)) $sent++; }
        audit($user['id'],'send_notification','notification',$clientId ?: ($selectedIds?implode(',',$selectedIds):'all'),$sent===count($recipients)?'success':'warning',$sent.' de '.count($recipients).' e-mail(s) enviado(s).'); if($sent===0) json_response(['error'=>'Não foi possível enviar a notificação. Verifique a configuração de e-mail da hospedagem.','sent'=>0,'total'=>count($recipients)],502); json_response(['ok'=>true,'sent'=>$sent,'total'=>count($recipients)]);
    }
    if ($action === 'invite.send') {
        $user=admin_user(); require_method('POST'); check_csrf(); $i=json_input(); $clientId=(int)($i['client_id']??0); if(!$clientId)json_response(['error'=>'Cliente inválido.'],422);
        $s=db()->prepare('SELECT u.id,u.email,u.name FROM users u WHERE u.client_id=?'); $s->execute([$clientId]); $target=$s->fetch(); if(!$target)json_response(['error'=>'Acesso do cliente não encontrado.'],404);
        $raw=bin2hex(random_bytes(32)); db()->prepare("INSERT INTO auth_tokens(user_id,purpose,token_hash,expires_at,created_at) VALUES(?,?,?,?,?)")->execute([$target['id'],'invite',hash('sha256',$raw),gmdate('Y-m-d H:i:s',time()+86400),now()]);
        $url=((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').'#/convite?token='.$raw; $from='gildasiomorais@moraiscontabilidade.com'; $ok=portal_mail($target['email'],'Seu acesso ao portal de onboarding',"Olá, {$target['name']}!\n\nDefina sua senha acessando:\n{$url}\n\nO link expira em 24 horas.",$from); if(!$ok){audit($user['id'],'send_invite','client',$clientId,'error','Falha no SMTP da hospedagem.');json_response(['error'=>'Não foi possível enviar o convite. Verifique a configuração de e-mail.'],502);} audit($user['id'],'send_invite','client',$clientId); json_response(['ok'=>true]);
    }
    if ($action === 'password.reset.request') {
        require_method('POST'); check_csrf(); $i=json_input(); $email=strtolower(trim((string)($i['email']??''))); $s=db()->prepare('SELECT id,name,email FROM users WHERE email=? AND status="active"'); $s->execute([$email]); $target=$s->fetch();
        if($target){$raw=bin2hex(random_bytes(32)); db()->prepare("INSERT INTO auth_tokens(user_id,purpose,token_hash,expires_at,created_at) VALUES(?,?,?,?,?)")->execute([$target['id'],'reset',hash('sha256',$raw),gmdate('Y-m-d H:i:s',time()+3600),now()]); $url=((!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off')?'https':'http').'://'.($_SERVER['HTTP_HOST']??'localhost').'#/redefinir?token='.$raw; $from='gildasiomorais@moraiscontabilidade.com'; $sent=portal_mail($target['email'],'Recuperação de acesso',"Olá, {$target['name']}!\n\nRedefina sua senha em:\n{$url}\n\nO link expira em 1 hora.",$from); if(!$sent){audit(null,'send_password_reset','user',$target['id'],'error','Falha no SMTP da hospedagem.'); json_response(['error'=>'Não foi possível enviar o e-mail de recuperação. Verifique a configuração de e-mail da hospedagem.'],502);} audit((int)$target['id'],'send_password_reset','user',$target['id']);}
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
        $user=require_user(['client','agency_admin','office_admin']); require_method('POST'); check_csrf(); $i=json_input(); $lesson=(int)($i['lesson_id']??0); $note=trim((string)($i['note']??'')); if(!$lesson||$note==='')json_response(['error'=>'A anotação não pode ficar vazia.'],422); $existing=db()->prepare('SELECT note_text FROM lesson_notes WHERE user_id=? AND lesson_id=?'); $existing->execute([$user['id'],$lesson]); $old=(string)($existing->fetchColumn()?:''); $list=json_decode($old,true); if (!is_array($list)||!array_is_list($list)) $list=$old!==''?[['id'=>'legacy','text'=>$old,'created_at'=>now()]]:[]; $list[]=['id'=>bin2hex(random_bytes(8)),'text'=>$note,'created_at'=>now()]; $payload=json_encode($list,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES); db()->prepare('INSERT INTO lesson_notes(user_id,lesson_id,note_text,updated_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE note_text=VALUES(note_text),updated_at=VALUES(updated_at)')->execute([$user['id'],$lesson,$payload,now()]); audit((int)$user['id'],'save_note','lesson',$lesson); json_response(['ok'=>true]);
    }
    if ($action === 'lesson.save_toggle') {
        $user=require_user(['client','agency_admin','office_admin']); require_method('POST'); check_csrf(); $i=json_input(); $lesson=(int)($i['lesson_id']??0); $saved=!empty($i['saved']); if(!$lesson)json_response(['error'=>'Aula inválida.'],422); if($saved) db()->prepare('INSERT IGNORE INTO saved_lessons(user_id,lesson_id,created_at) VALUES(?,?,?)')->execute([$user['id'],$lesson,now()]); else db()->prepare('DELETE FROM saved_lessons WHERE user_id=? AND lesson_id=?')->execute([$user['id'],$lesson]); audit((int)$user['id'],$saved?'save_lesson':'unsave_lesson','lesson',$lesson); json_response(['ok'=>true]);
    }
    json_response(['error'=>'Ação não encontrada.'],404);
} catch (Throwable $e) {
    error_log($e->getMessage()); $current=current_user(); audit_failure($current ? (int)$current['id'] : null, $action ?: 'unknown', 'request', null, $e); json_response(['error'=>'Não foi possível concluir a operação.'],500);
}
