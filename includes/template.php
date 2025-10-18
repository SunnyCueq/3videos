<?php
/**************************************************************************
 *                                                                        *
 *    4images - A Web Based Image Gallery Management System               *
 *    ----------------------------------------------------------------    *
 *                                                                        *
 *             File: template.php                                         *
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
    throw new Exception("Security violation");
}

class Template
{
    public int $no_error = 0;
    public array $val_cache = [];
    public array $missing_val_cache = [];
    public array $template_cache = [];
    public string $template_path;
    public string $template_extension = "html";
    public string $start = "{";
    public string $end = "}";

    public function __construct($template_path = "")
    {
        if (!@is_dir($template_path)) {
            $this->error("Couldn't open Template-Pack ".$template_path, 1);
        }
        $this->template_path = $template_path;
    }

    public function register_vars($var_name, $value = "")
    {
        if (!is_array($var_name)) {
            $this->val_cache[$var_name] = $value;
        } else {
            $this->val_cache = array_merge($this->val_cache, $var_name);
        }
    }

    public function un_register_vars($var_list)
    {
        $vars = explode(",", $var_list);
        foreach ($vars as $val) {
            unset($this->val_cache[trim($val)]);
        }
    }

    public function cache_templates($template_list)
    {
        $template_list = explode(",", $template_list);
        foreach ($template_list as $val) {
            $val = trim($val);
            if (!isset($this->template_cache[$val])) {
                $this->get_template($val);
            }
        }
    }

    public function get_template($template)
    {
        if (!isset($this->template_cache[$template])) {
            $template = basename($template);
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template)) {
                throw new Exception("Invalid template name");
            }
            $path = $this->template_path."/".$template.".".$this->template_extension;
            $real_path = realpath($path);
            $real_template_path = realpath($this->template_path);
            if (!$real_path || !$real_template_path || strpos($real_path, $real_template_path) !== 0) {
                throw new Exception("Invalid template path");
            }
            $line = @file_get_contents($real_path);
            if (empty($line)) {
                $this->error("Couldn't open Template ".$path, 1);
            }

            if (defined('EXEC_PHP_CODE') && EXEC_PHP_CODE == 0) {
                $line = preg_replace("/<[\?|%]+(php|=)?(.*)[\?|%]+>/siU", "", $line);
                $line = preg_replace("/<script\s+language\s?=\s?[\"|']?php[\"|']?>(.*)<\/script>/siU", "", $line);
            }

            $line = $this->compile_template($line);

            $this->template_cache[$template] = $line;
        }
        return $this->template_cache[$template];
    }

    public function parse_template($template)
    {
        $template = $this->get_template($template);

        // Don't show error notices
        $old = error_reporting(E_ALL ^ E_NOTICE);

        extract($this->val_cache);
        ob_start();
        $temp_file = tempnam(sys_get_temp_dir(), 'tpl_');
        if ($temp_file === false) {
            throw new Exception("Failed to create temporary file");
        }
        chmod($temp_file, 0600);
        if (file_put_contents($temp_file, $template, LOCK_EX) === false) {
            @unlink($temp_file);
            throw new Exception("Failed to write temporary file");
        }
        include $temp_file;
        @unlink($temp_file);

        $str = ob_get_contents();
        ob_end_clean();

        // Reset error_reporting
        error_reporting($old);

        return $str;
    }

    public function compile_template($template)
    {
        // Replace <?xml by printing them via php to avoid error messages when short_open_tags is on
        $template = preg_replace('/<\?xml/i', "<?php echo '<?xml'; ?>", $template);

        // Compile variables in PHP code
        preg_match_all(
            "/<[\?|%]+(php|=)?(.*)[\?|%]+>/siU",
            $template,
            $regs,
            PREG_SET_ORDER
        );

        for ($i = 0; isset($regs[$i]); $i++) {
            // Fix single quotes
            $parsed = preg_replace_callback(
                "/=\s*'(.*)".preg_quote($this->start)."([A-Z0-9_]+)".preg_quote($this->end)."(.*)';/Usi",
                [$this, '_fix_php_quotes'],
                $regs[$i][0]
            );

            $parsed = preg_replace_callback(
                '='.preg_quote($this->start).'([A-Z0-9_]+)'.preg_quote($this->end).'=Usi',
                [$this, '_compile_php_var'],
                $parsed
            );

            $template = str_replace($regs[$i][0], $parsed, $template);
        }

        // Compile variables
        $template = preg_replace_callback(
            '='.preg_quote($this->start).'([A-Z0-9_]+)'.preg_quote($this->end).'=Usi',
            [$this, '_compile_var'],
            $template
        );

        // Compile condition tags
        $template = preg_replace_callback(
            '='.preg_quote($this->start).'if(not?)?\s+([A-Z0-9_]+)'.preg_quote($this->end).'=Usi',
            [$this, '_compile_condition_start'],
            $template
        );

        $template = preg_replace_callback(
            '='.preg_quote($this->start).'endif(not?)?\s+([A-Z0-9_]+)'.preg_quote($this->end).'=Usi',
            [$this, '_compile_condition_end'],
            $template
        );

        return $template;
    }

    public function _compile_php_var($matches)
    {
        return '{$' . trim($matches[1]) . '}';
    }

    public function _fix_php_quotes($matches)
    {
        return '= "' . str_replace('"', '\\"', $matches[1])
           . $this->start.$matches[2].$this->end
           . str_replace('"', '\\"', $matches[3]) . '";';
    }

    public function _compile_var($matches)
    {
        $name = trim($matches[1]);

        if (!isset($this->val_cache[$name])) {
            return $matches[0];
        }

        // Header and Footer are parsed in print_template()
        if ($name == 'header' || $name == 'footer') {
            return $matches[0];
        }

        return '<?php echo $' . $name . '; ?>';
    }

    public function _compile_condition_start($matches)
    {
        $name = trim($matches[2]);

        if (!isset($this->val_cache[$name])) {
            return $matches[0];
        }

        if ($matches[1] == 'not' || $matches[1] == 'no') {
            return '<?php if (empty($' . $name . ') || $' . $name . ' === REPLACE_EMPTY){ ?>';
        }

        return '<?php if (!empty($' . $name . ') && $' . $name . ' !== REPLACE_EMPTY){ ?>';
    }

    public function _compile_condition_end($matches)
    {
        $name = trim($matches[2]);

        if (!isset($this->val_cache[$name])) {
            return $matches[0];
        }

        return '<?php } ?>';
    }

    public function parse_array($array)
    {
        static $keys;

        foreach ($array as $key => $val) {
            if (is_array($val)) {
                $array[$key] = $this->parse_array($val);
            } else {
                if (!isset($keys) || count($keys) != count($this->val_cache)) {
                    $keys = array_keys($this->val_cache);
                    array_walk($keys, [$this, '_prepare_key']);
                }

                $array[$key] = str_replace($keys, $this->val_cache, $val);
            }
        }
        return $array;
    }

    public function _prepare_key(&$item)
    {
        $item = $this->start.$item.$this->end;
    }

    private function sanitize_log_message($message)
    {
        return str_replace(["\r\n", "\r", "\n", "\t"], [' ', ' ', ' ', ' '], $message);
    }

    public function print_template($template)
    {
        if (strpos($template, $this->start.'header'.$this->end) !== false) {
            $header = $this->parse_template("header");
            $template = str_replace($this->start.'header'.$this->end, $header, $template);
        }

        if (strpos($template, $this->start.'footer'.$this->end) !== false) {
            $footer = $this->parse_template("footer");
            $template = str_replace($this->start.'footer'.$this->end, $footer, $template);
        }

        print $this->clean_template($template);
    }

    public function clean_template($template)
    {
        $search_array = [
      '='.preg_quote($this->start).'([A-Z0-9_]+)'.preg_quote($this->end).'=Usi',
      '='.preg_quote($this->start).'if(not?)?\s+([A-Z0-9_]+)'.preg_quote($this->end).'=Usi',
      '='.preg_quote($this->start).'endif(not?)?\s+([A-Z0-9_]+)'.preg_quote($this->end).'=Usi',
    ];
        $replace_array = [
      "",
      "",
      ""
    ];
        $template = preg_replace($search_array, $replace_array, $template);

        return $template;
    }

    public function error($errmsg, $halt = 0)
    {
        if (!$this->no_error) {
            global $user_info;
            $safe_log_msg = $this->sanitize_log_message($errmsg);
            
            if (defined('IN_CP') && IN_CP == 1) {
                error_log("Template Error: " . $safe_log_msg);
                echo "<br /><font color='#FF0000'><b>Template Error</b></font>: ".htmlspecialchars($errmsg, ENT_QUOTES, 'UTF-8')."<br />";
            } elseif (isset($user_info['user_level']) && $user_info['user_level'] == ADMIN) {
                error_log("Template Error: " . $safe_log_msg);
                echo "<br /><font color='#FF0000'><b>Template Error</b></font>: ".htmlspecialchars($errmsg, ENT_QUOTES, 'UTF-8')."<br />";
            } else {
                error_log("Template Error: " . $safe_log_msg);
                echo "<br /><font color='#FF0000'><b>An unexpected error occurred. Please try again later.</b></font><br />";
            }
            
            if ($halt) {
                if (defined('IN_CP') && IN_CP == 1) {
                    exit(1);
                }
                throw new Exception("Template error occurred");
            }
        }
    }
} // end of class
