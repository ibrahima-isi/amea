<?php
// Real CLI worker competition, solely inside the disposable jobs image/storage.
if (PHP_SAPI !== 'cli' || getenv('APP_ENV') !== 'test' || getenv('DB_NAME') !== 'amea_rehearsal') exit(1);
$queue='/opt/amea/storage/mail-queue';
if (glob($queue.'/*.json')) throw new RuntimeException('Expected an empty synthetic queue');
foreach ([1,2,3] as $id) file_put_contents($queue.'/synthetic-'.$id.'.json',json_encode(['to'=>'synthetic'.$id.'@example.invalid','subject'=>'Synthetic','body'=>'Safe substitute']));
$counter=$queue.'/.sent-'.date('Ymd');
$before=is_file($counter)?(int)file_get_contents($counter):0;
putenv('MAIL_SEND_PAUSE_MS=700');
$start=function($name) {
    $out='/tmp/amea-job-'.$name.'.log';
    $process=proc_open([PHP_BINARY,'scripts/run-shared-jobs.php','mail'],[0=>['file','/dev/null','r'],1=>['file',$out,'w'],2=>['file',$out,'a']],$pipes,'/opt/amea');
    return [$process,$out];
};
[$first,$firstLog]=$start('first');
// Wait for first delivery: the owner lock is held across the remaining messages.
for($i=0;$i<100 && !str_contains((string)@file_get_contents($firstLog),'Delivered to');$i++) usleep(20000);
if(!str_contains((string)file_get_contents($firstLog),'Delivered to')) throw new RuntimeException('First worker did not begin');
[$second,$secondLog]=$start('second');
if(proc_close($second)!==0 || proc_close($first)!==0) throw new RuntimeException('Worker failure');
$logs=file_get_contents($firstLog).file_get_contents($secondLog);
if(substr_count($logs,'Delivered to')!==3 || glob($queue.'/*.json') || (int)file_get_contents($counter)!==$before+3 || file_get_contents($secondLog)!=='') throw new RuntimeException('Competing worker duplicated or lost work');
echo "PASS jobs: overlapping owners process three synthetic messages once; safe EmailService substitute, no SMTP\n";
