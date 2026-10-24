<?php
/** Read user-supplied CSV or small XLSX without dependencies; no macros or formulas evaluated. */
function spreadsheet_rows(string $path,string $filename): array {
    $ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    if(!in_array($ext,['csv','xlsx'],true))throw new RuntimeException('Gunakan berkas CSV UTF-8 atau XLSX.');
    if(filesize($path)>5*1024*1024)throw new RuntimeException('Maksimum berkas 5 MB.');
    if($ext==='csv'){
        $stream=fopen($path,'rb');if(!$stream)throw new RuntimeException('Tidak dapat membaca CSV.');
        $first=fgets($stream);if($first===false){fclose($stream);return [];}
        $first=preg_replace('/^\xEF\xBB\xBF/','',$first);
        $delimiter=substr_count($first,';')>substr_count($first,',')?';':',';
        rewind($stream);$rows=[];
        while(($row=fgetcsv($stream,8192,$delimiter,'"',''))!==false){if(!$rows && isset($row[0]))$row[0]=preg_replace('/^\xEF\xBB\xBF/','',$row[0]);$rows[]=$row;if(count($rows)>10001)break;}
        fclose($stream);return $rows;
    }
    if(!class_exists('ZipArchive'))throw new RuntimeException('Ekstensi PHP zip harus aktif untuk membaca XLSX.');
    $zip=new ZipArchive();if($zip->open($path)!==true)throw new RuntimeException('File XLSX rusak.');
    try{
        $total=0;for($i=0;$i<$zip->numFiles;$i++){$s=$zip->statIndex($i);$total+=(int)($s['size']??0);if($total>25*1024*1024)throw new RuntimeException('XLSX terlalu besar sesudah diekstrak.');}
        $shared=[];$str=$zip->getFromName('xl/sharedStrings.xml');
        if($str!==false){$xml=simplexml_load_string($str,'SimpleXMLElement',LIBXML_NONET);if($xml===false)throw new RuntimeException('XML shared strings invalid.');foreach($xml->si as $si){$v='';foreach($si->xpath('.//*[local-name()="t"]')?:[] as $t)$v.=(string)$t;$shared[]=$v;}}
        $sheet=$zip->getFromName('xl/worksheets/sheet1.xml');if($sheet===false)throw new RuntimeException('Sheet pertama tidak ditemukan.');
        $xml=simplexml_load_string($sheet,'SimpleXMLElement',LIBXML_NONET);if($xml===false)throw new RuntimeException('XML worksheet tidak valid.');
        $result=[];
        foreach($xml->sheetData->row as $row){$cells=[];foreach($row->c as $c){$ref=(string)$c['r'];preg_match('/^[A-Z]+/',$ref,$m);$index=0;foreach(str_split($m[0]??'A') as $letter)$index=$index*26+(ord($letter)-64);$index--;if($index<0||$index>100)continue;
            $type=(string)$c['t'];$value=(string)$c->v;
            if($type==='s')$value=$shared[(int)$value]??'';
            elseif($type==='inlineStr'){$value='';foreach($c->xpath('.//*[local-name()="t"]')?:[] as $t)$value.=(string)$t;}
            $cells[$index]=$value;
        }if($cells){$max=max(array_keys($cells));$result[]=array_map(fn($i)=>$cells[$i]??'',range(0,$max));}if(count($result)>10001)break;}
        return $result;
    } finally {$zip->close();}
}
function write_csv_download(string $filename,array $header,array $rows): never {
    header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_',$filename).'"');
    header('Cache-Control: no-store');$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$header,',','"','');
    foreach($rows as $row){$safe=array_map(function($v){$x=(string)($v??'');return preg_match('/^[=+@\-\t\r]/',$x)?"'".$x:$x;},$row);fputcsv($out,$safe,',','"','');}fclose($out);exit;
}
function import_alumni(): never {
    $u=admin_access('write');
    if(!isset($_FILES['file'])||($_FILES['file']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Unggah berkas terlebih dahulu.');
    $rows=spreadsheet_rows($_FILES['file']['tmp_name'],basename($_FILES['file']['name']));
    if(count($rows)<2)throw new RuntimeException('File tidak memiliki data alumni.');
    $heads=array_map(fn($v)=>strtolower(trim((string)$v)),array_shift($rows));
    $required=['nim','nama','email','telepon','kode_prodi','tahun_lulus'];
    foreach($required as $column)if(!in_array($column,$heads,true))throw new RuntimeException('Kolom wajib tidak tersedia: '.$column);
    $ps=all('SELECT id,code FROM programs');$map=[];foreach($ps as $p)$map[strtolower($p['code'])]=(int)$p['id'];
    $new=[];$created=0;$updated=0;$skipped=[];
    db()->beginTransaction();
    try{
        foreach($rows as $i=>$arr){if(!array_filter($arr,fn($v)=>trim((string)$v)!==''))continue;$r=[];foreach($heads as $index=>$head)$r[$head]=trim((string)($arr[$index]??''));
            $nim=mbcut($r['nim']??'',40);$name=mbcut($r['nama']??'',160);$email=strtolower(mbcut($r['email']??'',190));$code=strtolower($r['kode_prodi']??'');$year=(int)($r['tahun_lulus']??0);
            if(!$nim||!$name||!isset($map[$code])||$year<1980||$year>(int)date('Y')+1){$skipped[]='Baris '.($i+2);continue;}
            $pid=$map[$code];if(!permission_prodi($pid)){$skipped[]='Baris '.($i+2);continue;}
            $existing=one('SELECT id,user_id FROM alumni WHERE nim=?',[$nim]);
            if($existing){if(!permission_prodi((int)val('SELECT prodi_id FROM alumni WHERE id=?',[$existing['id']]))){$skipped[]='Baris '.($i+2);continue;}
                query('UPDATE alumni SET name=?,email=?,phone=?,prodi_id=?,graduation_year=? WHERE id=?',[$name,$email,mbcut($r['telepon']??'',30),$pid,$year,$existing['id']]);$updated++;
            }else{$access=invite_code();query('INSERT INTO alumni(nim,name,email,phone,prodi_id,graduation_year,invite_hash) VALUES(?,?,?,?,?,?,?)',[$nim,$name,$email,mbcut($r['telepon']??'',30),$pid,$year,encrypt_code($access)]);$new[]=[$nim,$name,$email,$access];$created++;}
        }db()->commit();
    }catch(Throwable $e){db()->rollBack();throw $e;}
    log_action('import_alumni','alumni','Baru: '.$created.'; update: '.$updated.'; dilewati: '.count($skipped));
    if($created>0)write_csv_download('kode_aktivasi_alumni_'.date('Ymd_His').'.csv',['nim','nama','email','kode_akses'], $new);
    flash("Impor selesai: $created baru, $updated diperbarui, ".count($skipped)." dilewati.");redirect('admin_import');
}
