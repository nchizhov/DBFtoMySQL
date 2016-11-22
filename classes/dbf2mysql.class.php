<?php
/********************************************
 * DBF to MySQL Converter
 *
 * Author: Chizhov Nikolay <admin@kgd.in>
 * (c) 2016 CIOB "Inok"
 ********************************************/

include("iDBF/Table.php");
include("iDBF/Records.php");
include("iDBF/Memo.php");

use \iDBF\Table;
use \iDBF\Records;

class dbf2mysql {
  /**
   * @var PDO
   */
  private $db;

  /**
   * @var Records
   */
  private $dbfRecords;

  private $config, $dbfHeaders, $dbfColumns, $log, $log_replaces;
  private $timer = [
    "start" => null,
    "tableStart" => null,
  ];
  private $colors = [
    "default" => "\e[0m",
    "red" => "\e[31m",
    "yellow" => "\e[93m",
    "white" => "\e[97m"
  ];
  private $percent = -1;
  private $column_fixes;

  public function __construct($config) {
    ini_set("memory_limit", "2048M");

    $this->config = $config;

    $this->checkConfig();
    $this->dbConnect();
    $this->timer["start"] = time();
    $this->writeLog("Start importing");
    $this->convert();
  }

  public function __destruct() {
    if (!is_null($this->timer["start"])) {
      $this->writeLog("Finish importing in <red>".(round((time() - $this->timer["start"]) / 60, 2))."<default> minutes");
    }
    fclose($this->log);
  }

  private function checkConfig() {
    //defaults
    $config_defaults = [
      "db_host"         => "localhost",
      "db_port"         => 3306,
      "db_username"     => "root",
      "db_password"     => "",
      "db_charset"      => "utf8",
      "dbf_list"        => null,
      "table_prefix"    => null,
      "columns_only"    => false,
      "deleted_records" => false,
      "key_field"       => null,
      "verbose"         => true,
      "log_path"        => realpath(dirname(__FILE__)."/..")."/dbf2mysql.log"
    ];

    $this->config = $this->config + $config_defaults;

    $this->initLog();

    //check MySQL
    if (!is_numeric($this->config["db_port"])) {
      $this->writeLog("<red>Error in config:<default> MySQL port should be number");
      exit;
    }
    if (!isset($this->config["db_name"])) {
      $this->writeLog("<red>Error in config:<default> MySQL database name not exists");
      exit;
    }
    if (!isset($this->config["dbf_path"])) {
      $this->writeLog("<red>Error in config:<default> DBF-files directory not exists");
      exit;
    }
    if (is_null($this->config["table_prefix"])) {
      $this->config["table_prefix"] = "";
    }
    //check dbf
    if (!is_null($this->config["dbf_list"])) {
      if (is_array($this->config["dbf_list"])) {
        $this->config["dbf_list"] = array_map("strtolower", $this->config["dbf_list"]);
      }
      else {
        $this->writeLog("<red>Error in config:<default> dbf list should be array or null");
        exit;
      }
    }
  }

  private function initLog() {
    $this->log = fopen($this->config["log_path"], "a");
    if ($this->log === false) {
      echo($this->colors["red"]."Error in log:".$this->colors["default"]." Couldn't create log file\n");
      exit;
    }
    $this->log_replaces = [
      "from" => array_map(function($value) {
        return "<".$value.">";
      }, array_keys($this->colors)),
      "to" => array_values($this->colors)
    ];
  }

  private function dbConnect() {
    $db_options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_WARNING,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ];
    try {
      $this->db = new PDO("mysql:host=" . $this->config["db_host"] . ";dbname=" . $this->config["db_name"],
                          $this->config["db_username"], $this->config["db_password"], $db_options);
    }
    catch (PDOException $e) {
      $this->writeLog("<red>Error in MySQL connection:<default> ".$e->getMessage());
      exit;
    }

