<?php

class Jobs {

    function jobA($f3) {
        $f3->concat('job','A');
    }

    function jobB($f3) {
        $f3->concat('job','B');
    }

    function test1($f3) {
        $this->write('A');
        usleep(50000);
        $this->write('B');
    }

    function test2($f3) {
        $this->write('C');
        usleep(10000);
        $this->write('D');
    }

    function write($data) {
        $f3=\Base::instance();
        $f3->mutex('test',function()use($f3,$data){
            $f3->write($f3->TEMP.'cron-test.txt',$data,TRUE);
        });
    }

}
