<?php
/**
 * Created by PhpStorm.
 * User: ari
 * Date: 1/27/2015
 * Time: 1:56 PM
 */
namespace Site;

use CPath\Build\IBuildable;
use CPath\Build\IBuildRequest;
use CPath\Render\HTML\Attribute\Attributes;
use CPath\Render\HTML\Attribute\StyleAttributes;
use CPath\Render\HTML\Element\Form\HTMLForm;
use CPath\Render\HTML\Element\HTMLElement;
use CPath\Render\HTML\Element\Table\HTMLPDOQueryTable;
use CPath\Render\HTML\Header\HTMLMetaTag;
use CPath\Request\Executable\IExecutable;
use CPath\Request\IRequest;
use CPath\Response\IResponse;
use CPath\Route\IRoutable;
use CPath\Route\RouteBuilder;
use Site\Request\HTML\HTMLRequestHistory;
use Site\Song\DB\SongEntry;
use Site\Song\DB\SongTable;
use Site\Song\HTML\HTMLSongsTable;

class SiteIndex implements IExecutable, IBuildable, IRoutable
{
	const TITLE = 'Site Index';

	const FORM_METHOD = 'POST';
	const FORM_NAME = __CLASS__;

	/**
	 * Execute a command and return a response. Does not render
	 * @param IRequest $Request
	 * @return IResponse the execution response
	 */
	function execute(IRequest $Request) {
        $Query = SongEntry::query()
//            ->where(SongTable::COLUMN_STATUS, SongEntry::STATUS_PUBLISHED, '&?')
            ->orderBy(SongTable::COLUMN_CREATED, 'DESC');

        $SongsTable = new HTMLPDOQueryTable($Query);

        $SongsTable->addColumn(SongTable::COLUMN_TITLE);
        $SongsTable->addColumn(SongTable::COLUMN_DESCRIPTION);
        $SongsTable->addColumn('artist');
        $SongsTable->addColumn('chip-style');
//        $SongsTable->addColumn(SongTable::COLUMN_CREATED);
//        $SongsTable->addColumn(SongTable::COLUMN_STATUS);

		$Form = new HTMLForm(self::FORM_METHOD, $Request->getPath(), self::FORM_NAME,
			new HTMLMetaTag(HTMLMetaTag::META_TITLE, self::TITLE),
//			new HTMLHeaderScript(__DIR__ . '\assets\form-login.js'),
//			new HTMLHeaderStyleSheet(__DIR__ . '\assets\form-login.css'),
// http://snesology.tumblr.com/rss
			new HTMLElement('fieldset', 'legend-page',
                new StyleAttributes('display', 'inline-block'),
                new HTMLElement('legend', 'legend-page', self::TITLE),

                "Beta Test Coming Soon"
			),

            "<br/>",
            new HTMLElement('fieldset', 'fieldset-published-songs',
                new StyleAttributes('display', 'inline-block'),
                new HTMLElement('legend', 'legend-published-songs', "Recent Published Songs"),

                $SongsTable
            ),

            new HTMLElement('fieldset', 'fieldset-contribution-history',
                new StyleAttributes('display', 'inline-block'),
                new HTMLElement('legend', 'legend-contribution-history', "Contribution History"),

                new HTMLRequestHistory()
            )

//            new HTMLElement('fieldset', 'fieldset-songs inline',
//                new HTMLElement('legend', 'legend-songs', "Recent Songs"),
//
//                new HTMLSongsTable(20, true)
//            )

        );

		return $Form;
	}

	// Static

	/**
	 * Route the request to this class object and return the object
	 * @param IRequest $Request the IRequest inst for this render
	 * @param array|null $Previous all previous response object that were passed from a handler, if any
	 * @param null|mixed $_arg [varargs] passed by route map
	 * @return void|bool|Object returns a response object
	 * If nothing is returned (or bool[true]), it is assumed that rendering has occurred and the request ends
	 * If false is returned, this static handler will be called again if another handler returns an object
	 * If an object is returned, it is passed along to the next handler
	 */
	static function routeRequestStatic(IRequest $Request, Array &$Previous = array(), $_arg = null) {
		return new static();
	}

	/**
	 * Handle this request and render any content
	 * @param IBuildRequest $Request the build request inst for this build session
	 * @return void
	 * @build --disable 0
	 * Note: Use doctag 'build' with '--disable 1' to have this IBuildable class skipped during a build
	 */
	static function handleBuildStatic(IBuildRequest $Request) {
		$RouteBuilder = new RouteBuilder($Request, new SiteMap());
		$RouteBuilder->writeRoute('ANY /', __CLASS__);
	}
}