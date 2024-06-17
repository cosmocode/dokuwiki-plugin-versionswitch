<?php

namespace dokuwiki\plugin\versionswitch;

use dokuwiki\File\PageResolver;

/**
 * Manage the current page's version
 */
class Version
{
    protected $regex = '';
    protected $version = '';
    protected $namespace = '';
    protected $idpart = '';

    public const DEFAULT_REGEX = '[^:]+';

    /**
     * @param string $conf The configuration string containing the namespaces and regexes
     * @param string $id The current page id
     */
    public function __construct($conf, $id)
    {
        $this->match($this->conf2List($conf), $id);
    }

    /**
     * The regex that applies to the current namespace
     *
     * This will be empty if the current page is not in a versioned namespace
     *
     * @return string
     */
    public function getRegex(): string
    {
        return $this->regex;
    }

    /**
     * The base namespace that applies to the current page
     *
     * This will be empty if the current page is not in a versioned namespace
     *
     * @return string
     */
    public function getBaseNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * The version that applies to the current page
     *
     * This will be empty if the current page is not in a versioned namespace
     *
     * @return string
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * The part of the id that comes after the version namespace
     *
     * @return string
     */
    public function getIdPart(): string
    {
        return $this->idpart;
    }


    /**
     * Convert the list of namespaces and regexes into an associative array
     *
     * @param string $conf
     * @return array
     */
    protected function conf2List($conf)
    {
        $result = [];
        $list = explode("\n", $conf);
        foreach ($list as $line) {
            $line = trim($line);
            if ($line == '') {
                continue;
            }
            if ($line[0] == '#') {
                continue;
            }
            [$ns, $re] = sexplode(' ', $line, 2, '');
            $ns = ':' . cleanID($ns);
            $re = trim($re);
            if ($re === '') $re = self::DEFAULT_REGEX; // default is direct namespaces

            $result[$ns] = $re;
        }

        return $result;
    }

    /**
     * Try the given regexes against the namespace of the given id
     *
     * @param array $regexes
     * @param string $id
     * @return bool true if a match was found
     */
    protected function match($regexes, $id)
    {
        $namespace = ':' . getNS($id);

        foreach ($regexes as $base => $re) {
            $regex = "/$re/i";

            // match against base namespace first
            if (str_starts_with($namespace, $base)) {
                $namespace = substr($namespace, strlen($base));

                // match remainder against regex
                if (preg_match($regex, $namespace, $matches)) {
                    $this->namespace = $base;
                    $this->regex = $re;
                    $this->version = $matches[0];
                    $this->idpart = substr($id, strlen($base) + strlen($matches[0]) + 1);

                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get all versions and their titles
     *
     * @return array
     */
    public function getVersions()
    {
        $versions = $this->readVersionDirs();

        // get titles for the versions
        $resolver = new PageResolver('start'); // context doesn't matter, we always use absolute IDs
        foreach (array_keys($versions) as $ns) {
            $startPage = $resolver->resolveId($this->namespace . ':' . $ns . ':');
            $title = p_get_first_heading($startPage,);
            if ($title) $versions[$ns] = $title;
        }

        // sort, first by version, then by depth
        uasort($versions, 'version_compare');
        uksort($versions, [$this, 'sortByDepth']);

        return $versions;
    }

    /**
     * Sort by depth of the namespace
     *
     * @param string $a
     * @param string $b
     * @return int
     */
    protected function sortByDepth($a, $b)
    {
        $countA = substr_count($a, ':');
        $countB = substr_count($b, ':');
        if ($countA !== $countB) return $countA - $countB;
        return 0;
    }

    /**
     * Traverse the version directories and find all versions
     *
     * @param string $dir The base directory to start in, defaults to the set namespace
     * @param string $sub The currently traversed sub directory
     * @return array
     */
    protected function readVersionDirs($dir = '', $sub = '')
    {
        if ($dir === '') $dir = dirname(wikiFN($this->namespace . ':somthing', '', false));
        $subns = utf8_decodeFN($sub);
        $regex = '/^' . $this->regex . '$/i'; // anchored regex
        $versions = [];

        $fh = @opendir($dir . '/' . $sub);
        if (!$fh) return [];
        while (($item = readdir($fh)) !== false) {
            if ($item[0] == '.') continue;
            if (!is_dir($dir . '/' . $sub . '/' . $item)) continue;

            $itemid = utf8_decodeFN($item);

            // check if this is a version namespace
            if (preg_match($regex, ltrim("$subns:$itemid", ':'), $match)) {
                $versions[ltrim("$subns:$itemid", ':')] = $match[0];
            }

            // traverse into sub namespace unless default regex is used
            if ($this->regex !== self::DEFAULT_REGEX) {
                $versions = array_merge($versions, $this->readVersionDirs($dir, ltrim("$sub/$item", '/')));
            }
        }
        closedir($fh);
        return $versions;
    }
}
