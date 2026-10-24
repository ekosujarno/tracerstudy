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
function landing_icon(string $name): string {
    $paths=[
        'users'=>'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
        'clipboard'=>'<rect x="5" y="4" width="14" height="18" rx="2"/><path d="M9 4.5h6M9 10h6M9 14h6M9 18h4"/>',
        'chart'=>'<path d="M3 3v18h18"/><rect x="6" y="12" width="3" height="6" rx=".5"/><rect x="12" y="8" width="3" height="10" rx=".5"/><rect x="18" y="4" width="3" height="14" rx=".5"/>',
        'briefcase'=>'<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M8 7V5a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2M2 13a20 20 0 0 0 20 0M12 12v3"/>',
        'log-in'=>'<path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h7a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-7"/>',
        'id-card'=>'<rect x="2" y="4" width="20" height="16" rx="2"/><circle cx="8" cy="10" r="2"/><path d="M4.5 16a4 4 0 0 1 7 0M14 9h5M14 13h5"/>',
        'file-text'=>'<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8Z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
        'send'=>'<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'shield'=>'<path d="M12 22c6-3 9-7 9-13V5l-9-3-9 3v4c0 6 3 10 9 13Z"/><path d="m8 12 3 3 5-6"/>',
        'arrow'=>'<path d="M5 12h14m-7-7 7 7-7 7"/>',
        'book'=>'<path d="M12 7v14M3 18V5a2 2 0 0 1 2-2h3a4 4 0 0 1 4 4 4 4 0 0 1 4-4h3a2 2 0 0 1 2 2v13h-5a4 4 0 0 0-4 3 4 4 0 0 0-4-3Z"/>',
    ];
    return '<svg class="ts-svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.($paths[$name]??$paths['clipboard']).'</svg>';
}
function begin_page(string $title, string $page, bool $portal=false): void {
    $name=setting('campus_name',APP_TITLE);$u=auth();
    echo '<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#075445"><meta name="description" content="Sistem Tracer Study Alumni Perguruan Tinggi"><title>'.esc($title).' | '.esc($name).'</title><link rel="stylesheet" href="assets/style.css?v=101"></head><body class="'.($portal?'ts-public':'').'">';
    if(!$portal){echo '<div class="app-shell">';sidebar_menu($page);echo '<div class="app-main"><header class="topbar"><button type="button" class="menu-toggle" aria-label="Buka menu" data-toggle-sidebar>☰</button><div class="top-search"><span class="top-search-icon">⌕</span><span>Tracer Study Management System</span></div><div class="topbar-right"><span class="pill mini">◉ '.esc(current_period()['name']??'Belum ada survei aktif').'</span><div class="avatar">'.esc(strtoupper(substr($u['name']??'A',0,1))).'</div><div class="who"><b>'.esc($u['name']??'Admin').'</b><small>'.esc(ucfirst($u['role']??'')).'</small></div></div></header><div class="workspace">';}
    else {
        $logo=setting('campus_logo');
        $logoSrc=$logo&&preg_match('/^[a-z_]+_[a-f0-9]{24}\.(png|jpg|webp)$/',$logo)?'assets/uploads/'.$logo:'assets/logo-tracer.svg';
        echo '<header class="public-header ts-header"><div class="public-nav wrap ts-navigation"><a class="public-brand ts-brand" href="'.url('home').'"><img src="'.esc($logoSrc).'" alt="Logo kampus" class="ts-brand-mark"><span>Tracer Study Kampus<small>'.esc($name==='Tracer Study Kampus'?'Sistem Penelusuran Jejak Alumni':$name).'</small></span></a><button class="public-toggle" type="button" data-toggle-public aria-label="Buka navigasi" aria-expanded="false">☰</button><nav class="public-links" aria-label="Navigasi utama">';
        foreach([['home','Beranda'],['about','Tentang'],['guide','Panduan'],['employer','Survei Pengguna']] as $entry){
            echo '<a class="'.($entry[0]===$page?'ts-active':'').'" '.($entry[0]===$page?'aria-current="page"':'').' href="'.url($entry[0]).'">'.$entry[1].'</a>';
        }
        $logged=$u?url(admin_role()?'admin_dashboard':'alumni_home'):url('login');
        echo '<a class="btn btn-gold ts-login" href="'.$logged.'">'.landing_icon('users').'<span>'.($u?'Dashboard Saya':'Login Alumni / Admin').'</span><span class="ts-caret">⌄</span></a></nav></div></header>';
    }
}
function end_page(bool $portal=false): void {
    if($portal){
        $name=setting('campus_name',APP_TITLE);
        echo '<footer class="public-footer ts-footer"><div class="wrap ts-footer-inner"><div class="ts-footer-brand"><img src="assets/logo-tracer.svg" alt="" width="42" height="42"><span><b>'.esc($name).'</b><small>Sistem Penelusuran Jejak Alumni</small></span></div><nav class="ts-footer-nav"><a href="'.url('home').'">Beranda</a><a href="'.url('about').'">Tentang</a><a href="'.url('guide').'">Panduan</a><a href="'.url('employer').'">Survei Pengguna</a></nav><div class="ts-footer-copy"><span>© '.date('Y').' '.esc($name).'</span><a href="'.url('privacy').'">'.landing_icon('shield').' Kebijakan Privasi</a></div></div></footer>';
    } else echo '</div></div></div>';
    echo '<script src="assets/app.js?v=101" defer></script></body></html>';
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
