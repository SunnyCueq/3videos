<?php
/**************************************************************************
 *                                                                        *
 *    4images - A Web Based Image Gallery Management System               *
 *    ----------------------------------------------------------------    *
 *                                                                        *
 *             File: db_mysql.php                                         *
 *        Copyright: (C) 2002-2023 4homepages.de                          *
 *            Email: 4images@4homepages.de                                * 
 *              Web: http://www.4homepages.de                             * 
 *    Scriptversion: 1.10                                                 *
 *                                                                        *
 **************************************************************************
 *                                                                        *
 *    Dieses Script ist KEINE Freeware. Bitte lesen Sie die Lizenz-       *
 *    bedingungen (Lizenz.txt) für weitere Informationen.                 *
 *    ---------------------------------------------------------------     *
 *    This script is NOT freeware! Please read the Copyright Notice       *
 *    (Licence.txt) for further information.                              *
 *                                                                        *
 *************************************************************************/

declare(strict_types=1);

if (!defined('ROOT_PATH')) {
  die("Security violation");
}

class Db {
  public int $no_error = 0;
  public $connection;
  public $query_id = 0;
  public int $query_count = 0;
  public float $query_time = 0;
  public array $query_array = [];
  public array $table_fields = [];

  function __construct($db_host, $db_user, $db_password = "", $db_name = "", $db_pconnect = 0) {
    if (!$this->connection = @mysqli_connect($db_host, $db_user, $db_password, $db_name)) {
      $this->error("Could not connect to the database server (".safe_htmlspecialchars($db_host).", ".safe_htmlspecialchars($db_user).").", 1);
    }
    mysqli_set_charset($this->connection, 'utf8');
    return $this->connection;
  }

  function escape($value) {
    return mysqli_real_escape_string($this->connection, $value);
  }

  function close() {
    if ($this->connection) {
      if ($this->query_id instanceof mysqli_result) {
        @mysqli_free_result($this->query_id);
      }
      return @mysqli_close($this->connection);
    }
    else {
      return false;
    }
  }

  function query($query = "") {
    unset($this->query_id);
    if ($query != "") {
      if ((defined("PRINT_QUERIES") && PRINT_QUERIES == 1) || (defined("PRINT_STATS") && PRINT_STATS == 1)) {
        $startsqltime = explode(" ", microtime());
      }
      if (!$this->query_id = @mysqli_query($this->connection, $query)) {
        $this->error("<b>Bad SQL Query</b>: ".safe_htmlspecialchars($query)."<br /><b>".safe_htmlspecialchars(mysqli_error($this->connection))."</b>");
      }
      if ((defined("PRINT_QUERIES") && PRINT_QUERIES == 1) || (defined("PRINT_STATS") && PRINT_STATS == 1)) {
        $endsqltime = explode(" ", microtime());
        $totalsqltime = round($endsqltime[0]-$startsqltime[0]+$endsqltime[1]-$startsqltime[1],3);
        $this->query_time += $totalsqltime;
        $this->query_count++;
      }
      if (defined("PRINT_QUERIES") && PRINT_QUERIES == 1) {
        $query_stats = htmlentities($query);
        $query_stats .= "<br><b>Querytime:</b> ".$totalsqltime;
        $this->query_array[] = $query_stats;
      }
      return $this->query_id;
    }
  }

  function fetch_array($query_id = null, $assoc = 0) {
    if ($query_id !== null && $query_id !== -1) {
      $this->query_id = $query_id;
    }
    if ($this->query_id && $this->query_id instanceof mysqli_result) {
      return ($assoc) ? mysqli_fetch_assoc($this->query_id) : mysqli_fetch_array($this->query_id);
    }
    return false;
  }

  function free_result($query_id = null) {
    if ($query_id !== null && $query_id !== -1) {
      $this->query_id = $query_id;
    }
    if ($this->query_id instanceof mysqli_result) {
      return @mysqli_free_result($this->query_id);
    }
    return false;
  }

  function query_firstrow($query = "") {
    if ($query != "") {
      $this->query($query);
    }
    $result = $this->fetch_array($this->query_id);
    $this->free_result();
    return $result;
  }

  function get_numrows($query_id = null) {
    if ($query_id !== null && $query_id !== -1) {
      $this->query_id = $query_id;
    }
    if ($this->query_id instanceof mysqli_result) {
      return mysqli_num_rows($this->query_id);
    }
    return 0;
  }

  function get_insert_id() {
    return ($this->connection) ? @mysqli_insert_id($this->connection) : 0;
  }

  function get_next_id($column = "", $table = "") {
    if (!empty($column) && !empty($table)) {
      $sql = "SELECT MAX($column) AS max_id
              FROM $table";
      $row = $this->query_firstrow($sql);
      return (($row['max_id'] + 1) > 0) ? $row['max_id'] + 1 : 1;
    }
    else {
      return NULL;
    }
  }

  function get_numfields($query_id = null) {
    if ($query_id !== null && $query_id !== -1) {
      $this->query_id = $query_id;
    }
    if ($this->query_id instanceof mysqli_result) {
      return @mysqli_num_fields($this->query_id);
    }
    return 0;
  }

  function get_fieldname($query_id = null, $offset) {
    if ($query_id !== null && $query_id !== -1) {
      $this->query_id = $query_id;
    }
    if ($this->query_id instanceof mysqli_result) {
      $field_info = mysqli_fetch_field_direct($this->query_id, $offset);
      return $field_info ? $field_info->name : false;
    }
    return false;
  }

  function get_fieldtype($query_id = null, $offset) {
    if ($query_id !== null && $query_id !== -1) {
      $this->query_id = $query_id;
    }
    if ($this->query_id instanceof mysqli_result) {
      $field_info = mysqli_fetch_field_direct($this->query_id, $offset);
      return $field_info ? $field_info->type : false;
    }
    return false;
  }

  function affected_rows() {
    return ($this->connection) ? @mysqli_affected_rows($this->connection) : 0;
  }

  function is_empty($query = "") {
    if ($query != "") {
      $this->query($query);
    }
    if ($this->query_id instanceof mysqli_result) {
      return (!mysqli_num_rows($this->query_id)) ? 1 : 0;
    }
    return 1;
  }

  function not_empty($query = "") {
    if ($query != "") {
      $this->query($query);
    }
    if ($this->query_id instanceof mysqli_result) {
      return (!mysqli_num_rows($this->query_id)) ? 0 : 1;
    }
    return 0;
  }

  function get_table_fields($table) {
    if (!empty($this->table_fields[$table])) {
      return $this->table_fields[$table];
    }
    $this->table_fields[$table] = array();
    $result = $this->query("SHOW FIELDS FROM $table");
    while ($row = $this->fetch_array($result)) {
      $this->table_fields[$table][$row['Field']] = $row['Type'];
    }
    return $this->table_fields[$table];
  }

  function error($errmsg, $halt = 0) {
    if (!$this->no_error) {
      global $user_info;
      if (!defined("4IMAGES_ACTIVE") || (isset($user_info['user_level']) && $user_info['user_level'] == ADMIN)){
        echo "<br /><font color='#FF0000'><b>DB Error</b></font>: ".$errmsg."<br />";
      } else {
        echo "<br /><font color='#FF0000'><b>An unexpected error occured. Please try again later.</b></font><br />";
      }
      if ($halt) {
        exit;
      }
    }
  }
} // end of class
?>
