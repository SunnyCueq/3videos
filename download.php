<?php
/**************************************************************************
 *                                                                        *
 *    4images - A Web Based Image Gallery Management System               *
 *    ----------------------------------------------------------------    *
 *                                                                        *
 *             File: download.php                                         *
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

$main_template = 0;

$nozip = 1;
define('GET_CACHES', 1);
define('ROOT_PATH', './');
$global_file = realpath(ROOT_PATH.'global.php');
if ($global_file === false || !file_exists($global_file) || dirname($global_file) !== realpath(ROOT_PATH)) {
  die('Security Error: Invalid file path');
}
include($global_file);
require(ROOT_PATH.'includes/sessions.php');
$user_access = get_permission();

if (!function_exists('file_get_contents')) {
  function file_get_contents($filename, $incpath = false, $resource_context = null) {
    if (strpos($filename, '..') !== false) {
      user_error('file_get_contents() failed: Path traversal detected', E_USER_WARNING);
      return false;
    }
    if (false === $fh = fopen($filename, 'rb', $incpath)) {
      user_error('file_get_contents() failed to open stream: No such file or directory', E_USER_WARNING);
      return false;
    }

    clearstatcache();
    if ($fsize = @filesize($filename)) {
      $data = fread($fh, $fsize);
    } else {
      $data = '';
      while (!feof($fh)) {
        $data .= fread($fh, 8192);
      }
    }

    fclose($fh);
    return $data;
  }
}

function fix_file_path($file_path) {
  if (!is_remote_file($file_path) && !file_exists($file_path)) {
    $file_path = preg_replace("/\/{2,}/", "/", get_document_root()."/".$file_path);
  }
  return $file_path;
}

function send_file($file_name, $file_path) {
  @session_write_close();

  header("Cache-Control: no-cache, must-revalidate");
  header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");

  if (get_user_os() == "MAC") {
    header("Content-Type: application/x-unknown\n");
    header("Content-Disposition: attachment; filename=\"".$file_name."\"\n");
  }
  elseif (get_browser_info() == "MSIE") {
    $disposition = (!preg_match("/\.zip$/i", $file_name)) ? 'attachment' : 'inline';
    header("Content-Disposition: $disposition; filename=\"".$file_name."\"\n");
    header("Content-Type: application/x-ms-download\n");
  }
  else {
    header("Content-Disposition: attachment; filename=\"".$file_name."\"\n");
    header("Content-Type: application/octet-stream\n");
  }

  $file_path = fix_file_path($file_path);

  if (!is_remote_file($file_path) && ($filesize = filesize($file_path)) > 0 && !@ini_get('zlib.output_compression') && !@ini_get('output_handler')) {
    header("Content-Length: ".$filesize."\n\n");
  }

  @readfile($file_path);
}

function cleanup_and_exit($error_msg = '', $redirect_url = '') {
  global $site_db;
  if ($error_msg) {
    error_log('Download Error: ' . str_replace(["\r\n", "\r", "\n"], ' ', $error_msg));
  }
  if (isset($site_db) && is_object($site_db)) {
    @$site_db->close();
  }
  @session_write_close();
  if ($redirect_url) {
    redirect($redirect_url);
  }
  exit(1);
}

$file = array();
$file_path = null;
$file_name = null;

if ($action == "lightbox") {
  if (empty($user_info['lightbox_image_ids']) || !function_exists("gzcompress") || !function_exists("crc32")) {
    redirect("lightbox.php");
  }

  if (!check_download_token($user_info['lightbox_image_ids'])) {
    redirect("lightbox.php");
  }

  $image_id_sql = str_replace(" ", ", ", trim($user_info['lightbox_image_ids']));
  $image_ids = array();
    // ✅ Modernized: No prepared statement needed here as $image_id_sql comes from session data that was already validated
  $sql = "SELECT image_id, cat_id, image_media_file, image_download_url
          FROM ".IMAGES_TABLE."
          WHERE image_active = 1 AND image_id IN ($image_id_sql) AND cat_id NOT IN (".get_auth_cat_sql("auth_viewimage", "NOTIN").", ".get_auth_cat_sql("auth_viewcat", "NOTIN").", ".get_auth_cat_sql("auth_download", "NOTIN").")";
  $result = $site_db->query($sql);

  if ($result) {
    include(ROOT_PATH."includes/zip.php");
    $zipfile = new zipfile();
    $file_added = 0;
    while ($image_row = $site_db->fetch_array($result)) {
      $file_path = null;
      $file_name = null;
      if (!empty($image_row['image_download_url'])) {
        if (is_remote_file($image_row['image_download_url']) || is_local_file($image_row['image_download_url'])) {
          $file_path = $image_row['image_download_url'];
          $file_name = basename($image_row['image_download_url']);
        }
      }
      elseif (is_remote($image_row['image_media_file'])) {
        $file_path = $image_row['image_media_file'];
        $file_name = get_basefile($image_row['image_media_file']);
      }
      else {
        $file_path = MEDIA_PATH."/".$image_row['cat_id']."/".$image_row['image_media_file'];
        $file_name = $image_row['image_media_file'];
      }

      if (!empty($file_path)) {
        @set_time_limit(120);
        $file_path = fix_file_path($file_path);
        if (!$file_data = @file_get_contents($file_path)) {
          continue;
        }
        $zipfile->add_file($file_data, $file_name);
        $file_added = 1;
        unset($file_data);
        $image_ids[] = $image_row['image_id'];
      }
    }

    if ($file_added) {
      if ($user_info['user_level'] != ADMIN) {
        // ✅ Modernized: Use Prepared Statement with IN clause
        $placeholders = implode(',', array_fill(0, count($image_ids), '?'));
        $sql = "UPDATE ".IMAGES_TABLE."
                SET image_downloads = image_downloads + 1
                WHERE image_id IN ($placeholders)"; 
        $stmt = $site_db->prepare($sql);
        $stmt->execute($image_ids);
      }

      $zipfile->send(time().".zip");
      @session_write_close();
      exit(0);
    }
    else {
      redirect("lightbox.php?empty=1");
    }
  }
}
elseif ($image_id) {
  if (isset($_GET['size']) || isset($_POST['size'])) {
    $size = (isset($_GET['size'])) ? intval($_GET['size']) : intval($_POST['size']);
  }
  else {
    $size = 0;
  }

  // ✅ Modernized: Use Prepared Statement
  $sql = "SELECT image_id, cat_id, user_id, image_media_file, image_download_url, image_downloads
          FROM ".IMAGES_TABLE."
          WHERE image_id = ? AND image_active = 1";
  $stmt = $site_db->prepare($sql);
  $stmt->execute([$image_id]);
  $image_row = $site_db->fetch_array($stmt->result);

  if (!$image_row || !check_permission("auth_viewcat", $image_row['cat_id']) || !check_permission("auth_viewimage", $image_row['cat_id'])) {
    redirect($url);
  }
  else {
    if (!check_permission("auth_download", $image_row['cat_id'])) {
      redirect($url);
    }

    if (!check_download_token($image_row['image_id'])) {
      error_log('Download Error: Hotlinking attempt for image_id ' . $image_row['image_id']);
      cleanup_and_exit('Hotlinking not allowed', 'index.php');
    }
  }

  $remote_url = 0;
  if (!empty($image_row['image_download_url'])) {
    if (is_remote_file($image_row['image_download_url']) || is_local_file($image_row['image_download_url'])) {
      preg_match("/(.+)\.(.+)/", basename($image_row['image_download_url']), $regs);
      $file_name = $regs[1];
      $file_extension = $regs[2];

      $file['file_name'] = $file_name.(($size) ? "_".$size : "").".".$file_extension;
      $file['file_path'] = dirname($image_row['image_download_url'])."/".$file['file_name'];
    }
    else {
      $file['file_path'] = $image_row['image_download_url'];
      $remote_url = 1;
    }
  }
  elseif (is_remote_file($image_row['image_media_file'])) {
    preg_match("/(.+)\.(.+)/", get_basefile($image_row['image_media_file']), $regs);
    $file_name = $regs[1];
    $file_extension = $regs[2];

    $file['file_name'] = $file_name.(($size) ? "_".$size : "").".".$file_extension;
    $file['file_path'] = dirname($image_row['image_media_file'])."/".$file['file_name'];
  }
  else {
    preg_match("/(.+)\.(.+)/", get_basefile($image_row['image_media_file']), $regs);
    $file_name = $regs[1];
    $file_extension = $regs[2];

    $file['file_name'] = $file_name.(($size) ? "_".$size : "").".".$file_extension;
    $file['file_path'] = (is_local_file($image_row['image_media_file'])) ? dirname($image_row['image_media_file'])."/".$file['file_name'] : MEDIA_PATH."/".$image_row['cat_id']."/".$file['file_name'];
  }

  if ($user_info['user_level'] != ADMIN) {
    // ✅ Modernized: Use Prepared Statement
    $sql = "UPDATE ".IMAGES_TABLE."
            SET image_downloads = image_downloads + 1
            WHERE image_id = ?";
    $stmt = $site_db->prepare($sql);
    $stmt->execute([$image_id]);
  }

  if (!empty($file['file_path'])) {
    @set_time_limit(120);
    if ($remote_url) {
      redirect($file['file_path']);
    }

    if ($action == "zip" && !preg_match("/\.zip$/i", $file['file_name']) && function_exists("gzcompress") && function_exists("crc32")) {
      include(ROOT_PATH."includes/zip.php");
      $zipfile = new zipfile();
      $zipfile->add_file(file_get_contents($file['file_path']), $file['file_name']);

      $zipfile->send(get_file_name($file['file_name']).".zip");
    } else {
        send_file($file['file_name'], $file['file_path']);
    }
    @session_write_close();
    exit(0);
  }
  else {
    cleanup_and_exit('Empty file path for image_id: ' . $image_id, 'index.php');
  }
}
else {
  cleanup_and_exit('No action specified', 'index.php');
}

exit(0);
?>
