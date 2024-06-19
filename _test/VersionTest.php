<?php

namespace dokuwiki\plugin\versionswitch\test;

use DokuWikiTest;
use DOMWrap\Document;
use TestUtils;

/**
 * Regex, tree and config handling for versionswitch plugin
 *
 * @group plugin_versionswitch
 * @group plugins
 */
class VersionTest extends DokuWikiTest
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        TestUtils::rcopy(TMP_DIR, __DIR__ . '/data');
    }


    public function provideMatchData()
    {
        return [
            [
                ':en:products    (latest_release|wip|archive:[^:]+)',
                'en:products:latest_release:foo',
                ':en:products',
                'latest_release',
                'foo',
            ],
            [
                'en:products    (latest_release|wip|archive:[^:]+)',
                'en:products:archive:v7.5:foo',
                ':en:products',
                'archive:v7.5',
                'foo',
            ],
            [
                'en:products    (latest_release|wip|archive:[^:]+)',
                'en:products:archive:v7.5:feature:something:foo',
                ':en:products',
                'archive:v7.5',
                'feature:something:foo',
            ],
            [
                ':en:products   (latest_release|wip|archive:[^:]+)',
                'en:products:v7.5:foo',
                '',
                '',
                '',
            ],
        ];
    }


    /**
     * @dataProvider provideMatchData
     */
    public function testMatch($regex, $namespace, $expectedNamespace, $expectedVersion, $expectedIdPart)
    {
        $version = new \dokuwiki\plugin\versionswitch\Version($regex, $namespace);
        $this->assertEquals($expectedNamespace, $version->getBaseNamespace());
        $this->assertEquals($expectedVersion, $version->getVersion());
        $this->assertEquals($expectedIdPart, $version->getIdPart());
    }

    public function testGetVersions()
    {
        $version = new \dokuwiki\plugin\versionswitch\Version(
            ':en:products    (latest_release|wip|archive:[^:]+)',
            'en:products:latest_release:foo'
        );

        $this->assertEquals(
            [
                'wip' => 'Work in Progress',
                'latest_release' => 'Latest Release',
                'archive:v7.5' => 'Version 7.5',
                'archive:v7.15' => 'Version 7.15',
            ],
            $version->getVersions()
        );
    }

    public function testGetVersionsDefaultRegex()
    {
        $version = new \dokuwiki\plugin\versionswitch\Version(
            ':en:products',
            'en:products:latest_release:foo'
        );

        $this->assertEquals(
            [
                'wip' => 'Work in Progress',
                'latest_release' => 'Latest Release',
                'archive' => 'archive',
            ],
            $version->getVersions()
        );
    }

    public function testHTML()
    {
        global $INFO;
        global $conf;

        $conf['plugin']['versionswitch']['regexes'] = ':en:products    (latest_release|wip|archive:[^:]+)';
        $INFO['id'] = 'en:products:latest_release:bar';

        $syntax = new \syntax_plugin_versionswitch();
        $html = $syntax->versionSelector();

        $doc = new Document();
        $doc->html($html);

        $this->assertEquals(4, $doc->find('li')->count());
        $this->assertEquals(2, $doc->find('li.exists')->count());
        $this->assertEquals(2, $doc->find('li.missing')->count());
        $this->assertEquals(1, $doc->find('li.current')->count());
    }

    public function testSorting()
    {
        $input = [
            'archive:v7.5',
            'wip',
            'latest_release',
            'other:3.0',
            'archive:v7.15',
            'other:4.5',
            'other:4.6',
        ];

        $expected = [
            'wip',
            'latest_release',
            'archive:v7.15',
            'archive:v7.5',
            'other:4.6',
            'other:4.5',
            'other:3.0',
        ];

        $version = new \dokuwiki\plugin\versionswitch\Version(
            ':en:products    (latest_release|wip|archive:[^:]+)',
            'en:products:latest_release:foo'
        );

        usort($input, [$version, 'sortByDepthAndVersion']);
        $this->assertEquals($expected, $input);

        // just to be sure, test with shuffled input
        shuffle($input);
        usort($input, [$version, 'sortByDepthAndVersion']);
        $this->assertEquals($expected, $input);
    }


}
