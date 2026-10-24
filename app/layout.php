<?php
function icon(string $name): string {
    $icons=['cap'=>'🎓','home'=>'⌂','user'=>'♙','users'=>'♙','period'=>'▦','form'=>'▤','bell'=>'♧','chart'=>'▥','settings'=>'⚙','download'=>'⇩','company'=>'▣','key'=>'⚿','logout'=>'↪','files'=>'▤','shield'=>'♢','activity'=>'≋','program'=>'◈','check'=>'✓'];
    return '<span class="menu-icon" aria-hidden="true">'.($icons[$name]??'•').'</span>';
}
function sidebar_menu(string $page): void {
    $u=auth();$isAdmin=admin_role();
    $items=$isAdmin?[
        ['admin_dashboard','Dashboard','home'],['admin_alumni','Data Alumni','users'],['admin_import','Impor Alumni','download'],['admin_reminders','Pengingat Alumni','bell'],['admin_programs','Program Studi','program'],['admin_periods','Periode Survei','period'],['admin_questions','Kuesioner Tambahan','form'],['admin_responses','Responden','check'],['admin_employers','Pengguna Lulusan','company'],['admin_reports','Analitik & Laporan','chart']
    ]:[['alumni_home','Dashboard Saya','home'],['alumni_profile','Profil Alumni','user'],['survey','Isi Kuesioner','form']];
    if($u['role']==='leader')$items=[['admin_dashboard','Dashboard','home'],['admin_reports','Analitik & Laporan','chart']];
    if($u['role']==='admin'){$items[]=['admin_users','Manajemen User','users'];$items[]=['admin_settings','Pengaturan','settings'];$items[]=['admin_updates','Update Aplikasi','shield'];$items[]=['admin_logs','Log Aktivitas','activity'];}
    $logo=setting('campus_logo');$icon=$logo&&preg_match('/^[a-z_]+_[a-f0-9]{24}\.(png|jpg|webp)$/',$logo)?'<img class="brand-logo-img" src="assets/uploads/'.esc($logo).'" alt="Logo Kampus">':icon('cap');
    echo '<aside class="sidebar"><a class="sidebar-logo" href="'.url('home').'">'.$icon.'<span>TRACER STUDY<small>'.esc(setting('campus_name','KAMPUS')).'</small></span></a><div class="sidebar-label">MENU UTAMA</div><nav class="side-links">';
    foreach($items as [$p,$label,$ico])echo '<a class="'.($page===$p?'active':'').'" href="'.url($p).'">'.icon($ico).'<span>'.esc($label).'</span></a>';
    echo '</nav><div class="sidebar-end"><a href="'.url('home').'">'.icon('home').' Lihat Website</a><a href="'.url('logout').'">'.icon('logout').' Keluar</a></div></aside>';
}
function begin_page(string $title, string $page, bool $portal=false): void {
    $name=setting('campus_name',APP_TITLE);$u=auth();
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#075445"><meta name="description" content="Sistem Tracer Study Alumni Perguruan Tinggi"><title>'.esc($title).' | '.esc($name).'</title><link rel="stylesheet" href="assets/style.css"></head><body>';
    if(!$portal){echo '<div class="app-shell">';sidebar_menu($page);echo '<div class="app-main"><header class="topbar"><button type="button" class="menu-toggle" aria-label="Buka menu" data-toggle-sidebar>☰</button><div class="top-search"><span class="top-search-icon">⌕</span><span>Tracer Study Management System</span></div><div class="topbar-right"><span class="pill mini">◉ '.esc(current_period()['name']??'Belum ada survei aktif').'</span><div class="avatar">'.esc(strtoupper(substr($u['name']??'A',0,1))).'</div><div class="who"><b>'.esc($u['name']??'Admin').'</b><small>'.esc(ucfirst($u['role']??'')).'</small></div></div></header><div class="workspace">';}
    else {$logo=setting('campus_logo');$brandIcon=$logo&&preg_match('/^[a-z_]+_[a-f0-9]{24}\.(png|jpg|webp)$/',$logo)?'<img class="brand-logo-img" src="assets/uploads/'.esc($logo).'" alt="Logo Kampus">':icon('cap');echo '<header class="public-header"><div class="public-nav wrap"><a class="public-brand" href="'.url('home').'">'.$brandIcon.'<span>TRACER STUDY <b>KAMPUS</b><small>'.esc($name).'</small></span></a><button class="public-toggle" type="button" data-toggle-public>☰</button><nav class="public-links"><a href="'.url('home').'">Beranda</a><a href="'.url('about').'">Tentang</a><a href="'.url('guide').'">Panduan</a><a href="'.url('employer').'">Survei Pengguna</a>';if($u) echo '<a class="btn btn-primary btn-sm" href="'.url(admin_role()?'admin_dashboard':'alumni_home').'">Dashboard</a>';else echo '<a class="btn btn-outline btn-sm" href="'.url('login').'">Login Alumni / Admin</a>';echo '</nav></div></header>';}
}
function end_page(bool $portal=false): void {
    if($portal)echo '<footer class="public-footer"><div class="wrap footer-flex"><span>© '.date('Y').' '.esc(setting('campus_name',APP_TITLE)).'</span><span>'.esc(setting('footer_text','Tracer Study · Evaluasi Mutu Pendidikan')).' · <a href="'.url('privacy').'">Privasi</a></span></div></footer>';
    else echo '</div></div></div>';
    echo '<script src="assets/app.js" defer></script></body></html>';
}
function page_head(string $heading,string $subtitle='',string $extra=''): void {
    echo '<div class="page-heading"><div><p class="eyebrow">TRACER STUDY KAMPUS · V'.APP_VERSION.'</p><h1>'.esc($heading).'</h1><p class="muted">'.esc($subtitle).'</p></div><div class="heading-action">'.$extra.'</div></div>';
}
function stat_card(string $label,string $value,string $ico,string $sub='',string $color='green'): string {
    return '<div class="stat-card"><div class="stat-top"><span class="stat-icon '.$color.'">'.icon($ico).'</span><span class="subtle">◈</span></div><div class="stat-value">'.esc($value).'</div><div class="stat-label">'.esc($label).'</div><small class="muted">'.esc($sub).'</small></div>';
}
function form_label(string $label,string $input,bool $required=false): string {return '<label class="field"><span>'.esc($label).($required?' <em>*</em>':'').'</span>'.$input.'</label>';}
function select_html(string $name,array $options,string|int|null $selected=null,string $attrs=''): string { $out='<select name="'.esc($name).'" '.$attrs.'>'; foreach($options as $k=>$value)$out.='<option value="'.esc($k).'" '.((string)$selected===(string)$k?'selected':'').'>'.esc($value).'</option>';return $out.'</select>';}
function prodi_options(): array {$programs=all('SELECT id,name FROM programs ORDER BY name');return [''=>'Semua Program Studi']+array_column($programs,'name','id');}
function status_chip(string $status): string { return '<span class="chip '.($status==='final'||$status==='verified'?'chip-good':'chip-warn').'">'.esc($status==='final'?'Selesai':($status==='draft'?'Draft':ucfirst($status))).'</span>'; }
function empty_block(string $txt): void {echo '<div class="empty">▢<h3>Belum ada data</h3><p>'.esc($txt).'</p></div>';}
function nav_pager(int $total,int $perpage,int $page,array $params=[]): void { $pages=(int)ceil($total/$perpage); if($pages<=1)return;echo '<nav class="pager">';for($i=max(1,$page-2);$i<=min($pages,$page+2);$i++)echo '<a class="'.($page===$i?'active':'').'" href="'.url((string)($_GET['page']??'home'),array_merge($params,['p'=>$i])).'">'.$i.'</a>';echo '</nav>';}
