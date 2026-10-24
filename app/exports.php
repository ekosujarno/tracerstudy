<?php
require_once __DIR__.'/import.php';
function process_export(): never {
    admin_access();$type=(string)($_GET['export']??'');
    if($type==='template'){
        write_csv_download('template_impor_alumni.csv',['nim','nama','email','telepon','kode_prodi','tahun_lulus'],[['20200001','Nama Alumni','alumni@example.com','081234567890','PAI','2024']]);
    }
    if($type==='reminders'){
        admin_access('write');$rows=pending_alumni(current_period());$data=array_map(fn($a)=>[$a['nim'],$a['name'],$a['prodi_name'],$a['phone'],$a['email'],$a['response_status']?:'Belum mengisi'],$rows);write_csv_download('pengingat_alumni.csv',['NIM','Nama','Prodi','Telepon','Email','Status'],$data);
    }
    if($type==='details')export_details();
    if(!in_array($type,['csv','xlsx','pdf'],true)){http_response_code(400);exit('Format export tidak valid.');}
    $pid=(int)($_GET['period_id']??(current_period()['id']??0));$p=$pid?one('SELECT * FROM periods WHERE id=?',[$pid]):null;if(!$p)exit('Periode tidak tersedia.');
    $prodi=(int)($_GET['prodi']??0);$m=report_metrics($p,$prodi);$rows=[];
    foreach($m['programs'] as $r)$rows[]=[$r['prodi'],(int)$r['total'],(int)$r['responded'],(int)$r['total']-(int)$r['responded'],percentage((int)$r['responded'],(int)$r['total'])];
    $head=['Program Studi','Target Alumni','Responden Final','Belum Mengisi','Tingkat Respons'];
    $file='laporan_tracer_'.preg_replace('/[^0-9A-Za-z_-]/','_',strtolower($p['name']));
    log_action('export_report',$type,'Periode '.$pid);
    if($type==='csv')write_csv_download($file.'.csv',$head,$rows);
    if($type==='xlsx'){download_xlsx($file.'.xlsx',$head,$rows);exit;}
    download_pdf($file.'.pdf',setting('campus_name',APP_TITLE),'Laporan Tracer Study - '.$p['name'],$head,$rows,$m);exit;
}
function xmlvalue(string $v): string {return htmlspecialchars($v,ENT_XML1|ENT_QUOTES,'UTF-8');}
function spreadsheet_col(int $n): string {$s='';for($n++;$n>0;$n=intdiv($n-1,26))$s=chr(65+($n-1)%26).$s;return $s;}
function download_xlsx(string $filename,array $header,array $rows): void {
    if(!class_exists('ZipArchive')){write_csv_download(str_replace('.xlsx','.csv',$filename),$header,$rows);}
    $tmp=tempnam(sys_get_temp_dir(),'tracerxlsx_');$zip=new ZipArchive();if($zip->open($tmp,ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Gagal membuat file Excel.');
    $zip->addFromString('[Content_Types].xml','<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
    $zip->addFromString('_rels/.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml','<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Ringkasan" sheetId="1" r:id="rId1"/></sheets></workbook>');
    $zip->addFromString('xl/_rels/workbook.xml.rels','<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
    $xml='<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
    foreach(array_merge([$header],$rows) as $rowNo=>$values){$n=$rowNo+1;$xml.='<row r="'.$n.'">';foreach(array_values($values) as $i=>$v){$cell=spreadsheet_col($i).$n;
        if($rowNo>0&&is_int($v))$xml.='<c r="'.$cell.'"><v>'.$v.'</v></c>';
        else $xml.='<c r="'.$cell.'" t="inlineStr"><is><t>'.xmlvalue((string)$v).'</t></is></c>';}
        $xml.='</row>';}
    $xml.='</sheetData></worksheet>';$zip->addFromString('xl/worksheets/sheet1.xml',$xml);$zip->close();
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.filesize($tmp));readfile($tmp);unlink($tmp);
}
function pdf_escape(string $s): string {return str_replace(['\\','(',')',"\r","\n"],['\\\\','\\(','\\)',' ',' '],$s);}
function pdf_latin(string $s): string {return function_exists('iconv')?(iconv('UTF-8','Windows-1252//TRANSLIT',$s)?:preg_replace('/[^\x20-\x7E]/',' ',$s)):$s;}
function download_pdf(string $filename,string $campus,string $title,array $header,array $rows,array $m): void {
    // Dependency-free A4 landscape PDF, text-only to maximize compatibility.
    $pages=[];$lines=[];$max=28;
    $lines[]=$campus;$lines[]=$title;$lines[]='Dicetak: '.date('d-m-Y H:i');
    $lines[]='Total target: '.$m['total'].' | Responden final: '.$m['final'].' | Tingkat respons: '.percentage($m['final'],$m['total']);$lines[]='';
    $lines[]=implode(' | ',$header);
    foreach($rows as $row){$lines[]=implode(' | ',array_map(fn($v)=>mbcut((string)$v,28),$row));}
    $pages=array_chunk($lines,$max);if(!$pages)$pages=[[]];
    $objs=[];$add=function(string $body)use(&$objs): int {$objs[]=$body;return count($objs);};
    $catalog=$add('');$pagesRef=$add('');$font=$add('<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>');$references=[];
    foreach($pages as $pnum=>$lineList){$content="BT /F1 12 Tf 40 555 Td ";$first=true;foreach($lineList as $ln){if(!$first)$content.="0 -18 Td ";$first=false;$content.='('.pdf_escape(pdf_latin($ln)).') Tj ';}$content.='ET';$stream=$add('<< /Length '.strlen($content).' >>' ."\nstream\n".$content."\nendstream");$page=$add('<< /Type /Page /Parent '.$pagesRef.' 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 '.$font.' 0 R >> >> /Contents '.$stream.' 0 R >>');$references[]=$page.' 0 R';}
    $objs[$pagesRef-1]='<< /Type /Pages /Kids ['.implode(' ',$references).'] /Count '.count($references).' >>';$objs[$catalog-1]='<< /Type /Catalog /Pages '.$pagesRef.' 0 R >>';
    $pdf="%PDF-1.4\n";$offsets=[0];foreach($objs as $i=>$object){$offsets[]=strlen($pdf);$pdf.=($i+1)." 0 obj\n".$object."\nendobj\n";}
    $start=strlen($pdf);$pdf.="xref\n0 ".(count($objs)+1)."\n0000000000 65535 f \n";foreach(array_slice($offsets,1) as $offset)$pdf.=sprintf('%010d 00000 n ',$offset)."\n";
    $pdf.="trailer\n<< /Size ".(count($objs)+1).' /Root '.$catalog." 0 R >>\nstartxref\n".$start."\n%%EOF";
    header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="'.$filename.'"');header('Content-Length: '.strlen($pdf));echo $pdf;
}

function export_details(): never {
    admin_access('write');$pid=(int)($_GET['period_id']??(current_period()['id']??0));
    $period=one('SELECT * FROM periods WHERE id=?',[$pid]);if(!$period)throw new RuntimeException('Periode tidak tersedia.');
    $prodi=own_prodi()??(int)($_GET['prodi']??0);
    $conds=['r.period_id=?',"r.status='final'"];$params=[$pid];
    if($prodi){$conds[]='a.prodi_id=?';$params[]=$prodi;}
    $rows=all('SELECT a.nim,a.name,p.name AS prodi,a.graduation_year,r.employment_status,r.company_name,r.business_name,r.position,r.first_job_months,r.monthly_income,r.relevance,r.study_university,r.submitted_at FROM responses r JOIN alumni a ON a.id=r.alumni_id JOIN programs p ON p.id=a.prodi_id WHERE '.implode(' AND ',$conds).' ORDER BY p.name,a.name LIMIT 20000',$params);
    $head=['NIM','Nama','Program Studi','Tahun Lulus','Status Lulusan','Tempat Kerja','Nama Usaha','Posisi','Masa Tunggu (bulan)','Penghasilan Bulanan','Kesesuaian','Studi Lanjutan','Tanggal Kirim'];
    $values=[];foreach($rows as $r)$values[]=array_values($r);
    log_action('export_details','responses','Periode '.$pid);
    write_csv_download('data_responden_'.$pid.'.csv',$head,$values);
}
