## DBF to MySQL import

### Description
This script imports (DBASE/FoxPro) files with/without MEMO-fields into MySQL tables. Used library **iDBF** (description of library in repository directory: ```classes/iDBF```) 

#### Instructions 
1. Edit configuration in import.php:
   * **db_host** - MySQL Server (default **localhost**)
   * **db_port** - MySQL Port (default **3306**)
   * **db_username** - MySQL Username (default: **root**)
   * **db_password** - MySQL User Password (default: **empty**)
   * **db_name** - MySQL Database name: *should exists* (**required**)
   * **db_charset** - MySQL Table Charset (default: **utf8**)
   * **dbf_path** - Path to DBF-files (**required**)
   * **dbf_list** - List of import DBF-files: *without extension, case-insensitive*. If **null** - import of all files from directory (default: **null**)
   * **key_field** - Adds index to MySQL table after import (default: **null**, required if **update** - True)
   * **columns_only** - Imports only columns from DBF-file (default: **false**)
   * **deleted_records** - Import marked for deletion records: *creating column with name '**deleted**'* (default: **false**)
   * **update** - If set to True is update records (default: False)
   * **verbose** - Show import process in console (default: **true**)
   * **log_path** - Log-file with import process. If *empty* or *null* - not logging (default: **current script directory**)
2. Run script:
```
/path/to/php import.php
```

#### Notes
1. Empty Dates and TimeDates fields converts to **NULL**
2. General and Picture fields of DBF-files imports into BLOB-fields
3. Logical fields with values: **'t', 'y', 'д'** converts to **'1'**, otherwise - to **'0'**
4. MEMO-fields imports into TEXT-fields
 
#### License
MIT 