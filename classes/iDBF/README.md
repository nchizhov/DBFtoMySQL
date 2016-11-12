## DBF-file Reader Class

### Description
This group of classes (**Table**, **Records**, **Memo** in namespace **iDBF**) needs for reading DBF-files (structure, records) with/without MEMO-fields.

### Classes descriptions
#### Table
May read headers of: FoxBASE, dBASE III, dBASE IV, dBASE 5, dBASE 7 (*partial*), FoxPro, FoxBASE+, Visual FoxPro file structure.

##### Using: 
```
$table = new \iDBF\Table(/path/to/dbf/file);
```

##### Methods:
   * ```$table->getHeaders()``` - return array of DBF-file headers
   * ```$table->getColumns()``` - return array of DBF-file columns
   * ```$table->getData()``` - return resource to DBF-file body (required for **\iDBF\Records**)
   * ```$table->error``` - return boolean true if in DBF-file errors in headers or columns
   * ```$table->error_info``` - returns error description or **null** - if no errors
   * ```$table->close()``` - close DBF-file (also closing on destruct class)
      
### Records
May read records of: FoxBASE, dBASE III, dBASE IV, dBASE 5, dBASE 7, FoxPro, FoxBASE+, Visual FoxPro file records. Now implements column types:
* **C** - Character
* **D** - Date (if empty converts to null)
* **F** - Float
* **G** - General (OLE)
* **L** - Logical ('t', 'y', 'д' - converts to 1, all others to 0)
* **M** - Memo 
* **N** - Numeric
* **P** - Picture
* **T** - DateTime  (if empty converts to null) (*partial implemented*)

##### Using: 
```
$records = new \iDBF\Records($data, $headers, $columns, $encode);
```
* **$data** - DBF-file resource from \iBF\Table getData()
* **$headers** - DBF-file headers array
* **$columns** - DBF-file columns array
* **$encode** - iconv **Memo, Character** fields to selected character (default: **utf8**)

##### Methods:
   * ```$record->nextRecord``` - reads next record from DBF-file (return record-array or false - if records finished)
   
### Memo
May read MEMO-files formats (headers and records): DBT, FPT, SMT

##### Using:
```
$memo = new \iDBF\Memo(/path/to/dbf/memo/file);
```

##### Methods:
   * ```$memo->getHeaders()``` - returns array of MEMO-file headers
   * ```$memo->readMemo($record)``` - return array of MEMO ```$record``` position
   * ```$memo->close()``` - close MEMO-file (also closing on destruct class)
   
### Notes
#### Table header array:
* **dbf_file** - path to DBF-file
* **table** - DBF-table name in lowercase
* **version** - DBF-file version
* **version_name** - DBF-file version text description
* **date** - DBF-file last change date in *d.m.Y*-format (years between 1970 - 2069)
* **records** - Number of records in DBF-file
* **record_length** - One record length (in bytes) in DBF-file
* **unfinished_transaction** - Flag of unfinished transactions in DBF-file
* **coded** - Flag of coded *dBASE IV* database
* **mdx_flag** - Flag of index MDX-file
* **charset** - Charset identifier of DBF-file records
* **charset_name** - Normal charset name of DBF-file records
* **memo** - If *True* - DBF-file have MEMO-fields
* **memo_file** - MEMO-file path if DBF-file have MEMO-fields

#### Column header array:
* **name** - column name in lowercase
* **type** - column type (one char)
* **length** - column length
* **decimal** - if not *0* - decimal part of number
* **mdx_flag** - MDX-flag on column
* **auto_increment** - next auto increment value (only for *dBASE 7*)

#### MEMO-file header array:
* **freeblock_position** - position of next free block of MEMO-file
* **block_size** - MEMO-file block size

### License
MIT 