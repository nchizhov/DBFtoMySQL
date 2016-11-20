<?php
/********************************************
 * DBF to MySQL Converter
 *
 * Author: Chizhov Nikolay <admin@kgd.in>
 * (c) 2016 CIOB "Inok"
 ********************************************/

include("classes/dbf2mysql.class.php");

$config = [
  "db_host"         => "localhost",             //MySQL host (default: localhost)
  "db_port"         => 3306,                    //MySQL port (default: 3306)
  "db_username"     => "host",                  //MySQL username (default: root)
  "db_password"     => "host",                  //MySQL password (default: empty)
  "db_name"         => "host",                  //MySQL database (required)
  "db_charset"      => "utf8",                  //MySQL charset (default: utf8)
  "dbf_path"        => "/opt/host",             //DBF directory path (required)
  "dbf_list"        => null,                    //Array of dbf-files (without extension, case insensitive), if null - all dbf files in folder (default: null)
  "key_field"       => "code",                  //Key field in final MySQL tables (default: null)
  "table_prefix"    => "host_",                 //Add prefix for table name (default: null)
  "columns_only"    => false,                   //Import only columns to MySQL (default: false)
  "deleted_records" => false,                   //Import marked for deletion records: adds column deleted (default: false)
  "verbose"         => true,                    //Show conversion process (default: true)
  "log_path"        => "/var/log/dbf2mysql.log" //Log-file with process conversation, if empty or null - not logging (default: current script directory with filename dbf2mysql.log)
];

new dbf2mysql($config);