<?php

/**
 * Basic template engine. Support nest and block.
 * @package Muju
 * @todo Add Exception
 * @todo Add parse loop number check, prevent dead loop
 */
class Muju_Template
{
    /**
     * Support segment types
     */
    const SEGMENT_TYPE_SELF = 0;
    const SEGMENT_TYPE_FILE = 1;
    const SEGMENT_TYPE_BLOCK = 2;
    const SEGMENT_TYPE_VAR = 3;

    /**
     * Use to specify the template script path
     */
    const FILE_PATH_BASE = 0;
    const FILE_PATH_THIS = 1;

    /**
     * Define the debug level
     */
    const DEBUG_INFO_VARIABLE = 0;
    const DEBUG_INFO_SEGMENT = 1;
    const DEBUG_INFO_ALL = 2;

    /**
     * Variable to hold segments of template
     *
     * @var array
     */
    protected $_segments;

    /**
     * Variable to hold parsed content of template
     *
     * @var string
     */
    protected $_mainContent;

    /**
     * Variable to hold a table for variables in template
     *
     * @var array
     */
    protected $_varContent = array();

    /**
     * Variable to hold all tags found in template script
     *
     * @var array
     */
    protected $_templateTags = array();

    /**
     * Base path for template files
     *
     * @var string
     */
    protected $_tplBasePath = '';

    /**
     * Share path for template files
     *
     * @var string
     */
    protected $_tplSharePath = '';

    /**
     * Concrete path for template files
     *
     * @var string
     */
    protected $_tplScriptPath = '';

    /**
     * Full name of parsing file, use to control the cache operation.
     *
     * @var string
     */
    protected $_tplScriptName = null;

    /**
     * Set to true is current content fetched from cache.
     *
     * @var boolean
     */
    protected $_fromCache = false;

    /*
     * Template file's suffix
     *
     * @var string
     */
    protected $_tplSuffix = 'phtml';

    /**
     * Template file's encoding
     *
     * @var string
     */
    protected $_encoding = 'UTF-8';

    /**
     * Variable to hold all the assigning value
     *
     * @var array
     */
    protected $_t;

    /**
     * Variable to hold all the assigning value, that no need to cache.
     *
     * @var array
     */
    protected $_c = array();

    /**
     * Object of cache handler.
     *
     * @var Zend_Cache_Core
     */
    protected $_cache = false;


    public function __construct()
    {
        require_once dirname(__FILE__) .'/LIB.php';
    }

    /**
     * Set the encoding for template file
     *
     * @param string $encoding
     * @return void
     */
    public function setEncoding($encoding)
    {
        $this->_encoding = $encoding;
    }

    /**
     * Set the script path for template
     *
     * @param string $path
     * @return void
     */
    public function setScriptPath($path)
    {
        $this->_tplScriptPath= $path;
    }

    /**
     * Set the base path for template
     *
     * @param string $path
     * @return void
     */
    public function setBasePath($path)
    {
        $this->_tplBasePath = $path;
    }

    /**
     * Set the shared file path for template
     *
     * @param string $path
     * @return void
     */
    public function setSharePath($path)
    {
        $this->_tplSharePath = $path;
    }

    /**
     * Set the suffix name for template file
     *
     * @param string $suffix
     * @return void
     */
    public function setSuffix($suffix)
    {
        $this->_tplSuffix = $suffix;
    }

    /**
     * Get the suffix name for template file
     *
     * @return string
     */
    public function getSuffix()
    {
        return $this->_tplSuffix;
    }

    /**
     * Assign value
     *
     * @param string $name name of the variable
     * @param mixed $value
     * @return void
     */
    public function assign($name, $value)
    {
        $this->_t["$name"] = $value;
    }

    /**
     * Get value of variable by name
     *
     * @param string $name
     * @return mixed|null
     */
    public function fetch($name)
    {
        if (array_key_exists($name, $this->_t)) {
            return $this->_t["$name"];
        }
        return null;
    }

    /**
     * Delete a variable from var-list
     *
     * @param string $name
     * @return void
     */
    public function delete($name)
    {
        if (array_key_exists($name, $this->_t)) {
            unset($this->_t["$name"]);
        }
    }

    /**
     * Output debug information
     *
     * @param int $level
     * @return mixed
     *
     */
    public function debug($level = self::DEBUG_INFO_VARIABLE)
    {
        if ($level === self::DEBUG_INFO_VARIABLE) {
            return $this->_t;
        }

        if ($level === self::DEBUG_INFO_SEGMENT) {
            return $this->_segments;
        }

        if ($level === self::DEBUG_INFO_ALL) {
            $this->_t['{allSegments}'] = $this->_segments;
            return $this->_t;
        }
    }

