<?php
/**
 *
 * eg.
 * {$global.user_id}
 * {$widget.article.hots.total}
 * {var from=$widget.article.hots.total category=10}
 * {section from=$widget.article.hots.total name=hot category_id=$global.cid}
 *     {$hot.field_name}
 * {/section}
 * {include file=$widget.article.hots}
 * {pagination link="/page/@number@"}
 *
 */

namespace Humming;

class Template
{
    /**
     * Left Delimiter
     * @var string
     */
    protected $left = '{';

    /**
     * Right Delimiter
     * @var string
     */
    protected $right = '}';

    /**
     * Template File Extension
     * @var string
     */
    protected $extension = '.html';

    /**
     * Debug Mode
     * @var bool
     */
    protected $debug = false;

    /**
     * Template Dir
     * @var string
     */
    protected $dir;

    /**
     * Compiled Files Dir
     * @var string
     */
    protected $temp;

    /**
     * Global Variables
     * @var array
     */
    protected $globals = array();

    /**
     * Widget Base Class
     * @var Widget
     */
    protected $widget;

    /**
     * Pagination
     * @var Pagination
     */
    protected $pagination;

    /**
     * Template constructor.
     * @param $dir
     * @param $temp
     * @param Widget $widget
     * @param Pagination $pagination
     */
    public function __construct($dir, $temp, Widget $widget, Pagination $pagination)
    {
        $this->dir = $dir;
        $this->temp = $temp;
        $this->widget = $widget;
        $this->pagination = $pagination;
    }

    /**
     * Fetch Pagination Class
     * @return Pagination
     */
    public function getPagination()
    {
        return $this->pagination;
    }

    /**
     * Set Value To $global.value
     * @param $name
     * @param $value
     */
    public function assign($name, $value)
    {
        if (!empty($name))
            $this->globals[$name] = $value;
    }

    /**
     * Output View
     * @param $template
     * @param array $data
     * @return bool|false|string
     * @throws \Exception
     */
    public function display($template, array $data = array())
    {
        return $this->render($template, $data, true);
    }

    /**
     * Fetch View
     * @param $template
     * @param array $data
     * @param bool $output
     * @return bool|false|string
     * @throws \Exception
     */
    public function render($template, array $data = array(), $output = false)
    {
        foreach ($data as $k => $v) {
            $this->assign($k, $v);
        }
        ob_start();
        include $this->compiled($template);
        return $output ? ob_end_flush() : ob_get_flush();
    }

    /**
     * Fetch Compiled Template
     * @param $template
     * @return string
     * @throws \Exception
     */
    protected function compiled($template)
    {
        $template = ltrim($template, '/');
        if (strrchr($template, '.') == $this->extension)
            $template = substr($template, 0, -strlen($this->extension));
        $file = $this->dir . DIRECTORY_SEPARATOR . $template . $this->extension;
        if (!is_file($file))
            throw new \RuntimeException("template not found:$file!");

        $modified = filemtime($file);
        $compiled = $this->temp . DIRECTORY_SEPARATOR . $template . ".{$modified}.php";
        if (!$this->debug && is_file($compiled) && filemtime($compiled) >= $modified)
            return $compiled;

        $content = self::compile(file_get_contents($file), $this->left, $this->right);
        self::writeFile($compiled, $content, $this->temp);

        return $compiled;
    }

    /**
     * Compile Template
     * @param $content
     * @param $ldq
     * @param $rdq
     * @return string
     */
    protected static function compile($content, $ldq, $rdq)
    {
        $ldq = preg_quote($ldq, '!');
        $rdq = preg_quote($rdq, '!');
        $pattern = "!{$ldq}(/)?(\\$|var|section|paging|include)(.*?){$rdq}!s";
        $blocks = preg_split($pattern, $content);
        if (1 == count($blocks))
            return $content;

        preg_match_all($pattern, $content, $matches);
        $result = '';
        for ($i = 0, $found = count($matches[1]); $i < $found; $i++) {
            if ('$' == $matches[2][$i]) {
                $compiled = self::compileSimpleVar($matches[2][$i], $matches[3][$i]);
            } else if ('var' == $matches[2][$i]) {
                $compiled = self::compileVar($matches[3][$i]);
            } else if ('section' == $matches[2][$i]) {
                $compiled = self::compileSection($matches[3][$i], !empty($matches[1][$i]));
            } else if ('include' == $matches[2][$i]) {
                $compiled = self::compileInclude($matches[3][$i]);
            } else if ('paging' == $matches[2][$i]) {
                $compiled = self::compilePaging($matches[3][$i]);
            } else {
                $compiled = $matches[0][$i];
            }
            $result .= $blocks[$i] . $compiled;
        }
        $result .= $blocks[$i];

        return $result;
    }

    /**
     * Compile Variables
     * @param $name
     * @param $param
     * @return string
     */
    protected static function compileSimpleVar($name, $param)
    {
        return '<?php echo ' . self::__compileVar($name . $param) . '; ?>';
    }

