<?php
require 'config.php';
require_once 'vendor/autoload.php';

use \Illuminate\Database\Capsule\Manager as Capsule;

$capsule = new Capsule;

$capsule->addConnection(array(
    'driver' => 'mysql',
    'host' => DB_HOST,
    'database' => DB_NAME,
    'username' => DB_USER,
    'password' => DB_PASSWORD,
    'charset' => 'utf8',
    'collation' => 'utf8_unicode_ci',
    'prefix' => ''
));

$capsule->setAsGlobal();
$capsule->bootEloquent();

$log_data = Capsule::table('raw_logs')->where('status',0)->get();

foreach ($log_data as $value) {
    Capsule::table('raw_logs')->where('id',$value->id)->update(['status'=>1]);
    $empid = getEmpId($value->employee_code);
    $in = checkUserPunchedIn($empid);
    if ($value->direction == 'in' && !$in) {
        //insert
        $result = [
            'employee_id' => $empid,
            'punch_in_utc_time' => getUTCTime($value->log_date),
            'punch_in_note' => 'Logged ' . $value->direction . ' from ' . getDoor($value->device_id), // Anything
            'punch_in_time_offset' => 5.5,
            'punch_in_user_time' => $value->log_date,
            'state' => 'PUNCHED IN',
        ];
        setEmpAttendence($result);
    } elseif ($value->direction == 'out' && $in) {
        //update
        Capsule::table('ohrm_attendance_record')->where('id', $in)->update([
            'punch_out_utc_time' => getUTCTime($value->log_date),
            'punch_out_note' => 'Logged ' . $value->direction . ' from ' . getDoor($value->device_id), // Anything
            'punch_out_time_offset' => 5.5,
            'punch_out_user_time' => $value->log_date,
            'state' => 'PUNCHED OUT',
        ]);
    }


}

function getUTCTime($datestring)
{
    $date = new DateTime($datestring);
    $date->add(new DateInterval('PT5H30M'));
    return $date->format('Y-m-d H:i:s');
}

function getDoor($device)
{
    if ($device == 6.0) {
        return 'Front Door';
    } elseif ($device == 8.0) {
        return 'Back Door';
    } else {
        return 'Extenstion Door';
    }
}

function getEmpId($code)
{
    $obj = Capsule::table('hs_hr_employee')->where('employee_id', $code)->first();
    return empty($obj) ? 0 : $obj->emp_number;
}

function checkUserPunchedIn($empid)
{
    $obj = Capsule::table('ohrm_attendance_record')->where('employee_id', $empid)->where('state', 'PUNCHED IN')->first();
    return empty($obj) ? 0 : $obj->id;
}

function setEmpAttendence($array)
{
    Capsule::table('ohrm_attendance_record')->insert($array);
}
