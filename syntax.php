<?php

use dokuwiki\Extension\SyntaxPlugin;

/**
 * DokuWiki Plugin versionswitch (Syntax Component)
 *
 * @license GPL 2 http://www.gnu.org/licenses/gpl-2.0.html
 * @author Andreas Gohr <dokuwiki@cosmocode.de>
 */
class syntax_plugin_versionswitch extends SyntaxPlugin
{
    /** @inheritDoc */
    public function getType()
    {
        return 'substition';
    }

    /** @inheritDoc */
    public function getPType()
    {
        return 'normal';
    }

    /** @inheritDoc */
    public function getSort()
    {
        return 255;
    }

    /** @inheritDoc */
    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('~~VERSIONSWITCH~~', $mode, 'plugin_versionswitch');
    }


    /** @inheritDoc */
    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        return [];
    }

    /** @inheritDoc */
    public function render($mode, Doku_Renderer $renderer, $data)
    {
        if ($mode !== 'xhtml') {
            return false;
        }

        $renderer->doc .= $this->versionSelector();
        return true;
    }

    /**
     * Render the version selector HTML
     *
     * @return string
     */
    public function versionSelector()
    {
        global $INFO;
        $version = new \dokuwiki\plugin\versionswitch\Version($this->getConf('regexes'), $INFO['id']);
        $base = $version->getBaseNamespace();
        if ($base === '') return '';
        $current = $version->getVersion();

        $doc = '';
        $doc .= '<ul class="plugin_versionswitch">';
        foreach ($version->getVersions() as $ns => $title) {
            $id = $base . ':' . $ns . ':' . $version->getIdPart();
            if(auth_quickaclcheck($id) < AUTH_READ) continue; // skip if user can't read the target

            $classes = [];
            if ($ns === $current) $classes[] = 'current';
            $classes[] = page_exists($id) ? 'exists' : 'missing';


            $doc .= sprintf('<li class="%s">', join(' ', $classes));
            $doc .= html_wikilink($id, $title);
            $doc .= '</li>';
        }

        $doc .= '</ul>';

        return $doc;
    }
}