    /**
     * Set the cache handler.
     *
     * @param Zend_Cache_Core $cache
     * @return Muju_Template
     */
    public function setCacheHandler(Zend_Cache_Core $cache)
    {
        $this->_cache = $cache;
        return $this;
    }

    /**
     * Set not to cache the segment (support only file tags)
     *
     * @param string|array $name
     * @return Muju_Template
     */
    public function setNoCache($name)
    {
        if (is_string($name)) {
            $this->_c[$name] = 0;
        }

        if (is_array($name)) {
            foreach ($name as $n) {
                $this->_c[$n] = 0;
            }
        }

        return $this;
    }

    /**
     * Enter description here...
     *
     * @return unknown
     */
    public function getNoCache()
    {
        return array_keys($this->_c);
    }

    /**
     * Set a identifier for resource to be cached
     *
     * @param string $id
     * @return Muju_Template
     */
    public function setCacheId($id)
    {
        if (is_string($id)) {
            $this->_cacheId = $id;
            $this->_keyFile = md5($this->getCacheId() . '`!@#file$%^&');
            $this->_keySegment = md5($this->getCacheId() . '`!@#segment$%^&');
            return $this;
        }

        throw new Muju_View_Exception('Supplied cache id is not a string!');
    }

    /**
     * Get cache identifier
     *
     * @return mixed
     */
    public function getCacheId()
    {
        return $this->_cacheId;
    }

    /**
     *
     *
     * @return boolean
     */

    /**
     * Determine the parsing file is cached or not.
     *
     * @FIXME finish the trigger part
     * @param string $id identifier of object to be cached
     * @param string|null $triggerKey key of trigger
     * @param string|null $triggerValue value of trigger
     * @return boolean
     */
    public function isCached($id, $triggerKey = null, $triggerValue = null)
    {
        if (is_null($this->_keyFile) || is_null($this->_keySegment)) {
            $this->setCacheId($id);
        }

        /**
            if (!is_null($triggerKey)) {
            // key of trigger supplied, but not exit in cache
            $triggerKey = md5($id . '`!@#trigger$%^&' . $triggerKey);
            if ($this->_cache->test($triggerKey)) {
                if (!is_null($triggerValue)) {
                    $trigger = $this->_cache->load($triggerKey) == $triggerValue;
                    if (!$trigger) {
                        $this->_cache->save($triggerValue, $triggerKey);
                        return false;
                    }
                }
            }
        }*/

        return $this->_cache->test($this->_keyFile) && $this->_cache->test($this->_keySegment);
    }

    /**
     * Get pre parsed file from cache, wrap the cache operation transparently.
     *
     * @param string $filePath path to the file
     * @param string $fileContent hold the original content
     * @return array pre-parsed segments
     */
    protected function _cachePreParse($filePath, & $fileContent)
    {
        if ($this->isCached($this->getCacheId())) {
            $fileContent = $this->_cache->load($this->_keyFile);
            $segments = $this->_cache->load($this->_keySegment);
            $this->_fromCache = true;
            return $segments;
        } else {
            $segments = $this->_preParse($filePath, $fileContent);
            $this->_fromCache = false;
            return $segments;
        }
    }

    /**
     * Do post parse, store parsed content & 'noCache' segments in cache;
     *
     * @param string $filePath path to the file
     * @param array segments of parsed file
     * @param string $fileContent hold the original content
     * @return array pre-parsed segments
     */
    protected function _cachePostParse($filePath, $segments, & $fileContent)
    {
        if ($this->_cache->save($fileContent, $this->_keyFile)) {
            $notCachedTags = $this->getNoCache();
            if (count($notCachedTags) > 0) {
                foreach ($segments as $key => $segment) {
                    if ($segment['type'] !== self::SEGMENT_TYPE_FILE
                    || !in_array($segment['name'], $notCachedTags)) {
                        unset($segments[$key]);
                    }
                }
            }
            $this->_cache->save($segments, $this->_keySegment);
        }
    }

