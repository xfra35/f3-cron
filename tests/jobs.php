<?php

class Jobs {

    function jobA() {
        \Base::instance()->concat('job','A');
    }

    function jobB() {
        \Base::instance()->concat('job','B');
    }

    function test1($f3) {
        $this->write('A');
        //$f3->write($f3->TEMP.'cron-test.txt','A',TRUE);
        usleep(50000);
        $this->write('B');
        //$f3->write($f3->TEMP.'cron-test.txt','B',TRUE);
    }

    function test2($f3) {
        $this->write('C');
        //$f3->write($f3->TEMP.'cron-test.txt','C',TRUE);
        usleep(10000);
        $this->write('D');
        //$f3->write($f3->TEMP.'cron-test.txt','D',TRUE);
    }

    function write($data) {
        $f3=\Base::instance();
        $f3->mutex('test',function()use($f3,$data){
            $f3->write($f3->TEMP.'cron-test.txt',$data,TRUE);
        });
    }

}