    /**
     * Parse Variables
     * @param $val
     * @return string
     */
    protected static function __compileVar($val)
    {
        $parts = explode('.', trim($val));
        $deep = count($parts);
        if ('$widget' == $parts[0]) {
            $ret = "\$this->widget";
        } elseif ('$global' == $parts[0]) {
            $ret = "\$this->globals";
        } elseif ('$' == $parts[0]{0}) {
            $ret = "{$parts[0]}";
        } else {
            return $val;
        }
        $i = 1;
        while ($i < $deep) {
            if (false === filter_var($parts[$i], FILTER_VALIDATE_INT)) {
                $ret .= "['{$parts[$i]}']";
            } else {
                $ret .= "[{$parts[$i]}]";
            }
            $i++;
        }

        return $ret;
    }

    /**
     * Parse Variables
     * @param $param
     * @return string
     */
    protected static function compileVar($param)
    {
        $result = '<<<<{var} error!!!>>>>';
        if (!empty($param)) {
            $parameters = self::parseParameters($param);
            if (isset($parameters['from'])) {
                $result = "<?php echo {$parameters['from']};?>";
                if (0 == strncasecmp('$this->widget[', $parameters['from'], 14)) {
                    $widget = substr($parameters['from'], 15, strpos($parameters['from'], ']') - 16);
                    unset($parameters['from']);
                    $result = self::initWidgetParameters($widget, $parameters) . $result;
                }
            }
        }

        return $result;
    }

    /**
     * Parse Include
     * @param $param
     * @return string
     */
    protected static function compileInclude($param)
    {
        $result = '<<<<{include} error!!!>>>>';
        $parameters = self::parseParameters($param);
        if (isset($parameters['file'])) {
            if ('$' != $parameters['file']{0})
                $parameters['file'] = '\'' . $parameters['file'] . '\'';
            $result = "<?php \$this->display({$parameters['file']});?>";
        }

        return $result;
    }

    /**
     * Compile Humming
     * @param $param
     * @param $closed
     * @return string
     */
    protected static function compileSection($param, $closed)
    {
        $result = '<<<<{section} error!!!>>>>';
        if ($closed) {
            $result = "<?php endforeach;?>\n";
        } else if (!empty($param)) {
            $parameters = self::parseParameters($param);
            if (isset($parameters['loop'])) {
                $item = substr($parameters['loop'], strrpos($parameters['loop'], '[') + 2, -2);
                $result = "<?php foreach ({$parameters['loop']} as \$_{$item} => \${$item}):\n?>";
                if (0 == strncasecmp('$this->widget[', $parameters['loop'], 14)) {
                    $widget = substr($parameters['loop'], 15, strpos($parameters['loop'], ']') - 16);
                    unset($parameters['loop']);
                    $result = self::initWidgetParameters($widget, $parameters) . $result;
                }
            }
        }

        return $result;
    }

    /**
     * Compile Pagination
     * @param $param
     * @return string
     */
    protected static function compilePaging($param)
    {
        $parameters = self::parseParameters($param);
        $result = '';
        if (!empty($parameters['template']))
            $result .= "\$this->pagination->setTemplate(\$this->render('{$parameters['template']}'));\n?>";
        $link = 'null';
        if (!empty($parameters['link'])) {
            if ('$' != $parameters['link']{0})
                $parameters['link'] = '\'' . $parameters['link'] . '\'';
            $link = $parameters['link'];
        }
        $page = !empty($parameters['page']) ? $parameters['page'] : 'null';
        $total = !empty($parameters['total']) ? $parameters['total'] : 'null';
        $size = !empty($parameters['size']) ? $parameters['size'] : 'null';
        $result .= "<?php echo \$this->pagination->render({$link}, {$page}, {$total}, {$size});\n?>";

        return $result;
    }

    /**
     * Set Widget Parameters
     * @param string $widget
     * @param array $parameters
     * @return string
     */
    protected static function initWidgetParameters($widget, array $parameters)
    {
        $param = var_export($parameters, true);
        $param = preg_replace("/'\\$(.*)',/isU", "\$\\1,", $param);
        $param = str_replace('\\\'', '\'', $param);

        return "<?php \$this->widget['{$widget}']->setParameters($param);?>\n";
    }

    /**
     * Parse Parameters
     * @param $param
     * @return array
     */
    protected static function parseParameters($param)
    {
        $result = array();
        $parts = explode('=', trim($param));
        $key = array_shift($parts);
        while (!empty($parts)) {
            $value = array_shift($parts);
            while (('"' == $value{0} || "'" == $value{0}) && false == strrpos($value, $value{0}) && !empty($parts)) {
                $value .= '=' . array_shift($parts);
            }
            if (!empty($value) && !empty($parts) && $pos = strrpos($value, ' ')) {
                $result[$key] = self::__compileVar(trim(substr($value, 0, $pos), '\'"'));
                $key = substr($value, $pos + 1);
            } else {
                $result[$key] = self::__compileVar(trim($value, '\'"'));
            }
        }

        return $result;
    }

    /**
     * Write Compiled File
     * @param string $target
     * @param string $content
     * @param string $tmp
     * @return string bool
     */
    protected static function writeFile($target, $content, $tmp)
    {
        $dir = dirname($target);
        if (!is_dir($dir))
            mkdir($dir, 644, true);
        $file = tempnam($tmp, 'hum');
        if (!($fd = fopen($file, 'w')))
            throw new \RuntimeException("create template tmp file failed:$file!");
        fwrite($fd, $content);
        fclose($fd);
        if (file_exists($target))
            unlink($target);
        $ret = rename($file, $target);
        if ($ret)
            chmod($target, 644);
        else
            throw new \RuntimeException("create template failed:$target!");

        return $ret;
    }
}
