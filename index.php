<?php
declare(strict_types=1);
require __DIR__.'/app/core.php';
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_name('tracer_study_session');
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off','httponly'=>true,'samesite'=>'Lax']);
    session_start();
}
http_security();
$page=(string)($_GET['page']??'home');
$allowed=['home','about','guide','privacy','login','activate','logout','employer','alumni_home','alumni_profile','survey','admin_dashboard','admin_alumni','admin_import','admin_reminders','admin_programs','admin_periods','admin_questions','admin_responses','admin_employers','admin_reports','admin_users','admin_settings','admin_logs','admin_updates','report_print'];
if(!in_array($page,$allowed,true)){http_response_code(404);$page='404';}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    check_csrf();
    require __DIR__.'/app/actions.php';
    process_post();
}
if($page==='logout'){
    if(auth())log_action('logout');
    $_SESSION=[];session_regenerate_id(true);redirect('home');
}
if(isset($_GET['export'])) {require __DIR__.'/app/exports.php';process_export();exit;}
require __DIR__.'/app/layout.php';
require __DIR__.'/app/pages.php';
render_page($page);
