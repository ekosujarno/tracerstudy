<?php
require_once __DIR__.'/import.php';
function post_redirect(string $page,string $message): never { flash($message);redirect($page); }
function process_post(): void {
    $action=capture('action',64);
    try {process_action($action);} catch(Throwable $e){error_log('TracerStudy action '.$action.': '.$e->getMessage());flash('Gagal: '.$e->getMessage(),'error');redirect(in_array((string)($_GET['page']??''),['login','activate','employer'],true)?(string)$_GET['page']:(string)($_GET['page']??'admin_dashboard'));}
}
function process_action(string $action): void {
    if($action==='login'){
        if(($_SESSION['login_fails']??0)>=8 && time()-(int)($_SESSION['login_fail_time']??0)<900)throw new RuntimeException('Terlalu banyak percobaan login. Coba lagi dalam 15 menit.');
        $email=strtolower(capture('email',190));$u=one('SELECT * FROM users WHERE email=? AND is_active=1',[$email]);
        if(!$u || !password_verify((string)($_POST['password']??''),$u['password_hash'])){$_SESSION['login_fails']=($_SESSION['login_fails']??0)+1;$_SESSION['login_fail_time']=time();throw new RuntimeException('Email atau password salah.');}
        $_SESSION['login_fails']=0;session_regenerate_id(true);$_SESSION['uid']=(int)$u['id'];log_action('login');redirect($u['role']==='alumni'?'alumni_home':'admin_dashboard');
    }
    if($action==='activate'){
        $nim=capture('nim',40);$code=strtoupper(capture('invite_code',30));$email=strtolower(capture('email',190));$pass=(string)($_POST['password']??'');
        if(strlen($pass)<10||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Email tidak valid atau password kurang dari 10 karakter.');
        if(($_SESSION['activate_fails']??0)>10)throw new RuntimeException('Percobaan aktivasi dibatasi untuk sesi ini.');
        $a=one('SELECT * FROM alumni WHERE nim=?',[$nim]);
        if(!$a || $a['user_id'] || !$a['invite_hash'] || !hash_equals($a['invite_hash'],encrypt_code($code))){$_SESSION['activate_fails']=($_SESSION['activate_fails']??0)+1;throw new RuntimeException('NIM atau kode akses tidak cocok / akun sudah aktif.');}
        if($a['email'] && strcasecmp($email,$a['email'])!==0)throw new RuntimeException('Email tidak sesuai data alumni. Hubungi admin.');
        db()->beginTransaction();try{
            query('INSERT INTO users(name,email,password_hash,role,prodi_id,phone) VALUES(?,?,?,\'alumni\',?,?)',[$a['name'],$email,password_hash($pass,PASSWORD_DEFAULT),$a['prodi_id'],$a['phone']]);$uid=(int)db()->lastInsertId();
            query('UPDATE alumni SET user_id=?,invite_hash=NULL,email=? WHERE id=? AND user_id IS NULL',[$uid,$email,$a['id']]);db()->commit();
        }catch(Throwable $e){db()->rollBack();throw $e;}
        $_SESSION['uid']=$uid;session_regenerate_id(true);log_action('activate_alumni','alumni',(string)$a['id']);post_redirect('alumni_home','Akun berhasil diaktivasi. Selamat datang!');
    }
    if($action==='save_profile'){
        $u=require_role('alumni');$a=one('SELECT * FROM alumni WHERE user_id=?',[$u['id']]);if(!$a)throw new RuntimeException('Data alumni tidak ditemukan.');
        $phone=capture('phone',30);$city=capture('city',120);$gender=capture('gender',12);
        if($gender && !in_array($gender,['L','P'],true))throw new RuntimeException('Jenis kelamin tidak valid.');
        query('UPDATE alumni SET phone=?,city=?,gender=? WHERE id=?',[$phone,$city,$gender,$a['id']]);query('UPDATE users SET phone=? WHERE id=?',[$phone,$u['id']]);post_redirect('alumni_profile','Profil diperbarui.');
    }
    if($action==='save_survey' || $action==='submit_survey'){save_survey_action($action);return;}
    if($action==='employer_submit'){
        if(capture('website')!=='')post_redirect('employer','Terima kasih atas masukan Anda.');
        if(time()-(int)($_SESSION['employer_last']??0)<90)throw new RuntimeException('Tunggu sebentar sebelum mengirim survei berikutnya.');
        $company=capture('company_name',160);$person=capture('respondent_name',160);
        if(strlen($company)<3||strlen($person)<3)throw new RuntimeException('Nama perusahaan dan responden wajib diisi.');
        $scores=[];foreach(['ethics','expertise','language','technology','communication','teamwork','development'] as $field){$n=int_post($field);if($n<1||$n>5)throw new RuntimeException('Seluruh penilaian harus diisi dengan skor 1–5.');$scores[]=$n;}
        $pid=int_post('prodi_id')?:null;if($pid&&!one('SELECT id FROM programs WHERE id=?',[$pid]))$pid=null;
        query('INSERT INTO employer_responses(company_name,respondent_name,respondent_position,email,phone,graduate_name,prodi_id,ethics,expertise,language,technology,communication,teamwork,development,comment) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',array_merge([$company,$person,capture('respondent_position',120),capture('email',190),capture('phone',30),capture('graduate_name',160),$pid],$scores,[capture('comment',2000)]));
        $_SESSION['employer_last']=time();post_redirect('employer','Terima kasih. Survei kepuasan pengguna lulusan telah diterima dan menunggu verifikasi.');
    }
    if($action==='add_program'){
        require_role('admin');$code=strtoupper(capture('code',30));$name=capture('name',160);
        if(!$code||!$name)throw new RuntimeException('Kode dan nama prodi wajib diisi.');
        query('INSERT INTO programs(code,name,faculty) VALUES(?,?,?)',[$code,$name,capture('faculty',160)]);log_action('add_program',$code);post_redirect('admin_programs','Program studi ditambahkan.');
    }
    if($action==='add_period'){
        require_role('admin');$start=capture('start_date');$end=capture('end_date');$y1=int_post('graduation_year_min');$y2=int_post('graduation_year_max');
        if(!valid_date($start)||!valid_date($end)||$start>$end||$y1<1980||$y2<$y1)throw new RuntimeException('Rentang periode dan tahun lulusan tidak valid.');
        query('INSERT INTO periods(name,start_date,end_date,graduation_year_min,graduation_year_max) VALUES(?,?,?,?,?)',[capture('name',140),$start,$end,$y1,$y2]);log_action('add_period');post_redirect('admin_periods','Periode survei dibuat. Aktifkan untuk menerima respons.');
    }
    if($action==='activate_period'){
        require_role('admin');$id=int_post('id');if(!one('SELECT id FROM periods WHERE id=?',[$id]))throw new RuntimeException('Periode tidak ditemukan.');
        db()->beginTransaction();try{query('UPDATE periods SET is_active=0');query('UPDATE periods SET is_active=1 WHERE id=?',[$id]);db()->commit();}catch(Throwable $e){db()->rollBack();throw $e;}
        log_action('activate_period',(string)$id);post_redirect('admin_periods','Periode berhasil diaktifkan.');
    }
    if($action==='add_alumni'){
        admin_access('write');$nim=capture('nim',40);$name=capture('name',160);$year=int_post('graduation_year');$pid=int_post('prodi_id');$email=strtolower(capture('email',190));
        if(!$nim||!$name||$year<1980||$year>(int)date('Y')+1||!one('SELECT id FROM programs WHERE id=?',[$pid])||!permission_prodi($pid))throw new RuntimeException('Data alumni tidak valid.');
        if($email && !filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Format email salah.');
        $code=invite_code();query('INSERT INTO alumni(nim,name,email,phone,prodi_id,graduation_year,invite_hash) VALUES(?,?,?,?,?,?,?)',[$nim,$name,$email,capture('phone',30),$pid,$year,encrypt_code($code)]);
        $_SESSION['once_code']=['nim'=>$nim,'code'=>$code];log_action('add_alumni',$nim);post_redirect('admin_alumni','Alumni ditambahkan. Salin kode aktivasi yang tampil di halaman.');
    }
    if($action==='edit_alumni'){
        admin_access('write');$id=int_post('id');$a=one('SELECT * FROM alumni WHERE id=?',[$id]);
        $pid=int_post('prodi_id');$year=int_post('graduation_year');$email=strtolower(capture('email',190));
        if(!$a||!permission_prodi((int)$a['prodi_id'])||!permission_prodi($pid)||!one('SELECT id FROM programs WHERE id=?',[$pid]))throw new RuntimeException('Alumni atau prodi tidak valid.');
        if(!capture('name',160)||$year<1980||$year>(int)date('Y')+1||($email&&!filter_var($email,FILTER_VALIDATE_EMAIL)))throw new RuntimeException('Data belum lengkap atau salah format.');
        query('UPDATE alumni SET name=?,email=?,phone=?,prodi_id=?,graduation_year=? WHERE id=?',[capture('name',160),$email,capture('phone',30),$pid,$year,$id]);
        if($a['user_id'])query('UPDATE users SET name=?,phone=?,prodi_id=? WHERE id=?',[capture('name',160),capture('phone',30),$pid,$a['user_id']]);
        log_action('edit_alumni',(string)$a['nim']);post_redirect('admin_alumni','Biodata alumni berhasil diperbarui.');
    }
    if($action==='reset_alumni_password'){
        admin_access('write');$id=int_post('id');$a=one('SELECT * FROM alumni WHERE id=?',[$id]);
        if(!$a||!permission_prodi((int)$a['prodi_id'])||!$a['user_id'])throw new RuntimeException('Alumni belum memiliki akun atau di luar hak akses Anda.');
        $new=bin2hex(random_bytes(10));query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$a['user_id']]);
        $_SESSION['once_password']=['name'=>$a['name'],'password'=>$new];log_action('reset_alumni_password',$a['nim']);post_redirect('admin_alumni','Password sementara telah dibuat. Sampaikan kepada alumni secara privat.');
    }
    if($action==='regen_invite'){
        admin_access('write');$id=int_post('id');$a=one('SELECT * FROM alumni WHERE id=?',[$id]);if(!$a||!permission_prodi((int)$a['prodi_id'])||$a['user_id'])throw new RuntimeException('Kode hanya dapat diperbarui untuk alumni belum aktif.');
        $code=invite_code();query('UPDATE alumni SET invite_hash=? WHERE id=?',[encrypt_code($code),$id]);$_SESSION['once_code']=['nim'=>$a['nim'],'code'=>$code];log_action('regen_invite',$a['nim']);post_redirect('admin_alumni','Kode aktivasi baru dibuat. Kode sebelumnya tidak berlaku.');
    }
    if($action==='import_alumni')import_alumni();
    if($action==='add_question'){
        require_role('admin');$label=capture('label',255);$type=capture('type',15);$target=capture('only_status',30);$pid=int_post('period_id')?:null;
        if(!$label||!in_array($type,['text','textarea','select','number'],true)||($target&&!array_key_exists($target,statuses())))throw new RuntimeException('Pertanyaan tidak valid.');
        if($pid&&!one('SELECT id FROM periods WHERE id=?',[$pid]))throw new RuntimeException('Periode tidak ditemukan.');
        $options=array_values(array_filter(array_map('trim',explode("\n",str_replace("\r",'',capture('options',1800))))));
        if($type==='select' && count($options)<2)throw new RuntimeException('Pertanyaan pilihan harus memiliki minimal 2 opsi, satu per baris.');
        query('INSERT INTO questions(period_id,label,type,options_json,only_status,is_required,is_active,sort_order) VALUES(?,?,?,?,?,?,1,?)',[$pid,$label,$type,json_encode($options,JSON_UNESCAPED_UNICODE),$target,int_post('is_required')?1:0,int_post('sort_order')]);log_action('add_question');post_redirect('admin_questions','Pertanyaan berhasil ditambahkan.');
    }
    if($action==='toggle_question'){
        require_role('admin');$id=int_post('id');query('UPDATE questions SET is_active=1-is_active WHERE id=?',[$id]);log_action('toggle_question',(string)$id);post_redirect('admin_questions','Status pertanyaan diperbarui.');
    }
    if($action==='verify_employer'){
        admin_access('write');$id=int_post('id');$e=one('SELECT prodi_id FROM employer_responses WHERE id=?',[$id]);if(!$e||!permission_prodi($e['prodi_id']===null?null:(int)$e['prodi_id']))throw new RuntimeException('Tidak berhak memverifikasi survei ini.');query('UPDATE employer_responses SET verified=1 WHERE id=?',[$id]);log_action('verify_employer',(string)$id);post_redirect('admin_employers','Penilaian pengguna lulusan diverifikasi.');
    }
    if($action==='reset_staff_password'){
        $admin=require_role('admin');$id=int_post('id');$staff=one('SELECT * FROM users WHERE id=? AND role<>\'alumni\' ',[$id]);
        if(!$staff||$id===(int)$admin['id'])throw new RuntimeException('Akun tidak valid untuk reset oleh admin.');
        $new=bin2hex(random_bytes(10));query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($new,PASSWORD_DEFAULT),$id]);
        $_SESSION['once_password']=['name'=>$staff['name'],'password'=>$new];log_action('reset_staff_password',(string)$id);post_redirect('admin_users','Password sementara telah dibuat dan hanya ditampilkan sekali.');
    }
    if($action==='add_user'){
        require_role('admin');$email=strtolower(capture('email',190));$pass=(string)($_POST['password']??'');$r=capture('role',20);$pid=int_post('prodi_id')?:null;
        if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($pass)<12||!in_array($r,['admin','operator','leader'],true))throw new RuntimeException('Pengguna, peran, atau password tidak valid (min. 12 karakter).');
        if($r==='operator'&&(!$pid||!one('SELECT id FROM programs WHERE id=?',[$pid])))throw new RuntimeException('Operator wajib memiliki program studi.');
        query('INSERT INTO users(name,email,password_hash,role,prodi_id) VALUES(?,?,?,?,?)',[capture('name',160),$email,password_hash($pass,PASSWORD_DEFAULT),$r,$r==='operator'?$pid:null]);log_action('add_user',$email);post_redirect('admin_users','Pengguna baru berhasil ditambahkan.');
    }
    if($action==='toggle_user'){
        $u=require_role('admin');$id=int_post('id');if($id===$u['id'])throw new RuntimeException('Tidak dapat menonaktifkan akun sendiri.');
        query("UPDATE users SET is_active=1-is_active WHERE id=? AND role<>'alumni'",[$id]);log_action('toggle_user',(string)$id);post_redirect('admin_users','Status akun diperbarui.');
    }
    if($action==='settings'){
        require_role('admin');foreach(['campus_name'=>160,'campus_tagline'=>220,'contact_email'=>190,'footer_text'=>220] as $key=>$limit)set_setting($key,capture($key,$limit));
        foreach(['campus_logo','hero_image'] as $asset)if(isset($_FILES[$asset]) && ($_FILES[$asset]['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE)save_brand_image($asset);log_action('settings_update');post_redirect('admin_settings','Pengaturan berhasil disimpan.');
    }
    if($action==='change_password'){
        $u=user();if(!password_verify((string)($_POST['old_password']??''),$u['password_hash']))throw new RuntimeException('Password lama tidak cocok.');
        $p=(string)($_POST['new_password']??'');if(strlen($p)<12)throw new RuntimeException('Password baru minimal 12 karakter.');query('UPDATE users SET password_hash=? WHERE id=?',[password_hash($p,PASSWORD_DEFAULT),$u['id']]);log_action('change_password');post_redirect($u['role']==='alumni'?'alumni_profile':'admin_settings','Password telah diubah.');
    }
    throw new RuntimeException('Tindakan tidak dikenal.');
}
function save_survey_action(string $action): void {
    $u=require_role('alumni');$a=one('SELECT * FROM alumni WHERE user_id=?',[$u['id']]);$p=current_period();
    if(!$a||!$p||(int)$a['graduation_year']<(int)$p['graduation_year_min']||(int)$a['graduation_year']>(int)$p['graduation_year_max'])throw new RuntimeException('Anda belum termasuk target survei aktif.');
    $prev=respond_for((int)$a['id'],(int)$p['id']);if($prev && $prev['status']==='final')throw new RuntimeException('Kuesioner telah dikirim dan dikunci.');
    if(!period_open($p))throw new RuntimeException('Periode survei saat ini belum dibuka atau telah ditutup.');
    $id=response_id((int)$a['id'],(int)$p['id']);$step=int_post('step');
    if($action==='submit_survey'){
        $r=one('SELECT * FROM responses WHERE id=?',[$id]);$status=$r['employment_status'];
        if(!array_key_exists($status,statuses()))throw new RuntimeException('Status lulusan harus diisi.');
        if((int)$r['progress']<5)throw new RuntimeException('Selesaikan tahap kompetensi sebelum mengirim.');
        if($status==='bekerja'&&!trim((string)$r['company_name']))throw new RuntimeException('Nama perusahaan wajib diisi.');
        if($status==='wirausaha'&&!trim((string)$r['business_name']))throw new RuntimeException('Nama usaha wajib diisi.');
        if($status==='studi'&&!trim((string)$r['study_university']))throw new RuntimeException('Perguruan tinggi lanjutan wajib diisi.');
        if(in_array($status,['bekerja','wirausaha'],true) && ($r['first_job_months']===null || !$r['relevance']))throw new RuntimeException('Lengkapi masa tunggu kerja dan kesesuaian bidang kerja.');
        foreach(['competency_ethics','competency_expertise','competency_language','competency_it','competency_communication','competency_teamwork','competency_development'] as $field)if((int)$r[$field]<1)throw new RuntimeException('Lengkapi seluruh penilaian kompetensi.');
        $questions=all('SELECT * FROM questions WHERE is_active=1 AND (period_id IS NULL OR period_id=?) AND (only_status=\'\' OR only_status=?) AND is_required=1',[$p['id'],$status]);
        foreach($questions as $q)if(!trim((string)val('SELECT answer FROM answers WHERE response_id=? AND question_id=?',[$id,$q['id']])))throw new RuntimeException('Pertanyaan wajib belum diisi: '.$q['label']);
        query('UPDATE responses SET status=\'final\',progress=5,submitted_at=NOW() WHERE id=?',[$id]);log_action('survey_submitted',(string)$id);post_redirect('alumni_home','Terima kasih! Kuesioner Tracer Study berhasil dikirim.');
    }
    if($step===1){$gender=capture('gender',12);if($gender&&!in_array($gender,['L','P'],true))throw new RuntimeException('Pilihan jenis kelamin tidak valid.');query('UPDATE alumni SET phone=?,city=?,gender=? WHERE id=?',[capture('phone',30),capture('city',120),$gender,$a['id']]);}
    elseif($step===2){$status=capture('employment_status',30);if(!array_key_exists($status,statuses()))throw new RuntimeException('Pilih status lulusan.');query('UPDATE responses SET employment_status=?,progress=GREATEST(progress,3) WHERE id=?',[$status,$id]);}
    elseif($step===3){
        $r=one('SELECT employment_status FROM responses WHERE id=?',[$id]);$st=$r['employment_status'];if(!$st)throw new RuntimeException('Isi status terlebih dahulu.');
        $fields=['employment_type','company_name','company_sector','position','company_city','monthly_income','first_job_months','job_search_before','job_search_after','relevance','study_university','study_program','business_name','business_sector'];
        $nums=['monthly_income','first_job_months','job_search_before','job_search_after'];$data=[];
        foreach($fields as $k){$v=capture($k,160);if(in_array($k,$nums,true)){$v=$v===''?null:(float)$v;if($v!==null&&($v<0||$v>1000000000000))throw new RuntimeException('Angka tidak valid: '.$k);if($v!==null&&$k!=='monthly_income'&&$v>600)throw new RuntimeException('Rentang bulan maksimal 600.');} $data[$k]=$v;}
        if($data['relevance']&&!in_array($data['relevance'],['sangat_sesuai','sesuai','kurang','tidak'],true))throw new RuntimeException('Kesesuaian tidak valid.');
        $sets=implode(',',array_map(fn($k)=>"$k=?",array_keys($data)));query("UPDATE responses SET $sets,progress=GREATEST(progress,4) WHERE id=?",array_merge(array_values($data),[$id]));
    }
    elseif($step===4){
        $skills=['ethics','expertise','language','it','communication','teamwork','development'];$data=[];
        foreach(['competency','college'] as $prefix)foreach($skills as $skill){$field=$prefix.'_'.$skill;$v=int_post($field);if($v<1||$v>5)throw new RuntimeException('Semua nilai kompetensi dan kontribusi pendidikan wajib 1–5.');$data[$field]=$v;}
        $data['suggestions']=capture('suggestions',4000);$sets=implode(',',array_map(fn($k)=>"$k=?",array_keys($data)));query("UPDATE responses SET $sets,progress=5 WHERE id=?",array_merge(array_values($data),[$id]));
        $status=val('SELECT employment_status FROM responses WHERE id=?',[$id]);
        $questions=all('SELECT * FROM questions WHERE is_active=1 AND (period_id IS NULL OR period_id=?) AND (only_status=\'\' OR only_status=?)',[$p['id'],$status]);
        foreach($questions as $q){$answer=capture('q_'.$q['id'],2000);if($q['is_required']&&!$answer)throw new RuntimeException('Pertanyaan wajib: '.$q['label']);if($q['type']==='select'&&$answer&&!in_array($answer,json_decode((string)$q['options_json'],true)?:[],true))throw new RuntimeException('Pilihan jawaban tidak valid.');
            query('INSERT INTO answers(response_id,question_id,answer) VALUES(?,?,?) ON DUPLICATE KEY UPDATE answer=VALUES(answer)',[$id,$q['id'],$answer]);}
    }
    else throw new RuntimeException('Tahap formulir tidak valid.');
    log_action('survey_saved',(string)$id,'Step '.(string)$step);redirect('survey',['step'=>min(5,$step+1)]);
}

function save_brand_image(string $key): void {
    require_role('admin');$f=$_FILES[$key]??null;
    if(!$f||$f['error']!==UPLOAD_ERR_OK||!is_uploaded_file($f['tmp_name']))throw new RuntimeException('Unggah gambar gagal.');
    if($f['size']>3*1024*1024)throw new RuntimeException('Ukuran gambar maksimal 3 MB.');
    if(!function_exists('finfo_open')||!function_exists('getimagesize'))throw new RuntimeException('Ekstensi PHP fileinfo dan getimagesize diperlukan.');
    $mime=(new finfo(FILEINFO_MIME_TYPE))->file($f['tmp_name']);
    $extensions=['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
    if(!isset($extensions[$mime]))throw new RuntimeException('Format gambar harus PNG, JPG, atau WebP.');
    $info=@getimagesize($f['tmp_name']);if(!$info||$info[0]<50||$info[1]<50||$info[0]>5000||$info[1]>5000)throw new RuntimeException('Dimensi gambar tidak valid (maks. 5000px).');
    $dir=dirname(__DIR__).'/assets/uploads';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('Folder unggah tidak dapat dibuat.');
    $filename=$key.'_'.bin2hex(random_bytes(12)).'.'.$extensions[$mime];
    if(!move_uploaded_file($f['tmp_name'],$dir.'/'.$filename))throw new RuntimeException('File tidak dapat disimpan.');
    @chmod($dir.'/'.$filename,0644);set_setting($key,$filename);log_action('upload_brand_image',$key);
}