    /**
     * Parse a template file
     *
     * @param string $file path to the file
     * @return string parsed content
     */
    public function parse($file)
    {
        // invoke _cachePreParse() only when parsing main template file.
        if ($this->_cache && $this->_tplScriptName === null) {
            $this->_tplScriptName = $file;
            $segments = $this->_cachePreParse($file, $mainContent);
        } else {
            $segments = $this->_preParse($file, $mainContent);
        }

        if (is_array($segments)) {
            foreach ($segments as $segment) {
                if ($segment['type'] === self::SEGMENT_TYPE_FILE) {
                    if ($segment['path'] === self::FILE_PATH_BASE) {
                        $fileToParse = $this->_tplBasePath
                        . $segment['name'] . '.' . $this->_tplSuffix;
                        if (!is_readable($fileToParse)) {
                            $fileToParse = $this->_tplSharePath
                            . $segment['name'] . '.' . $this->_tplSuffix;
                        }
                    } else {
                        $fileToParse = $this->_tplBasePath . $this->_tplScriptPath
                        . $segment['name'] . '.' . $this->_tplSuffix;
                    }
                    if (!in_array($segment['name'], $this->getNoCache()) || $this->_fromCache) {
                        $mainContent = str_replace($segment['real_name'], $this->parse($fileToParse), $mainContent);
                    }
                } elseif ($segment['type'] === self::SEGMENT_TYPE_VAR) {
                    $this->_varParse($segment);
                } elseif ($segment['type'] === self::SEGMENT_TYPE_BLOCK) {
                    $replace = array_key_exists($segment['name'], $this->_t) ? $this->_t[$segment['name']] : null;
                    $mainContent = $this->_blockParse($segment, $replace, $mainContent);
                }
            }
            $mainContent = $this->_postParse($mainContent);
        }

        // cache the content if not fetched from cache
        if ($this->_cache && $this->_tplScriptName == $file && !$this->_fromCache) {
            $this->_cachePostParse($this->_tplScriptName, $segments, $mainContent);
            $this->_tplScriptName = null;
            $this->_fromCache = true;
            $mainContent = $this->parse($file);
        }

        return $mainContent;
    }

    /**
     * Parse a variable
     *
     * @param string $segment name of variable
     * @return void
     */
    private function _varParse($segment)
    {
        if (isset($this->_t[$segment['name']]) && !is_null($this->_t[$segment['name']])) {
            if (!array_key_exists($segment['real_name'], $this->_varContent)) {
                $func  = isset($segment['function']) ? $segment['function'] : NULL;
                $this->_varContent[$segment['real_name']] = $this->_runCallback($func,$this->_t[$segment['name']]);
            }
        }
        $this->_saveTags($segment['real_name']);
    }

    /**
     * Pre parse a template file
     *
     * @param string $name path to the file
     * @param string $file hold the original content
     * @return array pre-parsed segments
     */
    private function _preParse($name, & $file)
    {
        $file = file_get_contents($name, true);

        preg_match_all('/{[$_<>\[\]a-zA-Z0-9]+}/', $file, $j);

        if (is_array($j[0]) && !empty($j[0])) {
            for ($i = 0 ; $i < count($j[0]) ; $i++) {
                if (preg_match('/^{[a-z]+}$/', $j[0][$i])) {
                    $segments[$i]['name'] = preg_replace('/(^{)|(}$)/','',$j[0][$i]);
                    $segments[$i]['type'] = self::SEGMENT_TYPE_FILE;
                    $segments[$i]['path'] = self::FILE_PATH_BASE;
                    $segments[$i]['real_name'] = $j[0][$i];
                }

                if (preg_match('/^{_[a-z]+}$/', $j[0][$i])) {
                    $segments[$i]['name'] = preg_replace('/(^{)|(}$)/','',$j[0][$i]);
                    $segments[$i]['type'] = self::SEGMENT_TYPE_FILE;
                    $segments[$i]['path'] = self::FILE_PATH_THIS;
                    $segments[$i]['real_name'] = $j[0][$i];
                }

                if (preg_match('/^{\[_[a-zA-Z0-9]+\]}$/', $j[0][$i])) {
                    $segments[$i] = $this->_preParseBlock($j[0][$i], $j[0], $i);
                }

                if (preg_match('/^{\$(\<\w+\>)?([a-zA-Z0-9]+)}$/', $j[0][$i],$match)) {
                    $segments[$i]['name'] = $match[2];
                    $segments[$i]['type'] = self::SEGMENT_TYPE_VAR;
                    $segments[$i]['real_name'] = $j[0][$i];
                    $segments[$i]['function'] = $match[1];
                }
            }
            return $segments;
        }
    }

    /**
     * @return Function
     */
    private function _runCallback($func=NULL,$text=NULL)
    {
        $func = trim($func,'<>');
        return is_callable($func) ? $func($text) : $text;

    }

