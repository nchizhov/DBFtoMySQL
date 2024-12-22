<?php

//WARNING: Not finished. Not enough information about CDX, IDX index formats.

namespace iDBF;

use Exception;

class Indexes {
  private $file, $fp;
  private $headers = null;
  public $error = false;
  public $error_info = null;

  /**
   * @throws Exception
   */
  public function __construct($file) {
    $this->file = $file;
    $this->open();
  }

  /**
   * @throws Exception
   */
  private function open() {
    if (!file_exists($this->file)) {
      throw new Exception(sprintf('File %s cannot be found', $this->file));
    }
    $this->fp = fopen($this->file, "rb");
  }

  public function getHeaders() {
    if (!$this->error && is_null($this->headers)) {
      $this->readHeaders();
    }
    return $this->headers;
  }

  private function readHeaders() {
    $data = fread($this->fp, 1024);
    $this->headers = [
      "root_pointer" => unpack("L", substr($data, 0, 4))[1],
      "freelist_pointer" => unpack("L", substr($data, 4, 4))[1],
      "version_reserved" => unpack("N", substr($data, 8, 4))[1],
      "key_length" => unpack("S", substr($data, 12, 2))[1],
      "index_options" => unpack("C", $data[14])[1],
      "index_signature" => unpack("C", $data[15])[1], //32 + 64 + 128
      "sort_order" => unpack("S", substr($data, 502, 2))[1], //Ascending
      "total_expression_length" => unpack("n", substr($data, 504, 2))[1], //S, n?
      "for_expression_length" => unpack("n", substr($data, 506, 2))[1], //S, n
      "key_expression_length" => unpack("n", substr($data, 510, 2))[1],
    ];
    $data = fread($this->fp, 512);
    $attribute = [
      "node_attributes" => unpack("S", substr($data, 0, 2))[1],
      "number_keys" => unpack("S", substr($data, 2, 2))[1],
      "pointer_left" => unpack("L", substr($data, 4, 4))[1],
      "pointer_right" => unpack("L", substr($data, 8, 4))[1],
      "free_space" => unpack("S", substr($data, 12, 2))[1],
      "record_num_mask" => unpack("L", substr($data, 14, 4))[1],
      "duplicate_cnt_mask" => unpack("C", $data[18])[1],
      "trailing_byte_cnt_mask" => unpack("C", $data[19])[1],
      "record_n" => unpack("C", $data[20])[1],
      "duplicate_cnt" => unpack("C", $data[21])[1],
      "trailing_cnt" => unpack("C", $data[22])[1],
      "holding_record_n" => unpack("C", $data[23])[1],
      "rec_no" => unpack("N", substr($data, 24, 4))[1],
      "rec_no_data_file" => unpack("N", substr($data, 24, 4))[1],
      "key_data" => substr($data, 28)
    ];
    print_r($this->headers);
    print_r($attribute);
  }
}
