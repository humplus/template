<?php namespace Humming;

class Pagination
{
    const FIRST = '@first@';
    const PREV = '@prev@';
    const NEXT = '@next@';
    const LAST = '@last@';
    const NUMBER = '@number@';

    /**
     * Default template
     * @var string
     */
    protected $template = '<div class="pagination">
                                <ul>
                                    <li>@first@</li>
                                    <li>@prev@</li>
                                    <li>@number@</li>
                                    <li>@next@</li>
                                    <li>@last@</li>
                                </ul>
                            </div>';
    /**
     * Default Link Text
     * @var array
     */
    protected $links = array(
        self::FIRST => 'First',
        self::PREV => 'Previous',
        self::NEXT => 'Next',
        self::LAST => 'Last'
    );

    /**
     * Current Html Parts
     * @var string
     */
    protected $current = '<span>@number@</span>';

    /**
     * Page Url
     * @var string
     */
    protected $url;

    /**
     * Page Number
     * @var integer
     */
    protected $number;

    /**
     * Total Number
     * @var integer
     */
    protected $total;

    /**
     * Page Size
     * @var integer
     */
    protected $size;

    /**
     * Middle Page Links Size
     * @var integer
     */
    protected $middle = 7;

    /**
     * Mini Mode
     * @var bool
     */
    protected $thin = true;

    /**
     * Pagination constructor.
     * @param string $template
     * @param array $links
     * @param string $current
     */
    public function __construct($template = '', $links = array(), $current = '')
    {
        $this->setTemplate($template);
        $this->setLinks($links);
        $this->setCurrent($current);
    }

    /**
     * Set Paging Template
     * @param $template
     * @return void
     */
    public function setTemplate($template)
    {
        if (!empty($template))
            $this->template = $template;
    }

    /**
     * Set Links Text
     * @param array $links
     */
    public function setLinks(array $links)
    {
        $this->links = array_merge($this->links, $links);
    }

    /**
     * Set Current Link Text
     * @param $current
     */
    public function setCurrent($current)
    {
        if (!empty($current))
            $this->current = $current;
    }

    /**
     * Set Page Url
     * @param $url
     */
    public function setUrl($url)
    {
        $this->url = $url;
    }

    /**
     * Set Page Number
     * @param $number
     */
    public function setNumber($number)
    {
        $this->number = $number;
    }

    /**
     * Set Total Number
     * @param $total
     */
    public function setTotal($total)
    {
        $this->total = $total;
    }

    /**
     * Set Page Size
     * @param $size
     */
    public function setSize($size)
    {
        $this->size = $size;
    }

    /**
     * Paging
     * @param string $url eg. "/page/@number@"
     * @param integer $page page number
     * @param integer $total total number
     * @param integer $size page size
     * @return string
     */
    public function render($url, $page, $total, $size)
    {
        empty($url) && $url = $this->url;
        empty($page) && $page = $this->number;
        empty($total) && $total = $this->total;
        empty($size) && $size = $this->size;

        $max = ceil($total / $size);
        $first = $page > 1;
        $prev = $page > 1;
        $next = $page < $max;
        $last = $page < $max;
        $content = $this->template;

        //eg.[first][prev][6][7][8][9][10][next][end]
        $numbers = array();
        if ($this->middle > 0) {
            $half = floor($this->middle / 2);
            $start = $page - $half;
            if ($start <= 0) {
                $start = 1;
            }
            for ($i = 0; $i < $this->middle; $i++) {
                $numbers[] = $start++;
                if ($start > $max) {
                    break;
                }
            }
            $content = $this->parseFragment($url, $content, self::NUMBER, $numbers, $page);
            $first = $first && !in_array(1, $numbers);
            $last = $last && !in_array($max, $numbers);
        }

        if (!$this->thin) {
            $first = true;
            $prev = $first;
            $next = $prev;
            $last = $next;
        }

        $content = $first ? $this->parseFragment($url, $content, self::FIRST, 1, $page) : self::removeFragment($content, self::FIRST);
        $content = $prev ? $this->parseFragment($url, $content, self::PREV, $page - 1 > 0 ? $page - 1 : 1, $page) : self::removeFragment($content, self::PREV);
        $content = $next ? $this->parseFragment($url, $content, self::NEXT, $page + 1 > $max ? $max : $page + 1, $page) : self::removeFragment($content, self::NEXT);
        $content = $last ? $this->parseFragment($url, $content, self::LAST, $max, $page) : self::removeFragment($content, self::LAST);

        return $content;
    }

    /**
     * Parsing
     * @param string $url
     * @param string $template
     * @param string $tag
     * @param array|int $page
     * @param int $cur
     * @return string
     */
    protected function parseFragment($url, $template, $tag, $page, $cur)
    {
        $fragment = self::fetchFragment($template, $tag);
        if (empty($fragment)) {
            return $template;
        }
        $replace = '';
        if (is_array($page)) {
            foreach ($page as $number) {
                $replace .= $this->fillLink($fragment, $url, $tag, $number, $cur);
            }
        } else {
            $replace .= $this->fillLink($fragment, $url, $tag, $page, $cur);
        }

        return str_replace($fragment, $replace, $template);
    }

    /**
     * Fill Link
     * @param string $fragment
     * @param string $url
     * @param string $tag
     * @param integer $page
     * @param integer $cur
     * @return mixed
     */
    protected function fillLink($fragment, $url, $tag, $page, $cur)
    {
        $url = str_replace(self::NUMBER, $page, $url);
        $text = self::NUMBER == $tag ? $page : $this->links[$tag];
        $replace = $page == $cur ? str_replace(self::NUMBER, $text, $this->current) : "<a href='{$url}'>{$text}</a>";

        return str_replace($tag, $replace, $fragment);
    }

    /**
     * Remove Fragment
     * @param string $template
     * @param string $tag
     * @return string
     */
    protected static function removeFragment($template, $tag)
    {
        $fragment = self::fetchFragment($template, $tag);

        return !empty($fragment) ? str_replace($fragment, '', $template) : $template;
    }

    /**
     * Fetch Fragment
     * @param string $template
     * @param string $tag
     * @return string
     */
    protected static function fetchFragment($template, $tag)
    {
        $matches = array();
        $found = preg_match("/<[^>]+>{$tag}<\/[^>]+>/isU", $template, $matches);
        if (empty($found)) {
            preg_match("/{$tag}/isU", $template, $matches);
        }

        return !empty($matches) ? $matches[0] : '';
    }
}