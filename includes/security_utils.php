<?php
/**************************************************************************
 *                                                                        *
 *    4images - A Web Based Image Gallery Management System               *
 *    ----------------------------------------------------------------    *
 *                                                                        *
 *             File: security_utils.php                                   *
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
if (!defined('ROOT_PATH')) {
  die("Security violation");
}

function compare_passwords($plain, $hashed) {
  // Backwards compatibility
  if (strpos($hashed, ':') === false) {
    return secure_compare(md5($plain), $hashed);
  }

  return secure_compare(salted_hash($plain, $hashed), $hashed);
}

function salted_hash($value, $salt = null, $length = PASSWORD_SALT_LENGTH, $hash_algo = PASSWORD_HASH_ALGO) {
  if ($salt === null) {
    $salt = random_string($length);
  }

  $salt = substr($salt, 0, $length);

  if (!function_exists('hash') && $hash_algo == 'md5') {
    $hash = md5($salt . $value);
  } else {
    $hash = hash($hash_algo, $salt . $value);
  }

  return $salt . ':' . $hash;
}

function random_string($length, $letters_only = false) {
  $str = '';

  if (!$letters_only) {
    // ✅ Modernized: Use random_bytes() for cryptographically secure strings
    return bin2hex(random_bytes((int) ceil($length / 2)));
  }

  // ✅ Modernized: Use random_int() instead of mt_rand()
  for ($i = 0; $i < $length; $i++) {
    switch (random_int(1, 2)) {
      case 1:
        $str .= chr(random_int(65, 90)); // A-Z
        break;
      case 2:
        $str .= chr(random_int(97, 122)); // a-z
        break;
    }
  }

  return $str;
}

function secure_compare($a, $b) {
  if (strlen($a) !== strlen($b)) {
    return false;
  }
  $result = 0;
  for ($i = 0; $i < strlen($a); $i++) {
    $result |= ord($a[$i]) ^ ord($b[$i]);
  }
  return $result == 0;
}

?>