    /**
     * Pre-parse a block
     *
     * @param string $openTag name of open tag
     * @param string $segments content of segment
     * @param string|int $offset
     * @return array block information
     */
    private function _preParseBlock($openTag, $segments, & $offset)
    {
        $tmp['name'] = preg_replace('/(^{\[_)|(\]}$)/','',$openTag);
        $closeTag = '{[' . $tmp['name'] . '_]}';
        $tmp['open'] = $openTag;
        $tmp['close'] = $closeTag;
        $tmp['type'] = self::SEGMENT_TYPE_BLOCK;
        $segmentsTmp = array_slice($segments, $offset, count($segments), true);

        for ($i = $offset+1; $i < array_search($closeTag, $segmentsTmp); $i++) {
            if (preg_match('/^{\$(\<\w+\>)?([a-zA-Z0-9]+)}$/', $segments[$i], $match)) {

                $tmp['keys'][] = array(
            	   preg_replace('/(^{\$)|(}$)/','',$segments[$i]),//var key
            	   $match[2],//var name
            	   $match[1],//function name
            	   );
            }

            if (preg_match('/^{\$_(\<\w+\>)?([a-zA-Z0-9]+)}$/', $segments[$i],$match)) {
                $var['real_name'] = $segments[$i];
                $var['name'] = $match[2];
                $var['function'] = isset($match[1]) ? $match[1] : NULL;
                $this->_varParse($var);
            }

            if (preg_match('/^{\[_[a-zA-Z]+\]}$/', $segments[$i])) {
                $tmp['child'][] = $this->_preParseBlock($segments[$i], $segments, $i);
            }
        }
        $offset = $i;
        return $tmp;
    }

    /**
     * Save tag in one place, use them later
     *
     * @param string $tag
     * @return void
     */
    private function _saveTags($tag)
    {
        if (!in_array($tag, $this->_templateTags)) {
            $this->_templateTags[] = $tag;
        }
    }

    /**
     * Parse a block
     *
     * @param array $pattern
     * @param array $replace
     * @param string $subject
     * @return string
     */
    private function _blockParse($pattern, $replace, & $subject)
    {
        $open = strpos($subject, $pattern['open']);
        $close = strpos($subject, $pattern['close']);
        $tagLen = strlen($pattern['open']);
        $blockPatternWithoutTag = substr($subject, $open + $tagLen, $close - $open - $tagLen);
        $replacement = '';

        if (is_array($replace)) {
            foreach ($replace as $block) {
                $tmp = $blockPatternWithoutTag;
                if (array_key_exists('child', $pattern)) {
                    $subjectTmp = $subject;
                    foreach ($pattern['child'] as $childPattern) {
                        $childReplace = '';
                        if (array_key_exists($childPattern['name'], $block)) {
                            $childReplace = $block[$childPattern['name']];
                        } elseif (array_key_exists($childPattern['name'], $this->_t)) {
                            $childReplace = $this->_t[$childPattern['name']];
                        }

                        $tmp = $this->_blockParse($childPattern, $childReplace, $subjectTmp);
                        $subjectTmp = $tmp;
                    }
                    $open = strpos($tmp, $pattern['open']);
                    $close = strpos($tmp, $pattern['close']);
                    $tmp = substr($tmp, $open + $tagLen, $close - $open - $tagLen);
                }

                if (isset($pattern['keys']) && is_array($pattern['keys'])) {
                    foreach ($pattern['keys'] as $item) {
                        list($key,$name,$func) = $item;
                        if (is_array($block) && array_key_exists($name, $block)) {
                            //                            $tmp = $this->_replace('{$' . $key . '}',$this->_runCallback($func ,$block[$name]), $tmp);
                            $tmp = $this->_replace('{$' . $key . '}', $block[$name], $tmp);
                        } else {
                            $tmp = str_replace('{$' . $key . '}', '', $tmp);
                        }
                    }
                }
                $replacement .= $tmp;
            }
        }
        $this->_saveTags($pattern['open']);
        $this->_saveTags($pattern['close']);
        $open = strpos($subject, $pattern['open']);
        $close = strpos($subject, $pattern['close']);
        return substr_replace($subject, $replacement, $open, $close - $open + $tagLen);
    }

    /**
     * Post parse prepared segments from one file, remove unassigned.
     *
     * @param string $content content need to post parse
     * @return string
     */
    private function _postParse($content)
    {
        foreach ($this->_varContent as $tag => $val) {//parsing var
            $content = $this->_replace($tag, $val, $content);
        }

        foreach ($this->_templateTags as $tag) {//replace not assigned
            $content = str_replace($tag, '', $content);
        }
        return $content;
    }

    /**
     * Replace placeholder/tags with actual value
     *
     * @param string $search
     * @param string $replace
     * @param string $subject
     * @return string
     */
    private function _replace($search, $replace, $subject)
    {
        //return str_replace($search, $replace, $subject);
        return str_replace($search, htmlspecialchars($replace, ENT_COMPAT, $this->_encoding), $subject);
    }
}
?>