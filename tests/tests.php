<?php

class Tests {

    function run($f3) {
        $test=new \Test;
        Registry::clear('Cron');
        //plugin config
        $f3->config('tests/jobs.ini');
        $f3->mset([
            'log'=>FALSE,
            'cli'=>TRUE,
            'web'=>FALSE,
            'script'=>'index.php',
        ],'CRON.');
        $cron=Cron::instance();
        $test->expect(
            !$cron->log && !$cron->web && count($cron->jobs)==3 && $cron->script=='index.php',
            'Initial config'
        );
        $test->expect(
            $cron->jobs['JobC'][1]==='*/2 0-23 1-3,4-5 4,5,6 *',
            'Expression containing commas correctly restored'
        );
        $test->expect(
            isset($f3->ROUTES['/cron']),
            'Route automatically defined'
        );
        //binary auto-detection
        $binary=NULL;
        if (function_exists('exec'))
            foreach(['php','php-cli'] as $bin) {
                exec($bin.' -v 2>&1',$out,$ret);
                if ($ret==0) {
                    $binary=$bin;
                    break;
                }
            }
        $test->expect(
            $cron->binary===$binary,
            'Binary auto-detection: '.($binary?:'none')
        );
        $cron->binary('foobarbaz');
        $test->expect(
            $cron->binary===$binary,
            'Binary existence check'
        );
        //expression parsing
        $test->expect(
            $cron->parseExpr('1 2 3,9 4 5')===[[1], [2], [3,9], [4], [5]],
            'parseExpr(): single values'
        );
        $test->expect(
            $cron->parseExpr('1-3 2-5 3-2,1 4 *')===[[1,2,3], [2,3,4,5], [3,2,1], [4],range(0,6)],
            'parseExpr(): ranges and wildcards'
        );
        $test->expect(
            $cron->parseExpr('0,1-6/2,10 2 0-10/3 4 */3')===[[0,1,3,5,10], [2], [0,3,6,9], [4], [0,3,6]],
            'parseExpr(): step values on ranges and wildcards'
        );
        $test->expect(
            $cron->parseExpr('@yearly')===$cron->parseExpr('0 0 1 1 *') &&
            $cron->parseExpr('@annually')===$cron->parseExpr('0 0 1 1 *') &&
            $cron->parseExpr('@monthly')===$cron->parseExpr('0 0 1 * *') &&
            $cron->parseExpr('@weekly')===$cron->parseExpr('0 0 * * 0') &&
            $cron->parseExpr('@daily')===$cron->parseExpr('0 0 * * *') &&
            $cron->parseExpr('@hourly')===$cron->parseExpr('0 * * * *') &&
            $cron->parseExpr('@weekend')===$cron->parseExpr('0 8 * * 6') &&
            $cron->parseExpr('@lunch')===$cron->parseExpr('0 12 * * *'),
            'parseExpr(): schedule presets'
        );
        $test->expect(
            $cron->parseExpr('1 2 3 4')===FALSE && $cron->parseExpr('*-2 2 3 4 5')===FALSE &&
            $cron->parseExpr('1 2 3-5/4 4 5')===FALSE && $cron->parseExpr('1 2 3 4 sun')===FALSE &&
            $cron->parseExpr('@dinner')===FALSE,
            'parseExpr(): invalid expressions or presets return FALSE'
        );
        //timestamp parsing
        $time=mktime(1,2,3,4,5,2015);// 2015-04-05 01:02:03 (Saturday)
        $test->expect(
            $cron->parseTimestamp($time)==[2,1,5,4,0],
            'parseTimestamp()'
        );
        //due check
        $test->expect(
            $cron->isDue('JobA',$time) && $cron->isDue('JobB',$time) && $cron->isDue('JobC',$time),
            'isDue() returns TRUE if the requested job is due at the given time'
        );
        $test->expect(
            $cron->isDue('Foo',$time)===FALSE || $cron->isDue('joba',$time)===FALSE,
            'isDue() returns FALSE if the requested job doesn\'t exist or if the case doesn\'t match'
        );
        $cron->set('JobD',function($f3){
            $f3->job.='D';
        },'* * 4 * *');
        $cron->set('JobE','Jobs->jobE','2 1 5 4 1');
        $cron->set('JobF','Jobs->jobF','*/3 * * * *');
        $test->expect(
            !$cron->isDue('JobD',$time) && !$cron->isDue('JobE',$time) && !$cron->isDue('JobF',$time),
            'isDue() returns FALSE if the requested job is not due at the given time'
        );
        //job execution
        $f3->job='';
        $cron->execute('JobA',FALSE);
        $cron->execute('JobD',FALSE);
        $test->expect(
            $f3->job==='AD',
            'Serial job execution'
        );
        //logging
        @unlink($logfile=$f3->LOGS.'cron.log');
        $cron->log=TRUE;
        $cron->execute('JobA',FALSE);
        $cron->log=FALSE;
        $test->expect(
            file_exists($logfile) && count(file($logfile))==1,
            'Logging to file'
        );
        //schedule running
        $f3->job='';
        $cron->run($time,FALSE);
        $test->expect(
            $f3->job='AB',
            'Run scheduler, i.e executes all due jobs'
        );
        //async job execution
        if ($binary) {
            $cron->set('test1','Jobs->test1','* * * * *');
            $cron->set('test2','Jobs->test2','* * * * *');
            @unlink($testfile=$f3->TEMP.'cron-test.txt');
            $cron->execute('test1',TRUE);
            $cron->execute('test2',TRUE);
            $async_ok=FALSE;
            //wait for async processes to complete
            $start=microtime(TRUE);
            $loop=[0.1,4];//loop (step=0.1s / max=4s)
            while(microtime(TRUE)-$start<$loop[1]) {
                usleep($loop[0]*1000000);
                if (file_exists($testfile) && in_array(file_get_contents($testfile),['ACDB','CADB','CDAB'])) {
                    $async_ok=TRUE;
                    break;
                }
            }
            $test->expect(
                $async_ok,
                'Parallel job execution'
            );
        }
        //web access forbidden by default
        $f3->HALT=FALSE;
        $f3->clear('ERROR');
        $f3->ONERROR=function(){};
        $f3->mock('GET /cron');
        $test->expect(
            isset($f3->ERROR['code']) && $f3->ERROR['code']===404,
            'Web access forbidden by default'
        );
        $f3->set('results',$test->results());
    }

    function afterRoute($f3) {
        $f3->set('active','Cron');
        echo \Preview::instance()->render('tests.htm');
    }

}