    $this->db->exec("SET NAMES ".$this->config["db_charset"]);
  }

  private function convert() {
    $dbfs = new RegexIterator(new DirectoryIterator($this->config["dbf_path"]), "/\\.dbf\$/i");
    foreach ($dbfs as $file) {
      if (!is_null($this->config["dbf_list"]) && !in_array(strtolower($file->getBasename(".".$file->getExtension())), $this->config["dbf_list"])) {
        continue;
      }
      $this->timer["tableStart"] = time();
      $table = new Table($file->getPathname());
      $this->dbfHeaders = $table->getHeaders();
      if ($table->error) {
        $this->writeLog("<red>Error in DBF:<default> ".$table->error_info);
        continue;
      }
      $this->dbfHeaders["table"] = $this->config["table_prefix"].$this->dbfHeaders["table"];
      $this->dbfColumns = $table->getColumns();
      if ($table->error) {
        $this->writeLog("<red>Error in DBF:<default> ".$table->error_info);
        continue;
      }
      $this->createMySQLColumns();
      if (!$this->config["columns_only"]) {
        $this->dbfRecords = new Records($table, $this->config["db_charset"]);
        $this->writeRecords();
      }
      $this->setKeyField();
      unset($table);
    }
  }

  private function createMySQLColumns() {
    $line = [];
    $this->db->exec("DROP TABLE IF EXISTS `".$this->dbfHeaders["table"]."`");
    foreach ($this->dbfColumns as $column) {
      $name = "`".$column["name"]."`";
      switch ($column["type"]) {
        case "F":
        case "N":
          if ($column["decimal"]) {
            $line[] = $name." decimal(".($column["length"] + $column["decimal"]).", ".$column["decimal"].") NOT NULL DEFAULT 0";
          }
          else {
            $line[] = $name." bigint(".$column["length"].") NOT NULL DEFAULT 0";
          }
          break;
        case "D":
          $line[] = $name." date DEFAULT NULL";
          break;
        case "T":
          $line[] = $name." datetime DEFAULT NULL";
          break;
        case "L":
          $line[] = $name." tinyint(1) NOT NULL DEFAULT '0'";
          break;
        case "C":
          $line[] = $name." varchar(".$column["length"].") NOT NULL DEFAULT ''";
          break;
        case "M":
          $line[] = $name." text NOT NULL DEFAULT ''";
          break;
        case "P":
        case "G":
          $line[] = $name." blob NULL DEFAULT NULL";
          break;
      }
    }
    if (count($line)) {
      if ($this->config["deleted_records"]) {
        $line[] = "`deleted` tinyint(1) NOT NULL DEFAULT '0'";
      }
      $result = $this->db->exec("CREATE TABLE IF NOT EXISTS `".$this->dbfHeaders["table"]."` (".
                                  implode(", ", $line).
                                ") ENGINE=InnoDB DEFAULT 
                                 CHARSET=".$this->config["db_charset"]." 
                                 COMMENT='Converted DBF file: ".$this->dbfHeaders["table"].".dbf'");
      if ($result !== false) {
        $this->writeLog("Table <yellow>".$this->dbfHeaders["table"]."<default> successfully created");
      }
      else {
        $this->writeLog("<red>Error in MySQL:<default> ".print_r($this->db->errorInfo(), true));
      }
    }
    unset($line);
  }

  private function writeRecords() {
    if (count($this->dbfColumns)) {
      $this->writeLog("Init import records for table <yellow>".$this->dbfHeaders["table"]."<default>");
      $i = 0; $recordsPerPosition = $this->dbfHeaders["records"] / 50;
      $this->column_fixes = [];
      $sql_keys = [];
      $sql_values = [];
      foreach($this->dbfColumns as $column) {
        $sql_keys[] = "`".$column["name"]."`";
        $sql_values[] = ":".$column["name"];
        if (in_array($column["type"], ["F", "N"])) {
          $this->column_fixes[$column["name"]] = [
            "min" => 0,
            "max" => 0
          ];
        }
      }
      if ($this->config["deleted_records"]) {
        $sql_keys[] = "`deleted`";
        $sql_values[] = ":deleted";
      }
      $result = $this->db->prepare("INSERT INTO `".$this->dbfHeaders["table"]."` (".implode(", ", $sql_keys).") VALUES(".implode(", ", $sql_values).")");
      $this->db->beginTransaction();
      while ($record = $this->dbfRecords->nextRecord()) {
        $deleted = false;
        if ($this->config["deleted_records"]) {
          $result->execute($record);
        }
        else {
          if (!$record["deleted"]) {
            unset($record["deleted"]);
            $result->execute($record);
          }
          else {
            $deleted = true;
          }
        }

        if (!$deleted) {
          foreach ($this->column_fixes as $c_name => &$vals) {
            if ($vals["min"] > $record[$c_name]) {
              $vals["min"] = $record[$c_name];
            }
            if ($vals["max"] < $record[$c_name]) {
              $vals["max"] = $record[$c_name];
            }
          }
        }

        $i++;
        if ($this->config["verbose"]) {
          $this->drawStatus($i, $recordsPerPosition);
        }
      }
      $this->db->commit();

      //Fix max values
      $this->fixValues();

      $this->writeLog("Table <yellow>".$this->dbfHeaders["table"]."<default> successfully imported in <red>".
                       round((time() - $this->timer["tableStart"]) / 60, 2)."<default> minutes");
      unset($sql_keys, $sql_values);
    }
  }

  private function fixValues() {
    $this->writeLog("\nCaclulate column types for table <yellow>".$this->dbfHeaders["table"]."<default>");
    $lines = [];
    foreach ($this->dbfColumns as $column) {
      if (in_array($column["type"], ["F", "N"])) {
        $result = $this->column_fixes[$column["name"]];
        $unsigned = !($result["min"] < 0);
        if ($unsigned) {
          if (!$column["decimal"]) {
            $type = "bigint";
            if ($result["max"] > 16777215 && $result["max"] <= 4294967295) {
              $type = "int";
            } elseif ($result["max"] > 65535 && $result["max"] <= 16777215) {
              $type = "mediumint";
            } elseif ($result["max"] > 255 && $result["max"] <= 65535) {
              $type = "smallint";
            } elseif ($result["max"] <= 255) {
              $type = "tinyint";
            }
          }
        }
        else {
          if (!$column["decimal"]) {
            $type = "bigint";
            if ($result["min"] >= -128 && $result["max"] <= 127) {
              $type = "tinyint";
            } elseif ($result["min"] >= -32768 && $result["max"] <= 32767) {
              $type = "smallint";
            } elseif ($result["min"] >= -8388608 && $result["max"] <= 8388607) {
              $type = "mediumint";
            } elseif ($result["min"] >= -2147483648 && $result["max"] <= 2147483647) {
              $type = "int";
            }
          }
        }
        if ($column["decimal"] && $unsigned) {
          $lines[] = "CHANGE `".$column["name"]."` `".$column["name"]."` decimal(".($column["length"] + $column["decimal"]).", ".$column["decimal"].") UNSIGNED  
                      NOT NULL DEFAULT '0'";
        }
        else {
          $lines[] = "CHANGE `".$column["name"]."` `".$column["name"]."` ".$type."(".$column["length"].")".($unsigned ? " UNSIGNED" : "")." 
                      NOT NULL DEFAULT '0'";
        }
      }
    }
    if (count($lines)) {
      $this->db->beginTransaction();
      $this->db->exec("FLUSH TABLES; ALTER TABLE `".$this->dbfHeaders["table"]."` ".implode(", ", $lines).";");
      $this->db->commit();
    }
    unset($lines);
  }

  private function setKeyField() {
    if (!is_null($this->config["key_field"])) {
      $this->writeLog("Setting up index column for table <yellow>".$this->dbfHeaders["table"]."<default>");
      $result = $this->db->prepare("SELECT COLUMN_NAME 
                                    FROM INFORMATION_SCHEMA.COLUMNS 
                                    WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :table AND COLUMN_NAME = :column 
                                    LIMIT 1");
      $result->execute(["db" => $this->config["db_name"],
                        "table" => $this->dbfHeaders["table"],
                        "column" => $this->config["key_field"]]);
      if ($result->rowCount()) {
        $this->db->exec("ALTER TABLE `" . $this->dbfHeaders["table"] . "` ADD INDEX(`" . $this->config["key_field"] . "`)");
      }
    }
  }

  private function drawStatus($position, $perPosition) {
    $percent = (int) round($position / $perPosition);
    if ($this->percent <> !$percent) {
      $this->percent = $percent;
      $part1 = (($percent < 25) ?
          $this->colors["white"].str_repeat("H", $percent).$this->colors["default"].$this->colors["red"].str_repeat(".", 25 - $percent) :
          $this->colors["white"].str_repeat("H", 25)).$this->colors["default"];
      if ($percent > 25) {
        $percent = $percent - 25;
        $part2 = (($percent < 50) ?
            $this->colors["white"].str_repeat("H", $percent).$this->colors["default"].$this->colors["red"].str_repeat(".", 25 - $percent) :
            $this->colors["white"].str_repeat("H", 25)).$this->colors["default"];
      } else {
        $part2 = $this->colors["red"].str_repeat(".", 25).$this->colors["default"];
      }
      echo($this->colors["red"]."[".$this->colors["default"].$part1.
        $this->colors["red"]."50%".$this->colors["default"].
        $part2.$this->colors["red"]."]".$this->colors["default"]."\r");
    }
  }

  private function writeLog($message) {
    $message .= "\n";
    fwrite($this->log, "[".date("d.m.Y H:i:s")."] ".ltrim(str_replace($this->log_replaces["from"], "", $message), "\n"));
    if ($this->config["verbose"]) {
      echo(str_replace($this->log_replaces["from"], $this->log_replaces["to"], $message));
    }
  }
}