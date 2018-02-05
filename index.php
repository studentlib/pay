<?php
/**
 *  index.php PHPCMS 入口
 *
 * @copyright			(C) 2005-2010 PHPCMS
 * @license				http://www.phpcms.cn/license/
 * @lastmodify			2010-6-1
 */
 //PHPCMS根目录

define('PHPCMS_PATH', dirname(__FILE__).DIRECTORY_SEPARATOR);

include PHPCMS_PATH.'/phpcms/base.php';


// start profiling
// xhprof_enable(XHPROF_FLAGS_CPU + XHPROF_FLAGS_MEMORY); 

$app=new application();


// stop profiler
// $xhprof_data = xhprof_disable();

// // display raw xhprof data for the profiler run
// // print_r($xhprof_data);


// $XHPROF_ROOT = realpath(dirname(__FILE__) .'/..');
// include_once PHPCMS_PATH . "/xhprof_lib/utils/xhprof_lib.php";
// include_once PHPCMS_PATH . "/xhprof_lib/utils/xhprof_runs.php";

// // save raw data for this profiler run using default
// // implementation of iXHProfRuns.
// $xhprof_runs = new XHProfRuns_Default();

// // // save the run under a namespace "xhprof_foo"
// $run_id = $xhprof_runs->save_run($xhprof_data, "xhprof_foo");

// echo "---------------\n".
// "Assuming you have set up the http based UI for \n".
// "XHProf at some address, you can view run at \n".
// "http://<xhprof-ui-address>/index.php?run=$run_id&source=xhprof_foo\n".
// "---------------\n";


?>