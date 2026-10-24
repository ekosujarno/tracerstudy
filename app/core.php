<?php
declare(strict_types=1);

const APP_VERSION = '1.0.0';
date_default_timezone_set('Asia/Jakarta');
const APP_TITLE = 'Tracer Study Kampus';

function config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $file = dirname(__DIR__) . '/config/config.php';
        if (!is_file($file)) { header('Location: install.php'); exit; }
        $cfg = require $file;
    }
    return $cfg;
}
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $c = config();
        $pdo = new PDO('mysql:host='.$c['db_host'].';port='.(int)$c['db_port'].';dbname='.$c['db_name'].';charset=utf8mb4', $c['db_user'], $c['db_pass'], [
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,
        ]);
    }
    return $pdo;
}
function query(string $sql, array $params=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($params); return $s; }
function all(string $sql, array $params=[]): array { return query($sql,$params)->fetchAll(); }
function one(string $sql, array $params=[]): ?array { $v=query($sql,$params)->fetch(); return $v === false ? null : $v; }
function val(string $sql, array $params=[]): mixed { return query($sql,$params)->fetchColumn(); }
function esc(mixed $text): string { return htmlspecialchars((string)($text ?? ''), ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8'); }
function url(string $page='', array $params=[]): string { return 'index.php?' . http_build_query(array_merge(['page'=>$page ?: 'home'],$params)); }
function redirect(string $page, array $params=[]): never { header('Location: '.url($page,$params),true,303); exit; }
function csrf(): string { if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32)); return $_SESSION['csrf']; }
function csrf_field(): string { return '<input type="hidden" name="csrf" value="'.esc(csrf()).'">'; }
function check_csrf(): void { if (!isset($_POST['csrf']) || !hash_equals(csrf(), (string)$_POST['csrf'])) { http_response_code(419); exit('Sesi formulir telah kedaluwarsa. Muat ulang halaman.'); } }
function flash(string $msg, string $type='success'): void { $_SESSION['flash']=['msg'=>$msg,'type'=>$type]; }
function show_flash(): void { if (!empty($_SESSION['flash'])) { $m=$_SESSION['flash']; unset($_SESSION['flash']); echo '<div class="alert '.($m['type']==='error'?'alert-error':'alert-success').'">'.esc($m['msg']).'</div>'; } }
function setting(string $key, string $default=''): string { try {$x=val('SELECT value FROM settings WHERE `key`=?',[$key]); return $x===false?$default:(string)$x;} catch(Throwable){return $default;} }
function set_setting(string $key,string $value): void { query('INSERT INTO settings (`key`,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)',[$key,$value]); }
function auth(): ?array {
    static $memo = false;
    if ($memo !== false) return $memo;
    $id = (int)($_SESSION['uid'] ?? 0);
    $memo = $id ? one('SELECT u.*, p.name AS prodi_name FROM users u LEFT JOIN programs p ON p.id=u.prodi_id WHERE u.id=? AND u.is_active=1',[$id]) : null;
    return $memo;
}
function user(): array { $u=auth(); if (!$u) { flash('Silakan login terlebih dahulu.','error'); redirect('login'); } return $u; }
function role(array|string $roles): bool { $u=auth(); return $u && in_array($u['role'],(array)$roles,true); }
function require_role(array|string $roles): array { $u=user(); if(!in_array($u['role'],(array)$roles,true)){http_response_code(403); exit('403 - Anda tidak berhak mengakses halaman ini.');} return $u; }
function admin_role(): bool {return role(['admin','operator','leader']);}
function admin_access(string $permission='view'): array { return require_role($permission==='write'?['admin','operator']:['admin','operator','leader']); }
function own_prodi(): ?int { $u=auth(); return $u && $u['role']==='operator' && $u['prodi_id'] ? (int)$u['prodi_id'] : null; }
function log_action(string $action, string $target='', string $detail=''): void {
    try { query('INSERT INTO activity_logs(user_id,action,target,details,ip_address) VALUES(?,?,?,?,?)',[(int)(auth()['id']??0)?:null,$action,$target,mbcut($detail,480),substr($_SERVER['REMOTE_ADDR']??'',0,45)]); }catch(Throwable){}
}
function mbcut(string $str,int $len): string { return function_exists('mb_substr')?mb_substr($str,0,$len):substr($str,0,$len); }
function current_period(): ?array { return one('SELECT * FROM periods WHERE is_active=1 ORDER BY id DESC LIMIT 1'); }
function statuses(): array {return ['bekerja'=>'Bekerja','wirausaha'=>'Wirausaha','studi'=>'Melanjutkan Studi','mencari'=>'Mencari Pekerjaan','belum'=>'Belum Bekerja','lainnya'=>'Lainnya'];}
function money(float|int|string|null $n): string {return 'Rp '.number_format((float)$n,0,',','.');}
function percentage(int $a,int $b): string {return $b>0?number_format(100*$a/$b,1,',','.').'%':'0%';}
function capture(string $key,int $max=255): string{return mbcut(trim((string)($_POST[$key]??'')),$max);}
function int_post(string $key): int{return (int)($_POST[$key]??0);}
function valid_date(string $v): bool { $d=DateTimeImmutable::createFromFormat('!Y-m-d',$v); return $d!==false && $d->format('Y-m-d')===$v; }
function period_open(array $p): bool { $today=date('Y-m-d');return $today >= $p['start_date'] && $today <= $p['end_date']; }
function respond_for(int $alumni,int $period): ?array {return one('SELECT * FROM responses WHERE alumni_id=? AND period_id=?',[$alumni,$period]);}
function response_id(int $alumni,int $period): int { $row=respond_for($alumni,$period); if ($row) return (int)$row['id']; query('INSERT INTO responses(alumni_id,period_id,progress,status) VALUES(?,?,1,\'draft\')',[$alumni,$period]);return (int)db()->lastInsertId(); }
function csrf_action(string $action, array $fields=[]): string {
    $html='<form method="post" class="inline-form"><input type="hidden" name="action" value="'.esc($action).'">'.csrf_field();
    foreach($fields as $k=>$v)$html.='<input type="hidden" name="'.esc($k).'" value="'.esc($v).'">';
    return $html;
}
function safe_phone(string $s): string { return preg_replace('/[^0-9]/','',$s)??''; }
function encrypt_code(string $code): string {return hash('sha256',$code);}
function invite_code(): string {return strtoupper(bin2hex(random_bytes(5)));}
function permission_prodi(?int $prodiId): bool { $own=own_prodi(); return $own===null || $prodiId===$own; }
function http_security(): void {
    header('X-Content-Type-Options: nosniff'); header('Referrer-Policy: strict-origin-when-cross-origin'); header('X-Frame-Options: SAMEORIGIN');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'");
    header('Cache-Control: no-store, private');
}
